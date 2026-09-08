@extends('cloth-store.layouts.app')
@section('title', 'Stock Management')
@section('spaPage', 'cloth-store-stock')

@section('content')
<x-cloth-store.page-header
    title="Stock Management"
    subtitle="Manage Main Store inventory and track every movement">
    <x-slot:actions>
        <a href="{{ route('cloth-store.stock.alerts') }}" class="btn-cs-ghost">
            <i class="fa-solid fa-bell text-[10px]"></i> Alerts
        </a>
        <a href="{{ route('cloth-store.stock.history') }}" class="btn-cs-ghost">
            <i class="fa-solid fa-clock-rotate-left text-[10px]"></i> History
        </a>
        <button onclick="openTransactionModal()" class="btn-cs-primary">
            <i class="fa-solid fa-boxes-packing text-[10px]"></i> New Operation
        </button>
    </x-slot:actions>
</x-cloth-store.page-header>

<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <x-cloth-store.stat-card
        label="Total Stock" icon="fa-layer-group" tone="indigo"
        :value="rtrim(rtrim(number_format((float) $totalStock, 2), '0'), '.')"
        sub="Units across all products" />

    <x-cloth-store.stat-card
        label="Stock Value" icon="fa-coins" tone="emerald"
        :value="'Rs ' . number_format((float) $stockValue)"
        sub="Valued at cost price" />

    @php
        // Built in PHP: a bound attribute is passed through without
        // HTML-decoding, so &quot; entities inline would render literally.
        $lowSub = $lowStockCount
            ? '<span class="text-amber-600 font-semibold">Reorder soon</span>'
            : 'All levels healthy';
        $outSub = $outOfStockCount
            ? '<span class="text-red-500 font-semibold">Unsellable</span>'
            : 'Nothing depleted';
    @endphp

    <x-cloth-store.stat-card
        label="Low Stock" icon="fa-triangle-exclamation" :tone="$lowStockCount ? 'amber' : 'slate'"
        :value="number_format($lowStockCount)"
        :sub="$lowSub" />

    <x-cloth-store.stat-card
        label="Out of Stock" icon="fa-circle-xmark" :tone="$outOfStockCount ? 'red' : 'slate'"
        :value="number_format($outOfStockCount)"
        :sub="$outSub" />

    <x-cloth-store.stat-card
        label="Reserved" icon="fa-lock" tone="sky"
        :value="rtrim(rtrim(number_format((float) $reservedStock, 2), '0'), '.')"
        sub="Committed to open orders" />
</div>

<!-- Tabs -->
<div class="page flex gap-1 mb-6 border-b border-slate-200">
    <a href="{{ route('cloth-store.stock.index') }}" class="px-5 py-3 text-sm font-semibold text-slate-900 border-b-2 border-slate-900 -mb-px transition">Current Inventory</a>
    <a href="{{ route('cloth-store.stock.history') }}" class="px-5 py-3 text-sm font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent -mb-px transition">Stock History</a>
    <a href="{{ route('cloth-store.stock.alerts') }}" class="px-5 py-3 text-sm font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent -mb-px transition">Alerts</a>
</div>

