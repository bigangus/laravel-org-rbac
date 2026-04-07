<?php

namespace Zhanghongfei\OrgRbac\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Zhanghongfei\OrgRbac\Concerns\BelongsToTenant;

class Department extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
    ];

    public function getTable()
    {
        return config('org-rbac.tables.departments', parent::getTable());
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(config('org-rbac.models.role'), 'department_id');
    }
}
