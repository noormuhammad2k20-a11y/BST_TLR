<?php

namespace Tests\Feature;

use App\Models\{Setting, User};
use App\Services\{Settings, SidebarAppearance};
use App\View\Composers\LayoutComposer;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

final class SidebarAppearanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        (require database_path('migrations/2026_08_07_233018_create_settings_table.php'))->up();
        Settings::flush();
    }

    private function owner(int $id = 1): User
    {
        return (new User)->forceFill(['id' => $id, 'role' => 'admin', 'name' => 'Owner', 'is_active' => true, 'session_version' => 0]);
    }

    public function test_both_panels_save_one_global_record_and_another_session_reads_it(): void
    {
        $url = '/settings/sidebar-appearance';
        $this->actingAs($this->owner())->withHeader('Referer', '/settings')
            ->putJson($url, ['theme' => 'dark', 'color' => 'navy'])->assertOk()->assertExactJson(['theme' => 'dark', 'color' => 'navy']);
        $this->actingAs($this->owner(2))->getJson($url)->assertOk()->assertJsonPath('color', 'navy');
        $this->withHeader('Referer', '/cloth-store/settings')->putJson($url, ['theme' => 'light', 'color' => 'teal'])->assertOk();
        $this->assertSame(1, Setting::where('key', SidebarAppearance::KEY)->count());
        $this->assertSame(['theme' => 'light', 'color' => 'teal'], app(LayoutComposer::class)->display()['sidebarAppearance']);
        $this->assertSame('light', json_decode(Setting::where('key', SidebarAppearance::KEY)->value('value'), true)['theme']);
    }

    public function test_invalid_or_incomplete_values_do_not_overwrite_the_pair(): void
    {
        app(SidebarAppearance::class)->save(['theme' => 'dark', 'color' => 'slate']);
        $this->actingAs($this->owner());
        foreach ([['theme' => 'system', 'color' => 'slate'], ['theme' => 'light', 'color' => '<script>'], ['theme' => 'light']] as $input) {
            $this->putJson('/settings/sidebar-appearance', $input)->assertUnprocessable();
        }
        $this->assertSame(['theme' => 'dark', 'color' => 'slate'], app(SidebarAppearance::class)->current());
    }

    public function test_guest_and_non_admin_cannot_change_global_appearance(): void
    {
        $this->putJson('/settings/sidebar-appearance', ['theme' => 'dark', 'color' => 'slate'])->assertUnauthorized();
        $staff = $this->owner(); $staff->role = 'staff';
        $this->actingAs($staff)->putJson('/settings/sidebar-appearance', ['theme' => 'dark', 'color' => 'slate'])->assertForbidden();
        $this->assertSame(0, Setting::where('key', SidebarAppearance::KEY)->count());
    }

    public function test_migration_preserves_legacy_choice_and_never_replaces_saved_configuration(): void
    {
        Setting::put('sidebar_theme', 'navy');
        $migration = require database_path('migrations/2026_09_14_000002_shared_sidebar_appearance.php');
        $migration->up();
        $this->assertSame(['theme' => 'dark', 'color' => 'navy'], app(SidebarAppearance::class)->current());
        app(SidebarAppearance::class)->save(['theme' => 'light', 'color' => 'forest']);
        $migration->up();
        $this->assertSame(['theme' => 'light', 'color' => 'forest'], app(SidebarAppearance::class)->current());
    }

    public function test_all_sixteen_palettes_meet_text_and_focus_contrast(): void
    {
        $palettes = config('sidebar.palettes');
        $this->assertCount(8, $palettes);
        foreach ($palettes as $name => $palette) foreach (['light', 'dark'] as $mode) {
            $t = $palette[$mode];
            foreach ([['text', 'bg'], ['muted', 'bg'], ['text', 'hover'], ['muted', 'hover'], ['text', 'active'], ['logo-text', 'fill']] as [$fg, $bg]) {
                $this->assertGreaterThanOrEqual(4.5, $this->contrast($t[$fg], $t[$bg]), "$name/$mode $fg on $bg");
            }
            $this->assertGreaterThanOrEqual(3, $this->contrast($t['accent'], $t['bg']), "$name/$mode focus");
        }
    }

    private function contrast(string $a, string $b): float
    {
        $luminance = static function (string $hex): float {
            $rgb = array_map(static function ($part) {
                $v = hexdec($part) / 255;
                return $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }, str_split(ltrim($hex, '#'), 2));
            return $rgb[0] * 0.2126 + $rgb[1] * 0.7152 + $rgb[2] * 0.0722;
        };
        $x = $luminance($a); $y = $luminance($b);
        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }
}
