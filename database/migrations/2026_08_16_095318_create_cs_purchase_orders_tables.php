<?php

// Purchase Orders module removed. This migration is now a no-op so a fresh
// `migrate` run still succeeds without recreating the dropped tables.
// If the cs_purchase_orders / cs_purchase_order_items tables already exist
// in your database, drop them manually.

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // intentionally empty
    }

    public function down(): void
    {
        // intentionally empty
    }
};
