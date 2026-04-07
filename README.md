# laravel-org-rbac

Laravel **12.x / 13.x** 多租户、**统一租户树** + RBAC：**Platform / Organisation / Department / Team** 同一 `tenants` 表；**Roles / Users / Permissions**；可选 **用户主租户、超管、成员关系表**。

**环境**：`illuminate/*` 需 `^12.0` 或 `^13.0`。Laravel 13 需 **PHP ≥ 8.3**。

## 0.4.x

- **多角色 `data_scope` 取最宽**：`DataScope::widest()` / `widestFromStrings()`、`DataScopeMerger`，以及 `User::orgRbacWidestDataScopeForTenant($tenant)`；列表查询可用 `TenantDataScope::applyUsingWidestRoleScopeForUser(...)`。
- **租户移动事件**：修改 `parent_id` 并在库内更新完 `path` / 子孙后派发 **`Zhanghongfei\OrgRbac\Events\TenantReparented`**（含 `oldPath` / `newPath` / 新旧 `parent_id`），用于失效缓存或业务侧按旧路径清理。

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
- **组织树移动**：修改 `parent_id` 时自动重算本节点的 `depth`、`path`，并按前缀批量更新所有子孙的 `path` 与 `depth`（禁止挂到自己或子孙下）。
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

### 多角色 `data_scope`：取最宽

继承链上多条角色、各自 pivot 上有 `data_scope` 时，**最宽**顺序为：`Self` &lt; `Department` &lt; `Subtree` &lt; `Tenant`。

```php
use Zhanghongfei\OrgRbac\Enums\DataScope;
use Zhanghongfei\OrgRbac\Support\DataScopeMerger;
use Zhanghongfei\OrgRbac\Support\TenantDataScope;

$scope = $user->orgRbacWidestDataScopeForTenant($tenant) ?? DataScope::Department;
TenantDataScope::apply($query, 'tenant_id', $scope, $tenant);

// 或一行（默认 pivot 全空时按 Department）
TenantDataScope::applyUsingWidestRoleScopeForUser($query, 'tenant_id', $user, $tenant);
```

也可使用 `DataScope::widestFromStrings(...)` / `DataScopeMerger::widest(...)`。

### 组织树移动与缓存 / 业务失效

修改某节点的 `parent_id` 后，库会更新该节点及子孙的 `path` / `depth`，并派发 **`TenantReparented`**。请在应用里监听该事件，**失效**依赖 `tenant_id` 或旧 `path` 的缓存（例如本包权限缓存键 `org-rbac.perm.*`、你方业务 Redis 键），避免「静默脏读」。

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Zhanghongfei\OrgRbac\Events\TenantReparented;

// AppServiceProvider::boot() 等
Event::listen(TenantReparented::class, function (TenantReparented $e) {
    // 示例：按前缀清理（键名需与你实际一致）
    // Cache::tags(['tenant', $e->tenant->id])->flush(); // 若使用 tag
});
```

移动会影响子树内所有 `tenant_id` 的语义，凡按「路径前缀」或「整棵子树」缓存的数据都应随事件重建或清除。

## License

MIT
