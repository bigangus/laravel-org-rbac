<?php

namespace Zhanghongfei\OrgRbac\Concerns;

use Illuminate\Database\Eloquent\Model;
use Zhanghongfei\OrgRbac\Scopes\TenantScope;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;

/**
 * @property int|null $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(
            'tenant',
            app(TenantScope::class),
        );

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }
            $current = app(CurrentTenant::class);
            if ($current->id() !== null) {
                $model->setAttribute('tenant_id', $current->id());
            }
        });
    }

    public function tenant()
    {
        $class = config('org-rbac.models.tenant');

        return $this->belongsTo($class, 'tenant_id');
    }
}
