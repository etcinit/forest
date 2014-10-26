<?php

namespace Chromabits\Forest\Support;

use Chromabits\Forest\Entities\Branch;
use SplStack;

class PathStack
{
    /**
     * @var SplStack
     */
    protected $stack;

    function __construct()
    {
        $this->stack = new SplStack();
    }

    /**
     * @param Branch $branch
     */
    function push(Branch $branch) {
        $this->stack->push($branch);
    }

    /**
     * @return Branch
     */
    function pop()
    {
        return $this->stack->pop();
    }

    /**
     * @param Branch $branch
     * @return bool
     */
    function has(Branch $branch)
    {
        foreach ($this->stack as $item) {
            if ($item->getId() == $branch->getId()) {
                return true;
            }
        }

        return false;
    }
} 