<?php

namespace Zhanghongfei\OrgRbac\Tests\Feature;

use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class TenantResolutionHttpTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('/__org_rbac_route/{tenant}', fn () => response('ok', 200))
            ->middleware(['org-rbac.tenant']);
    }

    #[Test]
    public function it_resolves_tenant_from_route_parameter_for_middleware(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Route',
            'slug' => 'route-tenant',
            'parent_id' => null,
        ]);

        $this->get('/__org_rbac_route/'.$tenant->id)->assertOk();
    }
}
