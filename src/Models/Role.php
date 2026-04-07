<?php

namespace Zhanghongfei\OrgRbac\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role is scoped to one node in the tenant tree (organisation / department / …).
 * Query with explicit scopes — no global BelongsToTenant scope (inheritance spans ancestors).
 */
class Role extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'display_name',
        'description',
        'guard_name',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function getTable()
    {
        return config('org-rbac.tables.roles', parent::getTable());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(config('org-rbac.models.tenant'), 'tenant_id');
    }

    public function permissions(): BelongsToMany
    {
        $pivot = config('org-rbac.tables.role_permission');

        return $this->belongsToMany(
            config('org-rbac.models.permission'),
            $pivot,
            'role_id',
            'permission_id'
        );
    }

    /**
     * @param  string|\Zhanghongfei\OrgRbac\Models\Permission  ...$permissions
     */
    public function givePermissionTo(...$permissions): self
    {
        $ids = collect($permissions)->map(function ($p) {
            if ($p instanceof Permission) {
                return $p->id;
            }

            return $this->resolvePermissionIdByName((string) $p);
        });

        $this->permissions()->syncWithoutDetaching($ids->all());

        return $this;
    }

    /**
     * @param  string|\Zhanghongfei\OrgRbac\Models\Permission  ...$permissions
     */
    public function revokePermissionTo(...$permissions): self
    {
        $ids = collect($permissions)->map(function ($p) {
            if ($p instanceof Permission) {
                return $p->id;
            }

            return $this->resolvePermissionIdByName((string) $p);
        });

        $this->permissions()->detach($ids);

        return $this;
    }

    /**
     * @param  array<int, string|\Zhanghongfei\OrgRbac\Models\Permission>  $permissions
     */
    public function syncPermissions(array $permissions): self
    {
        $ids = collect($permissions)->map(function ($p) {
            if ($p instanceof Permission) {
                return $p->id;
            }

            return $this->resolvePermissionIdByName((string) $p);
        });

        $this->permissions()->sync($ids->all());

        return $this;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }

    public function scopeForTenant($query, Tenant|int $tenant)
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->where('tenant_id', $id);
    }

    public function scopeForGuard($query, string $guard = 'web')
    {
        return $query->where('guard_name', $guard);
    }

    protected function resolvePermissionIdByName(string $name): int
    {
        $permissionModel = config('org-rbac.models.permission');

        return (int) $permissionModel::query()
            ->where('name', $name)
            ->where(function ($q) {
                $q->whereNull('tenant_id')
                    ->orWhere('tenant_id', $this->tenant_id);
            })
            ->where('guard_name', $this->guard_name ?? 'web')
            ->firstOrFail()
            ->id;
    }
}
