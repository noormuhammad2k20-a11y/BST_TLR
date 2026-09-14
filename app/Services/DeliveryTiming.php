<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class DeliveryTiming
{
    public static function slotTime(?string $slot): ?string
    {
        $parts = preg_split('/\s*[-–—]\s*/u', trim($slot ?? ''));
        $time = trim(end($parts) ?: '');
        foreach (['H:i', 'H:i:s', 'g:i A', 'h:i A', 'g A'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!'.$format, strtoupper($time), Settings::timezone());
                if ($parsed && $parsed->format($format) === strtoupper($time)) return $parsed->format('H:i');
            } catch (\Throwable) { }
        }
        return null;
    }

    public static function promise(array $data, ?Order $existing = null): ?Carbon
    {
        if (!array_key_exists('delivery_date', $data) && !array_key_exists('delivery_time', $data) && !array_key_exists('time_slot', $data)) return $existing?->delivery_date;
        $raw = $data['delivery_date'] ?? $existing?->delivery_date;
        if (!$raw) return null;
        $date = Carbon::parse($raw, Settings::timezone());
        $time = $data['delivery_time'] ?? self::slotTime($data['time_slot'] ?? null);
        if (!$time && $existing) $time = $existing->delivery_date?->format('H:i');
        if (!$time && (is_object($raw) || preg_match('/[T ]\d{2}:\d{2}/', (string) $raw))) $time = $date->format('H:i');
        if (!$time || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw ValidationException::withMessages(['delivery_time' => 'Choose an exact promised delivery time.']);
        }
        return $date->setTimeFromTimeString($time)->startOfMinute();
    }

    /** Reuse the weekly shop hours; do not invent a second opening-time setting. */
    public static function hours(Carbon $date): ?array
    {
        $hours = Settings::businessHours()[$date->format('l')] ?? null;
        if (!$hours || empty($hours['open'])) return null;
        $opening = $date->copy()->startOfDay()->setTimeFromTimeString($hours['from']);
        $closing = $date->copy()->startOfDay()->setTimeFromTimeString($hours['to']);
        if ($closing->lte($opening)) $closing->addDay();
        return [$opening, $closing];
    }

    public static function isOpen(Carbon $at): bool
    {
        foreach ([$at, $at->copy()->subDay()] as $date) {
            $hours = self::hours($date);
            if ($hours && $at->gte($hours[0]) && $at->lt($hours[1])) return true;
        }
        return false;
    }

    public static function attentionAt(?Carbon $delivery): ?Carbon
    {
        if (!$delivery) return null;
        $day = $delivery->copy();
        // Closed-day promises become actionable at the next configured opening.
        for ($i = 0; $i < 8; $i++, $day->addDay()) {
            if ($hours = self::hours($day)) {
                $mathematical = $delivery->copy()->subHours(Settings::int('delivery_alert_hours'));
                return $mathematical->max($hours[0]);
            }
        }
        return null;
    }

    public static function describe(Order $order): array
    {
        $due = $order->delivery_date;
        $attention = self::attentionAt($due);
        $now = now(Settings::timezone());
        $unfinished = in_array($order->status, Order::UNVERIFIED_STATUSES, true);
        $overdue = $due && $unfinished && $now->gt($due);
        $soon = $due && $unfinished && !$overdue && $attention && $now->gte($attention);
        $today = $due && $due->isSameDay($now) && in_array($order->status, Order::OPEN_STATUSES, true);
        $minutes = $due ? (int) ceil(abs($now->diffInSeconds($due, false)) / 60) : null;
        $duration = $minutes === null ? '' : (($minutes >= 60 ? intdiv($minutes, 60).'h ' : '').($minutes % 60 || $minutes < 60 ? ($minutes % 60).'m' : ''));
        $indicator = match (true) {
            $order->status === 'Delivered' => 'Delivered',
            $order->status === 'Ready' => 'Ready for Pickup',
            (bool) $overdue => 'Overdue', (bool) $soon => 'Due Soon', (bool) $today => 'Today', default => '',
        };
        $text = match ($indicator) {
            'Overdue' => 'Overdue by '.trim($duration), 'Due Soon' => 'Due in '.trim($duration),
            'Today' => 'Today • '.$due->format('g:i A'),
            'Ready for Pickup' => 'Ready for Pickup'.($due ? ' • '.$due->format('g:i A') : ''),
            default => $due?->format('M d • g:i A') ?? 'No delivery time',
        };
        return ['indicator' => $indicator, 'text' => $text, 'overdue' => (bool) $overdue, 'dueSoon' => (bool) $soon,
            'dueToday' => (bool) $today, 'attentionAt' => $attention?->toIso8601String(),
            'deliveryAt' => $due?->toIso8601String(), 'deliveryDate' => $due?->format('Y-m-d'), 'deliveryTime' => $due?->format('H:i'),
            'readyLate' => $due && $order->completed_at && $order->completed_at->gt($due)];
    }
}
