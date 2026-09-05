<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\ProductService;
use App\Services\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Powers the header omnisearch. Every group is a single indexed query with a
 * hard limit, so the endpoint stays well under a few milliseconds.
 */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $groups = [];

        $orders = Order::query()
            ->with('customer:id,name')
            ->search($term)
            ->latest()
            ->limit(5)
            ->get();

        if ($orders->isNotEmpty()) {
            $groups[] = [
                'type'  => 'order',
                'label' => 'Orders',
                'items' => $orders->map(fn (Order $o) => [
                    'title'    => $o->display_number,
                    'subtitle' => trim(($o->customer?->name ?? 'Unknown') . ' · ' . $o->primary_item_name),
                    'meta'     => Money::format($o->total),
                    'url'      => route('orders.index', ['highlight' => $o->id]),
                ])->all(),
            ];
        }

        $customers = Customer::query()
            ->search($term)
            ->withCount('orders')
            ->limit(5)
            ->get();

        if ($customers->isNotEmpty()) {
            $groups[] = [
                'type'  => 'customer',
                'label' => 'Customers',
                'items' => $customers->map(fn (Customer $c) => [
                    'title'    => $c->name,
                    'subtitle' => trim($c->phone . ' · ' . $c->display_code),
                    'meta'     => $c->orders_count . ' orders',
                    'url'      => route('customers.index', ['highlight' => $c->id]),
                ])->all(),
            ];
        }

        $invoices = Order::query()
            ->with('customer:id,name')
            ->where(fn ($q) => $q
                ->where('invoice_number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")))
            ->where('balance', '>', 0)
            ->limit(4)
            ->get();

        if ($invoices->isNotEmpty()) {
            $groups[] = [
                'type'  => 'invoice',
                'label' => 'Outstanding Invoices',
                'items' => $invoices->map(fn (Order $o) => [
                    'title'    => $o->display_invoice,
                    'subtitle' => $o->customer?->name ?? 'Unknown',
                    'meta'     => Money::format($o->balance) . ' due',
                    'url'      => route('payments-billing.index', ['highlight' => $o->id]),
                ])->all(),
            ];
        }

        $services = ProductService::query()->search($term)->limit(4)->get();

        if ($services->isNotEmpty()) {
            $groups[] = [
                'type'  => 'service',
                'label' => 'Products & Services',
                'items' => $services->map(fn (ProductService $s) => [
                    'title'    => $s->name,
                    'subtitle' => $s->category,
                    'meta'     => Money::format($s->price),
                    'url'      => route('products-services.index'),
                ])->all(),
            ];
        }

        $expenses = Expense::query()->search($term)->latest('date')->limit(3)->get();

        if ($expenses->isNotEmpty()) {
            $groups[] = [
                'type'  => 'expense',
                'label' => 'Expenses',
                'items' => $expenses->map(fn (Expense $e) => [
                    'title'    => $e->description,
                    'subtitle' => $e->category . ' · ' . $e->date?->format('M d, Y'),
                    'meta'     => Money::format($e->amount),
                    'url'      => route('expenses.index'),
                ])->all(),
            ];
        }

        return response()->json(['groups' => $groups]);
    }
}
