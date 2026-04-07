<?php

namespace Zhanghongfei\OrgRbac\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Enums\TenantType;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class TenantModelHierarchyTest extends TestCase
{
    #[Test]
    public function descendants_returns_only_children_subtree(): void
    {
        $root = $this->tenant('T1', 't1', null);
        $c1 = $this->tenant('T1a', 't1a', $root->id);
        $c2 = $this->tenant('T1b', 't1b', $root->id);

        $ids = $root->descendants()->pluck('id')->sort()->values()->all();
        $this->assertSame([$c1->id, $c2->id], $ids);
    }

    #[Test]
    public function ancestors_ordered_from_root(): void
    {
        $root = $this->tenant('T2', 't2', null);
        $mid = $this->tenant('T2m', 't2m', $root->id);
        $leaf = $this->tenant('T2l', 't2l', $mid->id);

        $chain = $leaf->ancestors()->pluck('id')->all();
        $this->assertSame([(int) $root->id, (int) $mid->id], array_map('intval', $chain));
    }

    #[Test]
    public function breadcrumb_includes_self(): void
    {
        $root = $this->tenant('T3', 't3', null);
        $leaf = $this->tenant('T3l', 't3l', $root->id);

        $this->assertTrue($leaf->breadcrumb()->last()->is($leaf));
    }

    #[Test]
    public function subtree_ids_includes_self(): void
    {
        $root = $this->tenant('T4', 't4', null);
        $child = $this->tenant('T4c', 't4c', $root->id);

        $ids = $root->subtreeIds();
        $this->assertContains($root->id, $ids);
        $this->assertContains($child->id, $ids);
    }

    #[Test]
    public function is_descendant_of_and_ancestor_of(): void
    {
        $root = $this->tenant('T5', 't5', null);
        $child = $this->tenant('T5c', 't5c', $root->id);

        $this->assertTrue($child->isDescendantOf($root));
        $this->assertTrue($root->isAncestorOf($child));
        $this->assertTrue($child->isSameOrDescendantOf($root));
    }

    #[Test]
    public function is_root_and_is_leaf(): void
    {
        $root = $this->tenant('T6', 't6', null);
        $leaf = $this->tenant('T6l', 't6l', $root->id);

        $this->assertTrue($root->isRoot());
        $this->assertTrue($leaf->isLeaf());
        $this->assertFalse($root->isLeaf());
    }

    #[Test]
    public function scope_roots_filters(): void
    {
        $this->tenant('T7a', 't7a', null);
        $this->tenant('T7b', 't7b', null);
        $this->tenant('T7c', 't7c', Tenant::query()->where('slug', 't7a')->first()->id);

        $this->assertSame(2, Tenant::query()->roots()->count());
    }

    #[Test]
    public function root_ancestor_from_deep_node(): void
    {
        $root = $this->tenant('T8', 't8', null);
        $mid = $this->tenant('T8m', 't8m', $root->id);
        $leaf = $this->tenant('T8l', 't8l', $mid->id);

        $this->assertTrue($leaf->rootAncestor()->is($root));
    }

    protected function tenant(string $name, string $slug, ?int $parentId): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'type' => TenantType::Organisation->value,
        ]);
    }
}
