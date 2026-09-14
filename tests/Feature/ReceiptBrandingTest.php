<?php
namespace Tests\Feature;
use App\Models\{Setting,User};
use App\Services\Settings;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;
final class ReceiptBrandingTest extends TestCase
{
 protected function setUp():void {
  parent::setUp();
  config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','database.connections.sqlite.url'=>null]);
  DB::purge('sqlite');
  (require database_path('migrations/2026_08_07_233018_create_settings_table.php'))->up();
  Settings::flush(); Storage::fake('public');
 }
 public function test_saved_files_are_served_without_a_storage_symlink():void {
  $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
  $user=(new User)->forceFill(['id'=>1,'role'=>'admin','name'=>'Owner','is_active'=>true,'session_version'=>0]);
  $this->actingAs($user);
  foreach(['logo','stamp'] as $kind) {
   Storage::disk('public')->put('branding/'.$kind.'.png',$png);
   Settings::put([$kind.'_path'=>'http://old-laptop/storage/branding/'.$kind.'.png']);
   $url=Settings::brandingUrl($kind);
   $this->assertStringContainsString('/receipts/branding/'.$kind,$url);
   $response=$this->get($url)->assertOk()->assertHeader('Content-Type','image/png');
   $this->assertSame($png,file_get_contents($response->baseResponse->getFile()->getPathname()));
   $this->assertSame($url,Settings::forClient()[$kind.'_path']);
   $this->assertSame('http://old-laptop/storage/branding/'.$kind.'.png',Settings::str($kind.'_path'));
  }
 }
 public function test_no_arbitrary_file_can_be_requested():void {
  $this->getJson('/receipts/branding/logo')->assertUnauthorized();
  $this->actingAs((new User)->forceFill(['id'=>1,'role'=>'admin','name'=>'Owner','is_active'=>true,'session_version'=>0]));
  Settings::put(['logo_path'=>'/storage/branding/../../.env']);
  $this->get('/receipts/branding/logo')->assertNotFound();
  $this->get('/receipts/branding/other')->assertNotFound();
 }
}
