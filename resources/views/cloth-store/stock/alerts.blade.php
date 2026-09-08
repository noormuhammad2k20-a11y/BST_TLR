@extends('cloth-store.layouts.app')
@section('title', 'Low Stock Alerts')
@section('spaPage', 'cloth-store-stock-alerts')

@section('content')
<x-cloth-store.page-header
    title="Low Stock Alerts"
    subtitle="Products needing a reorder decision">
    <x-slot:actions>
        <a href="{{ route('cloth-store.stock.index') }}" class="btn-cs-ghost">
            <i class="fa-solid fa-warehouse text-[10px]"></i> Inventory
        </a>
        <a href="{{ route('cloth-store.stock.index') }}" class="btn-cs-primary">
            <i class="fa-solid fa-plus text-[10px]"></i> Record Market Stock
        </a>
    </x-slot:actions>
</x-cloth-store.page-header>

<!-- Tabs -->
<div class="page flex gap-1 mb-6 border-b border-slate-200">
    <a href="{{ route('cloth-store.stock.index') }}" class="px-5 py-3 text-sm font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent -mb-px transition">Current Inventory</a>
    <a href="{{ route('cloth-store.stock.history') }}" class="px-5 py-3 text-sm font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent -mb-px transition">Stock History</a>
    <a href="{{ route('cloth-store.stock.alerts') }}" class="px-5 py-3 text-sm font-semibold text-slate-900 border-b-2 border-slate-900 -mb-px transition">Alerts</a>
</div>

<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-cloth-store.stat-card
        label="Out of Stock" icon="fa-circle-xmark" :tone="$outOfStockCount ? 'red' : 'slate'"
        :value="number_format($outOfStockCount)" sub="Cannot be sold" />

    <x-cloth-store.stat-card
        label="Critical Stock" icon="fa-triangle-exclamation" :tone="$criticalCount ? 'red' : 'slate'"
        :value="number_format($criticalCount)" sub="Down to the last 30%" />

    <x-cloth-store.stat-card
        label="Low Stock" icon="fa-battery-quarter" :tone="$lowCount ? 'amber' : 'slate'"
        :value="number_format($lowCount)" sub="At or below reorder level" />

    <x-cloth-store.stat-card
        label="Reorder Ready" icon="fa-cart-arrow-down" tone="emerald"
        :value="number_format($recommendedCount)" sub="Have a suggested quantity" />
</div>

<x-cloth-store.panel>
    <x-slot:toolbar>
        <div class="relative w-full sm:w-80">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
            {{-- This list is intentionally short and already fully rendered
                 (only products actually in an alert state reach it), so
                 filtering it in the browser is accurate here. --}}
            <input type="search" id="alert-search" placeholder="Filter alerts..." class="input-cs w-full pl-9">
        </div>
    </x-slot:toolbar>

        <table class="table-cs" id="alert-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Available</th>
                    <th class="text-right">Threshold</th>
                    <th class="text-right">Suggested Qty</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($alertProducts as $item)
                @php
                    // Fabric is fractional — keep the decimals visible.
                    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');

                    $levelBadge = match ($item->alert_level) {
                        'Out of Stock' => 'badge-overdue',
                        'Critical'     => 'badge-overdue',
                        default        => 'badge-pending',
                    };
                @endphp
                <tr>
                    <td>
                        <div class="cell-strong truncate">{{ $item->name }}</div>
                        <div class="cell-muted">{{ $item->sku ?: 'No SKU' }} &bull; {{ $item->category?->name ?? 'Uncategorized' }}</div>
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $levelBadge }}">{{ $item->alert_level }}</span>
                    </td>
                    <td class="text-right cell-strong cell-num">{{ $fmt($item->available_stock) }} <span class="text-xs font-normal text-slate-400">{{ $item->unit }}</span></td>
                    <td class="text-right text-slate-600 cell-num">{{ $fmt($item->low_stock_threshold) }}</td>
                    <td class="text-right font-semibold text-slate-900 cell-num">{{ $fmt($item->suggested_reorder_qty) }}</td>
                    <td>
                        <div class="flex items-center justify-end gap-1 row-actions">
                            {{-- @js escapes the name safely for a JS argument;
                                 addslashes() does not escape quotes for HTML
                                 attributes and broke on names with an apostrophe. --}}
                            <button onclick="openConfigModal({{ $item->id }}, {{ (float) $item->low_stock_threshold }}, {{ (float) $item->suggested_reorder_qty }}, @js($item->name))"
                                    class="btn-cs-icon" title="Configure reorder settings">
                                <i class="fa-solid fa-gear text-xs"></i>
                            </button>
                            <button onclick="ignoreAlert({{ $item->id }})" class="btn-cs-icon danger" title="Ignore this alert">
                                <i class="fa-solid fa-eye-slash text-xs"></i>
                            </button>
                            <a href="{{ route('cloth-store.stock.index') }}" class="btn-cs-ghost py-1.5 px-2.5" title="Record a market stock purchase">
                                <i class="fa-solid fa-cart-plus text-[10px]"></i> PO
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                    <x-cloth-store.empty-state
                        :colspan="6"
                        icon="fa-face-smile"
                        title="All stock healthy"
                        message="Nothing is below its reorder level right now." />
                @endforelse
            </tbody>
        </table>
