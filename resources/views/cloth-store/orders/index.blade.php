@extends('cloth-store.layouts.app')
@section('title', 'Orders Management')
@section('spaPage', 'cloth-store-orders')

@section('content')
@php $filtered = request()->anyFilled(['search', 'status', 'payment_method', 'date']); @endphp

<x-cloth-store.page-header
  title="Orders"
  :subtitle="number_format($stats['total_orders']) . ' orders on record · manage and track customer sales'">
  <x-slot:actions>
    <button class="btn-cs-ghost" onclick="window.print()">
      <i class="fa-solid fa-print text-[10px]"></i> Print
    </button>
    <a href="{{ route('cloth-store.checkout.index') }}" class="btn-cs-primary">
      <i class="fa-solid fa-plus text-[10px]"></i> New Sale
    </a>
  </x-slot:actions>
</x-cloth-store.page-header>

<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <x-cloth-store.stat-card
    label="Today's Sales" icon="fa-rupee-sign" tone="emerald"
    :value="'Rs ' . number_format($stats['today_sales'])"
    :sub="$stats['today_count'] . ' ' . \Illuminate\Support\Str::plural('transaction', $stats['today_count']) . ' today'" />

  <x-cloth-store.stat-card
    label="Meters Sold Today" icon="fa-ruler-horizontal" tone="sky"
    :value="rtrim(rtrim(number_format($stats['today_meters'], 2), '0'), '.') . ' m'"
    sub="Fabric moved today" />

  @php
    // Built in PHP: a bound attribute is passed through without HTML-decoding,
    // so &quot; entities written inline would render literally.
    $outstandingSub = $stats['outstanding'] > 0
        ? '<span class="text-red-500 font-semibold">Awaiting collection</span>'
        : 'All invoices settled';
  @endphp
  <x-cloth-store.stat-card
    label="Outstanding" icon="fa-circle-exclamation" :tone="$stats['outstanding'] > 0 ? 'red' : 'slate'"
    :value="'Rs ' . number_format($stats['outstanding'])"
    :sub="$outstandingSub" />

  <x-cloth-store.stat-card
    label="Total Orders" icon="fa-receipt" tone="indigo"
    :value="number_format($stats['total_orders'])"
    sub="All time" />
</div>

