<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'category')) {
                $table->string('category')->default('system')->after('action');
            }
            if (!Schema::hasColumn('activity_logs', 'event')) {
                $table->string('event')->nullable()->after('category');
            }
            if (!Schema::hasColumn('activity_logs', 'subject_type')) {
                $table->string('subject_type')->nullable()->after('description');
            }
            if (!Schema::hasColumn('activity_logs', 'subject_id')) {
                $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            }
            if (!Schema::hasColumn('activity_logs', 'properties')) {
                $table->json('properties')->nullable()->after('subject_id');
            }
            if (!Schema::hasColumn('activity_logs', 'actor_name')) {
                $table->string('actor_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('activity_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('actor_name');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('category', 'activity_logs_category_idx');
            $table->index('created_at', 'activity_logs_created_at_idx');
            $table->index(['subject_type', 'subject_id'], 'activity_logs_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_category_idx');
            $table->dropIndex('activity_logs_created_at_idx');
            $table->dropIndex('activity_logs_subject_idx');
            $table->dropColumn([
                'category', 'event', 'subject_type', 'subject_id', 'properties',
                'actor_name', 'ip_address',
            ]);
        });
    }
};
