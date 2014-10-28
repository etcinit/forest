<?php

namespace Chromabits\Forest\Console;

use Chromabits\Forest\Runner;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ForestCommand extends Command
{
    protected function configure()
    {
        $this->setName('forest');

        $this->addArgument('vol', InputArgument::OPTIONAL, 'Volumes input file', './data/West_73_units_volumes.txt');

        $this->addArgument('adj', InputArgument::OPTIONAL, 'Adjacency input file', './data/West_73_units_adjacency.txt');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $runner = new Runner();

        $runner->parseInput(
            $input->getArgument('vol'),
            $input->getArgument('adj')
        );

        $runner->randomizedInit();

        $runner->printConstraintStatus();

        //$runner->renderGraph();

        $runner->process(5000,10);
        //$runner->processRandomUnit(1);

        $runner->renderGraph();
    }
} 