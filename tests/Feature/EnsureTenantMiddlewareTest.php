<?php

namespace Zhanghongfei\OrgRbac\Tests\Feature;

use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class EnsureTenantMiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('/__org_rbac_guarded', fn () => response('ok', 200))
            ->middleware(['org-rbac.tenant']);
    }

    #[Test]
    public function it_returns_403_when_tenant_cannot_be_resolved(): void
    {
        $this->get('/__org_rbac_guarded')->assertForbidden();
    }
}
