<?php

namespace App\View\Composers;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Setting;
use App\Services\NotificationService;
use App\Services\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Feeds the sidebar badges, header bell and notification drawer. Everything is
 * cached briefly and resolved in a single pass so the chrome around each page
 * costs a couple of milliseconds instead of a query per badge.
 */
class LayoutComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'layoutCounters'      => auth()->user()?->role==='tailor' ? ['customers'=>0,'pending_orders'=>0,'unread_notifications'=>0] : $this->counters(),
            'layoutNotifications' => auth()->user()?->role==='tailor' ? collect() : $this->recentNotifications(),

            // The raw key/value map, kept for views that still read it directly.
            'shopSettings'        => Settings::forClient(),

            // Everything resolved against defaults and typed, which is what the
            // layout and the client runtime should be using.
            'appSettings'         => Settings::all(),
            'appDisplay'          => $this->display(),
            'notificationPrefs'   => NotificationService::clientPreferences(),
        ]);
    }

    /**
     * Appearance and formatting choices the layout applies before first paint,
     * so the theme never flashes and dates read the same on every page.
     *
     * @return array<string, mixed>
     */
    public function display(): array
    {
        return [
            'colorMode'     => Settings::str('color_mode') ?: 'light',
            'primaryColor'  => Settings::str('primary_color') ?: '#4F46E5',
            'sidebarAppearance' => app(\App\Services\SidebarAppearance::class)->current(),
            'compactTables' => Settings::bool('compact_tables'),
            'rowsPerPage'   => Settings::rowsPerPage(),
            'dateFormat'    => Settings::jsDateFormat(),
            'timezone'      => Settings::timezone(),
            'currency'      => Settings::currency(),
            'locale'        => Settings::locale(),
            'direction'     => Settings::direction(),
            'language'      => Settings::language(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function counters(): array
    {
        return Cache::remember('layout.counters', 30, fn () => [
            'customers'           => Customer::count(),
            'pending_orders'      => Order::where('status', 'Pending')->count(),
            'unread_notifications' => Notification::unread()->count(),
        ]);
    }

    public function recentNotifications()
    {
        return Cache::remember(
            'layout.notifications.recent',
            30,
            fn () => Notification::query()
                ->latest()
                ->limit(10)
                ->get([
                    'id', 'title', 'type', 'message', 'category', 'icon',
                    'color', 'action_url', 'is_read', 'created_at',
                ])
        );
    }
}
