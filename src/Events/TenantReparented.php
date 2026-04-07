<?php

namespace Zhanghongfei\OrgRbac\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Zhanghongfei\OrgRbac\Models\Tenant;

/**
 * Fired after a tenant node was moved to a new parent: the row and all descendants
 * have updated `path` / `depth`. Use this to invalidate caches keyed by tenant id or path.
 */
class TenantReparented
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $oldPath,
        public string $newPath,
        public ?int $oldParentId,
        public ?int $newParentId,
    ) {}
}
