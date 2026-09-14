@extends('layouts.app')
@section('title', 'Customer Ledger')
@section('spaPage', 'customer-ledger')
@section('content')
<div class="page flex justify-between items-center mb-6">
  <div><h1 class="text-xl font-bold text-slate-900">Customer Ledger</h1><p class="text-sm text-slate-500 mt-1">{{ $customer->name }} · {{ $customer->phone }} · {{ $customer->display_code }}</p></div>
  <a href="{{ route('customers.index') }}" class="bg-white border border-slate-200 px-4 py-2 rounded-lg text-sm">Back to Customers</a>
</div>
<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
@foreach(['previous'=>'Previous Dues','sales'=>'Lifetime Orders / Sales','paid'=>'Lifetime Payments Received','due'=>'Current Total Due'] as $key=>$label)
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm"><div class="text-xs text-slate-500 font-semibold mb-2">{{ $label }}</div><div class="text-2xl font-bold {{ $key==='due' && $statement['due']>0 ? 'text-red-500' : 'text-slate-900' }}">{{ \App\Services\Money::format($statement[$key],true) }}</div></div>
@endforeach
</div>
<div class="page grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
  <form id="ledger-payment" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm" onsubmit="saveLedger(event,'payment')">
    <h2 class="font-bold text-slate-900 mb-1">Receive Payment</h2><p class="text-xs text-slate-500 mb-4">No new order needed. Payment clears the oldest outstanding entries first.</p>
    <label class="block text-xs text-slate-500 mb-1">Amount received</label>
    <input name="amount" type="number" step="0.01" min="0.01" max="{{ $statement['due'] }}" required class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 w-full mb-3" placeholder="0.00">
    <label class="block text-xs text-slate-500 mb-1">Payment method</label>
    <select name="payment_method" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 w-full mb-3">@foreach(\App\Models\Payment::METHODS as $method)<option>{{ $method }}</option>@endforeach</select>
    <label class="block text-xs text-slate-500 mb-1">Payment date</label>
    <input name="date" type="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 w-full mb-3">
    <input name="notes" maxlength="1000" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 w-full mb-4" placeholder="Reference / notes (optional)">
    <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-semibold" {{ $statement['due']<=0 ? 'disabled' : '' }}>Save Payment</button>
    <p class="ledger-error text-sm text-red-500 mt-3" role="alert"></p>
  </form>
  <form id="ledger-charge" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm" onsubmit="saveLedger(event,'charge')">
    <h2 class="font-bold text-slate-900 mb-1">Add Cloth Charges / Old Balance</h2><p class="text-xs text-slate-500 mb-4">Existing orders appear automatically. Only enter cloth charges or old balances not already recorded in this system.</p>
    <select name="type" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 w-full mb-3"><option value="Cloth Sale">Cloth Charges — For fabric/cloth sold to the customer</option><option value="Opening Due">Old Balance — For previous unpaid amount</option></select>
    <input name="description" required maxlength="255" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 w-full mb-3" placeholder="Description / sale reference">
    <input name="amount" type="number" required min="0.01" step="0.01" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 w-full mb-3" placeholder="Amount">
    <input name="date" type="date" required value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 w-full mb-4">
    <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-semibold">Add Entry</button>
    <p class="ledger-error text-sm text-red-500 mt-3" role="alert"></p>
  </form>
</div>
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
  <div class="p-5 border-b border-slate-200 flex justify-between"><h2 class="font-bold text-slate-900">Payment &amp; Sales History</h2><span class="text-sm font-semibold">{{ $statement['status'] }}</span></div>
  <div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead class="bg-slate-50 text-xs text-slate-500"><tr>@foreach(['Date','Reference','Description','Due / Sale','Received','Balance'] as $label)<th class="px-5 py-3">{{ $label }}</th>@endforeach</tr></thead>
  <tbody>@forelse($statement['rows'] as $row)<tr class="border-t border-slate-100 hover:bg-slate-50"><td class="px-5 py-3 whitespace-nowrap">{{ \App\Services\Dates::format($row['date']) }}</td><td class="px-5 py-3">{{ $row['reference'] }}</td><td class="px-5 py-3">{{ $row['description'] }}</td><td class="px-5 py-3">{{ $row['debit']>0 ? \App\Services\Money::format($row['debit'],true) : '—' }}</td><td class="px-5 py-3 text-emerald-600">{{ $row['credit']>0 ? \App\Services\Money::format($row['credit'],true) : '—' }}</td><td class="px-5 py-3 font-semibold">{{ \App\Services\Money::format($row['balance'],true) }}</td></tr>@empty<tr><td colspan="6" class="p-6 text-center text-slate-500">No ledger entries yet.</td></tr>@endforelse</tbody></table></div>
</div>
@endsection
@push('scripts')
<script>
window.saveLedger = async function(event,kind) {
  event.preventDefault(); const form=event.target, button=form.querySelector('button');
  form.dataset.operationKey ||= crypto.randomUUID();
  const data=Object.fromEntries(new FormData(form)); data.operation_key=form.dataset.operationKey;
  Atelier.setBusy(button,true); form.querySelector('.ledger-error').textContent='';
  try {
    await Atelier.api.post(@json(url('customers/'.$customer->id.'/ledger'))+'/'+(kind==='payment'?'payments':'charges'),data);
    window.location.reload();
  } catch(error) { form.querySelector('.ledger-error').textContent=Object.values(error.errors || {}).flat().join(' ') || error.message; }
  finally { Atelier.setBusy(button,false); }
};
</script>
@endpush
