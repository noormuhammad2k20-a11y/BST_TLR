@extends('cloth-store.layouts.app')
@section('title', 'Payments Management')
@section('spaPage', 'cloth-store-payments')

@section('content')
<x-cloth-store.page-header
  title="Payments"
  subtitle="Customer due collections, refunds and daily cash flow">
  <x-slot:actions>
    <a href="{{ route('cloth-store.customers.ledger') }}" class="btn-cs-ghost">
      <i class="fa-solid fa-book text-[10px]"></i> Ledger
    </a>
    <button class="btn-cs-primary" onclick="openDrawer('due-collection')">
      <i class="fa-solid fa-hand-holding-dollar text-[10px]"></i> Collect Due
    </button>
  </x-slot:actions>
</x-cloth-store.page-header>

<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
  <x-cloth-store.stat-card
    label="Today's Cash" icon="fa-money-bill-wave" tone="emerald"
    :value="'Rs ' . number_format($kpis['today_cash'])" sub="Received in cash" />

  <x-cloth-store.stat-card
    label="Today's Digital" icon="fa-mobile-screen" tone="indigo"
    :value="'Rs ' . number_format($kpis['today_digital'])" sub="Card, bank and wallets" />

  <x-cloth-store.stat-card
    label="Today's Total" icon="fa-rupee-sign" tone="sky"
    :value="'Rs ' . number_format($kpis['today_total'])" sub="All methods combined" />

  <x-cloth-store.stat-card
    label="Outstanding Dues" icon="fa-circle-exclamation" :tone="$kpis['outstanding_dues'] > 0 ? 'red' : 'slate'"
    :value="'Rs ' . number_format($kpis['outstanding_dues'])" sub="Awaiting collection" />

  <x-cloth-store.stat-card
    label="Today's Refunds" icon="fa-rotate-left" :tone="$kpis['today_refunds'] > 0 ? 'amber' : 'slate'"
    :value="'Rs ' . number_format($kpis['today_refunds'])" sub="Returned to customers" />
</div>

