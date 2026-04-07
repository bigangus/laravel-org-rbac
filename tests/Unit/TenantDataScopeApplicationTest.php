<?php

namespace Zhanghongfei\OrgRbac\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Enums\TenantType;
use Zhanghongfei\OrgRbac\Models\Permission;
use Zhanghongfei\OrgRbac\Models\Role;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Support\TenantDataScope;
use Zhanghongfei\OrgRbac\Tests\Fixtures\Post;
use Zhanghongfei\OrgRbac\Tests\Fixtures\TestUser;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class TenantDataScopeApplicationTest extends TestCase
{
    #[Test]
    public function apply_department_filters_current_node_only(): void
    {
        $org = $this->makeTenant('O1', 'o1', null, TenantType::Organisation);
        $dept = $this->makeTenant('D1', 'd1', $org->id, TenantType::Department);

        Post::withoutGlobalScopes()->create(['tenant_id' => $org->id, 'title' => 'a']);
        Post::withoutGlobalScopes()->create(['tenant_id' => $dept->id, 'title' => 'b']);

        $ids = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Department, $dept))->pluck('id')->all();

        $this->assertCount(1, $ids);
        $this->assertSame('b', Post::withoutGlobalScopes()->find($ids[0])->title);
    }

    #[Test]
    public function apply_subtree_includes_descendants(): void
    {
        $org = $this->makeTenant('O2', 'o2', null, TenantType::Organisation);
        $dept = $this->makeTenant('D2', 'd2', $org->id, TenantType::Department);

        Post::withoutGlobalScopes()->create(['tenant_id' => $org->id, 'title' => 'root']);
        Post::withoutGlobalScopes()->create(['tenant_id' => $dept->id, 'title' => 'leaf']);

        $ids = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Subtree, $org))->pluck('id')->sort()->values()->all();
        $titles = Post::withoutGlobalScopes()->whereIn('id', $ids)->orderBy('title')->pluck('title')->all();

        $this->assertSame(['leaf', 'root'], $titles);
    }

    #[Test]
    public function apply_self_without_owner_matches_department_only(): void
    {
        $t = $this->makeTenant('O3', 'o3', null, TenantType::Organisation);
        Post::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'title' => 'x']);

        $count = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Self, $t))->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function apply_self_with_owner_column(): void
    {
        $t = $this->makeTenant('O4', 'o4', null, TenantType::Organisation);
        Post::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'user_id' => 5, 'title' => 'mine']);
        Post::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'user_id' => 9, 'title' => 'other']);

        $count = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Self, $t, null, 'user_id', 5))->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function apply_from_string_unknown_defaults_to_department(): void
    {
        $t = $this->makeTenant('O5', 'o5', null, TenantType::Organisation);
        Post::withoutGlobalScopes()->create(['tenant_id' => $t->id]);

        $sql = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::applyFromString($q, 'tenant_id', 'not-real-scope', $t))->toSql();
        $this->assertStringContainsString('tenant_id', $sql);
    }

    #[Test]
    public function apply_using_widest_throws_without_trait(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $t = $this->makeTenant('O6', 'o6', null, TenantType::Organisation);
        $badUser = new class extends Model {};

        TenantDataScope::applyUsingWidestRoleScopeForUser(Post::withoutGlobalScopes(), 'tenant_id', $badUser, $t);
    }

    #[Test]
    public function apply_using_widest_uses_user_widest_scope(): void
    {
        config(['org-rbac.cache.permissions_ttl_minutes' => 0]);

        $org = $this->makeTenant('O7', 'o7', null, TenantType::Organisation);
        $dept = $this->makeTenant('D7', 'd7', $org->id, TenantType::Department);

        $perm = Permission::query()->create([
            'tenant_id' => null,
            'name' => 'x',
            'guard_name' => 'web',
        ]);
        $role = Role::query()->create([
            'tenant_id' => $org->id,
            'name' => 'r',
            'guard_name' => 'web',
        ]);
        $role->permissions()->attach($perm->id);

        $user = TestUser::query()->create(['email' => 'u7@test', 'is_super_admin' => false]);
        $user->assignOrgRbacRoleInTenant($role, $org, DataScope::Subtree);

        Post::withoutGlobalScopes()->create(['tenant_id' => $org->id]);
        Post::withoutGlobalScopes()->create(['tenant_id' => $dept->id]);

        $q = Post::withoutGlobalScopes();
        TenantDataScope::applyUsingWidestRoleScopeForUser($q, 'tenant_id', $user, $org);

        $this->assertGreaterThanOrEqual(2, $q->count());
    }

    #[Test]
    public function apply_tenant_scope_uses_organisation_subtree(): void
    {
        $org = $this->makeTenant('O8', 'o8', null, TenantType::Organisation);
        $dept = $this->makeTenant('D8', 'd8', $org->id, TenantType::Department);

        Post::withoutGlobalScopes()->create(['tenant_id' => $org->id]);
        Post::withoutGlobalScopes()->create(['tenant_id' => $dept->id]);

        $count = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Tenant, $dept))->count();
        $this->assertSame(2, $count);
    }

    protected function makeTenant(string $name, string $slug, ?int $parentId, TenantType $type): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'type' => $type->value,
        ]);
    }
}
