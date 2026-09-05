<?php

namespace App\Services;

use App\Models\Measurement;
use App\Models\Setting;

/**
 * The single source of truth for every configurable value in the app.
 *
 * `SCHEMA` declares each key once — its validation rule, its shipped default
 * and whether it holds structured JSON. Everything else in the codebase reads
 * settings through the typed accessors below rather than touching the
 * key/value table directly, so a setting can never be interpreted two
 * different ways in two different places.
 */
class Settings
{
    /* ------------------------------------------------------------------ */
    /*  Schema                                                             */
    /* ------------------------------------------------------------------ */

    public const SCHEMA = [
        /* ------------------------------- General -------------------------- */
        'app_language'   => ['rule' => 'nullable|in:English,Urdu,Sindhi', 'default' => 'English', 'group' => 'general'],
        'currency'       => ['rule' => 'required|string|max:5',   'default' => '₹',            'group' => 'general'],
        'date_format'    => ['rule' => 'nullable|in:DD/MM/YYYY,MM/DD/YYYY,YYYY-MM-DD,D MMM YYYY', 'default' => 'DD/MM/YYYY', 'group' => 'general'],
        'timezone'       => ['rule' => 'nullable|timezone',       'default' => 'Asia/Karachi', 'group' => 'general'],
        'business_hours' => ['rule' => 'nullable|array',          'default' => null, 'json' => true, 'group' => 'general'],
        'enforce_business_hours' => ['rule' => 'boolean',         'default' => '0',            'group' => 'general'],
        'monthly_revenue_target' => ['rule' => 'nullable|numeric|min:0|max:999999999', 'default' => '0', 'group' => 'general'],

        /* --------------------------- Business profile --------------------- */
        'store_name'      => ['rule' => 'required|string|max:255', 'default' => 'Atelier', 'group' => 'business'],
        'tagline'         => ['rule' => 'nullable|string|max:255', 'default' => '',        'group' => 'business'],
        'owner_name'      => ['rule' => 'nullable|string|max:255', 'default' => '',        'group' => 'business'],
        'registration_no' => ['rule' => 'nullable|string|max:100', 'default' => '',        'group' => 'business'],
        'address'         => ['rule' => 'nullable|string|max:500', 'default' => '',        'group' => 'business'],
        'phone'           => ['rule' => 'nullable|string|max:50',  'default' => '',        'group' => 'business'],
        'whatsapp_number' => ['rule' => 'nullable|string|max:50',  'default' => '',        'group' => 'business'],
        'email'           => ['rule' => 'nullable|email|max:255',  'default' => '',        'group' => 'business'],
        'website'         => ['rule' => 'nullable|string|max:255', 'default' => '',        'group' => 'business'],
        'logo_path'       => ['rule' => 'nullable|string|max:255', 'default' => '',        'group' => 'business'],
        'stamp_path'      => ['rule' => 'nullable|string|max:255', 'default' => '',        'group' => 'business'],

        /* ---------------------------- Invoice & billing ------------------- */
        'order_prefix'           => ['rule' => 'required|string|max:10', 'default' => 'ORD-', 'group' => 'invoice'],
        'invoice_prefix'         => ['rule' => 'required|string|max:10', 'default' => 'INV-', 'group' => 'invoice'],
        'tax_enabled'            => ['rule' => 'boolean',                'default' => '0',    'group' => 'invoice'],
        'tax_rate'               => ['rule' => 'nullable|numeric|min:0|max:100', 'default' => '0', 'group' => 'invoice'],
        'tax_label'              => ['rule' => 'nullable|string|max:30', 'default' => 'GST',  'group' => 'invoice'],
        'tax_inclusive'          => ['rule' => 'boolean',                'default' => '0',    'group' => 'invoice'],
        'service_charge_enabled' => ['rule' => 'boolean',                'default' => '0',    'group' => 'invoice'],
        'service_charge_rate'    => ['rule' => 'nullable|numeric|min:0|max:100', 'default' => '0', 'group' => 'invoice'],
        'service_charge_label'   => ['rule' => 'nullable|string|max:30', 'default' => 'Service Charge', 'group' => 'invoice'],
        'allow_partial'          => ['rule' => 'boolean',                'default' => '1',    'group' => 'invoice'],
        'receipt_footer'         => ['rule' => 'nullable|string|max:500', 'default' => "────────────────────────────\n    SYSTEM DEVELOPED BY\n\n      NOOR M HINGORJO\n       0303 4980786\n\n      POS & Management System\n\n         Thank You!\n────────────────────────────", 'group' => 'invoice'],
        'invoice_terms'          => ['rule' => 'nullable|string|max:1000', 'default' => '',   'group' => 'invoice'],

        /* ----------------------------- Thermal printer -------------------- */
        'printer_width'        => ['rule' => 'nullable|in:58mm,80mm', 'default' => '80mm', 'group' => 'printer'],
        'receipt_show_logo'    => ['rule' => 'boolean', 'default' => '1', 'group' => 'printer'],
        'receipt_show_phone'   => ['rule' => 'boolean', 'default' => '1', 'group' => 'printer'],
        'receipt_show_advance' => ['rule' => 'boolean', 'default' => '1', 'group' => 'printer'],
        'receipt_show_balance' => ['rule' => 'boolean', 'default' => '1', 'group' => 'printer'],
        'receipt_show_barcode' => ['rule' => 'boolean', 'default' => '1', 'group' => 'printer'],
        'receipt_show_terms'   => ['rule' => 'boolean', 'default' => '0', 'group' => 'printer'],
        'receipt_show_stamp'   => ['rule' => 'boolean', 'default' => '0', 'group' => 'printer'],

        /* ------------------------------ Measurements ---------------------- */
        'measurement_unit'     => ['rule' => 'nullable|in:cm,in', 'default' => 'cm', 'group' => 'measurements'],
        'measurement_required' => ['rule' => 'nullable|array',    'default' => null, 'json' => true, 'group' => 'measurements'],
        'measurement_decimals' => ['rule' => 'nullable|integer|min:0|max:3', 'default' => '2', 'group' => 'measurements'],

        /* ----------------------------- Notifications ---------------------- */
        'browser_notifications' => ['rule' => 'boolean', 'default' => '1', 'group' => 'notifications'],
        'sound_alerts'          => ['rule' => 'boolean', 'default' => '0', 'group' => 'notifications'],
        'payment_toasts'        => ['rule' => 'boolean', 'default' => '1', 'group' => 'notifications'],
        'alert_days_before'     => ['rule' => 'nullable|integer|min:0|max:30', 'default' => '1', 'group' => 'notifications'],
        'repeat_alerts'         => ['rule' => 'boolean', 'default' => '1', 'group' => 'notifications'],
        'repeat_alert_hours'    => ['rule' => 'nullable|integer|min:1|max:72', 'default' => '3', 'group' => 'notifications'],
        'low_stock_alert'       => ['rule' => 'nullable|integer|min:0|max:9999', 'default' => '10', 'group' => 'notifications'],
        'notify_order_created'  => ['rule' => 'boolean', 'default' => '1', 'group' => 'notifications'],
        'notify_status_changed' => ['rule' => 'boolean', 'default' => '1', 'group' => 'notifications'],
        'notify_payment'        => ['rule' => 'boolean', 'default' => '1', 'group' => 'notifications'],
        'notify_overdue'        => ['rule' => 'boolean', 'default' => '1', 'group' => 'notifications'],
        'notify_low_stock'      => ['rule' => 'boolean', 'default' => '1', 'group' => 'notifications'],

        /* -------------------------------- Workflow ------------------------ */
        'auto_status_enabled'       => ['rule' => 'boolean', 'default' => '1', 'group' => 'workflow'],
        /*
         * Auto-advance delays. Each one is "how long an order sits in this
         * status before the shop's own clock moves it on", counted in
         * `auto_status_unit`. Zero means that hop is not automated at all,
         * which is the default for every stage past the first — moving a
         * garment to Ready is a claim about physical work, and only the three
         * stages a shop explicitly opts into should ever be claimed by a timer.
         */
        'auto_status_unit'          => ['rule' => 'nullable|in:hours,minutes', 'default' => 'hours', 'group' => 'workflow'],
        'auto_status_pending_hours' => ['rule' => 'nullable|integer|min:0|max:10080', 'default' => '1', 'group' => 'workflow'],
        'auto_status_progress_delay'=> ['rule' => 'nullable|integer|min:0|max:10080', 'default' => '0', 'group' => 'workflow'],
        'auto_status_verify_delay'  => ['rule' => 'nullable|integer|min:0|max:10080', 'default' => '0', 'group' => 'workflow'],
        'auto_status_ready_delay'   => ['rule' => 'nullable|integer|min:0|max:10080', 'default' => '0', 'group' => 'workflow'],
        'auto_delivery_update'      => ['rule' => 'boolean', 'default' => '1', 'group' => 'workflow'],
        // How long before the delivery date an unfinished order starts warning.
        'at_risk_hours'             => ['rule' => 'nullable|integer|min:1|max:336', 'default' => '24', 'group' => 'workflow'],
        'delivery_slots'    => ['rule' => 'nullable|string|max:500', 'default' => '11:00 AM - 12:00 PM|3:00 PM - 4:00 PM|5:00 PM - 6:00 PM', 'group' => 'workflow'],
        'extension_reasons' => ['rule' => 'nullable|string|max:500', 'default' => 'Power Outage|Fabric Delay|Public Holiday|Staff Shortage|Machine Repair', 'group' => 'workflow'],

        /* ----------------------------- Theme & display -------------------- */
        'color_mode'     => ['rule' => 'nullable|in:light,dark,system', 'default' => 'light', 'group' => 'theme'],
        'primary_color'  => ['rule' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/', 'default' => '#4F46E5', 'group' => 'theme'],
        'sidebar_theme'  => ['rule' => 'nullable|in:white,slate,graphite,navy,midnight,espresso,steel,onyx', 'default' => 'white', 'group' => 'theme'],
        'compact_tables' => ['rule' => 'boolean',                       'default' => '0', 'group' => 'theme'],
        'rows_per_page'  => ['rule' => 'nullable|integer|min:5|max:100', 'default' => '10', 'group' => 'theme'],

        /* --------------------------- WhatsApp & alerts -------------------- */
        'whatsapp_enabled'  => ['rule' => 'boolean', 'default' => '1', 'group' => 'whatsapp'],
        'email_enabled'     => ['rule' => 'boolean', 'default' => '1', 'group' => 'whatsapp'],
        'sms_enabled'       => ['rule' => 'boolean', 'default' => '0', 'group' => 'whatsapp'],
        'whatsapp_provider' => ['rule' => 'nullable|in:manual,gateway,ultramsg', 'default' => 'manual', 'group' => 'whatsapp'],
        'ultramsg_instance' => ['rule' => 'nullable|string|max:100', 'default' => '', 'group' => 'whatsapp'],
        'ultramsg_token'    => ['rule' => 'nullable|string|max:255', 'default' => '', 'group' => 'whatsapp', 'secret' => true],

        /* Free self-hosted gateway (Baileys). Runs on the shop's own machine. */
        'gateway_url'       => ['rule' => 'nullable|url|max:255', 'default' => 'http://localhost:3001', 'group' => 'whatsapp'],
        'gateway_token'     => ['rule' => 'nullable|string|max:255', 'default' => '', 'group' => 'whatsapp', 'secret' => true],
        // When the gateway is offline, hand back a manual link instead of
        // failing — this is what makes the hybrid mode never lose a message.
        'gateway_fallback_manual' => ['rule' => 'boolean', 'default' => '1', 'group' => 'whatsapp'],
        'message_templates' => ['rule' => 'nullable|array', 'default' => null, 'json' => true, 'group' => 'whatsapp'],

        /* -------------------------------- SMS ----------------------------- */
        'sms_provider'      => ['rule' => 'nullable|in:sendpk', 'default' => 'sendpk', 'group' => 'sms'],
        'sendpk_api_key'    => ['rule' => 'nullable|string|max:255', 'default' => '', 'group' => 'sms', 'secret' => true],
        'sendpk_sender_id'  => ['rule' => 'nullable|string|max:50',  'default' => '', 'group' => 'sms'],
        'sendpk_sms_type'   => ['rule' => 'nullable|in:semi_branded,branded', 'default' => 'semi_branded', 'group' => 'sms'],
        'sms_templates'     => ['rule' => 'nullable|array', 'default' => null, 'json' => true, 'group' => 'sms'],
    ];

    /** Default weekly opening hours, used until the shop saves its own. */
    public const DEFAULT_HOURS = [
        'Monday'    => ['open' => true,  'from' => '10:00', 'to' => '20:00'],
        'Tuesday'   => ['open' => true,  'from' => '10:00', 'to' => '20:00'],
        'Wednesday' => ['open' => true,  'from' => '10:00', 'to' => '20:00'],
        'Thursday'  => ['open' => true,  'from' => '10:00', 'to' => '20:00'],
        'Friday'    => ['open' => true,  'from' => '14:00', 'to' => '20:00'],
        'Saturday'  => ['open' => true,  'from' => '10:00', 'to' => '20:00'],
        'Sunday'    => ['open' => false, 'from' => '10:00', 'to' => '20:00'],
    ];

    /** UI date format -> PHP and Day.js equivalents. */
    public const DATE_FORMATS = [
        'DD/MM/YYYY'  => ['php' => 'd/m/Y', 'js' => 'DD/MM/YYYY'],
        'MM/DD/YYYY'  => ['php' => 'm/d/Y', 'js' => 'MM/DD/YYYY'],
        'YYYY-MM-DD'  => ['php' => 'Y-m-d', 'js' => 'YYYY-MM-DD'],
        'D MMM YYYY'  => ['php' => 'j M Y', 'js' => 'D MMM YYYY'],
    ];

    /** Language -> locale code and writing direction. */
    public const LANGUAGES = [
        'English' => ['locale' => 'en', 'dir' => 'ltr'],
        'Urdu'    => ['locale' => 'ur', 'dir' => 'rtl'],
        'Sindhi'  => ['locale' => 'sd', 'dir' => 'rtl'],
    ];

    /* ------------------------------------------------------------------ */
    /*  Resolution                                                         */
    /* ------------------------------------------------------------------ */

    /** In-request memo so a single request resolves the schema only once. */
    private static ?array $resolved = null;

    /**
     * Stored values merged over defaults, with JSON keys decoded.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $stored = Setting::map();
        $out = [];

        foreach (self::SCHEMA as $key => $meta) {
            $value = $stored[$key] ?? null;

            if (!empty($meta['json'])) {
                $decoded = $value ? json_decode($value, true) : null;

                $out[$key] = match (true) {
                    is_array($decoded) && $decoded !== [] => $decoded,
                    $key === 'business_hours'       => self::DEFAULT_HOURS,
                    $key === 'message_templates'    => self::defaultTemplates(),
                    $key === 'sms_templates'        => self::defaultSmsTemplates(),
                    $key === 'measurement_required' => Measurement::REQUIRED_FIELDS,
                    default => [],
                };

                continue;
            }

            $out[$key] = $value ?? $meta['default'];
        }

        return self::$resolved = $out;
    }

    /**
     * The resolved map with secrets masked, safe to hand to the browser.
     *
     * @return array<string, mixed>
     */
    public static function forClient(): array
    {
        $all = self::all();

        foreach (self::SCHEMA as $key => $meta) {
            if (!empty($meta['secret']) && filled($all[$key] ?? null)) {
                // Confirm a token exists without ever shipping it to the client.
                $all[$key] = str_repeat('•', 12);
            }
        }

        return $all;
    }

    /** Drop the memo after a write so the next read sees fresh values. */
    public static function flush(): void
    {
        self::$resolved = null;
        Setting::flushCache();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default ?? (self::SCHEMA[$key]['default'] ?? null);
    }

    public static function bool(string $key): bool
    {
        return filter_var(self::get($key), FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function float(string $key): float
    {
        return (float) self::get($key);
    }

    public static function str(string $key): string
    {
        return (string) self::get($key);
    }

    /** @return array<mixed> */
    public static function json(string $key): array
    {
        $value = self::get($key);

        return is_array($value) ? $value : [];
    }

    /** Splits a pipe-delimited setting into a clean list. */
    public static function list(string $key): array
    {
        return collect(explode('|', self::str($key)))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /*  Typed accessors                                                    */
    /* ------------------------------------------------------------------ */

    public static function currency(): string
    {
        return self::str('currency') ?: '₹';
    }

    public static function timezone(): string
    {
        $tz = self::str('timezone');

        return in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : 'Asia/Karachi';
    }

    public static function language(): string
    {
        $lang = self::str('app_language');

        return isset(self::LANGUAGES[$lang]) ? $lang : 'English';
    }

    public static function locale(): string
    {
        return self::LANGUAGES[self::language()]['locale'];
    }

    public static function direction(): string
    {
        return self::LANGUAGES[self::language()]['dir'];
    }

    /** PHP `date()` pattern for the configured display format. */
    public static function phpDateFormat(): string
    {
        return self::DATE_FORMATS[self::str('date_format')]['php'] ?? 'd/m/Y';
    }

    /** The same pattern in the token style the browser helper understands. */
    public static function jsDateFormat(): string
    {
        return self::DATE_FORMATS[self::str('date_format')]['js'] ?? 'DD/MM/YYYY';
    }

    public static function rowsPerPage(): int
    {
        return max(5, min(100, self::int('rows_per_page') ?: 10));
    }

    /* ------------------------------ Measurements ---------------------- */

    /**
     * Which measurement columns the shop has marked mandatory. Falls back to
     * the shipped defaults, and always filters to columns that really exist.
     *
     * @return array<int, string>
     */
    public static function requiredMeasurementFields(): array
    {
        $configured = self::json('measurement_required');

        $fields = array_values(array_intersect(
            $configured ?: Measurement::REQUIRED_FIELDS,
            Measurement::FIELDS
        ));

        return $fields;
    }

    public static function measurementUnit(): string
    {
        return self::str('measurement_unit') === 'in' ? 'in' : 'cm';
    }

    public static function measurementDecimals(): int
    {
        return max(0, min(3, self::int('measurement_decimals')));
    }

    /* -------------------------------- Receipts ------------------------ */

    /**
     * Every receipt visibility switch in one array, so views never guess.
     *
     * @return array<string, bool|string>
     */
    public static function receipt(): array
    {
        return [
            'width'        => self::str('printer_width') === '58mm' ? '58mm' : '80mm',
            'show_logo'    => self::bool('receipt_show_logo'),
            'show_phone'   => self::bool('receipt_show_phone'),
            'show_advance' => self::bool('receipt_show_advance'),
            'show_balance' => self::bool('receipt_show_balance'),
            'show_barcode' => self::bool('receipt_show_barcode'),
            'show_terms'   => self::bool('receipt_show_terms'),
            'show_stamp'   => self::bool('receipt_show_stamp'),
            'footer'       => self::str('receipt_footer'),
            'terms'        => self::str('invoice_terms'),
        ];
    }

    /* ------------------------------ Business hours -------------------- */

    /** @return array<string, array{open: bool, from: string, to: string}> */
    public static function businessHours(): array
    {
        $hours = self::json('business_hours');

        return $hours ?: self::DEFAULT_HOURS;
    }

    /** Whether the shop is currently within its configured opening hours. */
    public static function isOpenNow(): bool
    {
        $now  = now(self::timezone());
        $today = self::businessHours()[$now->format('l')] ?? null;

        if (!$today || empty($today['open'])) {
            return false;
        }

        return $now->format('H:i') >= ($today['from'] ?? '00:00')
            && $now->format('H:i') <= ($today['to'] ?? '23:59');
    }

    /* --------------------------------- Templates ---------------------- */

    /**
     * Persisted message templates, guaranteed to include every shipped
     * template id so a missing entry can never break a send.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function templates(): array
    {
        $saved = collect(self::json('message_templates'))->keyBy('id');

        return collect(self::defaultTemplates())
            ->map(function (array $default) use ($saved) {
                $override = $saved->get($default['id']);

                return $override ? array_merge($default, $override) : $default;
            })
            ->values()
            ->all();
    }

    /** One template by id, or null when the shop has switched it off. */
    public static function activeTemplate(string $id): ?array
    {
        $template = collect(self::templates())->firstWhere('id', $id);

        return ($template && ($template['active'] ?? true)) ? $template : null;
    }

    /**
     * Placeholders every template may use, with a short description. Drives the
     * variable chips in Settings so the list can never drift from reality.
     *
     * @return array<string, string>
     */
    public static function templateVariables(): array
    {
        return [
            'customerName'     => "Customer's full name",
            'customerPhone'    => "Customer's phone number",
            'customerID'       => 'Customer code',
            'orderID'          => 'Order number',
            'invoiceID'        => 'Invoice number',
            'garmentType'      => 'Garment described on the order',
            'fabric'           => 'Fabric noted on the order',
            'quantity'         => 'Number of pieces',
            'dueDate'          => 'Delivery date',
            'dueTime'          => 'Delivery time slot',
            'totalAmount'      => 'Order total',
            'advancePaid'      => 'Advance already paid',
            'remainingBalance' => 'Outstanding balance',
            'paidAmount'       => 'Amount of the latest payment',
            'newDate'          => 'Rescheduled delivery date',
            'oldDate'          => 'Previous delivery date',
            'reason'           => 'Reason a date was extended',
            'status'           => 'Current order status',
            'shopName'         => 'Your shop name',
            'shopPhone'        => 'Your shop phone',
            'shopAddress'      => 'Your shop address',
            'todayDate'        => "Today's date",
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultTemplates(): array
    {
        return [
            ['id' => 'order-created', 'name' => 'ORDER CREATED', 'active' => true, 'event' => 'order.created', 'text' => "Assalam o Alaikum {customerName}!\n\nAap ka order mil gaya, shukriya! 🎉\n\n📋 Order No: {orderID}\n👔 Kapra: {garmentType}\n📅 Tayyar hoga: {dueDate}\n\n💰 Kul Raqam: {totalAmount}\n💰 Advance: {advancePaid}\n💰 Baqi: {remainingBalance}\n\n— {shopName}\n📞 {shopPhone}"],
            ['id' => 'order-ready', 'name' => 'ORDER READY', 'active' => true, 'event' => 'order.ready', 'text' => "Assalam o Alaikum {customerName}!\n\nKhushi ki khabar! 🎊\nAap ka {garmentType} tayyar ho gaya hai.\n\n💰 Baqi Raqam: {remainingBalance}\n📋 Order No: {orderID}\n\n— {shopName}\n📞 {shopPhone}"],
            ['id' => 'payment-received', 'name' => 'PAYMENT RECEIVED', 'active' => true, 'event' => 'payment.received', 'text' => "Assalam o Alaikum {customerName}!\n\nAap ki payment mil gayi, shukriya! ✅\n\n📋 Order No: {orderID}\n💰 Ada Ki Gayi Raqam: {paidAmount}\n💰 Baqi: {remainingBalance}\n\n— {shopName}\n📞 {shopPhone}"],
            ['id' => 'due-reminder', 'name' => 'DUE DATE REMINDER', 'active' => true, 'event' => 'order.due', 'text' => "Assalam o Alaikum {customerName}!\n\nAap ka {garmentType} kal tayyar ho jayega! 📅\n\n📋 Order No: {orderID}\n💰 Baqi Raqam: {remainingBalance}\n\n— {shopName}\n📞 {shopPhone}"],
            ['id' => 'due-extended', 'name' => 'DUE DATE EXTENDED', 'active' => true, 'event' => 'order.extended', 'text' => "Assalam o Alaikum {customerName}!\n\nPehle maafi chahte hain. 🙏\n\nAap ke {garmentType} ki delivery date barha di gayi hai.\n📅 Nayi Tarikh: {newDate}\n📝 Wajah: {reason}\n\n— {shopName}\n📞 {shopPhone}"],
            ['id' => 'final-receipt', 'name' => 'FINAL RECEIPT', 'active' => true, 'event' => 'order.delivered', 'text' => "Assalam o Alaikum {customerName}!\n\nShukriya tashreef lane ka! 🎉\n\n📋 Order No: {orderID}\n💰 Kul Raqam: {totalAmount}\n✅ Poora Hisaab Saaf\n\n— {shopName}\n📞 {shopPhone}"],
        ];
    }

    /* --------------------------------- SMS Templates ------------------- */

    /**
     * Persisted SMS templates, guaranteed to include every shipped
     * template id so a missing entry can never break a send.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function smsTemplates(): array
    {
        $saved = collect(self::json('sms_templates'))->keyBy('id');

        return collect(self::defaultSmsTemplates())
            ->map(function (array $default) use ($saved) {
                $override = $saved->get($default['id']);

                return $override ? array_merge($default, $override) : $default;
            })
            ->values()
            ->all();
    }

    /** One SMS template by id, or null when the shop has switched it off. */
    public static function activeSmsTemplate(string $id): ?array
    {
        $template = collect(self::smsTemplates())->firstWhere('id', $id);

        return ($template && ($template['active'] ?? true)) ? $template : null;
    }

    /**
     * Default SMS templates — shorter than WhatsApp because of the 160-char
     * ASCII limit (70 chars for Unicode). Uses the same template IDs.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultSmsTemplates(): array
    {
        return [
            ['id' => 'order-created',    'name' => 'ORDER CREATED',       'active' => true, 'event' => 'order.created',   'text' => "{shopName}: Aap ka order {orderID} mil gaya. {garmentType}, Total: {totalAmount}, Advance: {advancePaid}, Baqi: {remainingBalance}. Tayyar: {dueDate}. Shukriya!"],
            ['id' => 'order-ready',      'name' => 'ORDER READY',         'active' => true, 'event' => 'order.ready',     'text' => "{shopName}: {customerName}, aap ka {garmentType} tayyar hai! Order: {orderID}, Baqi: {remainingBalance}. Tashreef laein. {shopPhone}"],
            ['id' => 'payment-received', 'name' => 'PAYMENT RECEIVED',    'active' => true, 'event' => 'payment.received', 'text' => "{shopName}: {customerName}, {paidAmount} mil gaye, shukriya! Order: {orderID}, Baqi: {remainingBalance}. {shopPhone}"],
            ['id' => 'due-reminder',     'name' => 'DUE DATE REMINDER',   'active' => true, 'event' => 'order.due',       'text' => "{shopName}: {customerName}, aap ka {garmentType} kal tayyar hoga. Order: {orderID}, Baqi: {remainingBalance}. {shopPhone}"],
            ['id' => 'due-extended',     'name' => 'DUE DATE EXTENDED',   'active' => true, 'event' => 'order.extended',   'text' => "{shopName}: {customerName}, delivery date barhi: {newDate}. Wajah: {reason}. Maafi chahte hain. {shopPhone}"],
            ['id' => 'final-receipt',    'name' => 'FINAL RECEIPT',       'active' => true, 'event' => 'order.delivered',  'text' => "{shopName}: {customerName}, shukriya! Order {orderID}, Total: {totalAmount} — poora hisaab saaf. {shopPhone}"],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Writing                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Persists a validated payload, encoding JSON keys and normalising
     * booleans, then drops every cache that depends on settings.
     *
     * @param array<string, mixed> $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            $meta = self::SCHEMA[$key] ?? null;
            if (!$meta) {
                continue;
            }

            Setting::put(
                $key,
                self::encode($key, $value),
                $meta['group'] ?? 'general'
            );
        }

        self::flush();
        StatsService::flush();
    }

    /** Normalises one value into the string form the table stores. */
    public static function encode(string $key, mixed $value): string
    {
        $meta = self::SCHEMA[$key] ?? [];

        if (!empty($meta['json'])) {
            return json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
        }

        if (str_contains($meta['rule'] ?? '', 'boolean')) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        return (string) ($value ?? '');
    }

    /**
     * Keys belonging to one settings group, used by the per-panel reset.
     *
     * @return array<int, string>
     */
    public static function keysInGroup(string $group): array
    {
        return array_keys(array_filter(
            self::SCHEMA,
            fn ($meta) => ($meta['group'] ?? 'general') === $group
        ));
    }
}
