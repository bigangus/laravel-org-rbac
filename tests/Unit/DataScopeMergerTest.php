<?php

namespace Zhanghongfei\OrgRbac\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Support\DataScopeMerger;

class DataScopeMergerTest extends TestCase
{
    #[Test]
    public function widest_delegates_to_strings(): void
    {
        $this->assertSame(DataScope::Tenant, DataScopeMerger::widest('self', 'tenant'));
    }

    #[Test]
    public function widest_enums_delegates(): void
    {
        $this->assertSame(DataScope::Subtree, DataScopeMerger::widestEnums(DataScope::Self, DataScope::Subtree));
    }

    #[Test]
    public function widest_empty_pivot_strings_returns_null(): void
    {
        $this->assertNull(DataScopeMerger::widest());
    }
}