<x-cloth-store.panel class="mb-6">
  <x-slot:toolbar>
    <form data-filter-form id="filterForm" method="GET" action="{{ route('cloth-store.payments.index') }}" class="flex flex-wrap gap-3 items-center w-full">
      <div class="relative flex-1 min-w-[220px] max-w-xs">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
        <input type="search" name="search" value="{{ request('search') }}" autocomplete="off"
               placeholder="Search ref or customer..." class="input-cs w-full pl-9">
      </div>
      <select name="payment_method" class="input-cs w-40">
        <option value="all">All Methods</option>
        @foreach(['Cash', 'Card', 'Bank Transfer', 'EasyPaisa', 'JazzCash', 'Cheque'] as $m)
          <option value="{{ $m }}" @selected(request('payment_method') === $m)>{{ $m }}</option>
        @endforeach
      </select>
      <div class="flex items-center gap-1.5">
        <input type="date" name="start_date" value="{{ request('start_date') }}" class="input-cs w-36">
        <span class="text-slate-400 text-xs">to</span>
        <input type="date" name="end_date" value="{{ request('end_date') }}" class="input-cs w-36">
      </div>
      @if(request()->hasAny(['search', 'start_date', 'end_date']) || (request('payment_method') && request('payment_method') !== 'all'))
        <a href="{{ route('cloth-store.payments.index') }}" class="btn-cs-ghost">
          <i class="fa-solid fa-xmark text-[10px]"></i> Clear
        </a>
      @endif
    </form>
  </x-slot:toolbar>

    <table class="table-cs">
      <thead>
        <tr>
          <th>Txn / Ref</th>
          <th>Customer</th>
          <th>Type &amp; Method</th>
          <th class="text-right">Amount</th>
          <th>Date</th>
          <th class="text-center">Status</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($payments as $p)
        @php
          $reversed = $p->status === 'Reversed';
          $cname = $p->customer->name ?? 'Unknown';
          $initials = collect(preg_split('/\s+/', trim($cname)))
              ->filter()->take(2)
              ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
              ->implode('') ?: '?';
        @endphp
        <tr class="{{ $reversed ? 'opacity-60' : '' }}">
          <td>
            <div class="cell-strong cell-num">TXN-{{ str_pad($p->id, 5, '0', STR_PAD_LEFT) }}</div>
            <div class="cell-muted">Ref: {{ $p->reference ?: '—' }}</div>
          </td>
          <td>
            <div class="flex items-center gap-2.5">
              <div class="avatar sm shrink-0">{{ $initials }}</div>
              <div class="min-w-0">
                <div class="cell-strong truncate">{{ $cname }}</div>
                <div class="cell-muted">{{ $p->customer->phone ?: 'No phone' }}</div>
              </div>
            </div>
          </td>
          <td>
            <div class="text-xs font-medium text-slate-700">{{ $p->payment_type }}</div>
            <span class="badge badge-neutral mt-1">{{ $p->payment_method }}</span>
          </td>
          <td class="text-right">
            <span class="font-semibold cell-num {{ $reversed ? 'text-slate-400 line-through' : 'text-emerald-600' }}">
              Rs {{ number_format((float) $p->amount) }}
            </span>
          </td>
          <td class="text-slate-600 whitespace-nowrap text-xs cell-num">{{ $p->payment_date->format('d M Y, h:i A') }}</td>
          <td class="text-center">
            <span class="badge {{ $reversed ? 'badge-overdue' : 'badge-delivered' }}">{{ $p->status }}</span>
          </td>
          <td>
            <div class="flex items-center justify-end gap-1 row-actions">
              <button class="btn-cs-icon" title="Print receipt" onclick="window.print()"><i class="fa-solid fa-print text-xs"></i></button>
              @if(!$reversed)
                <button onclick="reverseTransaction({{ $p->id }})" class="btn-cs-icon danger" title="Reverse transaction"><i class="fa-solid fa-rotate-left text-xs"></i></button>
              @endif
            </div>
          </td>
        </tr>
        @empty
          <x-cloth-store.empty-state
            :colspan="7"
            icon="fa-money-bill-wave"
            title="No payments found"
            message="Collections recorded against customer dues will appear here." />
        @endforelse
      </tbody>
    </table>

  <x-slot:footer>
    <x-cloth-store.pagination :paginator="$payments" noun="payment" />
  </x-slot:footer>
</x-cloth-store.panel>
@endsection

