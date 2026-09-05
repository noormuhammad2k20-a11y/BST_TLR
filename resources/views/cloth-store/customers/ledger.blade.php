@extends('cloth-store.layouts.app')
@section('title', 'Customer Ledger')
@section('spaPage', 'cloth-store-ledger')

@section('content')
<x-cloth-store.page-header
  title="Customer Ledger"
  :subtitle="$selectedCustomer
      ? 'Statement for ' . $selectedCustomer->name
      : 'Track financial history, sales and payments'">
  @if($selectedCustomer)
    <x-slot:actions>
      <button onclick="window.print()" class="btn-cs-ghost">
        <i class="fa-solid fa-print text-[10px]"></i> Print Statement
      </button>
      <a href="{{ route('cloth-store.payments.index', ['search' => $selectedCustomer->phone ?: $selectedCustomer->name]) }}" class="btn-cs-primary">
        <i class="fa-solid fa-money-bill text-[10px]"></i> Add Payment
      </a>
    </x-slot:actions>
  @endif
</x-cloth-store.page-header>

<!-- Search Form -->
<div class="page bg-white p-5 border border-slate-200 rounded-xl shadow-sm mb-6">
  <form data-filter-form method="GET" action="{{ route('cloth-store.customers.ledger') }}" class="flex items-end gap-4">
    <div class="flex-1">
      <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Search Customer</label>
      <select name="cs_customer_id" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500" required>
        <option value="">Select a Customer...</option>
        @foreach($customers as $c)
        <option value="{{ $c->id }}" {{ ($selectedCustomer && $selectedCustomer->id == $c->id) ? 'selected' : '' }}>
          {{ $c->name }} ({{ $c->phone }})
        </option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Start Date</label>
      <input type="date" name="start_date" value="{{ request('start_date') }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500">
    </div>
    <div>
      <label class="block text-xs font-bold text-slate-700 uppercase mb-1">End Date</label>
      <input type="date" name="end_date" value="{{ request('end_date') }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500">
    </div>
    <div>
      <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 shadow-sm w-full">
        Load Ledger
      </button>
    </div>
  </form>
</div>

@if($selectedCustomer)
<!-- Profile Summary -->
<div class="page grid grid-cols-4 gap-4 mb-6">
  <div class="bg-white p-4 border border-slate-200 rounded-xl shadow-sm">
    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Total Purchases</div>
    <div class="text-xl font-bold text-slate-900">Rs {{ number_format($summary['total_purchases']) }}</div>
  </div>
  <div class="bg-white p-4 border border-slate-200 rounded-xl shadow-sm">
    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Total Paid</div>
    <div class="text-xl font-bold text-emerald-600">Rs {{ number_format($summary['total_paid']) }}</div>
  </div>
  <div class="bg-white p-4 border border-slate-200 rounded-xl shadow-sm">
    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Total Returns</div>
    <div class="text-xl font-bold text-slate-600">Rs {{ number_format($summary['total_returns']) }}</div>
  </div>
  <div class="bg-white p-4 border-l-4 border-rose-500 rounded-xl shadow-sm">
    <div class="text-[10px] font-bold text-rose-500 uppercase tracking-widest mb-1">Current Due</div>
    <div class="text-xl font-bold text-rose-600">Rs {{ number_format($summary['total_due']) }}</div>
  </div>
</div>

<!-- Ledger Table -->
<div class="page bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-8">
  <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
    <h3 class="font-bold text-slate-800">Ledger Statement: {{ $selectedCustomer->name }}</h3>
    <span class="text-xs text-slate-500 font-medium">Customer ID: #{{ str_pad($selectedCustomer->id, 5, '0', STR_PAD_LEFT) }}</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm text-left">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
        <tr>
          <th class="px-5 py-3 font-bold">Date</th>
          <th class="px-5 py-3 font-bold">Reference / Invoice</th>
          <th class="px-5 py-3 font-bold">Description</th>
          <th class="px-5 py-3 font-bold text-right text-rose-600">Debit (+)</th>
          <th class="px-5 py-3 font-bold text-right text-emerald-600">Credit (-)</th>
          <th class="px-5 py-3 font-bold text-right text-slate-900">Balance</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200">
        @php
            // Calculate Opening Balance if dates are filtered
            $openingBalance = 0;
            if (request()->filled('start_date')) {
                // Get the balance of the row right before the first row in the filtered set
                // Or just show 0 if this is too complex. The ledger table 'balance' is a snapshot, so we can just use the previous row's balance.
                $firstRow = $ledgers->first();
                if($firstRow) {
                    $prev = \App\Models\ClothStore\CustomerLedger::where('cs_customer_id', $selectedCustomer->id)
                                ->where('id', '<', $firstRow->id)
                                ->orderBy('id', 'desc')
                                ->first();
                    $openingBalance = $prev ? $prev->balance : 0;
                }
            }
        @endphp
        
        @if(request()->filled('start_date'))
        <tr class="bg-slate-50">
          <td colspan="5" class="px-5 py-3 text-right font-bold text-slate-600">Opening Balance</td>
          <td class="px-5 py-3 text-right font-bold text-slate-900">Rs {{ number_format($openingBalance) }}</td>
        </tr>
        @endif

        @forelse($ledgers as $ledger)
        <tr class="hover:bg-slate-50">
          <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ \Carbon\Carbon::parse($ledger->date)->format('d M, Y') }}</td>
          <td class="px-5 py-3 font-mono text-xs font-bold text-indigo-600">{{ $ledger->reference_number }}</td>
          <td class="px-5 py-3 text-slate-800">{{ $ledger->description }}</td>
          <td class="px-5 py-3 text-right text-rose-600 font-bold">{{ $ledger->debit > 0 ? 'Rs '.number_format($ledger->debit) : '-' }}</td>
          <td class="px-5 py-3 text-right text-emerald-600 font-bold">{{ $ledger->credit > 0 ? 'Rs '.number_format($ledger->credit) : '-' }}</td>
          <td class="px-5 py-3 text-right text-slate-900 font-bold">Rs {{ number_format($ledger->balance) }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="6" class="px-5 py-12 text-center text-slate-500">No transactions found for this customer.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<style>
@media print {
  body * { visibility: hidden; }
  #app { visibility: hidden; }
  .page { box-shadow: none !important; border: none !important; }
  /* Show only the ledger table and header */
  .page:nth-of-type(4), .page:nth-of-type(4) * { visibility: visible; }
  .page:nth-of-type(3), .page:nth-of-type(3) * { visibility: visible; }
  .page:nth-of-type(3) { position: absolute; left: 0; top: 0; width: 100%; }
  .page:nth-of-type(4) { position: absolute; left: 0; top: 100px; width: 100%; }
}
</style>
@else
<div class="page flex flex-col justify-center items-center h-64 border-2 border-dashed border-slate-300 rounded-xl text-slate-500 mb-8">
  <i class="fa-solid fa-file-invoice fa-3x mb-4 text-slate-300"></i>
  <p class="text-sm font-medium">Search for a customer to load their financial ledger.</p>
</div>
@endif

@endsection
