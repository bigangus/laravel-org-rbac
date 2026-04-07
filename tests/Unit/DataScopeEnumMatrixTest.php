<?php

namespace Zhanghongfei\OrgRbac\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Support\DataScopeMerger;

/**
 * 数据范围枚举：全序、字符串解析、合并矩阵。
 */
class DataScopeEnumMatrixTest extends TestCase
{
    #[Test]
    #[DataProvider('allCasesOrderedByRank')]
    public function rank_is_strictly_increasing(DataScope $narrower, DataScope $wider): void
    {
        $this->assertLessThan($wider->rank(), $narrower->rank());
    }

    /**
     * @return array<string, array{DataScope, DataScope}>
     */
    public static function allCasesOrderedByRank(): array
    {
        return [
            'self_department' => [DataScope::Self, DataScope::Department],
            'department_subtree' => [DataScope::Department, DataScope::Subtree],
            'subtree_tenant' => [DataScope::Subtree, DataScope::Tenant],
        ];
    }

    #[Test]
    public function widest_from_strings_all_four_picks_tenant(): void
    {
        $w = DataScope::widestFromStrings('self', 'department', 'subtree', 'tenant');
        $this->assertSame(DataScope::Tenant, $w);
    }

    #[Test]
    public function merger_widest_matches_enum_widest(): void
    {
        $this->assertSame(
            DataScope::widest(DataScope::Department, DataScope::Subtree),
            DataScopeMerger::widestEnums(DataScope::Department, DataScope::Subtree)
        );
    }

    #[Test]
    public function merger_widest_strings_department_and_subtree(): void
    {
        $this->assertSame(DataScope::Subtree, DataScopeMerger::widest('department', 'subtree'));
    }

    #[Test]
    public function try_from_accepts_all_case_values(): void
    {
        foreach (DataScope::cases() as $case) {
            $this->assertSame($case, DataScope::tryFrom($case->value));
        }
    }

    #[Test]
    public function is_wider_than_reflexive_false(): void
    {
        $this->assertFalse(DataScope::Tenant->isWiderThan(DataScope::Tenant));
    }
}
