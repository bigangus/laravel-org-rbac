<?php

namespace Zhanghongfei\OrgRbac\Support;

use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OrgRbacCache
{
    /**
     * Delete all effective-permission cache entries whose keys start with the configured prefix
     * (default `org-rbac.perm.`). Only works when the default cache store uses {@see RedisStore}.
     */
    public static function forgetPermissionCachesByPrefix(): void
    {
        $prefix = (string) config('org-rbac.cache.permission_key_prefix', 'org-rbac.perm.');

        try {
            $store = Cache::getStore();

            if (! $store instanceof RedisStore) {
                if (config('app.debug')) {
                    Log::debug('org-rbac: permission cache prefix flush skipped (cache store is not redis).');
                }

                return;
            }

            $connection = $store->connection();
            $pattern = $store->getPrefix().$prefix.'*';

            $keys = $connection->keys($pattern);

            if (! is_array($keys) || $keys === []) {
                return;
            }

            foreach (array_chunk($keys, 500) as $chunk) {
                $connection->del(...$chunk);
            }
        } catch (Throwable $e) {
            Log::warning('org-rbac: permission cache prefix flush failed: '.$e->getMessage());
        }
    }
}
