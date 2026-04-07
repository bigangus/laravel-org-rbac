<?php

use Illuminate\Foundation\Auth\User;
use Zhanghongfei\OrgRbac\Models\Permission;
use Zhanghongfei\OrgRbac\Models\Role;
use Zhanghongfei\OrgRbac\Models\Tenant;

return [

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    | Prefix or rename to avoid clashes with existing apps (e.g. Spatie).
    */
    'tables' => [
        'tenants' => 'org_rbac_tenants',
        'roles' => 'org_rbac_roles',
        'permissions' => 'org_rbac_permissions',
        'role_permission' => 'org_rbac_role_has_permissions',
        'model_has_roles' => 'org_rbac_model_has_roles',
        'model_has_permissions' => 'org_rbac_model_has_permissions',
        'tenant_user' => 'org_rbac_tenant_user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Application User model (for membership helpers & foreign keys in app code)
    |--------------------------------------------------------------------------
    */
    'user_model' => env('ORG_RBAC_USER_MODEL', User::class),

    /*
    |--------------------------------------------------------------------------
    | Effective permission cache (null = disable)
    |--------------------------------------------------------------------------
    | Production expects Redis as the default cache store. PHPUnit sets runningUnitTests()
    | and typically disables this check via ORG_RBAC_REQUIRE_REDIS_CACHE=false.
    */
    'cache' => [
        'require_redis' => env('ORG_RBAC_REQUIRE_REDIS_CACHE', true),
        /*
        | When require_redis is true: if strict, boot throws when store is not Redis.
        | If false, only logs (OrgRbacLog) and continues — for staging / degraded mode (not recommended for prod).
        */
        'require_redis_strict' => env('ORG_RBAC_REDIS_STRICT_BOOT', true),
        'permissions_ttl_minutes' => env('ORG_RBAC_PERMISSION_CACHE_TTL', 10),
        'permission_key_prefix' => env('ORG_RBAC_PERMISSION_KEY_PREFIX', 'org-rbac.perm.'),
        'flush_permission_cache_on_tenant_reparent' => env('ORG_RBAC_FLUSH_PERM_CACHE_ON_REPARENT', true),
        /*
        | With Redis, tag-based invalidation is recommended (avoids SCAN on every reparent).
        */
        'use_tagged_permission_cache' => env('ORG_RBAC_PERM_CACHE_USE_TAGS', true),
        'permission_cache_tags' => ['org-rbac-permissions'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Operational & audit-oriented logging (structured context: org_rbac => true)
    |--------------------------------------------------------------------------
    | Point `channel` at a dedicated daily log or syslog for compliance pipelines.
    */
    'logging' => [
        'enabled' => env('ORG_RBAC_LOG_ENABLED', true),
        'channel' => env('ORG_RBAC_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Super admin (users.is_super_admin)
    |--------------------------------------------------------------------------
    | Columns loaded when resolving effective permissions for super admins (avoid SELECT *).
    */
    'super_admin' => [
        'permission_columns' => ['id', 'name', 'guard_name', 'tenant_id', 'group'],
        /*
        | When true, logs once per request per tenant when a super admin resolves effective permissions (compliance).
        */
        'audit_resolution' => env('ORG_RBAC_SUPER_ADMIN_AUDIT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'assign_role_data_scope' => env('ORG_RBAC_DEFAULT_ASSIGN_SCOPE', 'department'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant resolution
    |--------------------------------------------------------------------------
    | Order matters: first non-null result wins.
    | Built-in keys: route_parameter, header, subdomain, authenticated_user.
    |
    | Security: HTTP header tenant id is spoofable unless your edge strips it. Header resolution is **off**
    | unless allow_header_resolution=true. When enabled, header_requires_authentication defaults to true
    | so unauthenticated requests ignore the header (fall through to other strategies).
    */
    'tenant_resolution' => [
        'route_parameter' => 'tenant',
        'header' => env('ORG_RBAC_TENANT_HEADER', 'X-Tenant-ID'),
        'allow_header_resolution' => env('ORG_RBAC_ALLOW_TENANT_HEADER', false),
        'header_requires_authentication' => env('ORG_RBAC_TENANT_HEADER_REQUIRES_AUTH', true),
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
        'tenant' => Tenant::class,
        'role' => Role::class,
        'permission' => Permission::class,
    ],

];
