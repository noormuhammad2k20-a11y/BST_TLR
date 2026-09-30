<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;

class NotificationVariables
{
    public static function forRecipient(Order $order, ?string $message): ?string
    {
        if ($message === null || $message === '') return $message;
        $owner = $order->customer;
        $contact = $owner?->effectiveContact();
        if (!$contact || $contact->id === $owner->id) return $message;
        $names = SmsTemplateContent::variables(['owner'=>$owner->name, 'contact'=>$contact->name, 'order'=>$order->display_number]);
        $context = "Dear {$names['contact']}, regarding {$names['owner']}'s order {$names['order']}:";
        $greeting = 'Dear '.$names['owner'].',';
        // Preserve custom wording; replace the known greeting when possible.
        if (str_contains($message, $greeting)) {
            return preg_replace_callback('/'.preg_quote($greeting, '/').'/', fn () => $context, $message, 1);
        }
        return $context."\n".$message;
    }

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
            'customerPhone' => $order->customer?->effectivePhone() ?? '',
            'contactName' => $order->customer?->effectiveContact()?->name ?? '',
            'relationship' => $order->customer?->relationship ?? '',
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
            'remainingBalance' => number_format($order->balance_due, 0),
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
            'customerPhone' => $customer->effectivePhone() ?? '',
            'contactName' => $customer->effectiveContact()?->name ?? '',
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
