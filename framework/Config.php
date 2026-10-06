<?php

declare(strict_types=1);

namespace Mini;

final class Config
{
    private static array $values = [];

    public static function load(string $directory): void
    {
        self::$values = [];
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            self::$values[pathinfo($file, PATHINFO_FILENAME)] = require $file;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
