<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    foreach (['App\\' => '/app/', 'Mini\\' => '/framework/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = BASE_PATH . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

require BASE_PATH . '/framework/helpers.php';

// Process environment takes precedence over .env values.
foreach (is_file(BASE_PATH . '/.env') ? file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES) : [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = array_map('trim', explode('=', $line, 2));
    if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
        $_ENV[$key] = trim($value, "\"'");
    }
}

Mini\Config::load(BASE_PATH . '/config');
date_default_timezone_set((string) config('app.timezone', 'UTC'));
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug', false) ? '1' : '0');

set_exception_handler(function (Throwable $error): void {
    error_log((string) $error);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "ERROR: {$error->getMessage()}\n");
        exit(1);
    }
    http_response_code(500);
    echo config('app.debug')
        ? '<h1>'.e(function_exists('t')?t('Application error'):'Application error').'</h1><pre>' . e((string) $error) . '</pre>'
        : '<h1>'.e(function_exists('t')?t('Server error'):'Server error').'</h1><p>'.e(function_exists('t')?t('Please try again later.'):'Please try again later.').'</p>';
});

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) config('app.session_name', 'mini_php_session'));
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (is_file(BASE_PATH . '/app/helpers.php')) {
    require BASE_PATH . '/app/helpers.php';
}
if (PHP_SAPI !== 'cli' && is_file(BASE_PATH . '/app/bootstrap.php')) {
    require BASE_PATH . '/app/bootstrap.php';
}
