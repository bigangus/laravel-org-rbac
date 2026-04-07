<?php

namespace Zhanghongfei\OrgRbac\Console;

use Illuminate\Console\Command;
use Zhanghongfei\OrgRbac\Support\OrgRbacCache;

class ClearOrgRbacPermissionCacheCommand extends Command
{
    protected $signature = 'org-rbac:clear-permission-cache';

    protected $description = 'Flush org-rbac effective permission caches (tags and/or Redis prefix)';

    public function handle(): int
    {
        OrgRbacCache::flushAllPermissionCaches();
        $this->info('org-rbac permission caches cleared.');

        return self::SUCCESS;
    }
}
