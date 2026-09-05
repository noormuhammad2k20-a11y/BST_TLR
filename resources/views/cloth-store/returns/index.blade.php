@extends('cloth-store.layouts.app')
@section('title', 'Returns & Exchanges')
@section('spaPage', 'cloth-store-returns')

@section('content')
<x-cloth-store.page-header
    title="Returns &amp; Exchanges"
    subtitle="Manage customer returns, exchanges and refunds">
    <x-slot:actions>
        <button onclick="openReturnModal()" class="btn-cs-primary">
            <i class="fa-solid fa-arrow-rotate-left text-[10px]"></i> Create Return
        </button>
    </x-slot:actions>
</x-cloth-store.page-header>

<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-cloth-store.stat-card
        label="Total Returns" icon="fa-boxes-stacked" tone="slate"
        :value="number_format($totalReturns)" sub="All time" />

    <x-cloth-store.stat-card
        label="Pending Review" icon="fa-clock-rotate-left" :tone="$pending ? 'amber' : 'slate'"
        :value="number_format($pending)"
        :sub="$pending ? 'Awaiting a decision' : 'Nothing waiting'" />

    <x-cloth-store.stat-card
        label="Exchanges" icon="fa-right-left" tone="sky"
        :value="number_format($exchangesCount)" sub="Swapped rather than refunded" />

    <x-cloth-store.stat-card
        label="Refunded" icon="fa-money-bill-transfer" tone="emerald"
        :value="'Rs ' . number_format($totalRefunded)" sub="Returned to customers" />
</div>

