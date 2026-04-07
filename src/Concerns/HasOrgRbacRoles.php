<?php

namespace Zhanghongfei\OrgRbac\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Models\Permission;
use Zhanghongfei\OrgRbac\Models\Role;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;

trait HasOrgRbacRoles
{
    /**
     * 单次请求内缓存：超管有效权限（与租户无关，全表列）。
     *
     * @var Collection<int, Permission>|null
     */
    private $orgRbacMemoSuperAdminEffectivePermissions = null;

    /**
     * 单次请求内缓存：普通用户在某租户下的有效权限。
     *
     * @var array<int, Collection<int, Permission>>
     */
    private array $orgRbacMemoEffectivePermissionsByTenantId = [];

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
     * Widest {@see DataScope} among inherited role pivots (`data_scope`) for this context.
     * Direct permission grants do not carry a data scope and are not included.
     * When every pivot has empty `data_scope`, returns null — callers typically default to {@see DataScope::Department}.
     */
    public function orgRbacWidestDataScopeForTenant(Tenant $tenant): ?DataScope
    {
        $raw = $this->orgRbacInheritedRolesForTenant($tenant)
            ->map(fn (Role $role) => $role->pivot->data_scope ?? null)
            ->all();

        return DataScope::widestFromStrings(...$raw);
    }

    /**
     * Effective permission models for a tenant (roles including inheritance + direct grants on this tenant only).
     *
     * @return Collection<int, Permission>
     */
    public function orgRbacEffectivePermissions(Tenant $tenant): Collection
    {
        if ($this->orgRbacIsSuperAdmin()) {
            if ($this->orgRbacMemoSuperAdminEffectivePermissions !== null) {
                return $this->orgRbacMemoSuperAdminEffectivePermissions;
            }

            $cols = config('org-rbac.super_admin.permission_columns', ['id', 'name', 'guard_name', 'tenant_id', 'group']);
            if (! is_array($cols) || $cols === []) {
                $cols = ['id', 'name', 'guard_name'];
            }

            $ttl = config('org-rbac.cache.permissions_ttl_minutes');

            if ($ttl === null || (int) $ttl <= 0) {
                return $this->orgRbacMemoSuperAdminEffectivePermissions = Permission::query()->get($cols);
            }

            $key = $this->orgRbacSuperAdminPermissionCacheKey();
            $minutes = (int) $ttl;
            $callback = fn () => Permission::query()->get($cols);

            if (config('org-rbac.cache.use_tagged_permission_cache') && Cache::supportsTags()) {
                $tags = (array) config('org-rbac.cache.permission_cache_tags', ['org-rbac-permissions']);

                return $this->orgRbacMemoSuperAdminEffectivePermissions = Cache::tags($tags)->remember($key, now()->addMinutes($minutes), $callback);
            }

            return $this->orgRbacMemoSuperAdminEffectivePermissions = Cache::remember($key, now()->addMinutes($minutes), $callback);
        }

        $tenantId = (int) $tenant->getKey();
        if (isset($this->orgRbacMemoEffectivePermissionsByTenantId[$tenantId])) {
            return $this->orgRbacMemoEffectivePermissionsByTenantId[$tenantId];
        }

        $ttl = config('org-rbac.cache.permissions_ttl_minutes');

        if ($ttl === null || (int) $ttl <= 0) {
            return $this->orgRbacMemoEffectivePermissionsByTenantId[$tenantId] = $this->orgRbacResolveEffectivePermissions($tenant);
        }

        $key = $this->orgRbacPermissionCacheKey($tenant);
        $minutes = (int) $ttl;
        $callback = fn () => $this->orgRbacResolveEffectivePermissions($tenant);

        if (config('org-rbac.cache.use_tagged_permission_cache') && Cache::supportsTags()) {
            $tags = (array) config('org-rbac.cache.permission_cache_tags', ['org-rbac-permissions']);

            return $this->orgRbacMemoEffectivePermissionsByTenantId[$tenantId] = Cache::tags($tags)->remember($key, now()->addMinutes($minutes), $callback);
        }

        return $this->orgRbacMemoEffectivePermissionsByTenantId[$tenantId] = Cache::remember($key, now()->addMinutes($minutes), $callback);
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
        $this->forgetOrgRbacPermissionCacheKey($this->orgRbacPermissionCacheKey($tenant));
        $this->forgetOrgRbacPermissionCacheKey($this->orgRbacSuperAdminPermissionCacheKey());

        unset($this->orgRbacMemoEffectivePermissionsByTenantId[(int) $tenant->getKey()]);
        $this->orgRbacMemoSuperAdminEffectivePermissions = null;
    }

