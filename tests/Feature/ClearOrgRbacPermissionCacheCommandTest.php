<?php

namespace Zhanghongfei\OrgRbac\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class ClearOrgRbacPermissionCacheCommandTest extends TestCase
{
    #[Test]
    public function command_runs_successfully(): void
    {
        $this->artisan('org-rbac:clear-permission-cache')->assertExitCode(0);
    }
}
