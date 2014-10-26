<?php

// Load composer autoloader
require_once 'vendor/autoload.php';

// Create app
$app = new \Symfony\Component\Console\Application('forest', '0.0.1');

// Setup available commands
$app->add(new \Chromabits\Forest\Console\ForestCommand());

// Execute app
$app->run();