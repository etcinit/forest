<?php

namespace Chromabits\Forest;

use Alom\Graphviz\Digraph;
use Chromabits\Forest\Entities\Branch;
use Chromabits\Forest\Entities\CutBranch;
use Exception;
use Symfony\Component\Process\Process;

class Runner
{
    /**
     * @var Branch[]
     */
    protected $branches = [];

    /**
     * @var CutBranch[]
     */
    protected $cutBranches = [];

    protected $adjacencyTable = [];

    protected $bestSolution = [];
    protected $bestSolutionTotal = -1;

    public function parseInput($volumesFilename, $adFilename)
    {
        $volumesContent = file($volumesFilename, FILE_USE_INCLUDE_PATH | FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $adjacencyContent = file($adFilename, FILE_USE_INCLUDE_PATH | FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $this->parseBranches($volumesContent);

        $this->parseAdjacencyTable($adjacencyContent);
    }

    protected function parseBranches(array $volumesContent)
    {
        foreach ($volumesContent as $line) {
            $values = explode(',', $line);

            $branch = new Branch(
                $values[0],
                $values[1],
                $values[2],
                $values[3],
                $values[4]
            );

            $id = $branch->getId();

            $this->branches[$id] = $branch;
        }
    }

    protected function parseAdjacencyTable(array $adjacencyContent)
    {
        foreach ($adjacencyContent as $line) {
            $values = explode(',', $line);

            $id1 = $values[0];
            $id2 = $values[1];

            $this->branches[$id1]->addNeighbor($this->branches[$id2]);

            $this->branches[$id2]->addNeighbor($this->branches[$id1]);

            $this->adjacencyTable[$id1] = $id2;
        }
    }

    public function randomizedInit()
    {
        // Create cutBranches with random time period
        foreach ($this->branches as $branch) {
            $id = $branch->getId();

            $this->cutBranches[$id] = CutBranch::makeFromBranch($branch);

            $this->cutBranches[$id]->setTimePeriod((int)rand(1,3));
        }

        // Recreate relationships
        foreach ($this->adjacencyTable as $id1 => $id2) {
            $this->cutBranches[$id1]->addNeighbor($this->cutBranches[$id2]);

            $this->cutBranches[$id2]->addNeighbor($this->cutBranches[$id1]);
        }
    }

    public function printConstraintStatus()
    {
        $totalVolume = 0;

        foreach ($this->cutBranches as $cutBranch) {
            if ($cutBranch->isValid()) {
                echo '[' . $cutBranch->getId() . '] ' . '✓' . "\n";
            } else {
                echo '[' . $cutBranch->getId() . '] ' . '✘' . "\n";
            }

            $totalVolume += $cutBranch->getTotal();
        }

        echo 'TOTAL: ' . $totalVolume;
    }

    public function getTotalVolume()
    {
        $totalVolume = 0;

        foreach ($this->cutBranches as $cutBranch) {
            $totalVolume += $cutBranch->getTotal();
        }

        return $totalVolume;
    }

    public function renderGraph()
    {
        $graph = new Digraph('G');

        foreach ($this->cutBranches as $cutBranch) {
            switch ($cutBranch->getTimePeriod()) {
                case 1:
                    $color = 'blue';
                    break;
                case 2:
                    $color = 'red';
                    break;
                case 3:
                    $color = 'green';
                    break;
            }

            $graph->node($cutBranch->getId(), ['color' => $color, 'shape' => 'circle']);
        }

        foreach ($this->adjacencyTable as $id1 => $id2) {
            $graph->edge([$id1, $id2]);
        }

        $gv = $graph->render();

        $dot = new Process('dot -Tpng -o test.png');

        $dot->setInput($gv);

        $dot->run();

        echo $dot->getOutput();
    }

    public function process($iterations, $fixIterations)
    {
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $this->processRandomUnit($fixIterations);
            } catch (Exception $e) {

            }

            $currentTotal = $this->getTotalVolume();

            if ($this->bestSolutionTotal == -1 || $currentTotal > $this->bestSolutionTotal) {
                foreach ($this->cutBranches as $key => $cutBranch) {
                    $this->bestSolution[$key] = clone $cutBranch;
                }

                $this->bestSolutionTotal = $currentTotal;
            } else {
                // Revert
                foreach ($this->bestSolution as $key => $cutBranch) {
                    $this->cutBranches[$key] = clone $cutBranch;
                }
            }
        }

        echo 'BEST: ' . $this->getTotalVolume() . "\n";
    }

    public function processRandomUnit($fixIterations = 1)
    {
        // Pick a random unit A
        $unitAIndex = rand(1, (count($this->cutBranches)));

        $unitA = $this->cutBranches[$unitAIndex];

        echo 'Picked: ' . $unitAIndex . "\n";
        echo 'Volume before: ' . $this->getTotalVolume() . "\n";

        // Get violations
        $violations = $this->getViolations($unitA);

        for ($i = 0; $i < $fixIterations; $i++) {
            $violations = $this->fixOneViolation($unitA, $violations);
        }

        echo 'Volume after: ' . $this->getTotalVolume() . "\n";
    }

    protected function fixOneViolation(CutBranch $unitA, array $violations)
    {
        $closestViolation = -1;
        $unitB = null;

        foreach ($violations as $violation) {
            try {
                $distance = $unitA->distanceTo(null, $violation);

                if ($closestViolation == -1 || $distance < $closestViolation) {
                    $closestViolation = $distance;

                    $unitB = $violation;
                }
            } catch (Exception $e) {

            }
        }

        if ($closestViolation != -1) {
            //echo 'Closest: ' . $unitB->getId() . "\n";
            //echo 'N: ' . count($unitA->getNeighbors()) . "\n";
            //echo 'Before: ' . $unitB->getTimePeriod() . "\n";
            $unitB->pickSafeTimePeriod($unitA);

            $this->cutBranches[$unitB->getId()] = $unitB;
            //echo 'After: ' . $unitB->getTimePeriod() . "\n";

            unset($violations[$unitB->getId()]);

            $violations = array_filter($violations);

            return $violations;
        }

        throw new Exception('Out of ideas');
    }

    protected function getViolations(Branch $except)
    {
        $violations = [];

        foreach ($this->cutBranches as $cutBranch) {
            if ($cutBranch == $except) {
                continue;
            }

            if (!$cutBranch->isValid()) {
                $violations[$cutBranch->getId()] = $cutBranch;
            }
        }

        return $violations;
    }
} 