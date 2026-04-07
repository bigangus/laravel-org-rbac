<?php

namespace Zhanghongfei\OrgRbac\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Structured logging for audit & operations; respects {@see config('org-rbac.logging')}.
 */
final class OrgRbacLog
{
    public static function enabled(): bool
    {
        return (bool) config('org-rbac.logging.enabled', true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = [], ?Throwable $e = null): void
    {
        if ($e !== null) {
            $context['exception'] = $e->getMessage();
        }

        self::write('error', $message, $context);
    }

    /**
     * @param  'debug'|'info'|'warning'|'error'  $level
     * @param  array<string, mixed>  $context
     */
    protected static function write(string $level, string $message, array $context): void
    {
        if (! self::enabled()) {
            return;
        }

        $channel = config('org-rbac.logging.channel');
        $payload = array_merge(['org_rbac' => true], $context);

        $prefixed = '[org-rbac] '.$message;

        if (is_string($channel) && $channel !== '') {
            Log::channel($channel)->{$level}($prefixed, $payload);

            return;
        }

        Log::{$level}($prefixed, $payload);
    }
}
