<?php

namespace Zhanghongfei\OrgRbac\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Enums\TenantType;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;
use Zhanghongfei\OrgRbac\Tests\Fixtures\Post;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class TenantScopeAndBelongsToTenantTest extends TestCase
{
    #[Test]
    public function tenant_scope_with_strict_and_no_bound_tenant_returns_no_rows(): void
    {
        config(['org-rbac.strict_tenant_scope' => true]);

        $t = Tenant::query()->create([
            'name' => 'S1',
            'slug' => 's1',
            'parent_id' => null,
            'type' => TenantType::Organisation->value,
        ]);

        Post::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'title' => 'p']);

        app(CurrentTenant::class)->clear();

        $this->assertSame(0, Post::query()->count());
    }

    #[Test]
    public function tenant_scope_when_strict_false_and_no_tenant_does_not_force_empty(): void
    {
        config(['org-rbac.strict_tenant_scope' => false]);

        $t = Tenant::query()->create([
            'name' => 'S2',
            'slug' => 's2',
            'parent_id' => null,
            'type' => TenantType::Organisation->value,
        ]);

        Post::withoutGlobalScopes()->create(['tenant_id' => $t->id]);

        app(CurrentTenant::class)->clear();

        $this->assertGreaterThanOrEqual(1, Post::query()->count());
    }

    #[Test]
    public function tenant_scope_filters_by_current_tenant_id(): void
    {
        config(['org-rbac.strict_tenant_scope' => true]);

        $a = Tenant::query()->create(['name' => 'A', 'slug' => 'sa', 'parent_id' => null, 'type' => TenantType::Organisation->value]);
        $b = Tenant::query()->create(['name' => 'B', 'slug' => 'sb', 'parent_id' => null, 'type' => TenantType::Organisation->value]);

        Post::withoutGlobalScopes()->create(['tenant_id' => $a->id]);
        Post::withoutGlobalScopes()->create(['tenant_id' => $b->id]);

        app(CurrentTenant::class)->set($a);

        $this->assertSame(1, Post::query()->count());
        $this->assertSame($a->id, (int) Post::query()->value('tenant_id'));
    }

    #[Test]
    public function belongs_to_tenant_sets_tenant_id_on_create_from_current_tenant(): void
    {
        config(['org-rbac.strict_tenant_scope' => true]);

        $t = Tenant::query()->create(['name' => 'C', 'slug' => 'sc', 'parent_id' => null, 'type' => TenantType::Organisation->value]);
        app(CurrentTenant::class)->set($t);

        $post = Post::query()->create(['title' => 'auto']);

        $this->assertSame((int) $t->id, (int) $post->tenant_id);
    }

    #[Test]
    public function belongs_to_tenant_respects_explicit_tenant_id(): void
    {
        config(['org-rbac.strict_tenant_scope' => true]);

        $a = Tenant::query()->create(['name' => 'D', 'slug' => 'sd', 'parent_id' => null, 'type' => TenantType::Organisation->value]);
        $b = Tenant::query()->create(['name' => 'E', 'slug' => 'se', 'parent_id' => null, 'type' => TenantType::Organisation->value]);

        app(CurrentTenant::class)->set($a);

        $post = Post::query()->create(['tenant_id' => $b->id, 'title' => 'explicit']);

        $this->assertSame((int) $b->id, (int) $post->tenant_id);
    }

    #[Test]
    public function current_tenant_clear_removes_binding(): void
    {
        $t = Tenant::query()->create(['name' => 'F', 'slug' => 'sf', 'parent_id' => null, 'type' => TenantType::Organisation->value]);
        $ct = app(CurrentTenant::class);
        $ct->set($t);
        $this->assertTrue($ct->check());
        $ct->clear();
        $this->assertFalse($ct->check());
        $this->assertNull($ct->get());
    }

    #[Test]
    public function current_tenant_id_matches_model(): void
    {
        $t = Tenant::query()->create(['name' => 'G', 'slug' => 'sg', 'parent_id' => null, 'type' => TenantType::Organisation->value]);
        $ct = app(CurrentTenant::class);
        $ct->set($t);
        $this->assertSame((int) $t->id, $ct->id());
    }
}
