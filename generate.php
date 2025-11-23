#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Database to Class Generator - Console Application
 *
 * Modern CLI tool for generating PHP classes from database tables
 */

// Autoload dependencies
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    echo "Error: Composer dependencies not installed.\n";
    echo "Run: composer install\n";
    exit(1);
}

use DatabaseToClass\Console\GenerateCommand;
use DatabaseToClass\Console\ListCommand;
use Symfony\Component\Console\Application;

$application = new Application('Database to Class Generator', '2.0.0');

// Register commands
$application->add(new GenerateCommand());
$application->add(new ListCommand());

// Set generate as default command
$application->setDefaultCommand('generate');

try {
    $application->run();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
