@extends('cloth-store.layouts.app')
@section('title', 'Customers')
@section('spaPage', 'cloth-store-customers')

@section('content')
@php $filtered = request()->hasAny(['search', 'with_dues']); @endphp

<x-cloth-store.page-header
  title="Customers"
  :subtitle="number_format($stats['total']) . ' customers · ' . number_format($stats['with_dues']) . ' with an outstanding balance'">
  <x-slot:actions>
    <a href="{{ route('cloth-store.customers.ledger') }}" class="btn-cs-ghost">
      <i class="fa-solid fa-book text-[10px]"></i> Customer Ledger
    </a>
    <button class="btn-cs-primary" onclick="openModal('customer-form')">
      <i class="fa-solid fa-plus text-[10px]"></i> Add Customer
    </button>
  </x-slot:actions>
</x-cloth-store.page-header>

<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  @php
    // Built in PHP: a bound attribute is passed through without HTML-decoding,
    // so &quot; entities written inline would render literally.
    $newSub = '<span class="text-emerald-600 font-semibold">'
        . number_format($stats['new_month']) . ' new</span> this month';
  @endphp
  <x-cloth-store.stat-card
    label="Total Customers" icon="fa-users" tone="indigo"
    :value="number_format($stats['total'])"
    :sub="$newSub" />

  <x-cloth-store.stat-card
    label="Receivables" icon="fa-hand-holding-dollar" :tone="$stats['receivable'] > 0 ? 'red' : 'emerald'"
    :value="'Rs ' . number_format($stats['receivable'])"
    sub="Total outstanding across all customers" />

  <x-cloth-store.stat-card
    label="With Dues" icon="fa-circle-exclamation" :tone="$stats['with_dues'] ? 'amber' : 'slate'"
    :value="number_format($stats['with_dues'])"
    :sub="$stats['with_dues'] ? 'Need follow-up' : 'Everyone is settled'" />

  <x-cloth-store.stat-card
    label="New This Month" icon="fa-user-plus" tone="violet"
    :value="number_format($stats['new_month'])"
    :sub="'Since ' . now()->startOfMonth()->format('d M')" />
</div>

<x-cloth-store.panel>
  <x-slot:toolbar>
    {{-- Database-backed search; the page previously had no way to find a
         customer beyond paging through 20 at a time. --}}
    <form data-filter-form method="GET" action="{{ route('cloth-store.customers.index') }}"
          class="flex flex-wrap gap-3 items-center w-full">
      <div class="relative flex-1 min-w-[240px] max-w-sm">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
        <input type="search" name="search" value="{{ request('search') }}" autocomplete="off"
               placeholder="Search by name or phone..." class="input-cs w-full pl-9">
      </div>

      <label class="flex items-center gap-2 text-sm text-slate-600 font-medium cursor-pointer select-none">
        <input type="checkbox" name="with_dues" value="1" @checked(request()->boolean('with_dues'))
               class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
        Only with dues
      </label>

      @if($filtered)
        <a href="{{ route('cloth-store.customers.index') }}" class="btn-cs-ghost ml-auto">
          <i class="fa-solid fa-xmark text-[10px]"></i> Clear
        </a>
      @endif
    </form>
  </x-slot:toolbar>

  <table class="table-cs">
    <thead>
      <tr>
        <th>Customer</th>
        <th>Phone</th>
        <th>City</th>
        <th class="text-right">Due Balance</th>
        <th class="text-right">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($customers as $c)
        @php
          $initials = collect(preg_split('/\s+/', trim($c->name)))
              ->filter()->take(2)
              ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
              ->implode('') ?: '?';
          $due = (float) $c->due_balance;
        @endphp
        <tr>
          <td>
            <div class="flex items-center gap-2.5">
              <div class="avatar sm shrink-0">{{ $initials }}</div>
              <div class="min-w-0">
                <div class="cell-strong truncate">{{ $c->name }}</div>
                <div class="cell-muted">Since {{ $c->created_at?->format('M Y') ?? '—' }}</div>
              </div>
            </div>
          </td>
          <td class="text-slate-600 cell-num">{{ $c->phone ?: '—' }}</td>
          <td class="text-slate-600">{{ $c->city ?: '—' }}</td>
          <td class="text-right">
            <span class="font-semibold cell-num {{ $due > 0 ? 'text-red-600' : 'text-slate-500' }}">
              Rs {{ number_format($due) }}
            </span>
            @if($due > 0)
              <div class="text-[10px] font-bold text-red-500 uppercase tracking-wide">Outstanding</div>
            @endif
          </td>
          <td>
            <div class="flex items-center justify-end gap-1 row-actions">
              <button class="btn-cs-icon" title="Edit customer"
                      data-customer="@json($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)"
                      onclick="editCustomer(this)" aria-label="Edit {{ $c->name }}">
                <i class="fa-solid fa-pen text-xs"></i>
              </button>
              <a href="{{ route('cloth-store.customers.ledger', $c->id) }}" class="btn-cs-icon" title="View ledger" aria-label="Ledger for {{ $c->name }}">
                <i class="fa-solid fa-book text-xs"></i>
              </a>
              <a href="{{ route('cloth-store.payments.index', ['search' => $c->phone ?: $c->name]) }}" class="btn-cs-icon" title="Payments" aria-label="Payments for {{ $c->name }}">
                <i class="fa-solid fa-money-bill-wave text-xs"></i>
              </a>
              <button class="btn-cs-icon danger" title="Delete customer"
                      data-id="{{ $c->id }}" data-name="{{ $c->name }}"
                      onclick="confirmDeleteCustomer(this.dataset.id, this.dataset.name)"
                      aria-label="Delete {{ $c->name }}">
                <i class="fa-solid fa-trash text-xs"></i>
              </button>
            </div>
          </td>
        </tr>
      @empty
        <x-cloth-store.empty-state
          :colspan="5"
          icon="fa-users"
          :title="$filtered ? 'No customers match that search' : 'No customers yet'"
          :message="$filtered
              ? 'Try a different name or phone number, or clear the filters.'
              : 'Add your first customer, or create one at the till during checkout.'">
          @if(!$filtered)
            <x-slot:action>
              <button class="btn-cs-primary" onclick="openModal('customer-form')">
                <i class="fa-solid fa-plus text-[10px]"></i> Add Customer
              </button>
            </x-slot:action>
          @endif
        </x-cloth-store.empty-state>
      @endforelse
    </tbody>
  </table>

  <x-slot:footer>
    <x-cloth-store.pagination :paginator="$customers" noun="customer" />
  </x-slot:footer>
