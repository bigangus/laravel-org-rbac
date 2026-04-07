<?php

namespace Zhanghongfei\OrgRbac\Enums;

/**
 * Row-level data scope. Wider scope sees more rows (when applied via {@see \Zhanghongfei\OrgRbac\Support\TenantDataScope}).
 *
 * Order: Self &lt; Department &lt; Subtree &lt; Tenant.
 */
enum DataScope: string
{
    case Self = 'self';
    case Department = 'department';
    case Subtree = 'subtree';
    case Tenant = 'tenant';

    /**
     * Higher rank = wider access.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Self => 0,
            self::Department => 1,
            self::Subtree => 2,
            self::Tenant => 3,
        };
    }

    public function isWiderThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    /**
     * Pick the widest scope among several (e.g. multiple roles with different pivot `data_scope`).
     */
    public static function widest(self ...$scopes): ?self
    {
        if (count($scopes) === 0) {
            return null;
        }

        return collect($scopes)->sortByDesc(fn (self $s) => $s->rank())->first();
    }

    /**
     * Merge pivot string values; unknown / empty strings are ignored.
     */
    public static function widestFromStrings(?string ...$raw): ?self
    {
        $enums = [];

        foreach ($raw as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $case = self::tryFrom($value);
            if ($case !== null) {
                $enums[] = $case;
            }
        }

        return self::widest(...$enums);
    }
}
