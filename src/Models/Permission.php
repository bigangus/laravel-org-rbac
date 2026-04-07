<?php

namespace Zhanghongfei\OrgRbac\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permissions may be global (tenant_id null) or defined per tenant node.
 */
class Permission extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'display_name',
        'description',
        'group',
        'guard_name',
    ];

    public function getTable()
    {
        return config('org-rbac.tables.permissions', parent::getTable());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(config('org-rbac.models.tenant'), 'tenant_id');
    }

    public function roles(): BelongsToMany
    {
        $pivot = config('org-rbac.tables.role_permission');

        return $this->belongsToMany(
            config('org-rbac.models.role'),
            $pivot,
            'permission_id',
            'role_id'
        );
    }

    public function scopeForTenant($query, Tenant|int $tenant)
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->where('tenant_id', $id);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('tenant_id');
    }

    public function scopeInGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeForGuard($query, string $guard = 'web')
    {
        return $query->where('guard_name', $guard);
    }

    public static function findOrCreateForTenant(
        string $name,
        Tenant $tenant,
        ?string $group = null,
        string $guard = 'web'
    ): self {
        return static::query()->firstOrCreate(
            [
                'name' => $name,
                'tenant_id' => $tenant->id,
                'guard_name' => $guard,
            ],
            [
                'group' => $group,
            ]
        );
    }
}
