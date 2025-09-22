<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class LogHelper
{
    const SEPARATOR = ' : ';

    public static function info($message, $context = null): void
    {
        Log::withContext(self::getContext('info', $context))->info($message);
    }

    public static function debug($message, $context = null): void
    {
        Log::withContext(self::getContext('debug', $context))->debug($message);
    }

    public static function alert($message, $context = null): void
    {
        Log::withContext(self::getContext('alert', $context))->alert($message);
    }

    public static function warning($message, $context = null): void
    {
        Log::withContext(self::getContext('warning', $context))->warning($message);
    }

    public static function error($message, $context = null): void
    {
        Log::withContext(self::getContext('error', $context))->error($message);
    }

    public static function critical($message, $context = null): void
    {
        Log::withContext(self::getContext('critical', $context))->critical($message);
    }

    public static function exception(\Exception $exception, array $context = [], $ext = 'EXCEPTION'): void
    {
        Log::withContext(self::getContext('error', $context))
            ->error($ext . self::SEPARATOR .  $exception->getMessage(), [
                'file-name' => $exception->getFile(),
                'file-line-number' => $exception->getLine()
            ]);
    }

    private static function getContext($level, $context = null): array
    {
        return array_merge([
            'ip' => request()->ip() ?? '',
            'ua' => request()->userAgent() ?? '',
            'host-name' => gethostname(),
            'level-name' => $level,
            'referer' => request()->getClientIp() ?? ''
        ], $context ?? []);
    }
}
