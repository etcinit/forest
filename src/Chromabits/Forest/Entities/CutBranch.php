<?php

namespace Chromabits\Forest\Entities;

use Chromabits\Forest\Support\PathStack;
use Exception;

class CutBranch extends Branch
{
    /**
     * @var int
     */
    protected $timePeriod;

    /**
     * @return int
     */
    public function getTimePeriod()
    {
        return $this->timePeriod;
    }

    /**
     * @param int $timePeriod
     * @throws Exception
     */
    public function setTimePeriod($timePeriod)
    {
        if ($timePeriod > 3 || $timePeriod < 1) {
            throw new Exception('Invalid time period: ' . $timePeriod);
        }

        $this->timePeriod = $timePeriod;
    }

    /**
     * @throws Exception
     * @return float
     */
    public function getTotal()
    {
        switch ($this->timePeriod) {
            case 1:
                return $this->acres * (float)$this->tp1Vol;
            case 2:
                return $this->acres * (float)$this->tp2Vol;
            case 3:
                return $this->acres * (float)$this->tp3Vol;
        }

        throw new Exception('Invalid state');
    }

    public function isValid()
    {
        foreach ($this->neighbors as $neighbor) {
            if ($neighbor->getTimePeriod() == $this->timePeriod) {
                return false;
            }
        }

        return true;
    }

    public function distanceTo(PathStack $previousPath = null, Branch $target)
    {
        // Check if we are the target
        if ($this == $target) {
            return 0;
        }

        // Initialize path stack
        if (is_null($previousPath)) {
            $previousPathClone = new PathStack();
        } else {
            $previousPathClone = clone $previousPath;
        }

        // Add the node to the path
        $previousPathClone->push($this);

        // Keep track of shortest path
        $shortestPath = -1;

        foreach ($this->neighbors as $neighbor) {
            // Ignore nodes we have already explored
            if ($previousPathClone->has($neighbor)) {
                continue;
            }

            try {
                $pathLength = $neighbor->distanceTo($previousPathClone, $target);

                if ($shortestPath == -1 || $shortestPath > $pathLength) {
                    $shortestPath = $pathLength + 1;
                }
            } catch (Exception $e) {

            }
        }

        if  ($shortestPath > -1) {
            return $shortestPath;
        }

        throw new Exception('Dead end');
    }

    public function pickSafeTimePeriod(CutBranch $unitA)
    {
        $options = [1, 2, 3];

        if (count($this->neighbors) == 1) {
            //$options = array_diff($options, [$this->timePeriod]);
            $this->pickBestIfLeaf();
            return;
        } else {
            foreach ($this->neighbors as $neighbor) {
                $options = array_diff($options, [$neighbor->getTimePeriod()]);
            }
        }

        // Check distance to unit a
        try {
            if ($this->distanceTo(null, $unitA) < 2) {
                $options = array_diff($options, [$unitA->timePeriod]);
            }
        } catch (Exception $e) {

        }

        // Set time period
        $optionsCount = count($options);
        if ($optionsCount == 0) {
            $closestNeighbor = null;
            $closestNeighborDistance = -1;

            /** @var CutBranch $neighbor */
            foreach ($this->neighbors as $neighbor) {
                $neighborDistance = $neighbor->distanceTo(null, $unitA);

                if ($closestNeighborDistance == -1 || $closestNeighborDistance > $neighborDistance) {
                    $closestNeighbor = $neighbor;
                    $closestNeighborDistance = $neighborDistance;
                }
            }

            switch ($closestNeighbor->getTimePeriod()) {
                case 1:
                    if ($this->tp2Vol > $this->tp3Vol) {
                        $this->timePeriod = 2;
                    } else {
                        $this->timePeriod = 3;
                    }
                    break;
                case 2:
                    if ($this->tp1Vol > $this->tp3Vol) {
                        $this->timePeriod = 1;
                    } else {
                        $this->timePeriod = 3;
                    }
                    break;
                case 3:
                    if ($this->tp2Vol > $this->tp1Vol) {
                        $this->timePeriod = 2;
                    } else {
                        $this->timePeriod = 1;
                    }
                    break;
            }

            return;
            //$options = array_diff([1, 2, 3], [$this->timePeriod]);
            //$optionsCount = count($options);
        }

        $options = array_values($options);

        if ($optionsCount == 1) {
            $this->setTimePeriod($options[0]);
        } else {
            $this->setTimePeriod($options[rand(0, count($options) - 1)]);
        }
    }

    public static function makeFromBranch(Branch $branch) {
        $cutBranch = new CutBranch(
            $branch->getId(),
            $branch->getAcres(),
            $branch->getTp1Vol(),
            $branch->getTp2Vol(),
            $branch->getTp3Vol()
        );

        return $cutBranch;
    }

    public function setRandomTimePeriod()
    {
        $this->timePeriod = rand(1,3);
    }

    public function pickBestIfLeaf()
    {
        if (count($this->neighbors) == 1) {
            // Get other neighbors tp
            $neighborTp = array_values($this->neighbors)[0]->getTimePeriod();

            switch ($neighborTp) {
                case 1:
                    if ($this->tp2Vol > $this->tp3Vol) {
                        $this->timePeriod = 2;
                    } else {
                        $this->timePeriod = 3;
                    }
                    break;
                case 2:
                    if ($this->tp1Vol > $this->tp3Vol) {
                        $this->timePeriod = 1;
                    } else {
                        $this->timePeriod = 3;
                    }
                    break;
                case 3:
                    if ($this->tp2Vol > $this->tp1Vol) {
                        $this->timePeriod = 2;
                    } else {
                        $this->timePeriod = 1;
                    }
                    break;
            }
        }
    }

    public function pickBest()
    {
        $options = [
            1 => $this->tp1Vol,
            2 => $this->tp2Vol,
            3 => $this->tp3Vol
        ];

        $max = max($options);

        foreach ($options as $tp => $vol) {
            if ($vol == $max) {
                $this->timePeriod = $tp;
            }
        }
    }
} 