<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClothStore\Discount;
use App\Models\ClothStore\Product;
use App\Models\ClothStore\Category;
use App\Models\ClothStore\Customer;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $all = Discount::orderByDesc('created_at')->get();
        
        $activeCount = 0;
        $scheduledCount = 0;
        $expiredCount = 0;
        $disabledCount = 0;

        foreach ($all as $d) {
            $status = $d->calculated_status;
            if ($status === 'Active') $activeCount++;
            elseif ($status === 'Scheduled') $scheduledCount++;
            elseif ($status === 'Expired') $expiredCount++;
            elseif ($status === 'Disabled') $disabledCount++;
        }

        $query = Discount::orderByDesc('created_at');
        
        if ($request->type) {
            $query->where('type', $request->type);
        }

        // Offer name or coupon code, queried on the server rather than by
        // hiding rows that happen to be rendered.
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $discounts = $query->paginate(20)->withQueryString();
        
        $products = Product::where('status', 'Active')->get(['id', 'name']);
        $categories = Category::get(['id', 'name']);
        $customers = Customer::get(['id', 'name']);

        return view('cloth-store.discounts.index', compact(
            'discounts', 'activeCount', 'scheduledCount', 'expiredCount', 'disabledCount',
            'products', 'categories', 'customers'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:cs_discounts',
            'type' => 'required|string',
            'value' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'customer_limit' => 'nullable|integer|min:1',
            'applicable_products' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'applicable_customers' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $data['is_active'] = $request->has('is_active');
        $data['min_purchase'] = $data['min_purchase'] ?? 0;
        $data['applicable_products'] = array_filter($data['applicable_products'] ?? []);
        $data['applicable_categories'] = array_filter($data['applicable_categories'] ?? []);
        $data['applicable_customers'] = array_filter($data['applicable_customers'] ?? []);

        Discount::create($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Discount offer created successfully.']);
        }

        return back()->with('success', 'Discount offer created successfully.');
    }

    public function update(Request $request, $id)
    {
        $discount = Discount::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:cs_discounts,code,'.$id,
            'type' => 'required|string',
            'value' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'customer_limit' => 'nullable|integer|min:1',
            'applicable_products' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'applicable_customers' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $data['is_active'] = $request->has('is_active');
        $data['min_purchase'] = $data['min_purchase'] ?? 0;
        $data['applicable_products'] = array_filter($data['applicable_products'] ?? []);
        $data['applicable_categories'] = array_filter($data['applicable_categories'] ?? []);
        $data['applicable_customers'] = array_filter($data['applicable_customers'] ?? []);

        $discount->update($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Discount offer updated successfully.']);
        }

        return back()->with('success', 'Discount offer updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        Discount::findOrFail($id)->delete();

        // The offers list deletes over AJAX so the page is not reloaded. The
        // back() redirect is kept as the no-JS fallback for the plain form.
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Discount offer deleted.']);
        }

        return back()->with('success', 'Discount offer deleted.');
    }

    public function toggleStatus(Request $request, $id)
    {
        $discount = Discount::findOrFail($id);
        $discount->is_active = !$discount->is_active;
        $discount->save();
        return response()->json(['success' => true, 'is_active' => $discount->is_active]);
    }
}
