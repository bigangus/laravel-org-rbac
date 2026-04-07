<?php

namespace Zhanghongfei\OrgRbac\Support;

use Illuminate\Database\Eloquent\Builder;
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Models\Tenant;

/**
 * Apply row-level constraints when business tables use a `tenant_id` FK into
 * {@see Tenant} (organisation / department / team nodes).
 *
 * Typical usage (after {@see CurrentTenant} is bound):
 *
 * `TenantDataScope::apply($query, 'tenant_id', DataScope::Subtree, $tenant);`
 */
final class TenantDataScope
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  string  $tenantColumn  Business column name (usually {@code tenant_id})
     * @param  Tenant  $context  Current tenant node (e.g. from {@see CurrentTenant})
     * @param  Tenant|null  $organisationRoot  Force org root for {@see DataScope::Tenant}; if null, uses {@see Tenant::nearestOrganisationAncestor()} or {@see Tenant::rootAncestor()}
     * @param  string|null  $ownerColumn  For {@see DataScope::Self}: e.g. {@code user_id} / {@code created_by}
     * @param  int|string|null  $ownerId  Current user id when using owner column
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function apply(
        Builder $query,
        string $tenantColumn,
        DataScope $scope,
        Tenant $context,
        ?Tenant $organisationRoot = null,
        ?string $ownerColumn = null,
        int|string|null $ownerId = null,
    ): Builder {
        return match ($scope) {
            DataScope::Self => static::applySelf($query, $tenantColumn, $context, $ownerColumn, $ownerId),
            DataScope::Department => $query->where($tenantColumn, $context->id),
            DataScope::Subtree => $query->whereIn($tenantColumn, $context->subtreeIds()),
            DataScope::Tenant => static::applyTenant($query, $tenantColumn, $context, $organisationRoot),
        };
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function applyFromString(
        Builder $query,
        string $tenantColumn,
        string $scopeValue,
        Tenant $context,
        ?Tenant $organisationRoot = null,
        ?string $ownerColumn = null,
        int|string|null $ownerId = null,
    ): Builder {
        $scope = DataScope::tryFrom($scopeValue) ?? DataScope::Department;

        return static::apply($query, $tenantColumn, $scope, $context, $organisationRoot, $ownerColumn, $ownerId);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected static function applySelf(
        Builder $query,
        string $tenantColumn,
        Tenant $context,
        ?string $ownerColumn,
        int|string|null $ownerId,
    ): Builder {
        if ($ownerColumn !== null && $ownerId !== null && $ownerId !== '') {
            return $query
                ->where($tenantColumn, $context->id)
                ->where($ownerColumn, $ownerId);
        }

        return $query->where($tenantColumn, $context->id);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected static function applyTenant(
        Builder $query,
        string $tenantColumn,
        Tenant $context,
        ?Tenant $organisationRoot,
    ): Builder {
        $root = $organisationRoot
            ?? $context->nearestOrganisationAncestor()
            ?? $context->rootAncestor();

        return $query->whereIn($tenantColumn, $root->subtreeIds());
    }
}
