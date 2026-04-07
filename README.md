# laravel-org-rbac

Laravel **12.x / 13.x** 多租户、**统一租户树** + RBAC：**Platform / Organisation / Department / Team** 同一 `tenants` 表；**Roles / Users / Permissions**；可选 **用户主租户、超管、成员关系表**。

**环境**：`illuminate/*` 需 `^12.0` 或 `^13.0`。Laravel 13 需 **PHP ≥ 8.3**。

## 适用范围（重要）

本包面向 **从零搭建的 Laravel 项目（greenfield）**：按本包迁移建表、在应用生命周期内以 **Redis 作为默认缓存** 为前提。若你已有存量用户/RBAC/多租户方案，或需与 Spatie 等包长期并存，**必须自行评估迁移与数据双写**；本仓库**不承诺**无痛接入复杂旧系统。

## 生产环境前置条件

- **Redis 缓存**：请设置 `CACHE_STORE=redis`（或等价配置）。自 **0.6** 起，默认 `ORG_RBAC_REQUIRE_REDIS_CACHE=true`，应用启动时若默认缓存 **不是** `Illuminate\Cache\RedisStore` 将 **抛出异常**。  
  - **PHPUnit**：框架 `runningUnitTests()` 下会跳过该检查；`phpunit.xml` 已示例设置 `ORG_RBAC_REQUIRE_REDIS_CACHE=false`。  
  - **本地无 Redis**：可临时 `.env` 设 `ORG_RBAC_REQUIRE_REDIS_CACHE=false`（**不推荐用于生产**）。
- **Tag 失效**：默认 `ORG_RBAC_PERM_CACHE_USE_TAGS=true`，与 Redis 搭配可在租户树变更时按 tag 清理权限缓存（避免依赖 SCAN）。

## 日志与合规

- 包内使用 **`OrgRbacLog`**（受 `config('org-rbac.logging')` 控制），日志上下文带 **`org_rbac => true`**，可路由到独立 channel（如 `ORG_RBAC_LOG_CHANNEL=stack` 或专用 daily 文件）。
- **审计、留存、渗透与认证**：见根目录 **`SECURITY.md`**（文档级实践说明，**不构成法律或认证结论**；正式合规需贵司法务/安全团队与持证机构定稿）。

### 常用环境变量

| 变量 | 说明 |
|------|------|
| `CACHE_STORE` | 生产请为 `redis`。 |
| `ORG_RBAC_REQUIRE_REDIS_CACHE` | 默认 `true`；仅测试/无 Redis 开发机可 `false`。 |
| `ORG_RBAC_PERM_CACHE_USE_TAGS` | 默认 `true`（建议与 Redis 同用）。 |
| `ORG_RBAC_LOG_ENABLED` | 默认 `true`；可 `false` 关闭包内结构化日志。 |
| `ORG_RBAC_LOG_CHANNEL` | 非空时写入指定 Log channel。 |
| `ORG_RBAC_ALLOW_TENANT_HEADER` | 默认 `false`。为 `true` 才允许从 HTTP Header 解析租户（须在网关剥离或仅内网使用）。 |
| `ORG_RBAC_TENANT_HEADER_REQUIRES_AUTH` | 默认 `true`：未登录则忽略 Header。 |
| `ORG_RBAC_SUPER_ADMIN_AUDIT` | 默认 `false`；为 `true` 时记录超管在各租户上下文的权限解析审计日志。 |
| `ORG_RBAC_REDIS_STRICT_BOOT` | 默认 `true`；`false` 时缓存非 Redis 仅打日志不中断启动（不推荐生产）。 |

## 0.7.x

- **Header 租户 ID**：默认 **不** 从 Header 解析；需 `ORG_RBAC_ALLOW_TENANT_HEADER=true` 且建议配合 **已认证用户**（默认要求登录后才采纳 Header）。
- **超管审计**：可选 `ORG_RBAC_SUPER_ADMIN_AUDIT=true`。
- **Redis**：可选非严格启动（仅日志），见 `ORG_RBAC_REDIS_STRICT_BOOT`。

## 0.6.x

- **强制 Redis**：非 PHPUnit 且 `require_redis=true` 时，启动期校验默认缓存为 Redis。
- **日志**：`OrgRbacLog`；租户解析失败/成功（debug）、租户重绑、权限缓存 flush（tag/SCAN）等可观测性。
- **测试**：补充 Resolver、中间件、HTTP 路由解析、超管有效权限、`RedisCacheRequirement` 等用例。
- **定位**：文档明确 **仅建议全新项目**；合规与审计见 `SECURITY.md`。

## 0.5.x

