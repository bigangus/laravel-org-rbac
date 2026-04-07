<?php

namespace Zhanghongfei\OrgRbac;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Zhanghongfei\OrgRbac\Console\ClearOrgRbacPermissionCacheCommand;
use Zhanghongfei\OrgRbac\Console\RepairTenantPathsCommand;
use Zhanghongfei\OrgRbac\Contracts\CurrentTenantContract;
use Zhanghongfei\OrgRbac\Contracts\TenantResolver;
use Zhanghongfei\OrgRbac\Events\TenantReparented;
use Zhanghongfei\OrgRbac\Listeners\FlushOrgRbacPermissionCacheOnTenantReparented;
use Zhanghongfei\OrgRbac\Middleware\EnsureTenant;
use Zhanghongfei\OrgRbac\Scopes\TenantScope;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;
use Zhanghongfei\OrgRbac\Support\OrgRbacLog;
use Zhanghongfei\OrgRbac\Support\RedisCacheRequirement;
use Zhanghongfei\OrgRbac\Support\RequestTenantResolver;

class OrgRbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/org-rbac.php', 'org-rbac');

        $this->app->singleton(CurrentTenant::class, fn () => new CurrentTenant);

        $this->app->singleton(CurrentTenantContract::class, fn (Application $app) => $app->make(CurrentTenant::class));

        $this->app->singleton(TenantScope::class, function (Application $app) {
            return new TenantScope($app->make(CurrentTenant::class));
        });

        $this->app->singleton(TenantResolver::class, function (Application $app) {
            return new RequestTenantResolver($app['config']->get('org-rbac', []));
        });
    }

    public function boot(): void
    {
        if (config('org-rbac.cache.require_redis', false) && ! $this->app->runningUnitTests()) {
            try {
                RedisCacheRequirement::assertDefaultStore(Cache::driver()->getStore());
            } catch (RuntimeException $e) {
                if (config('org-rbac.cache.require_redis_strict', true)) {
                    throw $e;
                }

                OrgRbacLog::error('redis_cache_requirement_not_met_boot_continues', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/org-rbac.php' => config_path('org-rbac.php'),
        ], 'org-rbac-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'org-rbac-migrations');

        $alias = (string) $this->app['config']->get('org-rbac.middleware.alias', 'org-rbac.tenant');

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware($alias, EnsureTenant::class);

        Event::listen(TenantReparented::class, FlushOrgRbacPermissionCacheOnTenantReparented::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RepairTenantPathsCommand::class,
                ClearOrgRbacPermissionCacheCommand::class,
            ]);
        }
    }
}
