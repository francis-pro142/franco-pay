<?php
namespace App\Services;

use PDO;
use App\Config\Database as DBConfig;

class DatabaseConnection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        // Load .env variables if present
        $envPath = __DIR__ . '/../Config/env.php';
        if (file_exists($envPath)) {
            require_once $envPath;
        }

        if (self::$pdo) {
            return self::$pdo;
        }

        $driver = DBConfig::getDriver();
        $cfg = DBConfig::getConfig();

        if (strtolower($driver) === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']);
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $options);
                return self::$pdo;
            } catch (\PDOException $e) {
                throw new \PDOException("MySQL connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
            }
        }

        // Local development default: SQLite file database
        $sqliteFile = __DIR__ . '/../../database/franco_pay.sqlite';
        $sqliteDsn = 'sqlite:' . $sqliteFile;
        self::$pdo = new PDO($sqliteDsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        return self::$pdo;
    }
}
