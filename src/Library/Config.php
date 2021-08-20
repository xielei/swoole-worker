<?php

declare(strict_types=1);

namespace Xielei\Swoole\Library;

class Config
{
    private static $configs = [];

    public static function load(string $file)
    {
        foreach (require $file as $key => $value) {
            Config::set($key, $value);
        }
    }

    public static function set(string $key, $value)
    {
        self::$configs[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        return self::$configs[$key] ?? $default;
    }

    public static function delete(string $key)
    {
        unset(self::$configs[$key]);
    }

    public static function isset(string $key)
    {
        return isset(self::$configs[$key]);
    }
}
