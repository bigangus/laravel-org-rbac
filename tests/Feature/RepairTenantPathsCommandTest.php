<?php

namespace Zhanghongfei\OrgRbac\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Tests\TestCase;

class RepairTenantPathsCommandTest extends TestCase
{
    #[Test]
    public function repair_recomputes_depth_and_path(): void
    {
        $table = (new Tenant)->getTable();

        $root = Tenant::query()->create([
            'name' => 'Root',
            'slug' => 'root',
            'parent_id' => null,
        ]);

        $child = Tenant::query()->create([
            'name' => 'Child',
            'slug' => 'child',
            'parent_id' => $root->id,
        ]);

        DB::table($table)->where('id', $root->id)->update(['depth' => 9, 'path' => 'broken']);
        DB::table($table)->where('id', $child->id)->update(['depth' => 1, 'path' => 'broken/child']);

        $this->artisan('org-rbac:repair-tenant-paths')->assertExitCode(0);

        $root->refresh();
        $child->refresh();

        $this->assertSame(0, $root->depth);
        $this->assertSame((string) $root->id, $root->path);
        $this->assertSame(1, $child->depth);
        $this->assertSame($root->path.'/'.$child->id, $child->path);
    }
}
