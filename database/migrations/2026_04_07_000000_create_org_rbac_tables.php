<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $t = config('org-rbac.tables', []);

        Schema::create($t['tenants'] ?? 'org_rbac_tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create($t['departments'] ?? 'org_rbac_departments', function (Blueprint $table) use ($t) {
            $table->id();
            $table->foreignId('tenant_id')->constrained($t['tenants'] ?? 'org_rbac_tenants')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained($t['departments'] ?? 'org_rbac_departments')->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['tenant_id', 'parent_id']);
        });

        Schema::create($t['permissions'] ?? 'org_rbac_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($t['roles'] ?? 'org_rbac_roles', function (Blueprint $table) use ($t) {
            $table->id();
            $table->foreignId('tenant_id')->constrained($t['tenants'] ?? 'org_rbac_tenants')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained($t['departments'] ?? 'org_rbac_departments')->nullOnDelete();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();

            $table->index(['tenant_id', 'department_id']);
        });

        $roleTable = $t['roles'] ?? 'org_rbac_roles';
        $permTable = $t['permissions'] ?? 'org_rbac_permissions';
        $rp = $t['role_permission'] ?? 'org_rbac_role_has_permissions';

        Schema::create($rp, function (Blueprint $table) use ($roleTable, $permTable) {
            $table->foreignId('role_id')->constrained($roleTable)->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained($permTable)->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        $mhr = $t['model_has_roles'] ?? 'org_rbac_model_has_roles';

        Schema::create($mhr, function (Blueprint $table) use ($roleTable, $t) {
            $table->id();
            $table->foreignId('role_id')->constrained($roleTable)->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('tenant_id')->constrained($t['tenants'] ?? 'org_rbac_tenants')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained($t['departments'] ?? 'org_rbac_departments')->nullOnDelete();
            $table->string('data_scope')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id', 'tenant_id'], 'org_rbac_mhr_model_tenant_idx');
        });

        $mhp = $t['model_has_permissions'] ?? 'org_rbac_model_has_permissions';

        Schema::create($mhp, function (Blueprint $table) use ($permTable, $t) {
            $table->id();
            $table->foreignId('permission_id')->constrained($permTable)->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('tenant_id')->constrained($t['tenants'] ?? 'org_rbac_tenants')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['model_type', 'model_id', 'tenant_id'], 'org_rbac_mhp_model_tenant_idx');
        });
    }

    public function down(): void
    {
        $t = config('org-rbac.tables', []);

        Schema::dropIfExists($t['model_has_permissions'] ?? 'org_rbac_model_has_permissions');
        Schema::dropIfExists($t['model_has_roles'] ?? 'org_rbac_model_has_roles');
        Schema::dropIfExists($t['role_permission'] ?? 'org_rbac_role_has_permissions');
        Schema::dropIfExists($t['roles'] ?? 'org_rbac_roles');
        Schema::dropIfExists($t['permissions'] ?? 'org_rbac_permissions');
        Schema::dropIfExists($t['departments'] ?? 'org_rbac_departments');
        Schema::dropIfExists($t['tenants'] ?? 'org_rbac_tenants');
    }
};
