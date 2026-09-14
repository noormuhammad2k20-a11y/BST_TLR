<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClothStore\Setting;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    private const DEFAULTS = [
        'store_name' => 'Cloth Store',
        'owner_name' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'city' => '',
        'currency' => 'Rs',
        'timezone' => 'Asia/Karachi',

        'default_tax' => '0',
        'invoice_prefix' => 'CS-',
        'invoice_format' => '00000',
        'default_payment_method' => 'Cash',
        'default_discount' => '0',
        'allow_customer_credit' => '1',
        'max_customer_credit' => '50000',

        'default_fabric_unit' => 'Meter',
        'decimal_precision' => '2',
        'min_measurable_qty' => '0.25',
        'stock_warning_threshold' => '10',
        'prevent_negative_stock' => '1',
        'auto_low_stock_alerts' => '1',

        'enable_roll_tracking' => '0',
        'roll_number_format' => 'ROLL-{ID}',

        'enable_customer_profiles' => '1',
        'allow_walk_in' => '1',
        'enable_loyalty_points' => '0',

        'pm_cash' => '1',
        'pm_card' => '1',
        'pm_easypaisa' => '1',
        'pm_jazzcash' => '1',
        'pm_bank' => '1',
        'pm_cheque' => '1',

        'receipt_width' => '80mm',
        'rcpt_show_customer' => '1',
        'rcpt_show_payment' => '1',
        'rcpt_show_unit' => '1',
        'rcpt_show_qty' => '1',
        'rcpt_show_rate' => '1',
        'rcpt_show_subtotal' => '1',
        'rcpt_show_discount' => '1',
        'rcpt_show_tax' => '1',
        'rcpt_footer' => 'Thank you for shopping with us! No returns without receipt.',
        'rcpt_auto_print' => '0',

        'return_period' => '7',
        'allow_partial_returns' => '1',
        'allow_exchange' => '1',
        'require_return_approval' => '0',
        'return_restock_auto' => '1',

        'notif_low_stock' => '1',
        'notif_out_stock' => '1',
        'notif_customer_due' => '1',
        'notif_expense_created' => '0',


    ];

    public function index()
    {
        $settingsData = Setting::all()->pluck('value', 'key')->toArray();
        $settings = array_merge(self::DEFAULTS, array_intersect_key($settingsData, self::DEFAULTS));

        return view('cloth-store.settings.index', compact('settings'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_fill_keys(array_keys(self::DEFAULTS), 'nullable|string|max:2000'));

        // Since checkboxes don't send values if unchecked, we map boolean settings explicitly if they are missing
        $booleanKeys = [
            'allow_customer_credit', 'prevent_negative_stock', 'auto_low_stock_alerts',
            'enable_roll_tracking', 'enable_customer_profiles', 'allow_walk_in', 'enable_loyalty_points',
            'pm_cash', 'pm_card', 'pm_easypaisa', 'pm_jazzcash', 'pm_bank', 'pm_cheque',
            'rcpt_show_customer', 'rcpt_show_payment', 'rcpt_show_unit', 'rcpt_show_qty', 'rcpt_show_rate',
            'rcpt_show_subtotal', 'rcpt_show_discount', 'rcpt_show_tax', 'rcpt_auto_print',
            'allow_partial_returns', 'allow_exchange', 'require_return_approval', 'return_restock_auto',
            'notif_low_stock', 'notif_out_stock', 'notif_customer_due', 'notif_expense_created'
        ];

        foreach ($booleanKeys as $key) {
            if (!isset($data[$key])) {
                $data[$key] = '0';
            }
        }

        try {
            DB::beginTransaction();

            foreach ($data as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'group' => $this->determineGroup($key)]
                );
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Settings saved successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function determineGroup($key) {
        if (str_starts_with($key, 'pm_')) return 'payments';
        if (str_starts_with($key, 'rcpt_')) return 'receipts';
        if (str_starts_with($key, 'notif_')) return 'notifications';
        return 'general';
    }
}
