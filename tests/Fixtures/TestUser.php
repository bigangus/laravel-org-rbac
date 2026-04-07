<?php

namespace Zhanghongfei\OrgRbac\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Zhanghongfei\OrgRbac\Concerns\HasOrgRbacRoles;

class TestUser extends Authenticatable
{
    use HasOrgRbacRoles;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'tenant_id',
    ];

    protected $casts = [
        'is_super_admin' => 'boolean',
    ];
}