    protected function orgRbacPermissionCacheKey(Tenant $tenant): string
    {
        $prefix = (string) config('org-rbac.cache.permission_key_prefix', 'org-rbac.perm.');

        return sprintf('%s%s.%s.%s', $prefix, class_basename($this), $this->getKey(), $tenant->getKey());
    }

    /**
     * 超管有效权限缓存键（不按租户分片，避免同一全表结果重复占用缓存）。
     */
    protected function orgRbacSuperAdminPermissionCacheKey(): string
    {
        $prefix = (string) config('org-rbac.cache.permission_key_prefix', 'org-rbac.perm.');

        return sprintf('%ssuper.%s.%s', $prefix, class_basename($this), $this->getKey());
    }

    protected function forgetOrgRbacPermissionCacheKey(string $key): void
    {
        if (config('org-rbac.cache.use_tagged_permission_cache') && Cache::supportsTags()) {
            $tags = (array) config('org-rbac.cache.permission_cache_tags', ['org-rbac-permissions']);
            Cache::tags($tags)->forget($key);

            return;
        }

        Cache::forget($key);
    }

    /**
     * Pivot `data_scope` string for role assignment (default from config when null).
     */
    protected function orgRbacResolveAssignDataScopeValue(DataScope|string|null $dataScope): string
    {
        if ($dataScope instanceof DataScope) {
            return $dataScope->value;
        }

        if (is_string($dataScope) && $dataScope !== '') {
            $try = DataScope::tryFrom($dataScope);

            return $try !== null ? $try->value : DataScope::Department->value;
        }

        $default = (string) config('org-rbac.defaults.assign_role_data_scope', 'department');
        $try = DataScope::tryFrom($default);

        return $try !== null ? $try->value : DataScope::Department->value;
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

    public function assignOrgRbacRoleInTenant(Role|string $role, Tenant $tenant, DataScope|string|null $dataScope = null): void
    {
        $roleModel = config('org-rbac.models.role');

        $role = is_object($role) && is_a($role, $roleModel, true)
            ? $role
            : $roleModel::query()->where('name', $role)->where('tenant_id', $tenant->id)->firstOrFail();

        $scopeValue = $this->orgRbacResolveAssignDataScopeValue($dataScope);

        $this->orgRbacRoles()->syncWithoutDetaching([
            $role->id => [
                'tenant_id' => $tenant->id,
                'data_scope' => $scopeValue,
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

    public function syncOrgRbacRolesInTenant(array $roles, Tenant $tenant, DataScope|string|null $defaultDataScope = null): void
    {
        $pivot = config('org-rbac.tables.model_has_roles');
        $roleModel = config('org-rbac.models.role');

        DB::table($pivot)
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->getKey())
            ->where('tenant_id', $tenant->id)
            ->delete();

        if ($roles !== []) {
            $scopeValue = $this->orgRbacResolveAssignDataScopeValue($defaultDataScope);
            $payload = [];

            foreach ($roles as $role) {
                $roleInstance = is_object($role) && is_a($role, $roleModel, true)
                    ? $role
                    : $roleModel::query()->where('name', $role)->where('tenant_id', $tenant->id)->firstOrFail();

                $payload[$roleInstance->id] = [
                    'tenant_id' => $tenant->id,
                    'data_scope' => $scopeValue,
                    'assigned_at' => now(),
                    'assigned_by' => auth()->id(),
                ];
            }

            $this->orgRbacRoles()->syncWithoutDetaching($payload);
        }

        $this->orgRbacForgetPermissionCache($tenant);
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
