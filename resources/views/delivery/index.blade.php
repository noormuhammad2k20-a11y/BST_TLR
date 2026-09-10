@extends('layouts.app')
@section('spaPage', 'delivery')
@section('title', 'Delivery Management')

@push('styles')
<style>
  .badge::before { display: none; }
  .badge-pending { background: #FFFBEB; color: #F59E0B; }
  .badge-progress { background: #F0F9FF; color: #0EA5E9; }
  .badge-scheduled { background: #E0F2FE; color: #0284C7; }
  .badge-delivered { background: #ECFDF5; color: #10B981; }
  .badge-overdue { background: #FEF2F2; color: #EF4444; }

  /* Added modal-xl for wider modals */
  .modal.modal-xl { max-width: 900px; }

  .pulse-dot { animation: pulse 1.5s infinite; }
  @keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
  }
</style>
@endpush

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Delivery Management</h1>
    <p class="text-sm text-slate-500 mt-0.5" id="subheader">Loading deliveries...</p>
  </div>
  <div class="flex gap-2">
    <button class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 hover:border-slate-300 flex items-center gap-2 transition-colors shadow-sm" onclick="openModal('bulk-extend')"><i class="fa-solid fa-calendar-day text-[10px]"></i> Extend Due Dates</button>
    <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="filterDelivery('Ready')"><i class="fa-solid fa-comment-sms text-[11px]"></i> Notify Ready Orders</button>
  </div>
</div>

<!-- Urgent Alert -->
<div class="page bg-red-50 border border-red-200 text-red-600 p-4 rounded-xl mb-6 flex items-center justify-between shadow-sm" id="urgent-banner">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center pulse-dot">
      <i class="fa-solid fa-triangle-exclamation text-red-500 text-sm"></i>
    </div>
    <div>
      <div class="font-bold text-sm text-red-900" id="urgent-alert">Checking today's collections…</div>
      <div class="text-xs text-red-700" id="urgent-subalert">&nbsp;</div>
    </div>
  </div>
  <button class="bg-white border border-red-200 text-red-600 px-3 py-1.5 rounded-md text-xs font-semibold hover:bg-red-100 transition-colors" onclick="filterDelivery('Overdue')">View Overdue</button>
</div>

<!-- Stats Row -->
<div class="page grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5 cursor-pointer" onclick="filterDelivery('All')">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Deliveries</span>
      <div class="w-7 h-7 rounded-md bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-layer-group text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" id="stat-total">0</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">All orders on the board</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5 cursor-pointer" onclick="filterDelivery('Ready')">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Ready</span>
      <div class="w-7 h-7 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center"><i class="fa-solid fa-box-archive text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" id="stat-ready">0</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">On the shelf, awaiting collection</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5 cursor-pointer" onclick="filterDelivery('Due Today')">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Due Today</span>
      <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fa-solid fa-calendar-day text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" id="stat-duetoday">0</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">Promised for today</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5 cursor-pointer" onclick="filterDelivery('Delivered')">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Delivered</span>
      <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-circle-check text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" id="stat-delivered">0</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">Handed to the customer</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5 cursor-pointer" onclick="filterDelivery('Overdue')">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Overdue</span>
      <div class="w-7 h-7 rounded-md bg-red-50 text-red-600 flex items-center justify-center"><i class="fa-solid fa-clock text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" id="stat-overdue">0</h3>
    <p class="text-[11px] text-red-500 font-medium mt-1">Needs follow-up</p>
  </div>
</div>

<!-- Table Section -->
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
  <div class="p-5 border-b border-slate-200 flex gap-1 bg-slate-50 flex-wrap" id="deliveryPills">
    <span class="px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer" onclick="filterDelivery('All', this)">All</span>
    <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="filterDelivery('Ready', this)">Ready</span>
    <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="filterDelivery('Due Today', this)">Due Today</span>
    <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="filterDelivery('Scheduled', this)">Scheduled</span>
    <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="filterDelivery('Delivered', this)">Delivered</span>
    <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="filterDelivery('Overdue', this)">Overdue</span>
  </div>

  <!-- Bulk action bar: only takes up space once something is selected. -->
  <div id="bulk-bar" class="hidden px-5 py-3 border-b border-slate-200 bg-indigo-50 flex items-center justify-between gap-3 flex-wrap">
    <div class="text-xs font-medium text-indigo-900" id="bulk-count">0 selected</div>
    <div class="flex items-center gap-2">
      <button class="bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-md text-xs font-medium hover:bg-slate-100 transition-colors" onclick="clearSelection()">Clear</button>
      <button id="bulk-notify-btn" class="bg-emerald-500 text-white px-3.5 py-1.5 rounded-md text-xs font-semibold hover:bg-emerald-600 flex items-center gap-2 transition-colors shadow-sm" onclick="confirmBulkNotify()">
        <i class="fa-solid fa-comment-sms text-[12px]"></i> Send SMS
      </button>
    </div>
  </div>

  <div class="overflow-x-auto min-h-[300px]">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
        <tr>
          <th class="chk-col py-3 text-left font-bold">
            <label class="chk-hit" title="Select every notifiable row on this page">
              <input type="checkbox" id="select-all-chk" class="chk" onchange="toggleSelectAll(this.checked)">
            </label>
          </th>
          <th class="px-5 py-3 text-left font-bold">Order ID</th>
          <th class="px-5 py-3 text-left font-bold">Customer</th>
          <th class="px-5 py-3 text-left font-bold">Garment</th>
          <th class="px-5 py-3 text-left font-bold">Due Date</th>
          <th class="px-5 py-3 text-left font-bold">Status</th>
          <th class="px-5 py-3 text-right font-bold">Actions</th>
        </tr>
      </thead>
      <tbody id="deliveryTableBody" class="divide-y divide-slate-100">
        <!-- Rendered dynamically -->
      </tbody>
    </table>
  </div>
  <div id="pagination-footer" class="px-6 py-3 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center text-sm gap-3"></div>
</div>
@endsection

@push('scripts')
<script>
  /* ============= DATA STORE ============= */
  /* `var` throughout: the SPA router re-evaluates this script per navigation. */
  var deliveries = @json($deliveries);
  var DELIVERY_STATUSES = @json($statuses);

  var ROUTES = {
    bulkNotify: @json(route('delivery.bulk-notify')),
    bulkExtend: @json(route('orders.bulk-extend')),
  };

  /* ============= STATE ============= */
  var currentPage = 1;
  /* Page size comes from Settings → Theme & Display, so one number governs
     every table in the app. */
  var itemsPerPage = Atelier.rowsPerPage();
  var currentFilter = 'All';
  var selectedIds = new Set();

  function upsertDelivery(payload) {
    const i = deliveries.findIndex(d => d.db_id === payload.db_id);
    if (i > -1) deliveries[i] = payload; else deliveries.unshift(payload);
  }

  /**
   * Whether a collection notice makes sense for this row.
   *
   * "Ready" is the obvious case — the garments are on the shelf. An overdue row
   * qualifies too, because that is exactly the customer who needs chasing. A
   * Scheduled order that is not yet late does not: the clothes are not finished,
   * and telling someone to come in for them is how a customer arrives to an
   * empty counter.
   */
  function canNotify(d) {
    return d.status !== 'Delivered' && (d.status === 'Ready' || d.overdue);
  }

  /* ============= DELETE LOGIC ============= */
  var deleteContext = { id: '', dbId: null };

  window.confirmDelete = function(id, dbId) {
    deleteContext = { id, dbId };

    Atelier.confirm({
      variant: 'delete',
      title: `Delete delivery ${id}?`,
      message: 'This will permanently remove the delivery record. This action cannot be undone.',
      confirmLabel: 'Confirm Delete',
      onConfirm: executeDelete,
    });
  }

  window.executeDelete = async function() {
    const res = await Atelier.api.delete(`/delivery/${deleteContext.dbId}`);
    deliveries = deliveries.filter(d => d.db_id !== deleteContext.dbId);
    selectedIds.delete(deleteContext.dbId);
    renderDeliveries();
    updateStats();
    toast(res.message, 'success');
  }

  /* ============= STATUS LOGIC ============= */
  window.confirmComplete = function(id, dbId) {
    deleteContext = { id, dbId };

    Atelier.confirm({
      variant: 'approve',
      title: `Mark ${id} as Delivered?`,
      message: 'The customer has collected the garments and payment is settled.',
      confirmLabel: 'Yes, Mark Delivered',
      onConfirm: executeComplete,
    });
  }

  window.executeComplete = async function() {
    const res = await Atelier.api.patch(`/delivery/${deleteContext.dbId}/status`, {
      status: 'Delivered',
      note: 'Collected by the customer'
    });
    upsertDelivery(res.delivery);
    selectedIds.delete(deleteContext.dbId);
    renderDeliveries();
    updateStats();
    toast(`Order ${deleteContext.id} marked as Delivered`, 'success');
    Atelier.refreshCounters();
  }

  /**
   * Kept for completeness, but the table no longer offers "Mark Ready".
   * An order becomes Ready on the Orders page, after the garments are verified
   * and the customer is notified — setting it from here would skip that check.
   */
  window.changeDeliveryStatus = async function(dbId, status) {
    try {
      const res = await Atelier.api.patch(`/delivery/${dbId}/status`, { status });
      upsertDelivery(res.delivery);
      renderDeliveries();
      updateStats();
      toast(res.message, 'success');
      Atelier.refreshCounters();
    } catch (err) {
      Atelier.reportError(err, 'Could not update the delivery status');
    }
  }

  /* ============= SELECTION ============= */

  /*
   * These handlers listen for `change`, not `click`.
   *
   * The box sits inside a <label>, which widens the hit area — but it also
   * means a click can reach the input twice: once directly, and once forwarded
   * by the label. On `click` that toggles the row in and straight back out, so
   * the tick appears to do nothing or to stick. `change` fires once per actual
   * state change however many clicks arrive, which is what we want.
   */
  window.toggleRow = function(dbId, checked) {
    if (checked) selectedIds.add(dbId); else selectedIds.delete(dbId);
    syncBulkBar();
  }

  window.toggleSelectAll = function(checked) {
    // Only the rows actually on screen, so "select all" never quietly picks up
    // a hundred customers on other pages.
    visiblePageItems().filter(canNotify).forEach(d => {
      if (checked) selectedIds.add(d.db_id); else selectedIds.delete(d.db_id);
    });
    renderDeliveries();
  }

  window.clearSelection = function() {
    selectedIds.clear();
    renderDeliveries();
  }

  function syncBulkBar() {
    const bar   = document.getElementById('bulk-bar');
    const count = document.getElementById('bulk-count');
    if (!bar) return;

    bar.classList.toggle('hidden', selectedIds.size === 0);
    if (count) count.textContent = `${selectedIds.size} customer${selectedIds.size === 1 ? '' : 's'} selected`;

    const all = document.getElementById('select-all-chk');
    if (all) {
      const eligible = visiblePageItems().filter(canNotify);
      const picked   = eligible.filter(d => selectedIds.has(d.db_id)).length;

      // Half-ticked when only some of the page is selected, so the header box
      // never claims more than it means.
      all.checked       = eligible.length > 0 && picked === eligible.length;
      all.indeterminate = picked > 0 && picked < eligible.length;
      all.disabled      = eligible.length === 0;
    }
  }

  /* ============= BULK NOTIFY ============= */
  window.confirmBulkNotify = function() {
    if (selectedIds.size === 0) return;
    openModal('notify-confirm', { count: selectedIds.size });
  }

  window.runBulkNotify = async function(btn) {
    Atelier.setBusy(btn, true);

    try {
      const res = await Atelier.api.post(ROUTES.bulkNotify, {
        delivery_ids: Array.from(selectedIds),
      });



      selectedIds.clear();
      await refreshFromServer();
      openModal('notify-results', res);
    } catch (err) {
      closeModal();
      Atelier.reportError(err, 'Could not send the notifications');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  /** Pulls fresh rows without a page reload. */
  async function refreshFromServer() {
    const fresh = await Atelier.api.get(window.location.pathname + '?json=1');
    if (fresh?.deliveries) {
      deliveries = fresh.deliveries;
      renderDeliveries();
      updateStats();
      Atelier.refreshCounters();
    }
  }

  /* ============= FILTER LOGIC ============= */
  window.filterDelivery = function(status, el = null) {
    currentFilter = status;
    currentPage = 1;

    /* Selection you cannot see is selection you cannot trust: a row ticked on
       the All tab would otherwise stay in the bulk count while filtered out of
       view, and a later "Send" would message someone who is no longer on
       screen. */
    selectedIds.clear();

    if (el) {
      document.querySelectorAll('#deliveryPills span').forEach(s => {
        s.className = 'px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors';
      });
      el.className = 'px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer';
    } else {
      document.querySelectorAll('#deliveryPills span').forEach(s => {
        s.className = s.textContent.trim() === status
          ? 'px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer'
          : 'px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors';
      });
    }
    renderDeliveries();
  }

  /**
   * Overdue and Due Today are conditions rather than stored statuses, so they
   * are matched against the derived flags. That is why an order can appear
   * under both "Scheduled" and "Overdue": it is genuinely both.
   */
  function getFilteredDeliveries() {
    switch (currentFilter) {
      case 'All':       return deliveries;
      case 'Overdue':   return deliveries.filter(d => d.overdue);
      case 'Due Today': return deliveries.filter(d => d.dueToday);
      default:          return deliveries.filter(d => d.status === currentFilter);
    }
  }

  function visiblePageItems() {
    const filtered = getFilteredDeliveries();
    const start = (currentPage - 1) * itemsPerPage;
    return filtered.slice(start, start + itemsPerPage);
  }

  /* ============= RENDER LOGIC ============= */
  function renderDeliveries() {
    const list = document.getElementById('deliveryTableBody');
    const footer = document.getElementById('pagination-footer');
    if (!list) return;

    const filtered = getFilteredDeliveries();
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;

    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const paginatedItems = filtered.slice(start, end);

    const statusColors = {
      'Ready': 'badge-pending',
      'Scheduled': 'badge-scheduled',
      'Delivered': 'badge-delivered',
    };

    if (paginatedItems.length === 0) {
      list.innerHTML = Atelier.emptyRow(7, {
        icon: 'fa-box-open',
        title: deliveries.length === 0 ? 'Nothing to hand over yet' : 'No matching orders',
        message: deliveries.length === 0
          ? 'Collection records are created automatically with each order.'
          : 'Try a different filter.'
      });
      footer.innerHTML = '';
      syncBulkBar();
      return;
    }

    list.innerHTML = paginatedItems.map(d => {
      const urgent   = d.overdue || d.dueToday;
      const dataStr  = JSON.stringify(d).replace(/"/g, '&quot;');
      const eligible = canNotify(d);
      const checked  = selectedIds.has(d.db_id) ? 'checked' : '';

      /* Actions follow the status. A delivered order gets no delivery actions,
         because there is nothing left to do to it. */
      let actions = `<button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="View Order" onclick="openModal('delivery-details', ${dataStr})"><i class="fa-regular fa-eye text-xs"></i></button>`;

      if (eligible) {
        actions += `<button class="w-8 h-8 rounded-md text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 inline-flex items-center justify-center mr-1 transition-colors" title="${d.overdue ? 'Send follow-up' : 'Send SMS'}" onclick="notifyOne(${d.db_id})"><i class="fa-solid fa-comment-sms text-sm"></i></button>`;
      }

      if (d.status !== 'Delivered') {
        actions += `<button class="w-8 h-8 rounded-md text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 inline-flex items-center justify-center mr-1 transition-colors" title="Mark Delivered" onclick="confirmComplete('${d.id}', ${d.db_id})"><i class="fa-solid fa-check text-xs"></i></button>`;
      }

      actions += `<button class="w-8 h-8 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-500 inline-flex items-center justify-center transition-colors" title="Delete" onclick="confirmDelete('${d.id}', ${d.db_id})"><i class="fa-solid fa-trash text-xs"></i></button>`;

      return `
        <tr class="hover:bg-slate-50 transition-colors ${d.overdue ? 'border-l-4 border-l-red-500' : d.dueToday ? 'border-l-4 border-l-amber-400' : ''}" id="row-${d.id}">
          <td class="chk-col py-3">
            ${eligible
              ? `<label class="chk-hit"><input type="checkbox" class="chk" ${checked} onchange="toggleRow(${d.db_id}, this.checked)"></label>`
              : ''}
          </td>
          <td class="px-5 py-3 font-semibold text-slate-900">${d.id}</td>
          <td class="px-5 py-3 text-slate-600">${Atelier.escapeHtml(d.cust)}</td>
          <td class="px-5 py-3 text-slate-500">${Atelier.escapeHtml(d.gmt)}</td>
          <td class="px-5 py-3 ${urgent && d.status !== 'Delivered' ? 'text-red-500 font-semibold' : 'text-slate-600'}">${d.due}</td>
          <td class="px-5 py-3 whitespace-nowrap">
            <span class="badge ${statusColors[d.status] || 'badge-scheduled'}">${d.status}</span>
            ${d.overdue ? `<span class="badge badge-overdue ml-1">Overdue</span>` : ''}
            ${d.notified && d.status === 'Ready' ? `<i class="fa-solid fa-comment-sms text-emerald-500 text-xs ml-1" title="Customer already notified"></i>` : ''}
          </td>
          <td class="px-5 py-3 text-right whitespace-nowrap">${actions}</td>
        </tr>
      `;
    }).join('');

    // Pagination HTML
    const currentStart = start + 1;
    const currentEnd = Math.min(end, filtered.length);

    let controlsHTML = `
      <div class="text-xs text-slate-500">Showing ${currentStart} to ${currentEnd} of ${filtered.length} results</div>
      <div class="flex items-center gap-1">
        <button onclick="changeDeliveryPage(-1)" ${currentPage === 1 ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          <i class="fa-solid fa-chevron-left text-xs"></i>
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      controlsHTML += `
        <button onclick="goToDeliveryPage(${i})" class="w-8 h-8 flex items-center justify-center text-xs font-medium rounded-md transition-colors ${currentPage === i ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}">
          ${i}
        </button>
      `;
    }

    controlsHTML += `
        <button onclick="changeDeliveryPage(1)" ${currentPage === totalPages ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          <i class="fa-solid fa-chevron-right text-xs"></i>
        </button>
      </div>
    `;

    footer.innerHTML = controlsHTML;
    syncBulkBar();
  }

  window.changeDeliveryPage = function(dir) {
    const filtered = getFilteredDeliveries();
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    const newPage = currentPage + dir;
    if (newPage >= 1 && newPage <= totalPages) {
      currentPage = newPage;
      selectedIds.clear();
      renderDeliveries();
    }
  }

  window.goToDeliveryPage = function(page) {
    currentPage = page;
    selectedIds.clear();
    renderDeliveries();
  }

  /** Sends to a single customer through the same endpoint as the bulk action. */
  window.notifyOne = function(dbId) {
    selectedIds = new Set([dbId]);
    confirmBulkNotify();
  }

  window.updateStats = function() {
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.innerText = value; };

    const ready     = deliveries.filter(d => d.status === 'Ready').length;
    const delivered = deliveries.filter(d => d.status === 'Delivered').length;
    const overdue   = deliveries.filter(d => d.overdue).length;
    const dueToday  = deliveries.filter(d => d.dueToday).length;

    set('stat-total', deliveries.length);
    set('stat-ready', ready);
    set('stat-duetoday', dueToday);
    set('stat-delivered', delivered);
    set('stat-overdue', overdue);

    set('subheader', `${ready} ready for collection · ${dueToday} due today · ${overdue} overdue`);

    /* The red banner earns its place only when something is actually wrong. */
    const banner = document.getElementById('urgent-banner');
    if (banner) banner.classList.toggle('hidden', dueToday === 0 && overdue === 0);

    set('urgent-alert', overdue > 0
      ? `${overdue} order${overdue === 1 ? '' : 's'} past the promised date`
      : `${dueToday} collection${dueToday === 1 ? '' : 's'} due today`);

    set('urgent-subalert', overdue > 0
      ? `${dueToday} more due today. Call or message these customers.`
      : 'Have these ready at the counter.');
  }

  /* ============= MODAL OVERRIDES ============= */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'delivery-details': (data) => {
      const modalElement = document.getElementById('modal-content');
      if (modalElement) modalElement.classList.add('modal-xl');

      const fmt = (iso) => iso
        ? new Date(iso).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
        : null;

      /* Three real steps, driven by the order's own timestamps. The garments
         are collected from the shop, so there is no leg in between. */
      const steps = [
        { label: 'Order Created', icon: 'fa-file-pen', at: fmt(data.createdAt), note: 'Order placed and advance received.', done: true },
        { label: 'Ready for Collection', icon: 'fa-box-archive', at: fmt(data.readyAt), note: data.notified ? 'Customer has been notified.' : 'Customer not notified yet.', done: data.status !== 'Scheduled' },
        { label: 'Collected by Customer', icon: 'fa-handshake', at: fmt(data.deliveredAt), note: 'Handed over and payment settled.', done: data.status === 'Delivered' },
      ];

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">Order Details</div>
            <div class="text-xs text-slate-500 mt-0.5">Order ID: ${data.id}</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Left Column: Info & Payment -->
          <div class="space-y-5">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
              <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2"><i class="fa-solid fa-info-circle text-slate-400"></i> Order Info</h4>
              <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Customer:</span><span class="font-semibold text-slate-900">${Atelier.escapeHtml(data.cust)}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Phone:</span><span class="font-semibold text-slate-900">${Atelier.escapeHtml(data.phone || '—')}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Garment:</span><span class="font-semibold text-slate-900">${Atelier.escapeHtml(data.gmt)}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Due Date:</span><span class="font-semibold ${data.overdue ? 'text-red-500' : 'text-slate-900'}">${data.due}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Status:</span><span class="font-semibold text-slate-900">${data.status}${data.overdue ? ' · Overdue' : ''}</span></div>
              </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
              <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2"><i class="fa-solid fa-receipt text-slate-400"></i> Payment</h4>
              <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Total Amount:</span><span class="font-semibold text-slate-900">${Atelier.money(data.amount)}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Amount Paid:</span><span class="font-semibold text-emerald-600">${Atelier.money(data.amount - data.balance)}</span></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 mt-2"><span class="font-bold text-slate-900">Balance Due:</span><span class="font-bold ${data.balance > 0 ? 'text-red-500' : 'text-emerald-600'}">${Atelier.money(data.balance)}</span></div>
                ${data.balance > 0 ? `<div class="text-[11px] text-amber-600 font-medium">Collect the balance before handing over.</div>` : ''}
              </div>
            </div>
          </div>

          <!-- Right Column: Timeline -->
          <div class="bg-slate-50 p-5 rounded-xl border border-slate-100">
            <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-5 flex items-center gap-2">
              <i class="fa-solid fa-clock-rotate-left text-slate-400"></i> Collection Timeline
            </h4>
            <div class="relative pl-8 space-y-6">
              <div class="absolute left-[14px] top-2 bottom-2 w-0.5 bg-slate-200"></div>
              ${steps.map(s => `
                <div class="relative ${s.done ? '' : 'opacity-50'}">
                  <div class="absolute -left-8 top-0 w-7 h-7 rounded-full ${s.done ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400'} border-2 border-white shadow-sm flex items-center justify-center">
                    <i class="fa-solid ${s.done ? 'fa-check' : s.icon} text-[10px]"></i>
                  </div>
                  <div class="text-xs text-slate-400 font-medium">${s.at || 'Pending'}</div>
                  <div class="text-sm font-semibold ${s.done ? 'text-slate-900' : 'text-slate-700'} mt-0.5">${s.label}</div>
                  ${s.done ? `<div class="text-xs text-slate-500 mt-1">${s.note}</div>` : ''}
                </div>
              `).join('')}
            </div>
          </div>

        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Close</button>
          ${data.status !== 'Delivered' ? `
            <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600 transition-colors shadow-sm inline-flex items-center gap-2" onclick="closeModal(); confirmComplete('${data.id}', ${data.db_id})"><i class="fa-solid fa-check text-xs"></i> Mark Delivered</button>
          ` : ''}
        </div>`;
    },

    'notify-confirm': (data) => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div class="text-lg font-bold text-slate-900 tracking-tight">Send Collection Notice?</div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-6">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 flex-shrink-0">
            <i class="fa-solid fa-comment-sms"></i>
          </div>
          <div class="flex-1">
            <p class="text-sm text-slate-700">The shop's <span class="font-semibold">ORDER READY</span> message will be sent to <span class="font-bold text-emerald-600">${data.count}</span> customer${data.count === 1 ? '' : 's'}.</p>
            <p class="text-xs text-slate-500 mt-2">Order statuses are not changed — this is a collection reminder.</p>
          </div>
        </div>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
        <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600 flex items-center gap-2 transition-colors shadow-sm" onclick="runBulkNotify(this)"><i class="fa-solid fa-paper-plane text-xs"></i> Send Now</button>
      </div>`,

    'notify-results': (res) => {
      const failed = res.failed || [];
      const sent   = res.results || [];

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">Notification Results</div>
            <div class="text-xs text-slate-500 mt-0.5">${res.sent} sent · ${failed.length} failed</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto" style="max-height:60vh">
          ${sent.length ? `
            <h4 class="text-[11px] font-bold text-emerald-600 uppercase tracking-widest mb-2">Sent</h4>
            <div class="space-y-1 mb-5">
              ${sent.map(r => `
                <div class="flex items-center justify-between text-sm bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2">
                  <span class="text-slate-700">${Atelier.escapeHtml(r.customer)}</span>
                  <span class="text-xs font-semibold text-emerald-600">${Atelier.escapeHtml(r.order)}</span>
                </div>`).join('')}
            </div>` : ''}

          ${failed.length ? `
            <h4 class="text-[11px] font-bold text-red-500 uppercase tracking-widest mb-2">Could not be sent</h4>
            <div class="space-y-1">
              ${failed.map(r => `
                <div class="bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-700">${Atelier.escapeHtml(r.customer)}</span>
                    <span class="text-xs font-semibold text-red-500">${Atelier.escapeHtml(r.order)}</span>
                  </div>
                  <div class="text-xs text-red-600 mt-0.5">${Atelier.escapeHtml(r.reason || 'Unknown error')}</div>
                </div>`).join('')}
            </div>` : ''}

          ${!sent.length && !failed.length ? `<div class="text-sm text-slate-500 text-center py-6">Nothing was sent.</div>` : ''}
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="closeModal()">Done</button>
        </div>`;
    },

    'bulk-extend': () => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div>
          <div class="text-lg font-bold text-slate-900 tracking-tight">Extend Due Dates</div>
          <div class="text-xs text-slate-500 mt-1">Applies to every order that is still open</div>
        </div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-6 overflow-y-auto">
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Reason *</label>
            <select id="extend-reason" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
              <option>Power Outage</option><option>Fabric Delay</option><option>Public Holiday</option><option>Staff Shortage</option><option>Machine Repair</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Extend By (days) *</label>
            <input type="number" id="extend-days" min="1" max="90" value="1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
          </div>
        </div>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
        <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="runBulkExtend(this)"><i class="fa-solid fa-paper-plane text-xs mr-1"></i> Extend and Notify All</button>
      </div>`
  });

  window.runBulkExtend = async function(btn) {
    const days = parseInt(document.getElementById('extend-days').value, 10);
    const reason = document.getElementById('extend-reason').value;

    if (!days || days < 1) { toast('Enter how many days to extend by', 'error'); return; }

    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.post(ROUTES.bulkExtend, { days, reason });
      closeModal();
      toast(res.message, 'success');
      await refreshFromServer();
    } catch (err) {
      Atelier.reportError(err, 'Could not extend the due dates');
    } finally {
      Atelier.setBusy(btn, false);
    }
  };

  Atelier.onPageReady(() => {
    selectedIds.clear();
    updateStats();
    renderDeliveries();
  });
</script>
@endpush