<x-cloth-store.panel class="mb-8">
    <x-slot:toolbar>
        <form data-filter-form id="returns-filter" method="GET" action="{{ route('cloth-store.returns.index') }}" class="flex flex-wrap gap-3 items-center w-full">
            <div class="relative flex-1 min-w-[240px] max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" id="return-search" value="{{ request('search') }}" autocomplete="off"
                       placeholder="Search return ID, invoice or customer..." class="input-cs w-full pl-9">
            </div>
            <select name="status" class="input-cs w-44">
                <option value="">All Statuses</option>
                @foreach(['Pending', 'Approved', 'Rejected', 'Completed'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                @endforeach
            </select>
            @if(request()->hasAny(['search', 'status']))
                <a href="{{ route('cloth-store.returns.index') }}" class="btn-cs-ghost">
                    <i class="fa-solid fa-xmark text-[10px]"></i> Clear
                </a>
            @endif
        </form>
    </x-slot:toolbar>

        <table class="table-cs" id="return-table">
            <thead>
                <tr>
                    <th>Return</th>
                    <th>Order / Customer</th>
                    <th>Items Returned</th>
                    <th class="text-right">Refunded</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($returns as $r)
                @php
                    $statusBadge = match ($r->status) {
                        'Approved', 'Completed' => 'badge-delivered',
                        'Rejected'              => 'badge-overdue',
                        default                 => 'badge-pending',
                    };
                @endphp
                <tr>
                    <td>
                        <div class="cell-strong cell-num">{{ $r->return_number }}</div>
                        <div class="cell-muted">{{ $r->created_at->format('d M Y, h:i A') }}</div>
                    </td>
                    <td>
                        <div class="cell-strong">{{ $r->order->invoice_number ?? 'N/A' }}</div>
                        <div class="cell-muted">{{ $r->customer->name ?? 'Walk-in Customer' }}</div>
                    </td>
                    <td>
                        <div class="text-sm text-slate-700">{{ $r->items->count() }} {{ \Illuminate\Support\Str::plural('item', $r->items->count()) }}</div>
                        <div class="cell-muted max-w-[220px] truncate">
                            @foreach($r->items as $item)
                                {{ $item->product->name ?? 'Unknown' }} ({{ $item->action_type }})@if(!$loop->last), @endif
                            @endforeach
                        </div>
                    </td>
                    <td class="text-right cell-strong cell-num">Rs {{ number_format((float) $r->total_refund_amount) }}</td>
                    <td class="text-center"><span class="badge {{ $statusBadge }}">{{ $r->status }}</span></td>
                    <td>
                        <div class="flex items-center justify-end gap-1.5">
                            @if($r->status === 'Pending')
                                {{-- Both post over AJAX: these were native form
                                     submits, i.e. a full page reload each, with
                                     a blocking browser confirm() in front. --}}
                                <button type="button" onclick="setReturnStatus({{ $r->id }}, 'Approved')"
                                        class="text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 rounded-lg transition">Approve</button>
                                <button type="button" onclick="setReturnStatus({{ $r->id }}, 'Rejected')"
                                        class="text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition">Reject</button>
                            @else
                                <span class="cell-muted">—</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                    <x-cloth-store.empty-state
                        :colspan="6"
                        icon="fa-arrow-rotate-left"
                        title="No returns found"
                        message="Returns and exchanges raised against a sale will appear here." />
                @endforelse
            </tbody>
        </table>

    <x-slot:footer>
        <x-cloth-store.pagination :paginator="$returns" noun="return" />
    </x-slot:footer>
</x-cloth-store.panel>


<!-- ========================================== -->
<!-- CREATE RETURN MODAL                        -->
<!-- ========================================== -->
<div id="return-modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h3 class="text-lg font-bold text-slate-900">Process Return / Exchange</h3>
            <button type="button" onclick="closeReturnModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200 transition"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="p-6 overflow-y-auto flex-1 bg-slate-50/50">
            <!-- Step 1: Find Order -->
            <div id="step-1-find" class="mb-6">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Find Order by Invoice Number</label>
                <div class="flex gap-3">
                    <input type="text" id="find-invoice" placeholder="INV-2026-..." class="flex-1 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:border-indigo-500 transition shadow-sm">
                    <button type="button" onclick="searchOrder()" class="px-5 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition shadow-sm">Search</button>
                </div>
                <div id="find-error" class="text-sm text-rose-500 mt-2 hidden"></div>
            </div>

            <!-- Step 2: Order Details & Items (Hidden initially) -->
            <form id="return-form" class="hidden">
                @csrf
                <input type="hidden" name="order_id" id="return-order-id">
                
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6 flex justify-between items-center">
                    <div>
                        <div class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">Customer</div>
                        <div class="text-sm font-bold text-slate-900" id="ro-customer">Ali Raza</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-slate-500 font-semibold uppercase tracking-wider mb-1">Invoice</div>
                        <div class="text-sm font-bold text-indigo-600" id="ro-invoice">INV-001</div>
                    </div>
                </div>

                <h4 class="text-sm font-bold text-slate-800 mb-3 uppercase tracking-wider">Select Items to Return</h4>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
                    <table class="w-full text-left" id="ro-items-table">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase">Product</th>
                                <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase text-center">Purchased</th>
                                <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase text-center">Return Qty</th>
                                <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase w-40">Reason</th>
                                <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase w-40">Action</th>
                                <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase text-right">Refund Amt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>

                <div class="mb-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Internal Notes (Optional)</label>
                    <textarea name="notes" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-sm focus:border-indigo-500 transition shadow-sm h-20" placeholder="Reason for return, condition of item, etc..."></textarea>
                </div>
            </form>
        </div>
        
        <div class="px-6 py-4 border-t border-slate-200 bg-white flex items-center justify-end gap-3 hidden" id="return-actions">
            <button type="button" onclick="closeReturnModal()" class="px-5 py-2.5 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition shadow-sm">Cancel</button>
            <button type="button" onclick="submitReturn()" class="px-5 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition shadow-sm">
                Submit Return
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    /* Approve / Reject. Posts through Atelier.request (which drops the page
       cache, since a return moves both stock and ledgers) and then re-renders
       <main> in place — no reload, and the confirmation uses the app's own
       modal rather than a blocking browser dialog. */
    window.setReturnStatus = function (id, status) {
        const approving = status === 'Approved';

        Atelier.confirmAction({
            title: approving ? 'Approve this return?' : 'Reject this return?',
            message: approving
                ? 'Stock and ledgers will be adjusted to reflect the returned items.'
                : 'The return will be marked as rejected. Nothing is restocked.',
            confirmLabel: approving ? 'Approve' : 'Reject',
            danger: !approving,
            onConfirm: async () => {
                await Atelier.request(`/cloth-store/returns/${id}/status`, {
                    method: 'POST',
                    body: { status },
                });
                await Atelier.refreshPage();
            },
        });
    };

    // Products fetched for Exchange dropdown
    const allProducts = @json(\App\Models\ClothStore\Product::where('status', 'Active')->get(['id', 'name']));

    /* Client-side row hiding removed. #return-search is now part of the filter
       form and queries the database, so it finds returns on any page — and
       leaving this in would have re-hidden rows the server just returned. */

    window.openReturnModal = function() {
        document.getElementById('step-1-find').classList.remove('hidden');
        document.getElementById('return-form').classList.add('hidden');
        document.getElementById('return-actions').classList.add('hidden');
        document.getElementById('find-invoice').value = '';
        document.getElementById('find-error').classList.add('hidden');
        document.getElementById('return-modal').classList.remove('hidden');
    };

    window.closeReturnModal = function() {
        document.getElementById('return-modal').classList.add('hidden');
    };

    window.searchOrder = async function() {
        let inv = document.getElementById('find-invoice').value;
        if (!inv) return;

        let err = document.getElementById('find-error');
        err.classList.add('hidden');

        try {
            let res = await fetch(`/cloth-store/returns-search?invoice_number=${encodeURIComponent(inv)}`);
            let data = await res.json();
            
            if (data.success) {
                populateOrderForm(data.order);
            } else {
                err.innerText = data.message;
                err.classList.remove('hidden');
            }
        } catch (e) {
            err.innerText = "An error occurred searching for the order.";
            err.classList.remove('hidden');
        }
    }

    window.populateOrderForm = function(order) {
        document.getElementById('return-order-id').value = order.id;
        document.getElementById('ro-invoice').innerText = order.invoice_number;
        document.getElementById('ro-customer').innerText = order.customer ? order.customer.name : 'Walk-in Customer';

        let tbody = document.querySelector('#ro-items-table tbody');
        tbody.innerHTML = '';

        order.items.forEach((item, index) => {
            let tr = document.createElement('tr');
            
            // Build Exchange Options
            let exOpts = `<option value="">Select New Product</option>`;
            allProducts.forEach(p => {
                exOpts += `<option value="${p.id}">${p.name}</option>`;
            });

            tr.innerHTML = `
                <td class="px-4 py-4">
                    <div class="text-sm font-semibold text-slate-900">${item.product?.name || 'Item'}</div>
                    <div class="text-xs text-slate-500">Rs ${item.unit_price}</div>
                    <input type="hidden" name="items[${index}][order_item_id]" value="${item.id}">
                    <input type="hidden" name="items[${index}][product_id]" value="${item.cs_product_id}">
                </td>
                <td class="px-4 py-4 text-center">
                    <div class="text-sm text-slate-700">${item.quantity}</div>
                </td>
                <td class="px-4 py-4 text-center">
                    <input type="number" name="items[${index}][quantity]" class="w-16 px-2 py-1 bg-white border border-slate-200 rounded text-sm text-center focus:border-indigo-500 qty-input" min="0" max="${item.quantity}" step="0.01" placeholder="0" data-price="${item.unit_price}" onchange="calcRefund(this, ${index})">
                </td>
                <td class="px-4 py-4">
                    <select name="items[${index}][reason]" class="w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs">
                        <option value="Customer Changed Mind">Changed Mind</option>
                        <option value="Wrong Size">Wrong Size</option>
                        <option value="Defective">Defective</option>
                        <option value="Damaged">Damaged</option>
                        <option value="Color Issue">Color Issue</option>
                        <option value="Wrong Product">Wrong Product</option>
                    </select>
                </td>
                <td class="px-4 py-4">
                    <select name="items[${index}][action_type]" class="w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs mb-1" onchange="toggleExchange(this, ${index})">
                        <option value="Refund">Refund</option>
                        <option value="Exchange">Exchange</option>
                    </select>
                    <select name="items[${index}][exchange_product_id]" class="w-full px-2 py-1 bg-slate-50 border border-slate-200 rounded text-[10px] hidden" id="ex-prod-${index}">
                        ${exOpts}
                    </select>
                </td>
                <td class="px-4 py-4 text-right">
                    <input type="number" name="items[${index}][refund_amount]" id="ref-amt-${index}" class="w-20 px-2 py-1 bg-slate-50 border border-slate-200 rounded text-sm text-right" placeholder="0" readonly>
                </td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('step-1-find').classList.add('hidden');
        document.getElementById('return-form').classList.remove('hidden');
        document.getElementById('return-actions').classList.remove('hidden');
    }

    window.toggleExchange = function(selectElem, index) {
        let exSelect = document.getElementById(`ex-prod-${index}`);
        let refAmt = document.getElementById(`ref-amt-${index}`);
        
        if (selectElem.value === 'Exchange') {
            exSelect.classList.remove('hidden');
            refAmt.value = "0"; // No refund if exchanging
            refAmt.readOnly = true;
            refAmt.classList.add('bg-slate-100');
        } else {
            exSelect.classList.add('hidden');
            exSelect.value = '';
            
            // Recalc refund based on qty
            let tr = selectElem.closest('tr');
            let qtyInput = tr.querySelector('.qty-input');
            calcRefund(qtyInput, index);
        }
    }

    window.calcRefund = function(qtyInput, index) {
        let action = document.querySelector(`select[name="items[${index}][action_type]"]`).value;
        if (action === 'Exchange') return;

        let price = parseFloat(qtyInput.getAttribute('data-price')) || 0;
        let qty = parseFloat(qtyInput.value) || 0;
        let refAmt = document.getElementById(`ref-amt-${index}`);
        
        refAmt.value = (price * qty).toFixed(2);
        refAmt.readOnly = false;
        refAmt.classList.remove('bg-slate-100');
        refAmt.classList.add('bg-white');
    }

    window.submitReturn = async function() {
        let form = document.getElementById('return-form');
        let fd = new FormData(form);
        
        // Basic validation: ensure at least one qty > 0
        let hasQty = false;
        form.querySelectorAll('.qty-input').forEach(inp => {
            if (parseFloat(inp.value) > 0) hasQty = true;
        });

        if (!hasQty) {
            toast('Please specify a return quantity for at least one item.', 'error');
            return;
        }

        try {
            let res = await fetch("{{ route('cloth-store.returns.store') }}", {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            let data = await res.json();

            if (data.success) {
                toast(data.message || 'Return recorded', 'success');
                closeDrawers();
                // A return moves stock and money, so every cached page is stale.
                await Atelier.refreshPage();
            } else {
                toast(data.message, 'error');
            }
        } catch (e) {
            toast('Failed to submit return request.', 'error');
        }
    }
</script>
@endpush
