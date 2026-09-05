<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductService;
use App\Models\User;
use App\Services\Money;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * End-to-end WhatsApp smoke test.
 *
 * Creates real customers and orders, then walks each order through its whole
 * life — created, ready, part-paid, extended, delivered — so every template
 * fires once against a real phone. Everything it makes is tagged, so
 * `--cleanup` can remove it again without touching genuine records.
 */
class WhatsappDemo extends Command
{
    protected $signature = 'whatsapp:demo
        {--phone=* : Numbers to message. Repeat the flag for more than one.}
        {--cleanup : Delete everything a previous run created, then stop.}
        {--gap=8 : Seconds to wait between stages.}';

    protected $description = 'Send every WhatsApp template to a real number by running orders through their full lifecycle.';

    /** Written into customer notes so cleanup can find them again. */
    private const TAG = '[whatsapp-demo]';

    public function handle(OrderService $orders): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $phones = $this->option('phone') ?: ['03034980786', '03288250270'];
        $gap    = max(0, (int) $this->option('gap'));

        if (!$this->preflight()) {
            return self::FAILURE;
        }

        // Activity logging and order creation both expect a signed-in user.
        if ($admin = User::where('role', User::ROLE_ADMIN)->first()) {
            Auth::login($admin);
        }

        $garment = ProductService::query()->value('name') ?: 'Shalwar Kameez Stitching';
        $tailor  = User::tailors()->first();

        $this->newLine();
        $this->info('  Starting WhatsApp demo');
        $this->line('  Watch the phones — messages arrive a few seconds apart.');
        $this->newLine();

        foreach ($phones as $index => $phone) {
            $this->runFor($orders, $phone, $index + 1, $garment, $tailor?->id, $gap);
        }

        $this->newLine();
        $this->info('  Done. Every template has been sent.');
        $this->line('  Remove these test records with:  php artisan whatsapp:demo --cleanup');
        $this->newLine();

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */

    /** Refuses to run unless a message would actually reach a phone. */
    private function preflight(): bool
    {
        if (!WhatsAppService::enabled()) {
            $this->error('  WhatsApp is switched off.');
            $this->line('  Turn it on: Settings -> Notifications -> Delivery Channels -> WhatsApp');

            return false;
        }

        $provider = WhatsAppService::provider();
        $this->line('  Provider: ' . $provider);

        if ($provider === 'manual') {
            $this->error('  Provider is set to Manual, so nothing will send by itself.');
            $this->line('  Switch to "Free Gateway" in Settings -> WhatsApp & Alerts.');

            return false;
        }

        if ($provider === 'gateway') {
            $status = WhatsAppService::gatewayStatus();

            if (!$status['running']) {
                $this->error('  The gateway is not running.');
                $this->line('  Run whatsapp-gateway\\start-gateway.bat and leave the window open.');

                return false;
            }

            if (!$status['connected']) {
                $this->error('  The gateway is running but no phone is linked.');
                $this->line('  Open http://localhost:3001/qr and scan the code.');

                return false;
            }

            $this->line('  Gateway linked to: ' . ($status['phone'] ?? 'unknown'));
        }

        return true;
    }

