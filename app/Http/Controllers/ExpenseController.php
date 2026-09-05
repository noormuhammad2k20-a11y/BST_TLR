<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\ActivityLogger;
use App\Services\Money;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::query()
            ->latest('date')
            ->get()
            ->map(fn (Expense $e) => $this->serialize($e));

        $stats      = $this->stats();
        $categories = Expense::CATEGORIES;
        $methods    = Expense::METHODS;
        $cashflow   = StatsService::cashflow(6);

        // Income vs expenses for the current month, for the three stat cards.
        $income  = (float) \App\Models\Payment::where('date', '>=', now()->startOfMonth())->sum('amount');
        $outflow = $stats['this_month'];

        $summary = [
            'income'      => $income,
            'expenses'    => $outflow,
            'profit'      => $income - $outflow,
            'expense_pct' => $income > 0 ? round($outflow / $income * 100, 1) : 0.0,
            'margin_pct'  => $income > 0 ? round(($income - $outflow) / $income * 100, 1) : 0.0,
        ];

        return view('expenses.index', compact(
            'expenses', 'stats', 'categories', 'methods', 'cashflow', 'summary'
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $validated['created_by'] = Auth::id();

        $expense = Expense::create($validated);

        ActivityLogger::created(
            $expense,
            sprintf('%s logged under %s (%s)', $expense->description, $expense->category, Money::format($expense->amount)),
            'payments'
        );

        $this->flush();

        return response()->json([
            'success' => true,
            'message' => 'Expense recorded successfully.',
            'expense' => $this->serialize($expense),
            'stats'   => $this->stats(),
        ], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json(['success' => true, 'expense' => $this->serialize($expense)]);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $validated = $this->validated($request);

        $expense->update($validated);

        ActivityLogger::updated($expense, sprintf('%s expense updated', $expense->description), 'payments');

        $this->flush();

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully.',
            'expense' => $this->serialize($expense),
            'stats'   => $this->stats(),
        ]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $label  = $expense->description;
        $amount = (float) $expense->amount;

        $expense->delete();

        ActivityLogger::log(
            'Deleted Expense',
            sprintf('%s (%s) removed', $label, Money::format($amount)),
            'payments',
            null,
            [],
            'deleted'
        );

        $this->flush();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully.',
            'stats'   => $this->stats(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function validated(Request $request): array
    {
        return $request->validate([
            'description'    => ['required', 'string', 'max:255'],
            'amount'         => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'category'       => ['required', Rule::in(Expense::CATEGORIES)],
            'payment_method' => ['required', Rule::in(Expense::METHODS)],
            'vendor'         => ['nullable', 'string', 'max:255'],
            'reference'      => ['nullable', 'string', 'max:100'],
            'date'           => ['required', 'date', 'before_or_equal:today'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ], [
            'date.before_or_equal' => 'An expense cannot be dated in the future.',
            'amount.min'           => 'The amount must be greater than zero.',
        ]);
    }

    private function serialize(Expense $e): array
    {
        return [
            'db_id'       => $e->id,
            'id'          => 'exp-' . $e->id,
            'date'        => $e->date?->format('M d') ?? $e->created_at->format('M d'),
            'fullDate'    => ($e->date ?? $e->created_at)->format('Y-m-d'),
            'category'    => $e->category,
            'description' => $e->description,
            'amount'      => (float) $e->amount,
            'method'      => $e->payment_method ?: 'Cash',
            'vendor'      => $e->vendor ?? '',
            'reference'   => $e->reference ?? '',
            'notes'       => $e->notes ?? '',
        ];
    }

    /**
     * Month-over-month expense figures plus a category breakdown.
     */
    private function stats(): array
    {
        return Cache::remember('expenses.stats', 60, function () {
            $monthStart = now()->startOfMonth();
            $prevStart  = now()->subMonth()->startOfMonth();
            $prevEnd    = now()->subMonth()->endOfMonth();

            $thisMonth = (float) Expense::where('date', '>=', $monthStart)->sum('amount');
            $lastMonth = (float) Expense::whereBetween('date', [$prevStart, $prevEnd])->sum('amount');

            $byCategory = Expense::query()
                ->where('date', '>=', $monthStart)
                ->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get();

            $topCategory = $byCategory->first();

            return [
                'total'        => (float) Expense::sum('amount'),
                'this_month'   => $thisMonth,
                'last_month'   => $lastMonth,
                'delta'        => StatsService::delta($thisMonth, $lastMonth),
                'count'        => Expense::count(),
                'avg'          => Expense::count() > 0 ? round((float) Expense::avg('amount')) : 0,
                'top_category' => $topCategory?->category ?? '—',
                'top_amount'   => (float) ($topCategory?->total ?? 0),
                'by_category'  => $byCategory->map(fn ($r) => [
                    'label' => $r->category,
                    'value' => (float) $r->total,
                ])->all(),
            ];
        });
    }

    private function flush(): void
    {
        Cache::forget('expenses.stats');
        StatsService::flush();
    }
}
