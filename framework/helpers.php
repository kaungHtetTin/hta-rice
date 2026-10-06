<?php

use Mini\Csrf;
use Mini\View;

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false ? $default : $value;
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) env('APP_URL', ''), '/');
    if ($base === '') {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/.');
    }
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '') ?: '/';
}

function url(string $path = ''): string
{
    return base_url($path);
}

function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function view(string $name, array $data = [], ?string $layout = '__default'): void
{
    View::render($name, $data, $layout === '__default' ? config('app.layout', 'layouts/app') : $layout);
}

function redirect(string $path, ?string $message = null, string $type = 'success'): never
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header('Location: ' . url($path));
    exit;
}

function back(string $fallback = ''): never
{
    $target = $_SERVER['HTTP_REFERER'] ?? url($fallback);
    header('Location: ' . $target);
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function active_nav(string $prefix): string
{
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
    $base = trim(parse_url(base_url(), PHP_URL_PATH) ?: '', '/');
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = trim(substr($path, strlen($base)), '/');
    }
    return ($path === $prefix || str_starts_with($path, $prefix . '/')) ? 'is-active' : '';
}


function config(string $key, mixed $default = null): mixed
{
    return \Mini\Config::get($key, $default);
}
