@extends('cloth-store.layouts.app')
@section('title', 'Smart Checkout')
@section('spaPage', 'cloth-store-checkout')

@section('content')
{{--
  Smart Checkout — five separate workspaces, one shown at a time.

  Store → Cart → Review → Payment → Done → (Receipt overlay)

  The previous single-page layout with a permanent right cart panel is gone.
  Each step now owns the full width beside the sidebar and never appears
  alongside another step.
--}}
<div class="pos-page">

    {{-- ---------- TOP BAR: title · stepper · cart button ---------- --}}
    <header class="pos-top">
        <div class="pos-top-title">
            <h1>Smart Checkout</h1>
            <p>Tap products to add · Scan barcode to quick-add</p>
        </div>

        {{-- Stepper labels for the 5 visible stages. Each carries the panel
             number it corresponds to; the "Cart" label spans panels 1 (Store)
             and 2 (Cart) because both belong to the cart-building stage. The
             stepper's active/done state is derived from the current panel via
             `data-step` and a "largest ≤ current" mapping in JS. --}}
        <nav class="pos-stepper" id="pos-stepper" aria-label="Checkout progress">
            @foreach([
                [1, 1, 'Cart',     'fa-basket-shopping'],
                [2, 3, 'Review',   'fa-clipboard-check'],
                [3, 4, 'Customer', 'fa-user'],
                [4, 5, 'Payment',  'fa-credit-card'],
                [5, 6, 'Done',     'fa-check'],
            ] as $s)
                <div class="pos-step-item {{ $loop->first ? 'is-active' : '' }}"
                     data-step="{{ $s[1] }}"
                     data-order="{{ $s[0] }}">
                    <span class="pos-step-dot">{{ $s[0] < 5 ? $s[0] : '' }}<i class="fa-solid {{ $s[3] }}"></i></span>
                    <span class="pos-step-label">{{ $s[2] }}</span>
                </div>
                @if(!$loop->last)<div class="pos-step-line"></div>@endif
            @endforeach
        </nav>

        <button class="pos-cart-btn" onclick="goToStep(2)" id="pos-cart-btn" aria-label="Open cart">
            <i class="fa-solid fa-basket-shopping"></i>
            <span>Cart</span>
            <span class="pos-cart-btn-count" id="pos-cart-count">0</span>
        </button>
    </header>

    {{-- ---------- ONE PANEL VISIBLE AT A TIME ---------- --}}
    <div class="pos-panels">

        {{-- ============================================================
             STEP 1 — PRODUCT STORE
             Full-width shopping surface. No permanent cart on the side.
             ============================================================ --}}
        <section id="step-store" class="pos-panel is-active" data-panel="1">

            <div class="pos-store-toolbar">
                <div class="pos-search-wrap">
                    <i class="fa-solid fa-magnifying-glass pos-search-icon"></i>
                    <input type="search" id="pos-search"
                           placeholder="Search products or scan barcode..."
                           class="pos-search-input" autocomplete="off" autofocus>
                    <div id="pos-search-spinner" class="pos-search-spinner hidden">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                    </div>
                </div>

                <div class="pos-store-filters">
                    <select id="pos-unit-filter" class="pos-store-select">
                        <option value="all">All Units</option>
                        <option value="meter">Meters</option>
                        <option value="pcs">Pieces</option>
                    </select>
                    <select id="pos-sort-filter" class="pos-store-select">
                        <option value="featured">Featured</option>
                        <option value="name">Name A–Z</option>
                        <option value="price_asc">Price ↑</option>
                        <option value="price_desc">Price ↓</option>
                    </select>
                </div>
            </div>

            {{-- Category pill row scrolls internally on narrow screens
                 rather than making the whole page taller. --}}
            <div class="pos-pill-row">
                <button type="button" class="pos-pill is-active" data-cat="all">All</button>
                @foreach($categories as $cat)
                    <button type="button" class="pos-pill" data-cat="{{ $cat->id }}">{{ $cat->name }}</button>
                @endforeach
            </div>

            <div class="pos-store-scroll" id="pos-grid-scroll">
                <div class="pos-products-grid" id="products-grid">
                    @include('cloth-store.checkout.partials.product-cards', ['products' => $products])
                </div>
                <div id="no-products" class="pos-empty hidden">
                    <i class="fa-solid fa-box-open"></i>
                    <p>No products found</p>
                    <span>Try a different search or clear the filters</span>
                </div>
            </div>
        </section>

        {{-- ============================================================
             STEP 2 — CART
             Dedicated page. Product grid is not visible here.
             ============================================================ --}}
        <section id="step-cart" class="pos-panel is-next" data-panel="2">

            <div class="pos-page-head">
                <button class="pos-back" onclick="goToStep(1)">
                    <i class="fa-solid fa-arrow-left"></i> Continue Shopping
                </button>
                <div>
                    <h2>Your Cart</h2>
                    <span id="cart-line-count" class="pos-page-head-sub">0 items</span>
                </div>
                <button class="pos-back pos-back-danger" onclick="clearCart()">
                    <i class="fa-solid fa-trash-can"></i> Clear
                </button>
            </div>

            <div class="pos-cart-layout">
                <div class="pos-cart-main">
                    {{-- Cart table. The header collapses on narrow screens; the
                         individual rows carry their own labels for a11y. --}}
                    <div class="pos-cart-thead">
                        <span>Product</span>
                        <span class="ta-c">Qty</span>
                        <span class="ta-r">Unit Price</span>
                        <span class="ta-r">Total</span>
                        <span class="ta-r">—</span>
                    </div>
                    <div class="pos-cart-body" id="cart-list-body"></div>
                    <div id="cart-empty" class="pos-empty pos-empty-in-cart">
                        <i class="fa-solid fa-basket-shopping"></i>
                        <p>Your cart is empty</p>
                        <span>Add products from the store to get started</span>
                        <button class="btn-cs-primary mt-3" onclick="goToStep(1)">
                            <i class="fa-solid fa-arrow-left text-[10px]"></i> Continue Shopping
                        </button>
                    </div>
                </div>

                <aside class="pos-cart-summary">
                    <div class="pos-summary-head">Order Summary</div>
                    <div class="pos-summary-body">
                        <div class="pos-summary-row">
                            <span>Items / Meters</span>
                            <span class="cell-num" id="cart-qty-summary">0 / 0</span>
                        </div>
                        <div class="pos-summary-row">
                            <span>Subtotal</span>
                            <span class="cell-num" id="cart-subtotal">Rs 0</span>
                        </div>
                        <div class="pos-summary-row" style="flex-direction: column; align-items: stretch; gap: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Discount</span>
                                <input type="number" id="cart-discount" value="0" min="0" step="0.01"
                                       class="pos-discount-inline cell-num" placeholder="0"
                                       oninput="document.getElementById('cart-offer-select').value=''; renderCartAll()">
                            </div>
                            <select id="cart-offer-select" class="pos-store-select" style="width: 100%; border-radius: 6px; padding: 6px 10px; font-size: 12px; color: #475569; border-color: #cbd5e1; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#e11d48';" onblur="this.style.borderColor='#cbd5e1';" onchange="document.getElementById('cart-discount').value=0; renderCartAll()">
                                <option value="" data-value="0" data-type="fixed">-- Select an active offer --</option>
                                @if(isset($discounts))
                                    @foreach($discounts as $d)
                                        <option value="{{ $d->id }}" data-value="{{ $d->value }}" data-type="{{ $d->type }}">{{ $d->name }} @if(in_array($d->type, ['percentage', 'seasonal']))({{ number_format($d->value, 0) }}%)@else(Rs {{ number_format($d->value, 2) }})@endif</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="pos-summary-divider"></div>
                        <div class="pos-summary-grand">
                            <span>Grand Total</span>
                            <span class="cell-num" id="cart-grand-total">Rs 0</span>
                        </div>
                    </div>
                    <div class="pos-summary-actions">
                        <button class="btn-cs-primary w-full py-3" id="btn-checkout"
                                onclick="goToReview()" disabled>
                            Proceed to Review <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                        <button class="btn-cs-ghost w-full py-2.5" onclick="goToStep(1)">
                            Continue Shopping
                        </button>
                    </div>
                </aside>
            </div>
        </section>

        {{-- ============================================================
             STEP 3 — REVIEW
             Verification only. No shopping controls, no payment controls.
             ============================================================ --}}
        <section id="step-review" class="pos-panel is-next" data-panel="3">

            <div class="pos-page-head">
                <button class="pos-back" onclick="goToStep(2)">
                    <i class="fa-solid fa-arrow-left"></i> Back to Cart
                </button>
                <div>
                    <h2>Review Your Order</h2>
                    <span class="pos-page-head-sub" id="review-item-count">0 items</span>
                </div>
                <span></span>
            </div>

            <div class="pos-cart-layout">
                <div class="pos-cart-main">
                    <div class="pos-cart-thead pos-cart-thead-readonly">
                        <span>Product</span>
                        <span class="ta-c">Qty</span>
                        <span class="ta-r">Unit Price</span>
                        <span class="ta-r">Total</span>
                    </div>
                    <div class="pos-cart-body" id="review-list-body"></div>
                </div>

                <aside class="pos-cart-summary">
                    <div class="pos-summary-head">Order Summary</div>
                    <div class="pos-summary-body">
                        <div class="pos-summary-row">
                            <span>Subtotal</span>
                            <span class="cell-num" id="review-subtotal">Rs 0</span>
                        </div>
                        <div class="pos-summary-row">
                            <span>Discount</span>
                            <span class="cell-num" id="review-discount" style="color:#e11d48;font-weight:700;">-Rs 0</span>
                        </div>
                        <div class="pos-summary-divider"></div>
                        <div class="pos-summary-grand">
                            <span>Grand Total</span>
                            <span class="cell-num" id="review-total">Rs 0</span>
                        </div>
                    </div>
                    <div class="pos-summary-actions">
                        {{-- Review → Customer (was → Payment before we
                             inserted the dedicated Customer step). --}}
                        <button class="btn-cs-primary w-full py-3" onclick="goToCustomer()">
                            Continue to Customer <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                        <button class="btn-cs-ghost w-full py-2.5" onclick="goToStep(2)">Back to Cart</button>
                    </div>
                </aside>
            </div>
        </section>

        {{-- ============================================================
             STEP 3.5 — CUSTOMER
             Dedicated screen. Only the customer picker and add-customer
             form live here — no payment controls, no cart, no product grid.
             The visible <select>/search inputs still carry the ids
             `pos-customer` and `pos-customer-search` so every existing JS
             call site keeps working unchanged.
             ============================================================ --}}
        <section id="step-customer" class="pos-panel is-next" data-panel="4">

            <div class="pos-page-head">
                <button class="pos-back" onclick="goToStep(3)">
                    <i class="fa-solid fa-arrow-left"></i> Back to Review
                </button>
                <div>
                    <h2>Customer Information</h2>
                    <span class="pos-page-head-sub">Step 3 of 5</span>
                </div>
                <span></span>
            </div>

            <div class="pos-customer-layout">

                {{-- LEFT: pick an existing customer --}}
                <div class="pos-pay-card">
                    <div class="pos-pay-card-head">
                        <span>Search Existing Customer</span>
                    </div>
                    <div class="pos-pay-card-body">
                        <div class="pos-cust-chip pos-cust-chip-lg">
                            <div class="pos-cust-avatar" id="pay-cust-initials">?</div>
                            <div class="pos-cust-meta">
                                <span class="pos-cust-name" id="pay-cust-name">Walk-in Customer</span>
                                <span class="pos-cust-phone" id="pay-cust-phone">No phone</span>
                            </div>
                        </div>

                        <label class="pos-modal-label mt-3">Search by name, phone or customer ID</label>
                        <div class="pos-pay-field">
                            <i class="fa-solid fa-magnifying-glass pos-pay-field-icon"></i>
                            <input type="search" id="pos-customer-search" autocomplete="off"
                                   placeholder="Type at least 2 characters..." class="pos-pay-input">
                        </div>

                        <label class="pos-modal-label mt-3">Selected customer</label>
                        <div class="pos-pay-field">
                            <i class="fa-solid fa-user pos-pay-field-icon"></i>
                            <select id="pos-customer" class="pos-pay-input pos-pay-select">
                                @foreach($customers as $c)
                                <option value="{{ $c->id }}"
                                        data-due="{{ $c->due_balance }}"
                                        data-phone="{{ $c->phone }}"
                                        @selected(in_array(strtolower($c->name), ['walk-in', 'walkin'], true))>
                                    {{ $c->name }}{{ $c->phone ? ' (' . $c->phone . ')' : '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="customer-due-warning" class="pos-due-warning hidden mt-3">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            Outstanding: <span id="customer-due-amt" class="cell-num">Rs 0</span>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: quick add. Fields match cs_customers columns. --}}
                <div class="pos-pay-card">
                    <div class="pos-pay-card-head">
                        <span>+ Add New Customer</span>
                    </div>
                    <div class="pos-pay-card-body">
                        <form id="inline-customer-form" onsubmit="submitInlineCustomer(event)">
                            @csrf
                            <label class="pos-modal-label">Customer Name *</label>
                            <div class="pos-pay-field">
                                <i class="fa-solid fa-user pos-pay-field-icon"></i>
                                <input type="text" id="ic-name" class="pos-pay-input" placeholder="e.g. Ahmed Ali" required>
                            </div>

                            <label class="pos-modal-label mt-3">Phone Number</label>
                            <div class="pos-pay-field">
                                <i class="fa-solid fa-phone pos-pay-field-icon"></i>
                                <input type="text" id="ic-phone" class="pos-pay-input" placeholder="0300-1234567">
                            </div>

                            <label class="pos-modal-label mt-3">Address</label>
                            <div class="pos-pay-field">
                                <i class="fa-solid fa-location-dot pos-pay-field-icon"></i>
                                <input type="text" id="ic-address" class="pos-pay-input" placeholder="Optional">
                            </div>

                            <button type="submit" id="btn-inline-customer" class="btn-cs-primary w-full py-2.5 mt-4">
                                <i class="fa-solid fa-user-plus text-[10px]"></i> Save &amp; Select Customer
                            </button>
                            <p class="pos-inline-hint">
                                Saved customers are added to the database and selected automatically — no page reload.
                            </p>
                        </form>
                    </div>
                </div>
            </div>

            <div class="pos-customer-actions">
                <button class="btn-cs-ghost py-2.5 px-5" onclick="goToStep(3)">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Review
                </button>
                <button class="btn-cs-primary py-2.5 px-6" onclick="goToPayment()">
                    Continue to Payment <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </button>
            </div>
        </section>

        {{-- ============================================================
             STEP 4 — PAYMENT
             Methods + amount + change + Complete Sale.
             Customer selection now lives on the previous step; this screen
             only shows a compact read-only chip of who the sale is for.
             ============================================================ --}}
        <section id="step-payment" class="pos-panel is-next" data-panel="5">

            <div class="pos-page-head">
                <button class="pos-back" onclick="goToStep(4)">
                    <i class="fa-solid fa-arrow-left"></i> Back to Customer
                </button>
                <div>
                    <h2>Payment</h2>
                    <span class="pos-page-head-sub">Step 4 of 5</span>
                </div>
                <span></span>
            </div>

            <div class="pos-pay-layout" style="display: flex; flex-direction: column; gap: 16px; padding: 16px; background: transparent;">
                
                {{-- MAIN PAYMENT CONTAINER: Two balanced cards --}}
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; align-items: stretch; width: 100%;">
                    
                    {{-- CARD 1 — ORDER TOTAL --}}
                    <div class="pos-pay-card">
                        <div class="pos-pay-card-head" style="padding: 12px 16px; font-size: 13px;">
                            <span>Order Total</span>
                        </div>
                        <div class="pos-pay-card-body" style="padding: 16px 20px; display: flex; flex-direction: column; justify-content: center; height: 100%;">
                            <div class="pos-summary-row" style="font-size: 14px; margin-bottom: 12px;">
                                <span style="color: #475569; font-weight: 600;">Subtotal</span>
                                <span class="cell-num" id="summary-subtotal" style="color: #1e293b; font-weight: 700;">Rs 0</span>
                            </div>
                            <div class="pos-summary-row" style="font-size: 14px; margin-bottom: 16px;">
                                <span style="color: #475569; font-weight: 600;">Discount</span>
                                <span class="cell-num" id="summary-discount" style="color:#e11d48;font-weight:700;">-Rs 0</span>
                            </div>
                            <div class="pos-summary-divider" style="margin: 0 0 16px 0;"></div>
                            <div class="pos-summary-grand" style="display: flex; justify-content: space-between; align-items: center; font-size: 22px; font-weight: 900; color: #0f172a;">
                                <span>Grand Total</span>
                                <span class="cell-num" id="summary-grand-total">Rs 0</span>
                            </div>
                        </div>
                    </div>

                    {{-- CARD 2 — PAYMENT METHOD --}}
                    <div class="pos-pay-card">
                        <div class="pos-pay-card-head" style="padding: 12px 16px; font-size: 13px;">
                            <span>Payment Method</span>
                        </div>
                        <div class="pos-pay-card-body" style="padding: 16px; display: flex; flex-direction: column; justify-content: center; height: 100%;">
                            <style>
                                .premium-pm-grid {
                                    display: grid;
                                    grid-template-columns: repeat(2, 1fr);
                                    gap: 12px;
                                }
                                .premium-pm-grid .pay-method {
                                    position: relative;
                                    background: #ffffff;
                                    border: 1px solid #e2e8f0;
                                    border-radius: 10px;
                                    padding: 12px;
                                    display: flex;
                                    align-items: flex-start;
                                    gap: 10px;
                                    text-align: left;
                                    cursor: pointer;
                                    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
                                    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
                                }
                                .premium-pm-grid .pay-method:hover {
                                    border-color: #cbd5e1;
                                    box-shadow: 0 4px 6px rgba(15, 23, 42, 0.04);
                                    transform: translateY(-1px);
                                }
                                
                                /* Active state */
                                .premium-pm-grid .pay-method.active {
                                    border-color: #0f172a !important;
                                    box-shadow: 0 0 0 1px #0f172a, 0 4px 6px rgba(15, 23, 42, 0.04) !important;
                                }

                                /* Icon wrapper */
                                .premium-pm-grid .pay-icon-wrap {
                                    width: 32px;
                                    height: 32px;
                                    border-radius: 8px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-size: 14px;
                                    flex-shrink: 0;
                                    transition: all 0.2s ease;
                                }
                                
                                /* Text details */
                                .premium-pm-grid .pay-details {
                                    display: flex;
                                    flex-direction: column;
                                    gap: 2px;
                                    margin-top: 0px;
                                }
                                .premium-pm-grid .pay-name {
                                    font-size: 13px;
                                    font-weight: 700;
                                    color: #0f172a;
                                    line-height: 1.2;
                                }
                                .premium-pm-grid .pay-desc {
                                    font-size: 11px;
                                    font-weight: 500;
                                    color: #64748b;
                                    line-height: 1.2;
                                }
                                
                                /* Checkmark */
                                .premium-pm-grid .pay-check {
                                    position: absolute;
                                    top: 12px;
                                    right: 12px;
                                    color: #0f172a;
                                    font-size: 12px;
                                    opacity: 0;
                                    transform: scale(0.5);
                                    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
                                }
                                .premium-pm-grid .pay-method.active .pay-check {
                                    opacity: 1;
                                    transform: scale(1);
                                }

                                /* --- Brand Colors --- */
                                /* Cash: Green */
                                .premium-pm-grid .pay-method[data-pm="cash"] .pay-icon-wrap {
                                    background: #dcfce7;
                                    color: #16a34a;
                                }
                                .premium-pm-grid .pay-method[data-pm="cash"].active {
                                    background: #f0fdf4 !important;
                                }

                                /* Card: Blue */
                                .premium-pm-grid .pay-method[data-pm="card"] .pay-icon-wrap {
                                    background: #dbeafe;
                                    color: #2563eb;
                                }
                                .premium-pm-grid .pay-method[data-pm="card"].active {
                                    background: #eff6ff !important;
                                }

                                /* Bank: Navy/Indigo */
                                .premium-pm-grid .pay-method[data-pm="bank"] .pay-icon-wrap {
                                    background: #e0e7ff;
                                    color: #4f46e5;
                                }
                                .premium-pm-grid .pay-method[data-pm="bank"].active {
                                    background: #eef2ff !important;
                                }

                                /* EasyPaisa: Green */
                                .premium-pm-grid .pay-method[data-pm="easypaisa"] .pay-icon-wrap {
                                    background: #d1fae5;
                                    color: #059669;
                                }
                                .premium-pm-grid .pay-method[data-pm="easypaisa"].active {
                                    background: #ecfdf5 !important;
                                }
                            </style>
                            <div class="pos-pay-methods premium-pm-grid">
                                @foreach([
                                    ['Cash', 'fa-money-bill-wave', 'Physical currency', 'cash'],
                                    ['Card', 'fa-credit-card', 'POS terminal', 'card'],
                                    ['Bank Transfer', 'fa-building-columns', 'Direct deposit', 'bank'],
                                    ['EasyPaisa', 'fa-wallet', 'Mobile wallet', 'easypaisa'],
                                ] as [$method, $icon, $desc, $type])
                                    <button type="button" class="pay-method {{ $loop->first ? 'active' : '' }}" data-method="{{ $method }}" data-pm="{{ $type }}">
                                        <div class="pay-icon-wrap">
                                            <i class="fa-solid {{ $icon }}"></i>
                                        </div>
                                        <div class="pay-details">
                                            <span class="pay-name">{{ $method }}</span>
                                            <span class="pay-desc">{{ $desc }}</span>
                                        </div>
                                        <i class="fa-solid fa-circle-check pay-check"></i>
                                    </button>
                                @endforeach
                            </div>
                            <input type="hidden" id="selected-method" value="Cash">
                        </div>
                    </div>
                </div>

                {{-- COMPLETE SALE --}}
                <div style="width: 100%; display: flex; justify-content: center;">
                    <button onclick="submitCheckout()" id="btn-submit-order" class="pos-complete-btn" style="width: 100%; max-width: 340px; padding: 12px; font-size: 15px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);">
                        <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> Complete Sale
                    </button>
                </div>

                {{-- Hidden Elements to prevent JS errors from removed sections --}}
                <div style="display: none;">
                    <input type="number" id="paid-amount" value="0">
                    <div id="pos-change-row">
                        <span id="change-label"></span>
                        <span id="change-amount"></span>
                        <span id="change-hint"></span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============================================================
             STEP 5 — DONE
             Success screen with items sold, payment summary, and receipt.
             ============================================================ --}}
        <section id="step-done" class="pos-panel is-next" data-panel="6">

            <div class="pos-done-head">
                <div class="pos-done-tick">
                    <svg viewBox="0 0 52 52" fill="none">
                        <path d="M14 27l8 8 16-16" stroke="#059669" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div>
                    <h2>Sale Complete</h2>
                    <p>Invoice <strong class="cell-num" id="done-invoice">—</strong>
                       · <span id="done-customer">Walk-in Customer</span></p>
                </div>
            </div>

            <div class="pos-done-layout">
                <div class="pos-cart-main">
                    <div class="pos-cart-thead pos-cart-thead-readonly">
                        <span>Product</span>
                        <span class="ta-c">Qty</span>
                        <span class="ta-r">Unit Price</span>
                        <span class="ta-r">Total</span>
                    </div>
                    <div class="pos-cart-body" id="done-items-body"></div>
                    <div class="pos-done-total-bar">
                        <span>Total</span>
                        <span class="cell-num" id="done-total">Rs 0</span>
                    </div>
                </div>

                <aside class="pos-done-side">
                    <div class="pos-pay-card">
                        <div class="pos-pay-card-head"><span>Payment Summary</span></div>
                        <div class="pos-pay-card-body pos-done-summary">
                            <div class="pos-done-kv">
                                <span>Payment Method</span>
                                <span class="pos-done-kv-value" id="done-method">Cash</span>
                            </div>
                            <div class="pos-done-kv pos-done-kv-paid">
                                <span>Total Paid</span>
                                <span class="cell-num" id="done-paid">Rs 0</span>
                            </div>
                            <div class="pos-done-kv" id="done-balance-kv">
                                <span id="done-balance-label">Change</span>
                                <span class="cell-num" id="done-balance">Rs 0</span>
                            </div>
                        </div>
                    </div>

                    <div class="pos-done-actions">
                        <button onclick="printReceipt()" class="btn-cs-primary w-full py-3">
                            <i class="fa-solid fa-print text-[10px]"></i> Print 80mm Receipt
                        </button>
                        <div class="grid grid-cols-2 gap-2">
                            <button onclick="showLastReceipt()" class="btn-cs-ghost py-2.5">
                                <i class="fa-solid fa-receipt text-[10px]"></i> Preview
                            </button>
                            <a href="{{ route('cloth-store.orders.index') }}" class="btn-cs-ghost py-2.5">
                                <i class="fa-solid fa-list text-[10px]"></i> Orders
                            </a>
                        </div>
                        <button onclick="startNewSale()" class="btn-cs-primary w-full py-3 mt-2">
                            <i class="fa-solid fa-plus text-[10px]"></i> New Sale
                        </button>
                        <p class="pos-done-hint">
                            Press <kbd>N</kbd> for the next sale
                        </p>
                    </div>
                </aside>
            </div>
        </section>

    </div>
