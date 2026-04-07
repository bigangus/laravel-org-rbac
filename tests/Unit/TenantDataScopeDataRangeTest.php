<?php

namespace Zhanghongfei\OrgRbac\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Enums\TenantType;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Support\TenantDataScope;
use Zhanghongfei\OrgRbac\Tests\Fixtures\Post;
use Zhanghongfei\OrgRbac\Tests\TestCase;

/**
 * 数据范围：{@see TenantDataScope} 各分支与列条件。
 */
class TenantDataScopeDataRangeTest extends TestCase
{
    #[Test]
    #[DataProvider('validScopeStringProvider')]
    public function apply_from_string_accepts_each_scope(string $scopeString, string $expectTitleMatch): void
    {
        $t = $this->makeTenant('A', 'tds-'.$scopeString, null);
        Post::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'title' => 'only']);

        $count = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::applyFromString($q, 'tenant_id', $scopeString, $t))->count();
        $this->assertSame(1, $count, $expectTitleMatch);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validScopeStringProvider(): array
    {
        return [
            'self' => ['self', 'self'],
            'department' => ['department', 'department'],
            'subtree' => ['subtree', 'subtree'],
            'tenant' => ['tenant', 'tenant'],
        ];
    }

    #[Test]
    public function apply_subtree_on_leaf_excludes_sibling_branch_posts(): void
    {
        $root = $this->makeTenant('R', 'tds-root', null);
        $b1 = $this->makeTenant('B1', 'tds-b1', $root->id);
        $b2 = $this->makeTenant('B2', 'tds-b2', $root->id);

        Post::withoutGlobalScopes()->create(['tenant_id' => $b1->id, 'title' => 'in-b1']);
        Post::withoutGlobalScopes()->create(['tenant_id' => $b2->id, 'title' => 'in-b2']);

        $count = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Subtree, $b1))->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function apply_department_excludes_parent_node_rows(): void
    {
        $root = $this->makeTenant('PR', 'tds-pr', null);
        $dept = $this->makeTenant('PD', 'tds-pd', $root->id);

        Post::withoutGlobalScopes()->create(['tenant_id' => $root->id]);
        Post::withoutGlobalScopes()->create(['tenant_id' => $dept->id]);

        $count = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Department, $dept))->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function apply_tenant_with_explicit_organisation_root(): void
    {
        $org = $this->makeTenant('EO', 'tds-eo', null);
        $other = $this->makeTenant('EO2', 'tds-eo2', null);

        Post::withoutGlobalScopes()->create(['tenant_id' => $org->id]);
        Post::withoutGlobalScopes()->create(['tenant_id' => $other->id]);

        $count = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Tenant, $org, $org))->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function apply_self_with_empty_string_owner_id_behaves_as_department_only(): void
    {
        $t = $this->makeTenant('S', 'tds-s', null);
        Post::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'user_id' => 99]);

        $count = Post::withoutGlobalScopes()->tap(
            fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Self, $t, null, 'user_id', '')
        )->count();

        $this->assertSame(1, $count);
    }

    #[Test]
    public function apply_self_with_null_owner_id_behaves_as_department_only(): void
    {
        $t = $this->makeTenant('S2', 'tds-s2', null);
        Post::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'user_id' => 1]);

        $count = Post::withoutGlobalScopes()->tap(
            fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Self, $t, null, 'user_id', null)
        )->count();

        $this->assertSame(1, $count);
    }

    #[Test]
    public function subtree_on_org_includes_only_that_subtree_not_other_root(): void
    {
        $o1 = $this->makeTenant('O1', 'tds-o1', null);
        $o2 = $this->makeTenant('O2', 'tds-o2', null);
        Post::withoutGlobalScopes()->create(['tenant_id' => $o1->id]);
        Post::withoutGlobalScopes()->create(['tenant_id' => $o2->id]);

        $count = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Subtree, $o1))->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function apply_tenant_on_org_node_covers_entire_org_subtree(): void
    {
        $org = $this->makeTenant('TO', 'tds-to', null);
        $d = $this->makeTenant('TD', 'tds-td', $org->id);
        Post::withoutGlobalScopes()->create(['tenant_id' => $org->id]);
        Post::withoutGlobalScopes()->create(['tenant_id' => $d->id]);

        $count = Post::withoutGlobalScopes()->tap(fn ($q) => TenantDataScope::apply($q, 'tenant_id', DataScope::Tenant, $org))->count();
        $this->assertSame(2, $count);
    }

    protected function makeTenant(string $name, string $slug, ?int $parentId): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'type' => TenantType::Organisation->value,
        ]);
    }
}
