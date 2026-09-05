<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Scope the headline figures to the signed-in user where that is
        // meaningful; admins see the whole shop.
        $ordersQuery = $user->isAdmin()
            ? Order::query()
            : Order::where(fn ($q) => $q->where('tailor_id', $user->id)->orWhere('created_by', $user->id));

        $totalOrders = (clone $ordersQuery)->count();
        $delivered   = (clone $ordersQuery)->whereIn('status', ['Delivered', 'Completed'])->count();

        $onTime = (clone $ordersQuery)
            ->whereIn('status', ['Delivered', 'Completed'])
            ->where(function ($q) {
                $q->whereNull('delivery_date')
                  ->orWhereNull('delivered_at')
                  ->orWhereColumn('delivered_at', '<=', 'delivery_date');
            })
            ->count();

        $stats = [
            'customers' => $user->isAdmin()
                ? Customer::count()
                : (clone $ordersQuery)->distinct('customer_id')->count('customer_id'),
            'orders'    => $totalOrders,
            'rating'    => $delivered > 0 ? round(($onTime / $delivered) * 5, 1) : 0.0,
        ];

        return view('profile.index', compact('user', 'stats'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:100'],
            'email'        => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'        => ['nullable', 'string', 'max:50'],
        ]);

        $user->update($validated);

        ActivityLogger::updated($user, sprintf('%s updated their profile', $user->name), 'auth');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Profile updated successfully.']);
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        $user->update(['password' => $request->input('password')]);

        ActivityLogger::log(
            'Password changed',
            sprintf('%s changed their password', $user->name),
            'auth',
            $user,
            [],
            'password_changed'
        );

        return response()->json(['success' => true, 'message' => 'Password updated successfully.']);
    }
}
