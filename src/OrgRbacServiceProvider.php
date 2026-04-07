<?php

namespace Zhanghongfei\OrgRbac;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Zhanghongfei\OrgRbac\Contracts\TenantResolver;
use Zhanghongfei\OrgRbac\Events\TenantReparented;
use Zhanghongfei\OrgRbac\Listeners\FlushOrgRbacPermissionCacheOnTenantReparented;
use Zhanghongfei\OrgRbac\Middleware\EnsureTenant;
use Zhanghongfei\OrgRbac\Scopes\TenantScope;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;
use Zhanghongfei\OrgRbac\Support\RequestTenantResolver;

class OrgRbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/org-rbac.php', 'org-rbac');

        $this->app->singleton(CurrentTenant::class, fn () => new CurrentTenant);

        $this->app->singleton(TenantScope::class, function (Application $app) {
            return new TenantScope($app->make(CurrentTenant::class));
        });

        $this->app->singleton(TenantResolver::class, function (Application $app) {
            return new RequestTenantResolver($app['config']->get('org-rbac', []));
        });
    }

    public function boot(): void
    {
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
    }
}
