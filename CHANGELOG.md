# Changelog

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