<x-cloth-store.panel :flush="false" class="mb-6">
    <form data-filter-form method="GET" action="{{ route('cloth-store.orders.index') }}" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[220px]">
            <label class="label-cs">Search</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" value="{{ request('search') }}" autocomplete="off"
                       placeholder="Invoice, customer or phone..." class="input-cs w-full pl-9">
            </div>
        </div>

        <div class="w-44">
            <label class="label-cs">Status</label>
            <select name="status" class="input-cs w-full">
                <option value="">All Statuses</option>
                @foreach(['Completed', 'Pending', 'Processing', 'Ready', 'Cancelled', 'Returned'] as $s)
                    <option value="{{ $s }}" @selected(request('status') == $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-44">
            <label class="label-cs">Payment</label>
            <select name="payment_method" class="input-cs w-full">
                <option value="">All Methods</option>
                @foreach(['Cash', 'Card', 'Bank Transfer', 'EasyPaisa', 'JazzCash', 'Cheque'] as $m)
                    <option value="{{ $m }}" @selected(request('payment_method') == $m)>{{ $m }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-40">
            <label class="label-cs">Date</label>
            <select name="date" class="input-cs w-full">
                <option value="">All Time</option>
                <option value="today" @selected(request('date') == 'today')>Today</option>
                <option value="week"  @selected(request('date') == 'week')>This Week</option>
                <option value="month" @selected(request('date') == 'month')>This Month</option>
            </select>
        </div>

        @if($filtered)
            <a href="{{ route('cloth-store.orders.index') }}" class="btn-cs-ghost">
                <i class="fa-solid fa-xmark text-[10px]"></i> Clear
            </a>
        @endif
    </form>
</x-cloth-store.panel>

<x-cloth-store.panel>
    <table class="table-cs">
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Customer</th>
                <th class="text-center">Items</th>
                <th class="text-right">Meters</th>
                <th class="text-right">Total</th>
                <th>Payment</th>
                <th class="text-center">Status</th>
                <th class="text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $o)
                @php
                    $due = (float) $o->total_amount - (float) $o->paid_amount;
                    $isCancelled = in_array($o->status, ['Cancelled', 'Returned'], true);

                    $badge = match ($o->status) {
                        'Completed'             => 'badge-delivered',
                        'Cancelled', 'Returned' => 'badge-overdue',
                        'Processing', 'Ready'   => 'badge-progress',
                        default                 => 'badge-pending',
                    };

                    $name = $o->customer->name ?? 'Walk-in Customer';
                    $initials = collect(preg_split('/\s+/', trim($name)))
                        ->filter()->take(2)
                        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                        ->implode('') ?: '?';

                    $meters = (float) $o->total_meters_sold;
                @endphp
                <tr>
                    <td>
                        <button onclick="viewOrder({{ $o->id }})"
                                class="cell-strong hover:text-indigo-600 transition-colors text-left">
                            {{ $o->invoice_number }}
                        </button>
                        <div class="cell-muted">{{ $o->created_at->format('d M Y · h:i A') }}</div>
                    </td>

                    <td>
                        <div class="flex items-center gap-2.5">
                            <div class="avatar sm shrink-0">{{ $initials }}</div>
                            <div class="min-w-0">
                                <div class="cell-strong truncate">{{ $name }}</div>
                                <div class="cell-muted truncate">{{ $o->customer->phone ?? '—' }}</div>
                            </div>
                        </div>
                    </td>

                    <td class="text-center">
                        <span class="inline-flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full bg-slate-100 text-xs font-bold text-slate-600 cell-num">
                            {{ $o->items->count() }}
                        </span>
                    </td>

                    <td class="text-right cell-num text-slate-600 font-medium">
                        {{ rtrim(rtrim(number_format($meters, 2), '0'), '.') }} m
                    </td>

                    <td class="text-right">
                        <div class="cell-strong cell-num">Rs {{ number_format((float) $o->total_amount) }}</div>
                        @if($due > 0 && !$isCancelled)
                            <div class="text-[10px] font-bold text-red-500 uppercase tracking-wide cell-num">
                                Due Rs {{ number_format($due) }}
                            </div>
                        @elseif(!$isCancelled)
                            <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-wide">Paid</div>
                        @endif
                    </td>

                    <td>
                        <span class="badge badge-neutral">{{ $o->payment_method ?: '—' }}</span>
                    </td>

                    <td class="text-center">
                        <span class="badge {{ $badge }}">{{ $o->status }}</span>
                    </td>

                    <td>
                        <div class="flex items-center justify-end gap-1 row-actions">
                            <button onclick="viewOrder({{ $o->id }})" class="btn-cs-icon" title="View details" aria-label="View {{ $o->invoice_number }}">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </button>
                            <button onclick="printReceipt({{ $o->id }})" class="btn-cs-icon" title="Print receipt" aria-label="Print {{ $o->invoice_number }}">
                                <i class="fa-solid fa-print text-xs"></i>
                            </button>
                            {{-- Status changes move stock, so they go through the API
                                 and report success or failure rather than posting a
                                 form and reloading the page. --}}
                            <select data-order-status data-id="{{ $o->id }}"
                                    aria-label="Update status for {{ $o->invoice_number }}"
                                    class="text-xs rounded-lg py-1 pl-2 pr-6 bg-slate-50 border border-slate-200 hover:bg-white text-slate-600 font-medium focus:ring-0 focus:border-slate-400 cursor-pointer">
                                <option value="" disabled selected>Update</option>
                                @foreach(['Pending', 'Processing', 'Ready', 'Completed', 'Cancelled'] as $s)
                                    <option value="{{ $s }}" @disabled($o->status === $s)>{{ $s === 'Cancelled' ? 'Cancel' : $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    </td>
                </tr>
            @empty
                <x-cloth-store.empty-state
                    :colspan="8"
                    icon="fa-cart-shopping"
                    :title="$filtered ? 'No orders match those filters' : 'No orders yet'"
                    :message="$filtered
                        ? 'Try widening your search or clearing the filters.'
                        : 'Completed sales from Smart Checkout will appear here.'" />
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <x-cloth-store.pagination :paginator="$orders" noun="order" />
    </x-slot:footer>
</x-cloth-store.panel>

<!-- ORDER VIEW MODAL -->
<div id="orderModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm">
    <div class="min-h-screen flex items-center justify-center p-4 sm:p-6">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl overflow-hidden flex flex-col">
            
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-white">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    Order Details
                </h3>
                <button onclick="document.getElementById('orderModal').classList.add('hidden')" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="p-6 bg-slate-50/50">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    
                    <!-- Left Column: Details & Items -->
                    <div class="lg:col-span-3 flex flex-col gap-6">
                        
                        <!-- Top Info Row -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Invoice Card -->
                            <div class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex flex-col justify-center">
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Invoice Number</div>
                                <div class="flex items-center gap-3">
                                    <div class="text-2xl font-black text-slate-900 tracking-tight" id="modal-inv">INV-XXX</div>
                                    <div id="modal-status-badge"></div>
                                </div>
                                <div class="mt-3 text-sm text-slate-500 font-medium flex items-center" id="modal-date">
                                    <i class="fa-regular fa-calendar mr-1"></i> Date
                                </div>
                            </div>

                            <!-- Customer Card -->
                            <div class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg shrink-0">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Customer</div>
                                    <div class="font-bold text-slate-800 text-lg truncate" id="modal-customer-name">Name</div>
                                    <div class="text-sm text-slate-500 truncate mt-0.5" id="modal-customer-phone">Phone</div>
                                </div>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                            <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
                                <h4 class="text-sm font-bold text-slate-800">Itemized Bill</h4>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm border-collapse">
                                    <thead>
                                        <tr class="bg-white border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider">
                                            <th class="py-3 px-5 font-semibold">Product</th>
                                            <th class="py-3 px-5 font-semibold text-center w-28">Qty/Mtr</th>
                                            <th class="py-3 px-5 font-semibold text-right w-32">Unit Rate</th>
                                            <th class="py-3 px-5 font-semibold text-right w-32">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modal-items-list" class="divide-y divide-slate-50">
                                        <!-- JS Injected -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Financials -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 sticky top-6">
                            <h4 class="text-sm font-bold text-slate-800 mb-4 pb-3 border-b border-slate-100">Payment Summary</h4>
                            
                            <div class="space-y-3.5">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-slate-500">Subtotal</span>
                                    <span class="font-medium text-slate-900" id="modal-subtotal">Rs 0</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-slate-500">Discount</span>
                                    <span class="font-medium text-rose-600" id="modal-discount">Rs 0</span>
                                </div>
                                
                                <div class="pt-4 mt-2 border-t border-slate-100">
                                    <div class="flex justify-between items-end mb-1">
                                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Amount</span>
                                        <span class="text-xl font-black text-indigo-600" id="modal-total">Rs 0</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-slate-100 space-y-3">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-slate-500">Paid (<span id="modal-method" class="font-medium text-slate-700">Cash</span>)</span>
                                        <span class="font-bold text-emerald-600" id="modal-paid">Rs 0</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm p-3 rounded-lg bg-rose-50 border border-rose-100/50">
                                        <span class="text-rose-600 font-semibold">Remaining Due</span>
                                        <span class="font-bold text-rose-700 text-base" id="modal-due">Rs 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="px-6 py-4 bg-white border-t border-slate-100 flex justify-end gap-3">
                <button onclick="document.getElementById('orderModal').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors">Close</button>
                <button id="modal-print-btn" class="px-5 py-2.5 rounded-xl text-sm font-bold bg-indigo-600 text-white hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-200 flex items-center gap-2">
                    <i class="fa-solid fa-print text-indigo-100"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- THERMAL RECEIPT MODAL (Shared logic) -->
<div id="receiptModal" class="hidden fixed inset-0 z-[60] bg-slate-900/80 backdrop-blur-sm overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-[360px] relative">
            <button onclick="document.getElementById('receiptModal').classList.add('hidden')" class="absolute -top-3 -right-3 w-8 h-8 bg-slate-800 text-white rounded-full flex items-center justify-center hover:bg-slate-900 border-2 border-white"><i class="fa-solid fa-xmark text-sm"></i></button>
            
            <div id="receipt-content" class="thermal-receipt mb-6 text-left">
                <!-- Injected via JS -->
            </div>

            <div class="flex gap-2">
                <button onclick="printClothThermalDirect()" class="flex-1 bg-indigo-600 text-white py-2.5 rounded-lg font-bold hover:bg-indigo-700 text-sm"><i class="fa-solid fa-print mr-1"></i> Print Receipt</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Dedicated 80mm thermal print mode — same architecture as checkout page. */
@include('receipts.slip-styles')
@include('cloth-store.receipts.simple-styles')

@media print {
    @page clothThermal80 { size: auto; margin: 0; }

    html.printing-cloth-thermal,
    html.printing-cloth-thermal body {
        page: clothThermal80;
        width: 72mm !important;
        min-width: 0 !important;
        max-width: 72mm !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        margin: 0 !important;
        padding: 0 !important;
        display: block !important;
        position: static !important;
        overflow: visible !important;
        transform: none !important;
        zoom: 1 !important;
        background: #fff !important;
    }

    html.printing-cloth-thermal body > :not(#thermal-print-area) { display: none !important; }
    html.printing-cloth-thermal body * { visibility: hidden; }

    #thermal-print-area, #thermal-print-area * { visibility: visible; }

    #thermal-print-area {
        position: static; width: 100%; max-width: 100%; min-width: 0;
        height: auto; max-height: none; overflow: visible;
        margin: 0; padding: 0; transform: none; zoom: 1;
        background: #fff !important;
        display: block !important;
    }

    #thermal-print-area .slip {
        box-sizing: border-box; width: 100%; max-width: 100%; min-width: 0;
        margin: 0;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 2mm 2mm 6mm;
        font-weight: 600;
    }
}
</style>
@endsection

@push('scripts')
<script>
    /* Order status changes: async, with the row refreshed in place.
       A rejected change (e.g. not enough stock to reinstate a cancelled
       order) must leave the select showing the real status, not the one the
       user picked — so the control is reset on failure. */
    document.addEventListener('change', async (e) => {
        const select = e.target.closest?.('[data-order-status]');
        if (!select || !select.value) return;


        const id = select.dataset.id;
        const status = select.value;
        select.disabled = true;

        try {
            const res = await Atelier.api.post(`/cloth-store/orders/${id}/status`, { status });
            toast(res.message || 'Order updated', 'success');
            // Re-render the current page so the badge, totals and any stock
            // dependent columns reflect the change.
            await SpaRouter.navigate(location.href, { push: false, scroll: false });
        } catch (err) {
            Atelier.reportError(err, 'Could not update the order');
            select.selectedIndex = 0;
            select.disabled = false;
        }
    }, { signal: Atelier.pageSignal() });

    let currentOrder = null;

    window.viewOrder = function(id) {
        fetch(`{{ url('cloth-store/orders') }}/${id}`)
            .then(res => res.json())
            .then(order => {
                currentOrder = order;
                document.getElementById('modal-inv').innerText = order.invoice_number;
                document.getElementById('modal-date').innerHTML = `<i class="fa-regular fa-calendar mr-1"></i> ` + new Date(order.created_at).toLocaleString();
                
                let bClass = 'badge-pending';
                if(order.status == 'Completed') bClass = 'badge-delivered';
                if(order.status == 'Cancelled' || order.status == 'Returned') bClass = 'badge-overdue';
                document.getElementById('modal-status-badge').innerHTML = `<span class="badge ${bClass}">${order.status}</span>`;

                document.getElementById('modal-customer-name').innerText = order.customer ? order.customer.name : 'Walk-in Customer';
                document.getElementById('modal-customer-phone').innerText = order.customer ? (order.customer.phone || '') : '';

                let itemsHtml = order.items.map(i => `
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="py-3 px-5 text-slate-800 font-medium">${i.product ? i.product.name : 'Unknown Product'}</td>
                        <td class="py-3 px-5 text-center text-slate-600">${Number(i.quantity)}</td>
                        <td class="py-3 px-5 text-right text-slate-500">Rs ${Number(i.unit_price).toLocaleString()}</td>
                        <td class="py-3 px-5 text-right font-bold text-slate-900">Rs ${Number(i.total).toLocaleString()}</td>
                    </tr>
                `).join('');
                document.getElementById('modal-items-list').innerHTML = itemsHtml;

                document.getElementById('modal-subtotal').innerText = 'Rs ' + Number(order.subtotal).toLocaleString();
                document.getElementById('modal-discount').innerText = 'Rs ' + Number(order.discount).toLocaleString();
                document.getElementById('modal-total').innerText = 'Rs ' + Number(order.total_amount).toLocaleString();
                document.getElementById('modal-method').innerText = order.payment_method;
                document.getElementById('modal-paid').innerText = 'Rs ' + Number(order.paid_amount).toLocaleString();
                
                let due = Number(order.total_amount) - Number(order.paid_amount);
                if (due < 0) due = 0;
                document.getElementById('modal-due').innerText = 'Rs ' + due.toLocaleString();

                document.getElementById('modal-print-btn').onclick = () => { printReceipt(order.id, order); };
                
                document.getElementById('orderModal').classList.remove('hidden');
            });
    };

    /* Thermal print helper — toggles class, prints, cleans up. */
    function printClothThermalDirect() {
        const sourceSlip = document.querySelector('#receipt-content .slip');
        if (!sourceSlip) return;

        // Clean up any old print area
        let oldArea = document.getElementById('thermal-print-area');
        if (oldArea) oldArea.remove();

        const area = document.createElement('div');
        area.id = 'thermal-print-area';
        area.hidden = true;

        const copy = sourceSlip.cloneNode(true);
        copy.classList.remove('slip-preview'); // don't need drop shadow in print
        area.appendChild(copy);
        
        document.body.appendChild(area);

        const cleanup = () => {
            document.documentElement.classList.remove('printing-cloth-thermal');
            const a = document.getElementById('thermal-print-area');
            if (a) a.remove();
        };
        window.addEventListener('afterprint', cleanup, { once: true });

        requestAnimationFrame(() => setTimeout(() => {
            try {
                area.hidden = false;
                document.documentElement.classList.add('printing-cloth-thermal');
                window.print();
            } finally {
                cleanup();
            }
        }, 60));
    }

    /* Called from the Print Receipt button inside the receipt modal. */
    window.printClothThermalDirect = printClothThermalDirect;

    window.printReceipt = function(id, orderData = null) {
        const modal = document.getElementById('receiptModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        if (orderData) {
            renderReceipt(orderData);
        } else {
            fetch(`{{ url('cloth-store/orders') }}/${id}`)
                .then(res => res.json())
                .then(order => {
                    renderReceipt(order);
                });
        }
    };

    function formatReceiptQty(item) {
        const quantity = Number(item?.quantity || 0);
        const clean = Number.isInteger(quantity)
            ? String(quantity)
            : quantity.toFixed(2).replace(/\.?0+$/, '');

        return item?.product?.unit === 'meter'
            ? `${clean}m`
            : clean;
    }

    function formatReceiptDate(value) {
        const date = value ? new Date(value) : new Date();

        if (Number.isNaN(date.getTime())) {
            return '';
        }

        return date.toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    }

    function renderReceipt(order) {
        const shop = Atelier.shop || {};
        const escapeHtml = value => window.Atelier?.escapeHtml
            ? window.Atelier.escapeHtml(value ?? '')
            : String(value ?? '');

        const customerName = order?.customer?.name || 'Walk-in Customer';

        const itemsHtml = (order?.items || []).map(item => {
            const qtyText = formatReceiptQty(item);
            const unitPrice = Number(item?.unit_price || item?.price || (item?.total / item?.quantity) || 0);
            const lineTotal = Number(item?.total || 0);
            const productName = item?.product?.name || item?.name || 'Item';

            return `
                <div class="slip-row">
                    <span class="k">${escapeHtml(productName)} × ${escapeHtml(qtyText)}</span>
                    <span class="v">Rs ${lineTotal.toLocaleString()}</span>
                </div>
                <div class="slip-sub">Unit Price: Rs ${unitPrice.toLocaleString()}</div>
            `;
        }).join('');

        const html = `
            <div class="slip slip-preview cloth-receipt">
                <div class="slip-hd">
                    <div class="slip-shop">${escapeHtml(shop.name || 'Cloth Store')}</div>
                    ${shop.tagline ? `<div class="slip-tag">${escapeHtml(shop.tagline)}</div>` : ''}
                    <div class="slip-meta">
                        ${shop.address ? `<div>${escapeHtml(shop.address)}</div>` : ''}
                        ${shop.phone ? `<div>${escapeHtml(shop.phone)}</div>` : ''}
                    </div>
                </div>

                <div class="cr-receipt-title">SALES RECEIPT</div>

                <div class="slip-row"><span class="k">Invoice</span><span class="v slip-bold">${escapeHtml(order?.invoice_number || '')}</span></div>
                <div class="slip-row"><span class="k">Date</span><span class="v">${escapeHtml(formatReceiptDate(order?.created_at))}</span></div>
                <div class="slip-row"><span class="k">Customer</span><span class="v slip-bold">${escapeHtml(customerName)}</span></div>

                <div class="slip-rule"></div>

                <div class="cr-item-head"><span>DESCRIPTION / QUANTITY</span><span>AMOUNT</span></div>
                ${itemsHtml || `<div class="slip-row"><span class="k">No items</span><span class="v"></span></div>`}

                <div class="slip-rule"></div>

                <div class="slip-row"><span class="k">Subtotal</span><span class="v">Rs ${Number(order?.subtotal || 0).toLocaleString()}</span></div>
                ${Number(order?.discount || 0) > 0 ? `
                    <div class="slip-row"><span class="k">Discount</span><span class="v">-Rs ${Number(order.discount).toLocaleString()}</span></div>
                ` : ''}

                <div class="slip-rule-s"></div>
                <div class="slip-total"><span>TOTAL</span><span>Rs ${Number(order?.total_amount || 0).toLocaleString()}</span></div>
                <div class="slip-rule-d"></div>

                ${order?.payment_method ? `
                    <div class="slip-row"><span class="k">Payment Method</span><span class="v">${escapeHtml(order.payment_method)}</span></div>
                ` : ''}

                <div class="slip-foot" style="margin-top:2mm">
                    Thank you for shopping with us.<br>
                    We look forward to serving you again.
                </div>

                <div class="slip-rule" style="margin-top:4mm"></div>
                <div class="slip-credit slip-credit-customer">
                    <div>Designed &amp; Developed by <span class="name">Noor M Hingorjo</span></div>
                    <div class="sys">POS &amp; MANAGEMENT SYSTEM</div>
                    <div class="tel">0303 4980786</div>
                    <div class="ty">Thank You!</div>
                </div>
            </div>
        `;
        
        document.getElementById('receipt-content').innerHTML = html;
        document.getElementById('receiptModal').classList.remove('hidden');
        document.getElementById('orderModal').classList.add('hidden'); // Close the other one if open
    }
</script>
@endpush
