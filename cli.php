<?php
declare(strict_types=1);

/**
 * Legacy CLI Interface
 *
 * This file is kept for backward compatibility.
 * Please use the new generate.php for better experience with colors, progress bars, and more features.
 */

echo "\n";
echo "====================================================================\n";
echo "  DEPRECATED: This CLI interface is deprecated.\n";
echo "  Please use the new console application:\n";
echo "  \n";
echo "  php generate.php                 # Interactive mode\n";
echo "  php generate.php list-tables     # List all tables\n";
echo "  php generate.php generate users  # Generate specific table\n";
echo "  php generate.php generate --all  # Generate all tables\n";
echo "  \n";
echo "  Redirecting to new interface in 3 seconds...\n";
echo "====================================================================\n";
echo "\n";

sleep(3);

// Redirect to new console application
if (file_exists(__DIR__ . '/generate.php')) {
    passthru('php ' . __DIR__ . '/generate.php');
} else {
    echo "Error: generate.php not found. Please run: composer install\n";
    exit(1);
}
