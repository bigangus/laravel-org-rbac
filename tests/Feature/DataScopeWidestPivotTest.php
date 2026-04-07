<?php

namespace Zhanghongfei\OrgRbac\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Enums\TenantType;
use Zhanghongfei\OrgRbac\Models\Role;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Tests\Fixtures\TestUser;
use Zhanghongfei\OrgRbac\Tests\TestCase;

/**
 * 数据范围：角色 pivot {@see data_scope} 与 {@see HasOrgRbacRoles::orgRbacWidestDataScopeForTenant}。
 */
class DataScopeWidestPivotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['org-rbac.cache.permissions_ttl_minutes' => 0]);
    }

    #[Test]
    public function widest_picks_tenant_over_department_when_two_roles_on_same_tenant(): void
    {
        $tenant = $this->org('W1', 'w1');
        $rDept = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'rd', 'guard_name' => 'web']);
        $rTenant = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'rt', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'w1@test']);
        $user->assignOrgRbacRoleInTenant($rDept, $tenant, DataScope::Department);
        $user->assignOrgRbacRoleInTenant($rTenant, $tenant, DataScope::Tenant);

        $this->assertSame(DataScope::Tenant, $user->orgRbacWidestDataScopeForTenant($tenant));
    }

    #[Test]
    public function widest_picks_subtree_over_self(): void
    {
        $tenant = $this->org('W2', 'w2');
        $r1 = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'rs', 'guard_name' => 'web']);
        $r2 = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'rst', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'w2@test']);
        $user->assignOrgRbacRoleInTenant($r1, $tenant, DataScope::Self);
        $user->assignOrgRbacRoleInTenant($r2, $tenant, DataScope::Subtree);

        $this->assertSame(DataScope::Subtree, $user->orgRbacWidestDataScopeForTenant($tenant));
    }

    #[Test]
    public function widest_across_inherited_chain_uses_max_pivot(): void
    {
        $org = $this->org('W3o', 'w3o');
        $dept = Tenant::query()->create([
            'name' => 'W3d',
            'slug' => 'w3d',
            'parent_id' => $org->id,
            'type' => TenantType::Department->value,
        ]);

        $rOrg = Role::query()->create(['tenant_id' => $org->id, 'name' => 'ro', 'guard_name' => 'web']);
        $rDept = Role::query()->create(['tenant_id' => $dept->id, 'name' => 'rd2', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'w3@test']);
        $user->assignOrgRbacRoleInTenant($rOrg, $org, DataScope::Department);
        $user->assignOrgRbacRoleInTenant($rDept, $dept, DataScope::Tenant);

        $this->assertSame(DataScope::Tenant, $user->orgRbacWidestDataScopeForTenant($dept));
    }

    #[Test]
    public function widest_returns_null_when_all_pivot_scopes_empty_strings(): void
    {
        $tenant = $this->org('W4', 'w4');
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 're', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'w4@test']);
        $user->orgRbacRoles()->syncWithoutDetaching([
            $role->id => [
                'tenant_id' => $tenant->id,
                'data_scope' => '',
                'assigned_at' => now(),
                'assigned_by' => null,
            ],
        ]);

        $this->assertNull($user->orgRbacWidestDataScopeForTenant($tenant));
    }

    #[Test]
    public function assign_role_without_scope_uses_config_default_department(): void
    {
        config(['org-rbac.defaults.assign_role_data_scope' => 'subtree']);

        $tenant = $this->org('W5', 'w5');
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'r5', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'w5@test']);
        $user->assignOrgRbacRoleInTenant($role, $tenant, null);

        $this->assertSame(DataScope::Subtree, $user->orgRbacWidestDataScopeForTenant($tenant));
    }

    #[Test]
    public function widest_from_invalid_string_in_pivot_falls_back_in_merge(): void
    {
        $tenant = $this->org('W6', 'w6');
        $role = Role::query()->create(['tenant_id' => $tenant->id, 'name' => 'r6', 'guard_name' => 'web']);

        $user = TestUser::query()->create(['email' => 'w6@test']);
        $user->orgRbacRoles()->syncWithoutDetaching([
            $role->id => [
                'tenant_id' => $tenant->id,
                'data_scope' => 'not-valid-enum',
                'assigned_at' => now(),
                'assigned_by' => null,
            ],
        ]);

        $this->assertNull($user->orgRbacWidestDataScopeForTenant($tenant));
    }

    protected function org(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => null,
            'type' => TenantType::Organisation->value,
        ]);
    }
}
