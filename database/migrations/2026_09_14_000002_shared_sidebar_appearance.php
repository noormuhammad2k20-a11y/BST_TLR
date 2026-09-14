<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('settings')->where('key', 'sidebar_theme')->value('value') ?: 'white';
        $color = match ($legacy) {
            'white' => 'classic', 'navy' => 'navy', 'espresso' => 'bronze',
            'steel' => 'petrol', 'midnight' => 'plum', default => 'slate',
        };
        DB::table('settings')->insertOrIgnore([
            'key' => 'sidebar_appearance', 'group' => 'appearance',
            'value' => json_encode(['theme' => $legacy === 'white' ? 'light' : 'dark', 'color' => $color]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'sidebar_appearance')->delete();
    }
};
