<?php

namespace Tests\Feature;

use App\Models\{Measurement, OrderItemPiece};
use App\Services\{CustomerImporter, MeasurementProfiles, Settings};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MeasurementNamingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        (require database_path('migrations/2026_08_07_233018_create_settings_table.php'))->up();
        Settings::flush();
    }

    public function test_exact_names_keep_existing_field_identifiers_and_order(): void
    {
        $labels = Measurement::labels();
        $this->assertSame(Measurement::FIELDS, array_keys($labels));
        foreach (['shoulder_width'=>'Shoulder','sleeve_length'=>'Sleeves','chest_losing'=>'Losing',
            'waist'=>'West','waist_losing'=>'Loasing','hip_losing'=>'Losing','armhole'=>'Armor',
            'takai'=>'Takki','ghera'=>'Galla','patti'=>'F/Patti'] as $field => $label) {
            $this->assertSame($label, $labels[$field]);
            foreach (MeasurementProfiles::all() as $profile) {
                if (in_array($field, $profile['fields'], true)) $this->assertSame($label, $profile['labels'][$field]);
            }
        }
        $this->assertSame('Notes', Measurement::label('measurement_notes'));
        $this->assertSame('Pajama length', Measurement::label('salwar_length', 'Pajama length'));
    }

    public function test_historical_profiles_and_print_rows_change_only_labels(): void
    {
        $profile = ['key'=>'generic','fields'=>['shoulder_width','waist','waist_losing','salwar_length'],
            'labels'=>['shoulder_width'=>'Shoulder Width','waist'=>'Waist','waist_losing'=>'Waist Ease','salwar_length'=>'Pajama length'],
            'required'=>['waist'],'saved_measurement_id'=>21];
        $display = Measurement::displayProfile($profile);
        $this->assertSame($profile['fields'], $display['fields']);
        $this->assertSame($profile['required'], $display['required']);
        $this->assertSame(21, $display['saved_measurement_id']);
        $this->assertSame(['Shoulder','West','Loasing','Pajama length'], array_values($display['labels']));
        $piece = new OrderItemPiece(['profile'=>$profile]);
        $sheet = new Measurement(['shoulder_width'=>18,'waist'=>34,'waist_losing'=>2,'salwar_length'=>40,'unit'=>'in']);
        $sheet->setRelation('piece', $piece);
        $before = $sheet->getAttributes();
        $method = new \ReflectionMethod(\App\Http\Controllers\OrderController::class, 'measurementRows');
        $rows = $method->invoke(app(\App\Http\Controllers\OrderController::class), $sheet);
        $this->assertSame(['Shoulder','West','Loasing','Pajama length'], array_column($rows, 'label'));
        $this->assertSame($before, $sheet->getAttributes());
        $this->assertSame($profile, $piece->profile);
    }

    public function test_new_import_template_and_legacy_headings_retain_their_bindings(): void
    {
        $fields = CustomerImporter::fields();
        $mapping = CustomerImporter::guess(array_column($fields, 'label'));
        foreach (array_keys($fields) as $index => $field) $this->assertSame($index, $mapping[$field], $field);
        $legacy = CustomerImporter::guess(['Shoulder Width','Sleeve Length','Waist','Waist Ease','Hip Ease','Armhole','Takai','Ghera','Patti','Measurement Notes']);
        foreach (['shoulder_width','sleeve_length','waist','waist_losing','hip_losing','armhole','takai','ghera','patti','measurement_notes'] as $index => $field) {
            $this->assertSame($index, $legacy[$field], $field);
        }
        $partial = CustomerImporter::guess(['Hip', 'Losing', 'Chest', 'Losing', 'West', 'Loasing']);
        $this->assertSame(1, $partial['hip_losing']);
        $this->assertSame(3, $partial['chest_losing']);
        $this->assertSame(5, $partial['waist_losing']);
    }
}
