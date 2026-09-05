@extends('cloth-store.layouts.app')
@section('title', 'Customer Loyalty')
@section('spaPage', 'cloth-store-loyalty')

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Customer Loyalty</h1>
    <p class="text-sm text-slate-500 mt-0.5">Manage customer rewards, levels, and point adjustments.</p>
  </div>
  <div>
    <button onclick="document.getElementById('adjustModal').classList.remove('hidden')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 flex items-center gap-2 shadow-sm">
      <i class="fa-solid fa-star"></i> Adjust Points
    </button>
  </div>
</div>

@if(!$loyaltyEnabled)
<div class="page bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-xl shadow-sm mb-6 flex items-center gap-3">
  <i class="fa-solid fa-triangle-exclamation text-amber-500 text-xl"></i>
  <div>
    <h4 class="font-bold">Loyalty System is Currently Disabled</h4>
    <p class="text-sm mt-0.5">Customers will not automatically earn points on checkout. You can enable this in System Settings.</p>
  </div>
</div>
@endif

<div class="page grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
  
  <!-- Left Col: Top Customers -->
  <div class="lg:col-span-2 space-y-6">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
        <h3 class="font-bold text-slate-800">Top Customers (By Points)</h3>
        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">Leaderboard</span>
      </div>
      <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
          <tr>
            <th class="px-5 py-3 font-bold">Rank</th>
            <th class="px-5 py-3 font-bold">Customer</th>
            <th class="px-5 py-3 font-bold">Level</th>
            <th class="px-5 py-3 font-bold text-right">Total Points</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          @forelse($topCustomers as $index => $customer)
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 text-slate-400 font-bold">#{{ $index + 1 }}</td>
            <td class="px-5 py-3 font-bold text-slate-800">{{ $customer->name }}</td>
            <td class="px-5 py-3">
              @if($customer->customer_level == 'VIP') <span class="badge badge-progress text-[10px]"><i class="fa-solid fa-crown mr-1"></i> VIP</span>
              @elseif($customer->customer_level == 'Wholesale') <span class="badge badge-paid text-[10px]">Wholesale</span>
              @elseif($customer->customer_level == 'Regular') <span class="badge badge-primary text-[10px]">Regular</span>
              @else <span class="badge bg-slate-100 text-slate-600 text-[10px]">New</span> @endif
            </td>
            <td class="px-5 py-3 text-right text-emerald-600 font-bold text-lg">{{ number_format($customer->loyalty_points) }} <span class="text-xs font-normal text-slate-400">pts</span></td>
          </tr>
          @empty
          <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">No customers have earned loyalty points yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right Col: Recent Transactions -->
  <div class="lg:col-span-1">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden h-full">
      <div class="px-5 py-4 border-b border-slate-200 bg-slate-50">
        <h3 class="font-bold text-slate-800">Loyalty History</h3>
      </div>
      <div class="p-0">
        <ul class="divide-y divide-slate-100 max-h-[500px] overflow-y-auto">
          @forelse($transactions as $txn)
          <li class="p-4 hover:bg-slate-50">
            <div class="flex justify-between items-start mb-1">
              <div class="font-bold text-slate-800 text-sm">{{ $txn->customer->name ?? 'Unknown' }}</div>
              <div class="text-xs font-bold {{ $txn->type == 'Redeemed' ? 'text-rose-600' : 'text-emerald-600' }}">
                {{ $txn->type == 'Redeemed' ? '-' : '+' }}{{ number_format($txn->points) }} pts
              </div>
            </div>
            <div class="flex justify-between items-center text-xs text-slate-500 mt-2">
              <div class="truncate pr-2">{{ $txn->description }}</div>
              <div class="whitespace-nowrap">{{ $txn->created_at->diffForHumans() }}</div>
            </div>
          </li>
          @empty
          <li class="p-8 text-center text-slate-400 text-sm">No recent loyalty transactions.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Adjust Points -->
<div id="adjustModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="document.getElementById('adjustModal').classList.add('hidden')"></div>
    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
    <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
      <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-slate-100">
        <div class="sm:flex sm:items-start">
          <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
            <i class="fa-solid fa-star text-indigo-600"></i>
          </div>
          <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
            <h3 class="text-lg leading-6 font-bold text-slate-900">Adjust Loyalty Points</h3>
            <p class="text-sm text-slate-500 mt-1">Manually award or deduct points for a customer.</p>
          </div>
        </div>
      </div>
      <form id="loyalty-adjust-form" action="{{ route('cloth-store.loyalty.adjust') }}" method="POST">
        @csrf
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Customer</label>
            <select name="cs_customer_id" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500" required>
              <option value="">Select a customer...</option>
              @foreach(\App\Models\ClothStore\Customer::orderBy('name')->get() as $c)
              <option value="{{ $c->id }}">{{ $c->name }} (Bal: {{ $c->loyalty_points }} pts)</option>
              @endforeach
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Type</label>
              <select name="type" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500" required>
                <option value="Earned">Award Points (+)</option>
                <option value="Redeemed">Deduct Points (-)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Points</label>
              <input type="number" name="points" min="1" step="0.01" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500" required>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description (Optional)</label>
            <input type="text" name="description" placeholder="e.g., Apology bonus, Manual redemption..." class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500">
          </div>
        </div>
        <div class="bg-slate-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-slate-100">
          <button type="submit" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Adjust Points</button>
          <button type="button" onclick="document.getElementById('adjustModal').classList.add('hidden')" class="mt-3 w-full inline-flex justify-center rounded-lg border border-slate-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-slate-700 hover:bg-slate-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
    /* This form was a plain POST, i.e. a full page reload on every points
       adjustment. Atelier.ajaxForm is opt-in — it is not bound automatically —
       so the page needed this block to get the same no-reload behaviour the
       rest of the Cloth Store has. */
    Atelier.onPageReady(() => {
        Atelier.ajaxForm('#loyalty-adjust-form', {
            onSuccess: async (res) => {
                toast(res?.message || 'Loyalty points adjusted', 'success');
                document.getElementById('adjustModal')?.classList.add('hidden');
                await Atelier.refreshPage();
            },
        });
    });
</script>
@endpush
