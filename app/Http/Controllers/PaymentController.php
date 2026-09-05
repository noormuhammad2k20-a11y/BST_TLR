<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\Dates;
use App\Services\Money;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Services\Settings;
use App\Services\SmsService;
use App\Services\StatsService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index()
    {
        $orders = Order::query()
            ->with([
                'customer:id,name,phone,code',
                'payments' => fn ($q) => $q->select('id', 'order_id', 'amount', 'type', 'payment_method', 'date')
                    ->where('status', 'Completed')->whereNull('reverses_payment_id')->orderBy('date'),
            ])
            ->withPaymentTotals()
            ->latest()
            ->get();

        $invoices = $orders->map(fn (Order $o) => $this->serialize($o));

        $totalCollected = round($invoices->sum('paid'), 2);
        $invoiced       = round($invoices->sum('amt'), 2);
        $pending        = round(max($invoiced - $totalCollected, 0), 2);
        $totalInvoices  = $invoices->count();
        $paidInvoices   = $invoices->where('status', 'Paid')->count();
        $collectionRate = $totalInvoices > 0 ? round($paidInvoices / $totalInvoices * 100, 1) : 0.0;

        $methods = Payment::METHODS;

        return view('payments-billing.index', compact(
            'invoices', 'totalCollected', 'pending', 'totalInvoices', 'paidInvoices', 'collectionRate', 'methods'
        ));
    }

    /**
     * Record a payment against an order, then re-derive the order balance and
     * fulfilment status from the actual ledger.
     */
    public function record(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'amount'=>['required','numeric','min:0.01','decimal:0,2'],
            'payment_method'=>['required',Rule::in(Payment::METHODS)],
            'reference'=>'nullable|string|max:100','notes'=>'nullable|string|max:1000','date'=>'nullable|date',
            'operation_key'=>'nullable|string|max:100',
        ]);

        $payment = app(\App\Services\TailoringFinanceService::class)->record($order->id, $validated);

        NotificationService::paymentReceived($order, (float) $validated['amount'], $validated['payment_method']);
        ActivityLogger::log(
            'Payment recorded',
            sprintf(
                '%s paid %s via %s for %s',
                $order->customer?->name ?? 'Customer',
                Money::format($validated['amount']),
                $validated['payment_method'],
                $order->display_invoice
            ),
            'payments',
            $payment,
            ['amount' => $validated['amount']],
            'created'
        );

        StatsService::flush();

        $order = $order->fresh()->load('customer')->loadPaymentTotals();

        // Send the shop's own "payment received" template, if that template and
        // the WhatsApp channel are both switched on.
        $whatsapp = WhatsAppService::sendTemplate(
            $order->balance_due <= 0 ? 'final-receipt' : 'payment-received',
            $order,
            ['paidAmount' => Money::format($validated['amount'])]
        );

        // SMS channel — fires independently of WhatsApp.
        SmsService::sendTemplate(
            $order->balance_due <= 0 ? 'final-receipt' : 'payment-received',
            $order,
            ['paidAmount' => Money::format($validated['amount'])]
        );

        return response()->json([
            'success'  => true,
            'message'  => Money::format($validated['amount']) . ' recorded successfully.',
            'invoice'  => $this->serialize($order),
            'whatsapp' => $whatsapp,
            // The client only pops a payment toast when the shop asked for one.
            'toast'    => Settings::bool('payment_toasts'),
        ], 201);
    }

    /**
     * Full invoice payload for the print/preview modal.
     */
    public function invoice(Order $order): JsonResponse
    {
        $order->load(['customer', 'payments' => fn ($q) => $q->latest('date')]);

        $total = (float) $order->total;

        // Tax and service charge come entirely from Invoice & Billing settings:
        // whether they apply, what they are called and how they are calculated.
        $pricing = PricingService::breakdown($total);
        $receipt = Settings::receipt();

        return response()->json([
            'success' => true,
            'invoice' => [
                'number'    => $order->display_invoice,
                'order'     => $order->display_number,
                'date'      => Dates::format($order->created_at),
                'due'       => Dates::format($order->delivery_date, null),
                'store'     => Settings::str('store_name') ?: 'Atelier',
                'address'   => Settings::str('address') ?: null,
                'phone'     => Settings::str('phone') ?: null,
                'email'     => Settings::str('email') ?: null,
                'website'   => Settings::str('website') ?: null,
                'logo'      => Settings::str('logo_path') ?: null,
                'stamp'     => $receipt['show_stamp'] ? (Settings::str('stamp_path') ?: null) : null,
                'terms'     => $receipt['show_terms'] ? ($receipt['terms'] ?: null) : null,
                'footer'    => $receipt['footer'] ?: null,
                'customer'  => [
                    'name'  => $order->customer?->name,
                    'phone' => $receipt['show_phone'] ? $order->customer?->phone : null,
                    'city'  => $order->customer?->city,
                    'code'  => $order->customer?->display_code,
                ],
                'items'     => [[
                    'name'  => $order->primary_item_name,
                    'desc'  => $order->fabric,
                    'qty'   => $order->items[0]['qty'] ?? 1,
                    'price' => $pricing['subtotal'],
                ]],
                'subtotal'  => $pricing['subtotal'],
                'tax_rate'  => $pricing['tax_rate'],
                'tax_label' => $pricing['tax_label'],
                'tax'       => $pricing['tax_enabled'] ? $pricing['tax'] : 0.0,
                'tax_enabled' => $pricing['tax_enabled'],
                'service_charge'       => $pricing['service_charge_enabled'] ? $pricing['service_charge'] : 0.0,
                'service_charge_label' => $pricing['service_charge_label'],
                'service_charge_rate'  => $pricing['service_charge_rate'],
                'service_charge_enabled' => $pricing['service_charge_enabled'],
                'lines'     => PricingService::lines($total),
                'total'     => $pricing['total'],
                'paid'      => $receipt['show_advance'] ? $order->paid_amount : null,
                'balance'   => $receipt['show_balance'] ? $order->balance_due : null,
                'status'    => $order->payment_status,
                'payments'  => $order->payments->map(fn (Payment $p) => [
                    'amount' => (float) $p->amount,
                    'method' => $p->payment_method,
                    'date'   => Dates::format($p->date, null),
                    'ref'    => $p->reference,
                ]),
                'currency'  => Money::symbol(),
            ],
        ]);
    }

    public function destroy(Payment $paymentsBilling): JsonResponse
    {
        $payment = $paymentsBilling;
        $order   = $payment->order;
        $amount  = (float) $payment->amount;

        app(\App\Services\TailoringFinanceService::class)->reverse($payment->id);

        ActivityLogger::log(
            'Payment reversed',
            sprintf('%s reversed on %s', Money::format($amount), $order?->display_invoice ?? 'an invoice'),
            'payments',
            null,
            ['amount' => $amount],
            'deleted'
        );

        StatsService::flush();

        return response()->json(['success' => true, 'message' => 'Payment reversed successfully.']);
    }

    /* ------------------------------------------------------------------ */

    private function serialize(Order $o): array
    {
        // `paid_amount` counts the advance once: it is already a payment row.
        $paid    = $o->paid_amount;
        $total   = (float) $o->total;
        $balance = round(max($total - $paid, 0), 2);

        $status = $paid <= 0 ? 'Pending' : ($balance <= 0 ? 'Paid' : 'Partial');

        // Anything unpaid past its delivery date needs chasing.
        if ($status !== 'Paid' && $o->delivery_date && $o->delivery_date->isPast()) {
            $status = 'Overdue';
        }

        return [
            'db_id'    => $o->id,
            'id'       => $o->display_invoice,
            'order'    => $o->display_number,
            'cust'     => $o->customer?->name ?? 'Unknown',
            'phone'    => $o->customer?->phone ?? '',
            'gmt'      => $o->primary_item_name,
            'amt'      => $total,
            'paid'     => $paid,
            'balance'  => $balance,
            'method'   => $paid > 0
                ? ($o->relationLoaded('payments') ? ($o->payments->last()?->payment_method ?? 'Cash') : 'Cash')
                : '—',
            'date'     => $o->created_at->format('M d'),
            'fullDate' => $o->created_at->format('Y-m-d'),
            'status'   => $status,
        ];
    }
}
