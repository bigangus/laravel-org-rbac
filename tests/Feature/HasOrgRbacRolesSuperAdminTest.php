<?php

namespace Zhanghongfei\OrgRbac\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Models\Permission;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Tests\Fixtures\TestUser;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class HasOrgRbacRolesSuperAdminTest extends TestCase
{
    #[Test]
    public function super_admin_effective_permissions_include_all_permissions(): void
    {
        config(['org-rbac.cache.permissions_ttl_minutes' => 0]);

        $tenant = Tenant::query()->create([
            'name' => 'Org',
            'slug' => 'org-1',
            'parent_id' => null,
        ]);

        Permission::query()->create([
            'tenant_id' => null,
            'name' => 'posts.view',
            'guard_name' => 'web',
        ]);
        Permission::query()->create([
            'tenant_id' => null,
            'name' => 'posts.edit',
            'guard_name' => 'web',
        ]);

        $user = TestUser::query()->create([
            'email' => 'sa@test.local',
            'is_super_admin' => true,
            'tenant_id' => $tenant->id,
        ]);

        $effective = $user->orgRbacEffectivePermissions($tenant);

        $this->assertCount(2, $effective);
        $this->assertTrue($user->hasOrgRbacPermissionInTenant('posts.view', $tenant));
    }
}
