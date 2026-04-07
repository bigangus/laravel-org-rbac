<?php

namespace Zhanghongfei\OrgRbac\Support;

use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Enforces that the active cache {@see Store} is Redis-backed (required for SCAN/tag semantics used by org-rbac).
 */
final class RedisCacheRequirement
{
    public static function assertDefaultStore(?Store $store = null): void
    {
        $store ??= Cache::driver()->getStore();

        if (! $store instanceof RedisStore) {
            throw new RuntimeException(
                'laravel-org-rbac requires the default cache store to use Redis (Illuminate\\Cache\\RedisStore). '.
                'Set CACHE_STORE=redis and configure a Redis connection. '.
                'In PHPUnit / local non-Redis environments set ORG_RBAC_REQUIRE_REDIS_CACHE=false in .env or phpunit.xml.'
            );
        }
    }
}
