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
    if (!empty($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'infy.click')) {
        return '';
    }
    $configured = envValue('APP_BASE_PATH', null);
    if ($configured !== null) {
        return ($configured === '' || $configured === '/') ? '' : rtrim((string)$configured, '/');
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    return ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
}

/**
 * Returns the configured public application origin (e.g. 'http://localhost').
 */
function appUrl(): string {
    if (!empty($_SERVER['HTTP_HOST'])) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        $scheme = $isHttps ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
    $configured = rtrim((string)envValue('APP_URL', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    return 'https://society.infy.click';
}
