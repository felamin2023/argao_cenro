<?php
// backend/bootstrap_env.php
declare(strict_types=1);

/**
 * Single source of truth for env loading.
 * Loads backend/.env and hydrates getenv()/$_ENV/$_SERVER.
 */

$ENV_ROOT = __DIR__; // -> backend/

// 1) Composer autoload (vendor/ is at project root)
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// 2) phpdotenv (populate getenv/$_ENV/$_SERVER)
try {
    if (class_exists(Dotenv\Dotenv::class) && is_file($ENV_ROOT . '/.env')) {
        // Important: createUnsafeImmutable so getenv() is populated
        Dotenv\Dotenv::createUnsafeImmutable($ENV_ROOT)->safeLoad();
    }
} catch (Throwable $e) {
    error_log('[ENV] Dotenv load error: ' . $e->getMessage());
}

// 3) Minimal fallback if host ignores phpdotenv
if (!getenv('SUPABASE_URL') && is_readable($ENV_ROOT . '/.env')) {
    foreach (file($ENV_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'"); // strip quotes
        if ($k !== '') {
            if (getenv($k) === false) putenv("$k=$v");
            $_ENV[$k]    = $_ENV[$k]    ?? $v;
            $_SERVER[$k] = $_SERVER[$k] ?? $v;
        }
    }
}
