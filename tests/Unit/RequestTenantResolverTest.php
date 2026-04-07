<?php

namespace Zhanghongfei\OrgRbac\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Support\RequestTenantResolver;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class RequestTenantResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_tenant_from_header(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'A',
            'slug' => 'tenant-a',
            'parent_id' => null,
        ]);

        $resolver = new RequestTenantResolver(config('org-rbac'));

        $request = Request::create('/noop', 'GET', [], [], [], [
            'HTTP_X_TENANT_ID' => (string) $tenant->id,
        ]);

        $this->assertTrue($tenant->is($resolver->resolve($request)));
    }
}
