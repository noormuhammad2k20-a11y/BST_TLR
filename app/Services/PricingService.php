<?php

namespace App\Services;

/**
 * The single place that decides how tax and service charge affect a bill.
 *
 * Two distinct jobs, deliberately kept apart:
 *
 *   grandTotal()  — takes the price a user typed and returns what the customer
 *                   owes. An *exclusive* rate is added on top here; an
 *                   *inclusive* rate is already contained in the typed price.
 *
 *   breakdown()   — takes a stored `orders.total` and splits it into lines.
 *                   Because grandTotal() has already run, a stored total always
 *                   contains any tax and charge, so this always works
 *                   backwards. That is what keeps an invoice's line items and
 *                   an order's outstanding balance from ever disagreeing.
 */
class PricingService
{
    public static function forOrder(\App\Models\Order $order): array
    {
        $snapshot = $order->billing_snapshot;
        if (!$snapshot) return self::breakdown((float)$order->total);
        return array_merge(['subtotal' => (float)$order->total, 'tax' => 0, 'tax_rate' => 0, 'tax_label' => 'Tax',
            'tax_enabled' => false, 'tax_inclusive' => true, 'service_charge' => 0, 'service_charge_rate' => 0,
            'service_charge_label' => 'Service Charge', 'service_charge_enabled' => false, 'total' => (float)$order->total,
            'currency' => Settings::currency()], $snapshot);
    }

    public static function orderLines(\App\Models\Order $order): array
    {
        $b = self::forOrder($order);
        $rows = [['label' => 'Subtotal', 'amount' => (float)$b['subtotal'], 'muted' => true]];
        foreach (['tax' => 'tax_label', 'service_charge' => 'service_charge_label'] as $key => $label) {
            if ($b[$key.'_enabled']) $rows[] = ['label' => $b[$label], 'amount' => (float)$b[$key], 'muted' => true];
        }
        if (!empty($b['adjustment']) && Decimal::cmp((string)$b['adjustment'], '0') !== 0) $rows[] = ['label' => 'Historical adjustment', 'amount' => (float)$b['adjustment'], 'muted' => true];
        $rows[] = ['label' => 'Total', 'amount' => (float)$b['total']];
        return $rows;
    }

    public static function invoiceItems(\App\Models\Order $order): array
    {
        if ($order->items_migrated_at) return $order->lineItems->map(fn($i) => ['name' => $i->name, 'desc' => $i->fabric,
            'qty' => $i->quantity, 'unit_price' => (float)$i->unit_price, 'price' => (float)$i->subtotal])->all();
        return array_map(fn($i) => ['name' => $i['name'] ?? $order->garment, 'desc' => $i['fabric'] ?? $order->fabric,
            'qty' => max(1,(int)($i['qty'] ?? 1)), 'price' => (float)($i['price'] ?? $order->total)], $order->items ?: [['name' => $order->primary_item_name, 'qty' => 1, 'price' => $order->total]]);
    }

    public static function allocatedItems(\App\Models\Order $order): array
    {
        $items = self::invoiceItems($order); $weight = '0.00';
        foreach ($items as $item) $weight = Decimal::add($weight, (string)$item['price']);
        $remaining = (string)$order->total;
        foreach ($items as $i => &$item) {
            $amount = $i === count($items)-1 ? $remaining : Decimal::ratio((string)$order->total, Decimal::cmp($weight,'0') ? (string)$item['price'] : '1', Decimal::cmp($weight,'0') ? $weight : (string)count($items));
            $item['revenue'] = $amount; $remaining = Decimal::sub($remaining,$amount);
        }
        return $items;
    }
    /** Combined tax + service charge as a multiplier, e.g. 1.17. */
    private static function multiplier(): float
    {
        $tax    = Settings::bool('tax_enabled') ? Settings::float('tax_rate') : 0.0;
        $charge = Settings::bool('service_charge_enabled') ? Settings::float('service_charge_rate') : 0.0;

        return 1 + ($tax / 100) + ($charge / 100);
    }

