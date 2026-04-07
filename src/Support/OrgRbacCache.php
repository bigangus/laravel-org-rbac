<?php

namespace Zhanghongfei\OrgRbac\Support;

use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class OrgRbacCache
{
    /**
     * After tenant reparent: flush tagged permission cache if configured, else delete keys by prefix (SCAN).
     */
    public static function flushAfterTenantReparent(): void
    {
        OrgRbacLog::info('permission_cache_flush_after_tenant_reparent_started', [
            'reason' => 'tenant_reparented',
        ]);

        if (self::flushTaggedPermissionCache()) {
            return;
        }

        self::forgetPermissionCachesByPrefixUsingScan();
    }

    /**
     * Clear all org-rbac permission caches (tags and/or prefix), e.g. from artisan.
     */
    public static function flushAllPermissionCaches(): void
    {
        OrgRbacLog::info('permission_cache_flush_all_started', [
            'reason' => 'manual_or_artisan',
        ]);

        if (self::flushTaggedPermissionCache()) {
            return;
        }

        self::forgetPermissionCachesByPrefixUsingScan();
    }

    /**
     * @return bool Whether tag-based flush ran (no further action needed for prefix).
     */
    public static function flushTaggedPermissionCache(): bool
    {
        if (! config('org-rbac.cache.use_tagged_permission_cache', false)) {
            return false;
        }

        if (! Cache::supportsTags()) {
            OrgRbacLog::debug('tagged_permission_cache_unsupported_store', [
                'hint' => 'Use Redis cache store with tagging support.',
            ]);

            return false;
        }

        try {
            $tags = (array) config('org-rbac.cache.permission_cache_tags', ['org-rbac-permissions']);
            Cache::tags($tags)->flush();
            OrgRbacLog::info('permission_cache_flushed_by_tags', [
                'tags' => $tags,
            ]);

            return true;
        } catch (Throwable $e) {
            OrgRbacLog::error('tagged_permission_cache_flush_failed', [], $e);

            return false;
        }
    }

    /**
     * Delete keys matching permission prefix using Redis SCAN (non-blocking), with KEYS fallback.
     */
    public static function forgetPermissionCachesByPrefixUsingScan(): void
    {
        $prefix = (string) config('org-rbac.cache.permission_key_prefix', 'org-rbac.perm.');

        try {
            $store = Cache::driver()->getStore();

            if (! $store instanceof RedisStore) {
                OrgRbacLog::debug('permission_cache_prefix_flush_skipped_non_redis', [
                    'store' => get_class($store),
                ]);

                return;
            }

            $connection = $store->connection();
            $pattern = $store->getPrefix().$prefix.'*';

            $client = method_exists($connection, 'client') ? $connection->client() : null;

            $deletedApprox = 0;

            if ($client instanceof \Redis) {
                $iterator = null;
                do {
                    /** @var array|false $keys */
                    $keys = $client->scan($iterator, $pattern, 200);
                    if ($keys !== false && $keys !== []) {
                        $deletedApprox += count($keys);
                        $client->del($keys);
                    }
                } while ($iterator > 0);

                OrgRbacLog::info('permission_cache_keys_deleted_by_scan', [
                    'pattern' => $pattern,
                    'keys_deleted_approx' => $deletedApprox,
                ]);

                return;
            }

            $keys = $connection->keys($pattern);

            if (! is_array($keys) || $keys === []) {
                OrgRbacLog::info('permission_cache_prefix_flush_no_keys', [
                    'pattern' => $pattern,
                ]);

                return;
            }

            $deletedApprox = count($keys);

            foreach (array_chunk($keys, 500) as $chunk) {
                $connection->del(...$chunk);
            }

            OrgRbacLog::info('permission_cache_keys_deleted_by_keys_command', [
                'pattern' => $pattern,
                'keys_deleted_approx' => $deletedApprox,
            ]);
        } catch (Throwable $e) {
            OrgRbacLog::error('permission_cache_prefix_flush_failed', [], $e);
        }
    }
}
