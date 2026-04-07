<?php

namespace Zhanghongfei\OrgRbac\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Global permission catalog (no tenant_id). Actual tenant isolation is enforced
 * via roles that belong to a tenant.
 */
class Permission extends Model
{
    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function getTable()
    {
        return config('org-rbac.tables.permissions', parent::getTable());
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
}
