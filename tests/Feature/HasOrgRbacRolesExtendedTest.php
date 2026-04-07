<?php

namespace Zhanghongfei\OrgRbac\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Enums\TenantType;
use Zhanghongfei\OrgRbac\Models\Permission;
use Zhanghongfei\OrgRbac\Models\Role;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;
use Zhanghongfei\OrgRbac\Tests\Fixtures\TestUser;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class HasOrgRbacRolesExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['org-rbac.cache.permissions_ttl_minutes' => 0]);
    }

    #[Test]
    public function assign_role_by_name_and_permission_check(): void
    {
        $tenant = $this->makeOrg('R1', 'r1');
        $perm = Permission::query()->create(['tenant_id' => null, 'name' => 'a.b', 'guard_name' => 'web']);
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'worker', 'guard_name' => 'web']);
        $role->permissions()->attach($perm->id);

        $user = TestUser::query()->create(['email' => 'e1@test', 'is_super_admin' => false]);
        $user->assignOrgRbacRoleInTenant('worker', $tenant);

        $this->assertTrue($user->hasOrgRbacPermissionInTenant('a.b', $tenant));
    }

    #[Test]
    public function has_any_permission_in_tenant(): void
    {
        $tenant = $this->makeOrg('R2', 'r2');
        $p1 = Permission::query()->create(['tenant_id' => null, 'name' => 'p1', 'guard_name' => 'web']);
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'r', 'guard_name' => 'web']);
        $role->permissions()->attach($p1->id);

        $user = TestUser::query()->create(['email' => 'e2@test']);
        $user->assignOrgRbacRoleInTenant($role, $tenant);

        $this->assertTrue($user->hasOrgRbacAnyPermissionInTenant(['missing', 'p1'], $tenant));
        $this->assertFalse($user->hasOrgRbacAnyPermissionInTenant(['x', 'y'], $tenant));
    }

    #[Test]
    public function has_all_permissions_in_tenant(): void
    {
        $tenant = $this->makeOrg('R3', 'r3');
        $p1 = Permission::query()->create(['tenant_id' => null, 'name' => 'a', 'guard_name' => 'web']);
        $p2 = Permission::query()->create(['tenant_id' => null, 'name' => 'b', 'guard_name' => 'web']);
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'r3', 'guard_name' => 'web']);
        $role->permissions()->attach([$p1->id, $p2->id]);

        $user = TestUser::query()->create(['email' => 'e3@test']);
        $user->assignOrgRbacRoleInTenant($role, $tenant);

        $this->assertTrue($user->hasOrgRbacAllPermissionsInTenant(['a', 'b'], $tenant));
        $this->assertFalse($user->hasOrgRbacAllPermissionsInTenant(['a', 'c'], $tenant));
    }

    #[Test]
    public function inherited_role_permission_on_child_tenant(): void
    {
        $org = $this->makeOrg('R4o', 'r4o');
        $dept = Tenant::query()->create([
            'name' => 'R4d',
            'slug' => 'r4d',
            'parent_id' => $org->id,
            'type' => TenantType::Department->value,
        ]);

        $perm = Permission::query()->create(['tenant_id' => null, 'name' => 'inherited', 'guard_name' => 'web']);
        $role = Role::query()->create(['tenant_id' => $org->id, 'name' => 'orgrole', 'guard_name' => 'web']);
        $role->permissions()->attach($perm->id);

        $user = TestUser::query()->create(['email' => 'e4@test']);
        $user->assignOrgRbacRoleInTenant($role, $org, DataScope::Tenant);

        $this->assertTrue($user->hasOrgRbacPermissionInTenant('inherited', $dept));
    }

    #[Test]
    public function direct_permission_on_tenant(): void
    {
        $tenant = $this->makeOrg('R5', 'r5');
        $perm = Permission::query()->create(['tenant_id' => null, 'name' => 'direct', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'e5@test']);
        $user->orgRbacDirectPermissions()->attach($perm->id, ['tenant_id' => $tenant->id]);

        $this->assertTrue($user->hasOrgRbacPermissionInTenant('direct', $tenant));
    }

    #[Test]
    public function remove_role_removes_permissions_from_effective(): void
    {
        $tenant = $this->makeOrg('R6', 'r6');
        $perm = Permission::query()->create(['tenant_id' => null, 'name' => 'gone', 'guard_name' => 'web']);
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'toremove', 'guard_name' => 'web']);
        $role->permissions()->attach($perm->id);

        $user = TestUser::query()->create(['email' => 'e6@test']);
        $user->assignOrgRbacRoleInTenant($role, $tenant);
        $this->assertTrue($user->hasOrgRbacPermissionInTenant('gone', $tenant));

        $user->removeOrgRbacRoleInTenant($role, $tenant);
        $this->assertFalse($user->hasOrgRbacPermissionInTenant('gone', $tenant));
    }

    #[Test]
    public function sync_roles_replaces_set_for_tenant(): void
    {
        $tenant = $this->makeOrg('R7', 'r7');
        $r1 = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 's1', 'guard_name' => 'web']);
        $r2 = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 's2', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'e7@test']);
        $user->assignOrgRbacRoleInTenant($r1, $tenant);
        $this->assertTrue($user->hasOrgRbacRoleInTenant('s1', $tenant));

        $user->syncOrgRbacRolesInTenant(['s2'], $tenant);
        $this->assertFalse($user->hasOrgRbacRoleInTenant('s1', $tenant));
        $this->assertTrue($user->hasOrgRbacRoleInTenant('s2', $tenant));
    }

    #[Test]
    public function sync_roles_empty_removes_all_roles_in_tenant(): void
    {
        $tenant = $this->makeOrg('R8', 'r8');
        $r = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'only', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'e8@test']);
        $user->assignOrgRbacRoleInTenant($r, $tenant);
        $user->syncOrgRbacRolesInTenant([], $tenant);

        $this->assertFalse($user->hasOrgRbacRoleInTenant('only', $tenant));
    }

    #[Test]
    public function org_rbac_widest_data_scope_for_tenant_from_pivot(): void
    {
        $tenant = $this->makeOrg('R9', 'r9');
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'w', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'e9@test']);
        $user->assignOrgRbacRoleInTenant($role, $tenant, DataScope::Subtree);

        $this->assertSame(DataScope::Subtree, $user->orgRbacWidestDataScopeForTenant($tenant));
    }

    #[Test]
    public function has_org_rbac_permission_uses_current_tenant_from_container(): void
    {
        $tenant = $this->makeOrg('R10', 'r10');
        $perm = Permission::query()->create(['tenant_id' => null, 'name' => 'ct', 'guard_name' => 'web']);
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'cr', 'guard_name' => 'web']);
        $role->permissions()->attach($perm->id);

        $user = TestUser::query()->create(['email' => 'e10@test']);
        $user->assignOrgRbacRoleInTenant($role, $tenant);

        app(CurrentTenant::class)->set($tenant);
        Auth::login($user);

        $this->assertTrue($user->hasOrgRbacPermission('ct'));
    }

    #[Test]
    public function has_org_rbac_permission_false_without_current_tenant(): void
    {
        $tenant = $this->makeOrg('R11', 'r11');
        $perm = Permission::query()->create(['tenant_id' => null, 'name' => 'nope', 'guard_name' => 'web']);
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'cr2', 'guard_name' => 'web']);
        $role->permissions()->attach($perm->id);

        $user = TestUser::query()->create(['email' => 'e11@test']);
        $user->assignOrgRbacRoleInTenant($role, $tenant);

        app(CurrentTenant::class)->clear();
        Auth::login($user);

        $this->assertFalse($user->hasOrgRbacPermission('nope'));
    }

    #[Test]
    public function org_rbac_permission_names_in_current_tenant(): void
    {
        $tenant = $this->makeOrg('R12', 'r12');
        $perm = Permission::query()->create(['tenant_id' => null, 'name' => 'nm', 'guard_name' => 'web']);
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'cr3', 'guard_name' => 'web']);
        $role->permissions()->attach($perm->id);

        $user = TestUser::query()->create(['email' => 'e12@test']);
        $user->assignOrgRbacRoleInTenant($role, $tenant);

        app(CurrentTenant::class)->set($tenant);
        Auth::login($user);

        $this->assertTrue($user->orgRbacPermissionNamesInCurrentTenant()->contains('nm'));
    }

    #[Test]
    public function join_and_owner_and_leave(): void
    {
        $tenant = $this->makeOrg('R13', 'r13');
        $user = TestUser::query()->create(['email' => 'e13@test']);

        $user->joinOrgRbacTenant($tenant, true);
        $this->assertTrue($user->orgRbacIsOwnerOfTenant($tenant));

        $user->leaveOrgRbacTenant($tenant);
        $this->assertFalse($user->orgRbacTenants()->whereKey($tenant->id)->exists());
    }

    #[Test]
    public function has_inherited_role_in_tenant(): void
    {
        $org = $this->makeOrg('R14o', 'r14o');
        $dept = Tenant::query()->create([
            'name' => 'R14d',
            'slug' => 'r14d',
            'parent_id' => $org->id,
            'type' => TenantType::Department->value,
        ]);
        $role = Role::query()->create(['tenant_id' => $org->id, 'name' => 'shared', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'e14@test']);
        $user->assignOrgRbacRoleInTenant($role, $org);

        $this->assertTrue($user->hasOrgRbacInheritedRoleInTenant('shared', $dept));
    }

    protected function makeOrg(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => null,
            'type' => TenantType::Organisation->value,
        ]);
    }
}
