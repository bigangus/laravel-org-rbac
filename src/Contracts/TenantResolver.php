<?php

namespace Zhanghongfei\OrgRbac\Contracts;

use Illuminate\Http\Request;
use Zhanghongfei\OrgRbac\Models\Tenant;

interface TenantResolver
{
    public function resolve(?Request $request = null): ?Tenant;
}
