<?php

namespace Zhanghongfei\OrgRbac\Console;

use Illuminate\Console\Command;
use Zhanghongfei\OrgRbac\Models\Tenant;
use Zhanghongfei\OrgRbac\Support\OrgRbacLog;

class RepairTenantPathsCommand extends Command
{
    protected $signature = 'org-rbac:repair-tenant-paths
                            {--dry-run : Print computed depth/path without saving}';

    protected $description = 'Recompute depth and materialized path for all tenant rows from parent_id (breadth-first order)';

    public function handle(): int
    {
        $tenantModel = config('org-rbac.models.tenant', Tenant::class);

        $dryRun = (bool) $this->option('dry-run');

        $tenantModel::withoutEvents(function () use ($tenantModel, $dryRun): void {
            $q = $tenantModel::query()->whereNull('parent_id')->orderBy('id')->get();
            $ordered = collect();

            while ($q->isNotEmpty()) {
                /** @var Tenant $t */
                $t = $q->shift();
                $ordered->push($t);

                $children = $tenantModel::query()->where('parent_id', $t->id)->orderBy('id')->get();
                foreach ($children as $c) {
                    $q->push($c);
                }
            }

            foreach ($ordered as $tenant) {
                if ($tenant->parent_id) {
                    $parent = $tenantModel::query()->find($tenant->parent_id);
                    if ($parent === null) {
                        $this->error("Tenant {$tenant->id}: parent_id {$tenant->parent_id} not found.");

                        continue;
                    }
                    $depth = $parent->depth + 1;
                    $path = $parent->path.'/'.$tenant->id;
                } else {
                    $depth = 0;
                    $path = (string) $tenant->id;
                }

                if ($dryRun) {
                    $this->line("[{$tenant->id}] depth={$depth} path={$path}");

                    continue;
                }

                if ((int) $tenant->depth !== $depth || $tenant->path !== $path) {
                    $tenant->depth = $depth;
                    $tenant->path = $path;
                    $tenant->saveQuietly();
                }
            }
        });

        if (! $dryRun) {
            OrgRbacLog::info('artisan_repair_tenant_paths_completed', [
                'command' => 'org-rbac:repair-tenant-paths',
            ]);
            $this->info('org-rbac tenant paths repaired.');
        }

        return self::SUCCESS;
    }
}
