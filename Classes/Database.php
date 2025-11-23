<?php
declare(strict_types=1);

/**
 * Backward compatibility wrapper
 * This file is kept for backward compatibility.
 * New projects should use Composer autoloading with DatabaseToClass\Database
 */

// Check if composer autoloader is available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    class_alias(\DatabaseToClass\Database::class, 'Database');
} else {
    // Fallback to direct include
    require_once __DIR__ . '/../src/Database.php';
    class_alias(\DatabaseToClass\Database::class, 'Database');
}
