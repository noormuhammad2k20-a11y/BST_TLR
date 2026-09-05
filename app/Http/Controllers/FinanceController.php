<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class FinanceController extends Controller
{
    /**
     * There is no dedicated Finance screen in the client design — the section
     * is represented by Payments & Billing, Expenses and Reports. This route is
     * kept so existing links resolve, and simply lands on Payments & Billing.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('payments-billing.index');
    }
}