- **Artisan**：`php artisan org-rbac:repair-tenant-paths`（修复 `depth`/`path` 不一致）、`php artisan org-rbac:clear-permission-cache`（清空本包权限缓存）。
- **权限缓存**：可选 **`cache.use_tagged_permission_cache`**（需支持 tag 的 store）；否则在 Redis 上使用 **SCAN** 按前缀删键。详见 `config/org-rbac.php`。
- **超管**：`super_admin.permission_columns` 控制加载权限列，避免 `SELECT *`；在启用 TTL 时对该结果做 **每用户一条** 的缓存（键前缀含 `super.`，不按租户重复），且 **单次请求内 memo**。
- **`syncOrgRbacRolesInTenant`**：批量写入 pivot 后 **只失效一次** 权限缓存。
- **契约**：`CurrentTenantContract` 已在容器绑定到 `CurrentTenant`。
- **安全说明**：见仓库根目录 **`SECURITY.md`**（Header 租户解析信任边界、超管与 Policy）。

## 0.4.x

- **多角色 `data_scope` 取最宽**：`DataScope::widest()` / `widestFromStrings()`、`DataScopeMerger`，以及 `User::orgRbacWidestDataScopeForTenant($tenant)`；列表查询可用 `TenantDataScope::applyUsingWidestRoleScopeForUser(...)`。
- **分配角色默认写入 `data_scope`**：`assignOrgRbacRoleInTenant($role, $tenant, $scope?)` 未传时使用配置 `defaults.assign_role_data_scope`（默认 `department`）；`syncOrgRbacRolesInTenant` 第三参数为同一默认。
- **租户移动**：派发 **`TenantReparented`**；监听器 **`FlushOrgRbacPermissionCacheOnTenantReparented`** 会调用 **`OrgRbacCache::flushAfterTenantReparent()`**（tag 或 Redis 前缀 SCAN）。非 Redis / 无 tag 时行为见 `OrgRbacCache` 与日志。

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
use Zhanghongfei\OrgRbac\Enums\DataScope;

$user->hasOrgRbacPermission('posts.create');
$user->orgRbacEffectivePermissions($tenant);
$user->joinOrgRbacTenant($tenant);
$user->assignOrgRbacRoleInTenant('admin', $tenant); // pivot.data_scope 默认 department（见 config）
$user->assignOrgRbacRoleInTenant('editor', $tenant, DataScope::Subtree);
```

## Tenant 侧成员（可选）

```php
$tenant->members(User::class)->attach($userId, ['is_owner' => false, 'joined_at' => now()]);
```

## 业务表只有 `tenant_id` 时的数据范围（行级）

业务行用 **外键 `tenant_id` → `org_rbac_tenants.id`**（部门/团队等节点）。**功能权限**仍用 `hasOrgRbacPermission`；**能看哪些行**用角色 pivot 上的 **`data_scope`**（`DataScope` 枚举）+ `TenantDataScope` 收窄查询。

| `DataScope`  | 对 `tenant_id` 的约束                                                                                    |
|--------------|------------------------------------------------------------------------------------------------------|
| `Department` | `tenant_id = 当前上下文节点`                                                                                |
| `Subtree`    | `tenant_id IN 当前节点.subtreeIds()`（含自身）                                                                |
| `Tenant`     | `tenant_id IN` 组织根子树：默认取 `nearestOrganisationAncestor()`，否则 `rootAncestor()`；也可传入明确的组织根              |
| `Self`       | 若提供 `user_id`/`created_by` 等列 + 当前用户 id：`tenant_id = 当前节点` 且 `owner 列 = 当前用户`；否则退化为与 `Department` 相同 |

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

修改某节点的 `parent_id` 后，库会更新该节点及子孙的 `path` / `depth`，并派发 **`TenantReparented`**。

- **本包权限缓存**：`OrgRbacServiceProvider` 已注册 **`FlushOrgRbacPermissionCacheOnTenantReparented`**：当默认缓存驱动为 **Redis** 时，会删除键名匹配 **`{cache 前缀}{permission_key_prefix}*`** 的项（见 `config/org-rbac.php` 中 `cache.permission_key_prefix`，默认 `org-rbac.perm.`）。可通过 **`ORG_RBAC_FLUSH_PERM_CACHE_ON_REPARENT=false`** 关闭。`file` / `database` 等驱动无法按前缀扫描时不会删键（debug 下打日志），可改用 Redis 作缓存或手动 `Cache::forget`。
- 事件 **`TenantReparented`** 仍会派发，若你还有按租户路径或业务维度自管的缓存、搜索索引等，可在应用里自行订阅处理。

## License

MIT
