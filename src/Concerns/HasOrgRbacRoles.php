<?php

namespace Zhanghongfei\OrgRbac\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;

trait HasOrgRbacRoles
{
    public function orgRbacRoles(): MorphToMany
    {
        $pivot = config('org-rbac.tables.model_has_roles');

        return $this->morphToMany(
            config('org-rbac.models.role'),
            'model',
            $pivot,
        )->withPivot(['tenant_id', 'department_id', 'data_scope']);
    }

    public function orgRbacDirectPermissions(): MorphToMany
    {
        $pivot = config('org-rbac.tables.model_has_permissions');

        return $this->morphToMany(
            config('org-rbac.models.permission'),
            'model',
            $pivot,
        )->withPivot(['tenant_id']);
    }

    /**
     * Permission names granted for the current bound tenant (via roles + direct).
     */
    public function orgRbacPermissionNamesInCurrentTenant(): Collection
    {
        $tenantId = app(CurrentTenant::class)->id();
        if ($tenantId === null) {
            return collect();
        }

        $roles = $this->orgRbacRoles()
            ->wherePivot('tenant_id', $tenantId)
            ->with('permissions')
            ->get();

        $fromRoles = $roles->flatMap(fn ($role) => $role->permissions->pluck('name'));

        $direct = $this->orgRbacDirectPermissions()
            ->wherePivot('tenant_id', $tenantId)
            ->get()
            ->pluck('name');

        return $fromRoles->merge($direct)->unique()->values();
    }

    public function hasOrgRbacPermission(string $name): bool
    {
        return $this->orgRbacPermissionNamesInCurrentTenant()->contains($name);
    }
}
