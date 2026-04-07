<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $t = config('org-rbac.tables', []);

        $tenants = $t['tenants'] ?? 'org_rbac_tenants';
        $roles = $t['roles'] ?? 'org_rbac_roles';
        $permissions = $t['permissions'] ?? 'org_rbac_permissions';
        $rp = $t['role_permission'] ?? 'org_rbac_role_has_permissions';
        $mhr = $t['model_has_roles'] ?? 'org_rbac_model_has_roles';
        $mhp = $t['model_has_permissions'] ?? 'org_rbac_model_has_permissions';
        $tenantUser = $t['tenant_user'] ?? 'org_rbac_tenant_user';

        Schema::create($tenants, function (Blueprint $table) use ($tenants) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained($tenants)
                ->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('type')->default('organisation');
            $table->unsignedInteger('depth')->default(0);
            $table->string('path')->nullable()->index();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
        });

        Schema::create($permissions, function (Blueprint $table) use ($tenants) {
            $table->id();
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained($tenants)
                ->nullOnDelete();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('group')->nullable()->index();
            $table->string('guard_name')->default('web');
            $table->timestamps();

            $table->index(['tenant_id', 'name', 'guard_name']);
        });

        Schema::create($roles, function (Blueprint $table) use ($tenants) {
            $table->id();
            $table->foreignId('tenant_id')->constrained($tenants)->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('guard_name')->default('web');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'name', 'guard_name']);
            $table->index('tenant_id');
            $table->index('guard_name');
        });

        Schema::create($rp, function (Blueprint $table) use ($roles, $permissions) {
            $table->foreignId('role_id')->constrained($roles)->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained($permissions)->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create($mhr, function (Blueprint $table) use ($roles, $tenants) {
            $table->id();
            $table->foreignId('role_id')->constrained($roles)->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('tenant_id')->constrained($tenants)->cascadeOnDelete();
            $table->string('data_scope')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id', 'tenant_id'], 'org_rbac_mhr_model_tenant_idx');
            $table->index(['tenant_id', 'role_id']);
        });

        Schema::create($mhp, function (Blueprint $table) use ($permissions, $tenants) {
            $table->id();
            $table->foreignId('permission_id')->constrained($permissions)->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('tenant_id')->constrained($tenants)->cascadeOnDelete();
            $table->timestamps();

            $table->index(['model_type', 'model_id', 'tenant_id'], 'org_rbac_mhp_model_tenant_idx');
        });

        Schema::create($tenantUser, function (Blueprint $table) use ($tenants) {
            $table->foreignId('tenant_id')->constrained($tenants)->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_owner')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->primary(['tenant_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        $t = config('org-rbac.tables', []);

        Schema::dropIfExists($t['tenant_user'] ?? 'org_rbac_tenant_user');
        Schema::dropIfExists($t['model_has_permissions'] ?? 'org_rbac_model_has_permissions');
        Schema::dropIfExists($t['model_has_roles'] ?? 'org_rbac_model_has_roles');
        Schema::dropIfExists($t['role_permission'] ?? 'org_rbac_role_has_permissions');
        Schema::dropIfExists($t['roles'] ?? 'org_rbac_roles');
        Schema::dropIfExists($t['permissions'] ?? 'org_rbac_permissions');
        Schema::dropIfExists($t['tenants'] ?? 'org_rbac_tenants');
    }
};
