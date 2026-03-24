<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Support;

use Illuminate\Support\Facades\Log;

class Logger
{
    protected static string $prefix = '[Sermon Formatter]';

    public static function info(string $message, array $context = []): void
    {
        static::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        static::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        static::log('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        static::log('debug', $message, $context);
    }

    private static function log(string $level, string $message, array $context): void
    {
        if (! config('sermon-formatter.logging.enabled', true)) {
            return;
        }

        Log::channel('sermon-formatter')->{$level}(static::$prefix.' '.$message, $context);
    }
}
