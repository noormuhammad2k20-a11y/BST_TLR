<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureLicensed;
use App\Services\Licensing\LicenseChecker;
use App\Services\Licensing\LicenseInstaller;
use App\Services\Licensing\MachineIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\ApplyShopSettings::class);
        $this->directory = sys_get_temp_dir().'/atelier-license-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        config(['license.path' => $this->directory.'/license.dat']);
        CarbonImmutable::setTestNow('2026-01-15 12:00:00 UTC');
        Route::middleware('web')->get('/license-test-protected', fn () => response('Protected application'));
        Route::middleware('web')->post('/license-test-protected', fn () => response('Mutation allowed'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function fixture(string $name = 'trial'): string
    {
        return file_get_contents(base_path('tests/Fixtures/licenses/'.$name.'.dat'));
    }

    private function installFixture(string $name = 'trial'): void
    {
        file_put_contents(config('license.path'), $this->fixture($name));
    }

    public function test_valid_trial(): void
    {
        $this->installFixture();
        $this->assertTrue(app(LicenseChecker::class)->check()->valid());
    }

    public function test_trial_expires_at_exact_utc_boundary(): void
    {
        $this->installFixture();
        CarbonImmutable::setTestNow('2026-01-31 23:59:59 UTC');
        $this->assertTrue(app(LicenseChecker::class)->check()->valid());
        CarbonImmutable::setTestNow('2026-02-01 00:00:00 UTC');
        $this->assertSame('expired', app(LicenseChecker::class)->check()->status);
        $this->get('/license-test-protected')->assertRedirect(route('license.show'));
    }

    public function test_lifetime_never_expires(): void
    {
        $this->installFixture('lifetime');
        CarbonImmutable::setTestNow('2126-02-01 UTC');
        $this->assertTrue(app(LicenseChecker::class)->check()->valid());
        $this->get('/license')->assertOk()->assertSee('Never Expires');
    }

    public function test_wrong_machine(): void
    {
        $this->installFixture('wrong-machine');
        $this->assertSame('wrong_machine', app(LicenseChecker::class)->check()->status);
    }

    public function test_every_editable_field_is_authenticated(): void
    {
        foreach (['client_name' => 'Attacker', 'machine_id' => str_repeat('B', 64), 'type' => 'Lifetime', 'expires_at' => 9999999999] as $field => $value) {
            $envelope = json_decode($this->fixture(), true);
            $payload = json_decode(base64_decode($envelope['payload']), true);
            $payload[$field] = $value;
            $envelope['payload'] = base64_encode(json_encode($payload));
            $this->assertSame('invalid', app(LicenseChecker::class)->verify(json_encode($envelope))->status, $field);
        }
    }

    public function test_missing_license_blocks_html_and_json_and_mutations(): void
    {
        $this->assertSame('missing', app(LicenseChecker::class)->check()->status);
        $this->get('/license-test-protected')->assertRedirect(route('license.show'));
        $this->postJson('/license-test-protected')->assertForbidden()->assertJsonPath('code', 'license_required');
        $this->get('/license')->assertOk()->assertSee(str_repeat('A', 64));
    }

    public function test_corrupted_and_oversized_licenses_fail_closed(): void
    {
        foreach (['', 'broken', '{}', 'null', '{"payload":[],"signature":[]}', str_repeat('x', 16385), '{"payload":"@@","signature":"@@"}'] as $input) {
            $this->assertSame('invalid', app(LicenseChecker::class)->verify($input)->status);
        }
    }

    public function test_valid_license_allows_normal_app_access_and_preserves_authentication(): void
    {
        $this->installFixture();
        $this->get('/license-test-protected')->assertOk()->assertSee('Protected application');
        $this->postJson('/license-test-protected')->assertOk();
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_all_business_routes_inherit_license_middleware(): void
    {
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        foreach (Route::getRoutes() as $route) {
            if (in_array('auth', $route->gatherMiddleware(), true) && $route->getName() !== 'logout') {
                $this->assertContains(EnsureLicensed::class, app('router')->gatherRouteMiddleware($route), $route->uri());
            }
        }
    }

    public function test_failed_upload_preserves_installed_license(): void
    {
        $this->installFixture('lifetime');
        $before = file_get_contents(config('license.path'));
        $this->from('/license')->post('/license', ['license' => UploadedFile::fake()->createWithContent('license.dat', 'tampered')])
            ->assertRedirect('/license')->assertSessionHasErrors('license');
        $this->assertSame($before, file_get_contents(config('license.path')));
    }

    public function test_valid_upload_activates_and_persists_on_next_request(): void
    {
        $this->post('/license', ['license' => UploadedFile::fake()->createWithContent('license.dat', $this->fixture('lifetime'))])
            ->assertRedirect(route('license.show'))->assertSessionHasNoErrors();
        $this->get('/license-test-protected')->assertOk();
        $this->assertSame($this->fixture('lifetime'), file_get_contents(config('license.path')));
        $this->assertCount(1, glob($this->directory.'/*'));
    }

    public function test_expired_or_wrong_machine_cannot_replace_license(): void
    {
        foreach (['trial', 'wrong-machine'] as $name) {
            CarbonImmutable::setTestNow('2026-02-02 UTC');
            try {
                app(LicenseInstaller::class)->install($this->fixture($name));
                $this->fail('Invalid license installed');
            } catch (ValidationException) {
                $this->assertFileDoesNotExist(config('license.path'));
            }
        }
    }

    public function test_invalid_signed_schema_is_rejected(): void
    {
        foreach (['bad-type', 'bad-expiry', 'bad-product'] as $name) {
            $this->assertSame('invalid', app(LicenseChecker::class)->verify($this->fixture($name))->status);
        }
    }

    public function test_missing_public_key_and_unavailable_machine_fail_closed(): void
    {
        config(['license.public_key' => $this->directory.'/missing.key']);
        $this->assertSame('configuration', app(LicenseChecker::class)->verify($this->fixture())->status);
        config(['license.public_key' => base_path('tests/Fixtures/licenses/public.key')]);
        $machine = new class extends MachineIdentity {
            public function id(): string { throw new \RuntimeException('Unavailable'); }
        };
        $this->assertSame('machine_unavailable', (new LicenseChecker($machine))->verify($this->fixture())->status);
    }

    public function test_clock_before_trial_issue_is_rejected(): void
    {
        CarbonImmutable::setTestNow('2025-12-31 UTC');
        $this->assertSame('clock', app(LicenseChecker::class)->verify($this->fixture())->status);
    }

    public function test_installed_file_is_reverified_on_every_request(): void
    {
        $this->installFixture('lifetime');
        $this->get('/license-test-protected')->assertOk();
        file_put_contents(config('license.path'), 'modified');
        $this->get('/license-test-protected')->assertRedirect(route('license.show'));
    }

    public function test_authenticated_business_reads_and_writes_are_blocked_without_license(): void
    {
        $user = new \App\Models\User(['name' => 'Owner', 'email' => 'owner@example.test', 'role' => 'admin', 'is_active' => true]);
        $user->id = 1;
        $this->actingAs($user);
        $this->get('/orders')->assertRedirect(route('license.show'));
        // No business tables exist here: reaching either controller would fail.
        $this->postJson('/orders', [])->assertForbidden()->assertJsonPath('code', 'license_required');
        $this->postJson('/cloth-store/checkout', [])->assertForbidden()->assertJsonPath('code', 'license_required');
    }

    public function test_activation_upload_keeps_csrf_protection(): void
    {
        $this->app['env'] = 'local';
        $this->post('/license', ['license' => UploadedFile::fake()->createWithContent('license.dat', $this->fixture())])->assertStatus(419);
        $this->assertFileDoesNotExist(config('license.path'));
    }

    public function test_test_fixtures_are_not_valid_under_the_shipping_public_key(): void
    {
        config(['license.public_key' => base_path('resources/license/public.key')]);
        $this->assertSame('invalid', app(LicenseChecker::class)->verify($this->fixture('lifetime'))->status);
    }
}
