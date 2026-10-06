<?php

declare(strict_types=1);

namespace Mini;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, bool $auth = false, ?string $permission = null): void
    {
        $this->add('GET', $pattern, $handler, $auth, $permission);
    }

    public function post(string $pattern, callable|array $handler, bool $auth = false, ?string $permission = null): void
    {
        $this->add('POST', $pattern, $handler, $auth, $permission);
    }

    public function add(string $method, string $pattern, callable|array $handler, bool $auth = false, ?string $permission = null): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler', 'auth', 'permission');
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method']) && is_string($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(parse_url(base_url(), PHP_URL_PATH) ?: '', '/');
        if ($base !== '' && ($uri === $base || str_starts_with($uri, $base . '/'))) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');
        $allowed = [];

        foreach ($this->routes as $route) {
            $regex = preg_quote($route['pattern'], '#');
            $regex = preg_replace_callback('/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/',
                fn (array $match): string => '(?P<' . $match[1] . '>[^/]+)', $regex);
            if (!preg_match('#^' . $regex . '$#', $uri, $matches)) {
                continue;
            }
            $allowed[] = $route['method'];
            if ($method !== $route['method'] && !($method === 'HEAD' && $route['method'] === 'GET')) {
                continue;
            }
            $check = config('auth.check');
            if ($route['auth'] && (!is_callable($check) || !$check())) {
                redirect((string) config('auth.login', 'login'), t('Please sign in to continue.'), 'warning');
            }
            $can = config('auth.can');
            if ($route['permission'] && (!is_callable($can) || !$can($route['permission']))) {
                $this->error(403);
                return;
            }
            if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) && !Csrf::verify($_POST['_token'] ?? null)) {
                $this->error(419);
                return;
            }
            $params = array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $handler = [new $handler[0](), $handler[1]];
            }
            if ($method === 'HEAD') {
                ob_start();
                try {
                    $handler(...$params);
                } finally {
                    ob_end_clean();
                }
            } else {
                $handler(...$params);
            }
            unset($_SESSION['old'], $_SESSION['errors']);
            return;
        }
        if ($allowed) {
            if (in_array('GET', $allowed, true)) {
                $allowed[] = 'HEAD';
            }
            header('Allow: ' . implode(', ', array_unique($allowed)));
            $this->error(405);
            return;
        }
        $this->error(404);
    }

    private function error(int $status): void
    {
        http_response_code($status);
        if (is_file(BASE_PATH . '/app/Views/errors/' . $status . '.php')) {
            view('errors/' . $status, [], null);
            return;
        }
        $messages = [403 => 'Access denied', 404 => 'Page not found', 405 => 'Method not allowed', 419 => 'Session expired'];
        echo '<h1>' . $status . ' ' . e(t($messages[$status])) . '</h1>';
    }
}
