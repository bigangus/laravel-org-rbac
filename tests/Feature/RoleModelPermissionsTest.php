<?php

namespace Zhanghongfei\OrgRbac\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Enums\TenantType;
use Zhanghongfei\OrgRbac\Models\Permission;
use Zhanghongfei\OrgRbac\Models\Role;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class RoleModelPermissionsTest extends TestCase
{
    #[Test]
    public function give_permission_to_attaches(): void
    {
        $t = Tenant::query()->create(['name' => 'Rp', 'slug' => 'rp', 'parent_id' => null, 'type' => TenantType::Organisation->value]);
        $role = Role::query()->create(['tenant_id' => $t->id, 'name' => 'r', 'guard_name' => 'web']);
        $p = Permission::query()->create(['tenant_id' => null, 'name' => 'perm', 'guard_name' => 'web']);

        $role->givePermissionTo($p);

        $this->assertTrue($role->hasPermission('perm'));
    }

    #[Test]
    public function revoke_permission_to_detaches(): void
    {
        $t = Tenant::query()->create(['name' => 'Rq', 'slug' => 'rq', 'parent_id' => null, 'type' => TenantType::Organisation->value]);
        $role = Role::query()->create(['tenant_id' => $t->id, 'name' => 'r2', 'guard_name' => 'web']);
        $p = Permission::query()->create(['tenant_id' => null, 'name' => 'p2', 'guard_name' => 'web']);
        $role->givePermissionTo($p);
        $role->revokePermissionTo($p);

        $this->assertFalse($role->hasPermission('p2'));
    }

    #[Test]
    public function sync_permissions_replaces(): void
    {
        $t = Tenant::query()->create(['name' => 'Rr', 'slug' => 'rr', 'parent_id' => null, 'type' => TenantType::Organisation->value]);
        $role = Role::query()->create(['tenant_id' => $t->id, 'name' => 'r3', 'guard_name' => 'web']);
        $a = Permission::query()->create(['tenant_id' => null, 'name' => 'pa', 'guard_name' => 'web']);
        $b = Permission::query()->create(['tenant_id' => null, 'name' => 'pb', 'guard_name' => 'web']);

        $role->givePermissionTo($a);
        $role->syncPermissions([$b]);

        $this->assertFalse($role->hasPermission('pa'));
        $this->assertTrue($role->hasPermission('pb'));
    }

    #[Test]
    public function scope_for_tenant_filters(): void
    {
        $a = Tenant::query()->create(['name' => 'Ta', 'slug' => 'ta', 'parent_id' => null, 'type' => TenantType::Organisation->value]);
        $b = Tenant::query()->create(['name' => 'Tb', 'slug' => 'tb', 'parent_id' => null, 'type' => TenantType::Organisation->value]);
        Role::query()->create(['tenant_id' => $a->id, 'name' => 'x', 'guard_name' => 'web']);
        Role::query()->create(['tenant_id' => $b->id, 'name' => 'y', 'guard_name' => 'web']);

        $this->assertSame(1, Role::query()->forTenant($a)->count());
    }
}
