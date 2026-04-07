<?php

namespace Zhanghongfei\OrgRbac\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function getTable()
    {
        return config('org-rbac.tables.tenants', parent::getTable());
    }

    public function departments(): HasMany
    {
        return $this->hasMany(config('org-rbac.models.department'), 'tenant_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(config('org-rbac.models.role'), 'tenant_id');
    }
}
