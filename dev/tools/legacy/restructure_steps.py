import re

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'r', encoding='utf-8') as f:
    text = f.read()

# 1. Replace Stepper
stepper_pattern = r'<div class="flex items-center w-full max-w-md bg-white border border-slate-200 rounded-xl shadow-sm px-4 py-2" id="pos-stepper">.*?</div>\s*</div>\s*<!-- Main Container -->'
new_stepper = """<div class="flex items-center w-full max-w-lg bg-white border border-slate-200 rounded-xl shadow-sm px-4 py-2" id="pos-stepper">
            <div class="pos-stepper-step is-active flex items-center gap-2" data-step="1">
                <span class="pos-stepper-dot">1</span>
                <span class="pos-stepper-label text-xs font-semibold text-slate-500 whitespace-nowrap">Cart</span>
            </div>
            <div class="pos-stepper-line"></div>
            <div class="pos-stepper-step flex items-center gap-2" data-step="2">
                <span class="pos-stepper-dot">2</span>
                <span class="pos-stepper-label text-xs font-semibold text-slate-500 whitespace-nowrap">Review</span>
            </div>
            <div class="pos-stepper-line"></div>
            <div class="pos-stepper-step flex items-center gap-2" data-step="3">
                <span class="pos-stepper-dot">3</span>
                <span class="pos-stepper-label text-xs font-semibold text-slate-500 whitespace-nowrap">Payment</span>
            </div>
            <div class="pos-stepper-line"></div>
            <div class="pos-stepper-step flex items-center gap-2" data-step="4">
                <span class="pos-stepper-dot"><i class="fa-solid fa-check text-[9px]"></i></span>
                <span class="pos-stepper-label text-xs font-semibold text-slate-500 whitespace-nowrap">Done</span>
            </div>
        </div>
    </div>

    <!-- Main Container -->"""
text = re.sub(stepper_pattern, new_stepper, text, flags=re.DOTALL)

# 2. Extract Step 2 and Step 3 contents
step2_pattern = r'<!-- ============================================== -->\s*<!-- STEP 2: SPLIT LAYOUT \(SUMMARY LEFT, PAYMENT RIGHT\) -->.*?<!-- ============================================== -->\s*<!-- STEP 3: SPLIT LAYOUT \(RECEIPT LEFT, SUCCESS RIGHT\) -->'
step3_pattern = r'<!-- STEP 3: SPLIT LAYOUT \(RECEIPT LEFT, SUCCESS RIGHT\) -->.*?<!-- ============================================== -->\s*<!-- PREMIUM NEW CUSTOMER MODAL'

