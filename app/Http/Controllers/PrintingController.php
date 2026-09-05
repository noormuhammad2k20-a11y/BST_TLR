<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Dates;
use App\Services\Money;
use App\Services\PricingService;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintingController extends Controller
{
    public function index()
    {
        $settings = Settings::all();

        // Recent, real records to preview against.
        $latestOrder = Order::with('customer')->latest()->first();

        $vipCustomer = Customer::where('type', 'VIP')->latest()->first()
            ?? Customer::latest()->first();

        $recentOrders = Order::with('customer:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Order $o) => [
                'id'    => $o->id,
                'label' => $o->display_number . ' · ' . ($o->customer?->name ?? 'Unknown'),
            ]);

        $customers = Customer::select('id', 'name', 'code', 'type')
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (Customer $c) => [
                'id'    => $c->id,
                'label' => $c->name . ' · ' . $c->display_code,
            ]);

        // Print volume by document type, from the audit trail.
        $counts = ActivityLog::query()
            ->where('event', 'printed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action');

        $printCounts = [
            '58mm'     => (int) ($counts['Printed 58mm'] ?? 0),
            '80mm'     => (int) ($counts['Printed 80mm'] ?? 0),
            'customer' => (int) ($counts['Printed Card'] ?? 0),
        ];

        // The printer settings the preview and the physical print both obey.
        $receiptConfig = Settings::receipt();

        return view('printing-center.index', compact(
            'settings', 'latestOrder', 'vipCustomer', 'recentOrders',
            'customers', 'printCounts', 'receiptConfig'
        ));
    }

    /**
     * Returns the data a preview template needs, for a real order or customer.
     *
     * Every optional block is resolved here against the Thermal Printer
     * settings, so the preview and the paper output can never disagree.
     */
    public function render(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'        => ['required', 'in:58mm,80mm,customer'],
            'order_id'    => ['nullable', 'integer', 'exists:orders,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        $receipt = Settings::receipt();

        $shop = [
            'name'    => $receipt['show_logo'] ? (Settings::str('store_name') ?: 'Atelier') : null,
            'logo'    => $receipt['show_logo'] ? (Settings::str('logo_path') ?: null) : null,
            'tagline' => Settings::str('tagline') ?: null,
            'address' => Settings::str('address') ?: null,
            'phone'   => Settings::str('phone') ?: null,
            'website' => Settings::str('website') ?: null,
            'footer'  => $receipt['footer'] ?: null,
            'terms'   => $receipt['show_terms'] ? ($receipt['terms'] ?: null) : null,
            'stamp'   => $receipt['show_stamp'] ? (Settings::str('stamp_path') ?: null) : null,
        ];

        if ($validated['type'] === 'customer') {
            $customer = Customer::findOrFail(
                $validated['customer_id'] ?? Customer::latest()->value('id')
            );

            return response()->json([
                'success' => true,
                'shop'    => $shop,
                'config'  => $receipt,
                'card'    => [
                    'name'  => $customer->name,
                    'code'  => $customer->display_code,
                    'type'  => $customer->type,
                    'phone' => $receipt['show_phone'] ? $customer->phone : null,
                    'since' => Dates::parse($customer->created_at)?->format('M Y'),
                ],
            ]);
        }

        $order = Order::with('customer')
            ->findOrFail($validated['order_id'] ?? Order::latest()->value('id'));

        $pricing = PricingService::breakdown((float) $order->total);

        return response()->json([
            'success' => true,
            'shop'    => $shop,
            // The requested paper size wins for a one-off print; otherwise the
            // saved default applies.
            'config'  => array_merge($receipt, ['width' => $validated['type']]),
            'receipt' => [
                'order'    => $order->display_number,
                'invoice'  => $order->display_invoice,
                'date'     => Dates::formatWithTime($order->created_at),
                'customer' => $order->customer?->name ?? 'Walk-in',
                'phone'    => $receipt['show_phone'] ? ($order->customer?->phone ?? '') : null,
                'garment'  => $order->primary_item_name,
                'fabric'   => $order->fabric ?? '',
                'lines'    => collect(PricingService::lines((float) $order->total))
                    ->map(fn (array $l) => [
                        'label'  => $l['label'],
                        'amount' => Money::format($l['amount']),
                        'muted'  => $l['muted'] ?? false,
                    ])
                    ->all(),
                'total'    => Money::format($pricing['total']),
                'advance'  => $receipt['show_advance'] ? Money::format($order->paid_amount) : null,
                'balance'  => $receipt['show_balance'] ? Money::format($order->balance_due) : null,
                'barcode'  => $receipt['show_barcode'] ? $order->display_number : null,
                'delivery' => trim(
                    Dates::format($order->delivery_date, 'To be confirmed')
                    . ' ' . ($order->time_slot ?? '')
                ),
            ],
        ]);
    }
}
