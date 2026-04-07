<?php

namespace Zhanghongfei\OrgRbac\Support;

use Zhanghongfei\OrgRbac\Contracts\CurrentTenantContract;
use Zhanghongfei\OrgRbac\Models\Tenant;

class CurrentTenant implements CurrentTenantContract
{
    protected ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
