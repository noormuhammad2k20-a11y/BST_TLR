@extends('layouts.app')
@section('spaPage', 'payments-billing')
@section('title', 'Payments & Billing')

@push('styles')
<style>
  .stamp {
    transform: rotate(-15deg);
    border: 3px solid #10B981;
    color: #10B981;
    padding: 4px 12px;
    font-weight: 800;
    font-size: 18px;
    text-transform: uppercase;
    border-radius: 4px;
    opacity: 0.8;
    display: inline-block;
    font-family: 'Courier New', Courier, monospace;
  }
</style>
@endpush

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Payments & Billing</h1>
    <p class="text-sm text-slate-500 mt-0.5">Invoice management and collections</p>
  </div>
  <div class="flex gap-2">
    <button class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 hover:border-slate-300 flex items-center gap-2 transition-colors shadow-sm" onclick="exportInvoices()"><i class="fa-solid fa-print text-[10px]"></i> A4 Print</button>
    <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="openModal('create-invoice')"><i class="fa-solid fa-file-invoice text-[10px]"></i> Create Invoice</button>
  </div>
</div>

<!-- Stats Row -->
<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Collected</span>
      <div class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-sack-dollar text-[11px]"></i></div>
    </div>
    @php $collectedTrend = \App\Services\StatsService::dashboard()['trends']['revenue']; @endphp
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-summary="collected">{{ \App\Services\Money::format($totalCollected) }}</h3>
    <p class="text-[11px] {{ $collectedTrend['direction'] === 'down' ? 'text-red-500' : 'text-emerald-600' }} font-medium mt-1 flex items-center gap-1"><i class="fa-solid {{ $collectedTrend['direction'] === 'down' ? 'fa-arrow-down' : 'fa-arrow-up' }} text-[7px]"></i> {{ $collectedTrend['direction'] === 'down' ? '-' : '+' }}{{ $collectedTrend['value'] }}% this month</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Pending</span>
      <div class="w-7 h-7 rounded-md bg-red-50 text-red-600 flex items-center justify-center"><i class="fa-solid fa-hourglass-half text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-summary="pending">{{ \App\Services\Money::format($pending) }}</h3>
    <p class="text-[11px] text-red-500 font-medium mt-1" data-summary="pending_count">{{ $totalInvoices - $paidInvoices }} invoices</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Invoices</span>
      <div class="w-7 h-7 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center"><i class="fa-solid fa-file-invoice text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-summary="total">{{ $totalInvoices }}</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1" data-summary="paid_count">{{ $paidInvoices }} paid</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Collection Rate</span>
      <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-chart-pie text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-summary="rate">{{ $collectionRate }}%</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1" data-summary="settled">{{ $paidInvoices }} of {{ $totalInvoices }} settled</p>
  </div>
</div>

<!-- Table Section -->
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
  <div class="p-5 border-b border-slate-200 flex gap-1 bg-slate-50 flex-wrap items-center" id="invoiceStatusPills">
    <span class="px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer" onclick="setInvoiceStatus('All', this)">All Invoices</span>
    <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="setInvoiceStatus('Paid', this)">Paid</span>
    <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="setInvoiceStatus('Pending', this)">Pending</span>
    <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="setInvoiceStatus('Partial', this)">Partial</span>
    <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="setInvoiceStatus('Overdue', this)">Overdue</span>
    <div class="relative ml-auto">
      <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
      <input id="invoiceSearch" oninput="setInvoiceSearch(this.value)" placeholder="Search invoices..." class="w-56 h-8 pl-8 pr-3 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-slate-900 transition-all">
    </div>
  </div>
  <div class="overflow-x-auto min-h-[300px]">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
        <tr>
          <th class="px-5 py-3 text-left font-bold">Invoice</th>
          <th class="px-5 py-3 text-left font-bold">Customer</th>
          <th class="px-5 py-3 text-left font-bold">Amount</th>
          <th class="px-5 py-3 text-left font-bold">Paid</th>
          <th class="px-5 py-3 text-left font-bold">Method</th>
          <th class="px-5 py-3 text-left font-bold">Date</th>
          <th class="px-5 py-3 text-left font-bold">Status</th>
          <th class="px-5 py-3 text-right font-bold">Actions</th>
        </tr>
      </thead>
      <tbody id="invoiceTableBody" class="divide-y divide-slate-100">
        <!-- Rendered dynamically -->
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <div id="pagination-footer" class="px-6 py-3 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center text-sm gap-3"></div>
</div>
@endsection

