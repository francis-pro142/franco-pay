<?php
// Reports which database the app will actually connect to, and whether the
// schema is present. Prints no credentials, so it is safe to run anywhere.
//
//   php tests/db_check.php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Config/env.php';

use App\Config\Database;
use App\Services\DatabaseConnection;

function present(string $name): string
{
    $value = Database::env($name);
    return $value === null ? 'not set' : 'set';
}

echo "Environment\n";
foreach (['MYSQL_URL', 'DATABASE_URL', 'MYSQLHOST', 'MYSQLDATABASE',
          'DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $name) {
    printf("  %-14s %s\n", $name, present($name));
}

$driver = Database::getDriver();
$cfg = Database::getConfig();

echo "\nResolved configuration\n";
printf("  driver         %s\n", $driver);
if ($driver === 'sqlite') {
    echo "  file           database/franco_pay.sqlite\n";
    echo "\n  WARNING: this file lives inside the container on a hosted deploy\n";
    echo "  and is discarded on every release. Set the database variables on\n";
    echo "  the application service if you expect MySQL.\n";
} else {
    printf("  host           %s\n", $cfg['host']);
    printf("  port           %s\n", $cfg['port']);
    printf("  database       %s\n", $cfg['database']);
    printf("  user           %s\n", $cfg['user']);
    printf("  password       %s\n", $cfg['password'] === '' ? 'EMPTY' : 'set');
}

echo "\nConnection\n";
try {
    $pdo = DatabaseConnection::get();
    printf("  connected via  %s\n", $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
} catch (Throwable $e) {
    echo "  FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

$expected = ['users', 'wallets', 'sessions', 'transactions', 'ledger_entries', 'audit_logs'];
$missing = [];
foreach ($expected as $table) {
    try {
        $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
    } catch (Throwable $e) {
        $missing[] = $table;
    }
}

echo "\nSchema\n";
if ($missing === []) {
    printf("  all %d tables present\n", count($expected));
    $users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    printf("  users rows     %d\n", $users);
} else {
    echo "  MISSING: " . implode(', ', $missing) . "\n";
    echo "  Run: php bin/migrate.php\n";
    exit(1);
}
