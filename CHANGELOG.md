# Changelog

## 0.7.1

### Changed

- **默认不再强制 Redis**：`org-rbac.cache.require_redis` / `ORG_RBAC_REQUIRE_REDIS_CACHE` 默认改为 **`false`**。`file` / `database` / `array` 等驱动可直接使用；生产仍 **推荐** Redis（tag、SCAN 清前缀）。若需启动期硬校验，设 `ORG_RBAC_REQUIRE_REDIS_CACHE=true`。
- **`RedisCacheRequirement` 异常文案**：明确为可选加固项，并提示可关闭强制。

## 0.7.0

### Security（破坏性变更）

- **HTTP Header 租户解析默认关闭**：`tenant_resolution.allow_header_resolution` 默认 `false`（`ORG_RBAC_ALLOW_TENANT_HEADER`）。需显式开启后才读取 `X-Tenant-ID`（或自定义 header 名）。
- **Header + 认证**：`header_requires_authentication` 默认 `true`，未登录请求 **忽略** Header，继续走后续策略（并打 debug 日志）。防止伪造 Header 在未鉴权路由上绑定租户。

### Added

- **超管合规审计（可选）**：`super_admin.audit_resolution`（`ORG_RBAC_SUPER_ADMIN_AUDIT`）为 true 时，对超管在 **每个租户上下文首次** 解析有效权限记一条 `OrgRbacLog::info`（`super_admin_effective_permissions_context`）。
- **Redis 启动策略**：`cache.require_redis_strict`（`ORG_RBAC_REDIS_STRICT_BOOT`，默认 `true`）。为 `false` 时，非 Redis 缓存仅 **记录错误日志** 并继续启动（降级/演练，**不推荐生产**）。

### Tests

- Header 默认忽略、未登录忽略 header 等用例。

## 0.6.0

### Breaking / 行为变更

- **默认要求 Redis 缓存**：`org-rbac.cache.require_redis` 默认 `true`，在非 PHPUnit 环境下若默认缓存 store 不是 `RedisStore`，启动时 **抛异常**。测试或本地无 Redis 可设 `ORG_RBAC_REQUIRE_REDIS_CACHE=false`。  
- **默认启用 tag 缓存**：`use_tagged_permission_cache` 默认改为 `true`（需支持 tag 的 Redis 配置）。

### Added

- **`RedisCacheRequirement`**：校验默认缓存为 Redis。  
- **`OrgRbacLog`**：结构化日志（可配置 channel / 开关）。  
- **日志埋点**：`EnsureTenant`、租户重绑监听器、`OrgRbacCache` flush（tag/SCAN）、`repair-tenant-paths` 等。  
- **测试**：Resolver、中间件、HTTP 路由租户、超管有效权限、`RedisCacheRequirement` 等。

### Documentation

- **README**：明确 **仅建议全新 Laravel 项目**；环境变量表；Redis/日志/合规说明。  
- **SECURITY.md**：审计日志建议、合规与外部审计责任边界。

## 0.5.1

### Performance

- **超管**：`orgRbacEffectivePermissions` 在配置 TTL 时对「全表权限列」使用应用缓存，键为 **每用户一条**（`…super.{Model}.{id}`），不按租户重复；同一请求内再次解析会命中 **memo**，避免重复查库/反序列化。
- **普通用户**：同一请求内对同一租户的有效权限结果 **memo**，减少重复计算。
- **`syncOrgRbacRolesInTenant`**：删除旧 pivot 后 **一次** `syncWithoutDetaching` 写入多角色，且 **仅调用一次** `orgRbacForgetPermissionCache`（含超管/租户缓存键与 memo 清理）。

## 0.5.0

### Quality

- Orchestra Testbench 基线测试（`tests/`、`phpunit.xml`）。
- 可选静态分析：`larastan/larastan` + `phpstan.neon`；代码风格：`laravel/pint` + `pint.json`。
- GitHub Actions：PHPUnit、PHPStan、Pint。

### Performance

- 租户重绑后清理权限缓存：优先使用带 **tag** 的缓存（`cache.use_tagged_permission_cache`）；否则对 Redis 使用 **SCAN** 按前缀删键，避免阻塞式 `KEYS`。
- 超管解析有效权限时，可配置 `super_admin.permission_columns`，避免对 `permissions` 表 `SELECT *`。

### Developer experience

- Artisan：`org-rbac:repair-tenant-paths`（按 `parent_id` BFS 重算 `depth`/`path`）、`org-rbac:clear-permission-cache`。
- 容器绑定：`CurrentTenantContract` → `CurrentTenant` 单例。

### Security / documentation

- `SECURITY.md`：说明租户解析 Header、路由参数等信任边界；超管与 Policy 的配合建议。
