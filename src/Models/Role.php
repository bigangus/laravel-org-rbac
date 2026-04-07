<?php

namespace Zhanghongfei\OrgRbac\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Zhanghongfei\OrgRbac\Concerns\BelongsToTenant;

class Role extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'department_id',
        'name',
        'guard_name',
    ];

    public function getTable()
    {
        return config('org-rbac.tables.roles', parent::getTable());
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(config('org-rbac.models.department'), 'department_id');
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
}
