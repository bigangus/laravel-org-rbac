<?php

namespace Zhanghongfei\OrgRbac\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Zhanghongfei\OrgRbac\Contracts\TenantResolver;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;

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
            abort(403, 'Tenant could not be resolved.');
        }

        $this->currentTenant->set($tenant);

        return $next($request);
    }
}
