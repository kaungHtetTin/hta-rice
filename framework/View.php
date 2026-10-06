<?php

namespace Mini;

use RuntimeException;

final class View
{
    private static function path(string $name): string
    {
        if (!preg_match('#^[a-zA-Z0-9_/-]+$#', $name) || str_contains($name, '..')) {
            throw new RuntimeException('Invalid view name.');
        }
        $path = BASE_PATH . '/app/Views/' . $name . '.php';
        if (!is_file($path)) {
            throw new RuntimeException("View not found: {$name}");
        }
        return $path;
    }

    public static function render(string $viewName, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $file = self::path($viewName);
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } finally {
            $content = ob_get_clean();
        }
        if ($layout === null) {
            echo $content;
            return;
        }
        require self::path($layout);
    }
}