</x-cloth-store.panel>
@endsection

@push('scripts')
<script>
  /* Customer CRUD — same modal + Atelier.api pattern the Tailor system uses.
     The page previously had no create, edit or delete at all. */

  function editCustomer(btn) {
    openModal('customer-form', JSON.parse(btn.dataset.customer));
  }

  function confirmDeleteCustomer(id, name) {
    Atelier.confirmAction({
      title: `Delete ${name}?`,
      message: 'Customers with sales or payment history cannot be deleted — set them aside instead.',
      confirmLabel: 'Confirm Delete',
      danger: true,
      onConfirm: async () => {
        try {
          const res = await Atelier.api.delete(`/cloth-store/customers/${id}`);
          toast(res.message || 'Customer deleted', 'success');
          await SpaRouter.navigate(location.href, { push: false, scroll: false });
        } catch (err) {
          Atelier.reportError(err, 'Could not delete the customer');
        }
      },
    });
  }

  async function saveCustomer(id, btn) {
    const val = (sel) => document.getElementById(sel).value.trim();

    const payload = {
      name:    val('cust-name'),
      phone:   val('cust-phone') || null,
      city:    val('cust-city') || null,
      customer_level: document.getElementById('cust-level').value,
    };

    if (!payload.name) {
      toast('Customer name is required', 'error');
      return;
    }

    Atelier.setBusy(btn, true);
    try {
      if (id) {
        await Atelier.api.put(`/cloth-store/customers/${id}`, payload);
      } else {
        await Atelier.api.post(@json(route('cloth-store.customers.store')), payload);
      }

      closeModal();
      toast('Customer saved successfully', 'success');
      await SpaRouter.navigate(location.href, { push: false, scroll: false });
    } catch (err) {
      // Surfaces the server's duplicate-phone message rather than a generic one.
      Atelier.reportError(err, 'Could not save the customer');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'customer-form': (data) => {
      const isEdit = data && data.id;
      const v = (key) => isEdit ? Atelier.escapeHtml(data[key] ?? '') : '';

      const levelOption = (value) =>
        `<option value="${value}" ${isEdit && data.customer_level === value ? 'selected' : ''}>${value}</option>`;

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">${isEdit ? 'Edit Customer' : 'Add Customer'}</div>
            <div class="text-xs text-slate-500 mt-1">${isEdit ? Atelier.escapeHtml(data.name) : 'Create a new customer record'}</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto">
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="label-cs">Full Name *</label>
              <input type="text" id="cust-name" class="input-cs w-full" placeholder="e.g. Ahmed Ali" value="${v('name')}">
            </div>
            <div>
              <label class="label-cs">Phone</label>
              <input type="text" id="cust-phone" class="input-cs w-full" placeholder="0300-1234567" value="${v('phone')}">
            </div>
            <div>
              <label class="label-cs">City</label>
              <input type="text" id="cust-city" class="input-cs w-full" placeholder="Lahore" value="${v('city')}">
            </div>
            <div class="col-span-2">
              <label class="label-cs">Customer Level</label>
              <select id="cust-level" class="input-cs w-full">
                ${levelOption('New')}${levelOption('Regular')}${levelOption('VIP')}${levelOption('Wholesale')}
              </select>
            </div>
            ${isEdit ? `
              <div class="col-span-2 text-[11px] text-slate-400 -mt-1">
                Outstanding balance is calculated from sales and payments and cannot be edited here.
              </div>` : ''}
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="btn-cs-ghost" onclick="closeModal()">Cancel</button>
          <button class="btn-cs-primary" onclick="saveCustomer(${isEdit ? `'${data.id}'` : 'null'}, this)">
            <i class="fa-solid fa-check text-[10px]"></i> Save Customer
          </button>
        </div>`;
    }
  });
</script>
@endpush
