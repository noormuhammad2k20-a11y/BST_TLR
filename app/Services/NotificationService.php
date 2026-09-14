<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

/**
 * Creates the in-app notifications that feed the header bell, the drawer and
 * the Notification Center. Keeps icon/colour selection in one place so the
 * existing visual language is applied consistently.
 */
class NotificationService
{
    public const CACHE_UNREAD = 'notifications.unread_count';

    /**
     * Which Settings toggle governs each notification category. A category with
     * no entry is always allowed, so system messages can never be silenced.
     */
    private const CATEGORY_SWITCH = [
        'orders'   => 'notify_order_created',
        'payments' => 'notify_payment',
        'alerts'   => 'notify_overdue',
        'stock'    => 'notify_low_stock',
    ];

    /**
     * Whether the shop wants this kind of notification recorded at all.
     */
    public static function wants(string $category): bool
    {
        $key = self::CATEGORY_SWITCH[$category] ?? null;

        return $key === null || Settings::bool($key);
    }

    public static function push(
        string $title,
        string $message,
        string $category = 'system',
        ?string $icon = null,
        ?string $color = null,
        ?Order $order = null,
        ?Customer $customer = null,
        ?string $actionUrl = null,
    ): ?Notification {
        // Respect the Notifications panel: a switched-off category is not
        // merely hidden, it is never written.
        if (!self::wants($category)) {
            return null;
        }

        $notification = Notification::create([
            'title'       => $title,
            'message'     => $message,
            'type'        => ucfirst($category),
            'category'    => $category,
            'icon'        => $icon ?: self::iconFor($category),
            'color'       => $color ?: self::colorFor($category),
            'action_url'  => $actionUrl,
            'order_id'    => $order?->id,
            'customer_id' => $customer?->id ?? $order?->customer_id,
            'is_read'     => false,
        ]);

        self::flushCache();

        return $notification;
    }

    /** Unique database key, shared across workers and tabs; cache eviction cannot duplicate it. */
    public static function pushOnce(string $key, string $title, string $message, ?Order $order = null, ?string $actionUrl = null): ?Notification
    {
        $notification = Notification::firstOrCreate(['event_key' => $key], [
            'title' => $title, 'message' => $message, 'type' => 'Alerts', 'category' => 'alerts',
            'icon' => 'fa-solid fa-clock', 'color' => 'warning', 'order_id' => $order?->id,
            'customer_id' => $order?->customer_id, 'action_url' => $actionUrl, 'is_read' => false,
        ]);
        if (!$notification->wasRecentlyCreated) return null;
        self::flushCache();
        return $notification;
    }

    public static function orderCreated(Order $order): ?Notification
    {
        return self::push(
            'New Order Created',
            sprintf(
                '%s placed an order for %s worth %s',
                $order->customer?->name ?? 'A customer',
                $order->primary_item_name,
                Money::format($order->total),
            ),
            'orders',
            'fa-solid fa-box',
            'primary',
            $order,
            actionUrl: route('orders.index'),
        );
    }

    public static function orderStatusChanged(Order $order, string $from, string $to): ?Notification
    {
        if (!Settings::bool('notify_status_changed')) {
            return null;
        }

        return self::push(
            'Order Status Updated',
            sprintf(
                '%s (%s) moved from %s to %s',
                $order->display_number,
                $order->customer?->name ?? 'Unknown',
                $from,
                $to,
            ),
            'orders',
            'fa-solid fa-arrows-rotate',
            $to === 'Ready' ? 'success' : 'info',
            $order,
            actionUrl: route('orders.index'),
        );
    }

    public static function paymentReceived(Order $order, float $amount, string $method): ?Notification
    {
        return self::push(
            'Payment Received',
            sprintf(
                '%s paid %s via %s for %s',
                $order->customer?->name ?? 'Customer',
                Money::format($amount),
                $method,
                $order->display_invoice,
            ),
            'payments',
            'fa-solid fa-indian-rupee-sign',
            'success',
            $order,
            actionUrl: route('payments-billing.index'),
        );
    }

    public static function smsSent(Order $order): ?Notification
    {
        return self::push(
            'Customer Notice Accepted',
            sprintf(
                'Pickup message accepted for sending to %s for %s',
                $order->customer?->name ?? 'Customer',
                $order->display_number,
            ),
            'sms',
            'fa-solid fa-comment-sms',
            'success',
            $order,
            actionUrl: route('orders.index'),
        );
    }

    public static function lowStock(string $productName, int $quantity): ?Notification
    {
        if (!self::mayRepeat('low-stock-' . md5($productName))) {
            return null;
        }

        return self::push(
            'Low Stock Alert',
            sprintf('%s is running low — only %d left in stock', $productName, $quantity),
            'stock',
            'fa-solid fa-boxes-stacked',
            'warning',
            actionUrl: route('products-services.index'),
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Rule-driven alerts                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * The stock level at which an item counts as low, unless that item
     * overrides it with its own threshold.
     */
    public static function lowStockThreshold(): int
    {
        return max(0, Settings::int('low_stock_alert'));
    }

    /**
     * Applies the repeat-alert rule.
     *
     * With repeats off, a given subject alerts once and never again. With them
     * on, it may alert again once `repeat_alert_hours` have passed.
     */
    public static function mayRepeat(string $subject): bool
    {
        $key = 'notify.last.' . $subject;
        $ttl = Settings::bool('repeat_alerts')
            ? now()->addHours(max(1, Settings::int('repeat_alert_hours')))
            : now()->addDays(30);
        return Cache::add($key, now()->toIso8601String(), $ttl);
    }

    public static function unreadCount(): int
    {
        return Cache::remember(
            self::CACHE_UNREAD,
            now()->addSeconds(15),
            fn () => Notification::unread()->count()
        );
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_UNREAD);
        Cache::forget('layout.notifications.recent');
    }

    /**
     * Client-side notification preferences, handed to the browser runtime so
     * chimes, desktop alerts and payment toasts obey the same settings.
     *
     * @return array<string, bool|int>
     */
    public static function clientPreferences(): array
    {
        return [
            'browser'        => Settings::bool('browser_notifications'),
            'sound'          => Settings::bool('sound_alerts'),
            'paymentToasts'  => Settings::bool('payment_toasts'),
            'repeat'         => Settings::bool('repeat_alerts'),
            'repeatHours'    => max(1, Settings::int('repeat_alert_hours')),
            'lowStock'       => self::lowStockThreshold(),
        ];
    }

    private static function iconFor(string $category): string
    {
        return match ($category) {
            'orders'   => 'fa-solid fa-box',
            'payments' => 'fa-solid fa-indian-rupee-sign',
            'stock'    => 'fa-solid fa-boxes-stacked',
            'sms' => 'fa-solid fa-comment-sms',
            'alerts'   => 'fa-solid fa-triangle-exclamation',
            default    => 'fa-solid fa-circle-info',
        };
    }

    private static function colorFor(string $category): string
    {
        return match ($category) {
            'orders'   => 'primary',
            'payments' => 'success',
            'stock'    => 'warning',
            'sms' => 'success',
            'alerts'   => 'danger',
            default    => 'info',
        };
    }
}