@push('scripts')
<script>
  window.drawers = window.drawers || {};

  // Customer Due Collection Drawer
  window.drawers['due-collection'] = () => `
    <div class="flex flex-col h-full bg-white shadow-2xl w-full sm:w-[450px]">
      <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
        <div>
          <h2 class="text-lg font-bold text-slate-900 tracking-tight">Customer Due Collection</h2>
          <p class="text-xs text-slate-500 mt-0.5">Record incoming payments & update ledger</p>
        </div>
        <button onclick="closeDrawer()" class="w-8 h-8 rounded-full hover:bg-slate-200 flex items-center justify-center text-slate-500"><i class="fa-solid fa-xmark"></i></button>
      </div>
      
      <div class="flex-1 overflow-y-auto p-6">
        <form id="collectionForm" onsubmit="submitCollection(event)">
          <div class="space-y-6">
            
            <!-- Customer Selection -->
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Search Customer *</label>
              <select name="cs_customer_id" id="customerSelect" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500" onchange="fetchCustomerDetails(this.value)">
                <option value="">-- Select Customer --</option>
                @foreach($customers as $c)
                  <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone ?? 'No Phone' }}) - Rs {{ number_format($c->due_balance) }} Due</option>
                @endforeach
              </select>
            </div>

            <!-- Dynamic Customer Info Card -->
            <div id="customerInfoCard" class="hidden bg-slate-50 border border-slate-200 rounded-xl p-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-xs font-bold text-slate-500 uppercase">Outstanding Balance</span>
                <span class="text-lg font-black text-rose-600" id="c-due">Rs 0</span>
              </div>
              <div class="text-[10px] text-slate-500">Payments will automatically apply to the oldest unpaid balance on the customer ledger.</div>
            </div>

            <!-- Payment Details -->
            <div id="paymentFields" class="space-y-4 opacity-50 pointer-events-none transition-opacity">
              <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Received Amount (Rs) *</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-medium text-sm">Rs</span>
                  <input type="number" name="amount" id="paymentAmount" min="1" required class="w-full pl-9 pr-3 py-2 border border-emerald-200 rounded-lg text-lg font-bold text-emerald-700 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-emerald-50">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Method *</label>
                  <select name="payment_method" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500">
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="EasyPaisa">EasyPaisa</option>
                    <option value="JazzCash">JazzCash</option>
                    <option value="Cheque">Cheque</option>
                  </select>
                </div>
                <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Reference No.</label><input type="text" name="reference" placeholder="Check/Txn ID" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500"></div>
              </div>

              <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Notes</label><textarea name="notes" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500"></textarea></div>
            </div>

          </div>
        </form>
      </div>
      
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex justify-end gap-2">
        <button class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50" type="button" onclick="closeDrawer()">Cancel</button>
        <button form="collectionForm" type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-lg hover:bg-slate-800 shadow-sm flex items-center gap-2" id="saveBtn" disabled><i class="fa-solid fa-check"></i> Record Payment</button>
      </div>
    </div>
  `;

  function fetchCustomerDetails(id) {
    if(!id) {
      document.getElementById('customerInfoCard').classList.add('hidden');
      document.getElementById('paymentFields').classList.add('opacity-50', 'pointer-events-none');
      document.getElementById('saveBtn').disabled = true;
      return;
    }

    Atelier.fetch(`/cloth-store/payments/customer/${id}`)
      .then(res => res.json())
      .then(data => {
        let c = data.customer;
        document.getElementById('customerInfoCard').classList.remove('hidden');
        document.getElementById('c-due').innerText = `Rs ${Number(c.due_balance).toLocaleString()}`;
        
        // Pre-fill amount if they have due
        let amountInput = document.getElementById('paymentAmount');
        amountInput.value = c.due_balance > 0 ? c.due_balance : '';
        
        document.getElementById('paymentFields').classList.remove('opacity-50', 'pointer-events-none');
        document.getElementById('saveBtn').disabled = false;
      });
  }

  // A payment is money moving — a double-submit here would collect twice, so
  // the in-flight flag guards the handler as well as the disabled button
  // (which a fast second Enter keypress can beat).
  var collectionSaving = false;

  async function submitCollection(e) {
    e.preventDefault();
    if (collectionSaving) return;

    const data = Object.fromEntries(new FormData(e.target));
    const btn = document.getElementById('saveBtn');

    collectionSaving = true;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

    try {
      const res = await Atelier.request(`/cloth-store/payments`, { method: 'POST', body: data });

      Atelier.paymentToast(res.message || 'Payment recorded', 'success');
      closeDrawer();
      await Atelier.refreshPage();
    } catch (err) {
      Atelier.reportError(err);
    } finally {
      collectionSaving = false;
      if (document.body.contains(btn)) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Record Payment';
      }
    }
  }

  function reverseTransaction(id) {
    Atelier.confirmAction({
      title: 'Reverse this transaction?',
      message: 'This restores the customer due balance and updates the accounting ledger. It cannot be undone.',
      confirmLabel: 'Reverse',
      onConfirm: async () => {
        await Atelier.request(`/cloth-store/payments/${id}/reverse`, { method: 'PUT' });
        await Atelier.refreshPage();
      },
      successMessage: 'Transaction reversed',
    });
  }
</script>
@endpush