    /**
     * @return array{
     *     subtotal: float, tax: float, tax_rate: float, tax_label: string,
     *     tax_enabled: bool, tax_inclusive: bool,
     *     service_charge: float, service_charge_rate: float,
     *     service_charge_label: string, service_charge_enabled: bool,
     *     total: float, currency: string
     * }
     */
    public static function breakdown(float $total): array
    {
        $taxEnabled    = Settings::bool('tax_enabled');
        $taxRate       = $taxEnabled ? Settings::float('tax_rate') : 0.0;

        $chargeEnabled = Settings::bool('service_charge_enabled');
        $chargeRate    = $chargeEnabled ? Settings::float('service_charge_rate') : 0.0;

        $total      = round($total, 2);
        $multiplier = self::multiplier();

        // A stored total always contains tax and charge, so split it back out.
        $subtotal = $multiplier > 0 ? round($total / $multiplier, 2) : $total;

        $tax    = round($subtotal * $taxRate / 100, 2);
        $charge = round($subtotal * $chargeRate / 100, 2);

        return [
            'subtotal'               => $subtotal,
            'tax'                    => $tax,
            'tax_rate'               => $taxRate,
            'tax_label'              => Settings::str('tax_label') ?: 'Tax',
            'tax_enabled'            => $taxEnabled && $taxRate > 0,
            'tax_inclusive'          => Settings::bool('tax_inclusive'),
            'service_charge'         => $charge,
            'service_charge_rate'    => $chargeRate,
            'service_charge_label'   => Settings::str('service_charge_label') ?: 'Service Charge',
            'service_charge_enabled' => $chargeEnabled && $chargeRate > 0,
            'total'                  => $total,
            'currency'               => Settings::currency(),
        ];
    }

    /**
     * What the customer owes, given the price that was typed in.
     *
     * With inclusive pricing the typed figure already covers tax and charge, so
     * it is returned unchanged. With exclusive pricing they are added on top,
     * which is what makes "Enable Tax" actually increase a bill rather than
     * only decorate the invoice.
     */
    public static function grandTotal(string|int $pricedAmount): string
    {
        $amount=Decimal::value($pricedAmount);
        if (Settings::bool('tax_inclusive')) return $amount;
        $tax=Settings::bool('tax_enabled')?Settings::str('tax_rate'):'0';
        $charge=Settings::bool('service_charge_enabled')?Settings::str('service_charge_rate'):'0';
        return Decimal::add($amount,Decimal::ratio($amount,Decimal::add($tax,$charge),'100.00'));
    }

    /**
     * The breakdown as printable label/amount rows, already filtered to the
     * lines the shop has chosen to show.
     *
     * @return array<int, array{label: string, amount: float, muted?: bool}>
     */
    public static function lines(float $total): array
    {
        $b = self::breakdown($total);
        $rows = [];

        if ($b['tax_enabled'] || $b['service_charge_enabled']) {
            $rows[] = ['label' => 'Subtotal', 'amount' => $b['subtotal'], 'muted' => true];
        }

        if ($b['service_charge_enabled']) {
            $rows[] = [
                'label'  => sprintf('%s (%s%%)', $b['service_charge_label'], rtrim(rtrim(number_format($b['service_charge_rate'], 2), '0'), '.')),
                'amount' => $b['service_charge'],
                'muted'  => true,
            ];
        }

        if ($b['tax_enabled']) {
            $rows[] = [
                'label'  => sprintf(
                    '%s (%s%%)',
                    $b['tax_label'],
                    rtrim(rtrim(number_format($b['tax_rate'], 2), '0'), '.')
                ),
                'amount' => $b['tax'],
                'muted'  => true,
            ];
        }

        $rows[] = ['label' => 'Total', 'amount' => $b['total']];

        return $rows;
    }
}
