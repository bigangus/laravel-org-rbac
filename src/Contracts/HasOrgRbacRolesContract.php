<?php

namespace Zhanghongfei\OrgRbac\Contracts;

use Illuminate\Support\Collection;
use Zhanghongfei\OrgRbac\Concerns\HasOrgRbacRoles;
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Models\Permission;
use Zhanghongfei\OrgRbac\Models\Tenant;

/**
 * Implemented by the application User model via {@see HasOrgRbacRoles}.
 */
interface HasOrgRbacRolesContract
{
    /**
     * @return Collection<int, Permission>
     */
    public function orgRbacEffectivePermissions(Tenant $tenant): Collection;

    public function hasOrgRbacPermission(string $name): bool;

    public function orgRbacWidestDataScopeForTenant(Tenant $tenant): ?DataScope;

    public function orgRbacForgetPermissionCache(Tenant $tenant): void;
}
