<?php

namespace Zhanghongfei\OrgRbac\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zhanghongfei\OrgRbac\Enums\DataScope;

class DataScopeMoreTest extends TestCase
{
    #[Test]
    public function try_from_returns_null_for_invalid_string(): void
    {
        $this->assertNull(DataScope::tryFrom('not-a-scope'));
    }

    #[Test]
    public function widest_from_empty_returns_null(): void
    {
        $this->assertNull(DataScope::widest());
    }

    #[Test]
    public function widest_from_strings_all_invalid_returns_null(): void
    {
        $this->assertNull(DataScope::widestFromStrings('bad', '', null));
    }

    #[Test]
    public function is_wider_than_department_vs_subtree(): void
    {
        $this->assertTrue(DataScope::Subtree->isWiderThan(DataScope::Department));
        $this->assertFalse(DataScope::Department->isWiderThan(DataScope::Subtree));
    }

    #[Test]
    public function is_wider_than_self_vs_tenant(): void
    {
        $this->assertTrue(DataScope::Tenant->isWiderThan(DataScope::Self));
    }

    #[Test]
    #[DataProvider('widestPairProvider')]
    public function widest_two_enums_picks_expected(DataScope $a, DataScope $b, DataScope $expected): void
    {
        $this->assertSame($expected, DataScope::widest($a, $b));
    }

    /**
     * @return array<string, array{DataScope, DataScope, DataScope}>
     */
    public static function widestPairProvider(): array
    {
        return [
            'self department' => [DataScope::Self, DataScope::Department, DataScope::Department],
            'subtree tenant' => [DataScope::Subtree, DataScope::Tenant, DataScope::Tenant],
            'same twice' => [DataScope::Department, DataScope::Department, DataScope::Department],
        ];
    }

    #[Test]
    public function rank_self_is_zero(): void
    {
        $this->assertSame(0, DataScope::Self->rank());
    }

    #[Test]
    public function rank_tenant_is_three(): void
    {
        $this->assertSame(3, DataScope::Tenant->rank());
    }
}