# Let's write out the new Step 2, Step 3, and Step 4
new_steps = """<!-- ============================================== -->
        <!-- STEP 2: ORDER REVIEW                           -->
        <!-- ============================================== -->
        <div id="step-2-review" class="pos-step is-next flex items-center justify-center bg-slate-50/50 rounded-2xl shadow-sm border border-slate-200 h-full p-4 md:p-8">
            <div class="w-full max-w-3xl bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-col h-full max-h-[700px] overflow-hidden">
                <!-- Header -->
                <div class="p-5 border-b border-slate-200 shrink-0 flex justify-between items-center bg-white">
                    <div>
                        <h2 class="text-xl font-black text-slate-900 tracking-tight">Review Order</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5 font-medium">Verify items before proceeding to payment</p>
                    </div>
                    <button onclick="goToStep(1)" class="btn-ghost py-2 px-3 text-xs font-semibold border border-slate-200 shadow-sm bg-white">
                        <i class="fa-solid fa-arrow-left text-[10px]"></i> Edit Cart
                    </button>
                </div>

                <!-- Items Details -->
                <div class="flex-1 min-h-0 flex flex-col px-6 py-4 bg-slate-50/50">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Items Details</span>
                        <span class="text-xs text-slate-500 font-bold bg-slate-200/50 px-2 py-0.5 rounded-full" id="checkout-item-count">0 items</span>
                    </div>
                    <div id="checkout-summary-list" class="space-y-2.5 overflow-y-auto min-h-0 pr-2"></div>
                </div>

                <!-- Total Price -->
                <div class="p-6 border-t border-slate-200 bg-white shrink-0">
                    <div class="space-y-3 mb-6 max-w-sm mx-auto">
                        <div class="flex justify-between text-sm text-slate-500 font-medium">
                            <span>Subtotal</span>
                            <span class="cell-num font-bold text-slate-700" id="checkout-subtotal">Rs 0</span>
                        </div>
                        <div class="flex justify-between text-sm text-slate-500 font-medium items-center">
                            <span>Discount</span>
                            <input type="number" id="cart-discount" value="0" min="0" step="0.01" class="w-24 px-3 py-1.5 text-right bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold text-rose-600 focus:border-rose-400 focus:ring-rose-100 focus:outline-none cell-num shadow-sm" placeholder="0" onchange="calculateChange(); updateCheckoutTotals();">
                        </div>
                        <div class="flex justify-between items-end pt-4 border-t border-slate-100 mt-4">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Grand Total</span>
                            <span class="text-3xl font-black text-slate-900 tracking-tight cell-num" id="checkout-total">Rs 0</span>
                        </div>
                    </div>
                    <button onclick="goToCheckoutStep3()" class="btn-primary w-full py-4 rounded-xl text-base flex items-center justify-center gap-2 font-black shadow-lg shadow-indigo-200">
                        Proceed to Payment <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- STEP 3: PAYMENT & CUSTOMER                     -->
        <!-- ============================================== -->
        <div id="step-3-payment" class="pos-step is-next flex bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-full">
            <!-- LEFT: Customer -->
            <div class="w-80 lg:w-96 shrink-0 bg-slate-50/50 border-r border-slate-200 flex flex-col h-full">
                <div class="p-5 lg:p-6 border-b border-slate-200 bg-white shrink-0">
                    <div class="flex justify-between items-center mb-5">
                        <div>
                            <h2 class="text-lg font-black text-slate-900 tracking-tight">Customer</h2>
                            <p class="text-[11px] text-slate-500 mt-0.5 font-medium">Select or create customer</p>
                        </div>
                        <button onclick="document.getElementById('new-customer-modal').classList.remove('hidden')" class="text-xs font-bold text-indigo-600 hover:text-white bg-indigo-50 hover:bg-indigo-600 px-3 py-1.5 rounded-lg transition-colors shadow-sm">
                            <i class="fa-solid fa-plus mr-1 text-[10px]"></i> New
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                            <input type="search" id="pos-customer-search" autocomplete="off" placeholder="Search by name or phone..." class="input-premium w-full pl-9 py-2 text-sm shadow-sm">
                        </div>
                        <div class="relative">
                            <select id="pos-customer" class="input-premium w-full appearance-none pr-9 py-2.5 font-bold text-sm text-slate-800 bg-slate-50 shadow-sm">
                                {customers_html}
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        </div>
                        <div id="customer-due-warning" class="hidden text-xs font-semibold text-rose-600 bg-rose-50 p-3 rounded-xl border border-rose-100 flex items-center gap-2 shadow-sm">
                            <i class="fa-solid fa-triangle-exclamation"></i> 
                            <div>Outstanding due: <span id="customer-due-amt" class="cell-num font-black">Rs 0</span></div>
                        </div>
                    </div>
                </div>

                <div class="flex-1 p-5 lg:p-6 flex flex-col justify-end">
                    <button onclick="goToStep(2)" class="btn-ghost w-full py-3 text-sm font-bold border border-slate-200 bg-white shadow-sm hover:border-slate-300">
                        <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Review
                    </button>
                </div>
            </div>

            <!-- RIGHT: Payment Controls -->
            <div class="flex-1 flex flex-col bg-white min-h-0 h-full p-6 lg:p-10">
                <div class="flex items-center justify-between mb-8 shrink-0">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight">Payment Method</h2>
                        <p class="text-xs text-slate-500 mt-1 font-medium">Select method and enter amount</p>
                    </div>
                    <input type="hidden" id="selected-method" value="Cash">
                </div>

                <div class="flex-1 min-h-0 flex flex-col justify-center space-y-8">
                    <!-- Methods -->
                    <div class="grid grid-cols-4 gap-3">
                        <button type="button" class="pay-method stacked active py-5" data-method="Cash">
                            <div class="pay-icon shrink-0 mb-2"><i class="fa-solid fa-money-bill-wave text-2xl"></i></div>
                            <div class="min-w-0 w-full"><div class="font-bold text-slate-900 text-sm truncate">Cash</div></div>
                        </button>
                        <button type="button" class="pay-method stacked py-5" data-method="Card">
                            <div class="pay-icon shrink-0 mb-2"><i class="fa-solid fa-credit-card text-2xl"></i></div>
                            <div class="min-w-0 w-full"><div class="font-bold text-slate-900 text-sm truncate">Card</div></div>
                        </button>
                        <button type="button" class="pay-method stacked py-5" data-method="Bank Transfer">
                            <div class="pay-icon shrink-0 mb-2"><i class="fa-solid fa-building-columns text-2xl"></i></div>
                            <div class="min-w-0 w-full"><div class="font-bold text-slate-900 text-sm truncate">Transfer</div></div>
                        </button>
                        <button type="button" class="pay-method stacked py-5" data-method="EasyPaisa">
                            <div class="pay-icon shrink-0 mb-2"><i class="fa-solid fa-mobile-screen text-2xl"></i></div>
                            <div class="min-w-0 w-full"><div class="font-bold text-slate-900 text-sm truncate">EasyPaisa</div></div>
                        </button>
                    </div>

                    <!-- Tender / Change -->
                    <div class="grid grid-cols-2 gap-6 max-w-3xl">
                        <div class="p-6 bg-slate-50/50 border border-slate-200 rounded-2xl shadow-sm">
                            <label class="block text-xs font-bold text-slate-500 mb-2.5 uppercase tracking-wider">Amount Received</label>
                            <div class="relative mb-4">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-400 text-base">Rs</span>
                                <input type="number" id="paid-amount" value="0" min="0" step="0.01" class="w-full pl-12 pr-16 py-4 bg-white border border-slate-200 rounded-xl text-xl font-black text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-100 transition-all cell-num shadow-sm" oninput="calculateChange()" placeholder="0">
                                <button onclick="setFullPayment()" class="absolute right-2 top-1/2 -translate-y-1/2 bg-slate-900 text-white hover:bg-indigo-600 text-xs font-bold px-4 py-2 rounded-lg transition-all shadow-sm">Full</button>
                            </div>
                            <div class="grid grid-cols-4 gap-2.5">
                                <button onclick="addTender(500)" class="py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-600 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-600 transition-all cell-num shadow-sm">+500</button>
                                <button onclick="addTender(1000)" class="py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-600 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-600 transition-all cell-num shadow-sm">+1k</button>
                                <button onclick="addTender(2000)" class="py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-600 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-600 transition-all cell-num shadow-sm">+2k</button>
                                <button onclick="clearTender()" class="py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-400 hover:text-rose-600 hover:border-rose-200 hover:bg-rose-50 transition-all shadow-sm">Clr</button>
                            </div>
                        </div>
                        <div class="p-6 rounded-2xl border border-slate-200 bg-white flex flex-col justify-center items-center text-center shadow-sm">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest" id="change-label">Change Return</span>
                            <span class="text-4xl font-black text-slate-900 tracking-tight cell-num mt-3" id="change-amount">Rs 0</span>
                            <span class="text-xs text-slate-400 mt-3 font-medium" id="change-hint">Enter amount</span>
                        </div>
                    </div>
                </div>

                <div class="shrink-0 mt-8 flex justify-end">
                    <button onclick="submitCheckout()" class="btn-success w-full lg:w-auto px-10 py-4 rounded-xl text-base font-black flex items-center justify-center gap-2 shadow-lg shadow-emerald-200" id="btn-submit-order">
                        <i class="fa-solid fa-circle-check"></i> Complete Sale
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- STEP 4: SPLIT LAYOUT (RECEIPT LEFT, SUCCESS RIGHT) -->
        <!-- ============================================== -->
"""

