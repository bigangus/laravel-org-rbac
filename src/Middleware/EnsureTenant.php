<?php

namespace Zhanghongfei\OrgRbac\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Zhanghongfei\OrgRbac\Contracts\TenantResolver;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;
use Zhanghongfei\OrgRbac\Support\OrgRbacLog;

class EnsureTenant
{
    public function __construct(
        protected TenantResolver $resolver,
        protected CurrentTenant $currentTenant,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        if ($tenant === null) {
            OrgRbacLog::warning('tenant_resolution_failed', [
                'method' => $request->method(),
                'path' => $request->path(),
            ]);

            abort(403, 'Tenant could not be resolved.');
        }

        OrgRbacLog::debug('tenant_resolved', [
            'tenant_id' => $tenant->getKey(),
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        $this->currentTenant->set($tenant);

        return $next($request);
    }
}
