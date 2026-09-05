<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('cs_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('module')->nullable();
            $table->timestamps();
        });

        Schema::create('cs_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('cs_roles')->onDelete('cascade');
            $table->foreignId('permission_id')->constrained('cs_permissions')->onDelete('cascade');
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('cs_user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('cs_roles')->onDelete('cascade');
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_user_roles');
        Schema::dropIfExists('cs_role_permissions');
        Schema::dropIfExists('cs_permissions');
        Schema::dropIfExists('cs_roles');
    }
};