    /**
     * One customer, one order, taken through every stage that sends a message.
     */
    private function runFor(OrderService $orders, string $phone, int $n, string $garment, ?int $tailorId, int $gap): void
    {
        $clean = preg_replace('/\D+/', '', $phone);

        // Local numbers are stored the way the sender needs them.
        if (str_starts_with($clean, '0')) {
            $clean = '92' . substr($clean, 1);
        }

        $this->newLine();
        $this->line("  ── Customer {$n}: {$phone} → {$clean}");

        $customer = Customer::updateOrCreate(
            ['phone' => $clean],
            [
                'name'  => "Test Customer {$n}",
                'type'  => 'Regular',
                'city'  => 'Karachi',
                'notes' => self::TAG . ' created by the WhatsApp demo',
            ]
        );

        /* 1. Order created ------------------------------------------------ */
        $order = $orders->create([
            'customer_id'    => $customer->id,
            'garment'        => $garment,
            'fabric'         => 'Wash n Wear',
            'total'          => 2500,
            'advance'        => 500,
            'delivery_date'  => now()->addDays(2)->toDateString(),
            'time_slot'      => '5:00 PM - 6:00 PM',
            'tailor_id'      => $tailorId,
            'payment_method' => 'Cash',
            'notes'          => self::TAG,
        ]);

        $this->stage('ORDER CREATED', $order->display_number, $gap);

        /* 2. Due reminder -------------------------------------------------- */
        // Sent directly so the demo does not have to wait for the alert window.
        $this->send('due-reminder', $order, [], 'DUE DATE REMINDER', $gap);

        /* 3. Date extended ------------------------------------------------- */
        $oldDate = $order->delivery_date?->copy();
        $order->forceFill(['delivery_date' => now()->addDays(5)])->save();

        $this->send('due-extended', $order->fresh()->load('customer'), [
            'newDate' => \App\Services\Dates::format($order->fresh()->delivery_date),
            'oldDate' => \App\Services\Dates::format($oldDate),
            'reason'  => 'Fabric Delay',
        ], 'DUE DATE EXTENDED', $gap);

        /* 4. Ready --------------------------------------------------------- */
        $order = $orders->changeStatus($order->fresh(), 'Ready', 'Demo: marked ready');
        $this->send('order-ready', $order->fresh()->load('customer'), [], 'ORDER READY', $gap);

        /* 5. Part payment -------------------------------------------------- */
        Payment::create([
            'invoice_id'     => $order->invoice_number,
            'order_id'       => $order->id,
            'customer_id'    => $customer->id,
            'amount'         => 1000,
            'type'           => 'Receipt',
            'status'         => 'Completed',
            'payment_method' => 'Cash',
            'date'           => now(),
            'recorded_by'    => Auth::id(),
        ]);
        $orders->recalculateBalance($order->fresh());

        $this->send('payment-received', $order->fresh()->load('customer'), [
            'paidAmount' => Money::format(1000),
        ], 'PAYMENT RECEIVED', $gap);

        /* 6. Settled and delivered ----------------------------------------- */
        Payment::create([
            'invoice_id'     => $order->invoice_number,
            'order_id'       => $order->id,
            'customer_id'    => $customer->id,
            'amount'         => 1000,
            'type'           => 'Receipt',
            'status'         => 'Completed',
            'payment_method' => 'Cash',
            'date'           => now(),
            'recorded_by'    => Auth::id(),
        ]);
        $orders->recalculateBalance($order->fresh());
        $orders->changeStatus($order->fresh(), 'Delivered', 'Demo: delivered');

        $this->send('final-receipt', $order->fresh()->load('customer'), [], 'FINAL RECEIPT', $gap);
    }

    /** Reports a message that the app itself sent as a side effect. */
    private function stage(string $label, string $reference, int $gap): void
    {
        $this->line("     ✓ {$label}  ({$reference})");
        $this->wait($gap);
    }

    /** Sends one template and reports what happened. */
    private function send(string $templateId, Order $order, array $extra, string $label, int $gap): void
    {
        $result = WhatsAppService::sendTemplate($templateId, $order, $extra);

        match (true) {
            $result === null   => $this->warn("     – {$label} skipped (template is switched off)"),
            $result['sent']    => $this->line("     ✓ {$label}"),
            default            => $this->error("     ✗ {$label}: " . ($result['error'] ?? 'failed')),
        };

        $this->wait($gap);
    }

    /**
     * The gateway paces its own sending, so this only exists to keep the
     * console readable and the arrival order obvious on the phone.
     */
    private function wait(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }

    /* ------------------------------------------------------------------ */

    private function cleanup(): int
    {
        $customers = Customer::where('notes', 'like', '%' . self::TAG . '%')->get();

        if ($customers->isEmpty()) {
            $this->info('  Nothing to clean up.');

            return self::SUCCESS;
        }

        foreach ($customers as $customer) {
            $orderIds = Order::where('customer_id', $customer->id)->pluck('id');

            Payment::whereIn('order_id', $orderIds)->delete();
            Order::whereIn('id', $orderIds)->delete();
            $customer->delete();
        }

        NotificationService::flushCache();

        $this->info(sprintf('  Removed %d demo customer(s) and their orders.', $customers->count()));

        return self::SUCCESS;
    }
}