</x-cloth-store.panel>

<!-- ========================================== -->
<!-- CONFIGURE ALERT MODAL                      -->
<!-- ========================================== -->
<div id="config-modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h3 class="text-lg font-bold text-slate-900">Configure Reorder Settings</h3>
            <button type="button" onclick="closeConfigModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200 transition"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="p-6">
            <div class="mb-4 text-sm text-slate-600 font-semibold" id="config-product-name"></div>
            <form id="config-form" onsubmit="submitConfig(event)">
                <input type="hidden" id="config-id" name="id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Low Stock Threshold</label>
                        <input type="number" id="config-threshold" name="low_stock_threshold" required min="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                        <p class="text-[10px] text-slate-400 mt-1">Alert triggers when available stock falls at or below this number.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Suggested Market Purchase</label>
                        <input type="number" id="config-suggested" name="suggested_reorder_qty" required min="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                        <p class="text-[10px] text-slate-400 mt-1">Suggested quantity to buy during the next market visit.</p>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3">
            <button type="button" onclick="closeConfigModal()" class="px-5 py-2.5 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition shadow-sm">Cancel</button>
            <button type="button" onclick="document.getElementById('config-form').requestSubmit()" class="px-5 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-save"></i> Save Config
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Search
    document.getElementById('alert-search')?.addEventListener('input', function(e) {
        let term = e.target.value.toLowerCase();
        let rows = document.querySelectorAll('#alert-table tbody tr');
        rows.forEach(r => {
            let txt = r.innerText.toLowerCase();
            r.style.display = txt.includes(term) ? '' : 'none';
        });
    });

    function openConfigModal(id, threshold, suggested, name) {
        document.getElementById('config-id').value = id;
        document.getElementById('config-threshold').value = threshold;
        document.getElementById('config-suggested').value = suggested;
        document.getElementById('config-product-name').innerText = name;
        document.getElementById('config-modal').classList.remove('hidden');
    }

    function closeConfigModal() {
        document.getElementById('config-modal').classList.add('hidden');
    }

    async function submitConfig(e) {
        e.preventDefault();
        
        let fd = new FormData(e.target);
        let id = fd.get('id');
        
        try {
            let res = await fetch(`/cloth-store/stock/alerts/${id}/config`, {
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
                closeConfigModal();
                // Was setTimeout(reload, 800) — an artificial pause plus a full
                // document reload. This re-renders <main> straight away.
                await Atelier.refreshPage();
            } else {
                toast(data.message, 'error');
            }
        } catch (err) {
            toast('Failed to update config', 'error');
        }
    }

    /*
     * This used to hand-build the confirmation dialog AND reassign the global
     * window.confirmAction to its own handler. That handler was never restored,
     * so after ignoring one alert every confirmation dialog elsewhere in the app
     * ran the ignore-alert request instead of its own action. Atelier.confirmAction
     * scopes the callback to this one invocation.
     */
    function ignoreAlert(id) {
        Atelier.confirmAction({
            variant: 'delete',
            title: 'Ignore Alert?',
            message: 'This product will no longer appear on the low stock alerts page.',
            confirmLabel: 'Ignore',
            onConfirm: async () => {
                await Atelier.request(`/cloth-store/stock/alerts/${id}/ignore`, { method: 'POST' });
                await Atelier.refreshPage();
            },
            successMessage: 'Alert ignored',
        });
    }
</script>
@endpush