</div>

{{-- ---------- ADD CUSTOMER MODAL (compact) ---------- --}}
<div id="new-customer-modal" class="pos-modal-back hidden">
    <div class="pos-modal">
        <div class="pos-modal-head">
            <h3>Add Customer</h3>
            <button onclick="document.getElementById('new-customer-modal').classList.add('hidden')"
                    class="pos-modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="quick-customer-form" onsubmit="submitNewCustomer(event)">
            @csrf
            <div class="pos-modal-body">
                <label class="pos-modal-label">Full Name *</label>
                <input type="text" id="qc-name" name="name" class="pos-modal-input" placeholder="e.g. Ahmed Khan" required>

                <label class="pos-modal-label">Phone</label>
                <input type="text" id="qc-phone" name="phone" class="pos-modal-input" placeholder="0300-1234567">

                <label class="pos-modal-label">City</label>
                <input type="text" id="qc-city" name="city" class="pos-modal-input" placeholder="Optional">
            </div>
            <div class="pos-modal-foot">
                <button type="button" onclick="document.getElementById('new-customer-modal').classList.add('hidden')" class="btn-cs-ghost">Cancel</button>
                <button type="submit" id="btn-save-customer" class="btn-cs-primary">
                    <i class="fa-solid fa-check text-[10px]"></i> Save Customer
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ---------- 80mm RECEIPT PREVIEW ---------- --}}
<div id="receiptModal" class="pos-modal-back hidden">
    <div class="pos-receipt-wrap">
        <button onclick="closeReceipt()" class="pos-receipt-close">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="receipt-content" class="thermal-receipt"></div>
        <div class="pos-receipt-actions">
            <button onclick="printReceipt()" class="btn-cs-primary flex-1 py-2.5">
                <i class="fa-solid fa-print text-[10px]"></i> Print
            </button>
            <button onclick="closeReceipt()" class="btn-cs-ghost flex-1 py-2.5">Close</button>
        </div>
    </div>
