# laravel-org-rbac

Laravel **12.x / 13.x** 多租户、层级组织 + RBAC 包：**Platform → Organization（tenant）→ Department → Roles → Users → Permissions**。

**环境**：`illuminate/*` 需 `^12.0` 或 `^13.0`。使用 **Laravel 13** 时要求 **PHP ≥ 8.3**（框架要求）；仅使用 Laravel 12 时可为 PHP 8.2。

- **单库 + `tenant_id`** 行级隔离，Eloquent `TenantScope` 自动约束。
- **权限目录**为全局表 `permissions`；租户隔离通过 **角色（带 `tenant_id`）** 与 **用户-角色分配（pivot 带 `tenant_id`）** 实现。
- **中间件** `org-rbac.tenant`：解析当前租户并绑定到 `CurrentTenant`。

## 安装

在应用 `composer.json` 中加入路径仓库（开发阶段示例）：

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../code/laravel-org-rbac"
        }
    ],
    "require": {
        "zhanghongfei/laravel-org-rbac": "*"
    }
}
```

然后：

```bash
composer require zhanghongfei/laravel-org-rbac
php artisan vendor:publish --tag=org-rbac-config
php artisan vendor:publish --tag=org-rbac-migrations
php artisan migrate
```

## 配置

- `config/org-rbac.php`：表名、`tenant_resolution`（路由参数、Header、子域、已登录用户 `tenant_id` 列）、`strict_tenant_scope`。
- 路由示例：`Route::middleware(['auth', 'org-rbac.tenant'])->group(...)`，并在路由中提供 `{tenant}` 或使用 Header `X-Tenant-ID`。

## User 模型

```php
use Zhanghongfei\OrgRbac\Concerns\HasOrgRbacRoles;

class User extends Authenticatable
{
    use HasOrgRbacRoles;
}
```

检查权限（需已绑定当前租户，例如命中 `EnsureTenant` 中间件）：

```php
$user->hasOrgRbacPermission('posts.create');
```

## 业务模型

对租户内数据模型使用 `BelongsToTenant`：

```php
use Zhanghongfei\OrgRbac\Concerns\BelongsToTenant;

class Post extends Model
{
    use BelongsToTenant;
}
```

## License

MIT
