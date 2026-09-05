import re

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Extract the customer options from original text
customers_html = ""
c_match = re.search(r'<select id="pos-customer".*?>(.*?)</select>', text, re.DOTALL)
if c_match:
    customers_html = c_match.group(1).strip()
else:
    # Fallback to blade template logic
    customers_html = """@foreach($customers as $c)
                                <option value="{{ $c->id }}" data-due="{{ $c->due_balance }}" @selected(in_array(strtolower($c->name), ['walk-in', 'walkin'], true))>{{ $c->name }}{{ $c->phone ? ' (' . $c->phone . ')' : '' }}</option>
                                @endforeach"""

new_step2 = """<!-- ============================================== -->
        <!-- STEP 2: ORDER REVIEW                           -->
        <!-- ============================================== -->
        <div id="step-2-review" class="pos-step is-next flex items-start justify-center p-4 md:p-8 overflow-y-auto w-full h-full">
            <div class="w-full max-w-4xl bg-white rounded-2xl shadow-sm border border-slate-200 mt-4 md:mt-8 flex flex-col md:flex-row overflow-hidden shrink-0">
                <!-- Left: Items Details -->
                <div class="flex-1 border-b md:border-b-0 md:border-r border-slate-200 bg-slate-50/50 flex flex-col">
                    <!-- Header -->
                    <div class="px-6 py-5 border-b border-slate-200 bg-white flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-black text-slate-900 tracking-tight">Review Order</h2>
                            <p class="text-[11px] text-slate-500 mt-0.5 font-medium">Verify items before payment</p>
                        </div>
                        <span class="text-[11px] text-slate-500 font-bold bg-slate-100 px-2 py-1 rounded-md" id="checkout-item-count">0 items</span>
                    </div>

                    <!-- Items List (Scrollable inner) -->
                    <div id="checkout-summary-list" class="p-5 space-y-2 overflow-y-auto max-h-[450px]"></div>
                </div>

                <!-- Right: Total Price -->
                <div class="w-full md:w-80 shrink-0 bg-white p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between text-sm text-slate-500 font-medium mb-3">
                            <span>Subtotal</span>
                            <span class="cell-num font-bold text-slate-700" id="checkout-subtotal">Rs 0</span>
                        </div>
                        <div class="flex justify-between text-sm text-slate-500 font-medium items-center mb-6">
                            <span>Discount</span>
                            <input type="number" id="cart-discount" value="0" min="0" step="0.01" class="w-24 px-3 py-1.5 text-right bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold text-rose-600 focus:border-rose-400 focus:ring-rose-100 focus:outline-none cell-num shadow-sm transition-all" placeholder="0" onchange="calculateChange(); updateCheckoutTotals();">
                        </div>
                        <div class="pt-4 border-t border-slate-100 mb-8 flex flex-col items-end">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Grand Total</div>
                            <div class="text-3xl font-black text-slate-900 tracking-tight cell-num text-right" id="checkout-total">Rs 0</div>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <button onclick="goToCheckoutStep3()" class="btn-primary w-full py-3.5 rounded-xl text-sm flex items-center justify-center gap-2 font-black shadow-lg shadow-indigo-200/50">
                            Proceed to Payment <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                        <button onclick="goToStep(1)" class="btn-ghost w-full py-2.5 text-xs font-bold border border-slate-200 bg-white shadow-sm hover:border-slate-300">
                            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>"""