</div>

<style>
/* ==============================================================
   SMART CHECKOUT — full-width, five separate step panels
   ============================================================== */

/* The parent <main> normally has padding — the checkout is a workspace,
   not a page card, so we peel that off just for this route. */
#spa-main:has(.pos-page) { padding: 0 !important; }

.pos-page {
    width: 100%;
    height: calc(100vh - 64px);
    display: flex;
    flex-direction: column;
    background: #f8fafc;
    overflow: hidden;
}

/* ---------- top bar ---------- */
.pos-top {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 20px;
    padding: 12px 20px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}
.pos-top-title h1 {
    font-size: 15px; font-weight: 800; color: #0f172a; letter-spacing: -0.01em;
}
.pos-top-title p {
    font-size: 11px; color: #64748b; margin-top: 2px;
}
.pos-cart-btn {
    justify-self: end;
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 14px;
    background: #0f172a; color: #fff;
    border-radius: 10px;
    font-size: 12px; font-weight: 700;
    cursor: pointer;
    transition: background .15s ease, transform .15s ease;
    border: none;
}
.pos-cart-btn:hover { background: #1e293b; }
.pos-cart-btn:active { transform: translateY(1px); }
.pos-cart-btn-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; padding: 0 6px;
    background: #f59e0b; color: #0f172a;
    border-radius: 10px;
    font-size: 10px; font-weight: 900;
}

