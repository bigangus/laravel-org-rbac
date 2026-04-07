<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('org_rbac_tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_posts');
    }
};
