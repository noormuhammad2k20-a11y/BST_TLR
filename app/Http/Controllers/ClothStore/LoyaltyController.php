<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClothStore\Customer;
use App\Models\ClothStore\LoyaltyTransaction;
use App\Models\ClothStore\Setting;
use Illuminate\Support\Facades\DB;

class LoyaltyController extends Controller
{
    public function index()
    {
        // Check if loyalty is enabled in settings
        $loyaltyEnabled = Setting::where('key', 'enable_loyalty_points')->value('value') == '1';

        // Get Top Customers by Points
        $topCustomers = Customer::orderByDesc('loyalty_points')->take(10)->get();

        // Get recent transactions
        $transactions = LoyaltyTransaction::with('customer')->orderByDesc('created_at')->take(50)->get();

        return view('cloth-store.loyalty.index', compact('loyaltyEnabled', 'topCustomers', 'transactions'));
    }

    public function adjust(Request $request)
    {
        $request->validate([
            'cs_customer_id' => 'required|exists:cs_customers,id',
            'points' => 'required|numeric',
            'type' => 'required|string', // Earned, Redeemed, Adjustment
            'description' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $customer = Customer::findOrFail($request->cs_customer_id);
            
            // Adjust points based on type
            if ($request->type == 'Redeemed') {
                if ($customer->loyalty_points < $request->points) {
                    throw new \Exception('Customer does not have enough points to redeem.');
                }
                $customer->loyalty_points -= $request->points;
            } else {
                $customer->loyalty_points += $request->points;
            }
            
            // Re-calculate customer level based on rules (mock example)
            if ($customer->loyalty_points > 5000) {
                $customer->customer_level = 'VIP';
            } elseif ($customer->loyalty_points > 1000) {
                $customer->customer_level = 'Regular';
            }

            $customer->save();

            LoyaltyTransaction::create([
                'cs_customer_id' => $customer->id,
                'points' => $request->points,
                'type' => $request->type,
                'description' => $request->description ?: 'Manual points adjustment'
            ]);

            DB::commit();

            // The adjust form submits over AJAX so the page is not reloaded.
            // back() stays as the no-JS fallback.
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Loyalty points adjusted successfully.',
                ]);
            }

            return back()->with('success', 'Loyalty points adjusted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }
}
