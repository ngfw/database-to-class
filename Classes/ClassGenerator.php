<?php
declare(strict_types=1);

/**
 * Backward compatibility wrapper
 * This file is kept for backward compatibility.
 * New projects should use Composer autoloading with DatabaseToClass\ClassGenerator
 */

// Check if composer autoloader is available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    class_alias(\DatabaseToClass\ClassGenerator::class, 'ClassGenerator');
} else {
    // Fallback to direct include
    require_once __DIR__ . '/../src/Database.php';
    require_once __DIR__ . '/../src/ClassGenerator.php';
    class_alias(\DatabaseToClass\ClassGenerator::class, 'ClassGenerator');
}
