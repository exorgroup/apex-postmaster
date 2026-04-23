<?php

/**
 * APEX PostMaster Database Migration Runner
 *
 * Usage:
 *   php migrate.php              - Run all pending migrations
 *   php migrate.php --status     - Show migration status
 *   php migrate.php --fresh      - Drop all tables and re-run all migrations
 *   php migrate.php --rollback   - Rollback last migration (if rollback file exists)
 */

declare(strict_types=1);

// Load environment
$envFile = __DIR__ . '/../public_html/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value, '"\'');
    }
}

// Database connection
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'apex_postmaster';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

echo "APEX PostMaster Migration Runner\n";
echo "================================\n\n";

try {
    // First connect without database to create it if needed
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '{$dbname}' ready.\n";

    // Connect to the database
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Parse command line arguments
$args = array_slice($argv, 1);
$showStatus = in_array('--status', $args);
$fresh = in_array('--fresh', $args);
$rollback = in_array('--rollback', $args);

// Handle fresh install
if ($fresh) {
    echo "Dropping all tables...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        echo "  Dropped: {$table}\n";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "\n";
}

// Create migrations table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `migrations` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `migration` varchar(255) NOT NULL,
        `executed_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `migrations_name_unique` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Get executed migrations
$executed = $pdo->query("SELECT migration FROM migrations ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

// Get migration files
$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

// Show status
if ($showStatus) {
    echo "Migration Status:\n";
    echo "-----------------\n";

    foreach ($files as $file) {
        $name = basename($file, '.sql');
        $status = in_array($name, $executed) ? '[✓] Executed' : '[ ] Pending';
        echo "{$status}: {$name}\n";
    }

    exit(0);
}

// Handle rollback
if ($rollback) {
    if (empty($executed)) {
        echo "Nothing to rollback.\n";
        exit(0);
    }

    $last = end($executed);
    $rollbackFile = $migrationsDir . '/' . $last . '_rollback.sql';

    if (!file_exists($rollbackFile)) {
        echo "No rollback file found for: {$last}\n";
        exit(1);
    }

    echo "Rolling back: {$last}\n";
    $sql = file_get_contents($rollbackFile);
    $pdo->exec($sql);
    $pdo->prepare("DELETE FROM migrations WHERE migration = ?")->execute([$last]);
    echo "Rollback complete.\n";
    exit(0);
}

// Run pending migrations
$pending = 0;

foreach ($files as $file) {
    $name = basename($file, '.sql');

    // Skip if already executed
    if (in_array($name, $executed)) {
        continue;
    }

    echo "Running migration: {$name}\n";

    $sql = file_get_contents($file);

    try {
        $pdo->exec($sql);

        // Record migration (if not already recorded by the migration itself)
        $exists = $pdo->prepare("SELECT 1 FROM migrations WHERE migration = ?");
        $exists->execute([$name]);

        if (!$exists->fetch()) {
            $pdo->prepare("INSERT INTO migrations (migration, executed_at) VALUES (?, NOW())")->execute([$name]);
        }

        echo "  ✓ Complete\n";
        $pending++;

    } catch (PDOException $e) {
        echo "  ✗ Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($pending === 0) {
    echo "No pending migrations.\n";
} else {
    echo "\n{$pending} migration(s) executed successfully.\n";
}

echo "\nDone.\n";
