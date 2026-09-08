<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;

class NotificationVariables
{
    public static function variablesForOrder(Order $order, array $extra = []): array
    {
        $order->loadMissing('customer');

        $due = Dates::format($order->delivery_date, 'To be confirmed');

        // The most recent payment, which is what "paidAmount" means to a
        // customer reading a receipt. Falls back to the advance.
        $lastPayment = $order->relationLoaded('payments')
            ? (float) ($order->payments->sortByDesc('date')->first()?->amount ?? 0)
            : (float) ($order->payments()->latest('date')->value('amount') ?? 0);

        return array_merge([
            'customerName' => $order->customer?->name ?? 'Customer',
            'customerPhone' => $order->customer?->phone ?? '',
            'customerID' => $order->customer?->display_code ?? '',
            'orderID' => $order->display_number,
            'invoiceID' => $order->display_invoice,
            'garmentType' => $order->primary_item_name,
            'fabric' => $order->fabric ?? '',
            'quantity' => (string) ($order->items[0]['qty'] ?? 1),
            'dueDate' => $due,
            'dueTime' => $order->time_slot ?? '',
            'totalAmount' => Money::format($order->total),
            'advancePaid' => Money::format($order->advance),
            'remainingBalance' => Money::format($order->balance_due),
            'paidAmount' => Money::format($lastPayment ?: $order->advance),
            'status' => $order->status,

            // Only meaningful when a date is actually being changed, but given
            // sensible values here so no template ever renders a blank hole.
            'newDate' => $due,
            'oldDate' => $due,
            'reason' => 'Schedule change',
        ], self::shopVariables(), $extra);
    }

    /**
     * @return array<string, string>
     */
    public static function variablesForCustomer(Customer $customer, array $extra = []): array
    {
        return array_merge([
            'customerName' => $customer->name,
            'customerPhone' => $customer->phone ?? '',
            'customerID' => $customer->display_code,
        ], self::shopVariables(), $extra);
    }

    /**
     * @return array<string, string>
     */
    public static function shopVariables(): array
    {
        return [
            'shopName' => Settings::str('store_name') ?: 'Atelier',
            'shopPhone' => Settings::str('whatsapp_number') ?: Settings::str('phone'),
            'shopAddress' => Settings::str('address'),
            'todayDate' => Dates::format(now()),
        ];
    }

    /**
     * Substitutes {placeholders} in a template body. Unknown placeholders are
     * stripped rather than left visible in the customer's message.
     *
     * @param  array<string, string>  $variables
     */
    public static function render(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{'.$key.'}', (string) $value, $text);
        }

        return trim(preg_replace('/\{[a-zA-Z]+\}/', '', $text));
    }
}
