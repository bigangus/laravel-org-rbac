<?php

namespace Zhanghongfei\OrgRbac\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Zhanghongfei\OrgRbac\Contracts\TenantResolver;
use Zhanghongfei\OrgRbac\Models\Tenant;

class RequestTenantResolver implements TenantResolver
{
    public function __construct(
        protected array $config,
    ) {}

    public function resolve(?Request $request = null): ?Tenant
    {
        $request ??= request();
        if (! $request) {
            return null;
        }

        $tenantModel = $this->tenantModelClass();

        $param = Arr::get($this->config, 'tenant_resolution.route_parameter');
        if ($param && $request->route($param)) {
            $value = $request->route($param);
            if ($value instanceof Tenant) {
                return $value;
            }

            return $tenantModel::query()
                ->where(function ($q) use ($value) {
                    if (is_numeric($value)) {
                        $q->where('id', $value);
                    } else {
                        $q->where('slug', (string) $value);
                    }
                })
                ->first();
        }

        $header = Arr::get($this->config, 'tenant_resolution.header');
        if ($header && $request->headers->has($header)) {
            $id = $request->headers->get($header);
            if ($id !== null && $id !== '') {
                return $tenantModel::query()->find($id);
            }
        }

        if (Arr::get($this->config, 'tenant_resolution.subdomain')) {
            $host = $request->getHost();
            $parts = explode('.', $host);
            if (count($parts) > 2) {
                $slug = $parts[0];
                $t = $tenantModel::query()->where('slug', $slug)->first();
                if ($t) {
                    return $t;
                }
            }
        }

        $col = Arr::get($this->config, 'tenant_resolution.authenticated_user_column');
        if ($col && $request->user()) {
            $tid = data_get($request->user(), $col);
            if ($tid) {
                return $tenantModel::query()->find($tid);
            }
        }

        return null;
    }

    protected function tenantModelClass(): string
    {
        $class = Arr::get($this->config, 'models.tenant', Tenant::class);

        return $class;
    }
}
