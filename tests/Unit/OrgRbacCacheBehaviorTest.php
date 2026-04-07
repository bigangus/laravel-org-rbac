<?php

namespace Zhanghongfei\OrgRbac\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Support\OrgRbacCache;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class OrgRbacCacheBehaviorTest extends TestCase
{
    #[Test]
    public function flush_tagged_returns_false_when_tags_disabled_in_config(): void
    {
        config(['org-rbac.cache.use_tagged_permission_cache' => false]);

        $this->assertFalse(OrgRbacCache::flushTaggedPermissionCache());
    }

    #[Test]
    public function flush_tagged_respects_config_and_store_capabilities(): void
    {
        config(['org-rbac.cache.use_tagged_permission_cache' => true]);

        $result = OrgRbacCache::flushTaggedPermissionCache();

        if (Cache::supportsTags()) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    #[Test]
    public function flush_all_permission_caches_runs_without_exception(): void
    {
        config(['org-rbac.cache.use_tagged_permission_cache' => false]);

        OrgRbacCache::flushAllPermissionCaches();

        $this->assertTrue(true);
    }
}
