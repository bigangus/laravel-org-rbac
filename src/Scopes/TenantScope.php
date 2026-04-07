<?php

namespace Zhanghongfei\OrgRbac\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;

class TenantScope implements Scope
{
    public function __construct(
        protected CurrentTenant $currentTenant,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = $this->currentTenant->id();

        if (config('org-rbac.strict_tenant_scope', true) && $tenantId === null) {
            $builder->whereRaw('0 = 1');

            return;
        }

        if ($tenantId === null) {
            return;
        }

        $builder->where($model->getTable().'.tenant_id', $tenantId);
    }
}
