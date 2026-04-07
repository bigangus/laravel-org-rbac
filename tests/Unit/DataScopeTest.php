<?php

namespace Zhanghongfei\OrgRbac\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zhanghongfei\OrgRbac\Enums\DataScope;

class DataScopeTest extends TestCase
{
    #[Test]
    public function rank_orders_from_narrow_to_wide(): void
    {
        // PHPUnit: assertLessThan($expected, $actual) asserts $actual < $expected.
        $this->assertLessThan(DataScope::Subtree->rank(), DataScope::Department->rank());
        $this->assertLessThan(DataScope::Tenant->rank(), DataScope::Subtree->rank());
    }

    #[Test]
    public function widest_picks_maximum_rank(): void
    {
        $w = DataScope::widest(DataScope::Self, DataScope::Tenant, DataScope::Department);
        $this->assertSame(DataScope::Tenant, $w);
    }

    #[Test]
    public function widest_from_strings_ignores_invalid(): void
    {
        $w = DataScope::widestFromStrings('self', '', 'tenant', 'nope');
        $this->assertSame(DataScope::Tenant, $w);
    }
}