@push('scripts')
<script>
  /* ============= DATA STORE ============= */
  /* `var` throughout: the SPA router re-evaluates this script per navigation. */
  var invoices = @json($invoices);
  var PAYMENT_METHODS = @json($methods);

  var invoiceSearch = '';
  var invoiceStatus = 'All';

  function getFilteredInvoices() {
    let list = invoices;

    if (invoiceStatus !== 'All') list = list.filter(i => i.status === invoiceStatus);

    if (invoiceSearch) {
      const q = invoiceSearch.toLowerCase();
      list = list.filter(i =>
        (i.id || '').toLowerCase().includes(q) ||
        (i.order || '').toLowerCase().includes(q) ||
        (i.cust || '').toLowerCase().includes(q) ||
        (i.gmt || '').toLowerCase().includes(q)
      );
    }

    return list;
  }

  window.setInvoiceSearch = function(v) {
    invoiceSearch = (v || '').trim();
    currentPage = 1;
    renderInvoices();
  };

  window.setInvoiceStatus = function(status, el) {
    invoiceStatus = status;
    currentPage = 1;

    if (el) {
      document.querySelectorAll('#invoiceStatusPills span').forEach(s => {
        s.className = 'px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors';
      });
      el.className = 'px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer';
    }

    renderInvoices();
  };

  /** Recomputes the four summary cards from the in-memory invoice list. */
  function recalcInvoiceStats() {
    const collected = invoices.reduce((s, i) => s + i.paid, 0);
    const invoiced  = invoices.reduce((s, i) => s + i.amt, 0);
    const paidCount = invoices.filter(i => i.balance <= 0).length;

    const set = (sel, text) => { const el = document.querySelector(sel); if (el) el.textContent = text; };

    set('[data-summary="collected"]', Atelier.money(collected));
    set('[data-summary="pending"]', Atelier.money(Math.max(invoiced - collected, 0)));
    set('[data-summary="pending_count"]', `${invoices.length - paidCount} invoices`);
    set('[data-summary="total"]', invoices.length.toLocaleString('en-IN'));
    set('[data-summary="paid_count"]', `${paidCount} paid`);
    set('[data-summary="rate"]', (invoices.length ? Math.round((paidCount / invoices.length) * 1000) / 10 : 0) + '%');
    set('[data-summary="settled"]', `${paidCount} of ${invoices.length} settled`);
  }

  /* ============= RECORD PAYMENT ============= */
  window.openRecordPayment = function(dbId) {
    const inv = invoices.find(i => i.db_id === dbId);
    if (!inv) return;
    openModal('record-payment', inv);
  };

  window.submitPayment = async function(dbId, btn) {
    const payload = {
      amount:         parseFloat(document.getElementById('pay-amount').value) || 0,
      payment_method: document.getElementById('pay-method').value,
      reference:      document.getElementById('pay-reference')?.value.trim() || null,
      notes:          document.getElementById('pay-notes')?.value.trim() || null,
    };

    if (payload.amount <= 0) { toast('Enter an amount greater than zero', 'error'); return; }

    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.post(`/payments-billing/${dbId}/record`, payload);

      const i = invoices.findIndex(x => x.db_id === dbId);
      if (i > -1) invoices[i] = res.invoice;

      closeModal();
      renderInvoices();
      recalcInvoiceStats();
      toast(res.message, 'success');
      Atelier.refreshCounters();
    } catch (err) {
      Atelier.reportError(err, 'Could not record the payment');
    } finally {
      Atelier.setBusy(btn, false);
    }
  };

  /* ============= INVOICE PREVIEW ============= */
  /**
   * Composed from the invoice row already in memory plus the shop details the
   * layout embeds, so the preview appears immediately with no request.
   */
  window.openInvoicePreview = function(dbId) {
    const inv = invoices.find(i => i.db_id === dbId);
    if (!inv) return;

    const shop = Atelier.shop;
    const taxRate = shop.taxRate || 0;
    const net = taxRate > 0 ? inv.amt / (1 + taxRate / 100) : inv.amt;

    openModal('print-preview', {
      number:   inv.id,
      order:    inv.order,
      date:     inv.date,
      store:    shop.name,
      address:  shop.address,
      phone:    shop.phone,
      terms:    shop.terms,
      customer: { name: inv.cust, phone: inv.phone },
      items:    [{ name: inv.gmt, qty: 1, price: inv.amt }],
      subtotal: Math.round(net * 100) / 100,
      tax_rate: taxRate,
      tax:      Math.round((inv.amt - net) * 100) / 100,
      total:    inv.amt,
      paid:     inv.paid,
      balance:  inv.balance,
      status:   inv.status,
    });
  };

  window.exportInvoices = function() {
    const filtered = getFilteredInvoices();
    if (!filtered.length) { toast('There is nothing to export', 'info'); return; }

    const rows = [['Invoice', 'Order', 'Customer', 'Garment', 'Total', 'Paid', 'Balance', 'Method', 'Date', 'Status']];
    filtered.forEach(i => rows.push([
      i.id, i.order, i.cust, i.gmt, i.amt, i.paid, i.balance, i.method, i.fullDate, i.status
    ]));

    const csv = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    Object.assign(document.createElement('a'), {
      href: url, download: `invoices-${new Date().toISOString().slice(0, 10)}.csv`
    }).click();
    URL.revokeObjectURL(url);
    toast(`Exported ${filtered.length} invoices`, 'success');
  };

  var currentPage = 1;
  /* Page size comes from Settings → Theme & Display, so one number governs
     every table in the app. */
  var itemsPerPage = Atelier.rowsPerPage();

  /* ============= RENDER INVOICES ============= */
  function renderInvoices() {
    const list = document.getElementById('invoiceTableBody');
    const footer = document.getElementById('pagination-footer');
    if (!list) return;

    const filtered = getFilteredInvoices();
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const paginatedItems = filtered.slice(start, end);

    list.innerHTML = paginatedItems.map(o => {
      const paidColor = o.balance <= 0 ? '#10B981' : o.paid === 0 ? '#EF4444' : '#F59E0B';
      const statusBadge = o.status === 'Paid' ? 'badge-delivered'
        : o.status === 'Partial' ? 'badge-pending'
        : o.status === 'Pending' ? 'badge-progress' : 'badge-overdue';
      const invDataStr = JSON.stringify(o).replace(/"/g, '&quot;');

      return `
        <tr class="hover:bg-slate-50 transition-colors" id="row-${o.id}">
          <td class="px-5 py-3 font-semibold text-slate-900">${o.id}<div class="text-xs text-slate-400 font-normal">${o.order}</div></td>
          <td class="px-5 py-3 text-slate-600">${Atelier.escapeHtml(o.cust)}<div class="text-xs text-slate-400">${Atelier.escapeHtml(o.gmt)}</div></td>
          <td class="px-5 py-3 font-semibold text-slate-900">${Atelier.money(o.amt)}</td>
          <td class="px-5 py-3 font-semibold" style="color:${paidColor}">${Atelier.money(o.paid)}${o.balance > 0 ? `<div class="text-xs text-red-500 font-normal">${Atelier.money(o.balance)} due</div>` : ''}</td>
          <td class="px-5 py-3 text-slate-600">${o.method}</td>
          <td class="px-5 py-3 text-slate-600">${o.date}</td>
          <td class="px-5 py-3"><span class="badge ${statusBadge}">${o.status}</span></td>
          <td class="px-5 py-3 text-right whitespace-nowrap">
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="View Details" onclick="openModal('invoice-details', ${invDataStr})"><i class="fa-regular fa-eye text-xs"></i></button>
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="Print" onclick="openInvoicePreview(${o.db_id})"><i class="fa-solid fa-print text-xs"></i></button>
            ${o.balance > 0 ? `<button class="ml-1 bg-slate-900 text-white px-2 py-1 rounded-md text-xs font-medium hover:bg-slate-800 inline-flex items-center gap-1 transition-colors shadow-sm" onclick="openRecordPayment(${o.db_id})"><i class="fa-solid fa-indian-rupee-sign text-[10px]"></i> Record</button>` : ''}
            ${o.status === 'Paid' ? `<button class="ml-1 bg-emerald-500 text-white px-2 py-1 rounded-md text-xs font-medium hover:bg-emerald-600 inline-flex items-center gap-1 transition-colors shadow-sm" onclick="openModal('final-receipt', ${invDataStr})"><i class="fa-solid fa-receipt text-[10px]"></i> Final Receipt</button>` : ''}
          </td>
        </tr>
      `;
    }).join('');

    // Pagination HTML
    if (filtered.length === 0) {
      list.innerHTML = Atelier.emptyRow(8, {
        icon: 'fa-receipt',
        title: invoices.length === 0 ? 'No invoices yet' : 'No matching invoices',
        message: invoices.length === 0
          ? 'Invoices are created automatically when you take an order.'
          : 'Try a different search term or status filter.'
      });
      footer.innerHTML = `<div class="text-xs text-slate-500 w-full text-center">Showing 0 of ${invoices.length} results</div>`;
      return;
    }

    const currentStart = start + 1;
    const currentEnd = Math.min(end, filtered.length);

    let controlsHTML = `
      <div class="text-xs text-slate-500">Showing ${currentStart} to ${currentEnd} of ${filtered.length} results</div>
      <div class="flex items-center gap-1">
        <button onclick="changeInvoicePage(-1)" ${currentPage === 1 ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          <i class="fa-solid fa-chevron-left text-xs"></i>
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      controlsHTML += `
        <button onclick="goToInvoicePage(${i})" class="w-8 h-8 flex items-center justify-center text-xs font-medium rounded-md transition-colors ${currentPage === i ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}">
          ${i}
        </button>
      `;
    }

    controlsHTML += `
        <button onclick="changeInvoicePage(1)" ${currentPage === totalPages ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          <i class="fa-solid fa-chevron-right text-xs"></i>
        </button>
      </div>
    `;

    footer.innerHTML = controlsHTML;
  }

  window.changeInvoicePage = function(dir) {
    const totalPages = Math.ceil(getFilteredInvoices().length / itemsPerPage);
    const newPage = currentPage + dir;
    if (newPage >= 1 && newPage <= totalPages) {
      currentPage = newPage;
      renderInvoices();
    }
  }

  window.goToInvoicePage = function(page) {
    currentPage = page;
    renderInvoices();
  }

  /* ============= MODAL OVERRIDES ============= */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'create-invoice': () => {
      // Invoices are issued with the order, so this routes to the order wizard
      // and lists anything still awaiting payment.
      const outstanding = invoices.filter(i => i.balance > 0).slice(0, 6);

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">Create Invoice</div>
            <div class="text-xs text-slate-500 mt-1">Every order issues its own invoice automatically</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto">
          <div class="flex items-start gap-3 p-3 rounded-lg bg-indigo-50 border border-indigo-100 mb-5">
            <i class="fa-solid fa-circle-info text-indigo-600 text-xs mt-0.5"></i>
            <p class="text-xs text-indigo-800 leading-relaxed flex-1">Invoice numbers are generated when an order is created, so totals and balances always reconcile. Start a new order to raise a new invoice.</p>
          </div>

          <div class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Awaiting Payment</div>
          <div class="space-y-2">
            ${outstanding.map(i => `
              <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50 hover:bg-slate-100 cursor-pointer transition-colors" onclick="closeModal(); openRecordPayment(${i.db_id})">
                <div>
                  <div class="text-sm font-semibold text-slate-900">${i.id} · ${Atelier.escapeHtml(i.cust)}</div>
                  <div class="text-xs text-slate-500">${Atelier.escapeHtml(i.gmt)} · ${i.date}</div>
                </div>
                <div class="text-sm font-bold text-red-500">${Atelier.money(i.balance)}</div>
              </div>
            `).join('') || '<div class="text-center text-slate-400 py-6 text-sm">Every invoice is settled</div>'}
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="window.location.href='{{ route('orders.index') }}?action=create'">New Order & Invoice</button>
        </div>`;
    },
    'record-payment': (d) => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div>
          <div class="text-lg font-bold text-slate-900 tracking-tight">Record Payment</div>
          <div class="text-xs text-slate-500 mt-1">${d.id} · ${Atelier.escapeHtml(d.cust)}</div>
        </div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-6 overflow-y-auto">
        <div class="bg-slate-50 p-4 rounded-lg mb-5 space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-slate-500">Invoice Total:</span><span class="font-semibold text-slate-900">${Atelier.money(d.amt)}</span></div>
          <div class="flex justify-between"><span class="text-slate-500">Already Paid:</span><span class="font-semibold text-emerald-600">${Atelier.money(d.paid)}</span></div>
          <div class="flex justify-between border-t border-slate-200 pt-2"><span class="font-bold text-red-500">Balance Due:</span><span class="font-bold text-red-500">${Atelier.money(d.balance)}</span></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Amount (${Atelier.currency}) *</label>
            <input type="number" id="pay-amount" step="0.01" min="0.01" max="${d.balance}" value="${d.balance}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Method *</label>
            <select id="pay-method" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
              ${PAYMENT_METHODS.map(m => `<option>${m}</option>`).join('')}
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Reference</label>
            <input type="text" id="pay-reference" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Transaction / cheque no.">
          </div>
          <div class="col-span-2">
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Notes</label>
            <textarea id="pay-notes" rows="2" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Optional notes..."></textarea>
          </div>
        </div>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
        <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600 flex items-center gap-2 transition-colors shadow-sm" onclick="submitPayment(${d.db_id}, this)"><i class="fa-solid fa-check text-xs"></i> Record Payment</button>
      </div>
    `,
    'invoice-details': (data) => {
      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div class="text-lg font-bold text-slate-900 tracking-tight">Invoice Details</div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto">
          <div class="bg-slate-50 p-4 rounded-lg mb-6">
            <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3">Summary</h4>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div class="text-slate-500">Invoice ID:</div><div class="font-semibold text-slate-900">${data.id}</div>
              <div class="text-slate-500">Customer:</div><div class="font-semibold text-slate-900">${data.cust}</div>
              <div class="text-slate-500">Garment:</div><div class="font-semibold text-slate-900">${data.gmt}</div>
              <div class="text-slate-500">Date:</div><div class="font-semibold text-slate-900">${data.date}</div>
            </div>
          </div>
          <div class="border border-slate-200 rounded-lg p-4">
            <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3">Payment Breakdown</h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span class="text-slate-500">Total Amount:</span><span class="font-semibold text-slate-900">${Atelier.money(data.amt)}</span></div>
              <div class="flex justify-between"><span class="text-slate-500">Amount Paid:</span><span class="font-semibold text-emerald-600">${Atelier.money(data.paid)}</span></div>
              <div class="flex justify-between border-t border-slate-200 pt-2 mt-2"><span class="font-bold text-slate-900">Balance Due:</span><span class="font-bold text-red-500">${Atelier.money(data.balance)}</span></div>
              <div class="flex justify-between pt-2"><span class="text-slate-500">Payment Method:</span><span class="font-semibold text-slate-900">${data.method}</span></div>
              <div class="flex justify-between"><span class="text-slate-500">Status:</span><span class="badge ${data.status === 'Paid' ? 'badge-delivered' : data.status === 'Partial' ? 'badge-pending' : 'badge-overdue'}">${data.status}</span></div>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Close</button>
          ${data.balance > 0 ? `<button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="closeModal(); openRecordPayment(${data.db_id})">Record Payment</button>` : ''}
        </div>`;
    },
    'print-preview': (data) => {
      const isSpecific = data && data.id;
      const invId = isSpecific ? data.id : 'INV-1042';
      const inv = data || {};

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center no-print">
          <div class="text-lg font-bold text-slate-900 tracking-tight">Print Preview</div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 bg-slate-100 overflow-y-auto flex justify-center" style="max-height:60vh;">
          <div class="bg-white p-8 shadow-sm w-full" id="printable-invoice" style="max-width:400px; color:#000; font-family: 'Courier New', monospace;">
             <div class="text-center font-bold text-lg mb-2">${Atelier.escapeHtml((inv.store || 'Atelier').toUpperCase())}</div>
             <div class="text-center text-xs mb-4">${Atelier.escapeHtml(inv.address || '')}${inv.phone ? ' | ' + Atelier.escapeHtml(inv.phone) : ''}</div>
             <div class="border-t-2 border-dashed border-slate-400 my-2"></div>
             <div class="flex justify-between text-sm mb-1"><span>Invoice #:</span><span class="font-bold">${Atelier.escapeHtml(inv.number || '')}</span></div>
             <div class="flex justify-between text-sm mb-1"><span>Order #:</span><span class="font-bold">${Atelier.escapeHtml(inv.order || '')}</span></div>
             <div class="flex justify-between text-sm mb-1"><span>Customer:</span><span class="font-bold">${Atelier.escapeHtml(inv.customer?.name || '')}</span></div>
             <div class="flex justify-between text-sm mb-4"><span>Date:</span><span class="font-bold">${Atelier.escapeHtml(inv.date || '')}</span></div>
             <div class="border-t-2 border-dashed border-slate-400 my-2"></div>
             ${(inv.items || []).map(it => `
               <div class="flex justify-between text-sm mb-1"><span>${Atelier.escapeHtml(it.name)} x${it.qty}</span><span>${Atelier.money(it.price)}</span></div>
               ${it.desc ? `<div class="text-xs text-slate-500 mb-2">${Atelier.escapeHtml(it.desc)}</div>` : ''}
             `).join('')}
             <div class="border-t-2 border-dashed border-slate-400 my-2"></div>
             ${inv.tax_rate > 0 ? `
               <div class="flex justify-between text-sm"><span>Subtotal:</span><span>${Atelier.money(inv.subtotal, true)}</span></div>
               <div class="flex justify-between text-sm"><span>Tax (${inv.tax_rate}%):</span><span>${Atelier.money(inv.tax, true)}</span></div>
             ` : ''}
             <div class="flex justify-between font-bold text-lg mt-2"><span>Total:</span><span>${Atelier.money(inv.total)}</span></div>
             <div class="flex justify-between text-sm mt-1"><span>Paid:</span><span>${Atelier.money(inv.paid)}</span></div>
             <div class="flex justify-between text-sm font-bold"><span>Balance:</span><span>${Atelier.money(inv.balance)}</span></div>
             <div class="border-t-2 border-dashed border-slate-400 my-2 mt-4"></div>
             ${inv.terms ? `<div class="text-[10px] mt-2">${Atelier.escapeHtml(inv.terms)}</div>` : ''}
             <div class="text-center text-xs mt-4">Thank you for your business!</div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2 no-print">
          <button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 flex items-center gap-2 transition-colors" onclick="window.print()"><i class="fa-solid fa-file-pdf text-xs"></i> Save as PDF</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="window.print()"><i class="fa-solid fa-print text-xs"></i> Print</button>
        </div>`;
    },
    'final-receipt': (data) => {
      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div><div class="text-lg font-bold text-slate-900 tracking-tight">FINAL DELIVERY RECEIPT</div><div class="text-xs text-slate-500">Atelier · ${data.id}</div></div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto space-y-4">
          <div class="flex justify-center items-center py-6 relative">
            <div class="stamp">PAID IN FULL ✓</div>
          </div>
          <div class="bg-slate-50 p-4 rounded-lg">
            <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3">Order Summary</h4>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div class="text-slate-500">Customer:</div><div class="font-semibold text-slate-900">${data.cust}</div>
              <div class="text-slate-500">Garment:</div><div class="font-semibold text-slate-900">${data.gmt}</div>
              <div class="text-slate-500">Total Amount:</div><div class="font-semibold text-slate-900">${Atelier.money(data.amt)}</div>
              <div class="text-slate-500">Payment Method:</div><div class="font-semibold text-slate-900">${data.method}</div>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 flex items-center gap-2 transition-colors" onclick="window.print()"><i class="fa-solid fa-print text-xs"></i> Print</button>
          <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600 flex items-center gap-2 transition-colors shadow-sm" onclick="markDelivered(${data.db_id}, this)"><i class="fa-solid fa-truck text-xs"></i> Mark Delivered</button>
        </div>`;
    }
  });

  window.markDelivered = async function(dbId, btn) {
    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.patch(`/orders/${dbId}/status`, {
        status: 'Delivered',
        note: 'Marked delivered from the final receipt'
      });
      const inv = invoices.find(i => i.db_id === dbId);
      if (inv) inv.status = 'Paid';

      closeModal();
      renderInvoices();
      toast(res.message, 'success');
      Atelier.refreshCounters();
    } catch (err) {
      Atelier.reportError(err, 'Could not mark the order delivered');
    } finally {
      Atelier.setBusy(btn, false);
    }
  };

  Atelier.onPageReady(() => {
    renderInvoices();

    const highlight = parseInt(new URLSearchParams(window.location.search).get('highlight'), 10);
    if (highlight) {
      const inv = invoices.find(i => i.db_id === highlight);
      if (inv) openModal('invoice-details', inv);
    }
  });
</script>
@endpush
