<?php
// Simple .env loader for development (no external dependency)
// Loads .env in project root into environment variables

$root = dirname(__DIR__, 2);
$envFile = $root . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$name, $val] = explode('=', $line, 2);
        $name = trim($name);
        $val = trim($val);
        // Real environment variables win over the .env file. Platforms such as
        // Railway inject the database credentials this way, and a checked-in
        // .env must not quietly override them.
        if (getenv($name) !== false || isset($_ENV[$name]) || isset($_SERVER[$name])) {
            continue;
        }
        // remove surrounding quotes
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        putenv(sprintf('%s=%s', $name, $val));
        $_ENV[$name] = $val;
        $_SERVER[$name] = $val;
    }
}