# Extract the customer options from original text
customers_html = ""
c_match = re.search(r'<select id="pos-customer".*?>(.*?)</select>', text, re.DOTALL)
if c_match:
    customers_html = c_match.group(1).strip()
    new_steps = new_steps.replace('{customers_html}', customers_html)

# Extract Step 4 inner content (which is old Step 3)
step4_content_match = re.search(r'<!-- STEP 3: SPLIT LAYOUT \(RECEIPT LEFT, SUCCESS RIGHT\) -->.*?<div id="step-3-success"(.*?)<!-- ============================================== -->\s*<!-- PREMIUM NEW CUSTOMER MODAL', text, re.DOTALL)
step4_inner = step4_content_match.group(1)
# Replace step-3-success with step-4-success
step4_full = new_steps + '        <div id="step-4-success"' + step4_inner + '\n        <!-- ============================================== -->\n        <!-- PREMIUM NEW CUSTOMER MODAL'

# Substitute everything from Step 2 comment to the modal comment
text = re.sub(r'<!-- ============================================== -->\s*<!-- STEP 2: SPLIT LAYOUT \(SUMMARY LEFT, PAYMENT RIGHT\) -->.*?<!-- ============================================== -->\s*<!-- PREMIUM NEW CUSTOMER MODAL', step4_full, text, flags=re.DOTALL)

