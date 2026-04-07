<?php

namespace Zhanghongfei\OrgRbac\Support;

use Zhanghongfei\OrgRbac\Enums\DataScope;

/**
 * Discoverable alias for {@see DataScope::widest()} / {@see DataScope::widestFromStrings()}.
 */
final class DataScopeMerger
{
    public static function widest(?string ...$pivotDataScopes): ?DataScope
    {
        return DataScope::widestFromStrings(...$pivotDataScopes);
    }

    public static function widestEnums(DataScope ...$scopes): ?DataScope
    {
        return DataScope::widest(...$scopes);
    }
}
