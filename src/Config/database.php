<?php
namespace App\Config;

class Database
{
    /**
     * Read a configuration variable from the environment.
     *
     * Checks getenv() first, then $_ENV/$_SERVER: under some SAPIs (notably
     * Apache with mod_php) variables injected by the container are not always
     * visible to getenv() alone.
     */
    public static function env(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);
        if ($value === false) {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }
        if ($value === null || $value === '') {
            return $default;
        }
        return (string)$value;
    }

    /**
     * Managed MySQL providers publish a single connection URL of the form
     * mysql://user:pass@host:port/database. Railway exposes MYSQL_URL (and
     * DATABASE_URL); other hosts use their own names.
     */
    private static function configFromUrl(): ?array
    {
        foreach (['MYSQL_URL', 'DATABASE_URL', 'CLEARDB_DATABASE_URL', 'JAWSDB_URL'] as $key) {
            $url = self::env($key);
            if ($url === null) {
                continue;
            }

            $parts = parse_url($url);
            if ($parts === false || !isset($parts['host'])) {
                continue;
            }

            // Ignore a DATABASE_URL that points at some other engine.
            $scheme = strtolower($parts['scheme'] ?? 'mysql');
            if ($scheme !== 'mysql' && $scheme !== 'mysqli') {
                continue;
            }

            $database = ltrim($parts['path'] ?? '', '/');

            return [
                'host' => $parts['host'],
                'port' => (string)($parts['port'] ?? 3306),
                'database' => $database !== '' ? $database : 'railway',
                // Credentials are percent-encoded inside a URL.
                'user' => isset($parts['user']) ? rawurldecode($parts['user']) : 'root',
                'password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
                'charset' => 'utf8mb4'
            ];
        }

        return null;
    }

    /**
     * Railway's MySQL service also publishes the connection as discrete
     * MYSQL* variables, which is what you get when you reference the database
     * service from the app service's variables.
     */
    private static function configFromMysqlVars(): ?array
    {
        $host = self::env('MYSQLHOST');
        if ($host === null) {
            return null;
        }

        return [
            'host' => $host,
            'port' => self::env('MYSQLPORT', '3306'),
            'database' => self::env('MYSQLDATABASE', 'railway'),
            'user' => self::env('MYSQLUSER', 'root'),
            'password' => self::env('MYSQLPASSWORD', ''),
            'charset' => 'utf8mb4'
        ];
    }

    public static function getConfig(): array
    {
        $managed = self::configFromUrl() ?? self::configFromMysqlVars();
        if ($managed !== null) {
            return $managed;
        }

        return [
            'host' => self::env('DB_HOST', '127.0.0.1'),
            'port' => self::env('DB_PORT', '3306'),
            'database' => self::env('DB_NAME', 'franco_pay'),
            'user' => self::env('DB_USER', 'root'),
            'password' => self::env('DB_PASS', ''),
            'charset' => 'utf8mb4'
        ];
    }

    /**
     * Which PDO driver to connect with. An explicit DB_DRIVER always wins;
     * otherwise a detected managed MySQL service selects mysql, so that
     * attaching a database on Railway does not silently leave the app writing
     * to the local SQLite file (which is discarded on every deploy).
     */
    public static function getDriver(): string
    {
        $explicit = self::env('DB_DRIVER');
        if ($explicit !== null) {
            return strtolower($explicit);
        }

        if (self::configFromUrl() !== null || self::configFromMysqlVars() !== null) {
            return 'mysql';
        }

        return 'sqlite';
    }
}
