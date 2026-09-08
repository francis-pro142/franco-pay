#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\DatabaseConnection;

$pdo = DatabaseConnection::get();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'sqlite') {
    $file = __DIR__ . '/../database/migrations/001_create_tables_sqlite.sql';
    if (!file_exists($file)) {
        echo "SQLite migration file not found: $file\n";
        exit(1);
    }

    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        echo "SQLite migrations applied.\n";
    } catch (PDOException $e) {
        echo "SQLite migration error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    $file = __DIR__ . '/../database/migrations/001_create_tables.sql';
    if (!file_exists($file)) {
        echo "Migration file not found: $file\n";
        exit(1);
    }

    $sql = file_get_contents($file);
    // Split on semicolon followed by newline to avoid breaking DEFINER statements
    $statements = preg_split('/;\s*\n/', $sql);

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
            echo "OK\n";
        } catch (PDOException $e) {
            echo "Migration error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    echo "Migrations applied.\n";
}