<x-cloth-store.panel>
    <x-slot:toolbar>
        <form data-filter-form method="GET" action="{{ route('cloth-store.stock.index') }}" class="flex flex-wrap gap-3 items-center w-full">
            <div class="relative flex-1 min-w-[240px] max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" id="inventory-search" value="{{ request('search') }}" autocomplete="off"
                       placeholder="Search name, SKU or barcode..." class="input-cs w-full pl-9">
            </div>
            <select name="stock_status" class="input-cs w-44">
                <option value="">Any Stock Level</option>
                <option value="healthy" @selected(request('stock_status') === 'healthy')>Healthy</option>
                <option value="low"     @selected(request('stock_status') === 'low')>Low Stock</option>
                <option value="out"     @selected(request('stock_status') === 'out')>Out of Stock</option>
            </select>
            @if(request()->hasAny(['search', 'stock_status']))
                <a href="{{ route('cloth-store.stock.index') }}" class="btn-cs-ghost">
                    <i class="fa-solid fa-xmark text-[10px]"></i> Clear
                </a>
            @endif
        </form>
    </x-slot:toolbar>

        <table class="table-cs" id="inventory-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Location(s)</th>
                    <th class="text-right">Available</th>
                    <th class="text-right">Reserved</th>
                    <th class="text-right">Reorder At</th>
                    <th class="text-right">Stock Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inventory as $item)
                    @php
                        // Metres are fractional; trim the trailing zeros so a
                        // whole number doesn't read as "12.00".
                        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');

                        $available = (float) $item->stock_quantity - (float) $item->reserved_quantity;
                        $isLow = $available <= (float) $item->low_stock_threshold;
                        $isOut = $available < 0.01;
                    @endphp
                    <tr>
                        <td>
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $item->category?->color_bg ?? 'bg-slate-100' }} {{ $item->category?->color_text ?? 'text-slate-600' }}">
                                    <i class="fa-solid {{ $item->category?->icon ?? 'fa-shirt' }} text-sm"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="cell-strong truncate">{{ $item->name }}</div>
                                    <div class="cell-muted">{{ $item->sku ?: 'No SKU' }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="text-slate-600">{{ $item->category?->name ?? '—' }}</td>

                        <td>
                            @php $placed = $item->productLocations->filter(fn ($pl) => (float) $pl->quantity > 0); @endphp
                            @if($placed->isEmpty())
                                <span class="text-slate-400 italic text-xs">No location</span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach($placed as $pl)
                                        <span class="badge badge-neutral">{{ $pl->location->name ?? 'Unknown' }}: {{ $fmt($pl->quantity) }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>

                        <td class="text-right">
                            <span class="font-semibold cell-num {{ $isOut ? 'text-red-600' : ($isLow ? 'text-amber-600' : 'text-slate-900') }}">
                                {{ $fmt($available) }}
                            </span>
                            <span class="text-xs text-slate-400">{{ $item->unit }}</span>
                        </td>

                        <td class="text-right text-slate-600 cell-num">
                            {{ $fmt($item->reserved_quantity) }} <span class="text-xs text-slate-400">{{ $item->unit }}</span>
                        </td>

                        <td class="text-right cell-num">
                            @if($isLow)
                                <span class="text-red-600 font-semibold">
                                    <i class="fa-solid fa-triangle-exclamation mr-1 text-[10px]"></i>{{ $fmt($item->low_stock_threshold) }}
                                </span>
                            @else
                                <span class="text-slate-500">{{ $fmt($item->low_stock_threshold) }}</span>
                            @endif
                        </td>

                        <td class="text-right cell-strong cell-num">
                            Rs {{ number_format((float) $item->stock_quantity * (float) $item->cost_price, 2) }}
                        </td>
                    </tr>
                @empty
                    <x-cloth-store.empty-state
                        :colspan="7"
                        icon="fa-warehouse"
                        :title="request()->hasAny(['search', 'stock_status']) ? 'No products match those filters' : 'No inventory yet'"
                        :message="request()->hasAny(['search', 'stock_status'])
                            ? 'Try widening your search or clearing the filters.'
                            : 'Add products, then record a stock-in operation to build inventory.'" />
                @endforelse
            </tbody>
        </table>

    <x-slot:footer>
        <x-cloth-store.pagination :paginator="$inventory" noun="product" />
    </x-slot:footer>
</x-cloth-store.panel>

<!-- ========================================== -->
<!-- NEW TRANSACTION MODAL                      -->
<!-- ========================================== -->
<div id="tx-modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h3 class="text-lg font-bold text-slate-900" id="tx-modal-title">New Stock Operation</h3>
            <button type="button" onclick="closeTransactionModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200 transition"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="p-6 overflow-y-auto flex-1">
            <form id="tx-form" onsubmit="submitTransaction(event)">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                    <!-- Operation Type -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Operation Type</label>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" name="operation" value="in" class="peer sr-only" checked onchange="toggleTxFields()">
                                <div class="px-3 py-2 text-center rounded-lg border border-slate-200 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-700 text-sm font-semibold text-slate-600 transition">Market Purchase</div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="operation" value="out" class="peer sr-only" onchange="toggleTxFields()">
                                <div class="px-3 py-2 text-center rounded-lg border border-slate-200 peer-checked:border-rose-500 peer-checked:bg-rose-50 peer-checked:text-rose-700 text-sm font-semibold text-slate-600 transition">Stock Out</div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="operation" value="adjustment" class="peer sr-only" onchange="toggleTxFields()">
                                <div class="px-3 py-2 text-center rounded-lg border border-slate-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-700 text-sm font-semibold text-slate-600 transition">Adjust</div>
                            </label>
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Category</label>
                        <select id="tx-category-select" onchange="updateProductDropdown()" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                            <option value="">[ Select Category... ]</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Product -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Product</label>
                        <select id="tx-product-select" name="cs_product_id" required disabled class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm disabled:opacity-60 disabled:bg-slate-100 disabled:cursor-not-allowed">
                            <option value="">Select a category first...</option>
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Quantity</label>
                        <input type="number" name="quantity" required min="1" step="any" placeholder="e.g. 10" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                    </div>

                    <!-- Reason -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Reason</label>
                        <select name="reason" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                            <option value="Market stock purchase">Bought from market</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Lost">Lost / Missing</option>
                            <option value="Returned">Returned by Customer</option>
                            <option value="Manual Adjustment">Manual Adjustment</option>
                        </select>
                    </div>

                    <!-- Adjustment Type (+/-) -->
                    <div id="field-adj-type" class="sm:col-span-2 hidden">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Adjustment Type</label>
                        <select name="adjustment_type" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                            <option value="add">Add to Stock (+)</option>
                            <option value="subtract">Subtract from Stock (-)</option>
                        </select>
                    </div>

                    <!-- Reference & Notes -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Reference / Invoice #</label>
                        <input type="text" name="reference" placeholder="e.g. Market receipt 1234" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Notes</label>
                        <input type="text" name="notes" placeholder="Optional details..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                    </div>
                </div>
            </form>
        </div>
        
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3">
            <button type="button" onclick="closeTransactionModal()" class="px-5 py-2.5 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition shadow-sm">Cancel</button>
            <button type="button" id="tx-save-btn" onclick="document.getElementById('tx-form').requestSubmit()" class="px-5 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition shadow-sm flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                <i class="fa-solid fa-check"></i> Save Transaction
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    /* Client-side row hiding removed — inventory search and the stock-level
       filter are now database queries (see StockController@index), so they
       reach products beyond the current page. */

    @php
        $mappedProducts = $products->map(fn($p) => [
            'id' => $p->id, 
            'cs_category_id' => $p->cs_category_id, 
            'name' => $p->name, 
            'sku' => $p->sku ?? 'N/A'
        ]);
    @endphp
    const allProducts = @json($mappedProducts);

    function updateProductDropdown() {
        const catId = document.getElementById('tx-category-select').value;
        const prodSelect = document.getElementById('tx-product-select');
        
        prodSelect.innerHTML = '';
        
        if (!catId) {
            prodSelect.disabled = true;
            prodSelect.innerHTML = '<option value="">Select a category first...</option>';
            return;
        }
        
        prodSelect.disabled = false;
        prodSelect.innerHTML = '<option value="">[ Select Product from selected category... ]</option>';
        
        const filtered = allProducts.filter(p => p.cs_category_id == catId);
        
        filtered.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.text = `${p.name} (SKU: ${p.sku})`;
            prodSelect.appendChild(opt);
        });
    }

    // Modal Logic
    function openTransactionModal() {
        const modal = document.getElementById('tx-modal');
        modal.classList.remove('hidden');
        modal.classList.add('active');
        toggleTxFields();
    }
    
    function closeTransactionModal() {
        const modal = document.getElementById('tx-modal');
        modal.classList.remove('active');
        modal.classList.add('hidden');
        document.getElementById('tx-form').reset();
        updateProductDropdown();
    }
    
    function toggleTxFields() {
        let op = document.querySelector('input[name="operation"]:checked').value;
        let adjType = document.getElementById('field-adj-type');
        if (op === 'adjustment') {
            adjType.classList.remove('hidden');
        } else {
            adjType.classList.add('hidden');
        }
    }

    // A stock movement must not be submitted twice — the second POST would
    // apply the same quantity again and silently corrupt inventory.
    var txSubmitting = false;

    async function submitTransaction(e) {
        e.preventDefault();
        if (txSubmitting) return;

        const submitBtn = document.getElementById('tx-save-btn');
        let fd = new FormData(e.target);

        txSubmitting = true;
        if (submitBtn) submitBtn.disabled = true;

        try {
            let res = await fetch("{{ route('cloth-store.stock.transaction') }}", {
                method: 'POST',
                body: fd,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            let data = await res.json();

            if (data.success) {
                toast(data.message, 'success');
                closeTransactionModal();
                // Was setTimeout(reload, 800): an artificial wait followed by a
                // full document reload. refreshPage re-renders <main> from the
                // server immediately — the toast stays on screen either way.
                await Atelier.refreshPage();
            } else {
                toast(data.message, 'error');
            }
        } catch (err) {
            toast('Failed to process transaction', 'error');
        } finally {
            txSubmitting = false;
            if (submitBtn && document.body.contains(submitBtn)) submitBtn.disabled = false;
        }
    }
</script>
@endpush
