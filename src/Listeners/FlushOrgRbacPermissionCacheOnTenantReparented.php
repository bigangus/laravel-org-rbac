<?php

namespace Zhanghongfei\OrgRbac\Listeners;

use Zhanghongfei\OrgRbac\Events\TenantReparented;
use Zhanghongfei\OrgRbac\Support\OrgRbacCache;
use Zhanghongfei\OrgRbac\Support\OrgRbacLog;

class FlushOrgRbacPermissionCacheOnTenantReparented
{
    public function handle(TenantReparented $event): void
    {
        if (! config('org-rbac.cache.flush_permission_cache_on_tenant_reparent', true)) {
            return;
        }

        OrgRbacLog::info('tenant_tree_reparented', [
            'tenant_id' => $event->tenant->getKey(),
            'old_path' => $event->oldPath,
            'new_path' => $event->newPath,
            'old_parent_id' => $event->oldParentId,
            'new_parent_id' => $event->newParentId,
        ]);

        OrgRbacCache::flushAfterTenantReparent();
    }
}
