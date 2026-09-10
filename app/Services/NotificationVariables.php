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
            'garmentType' => ($order->items_migrated_at ? $order->lineItems->count() : count($order->items ?? [])) > 1 ? $order->garmentSummary() : $order->primary_item_name,
            'garmentSummary' => $order->garmentSummary(),
            'fabric' => $order->fabric ?? '',
            'quantity' => (string) $order->quantity,
            'dueDate' => $due,
            'dueTime' => $order->time_slot ?? '',
            'totalAmount' => Money::format($order->total),
            'advancePaid' => Money::format($order->advance),
            'remainingBalance' => Money::format($order->balance_due),
            'paidAmount' => Money::format($lastPayment ?: $order->advance),
            'status' => $order->status,

            // The extension event supplies the actual previous date and reason.
            // Leave absent context empty so optional SMS clauses can be omitted.
            'newDate' => $due,
            'oldDate' => '',
            'reason' => '',
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
            'shopPhone' => Settings::str('phone'),
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
        return SmsTemplateContent::render($text, $variables);
    }
}