/* Stepper */
.pos-stepper { display: flex; align-items: center; gap: 8px; }
.pos-step-item { display: flex; align-items: center; gap: 6px; }
.pos-step-dot {
    width: 22px; height: 22px; border-radius: 50%;
    background: #f1f5f9; color: #94a3b8;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 800;
    border: 1px solid #e2e8f0;
    transition: background .2s ease, color .2s ease, border-color .2s ease, transform .2s ease;
}
.pos-step-dot i { display: none; font-size: 9px; }
/* Only the Done dot (the last order) renders its icon; numbered dots hide it. */
.pos-step-item[data-order="5"] .pos-step-dot i { display: inline; }
.pos-step-label {
    font-size: 11px; color: #94a3b8; font-weight: 600;
    transition: color .2s ease;
}
.pos-step-line {
    width: 24px; height: 2px; background: #e2e8f0; border-radius: 2px;
    position: relative; overflow: hidden;
}
.pos-step-item.is-active .pos-step-dot {
    background: #0f172a; color: #fff; border-color: #0f172a; transform: scale(1.08);
}
.pos-step-item.is-active .pos-step-label { color: #0f172a; font-weight: 700; }
.pos-step-item.is-done .pos-step-dot { background: #059669; color: #fff; border-color: #059669; }
.pos-step-item.is-done + .pos-step-line { background: #10b981; }

/* ---------- panels ---------- */
.pos-panels { flex: 1; min-height: 0; position: relative; }
.pos-panel {
    position: absolute; inset: 0;
    display: flex; flex-direction: column;
    opacity: 0; pointer-events: none;
    transform: translateX(40px);
    transition: opacity .35s cubic-bezier(.16,1,.3,1),
                transform .35s cubic-bezier(.16,1,.3,1);
    background: #f8fafc;
    overflow: hidden;
}
.pos-panel.is-active { opacity: 1; pointer-events: auto; transform: translateX(0); z-index: 5; }
.pos-panel.is-previous { transform: translateX(-40px); }

/* ==============================================================
   STEP 1 — PRODUCT STORE
   ============================================================== */
.pos-store-toolbar {
    display: flex; gap: 12px; align-items: center;
    padding: 14px 20px; background: #fff; border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}
.pos-search-wrap { position: relative; flex: 1; min-width: 0; }
.pos-search-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: 12px; pointer-events: none;
}
.pos-search-input {
    width: 100%;
    padding: 11px 42px 11px 40px;
    border: 1px solid #e2e8f0; border-radius: 10px;
    font-size: 13px; font-weight: 500; color: #0f172a;
    background: #f8fafc;
    transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
}
.pos-search-input:focus {
    outline: none; background: #fff;
    border-color: #0f172a; box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08);
}
.pos-search-spinner { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

.pos-store-filters { display: flex; gap: 8px; flex-shrink: 0; }
.pos-store-select {
    padding: 10px 12px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
    font-size: 12px; font-weight: 600; color: #334155;
    cursor: pointer;
}
.pos-store-select:focus { outline: none; border-color: #0f172a; }

.pos-pill-row {
    display: flex; gap: 8px;
    padding: 12px 20px;
    background: #fff; border-bottom: 1px solid #e2e8f0;
    overflow-x: auto; scrollbar-width: thin;
    flex-shrink: 0;
}
.pos-pill-row::-webkit-scrollbar { height: 4px; }
.pos-pill-row::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }
.pos-pill {
    flex-shrink: 0;
    padding: 6px 14px;
    background: #f1f5f9; color: #475569;
    border: 1px solid transparent; border-radius: 999px;
    font-size: 12px; font-weight: 600;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.pos-pill:hover { background: #e2e8f0; color: #0f172a; }
.pos-pill.is-active { background: #0f172a; color: #fff; }

.pos-store-scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 16px 20px; }
.pos-products-grid {
    display: grid;
    /* Compact tiles — the store is optimised for 100+ products. */
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 12px;
}

/* --- product card --- */
.pos-product-card {
    position: relative;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px;
    display: flex; flex-direction: column;
    cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}
.pos-product-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(15, 23, 42, .06);
    transform: translateY(-1px);
}
.pos-product-card.is-selected {
    border-color: #0f172a;
    box-shadow: inset 0 0 0 1px #0f172a, 0 4px 12px rgba(15, 23, 42, .1);
}
.pos-product-card.is-out { opacity: .55; cursor: not-allowed; }
.pos-product-card.is-out:hover { transform: none; box-shadow: none; }

.pos-card-oos-badge {
    position: absolute; top: 8px; left: 8px;
    padding: 2px 6px; border-radius: 4px;
    background: #ef4444; color: #fff;
    font-size: 9px; font-weight: 800; letter-spacing: .06em;
    z-index: 2;
}
.pos-card-qty-badge {
    position: absolute; top: 8px; right: 8px;
    min-width: 22px; height: 22px; padding: 0 6px;
    background: #0f172a; color: #fff;
    border-radius: 11px;
    font-size: 11px; font-weight: 800;
    display: none; align-items: center; justify-content: center;
    z-index: 2;
}
.pos-product-card.is-selected .pos-card-qty-badge { display: inline-flex; }

.pos-card-img {
    aspect-ratio: 4 / 3;
    background: #f1f5f9;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #cbd5e1;
    font-size: 24px;
    overflow: hidden;
    margin-bottom: 8px;
}
.pos-card-img img { width: 100%; height: 100%; object-fit: cover; }

.pos-card-body { display: flex; flex-direction: column; gap: 3px; }
.pos-card-name {
    font-size: 12px; font-weight: 700; color: #0f172a;
    line-height: 1.3;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.6em;
}
.pos-card-meta { display: flex; align-items: center; gap: 4px; font-size: 10px; color: #94a3b8; }
.pos-card-dot { color: #cbd5e1; }
.pos-card-footer {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 4px;
}
.pos-card-price { font-size: 13px; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; }
.pos-card-stock {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 700; color: #64748b;
    font-variant-numeric: tabular-nums;
}
.pos-card-stock-dot { width: 6px; height: 6px; border-radius: 50%; background: #10b981; }
.pos-card-stock.is-low { color: #f59e0b; }
.pos-card-stock.is-low .pos-card-stock-dot { background: #f59e0b; }

.pos-card-add { display: none; }

/* --- empty state --- */
.pos-empty {
    padding: 60px 20px; text-align: center; color: #94a3b8;
}
.pos-empty i { font-size: 42px; color: #cbd5e1; margin-bottom: 12px; }
.pos-empty p { font-size: 14px; font-weight: 700; color: #475569; margin: 0; }
.pos-empty span { display: block; font-size: 12px; color: #94a3b8; margin-top: 4px; }
.pos-empty-in-cart {
    display: none;
    padding: 80px 20px;
}
.pos-cart-body:empty + .pos-empty-in-cart { display: block; }

/* ==============================================================
   STEP 2 & 3 — CART / REVIEW page shell
   ============================================================== */
.pos-page-head {
    display: grid;
    grid-template-columns: minmax(0, 200px) 1fr minmax(0, 200px);
    align-items: center;
    gap: 16px;
    padding: 14px 20px;
    background: #fff; border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}
.pos-page-head > div { text-align: center; }
.pos-page-head h2 {
    font-size: 15px; font-weight: 800; color: #0f172a; letter-spacing: -0.01em;
}
.pos-page-head-sub { font-size: 11px; color: #94a3b8; font-weight: 600; }
.pos-page-head > span:last-child { justify-self: end; }

.pos-back {
    justify-self: start;
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px;
    background: #f8fafc; color: #475569;
    border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: 12px; font-weight: 600;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, border-color .15s ease;
}
.pos-back:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }
.pos-back-danger { justify-self: end; color: #dc2626; }
.pos-back-danger:hover { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

.pos-cart-layout {
    flex: 1; min-height: 0;
    display: grid; grid-template-columns: 1fr 380px;
    gap: 16px; padding: 16px 20px;
    align-items: stretch;
}
.pos-cart-main {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    display: flex; flex-direction: column; min-height: 0; overflow: hidden;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}

.pos-cart-thead,
.pos-cart-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 130px 100px 100px 40px;
    gap: 12px;
    align-items: center;
    padding: 10px 16px;
}

/* --- Inline Cut UI --- */
.pos-cut-ui {
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    font-size: 11px;
    display: none;
}
.pos-cut-ui:not(.hidden) {
    display: block;
}
.pos-cut-ui-inner {
    padding: 12px 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.pos-cut-input-row {
    display: flex;
    gap: 6px;
}
.pos-cut-input {
    flex: 1;
    min-width: 0;
    padding: 4px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 12px;
    outline: none;
}
.pos-cut-input:focus {
    border-color: #0f172a;
}
.pos-cut-btn {
    padding: 4px 10px;
    font-size: 10px;
    border-radius: 6px;
}
.pos-cut-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 80px;
    overflow-y: auto;
}
.pos-cut-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    padding: 4px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
}
.pos-cut-item-info {
    display: flex;
    gap: 8px;
    align-items: baseline;
}
.pos-cut-item-qty {
    font-weight: 700;
    color: #0f172a;
}
.pos-cut-item-amt {
    color: #64748b;
}
.pos-cut-item-del {
    color: #ef4444;
    cursor: pointer;
    font-size: 10px;
}
.pos-cut-item-del:hover {
    color: #b91c1c;
}
.pos-cut-summary {
    display: flex;
    justify-content: space-between;
    border-top: 1px solid #e2e8f0;
    padding-top: 6px;
    margin-top: 2px;
}
.pos-cut-remaining {
    font-size: 9px;
    color: #64748b;
    text-align: right;
}
.pos-cart-thead-readonly,
.pos-cart-thead-readonly + .pos-cart-body .pos-cart-row {
    grid-template-columns: minmax(0, 1fr) 90px 100px 110px;
}
.pos-cart-thead {
    background: #f8fafc; border-bottom: 1px solid #e2e8f0;
    font-size: 9px; font-weight: 800; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .06em;
    flex-shrink: 0;
}
.pos-cart-thead .ta-c { text-align: center; }
.pos-cart-thead .ta-r { text-align: right; }
.pos-cart-body { flex: 1; min-height: 0; overflow-y: auto; }
.pos-cart-row { border-bottom: 1px solid #f1f5f9; transition: background .1s ease; }
.pos-cart-row:hover { background: #f8fafc; }
.pos-cart-row:last-child { border-bottom: none; }

.pos-cart-prod { display: flex; align-items: center; gap: 10px; min-width: 0; }
.pos-cart-thumb {
    width: 40px; height: 40px; border-radius: 8px;
    background: #f1f5f9; color: #cbd5e1;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.pos-cart-thumb img { width: 100%; height: 100%; border-radius: 8px; object-fit: cover; }
.pos-cart-prod-info { min-width: 0; }
.pos-cart-prod-name {
    font-size: 12px; font-weight: 700; color: #0f172a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pos-cart-prod-meta { font-size: 10px; color: #94a3b8; }

.pos-qty-ctrl {
    display: inline-flex; align-items: center; gap: 4px;
    background: #f1f5f9; border-radius: 8px; padding: 2px;
}
.pos-qty-btn {
    width: 22px; height: 22px; border-radius: 6px;
    background: #fff; color: #475569;
    border: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 10px;
    transition: background .1s ease, color .1s ease;
}
.pos-qty-btn:hover { background: #0f172a; color: #fff; border-color: #0f172a; }
.pos-qty-val {
    min-width: 44px; padding: 0 4px;
    background: transparent; border: none;
    text-align: center;
    font-size: 12px; font-weight: 700; color: #0f172a;
    font-variant-numeric: tabular-nums;
    outline: none;
}
.pos-qty-unit { font-size: 10px; color: #94a3b8; padding-right: 4px; }

.pos-cart-price { font-size: 12px; color: #64748b; text-align: right; font-variant-numeric: tabular-nums; }
.pos-cart-total { font-size: 13px; font-weight: 800; color: #0f172a; text-align: right; font-variant-numeric: tabular-nums; }
.pos-cart-remove {
    justify-self: end;
    width: 28px; height: 28px; border-radius: 8px;
    background: transparent; color: #cbd5e1; border: none;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 12px;
    transition: background .1s ease, color .1s ease;
}
.pos-cart-remove:hover { background: #fef2f2; color: #dc2626; }

/* --- summary card (right side of cart/review) --- */
.pos-cart-summary {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    display: flex; flex-direction: column; min-height: 0; overflow: hidden;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}
.pos-summary-head {
    padding: 12px 16px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;
    font-size: 11px; font-weight: 800; color: #475569;
    text-transform: uppercase; letter-spacing: .05em;
    flex-shrink: 0;
}
.pos-summary-body { padding: 16px; display: flex; flex-direction: column; gap: 10px; flex: 1; }
.pos-summary-row {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 13px; color: #64748b; font-weight: 600;
}
.pos-summary-row .cell-num { color: #0f172a; font-weight: 700; }
.pos-discount-inline {
    width: 100px;
    padding: 6px 10px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
    text-align: right; font-size: 12px; font-weight: 700; color: #e11d48;
    outline: none;
}
.pos-discount-inline:focus { border-color: #e11d48; background: #fff; }
.pos-summary-divider { height: 1px; background: #e2e8f0; margin: 4px 0; }
.pos-summary-grand {
    display: flex; justify-content: space-between; align-items: baseline;
}
.pos-summary-grand span:first-child {
    font-size: 10px; font-weight: 800; color: #64748b;
    text-transform: uppercase; letter-spacing: .05em;
}
.pos-summary-grand .cell-num {
    font-size: 22px; font-weight: 900; color: #0f172a;
    letter-spacing: -0.02em;
}
.pos-summary-actions {
    padding: 14px 16px; border-top: 1px solid #e2e8f0; background: #fff;
    flex-shrink: 0;
    display: flex; flex-direction: column; gap: 8px;
}

/* ==============================================================
   STEP — CUSTOMER (between Review and Payment)
   ============================================================== */
.pos-customer-layout {
    flex: 1; min-height: 0;
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 16px; padding: 16px 20px;
    align-items: start;
    overflow-y: auto;
}
.pos-customer-actions {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 20px; background: #fff; border-top: 1px solid #e2e8f0;
    flex-shrink: 0;
    gap: 12px;
}
.pos-cust-chip-lg {
    padding: 14px 16px;
}
.pos-cust-chip-lg .pos-cust-avatar {
    width: 44px; height: 44px; font-size: 14px;
}
.pos-cust-chip-lg .pos-cust-name { font-size: 15px; }
.pos-cust-chip-lg .pos-cust-phone { font-size: 12px; }
.pos-inline-hint {
    font-size: 11px; color: #94a3b8; text-align: center;
    margin-top: 10px; line-height: 1.4;
}

/* ==============================================================
   STEP — PAYMENT
   ============================================================== */
.pos-pay-layout {
    flex: 1; min-height: 0;
    display: grid; grid-template-columns: 360px 1fr;
    gap: 16px; padding: 16px 20px;
    align-items: stretch;
}
.pos-pay-side, .pos-pay-main {
    display: flex; flex-direction: column; gap: 16px; min-height: 0;
}
.pos-pay-side { overflow-y: auto; }
.pos-pay-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    display: flex; flex-direction: column;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    overflow: hidden;
}
.pos-pay-card-grow { flex: 1; min-height: 0; }
.pos-pay-card-head {
    padding: 10px 16px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;
    display: flex; justify-content: space-between; align-items: center;
    font-size: 11px; font-weight: 800; color: #475569;
    text-transform: uppercase; letter-spacing: .05em;
    flex-shrink: 0;
}
.pos-pay-mini {
    padding: 3px 10px; border-radius: 6px;
    background: #eef2ff; color: #4338ca; border: none;
    font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em;
    cursor: pointer;
}
.pos-pay-mini:hover { background: #e0e7ff; }
.pos-pay-card-body { padding: 14px; display: flex; flex-direction: column; gap: 10px; }

.pos-pay-field { position: relative; }
.pos-pay-field-icon {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: 11px; pointer-events: none;
}
.pos-pay-input {
    width: 100%;
    padding: 9px 12px 9px 34px;
    border: 1px solid #e2e8f0; border-radius: 8px;
    background: #f8fafc;
    font-size: 12px; font-weight: 600; color: #0f172a;
    outline: none;
    transition: border-color .15s ease, background-color .15s ease;
}
.pos-pay-input:focus { border-color: #0f172a; background: #fff; box-shadow: 0 0 0 3px rgba(15,23,42,.06); }
.pos-pay-select {
    appearance: none;
    padding-right: 32px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center; background-size: 12px;
}

.pos-cust-chip {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
}
.pos-cust-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: #0f172a; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800;
    flex-shrink: 0;
}
.pos-cust-meta { display: flex; flex-direction: column; min-width: 0; }
.pos-cust-name {
    font-size: 13px; font-weight: 700; color: #0f172a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pos-cust-phone { font-size: 11px; color: #94a3b8; }

.pos-due-warning {
    padding: 8px 10px; border-radius: 8px;
    background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
    font-size: 11px; font-weight: 700;
}

/* Payment methods 2x2 */
.pos-pay-methods {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
}
.pay-method {
    display: flex; align-items: center; gap: 10px;
    padding: 12px;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
    cursor: pointer; text-align: left;
    transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease;
}
.pay-method:hover { border-color: #cbd5e1; background: #f8fafc; }
.pay-method.active {
    border-color: #0f172a; background: #eef2ff;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, .08);
}
.pay-icon {
    width: 34px; height: 34px; border-radius: 8px;
    background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0;
    transition: background .18s ease, color .18s ease, border-color .18s ease;
}
.pay-method.active .pay-icon {
    background: #0f172a; color: #fff; border-color: #0f172a;
}
.pos-pm-text { display: flex; flex-direction: column; min-width: 0; }
.pos-pm-name {
    font-size: 12px; font-weight: 700; color: #0f172a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pos-pm-desc {
    font-size: 10px; color: #94a3b8;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* Amount received */
.pos-amount-wrap { position: relative; }
.pos-amount-rs {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    font-size: 15px; font-weight: 800; color: #94a3b8;
}
.pos-amount-input {
    width: 100%;
    padding: 12px 68px 12px 42px;
    border: 1px solid #e2e8f0; border-radius: 10px;
    font-size: 20px; font-weight: 900; color: #0f172a;
    background: #fff;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.pos-amount-input:focus { border-color: #0f172a; box-shadow: 0 0 0 3px rgba(15,23,42,.08); }
.pos-amount-full {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    padding: 6px 12px;
    background: #0f172a; color: #fff; border: none; border-radius: 6px;
    font-size: 11px; font-weight: 800;
    cursor: pointer;
}
.pos-amount-full:hover { background: #1e293b; }

.pos-quick-grid {
    display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px;
}
.pos-quick {
    padding: 8px 0;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;
    font-size: 11px; font-weight: 700; color: #475569;
    cursor: pointer;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
}
.pos-quick:hover { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
.pos-quick-clear { color: #dc2626; background: #fef2f2; border-color: #fecaca; }
.pos-quick-clear:hover { background: #fee2e2; color: #b91c1c; }

.pos-change-row {
    display: flex; justify-content: space-between; align-items: center; gap: 12px;
    padding: 12px; border-radius: 8px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    margin-top: 4px;
}
.pos-change-row.is-paid { background: #ecfdf5; border-color: #a7f3d0; }
.pos-change-row.is-due  { background: #fef2f2; border-color: #fecaca; }
.pos-change-row-text { display: flex; flex-direction: column; min-width: 0; }
.pos-change-row-label {
    font-size: 10px; font-weight: 800; color: #475569;
    text-transform: uppercase; letter-spacing: .05em;
}
.pos-change-row-hint { font-size: 11px; color: #94a3b8; margin-top: 2px; }
.pos-change-row-value {
    font-size: 22px; font-weight: 900; color: #0f172a;
    letter-spacing: -0.02em;
}

.pos-pay-footer {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 12px;
    flex-shrink: 0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}
.pos-complete-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #fff; border: none; border-radius: 10px;
    font-size: 14px; font-weight: 800; letter-spacing: -0.01em;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(5, 150, 105, .3);
    transition: transform .15s ease, box-shadow .15s ease;
}
.pos-complete-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(5, 150, 105, .4); }
.pos-complete-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; box-shadow: none; }

/* ==============================================================
   STEP 5 — DONE
   ============================================================== */
.pos-done-head {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 20px;
    background: #fff; border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}
.pos-done-tick {
    width: 48px; height: 48px; border-radius: 50%;
    background: #ecfdf5; border: 2px solid #a7f3d0;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    animation: donePop .5s cubic-bezier(.16,1,.3,1) both;
}
.pos-done-tick svg { width: 24px; height: 24px; }
.pos-done-tick svg path {
    stroke-dasharray: 48; stroke-dashoffset: 48;
    animation: drawTick .5s ease-out .3s forwards;
}
@keyframes donePop {
    0%   { transform: scale(.4); opacity: 0; }
    60%  { transform: scale(1.1); opacity: 1; }
    100% { transform: scale(1); }
}
@keyframes drawTick { to { stroke-dashoffset: 0; } }
.pos-done-head h2 { font-size: 18px; font-weight: 900; color: #0f172a; letter-spacing: -0.02em; }
.pos-done-head p { font-size: 12px; color: #64748b; margin-top: 2px; }

.pos-done-layout {
    flex: 1; min-height: 0;
    display: grid; grid-template-columns: 1fr 360px;
    gap: 16px; padding: 16px 20px;
    align-items: stretch;
}
.pos-done-total-bar {
    display: flex; justify-content: space-between; align-items: baseline;
    padding: 14px 16px; border-top: 1px solid #e2e8f0; background: #f8fafc;
    flex-shrink: 0;
}
.pos-done-total-bar span:first-child {
    font-size: 10px; font-weight: 800; color: #64748b;
    text-transform: uppercase; letter-spacing: .05em;
}
.pos-done-total-bar .cell-num {
    font-size: 22px; font-weight: 900; color: #0f172a;
    letter-spacing: -0.02em;
}

.pos-done-side { display: flex; flex-direction: column; gap: 16px; min-height: 0; overflow-y: auto; }
.pos-done-summary { gap: 12px; }
.pos-done-kv {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 12px; color: #64748b; font-weight: 600;
}
.pos-done-kv-value { color: #0f172a; font-weight: 700; }
.pos-done-kv-paid { padding: 10px; background: #ecfdf5; border-radius: 8px; }
.pos-done-kv-paid span:first-child { color: #047857; font-weight: 800; }
.pos-done-kv-paid .cell-num { color: #059669; font-size: 18px; font-weight: 900; }
#done-balance-kv { padding: 10px; background: #f8fafc; border-radius: 8px; }
#done-balance-kv.is-due { background: #fef2f2; }
#done-balance-kv.is-due #done-balance-label { color: #b91c1c; font-weight: 800; }
#done-balance-kv.is-due .cell-num { color: #dc2626; font-size: 18px; font-weight: 900; }

.pos-done-actions {
    display: flex; flex-direction: column; gap: 8px;
    padding: 4px 0;
}
.pos-done-hint {
    font-size: 11px; text-align: center; color: #94a3b8; margin-top: 6px;
}
.pos-done-hint kbd {
    padding: 1px 6px; border: 1px solid #e2e8f0; border-radius: 4px;
    background: #f8fafc; font-family: inherit; font-weight: 700; color: #475569;
}

/* ==============================================================
   MODALS — Add Customer + Receipt
   ============================================================== */
.pos-modal-back {
    position: fixed; inset: 0; z-index: 60;
    background: rgba(15, 23, 42, .5);
    backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}
.pos-modal-back.hidden { display: none; }
.pos-modal {
    width: 100%; max-width: 420px;
    background: #fff; border-radius: 14px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, .3);
    overflow: hidden;
    animation: modalIn .25s cubic-bezier(.16,1,.3,1);
}
@keyframes modalIn {
    from { opacity: 0; transform: scale(.95) translateY(10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
.pos-modal-head {
    padding: 14px 18px; border-bottom: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: space-between;
}
.pos-modal-head h3 { font-size: 14px; font-weight: 800; color: #0f172a; }
.pos-modal-close {
    width: 28px; height: 28px; border-radius: 8px;
    background: transparent; color: #94a3b8; border: none;
    cursor: pointer; font-size: 14px;
}
.pos-modal-close:hover { background: #f1f5f9; color: #0f172a; }
.pos-modal-body { padding: 18px; }
.pos-modal-label {
    display: block; font-size: 10px; font-weight: 800; color: #64748b;
    text-transform: uppercase; letter-spacing: .05em;
    margin: 12px 0 4px;
}
.pos-modal-label:first-child { margin-top: 0; }
.pos-modal-input {
    width: 100%; padding: 9px 12px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: 13px; font-weight: 500; color: #0f172a;
    outline: none;
}
.pos-modal-input:focus { border-color: #0f172a; background: #fff; box-shadow: 0 0 0 3px rgba(15,23,42,.06); }
.pos-modal-foot {
    padding: 14px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0;
    display: flex; justify-content: flex-end; gap: 8px;
}

/* ==============================================================
   80mm THERMAL RECEIPT
   ============================================================== */
.pos-receipt-wrap {
    position: relative;
    background: #fff; border-radius: 12px;
    padding: 20px;
    max-width: 340px; width: 100%;
    box-shadow: 0 20px 50px rgba(15, 23, 42, .3);
}
.pos-receipt-close {
    position: absolute; top: -10px; right: -10px;
    width: 28px; height: 28px; border-radius: 50%;
    background: #0f172a; color: #fff; border: 2px solid #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 12px;
    box-shadow: 0 4px 8px rgba(15, 23, 42, .2);
}
.pos-receipt-actions { display: flex; gap: 8px; margin-top: 16px; }

.thermal-receipt {
    max-width: 302px;   /* 80mm at 96dpi ≈ 302px */
    margin: 0 auto;
    padding: 0 4px;
    font-family: 'Courier New', Courier, monospace;
    color: #000;
    background: #fff;
    font-size: 12px;
    line-height: 1.35;
}
.tr-center { text-align: center; }
.tr-hr { border: 0; border-top: 1px dashed #444; margin: 8px 0; }
.tr-row { display: flex; justify-content: space-between; }
.tr-shop-name { font-size: 15px; font-weight: 900; letter-spacing: -0.01em; }
.tr-shop-sub { font-size: 10px; color: #444; margin-top: 2px; }
.tr-table { font-size: 10px; text-transform: uppercase; font-weight: 700; }
.tr-total { font-size: 14px; font-weight: 900; }

/* Print only the receipt in 80mm thermal format. */
@media print {
    @page { 
        size: 80mm auto; 
        margin: 0; 
    }
    html, body {
        width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }
    body * { visibility: hidden !important; }
    #receiptModal, #receiptModal * { visibility: visible !important; }
    #receiptModal {
        position: absolute !important; 
        left: 0 !important; 
        top: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 80mm !important;
        background: #fff !important; 
        display: block !important;
    }
    .pos-receipt-wrap {
        position: relative !important; 
        box-shadow: none !important;
        border-radius: 0 !important; 
        padding: 0 !important; 
        margin: 0 !important;
        width: 100% !important;
        max-width: 80mm !important;
    }
    .pos-receipt-close, .pos-receipt-actions { display: none !important; }
    .thermal-receipt { 
        box-sizing: border-box !important;
        width: 80mm !important;
        max-width: 80mm !important; 
        margin: 0 !important; 
        padding: 4mm !important; 
        font-size: 11px; 
        overflow: hidden !important;
    }
}

/* ==============================================================
   RESPONSIVE
   ============================================================== */
@media (max-width: 1024px) {
    .pos-cart-layout { grid-template-columns: 1fr; overflow-y: auto; }
    .pos-cart-main, .pos-cart-summary { min-height: 0; }
    .pos-cart-summary { position: sticky; bottom: 0; }
    .pos-pay-layout { grid-template-columns: 1fr; overflow-y: auto; }
    .pos-customer-layout { grid-template-columns: 1fr; }
    .pos-pay-side, .pos-pay-main { overflow: visible; }
    .pos-done-layout { grid-template-columns: 1fr; overflow-y: auto; }
    .pos-stepper .pos-step-label { display: none; }
    .pos-top { grid-template-columns: minmax(0, 1fr) auto auto; gap: 12px; }
}
@media (max-width: 640px) {
    .pos-top { grid-template-columns: 1fr; gap: 8px; padding: 10px 14px; }
    .pos-stepper, .pos-cart-btn { justify-self: start; }
    .pos-store-toolbar { flex-wrap: wrap; }
    .pos-cart-thead,
    .pos-cart-row {
        grid-template-columns: minmax(0, 1fr) 90px 40px;
    }
    .pos-cart-thead span:nth-child(3), .pos-cart-thead span:nth-child(4),
    .pos-cart-row .pos-cart-price, .pos-cart-row .pos-cart-total { display: none; }
    .pos-pay-methods { grid-template-columns: 1fr; }
    .pos-quick-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (prefers-reduced-motion: reduce) {
    .pos-panel { transition: none; }
    .pos-done-tick, .pos-done-tick svg path { animation: none; }
    .pos-done-tick svg path { stroke-dashoffset: 0; }
}
</style>

@endsection

@push('scripts')
<script>
    /* ============================================================
       SMART CHECKOUT — front-end
       ============================================================ */

    // In-memory catalogue that the cart reads from. Seeded with the first
    // page of products; topped up as the search endpoint returns more.
    let products = {!! json_encode($products->map(function($p) {
        return [
            'id' => $p->id, 'name' => $p->name,
            'price' => (float) $p->price,
            'stock' => (float) $p->stock_quantity,
            'unit' => $p->unit, 'sku' => $p->sku,
        ];
    })->keyBy('id')) !!};

    let cart = [];
    let currentStep = 1;
    let lastOrder = null;
    let lastChange = 0;
    let activeCategoryId = 'all';

    /* ============================================================
       STEP MACHINE — one panel owns the screen at a time
       ============================================================ */
    const stepPanels = () => document.querySelectorAll('.pos-panel');

    window.goToStep = function (step) {
        currentStep = step;

        stepPanels().forEach(el => {
            const n = Number(el.dataset.panel);
            el.classList.remove('is-active', 'is-next', 'is-previous');
            el.classList.add(n === step ? 'is-active' : (n < step ? 'is-previous' : 'is-next'));
        });

        /*
         * The stepper has fewer visible stages than there are panels
         * (Store + Cart both belong to the "Cart" stage). Each stepper
         * label's data-step is the smallest panel it represents; the
         * active label is the highest data-step that is ≤ current panel.
         */
        const items = Array.from(document.querySelectorAll('.pos-step-item'))
            .sort((a, b) => Number(a.dataset.step) - Number(b.dataset.step));

        let activeStepper = null;
        for (const el of items) {
            if (Number(el.dataset.step) <= step) activeStepper = el;
        }

        items.forEach(el => {
            const n = Number(el.dataset.step);
            el.classList.toggle('is-active', el === activeStepper);
            el.classList.toggle('is-done', activeStepper && n < Number(activeStepper.dataset.step));
        });

        // Populate destination when it becomes visible.
        if (step === 2) renderCartAll();
        if (step === 3) renderReview();
        if (step === 4) enterCustomer();
        if (step === 5) enterPayment();
    };

    window.goToReview = function () {
        if (cart.length === 0) return;
        goToStep(3);
    };

    // Cart → Customer step (was straight to Payment).
    window.goToCustomer = function () {
        if (cart.length === 0) return;
        goToStep(4);
    };

    // Customer → Payment.
    window.goToPayment = function () {
        if (cart.length === 0) return;
        // A customer must be picked before payment can begin, or the sale
        // has no ledger to post to.
        const sel = document.getElementById('pos-customer');
        if (!sel || !sel.value) {
            toast('Select a customer before continuing to payment.', 'error');
            goToStep(4);
            return;
        }
        goToStep(5);
    };

    /* ============================================================
       STORE — search, filters, category, sort
       ============================================================ */
    const searchInput = document.getElementById('pos-search');
    const unitFilter = document.getElementById('pos-unit-filter');
    const sortFilter = document.getElementById('pos-sort-filter');
    const productsGrid = document.getElementById('products-grid');

    let searchTimer = null;
    let searchController = null;

    async function fetchProducts() {
        const params = new URLSearchParams({
            search: searchInput.value.trim(),
            category: activeCategoryId,
            unit: unitFilter.value,
        });

        searchController?.abort();
        searchController = new AbortController();

        const spinner = document.getElementById('pos-search-spinner');
        spinner?.classList.remove('hidden');

        try {
            const res = await Atelier.api.get(
                `{{ route('cloth-store.checkout.products') }}?${params}`,
                { signal: searchController.signal }
            );

            productsGrid.innerHTML = res.html;
            Object.assign(products, res.products || {});
            document.getElementById('no-products').classList.toggle('hidden', res.count !== 0);
            highlightCartProducts();
            applySortClient();
        } catch (err) {
            if (err?.name === 'AbortError') return;
            Atelier.reportError(err, 'Could not load products');
        } finally {
            if (!searchController.signal.aborted) spinner?.classList.add('hidden');
        }
    }

    function queueProductSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(fetchProducts, 250);
    }

    searchInput.addEventListener('input', queueProductSearch);
    unitFilter.addEventListener('change', fetchProducts);
    sortFilter.addEventListener('change', applySortClient);

    // Barcode scanner: exact match resolves to a single product.
    searchInput.addEventListener('keydown', async function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const code = this.value.trim();
        if (!code) return;
        clearTimeout(searchTimer);
        try {
            const res = await Atelier.api.get(
                `{{ route('cloth-store.checkout.scan') }}?code=${encodeURIComponent(code)}`
            );
            products[res.product.id] = res.product;
            addToCart(res.product.id);
            this.value = '';
            fetchProducts();
        } catch {
            fetchProducts();
        }
    });

    document.getElementById('step-store').addEventListener('click', (e) => {
        const pill = e.target.closest('.pos-pill');
        if (!pill) return;
        document.querySelectorAll('.pos-pill').forEach(p => p.classList.remove('is-active'));
        pill.classList.add('is-active');
        activeCategoryId = pill.dataset.cat;
        fetchProducts();
    });

    function applySortClient() {
        const cards = Array.from(productsGrid.querySelectorAll('.pos-product-card'));
        const key = sortFilter.value;
        cards.sort((a, b) => {
            const A = { name: a.dataset.name, price: parseFloat(a.dataset.price) || 0 };
            const B = { name: b.dataset.name, price: parseFloat(b.dataset.price) || 0 };
            if (key === 'name') return A.name.localeCompare(B.name);
            if (key === 'price_asc')  return A.price - B.price;
            if (key === 'price_desc') return B.price - A.price;
            return 0;
        });
        cards.forEach(c => productsGrid.appendChild(c));
    }

    /* ============================================================
       CUSTOMER — database-backed search on Payment step
       ============================================================ */
    const customerSearch = document.getElementById('pos-customer-search');
    const customerSelect = document.getElementById('pos-customer');
    let customerTimer = null;

    // Product search aborts its previous request; customer search did not, so
    // two searches in flight could resolve out of order and leave the dropdown
    // showing matches for an earlier, shorter term than the one in the box.
    // The debounce makes that rare, not impossible — a slow first response is
    // enough. This sequence number ignores any reply that is not the newest.
    let customerSearchSeq = 0;

    async function fetchCustomers() {
        const term = customerSearch.value.trim();
        const seq = ++customerSearchSeq;
        try {
            const list = await Atelier.api.get(
                `{{ route('cloth-store.checkout.customers') }}?search=${encodeURIComponent(term)}`
            );

            // A newer search started while this one was in flight — discard.
            if (seq !== customerSearchSeq) return;

            const previous = customerSelect.value;

            if (!list.length) {
                customerSelect.innerHTML = '<option value="" disabled selected>No customer matches that search</option>';
                customerSelect.dispatchEvent(new Event('change'));
                return;
            }

            customerSelect.innerHTML = list.map(c => `<option value="${c.id}" data-due="${c.due}" data-phone="${Atelier.escapeHtml(c.phone || '')}">${Atelier.escapeHtml(c.name)}${c.phone ? ' (' + Atelier.escapeHtml(c.phone) + ')' : ''}</option>`).join('');
            if (list.some(c => String(c.id) === String(previous))) customerSelect.value = previous;
            customerSelect.dispatchEvent(new Event('change'));
        } catch (err) {
            Atelier.reportError(err, 'Could not search customers');
        }
    }

    customerSearch.addEventListener('input', () => {
        clearTimeout(customerTimer);
        customerTimer = setTimeout(fetchCustomers, 250);
    });
    customerSearch.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(customerTimer);
            fetchCustomers();
        }
    });

    customerSelect.addEventListener('change', function () {
        renderPaymentCustomer();
        const selected = this.options[this.selectedIndex];
        const due = selected ? (parseFloat(selected.getAttribute('data-due')) || 0) : 0;

        // Mirror the due-balance warning to both the Customer step and the
        // Payment step's read-only chip.
        ['', '-2'].forEach(suffix => {
            const warn = document.getElementById('customer-due-warning' + suffix);
            const amt = document.getElementById('customer-due-amt' + suffix);
            if (!warn) return;
            if (selected && due > 0) {
                if (amt) amt.innerText = 'Rs ' + due.toLocaleString();
                warn.classList.remove('hidden');
            } else {
                warn.classList.add('hidden');
            }
        });
    });

    /* Paints the customer chip that appears on both the Customer step
       (`pay-cust-name`) and the Payment step (`pay-cust-name-2`). The two
       ids stay in sync so navigating back and forth never shows a stale
       name; nothing else in the app needs to know they are duplicated. */
    function renderPaymentCustomer() {
        const selected = customerSelect.options[customerSelect.selectedIndex];

        const name = selected ? (selected.text.split('(')[0].trim() || 'Walk-in Customer') : 'Walk-in Customer';
        const phone = selected ? (selected.dataset.phone || '') : '';
        const initials = name.split(/\s+/).filter(Boolean)
            .slice(0, 2).map(w => w[0].toUpperCase()).join('') || '?';

        ['', '-2'].forEach(suffix => {
            const nameEl = document.getElementById('pay-cust-name' + suffix);
            const phoneEl = document.getElementById('pay-cust-phone' + suffix);
            const initEl = document.getElementById('pay-cust-initials' + suffix);
            if (!nameEl) return;
            nameEl.innerText = name;
            phoneEl.innerText = phone || 'No phone';
            initEl.innerText = initials;
        });
    }

    /* Runs when the Customer step becomes visible. Focuses search so the
       cashier can start typing immediately. */
    function enterCustomer() {
        setTimeout(() => document.getElementById('pos-customer-search')?.focus(), 200);
    }

    /* ============================================================
       CART — state + rendering
       ============================================================ */
    window.addToCart = function (id) {
        const p = products[id];
        if (!p) return;
        if (p.stock <= 0) return;

        const existing = cart.find(i => i.id == id && i.unit !== 'meter');
        if (existing) {
            if (existing.qty + 1 > p.stock) {
                toast(`Not enough stock. Only ${p.stock} ${p.unit} available.`, 'error');
                return;
            }
            existing.qty += 1;
        } else {
            cart.unshift({
                cartItemId: 'c_' + Math.random().toString(36).substr(2, 9),
                id: id, name: p.name, price: p.price, qty: 1,
                unit: p.unit, maxStock: p.stock, sku: p.sku,
            });
        }
        updateCartBadge();
        renderCartAll();
        highlightCartProducts();
    };

    window.toggleCutUI = function(id) {
        const ui = document.getElementById('cut-ui-' + id);
        if (ui) {
            ui.classList.toggle('hidden');
            if (!ui.classList.contains('hidden')) {
                const input = document.getElementById('cut-input-' + id);
                if (input) input.focus();
                renderCutUI(id);
            }
        }
    };

    window.addCut = function(id) {
        const p = products[id];
        if (!p) return;
        const input = document.getElementById('cut-input-' + id);
        let qty = parseFloat(input.value);
        if (isNaN(qty) || qty <= 0) {
            toast('Please enter a valid length', 'error');
            return;
        }

        const totalUsed = cart.filter(i => i.id == id).reduce((s, i) => s + i.qty, 0);
        if (totalUsed + qty > p.stock) {
            toast(`Not enough stock. Only ${(p.stock - totalUsed).toFixed(2)}m remaining.`, 'error');
            return;
        }

        cart.unshift({
            cartItemId: 'c_' + Math.random().toString(36).substr(2, 9),
            id: id, name: p.name, price: p.price, qty: qty,
            unit: p.unit, maxStock: p.stock, sku: p.sku,
        });

        input.value = '';
        renderCutUI(id);
        updateCartBadge();
        renderCartAll();
        highlightCartProducts();
    };

    window.removeCut = function(cartItemId, productId) {
        cart = cart.filter(i => i.cartItemId !== cartItemId);
        renderCutUI(productId);
        updateCartBadge();
        renderCartAll();
        highlightCartProducts();
    };

    window.renderCutUI = function(id) {
        const p = products[id];
        if (!p) return;
        const items = cart.filter(i => i.id == id);
        const list = document.getElementById('cut-list-' + id);
        if (list) {
            if (items.length === 0) {
                list.innerHTML = '';
            } else {
                list.innerHTML = items.map(i => `
                    <div class="pos-cut-item">
                        <div class="pos-cut-item-info">
                            <span class="pos-cut-item-qty">${i.qty.toFixed(2)} m</span>
                            <span class="pos-cut-item-amt">@ ${money(i.price)}</span>
                        </div>
                        <i class="fa-solid fa-xmark pos-cut-item-del" onclick="removeCut('${i.cartItemId}', '${id}')" title="Remove cut"></i>
                    </div>
                `).join('');
            }
        }
        
        const totalQty = items.reduce((s, i) => s + i.qty, 0);
        const totalAmt = items.reduce((s, i) => s + (i.price * i.qty), 0);
        const stockRem = p.stock - totalQty;

        const mEl = document.getElementById('cut-total-m-' + id);
        if (mEl) mEl.innerText = totalQty.toFixed(2) + ' m';
        const rsEl = document.getElementById('cut-total-rs-' + id);
        if (rsEl) rsEl.innerText = money(totalAmt);
        const stEl = document.getElementById('cut-stock-' + id);
        if (stEl) stEl.innerText = stockRem.toFixed(2) + ' m';
    };

    window.updateQty = function (cartItemId, qty) {
        const item = cart.find(i => i.cartItemId === cartItemId);
        if (!item) return;
        let n = parseFloat(qty);
        if (isNaN(n) || n <= 0) {
            cart = cart.filter(i => i.cartItemId !== cartItemId);
        } else {
            let maxAvailable = item.maxStock;
            if (item.unit === 'meter') {
                const otherUsed = cart.filter(i => i.id == item.id && i.cartItemId !== cartItemId).reduce((s, i) => s + i.qty, 0);
                maxAvailable = item.maxStock - otherUsed;
            }
            if (n > maxAvailable) { 
                n = maxAvailable; 
                toast(`Only ${maxAvailable} available`, 'warning'); 
            }
            item.qty = n;
        }
        if (item.unit === 'meter') renderCutUI(item.id);
        updateCartBadge();
        renderCartAll();
        highlightCartProducts();
    };

    window.addDecQty = function (cartItemId, delta) {
        const item = cart.find(i => i.cartItemId === cartItemId);
        if (!item) return;
        let n = item.qty + delta;
        if (n < (item.unit === 'meter' ? 0.25 : 1)) n = 0;
        updateQty(cartItemId, n.toFixed(2));
    };

    window.removeFromCart = function (cartItemId) {
        const item = cart.find(i => i.cartItemId === cartItemId);
        if (!item) return;
        const productId = item.id;
        const isMeter = item.unit === 'meter';
        cart = cart.filter(i => i.cartItemId !== cartItemId);
        if (isMeter) renderCutUI(productId);
        updateCartBadge();
        renderCartAll();
        highlightCartProducts();
    };

    window.clearCart = function () {
        if (cart.length === 0) return;
        Atelier.confirmAction({
            title: 'Clear the cart?',
            message: 'All items in the current cart will be removed.',
            confirmLabel: 'Clear Cart',
            danger: true,
            onConfirm: () => {
                cart = [];
                document.getElementById('cart-discount').value = 0;
                if(document.getElementById('cart-offer-select')) document.getElementById('cart-offer-select').value = '';
                updateCartBadge();
                renderCartAll();
                highlightCartProducts();
            },
        });
    };

    function updateCartBadge() {
        const count = cart.length;
        const badge = document.getElementById('pos-cart-count');
        badge.innerText = count;
        badge.style.display = count > 0 ? 'inline-flex' : 'none';
    }

    /* Product-card quantity badges. */
    function highlightCartProducts() {
        document.querySelectorAll('.pos-product-card').forEach(card => {
            card.classList.remove('is-selected');
            const badge = card.querySelector('.pos-card-qty-badge');
            if (badge) badge.innerText = '';
        });
        
        // Group by product ID
        const qtyById = {};
        cart.forEach(item => {
            if (!qtyById[item.id]) {
                qtyById[item.id] = { qty: 0, unit: item.unit };
            }
            qtyById[item.id].qty += item.qty;
        });

        for (const [id, data] of Object.entries(qtyById)) {
            const card = document.querySelector(`.pos-product-card[data-id="${id}"]`);
            if (!card) continue;
            card.classList.add('is-selected');
            const badge = card.querySelector('.pos-card-qty-badge');
            if (badge) badge.innerText = data.unit === 'meter' ? `${data.qty.toFixed(2)}m` : `×${data.qty}`;
        }
    }

    function money(n) {
        return 'Rs ' + Number(n || 0).toLocaleString(undefined, {
            minimumFractionDigits: 0, maximumFractionDigits: 2,
        });
    }

    function cartTotals() {
        const subtotal = cart.reduce((sum, i) => sum + (i.price * i.qty), 0);
        let discountRaw = parseFloat(document.getElementById('cart-discount')?.value) || 0;
        
        const offerSelect = document.getElementById('cart-offer-select');
        if (offerSelect && offerSelect.value !== '') {
            const opt = offerSelect.options[offerSelect.selectedIndex];
            const type = opt.getAttribute('data-type');
            const val = parseFloat(opt.getAttribute('data-value')) || 0;
            if (type === 'percentage') {
                discountRaw = (subtotal * val) / 100;
            } else {
                discountRaw = val;
            }
        }
        
        const discount = Math.min(discountRaw, subtotal);
        const total = subtotal - discount;
        const meters = cart.filter(i => i.unit === 'meter').reduce((sum, i) => sum + i.qty, 0);
        return { subtotal, discount, total, meters };
    }

    /* Renders the cart list, review list, and every dependent summary. */
    window.renderCartAll = function () {
        const { subtotal, discount, total, meters } = cartTotals();
        const items = cart.length;
        const btn = document.getElementById('btn-checkout');
        if (btn) btn.disabled = items === 0;

        // Cart page
        const cartBody = document.getElementById('cart-list-body');
        if (cartBody) {
            cartBody.innerHTML = cart.map(item => cartRow(item, true)).join('');
        }
        document.getElementById('cart-line-count').innerText = `${items} ${items === 1 ? 'item' : 'items'}`;
        document.getElementById('cart-qty-summary').innerText = `${items} / ${meters.toFixed(2)} m`;
        document.getElementById('cart-subtotal').innerText = money(subtotal);
        document.getElementById('cart-grand-total').innerText = money(total);

        // Payment step (Order Total card)
        const sSub = document.getElementById('summary-subtotal');
        if (sSub) sSub.innerText = money(subtotal);
        const sDisc = document.getElementById('summary-discount');
        if (sDisc) sDisc.innerText = '-' + money(discount);
        const sGrand = document.getElementById('summary-grand-total');
        if (sGrand) sGrand.innerText = money(total);
    };

    function renderReview() {
        const { subtotal, discount, total } = cartTotals();
        const body = document.getElementById('review-list-body');
        if (body) body.innerHTML = cart.map(item => cartRow(item, false)).join('');

        document.getElementById('review-item-count').innerText = `${cart.length} ${cart.length === 1 ? 'item' : 'items'}`;
        document.getElementById('review-subtotal').innerText = money(subtotal);
        document.getElementById('review-discount').innerText = '-' + money(discount);
        document.getElementById('review-total').innerText = money(total);
    }

    function enterPayment() {
        renderCartAll();
        renderPaymentCustomer();
        const { total } = cartTotals();
        document.getElementById('paid-amount').value = total;
        calculateChange();
    }

    function cartRow(item, editable) {
        const isMeter = item.unit === 'meter';
        const step = isMeter ? 0.5 : 1;
        const unit = isMeter ? 'm' : 'pc';
        const total = item.price * item.qty;

        const qtyControl = editable ? `
            <div class="pos-qty-ctrl">
              <button class="pos-qty-btn" onclick="addDecQty('${item.cartItemId}', -${step})" aria-label="Decrease">−</button>
              <input class="pos-qty-val cell-num" type="number" step="${step}" min="0" max="${item.maxStock}"
                     value="${item.qty}" onchange="updateQty('${item.cartItemId}', this.value)">
              <span class="pos-qty-unit">${unit}</span>
              <button class="pos-qty-btn" onclick="addDecQty('${item.cartItemId}', ${step})" aria-label="Increase">+</button>
            </div>
        ` : `<div class="cell-num" style="text-align:center;font-weight:700;color:#334155;">${item.qty} ${unit}</div>`;

        return `
            <div class="pos-cart-row" data-cart-id="${item.cartItemId}">
              <div class="pos-cart-prod">
                <div class="pos-cart-thumb"><i class="fa-solid fa-shirt"></i></div>
                <div class="pos-cart-prod-info">
                  <div class="pos-cart-prod-name" title="${Atelier.escapeHtml(item.name)}">${Atelier.escapeHtml(item.name)}</div>
                  <div class="pos-cart-prod-meta">${item.sku ? Atelier.escapeHtml(item.sku) + ' · ' : ''}${isMeter ? 'Meter' : 'Piece'}</div>
                </div>
              </div>
              ${qtyControl}
              <div class="pos-cart-price cell-num">${money(item.price)}</div>
              <div class="pos-cart-total cell-num">${money(total)}</div>
              ${editable ? `<button class="pos-cart-remove" onclick="removeFromCart('${item.cartItemId}')" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button>` : ''}
            </div>
        `;
    }

    /* ============================================================
       AMOUNT / CHANGE
       ============================================================ */
    window.addTender = function (amount) {
        const input = document.getElementById('paid-amount');
        const current = parseFloat(input.value) || 0;
        input.value = current + amount;
        calculateChange();
    };
    window.clearTender = function () {
        document.getElementById('paid-amount').value = 0;
        calculateChange();
    };
    window.setFullPayment = function () {
        const { total } = cartTotals();
        document.getElementById('paid-amount').value = total;
        calculateChange();
    };

    window.calculateChange = function () {
        const { total } = cartTotals();
        const paid = parseFloat(document.getElementById('paid-amount').value) || 0;

        const label = document.getElementById('change-label');
        const amt = document.getElementById('change-amount');
        const hint = document.getElementById('change-hint');
        const row = document.getElementById('pos-change-row');

        if (paid >= total) {
            label.innerText = 'Change Return';
            amt.innerText = money(paid - total);
            amt.style.color = paid > total ? '#059669' : '#0f172a';
            hint.textContent = paid > total ? 'Hand this back to the customer' : 'Exact amount — no change due';
            hint.style.color = '';
            row.classList.toggle('is-paid', paid > total);
            row.classList.remove('is-due');
        } else {
            label.innerText = 'Balance Due';
            amt.innerText = money(total - paid);
            amt.style.color = '#e11d48';
            hint.textContent = 'Will be added to the customer ledger';
            hint.style.color = '#e11d48';
            row.classList.add('is-due');
            row.classList.remove('is-paid');
        }
    };

    /* Payment method selection. */
    document.querySelectorAll('.pay-method').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.pay-method').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('selected-method').value = this.dataset.method;
        });
    });

    /* ============================================================
       SUBMIT — server owns the money
       ============================================================ */
    window.submitCheckout = function () {
        const btn = document.getElementById('btn-submit-order');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        const { subtotal, discount, total } = cartTotals();
        const paid = parseFloat(document.getElementById('paid-amount').value) || 0;
        const method = document.getElementById('selected-method').value;
        const customer_id = document.getElementById('pos-customer').value;

        if (!customer_id) {
            toast('Select a customer before completing the sale.', 'error');
            btn.disabled = false;
            btn.innerHTML = original;
            return;
        }

        const round2 = n => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

        // Server recomputes prices from the DB — we only send ids and qty
        // (plus discount / paid / method). See CheckoutController@store.
        const payload = {
            cs_customer_id: customer_id,
            discount: round2(discount),
            paid_amount: round2(Math.min(paid, total)),
            payment_method: method,
            items: cart.map(i => ({
                cs_product_id: i.id,
                quantity: round2(i.qty),
            })),
        };

        fetch(@json(route('cloth-store.checkout.store')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // A completed sale moves stock, orders, payments and every
                // dashboard figure. This POST goes out via raw fetch, so it
                // never passed through the cache-busting in Atelier.request —
                // without this, a page prefetched on hover before the sale
                // stays servable for the rest of the cache TTL and Stock or
                // Dashboard would render pre-sale numbers.
                //
                // Only the cache is dropped, deliberately: the user stays on
                // the Done screen, and the next navigation re-fetches fresh.
                Atelier.clearPageCache();

                showSuccess(data.order, round2(total), round2(Math.min(paid, total)), paid > total ? round2(paid - total) : 0);
            } else {
                toast(data.message || 'Error processing checkout.', 'error');
                btn.disabled = false;
                btn.innerHTML = original;
            }
        })
        .catch(err => {
            toast('Network error during checkout.', 'error');
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = original;
        });
    };

    /* ============================================================
       DONE step
       ============================================================ */
    function showSuccess(order, total, paidAmount, change) {
        lastOrder = order;
        lastChange = change;

        const selected = customerSelect.options[customerSelect.selectedIndex];
        const cName = order.customer?.name || (selected ? selected.text.split('(')[0].trim() : 'Walk-in Customer');

        document.getElementById('done-invoice').innerText = order.invoice_number;
        document.getElementById('done-customer').innerText = cName;
        document.getElementById('done-total').innerText = money(total);
        document.getElementById('done-paid').innerText = money(paidAmount);
        document.getElementById('done-method').innerText = document.getElementById('selected-method').value;

        // items from the saved order — the local cart is cleared by
        // startNewSale() so we cannot render from it.
        document.getElementById('done-items-body').innerHTML = (order.items || []).map(i => {
            const isMeter = i.product?.unit === 'meter';
            const unit = isMeter ? 'm' : 'pc';
            return `
                <div class="pos-cart-row">
                  <div class="pos-cart-prod">
                    <div class="pos-cart-thumb"><i class="fa-solid fa-shirt"></i></div>
                    <div class="pos-cart-prod-info">
                      <div class="pos-cart-prod-name">${Atelier.escapeHtml(i.product?.name || 'Item')}</div>
                      <div class="pos-cart-prod-meta">${isMeter ? 'Meter' : 'Piece'}</div>
                    </div>
                  </div>
                  <div class="cell-num" style="text-align:center;font-weight:700;color:#334155;">${Number(i.quantity)} ${unit}</div>
                  <div class="pos-cart-price cell-num">${money(i.unit_price)}</div>
                  <div class="pos-cart-total cell-num">${money(i.total)}</div>
                </div>`;
        }).join('');

        // Change vs balance due.
        const owing = Math.max(0, total - paidAmount);
        const balCard = document.getElementById('done-balance-kv');
        const balLabel = document.getElementById('done-balance-label');
        const balVal = document.getElementById('done-balance');
        if (owing > 0) {
            balLabel.innerText = 'Balance Due';
            balVal.innerText = money(owing);
            balCard.classList.add('is-due');
        } else {
            balLabel.innerText = 'Change';
            balVal.innerText = money(change);
            balCard.classList.remove('is-due');
        }

        // Done is now panel 6 (Customer step pushed Payment to 5, Done to 6).
        goToStep(6);
        Atelier.playChime?.();
        toast('Sale completed successfully!', 'success');

        // Automatically open the 80mm receipt print dialog
        setTimeout(() => {
            if (lastOrder) {
                buildReceiptHtml();
                const modal = document.getElementById('receiptModal');
                modal.classList.remove('hidden');
                
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        window.print();
                        // Hide the modal once the print dialog is closed
                        modal.classList.add('hidden');
                    }, 60);
                });
            }
        }, 400); // slight delay to allow the "Done" screen to paint first
    }

    window.startNewSale = function () {
        cart = [];
        lastOrder = null;
        lastChange = 0;
        document.getElementById('cart-discount').value = 0;
        if(document.getElementById('cart-offer-select')) document.getElementById('cart-offer-select').value = '';
        document.getElementById('paid-amount').value = 0;
        document.getElementById('pos-search').value = '';
        const submitBtn = document.getElementById('btn-submit-order');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Complete Sale';

        updateCartBadge();
        renderCartAll();
        highlightCartProducts();
        goToStep(1);
        fetchProducts();
        setTimeout(() => document.getElementById('pos-search').focus(), 200);
    };

    document.addEventListener('keydown', (e) => {
        // "N" for New Sale is only relevant on the Done screen (panel 6).
        if (currentStep !== 6) return;
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === 'n' || e.key === 'N') {
            e.preventDefault();
            startNewSale();
        }
    }, { signal: Atelier.pageSignal() });

    /* ============================================================
       ADD CUSTOMER modal
       ============================================================ */
    /* Shared save-customer implementation. `opts.form` is the form to reset
       on success; `opts.button` is the busy button; `opts.close` optionally
       hides a modal. Both submitNewCustomer (legacy modal) and
       submitInlineCustomer (Customer step form) delegate here. */
    function saveNewCustomer({ payload, button, form, close }) {
        const original = button.innerHTML;
        Atelier.setBusy(button, true);

        return fetch(@json(route('cloth-store.customers.quick')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(res => res.json().then(body => ({ ok: res.ok, body })))
        .then(({ ok, body }) => {
            if (!ok || !body.success) {
                toast(body.message || 'Could not add customer', 'error');
                return;
            }
            toast('Customer added', 'success');

            // Raw fetch again, so drop the cache — otherwise the Customers page
            // can still be served from a copy prefetched before this insert.
            // The dropdown itself is patched in place below rather than
            // re-fetched, so the cashier never loses their place in the sale.
            Atelier.clearPageCache();

            const opt = document.createElement('option');
            opt.value = body.customer.id;
            opt.dataset.due = body.customer.due_balance || 0;
            opt.dataset.phone = body.customer.phone || '';
            opt.text = body.customer.name + (body.customer.phone ? ' (' + body.customer.phone + ')' : '');
            customerSelect.appendChild(opt);
            customerSelect.value = body.customer.id;
            customerSelect.dispatchEvent(new Event('change'));

            if (close) close();
            if (form) form.reset();
        })
        .catch(() => toast('Network error while adding customer', 'error'))
        .finally(() => { Atelier.setBusy(button, false); button.innerHTML = original; });
    }

    /* Legacy modal — still wired up in case a future page opens it. */
    window.submitNewCustomer = function (e) {
        e.preventDefault();
        saveNewCustomer({
            payload: {
                name: document.getElementById('qc-name').value,
                phone: document.getElementById('qc-phone').value,
                city: document.getElementById('qc-city').value,
            },
            button: document.getElementById('btn-save-customer'),
            form: document.getElementById('quick-customer-form'),
            close: () => document.getElementById('new-customer-modal').classList.add('hidden'),
        });
    };

    /* Inline form on the Customer step. Saves the customer and auto-selects
       it — the change handler above then paints the chip on both step 3
       and step 4, so no reload is needed. */
    window.submitInlineCustomer = function (e) {
        e.preventDefault();
        saveNewCustomer({
            payload: {
                name: document.getElementById('ic-name').value,
                phone: document.getElementById('ic-phone').value,
                address: document.getElementById('ic-address').value,
            },
            button: document.getElementById('btn-inline-customer'),
            form: document.getElementById('inline-customer-form'),
        });
    };

    /* ============================================================
       80mm THERMAL RECEIPT
       ============================================================ */
    window.printReceipt = function () {
        if (!lastOrder) return;
        buildReceiptHtml();
        document.getElementById('receiptModal').classList.remove('hidden');
        // Wait for the modal to paint before triggering print.
        requestAnimationFrame(() => setTimeout(() => window.print(), 60));
    };
    window.showLastReceipt = function () {
        if (!lastOrder) return;
        buildReceiptHtml();
        document.getElementById('receiptModal').classList.remove('hidden');
    };
    window.closeReceipt = function () {
        document.getElementById('receiptModal').classList.add('hidden');
    };

    function buildReceiptHtml() {
        const o = lastOrder;
        const shop = Atelier.shop || {};
        const selected = customerSelect.options[customerSelect.selectedIndex];
        const cName = o.customer?.name || (selected ? selected.text.split('(')[0].trim() : 'Walk-in Customer');

        const paid = Number(o.paid_amount) + lastChange;

        const itemsHtml = (o.items || []).map(i => `
            <div class="tr-row" style="margin-bottom:2px;">
              <div style="flex:1;text-align:left;padding-right:4px;">${Atelier.escapeHtml(i.product?.name || 'Item')}</div>
              <div style="width:38px;text-align:center;">${Number(i.quantity)}${i.product?.unit === 'meter' ? 'm' : ''}</div>
              <div style="width:60px;text-align:right;">${Number(i.total).toLocaleString()}</div>
            </div>
        `).join('');

        document.getElementById('receipt-content').innerHTML = `
            <div class="tr-center">
                <div class="tr-shop-name">${Atelier.escapeHtml(shop.name || 'Cloth Store')}</div>
                ${shop.tagline ? `<div class="tr-shop-sub">${Atelier.escapeHtml(shop.tagline)}</div>` : ''}
                ${shop.address ? `<div class="tr-shop-sub">${Atelier.escapeHtml(shop.address)}</div>` : ''}
                ${shop.phone ? `<div class="tr-shop-sub">Ph: ${Atelier.escapeHtml(shop.phone)}</div>` : ''}
            </div>
            <hr class="tr-hr">
            <div style="font-size:11px;">
                <div class="tr-row"><span>Invoice</span><strong>${o.invoice_number}</strong></div>
                <div class="tr-row"><span>Date</span><span>${new Date().toLocaleString()}</span></div>
                <div class="tr-row"><span>Customer</span><strong>${Atelier.escapeHtml(cName)}</strong></div>
            </div>
            <hr class="tr-hr">
            <div class="tr-table tr-row" style="border-bottom:1px solid #000;padding-bottom:3px;margin-bottom:5px;">
                <div style="flex:1;text-align:left;">Item</div>
                <div style="width:38px;text-align:center;">Qty</div>
                <div style="width:60px;text-align:right;">Total</div>
            </div>
            <div>${itemsHtml}</div>
            <hr class="tr-hr">
            <div style="font-size:11px;">
                <div class="tr-row"><span>Subtotal</span><span>Rs ${Number(o.subtotal).toLocaleString()}</span></div>
                ${Number(o.discount) > 0 ? `<div class="tr-row"><span>Discount</span><span>-Rs ${Number(o.discount).toLocaleString()}</span></div>` : ''}
                <div class="tr-row tr-total" style="border-top:1px solid #000;margin-top:4px;padding-top:4px;">
                    <span>TOTAL</span><span>Rs ${Number(o.total_amount).toLocaleString()}</span>
                </div>
            </div>

            <hr class="tr-hr" style="margin-top:12px; margin-bottom:6px;">
            <div class="tr-center" style="line-height:1.2;">
                <div style="font-size:9px; text-transform:uppercase; color:#555;">Developed By</div>
                <div style="font-size:13px; font-weight:bold; margin-top:2px; color:#000;">NOOR M HINGORJO</div>
                <div style="font-size:11px; color:#000; margin-top:1px;">0303 4980786</div>
                <div style="font-size:9px; color:#555; margin-top:3px;">POS & MANAGEMENT SYSTEM</div>
                <div style="font-size:11px; font-weight:bold; margin-top:5px; color:#000;">THANK YOU!</div>
            </div>
            <hr class="tr-hr" style="margin-top:6px; margin-bottom:0;">
        `;
    }

    /* ============================================================
       INIT
       ============================================================ */
    updateCartBadge();
    renderCartAll();
    highlightCartProducts();
</script>
@endpush
