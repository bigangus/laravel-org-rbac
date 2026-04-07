<?php

namespace Zhanghongfei\OrgRbac\Contracts;

use Zhanghongfei\OrgRbac\Models\Tenant;

interface CurrentTenantContract
{
    public function set(?Tenant $tenant): void;

    public function get(): ?Tenant;

    public function id(): ?int;

    public function check(): bool;

    public function clear(): void;
}
