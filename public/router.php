<?php

// Router for PHP's development server. Apache uses public/.htaccess instead.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = realpath(__DIR__ . '/' . ltrim(rawurldecode($path), '/'));
if ($path !== '/' && $file !== false && is_file($file)
    && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)) {
    return false;
}
require __DIR__ . '/index.php';
