@extends('cloth-store.layouts.app')
@section('title', 'Stock History')
@section('spaPage', 'cloth-store-stock')

@section('content')
<x-cloth-store.page-header
    title="Stock Management"
    subtitle="Every movement in and out of inventory, with a full audit trail">
    <x-slot:actions>
        <a href="{{ route('cloth-store.stock.index') }}" class="btn-cs-ghost">
            <i class="fa-solid fa-warehouse text-[10px]"></i> Inventory
        </a>
    </x-slot:actions>
</x-cloth-store.page-header>

<!-- Tabs -->
<div class="page flex gap-1 mb-6 border-b border-slate-200">
    <a href="{{ route('cloth-store.stock.index') }}" class="px-5 py-3 text-sm font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent -mb-px transition">Current Inventory</a>
    <a href="{{ route('cloth-store.stock.history') }}" class="px-5 py-3 text-sm font-semibold text-slate-900 border-b-2 border-slate-900 -mb-px transition">Stock History</a>
    <a href="{{ route('cloth-store.stock.alerts') }}" class="px-5 py-3 text-sm font-semibold text-slate-500 hover:text-slate-900 border-b-2 border-transparent -mb-px transition">Alerts</a>
</div>

<x-cloth-store.panel>
    <table class="table-cs">
        <thead>
            <tr>
                <th>Date</th>
                <th>Product</th>
                <th>Operation</th>
                <th class="text-center">Change</th>
                <th class="text-right">Balance</th>
                <th>User / Reason</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $tx)
                @php
                    // Metres are fractional — trim trailing zeros so a whole
                    // number reads as "12" rather than "12.00".
                    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');

                    // An adjustment can go either way, so its direction comes
                    // from the before/after snapshot rather than the type.
                    $isIncrease = $tx->type === 'in'
                        || ($tx->type === 'adjustment' && (float) $tx->new_qty > (float) $tx->previous_qty);

                    $operation = match ($tx->type) {
                        'in'       => ['badge-delivered', 'fa-arrow-down', 'Stock In'],
                        'out'      => ['badge-overdue', 'fa-arrow-up', 'Stock Out'],
                        'transfer' => ['badge-progress', 'fa-right-left', 'Transfer'],
                        default    => ['badge-pending', 'fa-sliders', 'Adjustment'],
                    };

                    [$badgeClass, $icon, $label] = $operation;
                @endphp
                <tr>
                    <td class="whitespace-nowrap">
                        <div class="cell-strong cell-num">{{ $tx->created_at->format('d M Y') }}</div>
                        <div class="cell-muted cell-num">{{ $tx->created_at->format('h:i A') }}</div>
                    </td>

                    <td>
                        <div class="cell-strong truncate">{{ $tx->product->name ?? 'Unknown' }}</div>
                        <div class="cell-muted">Ref: {{ $tx->reference ?: 'N/A' }}</div>
                    </td>

                    <td>
                        <span class="badge {{ $badgeClass }}"><i class="fa-solid {{ $icon }} mr-1"></i>{{ $label }}</span>
                        @if($tx->type === 'transfer')
                            <div class="cell-muted mt-1">{{ $tx->fromLocation->name ?? 'Unknown' }} &rarr; {{ $tx->toLocation->name ?? 'Unknown' }}</div>
                        @elseif($tx->type === 'in' && $tx->toLocation)
                            <div class="cell-muted mt-1">To {{ $tx->toLocation->name }}</div>
                        @elseif($tx->type === 'out' && $tx->fromLocation)
                            <div class="cell-muted mt-1">From {{ $tx->fromLocation->name }}</div>
                        @endif
                    </td>

                    <td class="text-center">
                        @if($tx->type === 'transfer')
                            <span class="text-sky-600 font-semibold cell-num">{{ $fmt($tx->quantity) }}</span>
                        @else
                            <span class="font-semibold cell-num {{ $isIncrease ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $isIncrease ? '+' : '−' }}{{ $fmt($tx->quantity) }}
                            </span>
                        @endif
                    </td>

                    <td class="text-right">
                        <div class="cell-muted cell-num"><del>{{ $fmt($tx->previous_qty) }}</del></div>
                        <div class="cell-strong cell-num">{{ $fmt($tx->new_qty) }}</div>
                    </td>

                    <td>
                        <div class="text-sm text-slate-700">
                            <i class="fa-solid fa-circle-user text-slate-400 mr-1"></i>{{ $tx->user->name ?? 'System' }}
                        </div>
                        <div class="cell-muted">{{ $tx->reason ?: 'No reason recorded' }}</div>
                    </td>
                </tr>
            @empty
                <x-cloth-store.empty-state
                    :colspan="6"
                    icon="fa-clock-rotate-left"
                    title="No stock movements yet"
                    message="Sales, goods receipts, returns and adjustments will all be logged here." />
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <x-cloth-store.pagination :paginator="$transactions" noun="movement" />
    </x-slot:footer>
</x-cloth-store.panel>
@endsection
