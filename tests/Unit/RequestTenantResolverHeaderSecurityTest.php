<?php

namespace Zhanghongfei\OrgRbac\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Support\RequestTenantResolver;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class RequestTenantResolverHeaderSecurityTest extends TestCase
{
    #[Test]
    public function it_ignores_tenant_header_when_disabled_by_default(): void
    {
        config([
            'org-rbac.tenant_resolution.allow_header_resolution' => false,
            'org-rbac.tenant_resolution.header' => 'X-Tenant-ID',
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'H',
            'slug' => 'tenant-h',
            'parent_id' => null,
        ]);

        $resolver = new RequestTenantResolver(config('org-rbac'));

        $request = Request::create('/noop', 'GET', [], [], [], [
            'HTTP_X_TENANT_ID' => (string) $tenant->id,
        ]);

        $this->assertNull($resolver->resolve($request));
    }

    #[Test]
    public function it_ignores_header_when_authentication_required_but_user_missing(): void
    {
        config([
            'org-rbac.tenant_resolution.allow_header_resolution' => true,
            'org-rbac.tenant_resolution.header_requires_authentication' => true,
            'org-rbac.tenant_resolution.header' => 'X-Tenant-ID',
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'I',
            'slug' => 'tenant-i',
            'parent_id' => null,
        ]);

        $resolver = new RequestTenantResolver(config('org-rbac'));

        $request = Request::create('/noop', 'GET', [], [], [], [
            'HTTP_X_TENANT_ID' => (string) $tenant->id,
        ]);

        $this->assertNull($resolver->resolve($request));
    }
}