new_step3 = """<!-- ============================================== -->
        <!-- STEP 3: PAYMENT & CUSTOMER                     -->
        <!-- ============================================== -->
        <div id="step-3-payment" class="pos-step is-next flex items-start justify-center p-4 md:p-8 overflow-y-auto w-full h-full">
            <div class="w-full max-w-5xl bg-white rounded-2xl shadow-sm border border-slate-200 mt-4 md:mt-8 flex flex-col md:flex-row overflow-hidden shrink-0">
                <!-- LEFT: Customer -->
                <div class="w-full md:w-[320px] shrink-0 bg-slate-50/50 border-b md:border-b-0 md:border-r border-slate-200 flex flex-col">
                    <div class="p-5 border-b border-slate-200 bg-white">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h2 class="text-base font-black text-slate-900 tracking-tight">Customer</h2>
                                <p class="text-[10px] text-slate-500 font-medium">Select or create</p>
                            </div>
                            <button onclick="document.getElementById('new-customer-modal').classList.remove('hidden')" class="text-[10px] font-bold text-indigo-600 hover:text-white bg-indigo-50 hover:bg-indigo-600 px-2.5 py-1.5 rounded-lg transition-colors shadow-sm">
                                <i class="fa-solid fa-plus"></i> New
                            </button>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                                <input type="search" id="pos-customer-search" autocomplete="off" placeholder="Search customer..." class="input-premium w-full pl-8 py-2 text-xs shadow-sm">
                            </div>
                            <div class="relative">
                                <select id="pos-customer" class="input-premium w-full appearance-none pr-8 py-2.5 font-bold text-xs text-slate-800 bg-slate-50 shadow-sm">
                                    {customers_html}
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
                            </div>
                            <div id="customer-due-warning" class="hidden text-[11px] font-semibold text-rose-600 bg-rose-50 p-2.5 rounded-lg border border-rose-100 flex items-center justify-between shadow-sm">
                                <div class="flex items-center gap-1.5"><i class="fa-solid fa-triangle-exclamation"></i> Due:</div>
                                <span id="customer-due-amt" class="cell-num font-black">Rs 0</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-end">
                        <button onclick="goToStep(2)" class="btn-ghost w-full py-2.5 text-xs font-bold border border-slate-200 bg-white shadow-sm hover:border-slate-300">
                            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Review
                        </button>
                    </div>
                </div>

                <!-- RIGHT: Payment Controls -->
                <div class="flex-1 p-6 md:p-8 bg-white flex flex-col">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-black text-slate-900 tracking-tight">Payment Method</h2>
                            <p class="text-[11px] text-slate-500 font-medium">Select method and tender amount</p>
                        </div>
                        <input type="hidden" id="selected-method" value="Cash">
                    </div>

                    <div class="space-y-6">
                        <!-- Methods Compact -->
                        <div class="grid grid-cols-4 gap-3">
                            <button type="button" class="pay-method stacked active py-3 rounded-xl" data-method="Cash">
                                <div class="pay-icon shrink-0 mb-1.5"><i class="fa-solid fa-money-bill-wave text-lg"></i></div>
                                <div class="font-bold text-slate-900 text-xs">Cash</div>
                            </button>
                            <button type="button" class="pay-method stacked py-3 rounded-xl" data-method="Card">
                                <div class="pay-icon shrink-0 mb-1.5"><i class="fa-solid fa-credit-card text-lg"></i></div>
                                <div class="font-bold text-slate-900 text-xs">Card</div>
                            </button>
                            <button type="button" class="pay-method stacked py-3 rounded-xl" data-method="Bank Transfer">
                                <div class="pay-icon shrink-0 mb-1.5"><i class="fa-solid fa-building-columns text-lg"></i></div>
                                <div class="font-bold text-slate-900 text-xs">Transfer</div>
                            </button>
                            <button type="button" class="pay-method stacked py-3 rounded-xl" data-method="EasyPaisa">
                                <div class="pay-icon shrink-0 mb-1.5"><i class="fa-solid fa-mobile-screen text-lg"></i></div>
                                <div class="font-bold text-slate-900 text-xs">EasyPaisa</div>
                            </button>
                        </div>

                        <!-- Tender / Change (Compact Two Column) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-5 bg-slate-50/50 border border-slate-200 rounded-xl shadow-sm">
                                <label class="block text-[10px] font-bold text-slate-500 mb-2 uppercase tracking-wider">Amount Received</label>
                                <div class="relative mb-3">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-slate-400 text-xs">Rs</span>
                                    <input type="number" id="paid-amount" value="0" min="0" step="0.01" class="w-full pl-8 pr-12 py-2.5 bg-white border border-slate-200 rounded-lg text-lg font-black text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all cell-num shadow-sm" oninput="calculateChange()" placeholder="0">
                                    <button onclick="setFullPayment()" class="absolute right-1.5 top-1/2 -translate-y-1/2 bg-slate-900 text-white hover:bg-indigo-600 text-[10px] font-bold px-2.5 py-1.5 rounded-md transition-all shadow-sm">Full</button>
                                </div>
                                <div class="grid grid-cols-4 gap-2">
                                    <button onclick="addTender(500)" class="py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:border-indigo-200 transition-all cell-num shadow-sm">+500</button>
                                    <button onclick="addTender(1000)" class="py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:border-indigo-200 transition-all cell-num shadow-sm">+1k</button>
                                    <button onclick="addTender(2000)" class="py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:border-indigo-200 transition-all cell-num shadow-sm">+2k</button>
                                    <button onclick="clearTender()" class="py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all shadow-sm">Clr</button>
                                </div>
                            </div>
                            <div class="p-5 rounded-xl border border-slate-200 bg-white flex flex-col justify-center items-center text-center shadow-sm">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest" id="change-label">Change Return</span>
                                <span class="text-3xl font-black text-slate-900 tracking-tight cell-num mt-2" id="change-amount">Rs 0</span>
                                <span class="text-[10px] text-slate-400 mt-2 font-medium" id="change-hint">Enter amount</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <button onclick="submitCheckout()" class="btn-success w-full md:w-auto px-8 py-3 rounded-xl text-sm font-black flex items-center justify-center gap-2 shadow-lg shadow-emerald-200/50" id="btn-submit-order">
                            <i class="fa-solid fa-circle-check text-xs"></i> Complete Sale
                        </button>
                    </div>
                </div>
            </div>
        </div>"""

new_step3 = new_step3.replace('{customers_html}', customers_html)

# Replace step 2
text = re.sub(r'<!-- ============================================== -->\s*<!-- STEP 2: ORDER REVIEW.*?<!-- ============================================== -->\s*<!-- STEP 3:', new_step2 + '\n\n        <!-- ============================================== -->\n        <!-- STEP 3:', text, flags=re.DOTALL)

# Replace step 3
text = re.sub(r'<!-- ============================================== -->\s*<!-- STEP 3: PAYMENT & CUSTOMER.*?<!-- ============================================== -->\s*<!-- STEP 4:', new_step3 + '\n\n        <!-- ============================================== -->\n        <!-- STEP 4:', text, flags=re.DOTALL)

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'w', encoding='utf-8') as f:
    f.write(text)

print('Updated index.blade.php layouts to content-sized containers!')
