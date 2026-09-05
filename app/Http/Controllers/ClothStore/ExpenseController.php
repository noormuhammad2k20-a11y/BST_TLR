<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClothStore\Expense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::query();

        // Search the database. The page previously filtered the rendered table
        // in JavaScript, so it could only ever match the 50 rows on the
        // current page and quietly ignored everything else.
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('paid_by', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('expense_date', [$request->start_date, $request->end_date]);
        }
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $expenses = $query->orderByDesc('expense_date')->orderByDesc('id')->paginate(50);
        
        // Analytics
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        $kpis = [
            'today' => Expense::where('expense_date', $today)->sum('amount'),
            'this_month' => Expense::whereBetween('expense_date', [$startOfMonth, Carbon::now()])->sum('amount'),
            'last_month' => Expense::whereBetween('expense_date', [$startOfLastMonth, $endOfLastMonth])->sum('amount'),
            'yearly' => Expense::whereBetween('expense_date', [$startOfYear, Carbon::now()])->sum('amount'),
            'highest_category' => Expense::whereBetween('expense_date', [$startOfMonth, Carbon::now()])
                                    ->select('category', DB::raw('SUM(amount) as total'))
                                    ->groupBy('category')
                                    ->orderByDesc('total')
                                    ->first()
        ];

        // Charts Data
        // 1. Expenses by Category (This Month)
        $catData = Expense::whereBetween('expense_date', [$startOfMonth, Carbon::now()])
                    ->select('category', DB::raw('SUM(amount) as total'))
                    ->groupBy('category')
                    ->get();
                    
        // 2. Daily Trend (Last 30 Days)
        $dailyData = Expense::where('expense_date', '>=', Carbon::now()->subDays(29))
                    ->select(DB::raw('DATE(expense_date) as date'), DB::raw('SUM(amount) as total'))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                    
        $dailyLabels = [];
        $dailyTotals = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::now()->subDays($i)->format('Y-m-d');
            $dailyLabels[] = Carbon::now()->subDays($i)->format('M d');
            $row = $dailyData->firstWhere('date', $d);
            $dailyTotals[] = $row ? (float) $row->total : 0;
        }
        
        // 3. Monthly Trend (Last 12 Months)
        $monthlyData = Expense::where('expense_date', '>=', Carbon::now()->subMonths(11)->startOfMonth())
                    ->select(DB::raw('DATE_FORMAT(expense_date, "%Y-%m") as month'), DB::raw('SUM(amount) as total'))
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get();
                    
        $monthlyLabels = [];
        $monthlyTotals = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = Carbon::now()->subMonths($i)->format('Y-m');
            $monthlyLabels[] = Carbon::now()->subMonths($i)->format('M Y');
            $row = $monthlyData->firstWhere('month', $m);
            $monthlyTotals[] = $row ? (float) $row->total : 0;
        }

        $charts = [
            'cat_labels' => $catData->pluck('category'),
            'cat_data' => $catData->pluck('total'),
            'daily_labels' => $dailyLabels,
            'daily_data' => $dailyTotals,
            'monthly_labels' => $monthlyLabels,
            'monthly_data' => $monthlyTotals,
        ];

        return view('cloth-store.expenses.index', compact('expenses', 'kpis', 'charts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|string',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'paid_by' => 'nullable|string',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|string',
            'status' => 'required|string',
        ]);
        
        $data['created_by'] = auth()->user()->short_name ?? 'Admin';

        Expense::create($data);
        return response()->json(['success' => true, 'message' => 'Expense added successfully!']);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|string',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'paid_by' => 'nullable|string',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|string',
            'status' => 'required|string',
        ]);

        $expense->update($data);
        return response()->json(['success' => true, 'message' => 'Expense updated successfully!']);
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return response()->json(['success' => true, 'message' => 'Expense deleted successfully!']);
    }

    public function updateStatus(Request $request, Expense $expense)
    {
        $request->validate(['status' => 'required|in:Pending,Approved,Rejected']);
        $expense->update(['status' => $request->status]);
        return response()->json(['success' => true, 'message' => 'Status updated to ' . $request->status]);
    }
}
