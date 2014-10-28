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
    protected $bestSolutionViolations = -1;

    protected $bestSoFar = [];
    protected $bestSoFarTotal = -1;
    protected $bestSoFarViolations = -1;

    protected $attemptsPerNode = 3;
    protected $attempted = [];

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
            echo $line . "\n";
            $values = explode(',', $line);

            $id1 = $values[0];
            $id2 = $values[1];

            $this->branches[$id1]->addNeighbor($this->branches[$id2]);

            $this->branches[$id2]->addNeighbor($this->branches[$id1]);

            if (!array_key_exists($id1, $this->adjacencyTable)) {
                $this->adjacencyTable[$id1] = [];
            }

            $this->adjacencyTable[$id1][$id2] = true;
        }

        print_r($this->adjacencyTable);
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
        foreach ($this->adjacencyTable as $id1 => $ids) {
            foreach ($ids as $id2 => $value) {
                $this->cutBranches[$id1]->addNeighbor($this->cutBranches[$id2]);

                $this->cutBranches[$id2]->addNeighbor($this->cutBranches[$id1]);
            }
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
    }

    public function getTotalVolume()
    {
        $totalVolume = 0;

        $totalTp1 = 0;
        $totalTp2 = 0;
        $totalTp3 = 0;

        foreach ($this->cutBranches as $cutBranch) {
            switch ($cutBranch->getTimePeriod()) {
                case 1:
                    $totalTp1 += $cutBranch->getTotal();
                    break;
                case 2:
                    $totalTp2 += $cutBranch->getTotal();
                    break;
                case 3:
                    $totalTp3 += $cutBranch->getTotal();
                    break;
            }
        }

        $totalTp1 = pow($totalTp1 - 34467, 2);
        $totalTp2 = pow($totalTp2 - 34467, 2);
        $totalTp3 = pow($totalTp3 - 34467, 2);

        $totalVolume = $totalTp1 + $totalTp2 + $totalTp3;

        return $totalVolume;
    }

    public function renderGraph($soFar = false)
    {
        $graph = new Digraph('G');

        $collection = $this->cutBranches;

        if ($soFar) {
            $collection = $this->bestSoFar;
        }

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
                default:
                    $color = 'yellow';
                    break;
            }

            $graph->node($cutBranch->getId(), ['color' => $color, 'shape' => 'circle']);
        }

        foreach ($this->adjacencyTable as $id1 => $ids) {
            foreach ($ids as $id2 => $value) {
                $graph->edge([$id1, $id2]);
            }
        }

        $gv = $graph->render();

        $dot = new Process('dot -Tpng -o test.png');
        //$dot = new Process('dot');

        if ($soFar) {
            $dot = new Process('dot -Tpng -o sofar.png');
        }

        $dot->setInput($gv);

        $dot->run();

        echo $dot->getOutput();
    }

    public function process($iterations, $fixIterations, $pickIterations = 1)
    {
        for ($i = 0; $i < $iterations; $i++) {
            // Try to fix leafs first
            foreach ($this->getViolations(null) as $violation) {
                $violation->pickBestIfLeaf();
            }

            for ($j = 0; $j < $pickIterations; $j++) {
                try {
                    $this->processRandomUnit($fixIterations);
                } catch (Exception $e) {

                }
            }

            // Try to fix leafs first
            foreach ($this->getViolations(null) as $violation) {
                $violation->pickBestIfLeaf();
            }

            if (count($this->getViolations()) == 0) {
                $currentTotal = $this->getTotalVolume();

                if ($this->bestSolutionTotal == -1 || $currentTotal < $this->bestSolutionTotal) {
                    foreach ($this->cutBranches as $key => $cutBranch) {
                        $this->bestSolution[$key] = clone $cutBranch;
                    }

                    $this->bestSolutionTotal = $currentTotal;
                    $this->bestSolutionViolations = count($this->getViolations(null));

                    echo 'BEST: ' . $currentTotal. "\n";
                } else {
                    // Revert
                    foreach ($this->bestSolution as $key => $cutBranch) {
                        $this->cutBranches[$key] = clone $cutBranch;
                    }
                }
            } else {
                $currentTotal = $this->getTotalVolume();

                // If best so far keep
                if ($this->bestSoFarTotal == -1 || $this->bestSoFarTotal > $currentTotal) {
                    foreach ($this->cutBranches as $key => $cutBranch) {
                        $this->bestSoFar[$key] = clone $cutBranch;
                    }

                    $this->bestSoFarTotal = $currentTotal;
                }

                // Revert
                foreach ($this->bestSolution as $key => $cutBranch) {
                    $this->cutBranches[$key] = clone $cutBranch;
                }
            }

            echo 'CURRENT: ' . $this->getTotalVolume() . "\n";
            echo 'Violations: ' . count($this->getViolations(null)) . "\n";
            echo 'BEST SO FAR: ' . $this->bestSoFarTotal. "\n";

            if ($i % 20 == 0) {
                $this->renderGraph(true);
            }
        }

        echo 'Best: ' . $this->bestSolutionTotal . "\n";
        echo 'Violations: ' . $this->bestSolutionViolations. "\n";
    }

    public function pickUnitA()
    {
        $violations = array_values($this->getViolations());

        if (count($violations) == count($this->attempted)) {
            echo 'Exhausted' . "\n";
        }

        // Bias towards violations
        if (count($violations) > 0 && count($violations) == count($this->attempted) && false) {
            $unitAIndex = rand(0, (count($violations) - 1));

            $unitA = $violations[$unitAIndex];

            if (array_key_exists($unitA->getId(), $this->attempted)) {
                $this->attempted[$unitA->getId()] += 1;
            } else {
                $this->attempted[$unitA->getId()] = 1;
            }

            return $unitA;
        }

        if (count($violations) == 0) {
            return -1;
        }


        // Introduce a random change somewhere
        $unitAIndex = rand(1, (count($this->cutBranches)));

        //$this->cutBranches[$unitAIndex]->setRandomTimePeriod();
        $this->cutBranches[$unitAIndex]->pickBest();

        return $this->cutBranches[$unitAIndex];
    }

    public function processRandomUnit($fixIterations = 1)
    {
        // Pick a random unit A
        $unitA = $this->pickUnitA();

        if ($unitA === -1) {
            echo 'No more violations: ' . $this->getTotalVolume() . "\n";
            return;
        }

        //echo 'Picked: ' . $unitA->getId() . "\n";
        //echo 'Volume before: ' . $this->getTotalVolume() . "\n";

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

    protected function getViolations(Branch $except = null)
    {
        $violations = [];

        foreach ($this->cutBranches as $cutBranch) {
            if (!is_null($except) && $cutBranch == $except) {
                continue;
            }

            if (!$cutBranch->isValid()) {
                $violations[$cutBranch->getId()] = $cutBranch;
            }
        }

        return $violations;
    }
} 