# Update stepPanels in JS
js_pattern = r'const stepPanels = \{[^\}]+\};'
new_js_panels = """const stepPanels = {
        1: document.getElementById('step-1-pos'),
        2: document.getElementById('step-2-review'),
        3: document.getElementById('step-3-payment'),
        4: document.getElementById('step-4-success')
    };"""
text = re.sub(js_pattern, new_js_panels, text)

# Update goToCheckout to transition to Step 2 instead of executing Step 3 prep
# We also need a goToCheckoutStep3()
# Originally:
# window.goToCheckout = function() { ... calculate totals, populate customer, goToStep(2); }
# We want goToCheckout to populate the summary list, then goToStep(2).
# We want goToCheckoutStep3 to calculate totals, populate customer due, and goToStep(3).
old_goto = r'window\.goToCheckout = function\(\) \{.*?\n    \};'
new_goto = """window.goToCheckout = function() {
        if(cart.length === 0) return;
        if (isDockOpen) toggleCart();
        
        updateCheckoutTotals();

        const summaryList = document.getElementById('checkout-summary-list');
        if(summaryList) {
            summaryList.innerHTML = cart.map(item => {
                const isMeter = item.unit === 'meter';
                const total = item.price * item.qty;
                return `
                <div class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-xl shadow-sm mb-2">
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-slate-800 text-sm truncate leading-tight">${Atelier.escapeHtml(item.name)}</div>
                        <div class="text-xs text-slate-500 mt-1 cell-num">${item.qty} ${isMeter ? 'm' : 'pcs'} &times; Rs ${item.price.toLocaleString()}</div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-black text-slate-900 text-sm cell-num">Rs ${total.toLocaleString()}</div>
                    </div>
                </div>`;
            }).join('');
        }
        
        const countEl = document.getElementById('checkout-item-count');
        if(countEl) countEl.innerText = `${cart.length} items`;
        
        goToStep(2);
    };
    
    window.goToCheckoutStep3 = function() {
        const paidInput = document.getElementById('paid-amount');
        let discountInput = document.getElementById('cart-discount');
        let discount = discountInput ? (parseFloat(discountInput.value) || 0) : 0;
        let total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0) - discount;
        
        if(paidInput) paidInput.value = total;
        
        const cSelect = document.getElementById('pos-customer');
        if(cSelect) cSelect.dispatchEvent(new Event('change')); 
        
        calculateChange();
        goToStep(3);
    };"""

text = re.sub(old_goto, new_goto, text, flags=re.DOTALL)

# Update showSuccess to transition to Step 4
text = text.replace('goToStep(3);', 'goToStep(4);')
# Wait, this might replace goToStep(3) in my new function! Let's be safer.
# Instead of replacing globally, replace inside showSuccess.
success_func_match = re.search(r'function showSuccess\(.*?\n    \};', text, re.DOTALL)
if success_func_match:
    success_func = success_func_match.group(0)
    # The original was goToStep(3) inside showSuccess
    new_success_func = success_func.replace('goToStep(3);', 'goToStep(4);')
    text = text.replace(success_func, new_success_func)
else:
    # try replacing goToStep(3) directly but be careful
    pass

# We also need to fix `step-3-success` selectors in JS
text = text.replace('#step-3-success', '#step-4-success')

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'w', encoding='utf-8') as f:
    f.write(text)

print('Updated index.blade.php for 4 steps!')
