<?php
// config/env.php

/**
 * Loads key=value pairs from the project root .env file into $_ENV and getenv().
 * Existing process environment variables take precedence.
 */
function loadEnvFile(?string $path = null): void {
    static $loaded = false;
    if ($loaded && $path === null) {
        return;
    }

    $envPath = $path ?? dirname(__DIR__) . '/.env';
    if (!is_file($envPath)) {
        if ($path === null) {
            $loaded = true;
        }
        return;
    }

    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $eqPos = strpos($trimmed, '=');
        if ($eqPos === false) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $eqPos));
        $val = trim(substr($trimmed, $eqPos + 1));

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))
        ) {
            $val = substr($val, 1, -1);
        }

        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            putenv($key . '=' . $val);
        }
    }

    if ($path === null) {
        $loaded = true;
    }
}

/**
 * Retrieves an environment variable with an optional fallback default.
 */
function envValue(string $key, ?string $default = null): ?string {
    loadEnvFile();
    if (array_key_exists($key, $_ENV)) {
        return (string)$_ENV[$key];
    }
    $val = getenv($key);
    return $val !== false ? (string)$val : $default;
}

/**
 * Returns the configured application base path (e.g. '/society_app').
 */
function appBasePath(): string {
    $base = rtrim((string)envValue('APP_BASE_PATH', '/society_app'), '/');
    return $base;
}

/**
 * Returns the configured public application origin (e.g. 'http://localhost').
 */
function appUrl(): string {
    $configured = rtrim((string)envValue('APP_URL', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}
