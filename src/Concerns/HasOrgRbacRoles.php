<?php

namespace Zhanghongfei\OrgRbac\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Zhanghongfei\OrgRbac\Models\Permission;
use Zhanghongfei\OrgRbac\Models\Role;
use Zhanghongfei\OrgRbac\Models\Tenant;
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
        )->withPivot(['tenant_id', 'data_scope', 'assigned_at', 'assigned_by'])
            ->withTimestamps();
    }

    public function orgRbacDirectPermissions(): MorphToMany
    {
        $pivot = config('org-rbac.tables.model_has_permissions');

        return $this->morphToMany(
            config('org-rbac.models.permission'),
            'model',
            $pivot,
        )->withPivot(['tenant_id'])
            ->withTimestamps();
    }

    /**
     * Membership: which tenant nodes (org / dept / …) this account belongs to.
     */
    public function orgRbacTenants(): BelongsToMany
    {
        return $this->belongsToMany(
            config('org-rbac.models.tenant'),
            config('org-rbac.tables.tenant_user'),
            'user_id',
            'tenant_id'
        )->withPivot(['is_owner', 'joined_at'])
            ->withTimestamps();
    }

    public function orgRbacRolesForTenant(Tenant $tenant): Collection
    {
        return $this->orgRbacRoles()
            ->wherePivot('tenant_id', $tenant->id)
            ->with('permissions')
            ->get();
    }

    /**
     * Roles assigned in this tenant or any ancestor tenant node (inheritance along the tree).
     *
     * @return Collection<int, Role>
     */
    public function orgRbacInheritedRolesForTenant(Tenant $tenant): Collection
    {
        $tenantChain = collect([$tenant])->merge($tenant->ancestors());
        $tenantIds = $tenantChain->pluck('id');

        return $this->orgRbacRoles()
            ->wherePivotIn('tenant_id', $tenantIds)
            ->with('permissions')
            ->get();
    }

    /**
     * Effective permission models for a tenant (roles including inheritance + direct grants on this tenant only).
     *
     * @return Collection<int, Permission>
     */
    public function orgRbacEffectivePermissions(Tenant $tenant): Collection
    {
        if ($this->orgRbacIsSuperAdmin()) {
            return Permission::query()->get();
        }

        $ttl = config('org-rbac.cache.permissions_ttl_minutes');

        if ($ttl === null || (int) $ttl <= 0) {
            return $this->orgRbacResolveEffectivePermissions($tenant);
        }

        $key = $this->orgRbacPermissionCacheKey($tenant);

        return Cache::remember($key, now()->addMinutes((int) $ttl), function () use ($tenant) {
            return $this->orgRbacResolveEffectivePermissions($tenant);
        });
    }

    /**
     * @return Collection<int, Permission>
     */
    protected function orgRbacResolveEffectivePermissions(Tenant $tenant): Collection
    {
        $fromRoles = $this->orgRbacInheritedRolesForTenant($tenant)
            ->flatMap(fn (Role $role) => $role->permissions);

        $direct = $this->orgRbacDirectPermissions()
            ->wherePivot('tenant_id', $tenant->id)
            ->get();

        return $fromRoles->merge($direct)->unique('id')->values();
    }

    public function orgRbacForgetPermissionCache(Tenant $tenant): void
    {
        Cache::forget($this->orgRbacPermissionCacheKey($tenant));
    }

    protected function orgRbacPermissionCacheKey(Tenant $tenant): string
    {
        return sprintf('org-rbac.perm.%s.%s.%s', class_basename($this), $this->getKey(), $tenant->getKey());
    }

    /**
     * @return Collection<int, string>
     */
    public function orgRbacPermissionNamesInCurrentTenant(): Collection
    {
        $tenant = app(CurrentTenant::class)->get();
        if ($tenant === null) {
            return collect();
        }

        return $this->orgRbacEffectivePermissions($tenant)->pluck('name')->unique()->values();
    }

    public function hasOrgRbacPermission(string $name): bool
    {
        if ($this->orgRbacIsSuperAdmin()) {
            return true;
        }

        $tenant = app(CurrentTenant::class)->get();
        if ($tenant === null) {
            return false;
        }

        return $this->orgRbacEffectivePermissions($tenant)->contains('name', $name);
    }

    public function hasOrgRbacPermissionInTenant(string $name, Tenant $tenant): bool
    {
        if ($this->orgRbacIsSuperAdmin()) {
            return true;
        }

        return $this->orgRbacEffectivePermissions($tenant)->contains('name', $name);
    }

    public function hasOrgRbacAnyPermissionInTenant(array $names, Tenant $tenant): bool
    {
        if ($this->orgRbacIsSuperAdmin()) {
            return true;
        }

        $effective = $this->orgRbacEffectivePermissions($tenant)->pluck('name');

        return collect($names)->intersect($effective)->isNotEmpty();
    }

    public function hasOrgRbacAllPermissionsInTenant(array $names, Tenant $tenant): bool
    {
        if ($this->orgRbacIsSuperAdmin()) {
            return true;
        }

        $effective = $this->orgRbacEffectivePermissions($tenant)->pluck('name');

        return collect($names)->every(fn ($n) => $effective->contains($n));
    }

    public function hasOrgRbacRoleInTenant(string|array $roles, Tenant $tenant): bool
    {
        $roles = (array) $roles;

        return $this->orgRbacRolesForTenant($tenant)->pluck('name')->intersect($roles)->isNotEmpty();
    }

    public function hasOrgRbacInheritedRoleInTenant(string|array $roles, Tenant $tenant): bool
    {
        $roles = (array) $roles;

        return $this->orgRbacInheritedRolesForTenant($tenant)->pluck('name')->intersect($roles)->isNotEmpty();
    }

    public function orgRbacIsOwnerOfTenant(Tenant $tenant): bool
    {
        return $this->orgRbacTenants()
            ->whereKey($tenant->getKey())
            ->wherePivot('is_owner', true)
            ->exists();
    }

    public function assignOrgRbacRoleInTenant(Role|string $role, Tenant $tenant): void
    {
        $roleModel = config('org-rbac.models.role');

        $role = is_object($role) && is_a($role, $roleModel, true)
            ? $role
            : $roleModel::query()->where('name', $role)->where('tenant_id', $tenant->id)->firstOrFail();

        $this->orgRbacRoles()->syncWithoutDetaching([
            $role->id => [
                'tenant_id' => $tenant->id,
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
            ],
        ]);

        $this->orgRbacForgetPermissionCache($tenant);
    }

    public function removeOrgRbacRoleInTenant(Role|string $role, Tenant $tenant): void
    {
        $roleModel = config('org-rbac.models.role');

        $role = is_object($role) && is_a($role, $roleModel, true)
            ? $role
            : $roleModel::query()->where('name', $role)->where('tenant_id', $tenant->id)->firstOrFail();

        $pivot = config('org-rbac.tables.model_has_roles');

        DB::table($pivot)
            ->where('role_id', $role->id)
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->getKey())
            ->where('tenant_id', $tenant->id)
            ->delete();

        $this->orgRbacForgetPermissionCache($tenant);
    }

    public function syncOrgRbacRolesInTenant(array $roles, Tenant $tenant): void
    {
        $pivot = config('org-rbac.tables.model_has_roles');

        DB::table($pivot)
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->getKey())
            ->where('tenant_id', $tenant->id)
            ->delete();

        foreach ($roles as $role) {
            $this->assignOrgRbacRoleInTenant($role, $tenant);
        }
    }

    public function joinOrgRbacTenant(Tenant $tenant, bool $asOwner = false): void
    {
        $this->orgRbacTenants()->syncWithoutDetaching([
            $tenant->id => [
                'is_owner' => $asOwner,
                'joined_at' => now(),
            ],
        ]);
    }

    public function leaveOrgRbacTenant(Tenant $tenant): void
    {
        $this->orgRbacTenants()->detach($tenant->id);

        $pivot = config('org-rbac.tables.model_has_roles');

        DB::table($pivot)
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->getKey())
            ->where('tenant_id', $tenant->id)
            ->delete();

        $this->orgRbacForgetPermissionCache($tenant);
    }

    protected function orgRbacIsSuperAdmin(): bool
    {
        return (bool) ($this->getAttribute('is_super_admin') ?? false);
    }
}
