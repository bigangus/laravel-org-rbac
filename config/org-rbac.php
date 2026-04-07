<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    | Prefix or rename to avoid clashes with existing apps (e.g. Spatie).
    */
    'tables' => [
        'tenants' => 'org_rbac_tenants',
        'departments' => 'org_rbac_departments',
        'roles' => 'org_rbac_roles',
        'permissions' => 'org_rbac_permissions',
        'role_permission' => 'org_rbac_role_has_permissions',
        'model_has_roles' => 'org_rbac_model_has_roles',
        'model_has_permissions' => 'org_rbac_model_has_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant resolution
    |--------------------------------------------------------------------------
    | Order matters: first non-null result wins.
    | Built-in keys: route_parameter, header, subdomain, authenticated_user.
    */
    'tenant_resolution' => [
        'route_parameter' => 'tenant',
        'header' => 'X-Tenant-ID',
        'subdomain' => false,
        'authenticated_user_column' => 'tenant_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */
    /*
    | When true, models using BelongsToTenant return no rows if no tenant is bound.
    | Set false only for consoles/tests where you rely on withoutGlobalScopes().
    */
    'strict_tenant_scope' => env('ORG_RBAC_STRICT_TENANT_SCOPE', true),

    'middleware' => [
        'alias' => 'org-rbac.tenant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models (override in AppServiceProvider if needed)
    |--------------------------------------------------------------------------
    */
    'models' => [
        'tenant' => Zhanghongfei\OrgRbac\Models\Tenant::class,
        'department' => Zhanghongfei\OrgRbac\Models\Department::class,
        'role' => Zhanghongfei\OrgRbac\Models\Role::class,
        'permission' => Zhanghongfei\OrgRbac\Models\Permission::class,
    ],

];

