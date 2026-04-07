<?php

namespace Zhanghongfei\OrgRbac\Listeners;

use Zhanghongfei\OrgRbac\Events\TenantReparented;
use Zhanghongfei\OrgRbac\Support\OrgRbacCache;

class FlushOrgRbacPermissionCacheOnTenantReparented
{
    public function handle(TenantReparented $event): void
    {
        if (! config('org-rbac.cache.flush_permission_cache_on_tenant_reparent', true)) {
            return;
        }

        OrgRbacCache::forgetPermissionCachesByPrefix();
    }
}
