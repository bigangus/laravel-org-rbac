# laravel-org-rbac

Laravel **12.x / 13.x** 多租户、**统一租户树** + RBAC：**Platform / Organisation / Department / Team** 同一 `tenants` 表；**Roles / Users / Permissions**；可选 **用户主租户、超管、成员关系表**。

**环境**：`illuminate/*` 需 `^12.0` 或 `^13.0`。Laravel 13 需 **PHP ≥ 8.3**。

## 0.3.x 变更（相对 0.2）

- **移除**独立 `departments` 表：组织/部门/团队均为 `tenants` 树节点（`parent_id`、`type`、`path`、`depth`）。
- **权限**：支持 `tenant_id` 为空（全局）或绑定节点；含 `display_name`、`group` 等。
- **角色分配**：`model_has_roles` 含 `assigned_at`、`assigned_by`；保留 `data_scope`。
- **成员**：`org_rbac_tenant_user`（`user_id` + `tenant_id`）。
- **继承**：`orgRbacInheritedRolesForTenant` / `orgRbacEffectivePermissions`（沿祖先租户链合并角色；可配置缓存 TTL）。
- **可选迁移**：若存在 `users` 表，可增加 `users.tenant_id`、`is_super_admin`。

若你已按 0.2 跑过迁移，升级需自行处理数据迁移或在新环境安装。

## 安装

```bash
composer require zhanghongfei/laravel-org-rbac
php artisan vendor:publish --tag=org-rbac-config
php artisan vendor:publish --tag=org-rbac-migrations
php artisan migrate
```

在 `config/org-rbac.php` 中设置 `user_model`（默认可指向 `App\Models\User`）。

## 功能要点

- **物化路径** `path`：子树查询、祖先链；`Tenant::descendants()` / `ancestors()`。
- **当前租户**：中间件 `org-rbac.tenant` + `CurrentTenant`（路由参数 `{tenant}`、Header、用户 `tenant_id` 等）。
- **业务表**：对仅当前租户可见的数据模型使用 `BelongsToTenant`（`tenant_id` 全局作用域）。
- **超管**：用户存在 `is_super_admin` 且为 true 时，`hasOrgRbacPermission*` 恒为 true（请配合可选迁移）。

## User 模型

```php
use Zhanghongfei\OrgRbac\Concerns\HasOrgRbacRoles;

class User extends Authenticatable
{
    use HasOrgRbacRoles;

    protected $fillable = [/* … */, 'tenant_id', 'is_super_admin'];
}
```

```php
$user->hasOrgRbacPermission('posts.create');
$user->orgRbacEffectivePermissions($tenant);
$user->joinOrgRbacTenant($tenant);
$user->assignOrgRbacRoleInTenant('admin', $tenant);
```

## Tenant 侧成员（可选）

```php
$tenant->members(User::class)->attach($userId, ['is_owner' => false, 'joined_at' => now()]);
```

## 业务表只有 `tenant_id` 时的数据范围（行级）

业务行用 **外键 `tenant_id` → `org_rbac_tenants.id`**（部门/团队等节点）。**功能权限**仍用 `hasOrgRbacPermission`；**能看哪些行**用角色 pivot 上的 **`data_scope`**（`DataScope` 枚举）+ `TenantDataScope` 收窄查询。

| `DataScope` | 对 `tenant_id` 的约束 |
|-------------|------------------------|
| `Department` | `tenant_id = 当前上下文节点` |
| `Subtree` | `tenant_id IN 当前节点.subtreeIds()`（含自身） |
| `Tenant` | `tenant_id IN` 组织根子树：默认取 `nearestOrganisationAncestor()`，否则 `rootAncestor()`；也可传入明确的组织根 |
| `Self` | 若提供 `user_id`/`created_by` 等列 + 当前用户 id：`tenant_id = 当前节点` 且 `owner 列 = 当前用户`；否则退化为与 `Department` 相同 |

示例（列表查询）：

```php
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Support\CurrentTenant;
use Zhanghongfei\OrgRbac\Support\TenantDataScope;

$tenant = app(CurrentTenant::class)->get();
$query = Post::query();
TenantDataScope::apply($query, 'tenant_id', DataScope::Subtree, $tenant);
```

多角色、多 `data_scope` 时，在应用层合并为 **最宽** 或 **最严** 策略后再调用一次即可。

## License

MIT
