<?php

declare(strict_types=1);

namespace Xielei\Swoole\Library;

class Reload
{
    private static $watch = [];
    private static $filetimes = [];

    public static function init(array $watch = [])
    {
        self::$watch = $watch;
        $tmp_filetimes = [];
        foreach ($watch as $item) {
            self::getFilesTime($item, $tmp_filetimes);
        }
        self::$filetimes = $tmp_filetimes;
    }

    public static function check()
    {
        clearstatcache();
        $tmp_filetimes = [];
        foreach (self::$watch as $item) {
            self::getFilesTime($item, $tmp_filetimes);
        }

        if ($tmp_filetimes != self::$filetimes) {
            self::$filetimes = $tmp_filetimes;
            return true;
        }

        return false;
    }

    private static function getFilesTime($path, &$files)
    {
        if (is_dir($path)) {
            $dp = dir($path);
            while ($file = $dp->read()) {
                if ($file !== "." && $file !== "..") {
                    self::getFilesTime($path . "/" . $file, $files);
                }
            }
            $dp->close();
        }
        if (is_file($path)) {
            $files[$path] = filemtime($path);
        }
    }
}
