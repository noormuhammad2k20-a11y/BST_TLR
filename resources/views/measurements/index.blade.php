@extends('layouts.app')
@section('spaPage', 'measurements')
@section('title', 'Measurements')

@section('content')
<!-- Header -->
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Measurement Management</h1>
    <p class="text-sm text-slate-500 mt-0.5">Customer measurements: save, update and print. No order required.</p>
  </div>
  <div class="flex gap-2">
      <a href="{{ route('customers.import') }}" class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 hover:border-slate-300 flex items-center gap-2 transition-colors shadow-sm" title="Bulk import customers and their measurements from a spreadsheet"><i class="fa-solid fa-file-import text-[10px]"></i> Import</a>
      <button class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 hover:border-slate-300 flex items-center gap-2 transition-colors shadow-sm" onclick="openModal('print-preview')"><i class="fa-solid fa-print text-[10px]"></i> Print Sheet</button>
      <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="openModal('add-measurement')"><i class="fa-solid fa-plus text-[10px]"></i> Add Measurements</button>
  </div>
</div>

<!-- Stats Row -->
<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Records</span>
      <div class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-database text-[11px]"></i></div>
    </div>
    @php $thisWeek = $measurementsData->where('created_at', '>=', now()->startOfWeek())->count(); @endphp
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight">{{ number_format($stats['total']) }}</h3>
    <p class="text-[11px] text-emerald-600 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-arrow-up text-[7px]"></i> +{{ $thisWeek }} this week</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Customers Measured</span>
      <div class="w-7 h-7 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center"><i class="fa-solid fa-file-lines text-[11px]"></i></div>
    </div>
    @php $catalogued = $garmentTypes->count(); @endphp
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight">{{ number_format($measurementsData->pluck('customer_id')->unique()->count()) }}</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">Customers with saved measurements</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Recent Updates</span>
      <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fa-solid fa-clock-rotate-left text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight">{{ number_format($stats['recent']) }}</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">Last 30 days</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Fields Filled</span>
      <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-bullseye text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $stats['accuracy'] }}</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">Field completion rate</p>
  </div>
</div>

<!-- Section 1: Search & Filter Bar -->
<div class="page bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 flex flex-col xl:flex-row gap-3 items-center">
  <div class="relative flex-1 w-full">
    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
    <input type="text" id="measurementSearch" oninput="filterMeasurements()" placeholder="Search by customer name" class="w-full h-10 pl-10 pr-4 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition">
  </div>
  <div class="flex flex-wrap gap-2 w-full xl:w-auto justify-end">
    <select id="statusFilter" onchange="filterMeasurements()" class="w-full sm:w-48 h-10 px-4 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-900 font-medium transition-all">
      <option value="all">All Measurements</option>
      <option value="completed">Mostly filled</option>
      <option value="pending">Partly filled</option>
      <option value="revision">Few fields filled</option>
    </select>
    <select id="garmentFilter" onchange="filterMeasurements()" class="w-full sm:w-36 h-10 px-4 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-900 font-medium transition-all">
      <option value="">Garment Type</option>
      @foreach($measurementsData->pluck('garment_type')->filter()->unique()->sort() as $type)
      <option value="{{ $type }}">{{ $type }}</option>
      @endforeach
    </select>
    <select id="tailorFilter" onchange="filterMeasurements()" class="w-full sm:w-36 h-10 px-4 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-900 font-medium transition-all">
      <option value="">All Units</option><option value="in">Inches (in)</option><option value="cm">Centimetres (cm)</option>
    </select>
    <button class="w-full sm:w-auto bg-slate-900 text-white px-4 h-10 rounded-lg text-xs font-semibold hover:bg-slate-800 flex items-center justify-center gap-2 transition-colors shadow-sm" onclick="exportData()"><i class="fa-solid fa-file-export text-[10px]"></i> Export</button>
  </div>
</div>

<!-- Section 2: Recent Measurements Table -->
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
  <div class="overflow-x-auto min-h-[300px]">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
        <tr>
          <th class="px-5 py-3 text-left font-bold">Customer Name</th>
          <th class="px-5 py-3 text-left font-bold">Garment Type</th>
          <th class="px-5 py-3 text-left font-bold">Unit</th>
          <th class="px-5 py-3 text-left font-bold">Last Updated</th>
          <th class="px-5 py-3 text-left font-bold">Fields Filled</th>
          <th class="px-5 py-3 text-right font-bold">Actions</th>
        </tr>
      </thead>
      <tbody id="measurementTableBody" class="divide-y divide-slate-100">
        @forelse($measurementsData as $m)
        @php
          $completeness = $m->completeness;
          $rowStatus = $completeness >= 80 ? 'completed' : ($completeness >= 40 ? 'pending' : 'revision');
          $rowBadge = ['completed' => 'badge-delivered', 'pending' => 'badge-pending', 'revision' => 'badge-overdue'][$rowStatus];
          $rowLabel = ['completed' => 'Mostly filled', 'pending' => 'Partly filled', 'revision' => 'Few fields filled'][$rowStatus];
        @endphp
        <tr class="hover:bg-slate-50 transition-colors" data-status="{{ $rowStatus }}" data-garment="{{ $m->garment_type }}" data-tailor="{{ $m->unit }}" data-name="{{ strtolower($m->customer->name ?? '') }}" id="row-{{ $m->id }}">
          <td class="px-5 py-3 font-semibold text-slate-900">{{ $m->customer->name ?? 'Unknown' }}</td>
          <td class="px-5 py-3 text-slate-600">{{ $m->garment_type }}</td>
          <td class="px-5 py-3 text-slate-600">{{ $m->unit === 'in' ? 'Inches (in)' : 'Centimetres (cm)' }}</td>
          <td class="px-5 py-3 text-slate-500">{{ $m->updated_at->format('d M Y') }}</td>
          <td class="px-5 py-3">
             <span class="badge {{ $rowBadge }}">{{ $rowLabel }}</span>
          </td>
          <td class="px-5 py-3 text-right whitespace-nowrap">
              <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="View Details" onclick='openModal("view-measurement", @json($m))'><i class="fa-regular fa-eye text-xs"></i></button>
              <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="Edit" onclick='openModal("add-measurement", @json($m))'><i class="fa-solid fa-pen text-xs"></i></button>
              <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="Print" onclick='openModal("print-preview", @json($m))'><i class="fa-solid fa-print text-xs"></i></button>
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-500 inline-flex items-center justify-center transition-colors" title="Delete" onclick="confirmDelete('Measurement', '{{ addslashes($m->customer->name ?? '') }}', 'row-{{ $m->id }}', {{ $m->id }})"><i class="fa-solid fa-trash text-xs"></i></button>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" class="px-5 py-4 text-center text-slate-500 text-sm">No measurements found.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <div class="px-6 py-3 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center text-sm gap-3">
    <span id="paginationInfo" class="text-xs text-slate-500">Showing {{ $measurementsData->count() }} of {{ $measurementsData->count() }} results</span>
    <div class="flex items-center gap-1"><span class="text-xs text-slate-500">All saved measurements</span></div>
  </div>
</div>

<div class="page grid grid-cols-1 gap-6">
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
    <div class="flex justify-between items-center mb-5">
      <div><h3 class="text-base font-semibold text-slate-900 tracking-tight">Recently Updated Measurements</h3>
      <p class="text-xs text-slate-500 mt-0.5">Open a saved set to update customer measurements</p></div>
      <span class="badge badge-pending text-[10px]">{{ $measurementsData->count() }} saved</span>
    </div>
    <div class="space-y-2">
      @forelse($measurementsData->sortByDesc('updated_at')->take(3) as $recent)
      <div class="flex items-center justify-between p-3 border border-slate-100 rounded-lg hover:bg-slate-50 transition-colors">
        <div class="flex items-center gap-3"><div class="avatar sm green">{{ mb_substr($recent->customer?->name ?? '?', 0, 1) }}</div>
        <div><div class="text-sm font-semibold text-slate-900">{{ $recent->customer?->name }}</div>
        <div class="text-xs text-slate-500 mt-0.5">{{ $recent->garment_type }} / {{ $recent->updated_at->format('d M Y') }}</div></div></div>
        <button class="bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md text-xs font-semibold hover:bg-slate-900 hover:text-white transition-colors" onclick='openModal("add-measurement", @json($recent))'>Update Measurements</button>
      </div>
      @empty
      <p class="text-xs text-slate-500">No saved measurements yet. Use Add Measurements to record your first set.</p>
      @endforelse
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
/* === BULLETPROOF THERMAL PRINT STYLES (NO EMPTY PAGES, NO HIDDEN RECEIPT) === */
@media print {
  body { 
    margin: 0 !important; 
    padding: 0 !important; 
    background: #fff !important; 
  }
  
  /* Hide ALL direct children of body EXCEPT the modal backdrop.
     This removes the main app height, preventing empty pages. */
  body > *:not(#modal-backdrop) { 
    display: none !important; 
  }
  
  /* Inside modal backdrop, hide ALL direct children EXCEPT modal content */
  #modal-backdrop > *:not(#modal-content) { 
    display: none !important; 
  }
  
  /* Inside modal content, hide ALL direct children EXCEPT the print container.
     This hides the modal header and footer. */
  #modal-content > *:not(.print-container) { 
    display: none !important; 
  }
  
  /* Inside print container, hide ALL direct children EXCEPT the print sheet */
  .print-container > *:not(#measurement-print-sheet) { 
    display: none !important; 
  }
  
  /* Reset layout constraints for the containers in the path */
  #modal-backdrop, #modal-content, .print-container {
    display: block !important;
    position: static !important;
    background: transparent !important;
    opacity: 1 !important;
    visibility: visible !important;
    height: auto !important;
    width: 100% !important;
    max-height: none !important;
    overflow: visible !important;
    box-shadow: none !important;
    border: none !important;
    border-radius: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  
  /* Make the print sheet perfect for thermal */
  #measurement-print-sheet { 
    display: block !important;
    position: static !important;
    left: auto !important;
    top: auto !important;
    width: 100% !important; 
    margin: 0 !important; 
    padding: 5mm !important; 
    box-sizing: border-box !important;
    box-shadow: none !important; 
    border: none !important; 
    background: #fff !important; 
    visibility: visible !important;
  }
  
  /* Ensure all text inside the sheet is visible */
  #measurement-print-sheet * {
    visibility: visible !important;
  }

  /* Page size for Thermal Printer */
  @page {
    size: 80mm auto; 
    margin: 0;
  }

  .no-print {
    display: none !important;
  }
}
</style>
@endpush

@push('scripts')
<script>
  /* ============= LIVE SEARCH LOGIC ============= */
  window.allCustomers = @json($customers);
  window.savedMeasurementSets = @json($measurementsData);
  window.garmentTypes = @json($garmentTypes);
  window.tailorNames = @json($tailors);

  function filterCustomerSearch(val) {
    const dropdown = document.getElementById('customer-dropdown');
    if (!dropdown) return;
    
    val = val.toLowerCase().trim();
    if (!val) {
      dropdown.classList.add('hidden');
      return;
    }
    
    const matches = window.allCustomers.filter(c => c.name.toLowerCase().includes(val) || (c.phone && c.phone.includes(val)));
    
    if (matches.length > 0) {
      dropdown.innerHTML = matches.map(c => `
        <div class="px-3 py-2 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0" onmousedown="selectCustomer(${c.id})">
          <div class="text-sm font-semibold text-slate-900">${Atelier.escapeHtml(c.name)}</div>
          <div class="text-[10px] text-slate-500">${Atelier.escapeHtml(c.phone || 'No phone')}</div>
        </div>
      `).join('');
      dropdown.classList.remove('hidden');
    } else {
      dropdown.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">No matching customers</div>';
      dropdown.classList.remove('hidden');
    }
  }

  function selectCustomer(id) {
    const customer = window.allCustomers.find(c => Number(c.id) === Number(id));
    if (!customer) return;
    const garment = document.getElementById('meas-garment')?.value || 'Wash & Wear';
    const latest = window.savedMeasurementSets
      .filter(m => Number(m.customer_id) === Number(customer.id) && String(m.garment_type || '').trim().toLowerCase() === garment.toLowerCase())
      .sort((a,b) => new Date(b.updated_at || b.created_at) - new Date(a.updated_at || a.created_at))[0];
    openModal('add-measurement', latest || { customer, customer_id: customer.id, name: customer.name, garment });
  }

  function savedMeasurementsForGarment(customerId, garment) {
    const garmentKey = String(garment || '').trim().toLowerCase();
    return window.savedMeasurementSets.filter(m => Number(m.customer_id) === Number(customerId)
      && String(m.garment_type || '').trim().toLowerCase() === garmentKey);
  }



  function selectGarmentMeasurements(garment) {
    const customerId = Number(document.getElementById('meas-customer').dataset.customerId);
    const customer = window.allCustomers.find(c => Number(c.id) === customerId);
    const existing = savedMeasurementsForGarment(customerId, garment)[0];
    const name = document.getElementById('meas-customer').value;
    openModal('add-measurement', existing || {customer, customer_id: customer?.id, name, garment});
  }

  function measurementCustomerChanged() {
    const input = document.getElementById('meas-customer');
    if (input.dataset.customerId && input.value !== input.dataset.customerName) {
      // An edited saved set must never be reassigned just by typing a different name.
      const name = input.value;
      const garment = document.getElementById('meas-garment')?.value || 'Wash & Wear';
      openModal('add-measurement', {name, garment});
      const fresh = document.getElementById('meas-customer');
      fresh.focus(); fresh.setSelectionRange(name.length, name.length);
    }
    filterCustomerSearch(document.getElementById('meas-customer').value);
  }

  /* ============= EXPORT LOGIC ============= */
  function exportData() {
    const rows = document.querySelectorAll('#measurementTableBody tr[data-status]');
    const visible = [...rows].filter(r => r.style.display !== 'none');

    if (!visible.length) { toast('There is nothing to export', 'info'); return; }

    const header = ['Customer', 'Garment Type', 'Tailor', 'Date', 'Status'];
    const data = visible.map(r => [...r.querySelectorAll('td')].slice(0, 5).map(td => td.innerText.trim()));

    const csv = [header, ...data]
      .map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(','))
      .join('\n');

    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    Object.assign(document.createElement('a'), {
      href: url, download: `measurements-${new Date().toISOString().slice(0, 10)}.csv`
    }).click();
    URL.revokeObjectURL(url);

    toast(`Exported ${visible.length} measurement records`, 'success');
  }

  /* ============= DELETE LOGIC ============= */
  var deleteContext = { type: '', name: '', elementId: '', dbId: null };

  function confirmDelete(type, name, elementId, dbId = null) {
    deleteContext = { type, name, elementId, dbId };

    Atelier.confirm({
      variant: 'delete',
      title: `Delete this ${type.toLowerCase()}?`,
      message: `${name}'s ${type.toLowerCase()} will be permanently removed. This action cannot be undone.`,
      confirmLabel: 'Confirm Delete',
      onConfirm: executeDelete,
    });
  }

  async function executeDelete() {
    if (deleteContext.dbId) {
      await Atelier.api.delete(`/measurements/${deleteContext.dbId}`);
      window.savedMeasurementSets = window.savedMeasurementSets.filter(m => Number(m.id) !== Number(deleteContext.dbId));
    }

    if (deleteContext.elementId) {
      const el = document.getElementById(deleteContext.elementId);
      if (el) {
        el.style.transition = 'opacity 0.3s, transform 0.3s';
        el.style.opacity = '0';
        el.style.transform = 'translateX(20px)';
        setTimeout(() => el.remove(), 300);
      }
    }

    toast(`${deleteContext.type} for ${deleteContext.name} deleted successfully`, 'success');
  }

  /* ============= LIVE TABLE UPDATES (no reload) ============= */
  /* Fields, labels, default unit, precision and which fields are mandatory all
     come from Settings — nothing about the form is hardcoded here. */
  window.MEASUREMENT_CONFIG = @json($measurementConfig);
  var MEASUREMENT_FIELDS = window.MEASUREMENT_CONFIG.fields;

  function completenessOf(m) {
    const filled = MEASUREMENT_FIELDS.filter(f => m[f] !== null && m[f] !== undefined && m[f] !== '').length;
    return Math.round((filled / MEASUREMENT_FIELDS.length) * 1000) / 10;
  }

  function buildMeasurementRow(m) {
    const pct = completenessOf(m);
    const status = pct >= 80 ? 'completed' : pct >= 40 ? 'pending' : 'revision';
    const badge = { completed: 'badge-delivered', pending: 'badge-pending', revision: 'badge-overdue' }[status];
    const label = { completed: 'Mostly filled', pending: 'Partly filled', revision: 'Few fields filled' }[status];
    const name = m.customer?.name || 'Unknown';
    const json = JSON.stringify(m).replace(/"/g, '&quot;');

    const tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50 transition-colors';
    tr.id = `row-${m.id}`;
    tr.dataset.status = status;
    tr.dataset.garment = m.garment_type || '';
    tr.dataset.tailor = m.unit || '';
    tr.dataset.name = name.toLowerCase();

    tr.innerHTML = `
      <td class="px-5 py-3 font-semibold text-slate-900">${Atelier.escapeHtml(name)}</td>
      <td class="px-5 py-3 text-slate-600">${Atelier.escapeHtml(m.garment_type || '')}</td>
      <td class="px-5 py-3 text-slate-600">${m.unit === 'in' ? 'Inches (in)' : 'Centimetres (cm)'}</td>
      <td class="px-5 py-3 text-slate-500">${new Date(m.updated_at || m.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
      <td class="px-5 py-3"><span class="badge ${badge}">${label}</span></td>
      <td class="px-5 py-3 text-right whitespace-nowrap">
        <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="View Details" onclick='openModal("view-measurement", ${json})'><i class="fa-regular fa-eye text-xs"></i></button>
        <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="Edit" onclick='openModal("add-measurement", ${json})'><i class="fa-solid fa-pen text-xs"></i></button>
        <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="Print" onclick='openModal("print-preview", ${json})'><i class="fa-solid fa-print text-xs"></i></button>
        <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-500 inline-flex items-center justify-center transition-colors" title="Delete" onclick="confirmDelete('Measurement', '${name.replace(/'/g, "\\'")}', 'row-${m.id}', ${m.id})"><i class="fa-solid fa-trash text-xs"></i></button>
      </td>`;

    return tr;
  }

  function upsertMeasurementRow(m, isEdit) {
    const tbody = document.getElementById('measurementTableBody');
    if (!tbody || !m) return;

    tbody.querySelector('#measurement-empty-state')?.remove();

    const row = buildMeasurementRow(m);
    const existing = document.getElementById(`row-${m.id}`);

    if (existing) {
      existing.replaceWith(row);
    } else {
      tbody.prepend(row);
    }

    row.classList.add('bg-indigo-50');
    setTimeout(() => row.classList.remove('bg-indigo-50'), 1500);

    if (!isEdit && !window.allCustomers.some(c => c.name === m.customer?.name)) {
      window.allCustomers.push({ id: m.customer_id, name: m.customer?.name, phone: m.customer?.phone });
    }

    filterMeasurements();
  }

  /* ============= SAVE MEASUREMENT ============= */
  async function saveMeasurement(id) {
    const isEdit = !!id;
    const url = isEdit ? `/measurements/${id}` : '/measurements';
    const method = isEdit ? 'PUT' : 'POST';

    const cfg = window.MEASUREMENT_CONFIG;

    const payload = {
      customer_name: document.getElementById('meas-customer').value,
      garment_type: document.getElementById('meas-garment').value,
      tailor: document.getElementById('meas-tailor').value,
      unit: document.getElementById('meas-unit').value,
      notes: document.getElementById('meas-notes').value,
    };

    // Built from the configured field list, so adding or removing a field in
    // Settings never leaves this payload out of step.
    cfg.fields.forEach(field => {
      payload[field] = document.getElementById(`meas-${field}`)?.value || null;
    });

    if (!payload.garment_type) { toast('Choose saved measurements to update, or a new garment set', 'error'); return; }

    // Catch missing mandatory fields before the round-trip.
    const missing = cfg.required.filter(f => !payload[f]);
    if (missing.length) {
      toast(`${cfg.labels[missing[0]]} is required`, 'error');
      document.getElementById(`meas-${missing[0]}`)?.focus();
      return;
    }

    // Bind to a real customer record when the typed name matches one.
    const selectedId = Number(document.getElementById('meas-customer').dataset.customerId);
    const candidates = (window.allCustomers || []).filter(c => c.name.toLowerCase() === payload.customer_name.trim().toLowerCase());
    const match = (window.allCustomers || []).find(c => Number(c.id) === selectedId) || (candidates.length === 1 ? candidates[0] : null);
    if (!match && candidates.length > 1) { toast('Select the customer from the search results to confirm their phone number', 'error'); return; }
    if (match) payload.customer_id = match.id;

    if (!payload.customer_name.trim()) {
      toast('Please enter a customer name', 'error');
      return;
    }

    const btn = document.getElementById('save-measurement-btn');
    Atelier.setBusy(btn, true);

    try {
      const res = await (isEdit ? Atelier.api.put(url, payload) : Atelier.api.post(url, payload));
      closeModal();
      const duplicates = window.savedMeasurementSets.filter(m => Number(m.customer_id) === Number(res.measurement.customer_id) && m.garment_type.trim().toLowerCase() === res.measurement.garment_type.trim().toLowerCase() && Number(m.id) !== Number(res.measurement.id));
      duplicates.forEach(m => document.getElementById(`row-${m.id}`)?.remove());
      window.savedMeasurementSets = window.savedMeasurementSets.filter(m => !duplicates.includes(m));
      const savedIndex = window.savedMeasurementSets.findIndex(m => Number(m.id) === Number(res.measurement.id));
      if (savedIndex >= 0) window.savedMeasurementSets[savedIndex] = res.measurement;
      else window.savedMeasurementSets.unshift(res.measurement);
      upsertMeasurementRow(res.measurement, isEdit);
      toast(res.message || 'Measurement saved successfully', 'success');
    } catch (err) {
      Atelier.reportError(err, 'Error saving measurement');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  /* ============= MODAL OVERRIDES ============= */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'view-measurement': (data) => {
      const customerName = data.customer?.name || 'Unknown';
      const garment = data.garment_type || 'N/A';
      const tailor = data.tailor || 'Unassigned';
      const date = data.created_at ? new Date(data.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : 'N/A';
      const unit = data.unit || window.MEASUREMENT_CONFIG.unit;

      const metrics = [
        { label: 'Length', id: 'length' },
        { label: 'Shoulder', id: 'shoulder_width' },
        { label: 'Sleeves', id: 'sleeve_length' },
        { label: 'Chest', id: 'chest', hasLosing: true },
        { label: 'West', id: 'waist', hasLosing: true },
        { label: 'Hip', id: 'hip', hasLosing: true },
        { label: 'Collar', id: 'collar' },
        { label: 'Galla', id: 'ghera' },
        { label: 'F/Patti', id: 'patti' },
        { label: 'Button', id: 'button' },
        { label: 'Cuff', id: 'cuff' },
        { label: 'Koni', id: 'koni' },
        { label: 'Elbow', id: 'elbow' },
        { label: 'Armor', id: 'armhole' },
        { label: 'Takki', id: 'takai' },
        { label: 'Salwar Length', id: 'salwar_length' },
        { label: 'Pancho', id: 'pancho' }
      ];

      const renderValue = (val) => (val !== null && val !== undefined && val !== '') ? `${val} ${unit}` : '—';

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">Measurement Details</div>
            <div class="text-xs text-slate-500 mt-1">Customer: ${customerName} · Order: ${data.order_id || 'N/A'}</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[80vh]">
          <!-- Basic Info Grid -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 bg-slate-50 p-4 rounded-xl border border-slate-100">
            <div>
              <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Garment</div>
              <div class="text-sm font-semibold text-slate-900 mt-1">${garment}</div>
            </div>
            <div>
              <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Unit</div>
              <div class="text-sm font-semibold text-slate-900 mt-1">${unit}</div>
            </div>
            <div>
              <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Date</div>
              <div class="text-sm font-semibold text-slate-900 mt-1">${date}</div>
            </div>
            <div>
              <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Fields Filled</div>
              <div class="text-sm font-semibold text-emerald-600 mt-1">${completenessOf(data)}%</div>
            </div>
          </div>

          <!-- Body Metrics Grid -->
          <div class="flex items-center gap-2 mb-4">
            <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Body Metrics (${unit})</h4>
            <div class="flex-1 h-px bg-slate-200"></div>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            ${metrics.map(m => {
              if (m.hasLosing) {
                const mainVal = renderValue(data[m.id]);
                const losingVal = data[m.id + '_losing'] !== null && data[m.id + '_losing'] !== undefined && data[m.id + '_losing'] !== '' ? `(${data[m.id + '_losing']} ${window.MEASUREMENT_CONFIG.labels[m.id + '_losing']})` : '';
                return `
                  <div class="bg-white border border-slate-200 rounded-lg p-3">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">${m.label}</div>
                    <div class="text-base font-bold text-slate-900 mt-1">${mainVal} <span class="text-xs font-medium text-amber-600">${losingVal}</span></div>
                  </div>
                `;
              }
              return `
                <div class="bg-white border border-slate-200 rounded-lg p-3">
                  <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">${m.label}</div>
                  <div class="text-base font-bold text-slate-900 mt-1">${renderValue(data[m.id])}</div>
                </div>
              `;
            }).join('')}
          </div>

          <!-- Measurement Notes -->
          ${data.notes ? `
          <div class="mt-6">
            <div class="flex items-center gap-2 mb-3">
              <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Notes</h4>
              <div class="flex-1 h-px bg-slate-200"></div>
            </div>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-lg text-sm">
              ${Atelier.escapeHtml(data.notes)}
            </div>
          </div>` : ''}
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Close</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-2" onclick='openModal("print-preview", ${JSON.stringify(data).replace(/'/g, "\\'")})'><i class="fa-solid fa-print text-xs"></i> Print Sheet</button>
        </div>`;
    },
    'add-measurement': (data) => {
      if (data?.id) data = window.savedMeasurementSets.find(m => Number(m.id) === Number(data.id)) || data;
      const isEdit = data && data.id;
      const title = isEdit ? 'Update Measurements' : 'Add Measurements';
      const desc = isEdit ? `Update saved measurements for ${data.customer?.name || 'Unknown'}` : 'Save or update customer measurements. No order required.';
      const customerVal = isEdit ? (data.customer?.name || '') : (data?.name || '');
      const garmentVal = isEdit ? data.garment_type : (data?.garment || window.garmentTypes[0] || '');
      const tailorVal = isEdit ? data.tailor : (window.tailorNames[0] || 'Unassigned');
      // Unit, precision and which fields are mandatory all come from Settings.
      const cfg = window.MEASUREMENT_CONFIG;
      const unitVal = isEdit ? data.unit : cfg.unit;
      const notesVal = isEdit ? data.notes : '';

      const customerId = data?.customer_id || data?.customer?.id;
      const savedSets = savedMeasurementsForGarment(customerId, garmentVal);
      const chooseSaved = data?.chooseSaved && savedSets.length > 0;
      const garmentOptions = ['Wash & Wear', 'Cotton', 'Boski'].map(g => `<option value="${Atelier.escapeHtml(g)}" ${g === garmentVal ? 'selected' : ''}>${Atelier.escapeHtml(g)}</option>`).join('');

      const isRequired = id => cfg.required.includes(id);
      const star = id => isRequired(id) ? ' <span class="text-red-500">*</span>' : '';
      const attrs = id => `step="${cfg.step}" min="0" max="999" ${isRequired(id) ? 'required' : ''}`;

      const metrics = [
        { label: 'Length', id: 'length' },
        { label: 'Shoulder', id: 'shoulder_width' },
        { label: 'Sleeves', id: 'sleeve_length' },
        { label: 'Chest', id: 'chest', hasLosing: true },
        { label: 'West', id: 'waist', hasLosing: true },
        { label: 'Hip', id: 'hip', hasLosing: true },
        { label: 'Collar', id: 'collar' },
        { label: 'Galla', id: 'ghera' },
        { label: 'F/Patti', id: 'patti' },
        { label: 'Button', id: 'button' },
        { label: 'Cuff', id: 'cuff' },
        { label: 'Koni', id: 'koni' },
        { label: 'Elbow', id: 'elbow' },
        { label: 'Armor', id: 'armhole' },
        { label: 'Takki', id: 'takai' },
        { label: 'Salwar Length', id: 'salwar_length' },
        { label: 'Pancho', id: 'pancho' }
      ];

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">${title}</div>
            <div class="text-xs text-slate-500 mt-1">${Atelier.escapeHtml(desc)}</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[80vh]">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="col-span-2 md:col-span-2 relative">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Customer Name *</label>
              <input type="text" id="meas-customer" data-customer-id="${customerId || ''}" data-customer-name="${Atelier.escapeHtml(customerVal)}" oninput="measurementCustomerChanged()" onfocus="filterCustomerSearch(this.value)" onblur="setTimeout(() => { const d = document.getElementById('customer-dropdown'); if(d) d.classList.add('hidden') }, 200)" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Search or enter customer name" value="${Atelier.escapeHtml(customerVal)}" autocomplete="off">
              <div id="customer-dropdown" class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg hidden max-h-48 overflow-y-auto"></div>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category *</label>
              <select id="meas-garment" onchange="selectGarmentMeasurements(this.value)" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                ${garmentOptions}
              </select>
              <input type="hidden" id="meas-tailor" value="${Atelier.escapeHtml(tailorVal || 'Unassigned')}">
            </div>
          </div>
          
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2 flex-1">
               <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Body Metrics</h4>
               <div class="flex-1 h-px bg-slate-200"></div>
            </div>
            <div class="ml-4">
               <select id="meas-unit" class="text-xs border border-slate-200 text-slate-600 rounded px-2 py-1 bg-white focus:outline-none focus:ring-1 focus:ring-slate-900">
                  <option value="cm" ${unitVal === 'cm' ? 'selected' : ''}>cm</option>
                  <option value="in" ${unitVal === 'in' ? 'selected' : ''}>in</option>
               </select>
            </div>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            ${metrics.map(m => {
              const cell = id => (isEdit && data[id] !== null && data[id] !== undefined) ? data[id] : '';

              if (m.hasLosing) {
                return `
                  <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">${m.label}${star(m.id)}${star(m.id + '_losing')}</label>
                    <div class="flex gap-2">
                      <input type="number" id="meas-${m.id}" ${attrs(m.id)} class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Body" value="${cell(m.id)}">
                      <input type="number" id="meas-${m.id}_losing" ${attrs(m.id + '_losing')} class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="${window.MEASUREMENT_CONFIG.labels[m.id + '_losing']}" value="${cell(m.id + '_losing')}">
                    </div>
                  </div>
                `;
              }
              return `
                <div>
                  <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">${m.label}${star(m.id)}</label>
                  <input type="number" id="meas-${m.id}" ${attrs(m.id)} class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="${Number(0).toFixed(cfg.decimals)}" value="${cell(m.id)}">
                </div>
              `;
            }).join('')}
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Notes</label>
            <textarea id="meas-notes" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors min-h-[80px]" placeholder="Fit, posture, or notes...">${Atelier.escapeHtml(notesVal || '')}</textarea>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button id="save-measurement-btn" class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-2" onclick="saveMeasurement(${isEdit ? data.id : 'null'})"><i class="fa-solid fa-check text-xs"></i> ${isEdit ? 'Update Measurements' : 'Save Measurements'}</button>
        </div>`;
    },
    'print-preview': (data) => {
      if (!data) return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center"><div class="text-lg font-bold text-slate-900 tracking-tight">Print Measurements</div><button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button></div>
        <div class="p-6"><label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Select saved measurements</label><select id="measurement-print-choice" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">${window.savedMeasurementSets.map(m => `<option value="${m.id}">${Atelier.escapeHtml(m.customer?.name || 'Customer')} / ${Atelier.escapeHtml(m.garment_type)} / #${m.id}</option>`).join('')}</select>${window.savedMeasurementSets.length ? '' : '<p class="text-xs text-slate-500 mt-1">Save measurements before printing.</p>'}</div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2"><button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800" onclick="openModal('print-preview', window.savedMeasurementSets.find(m => Number(m.id) === Number(document.getElementById('measurement-print-choice').value)))" ${window.savedMeasurementSets.length ? '' : 'disabled'}>Preview Sheet</button></div>`;
      if (data?.id) data = window.savedMeasurementSets.find(m => Number(m.id) === Number(data.id)) || data;
      
      const customer = data.customer?.name || data.name || 'Unknown';
      const garment = data.garment_type || data.garment || 'Unknown';
      const unit = data.unit || window.MEASUREMENT_CONFIG.unit;
      const date = data.created_at ? new Date(data.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

      const metrics = [
        { label: 'Length', id: 'length' },
        { label: 'Shoulder', id: 'shoulder_width' },
        { label: 'Sleeves', id: 'sleeve_length' },
        { label: 'Chest', id: 'chest', hasLosing: true },
        { label: 'West', id: 'waist', hasLosing: true },
        { label: 'Hip', id: 'hip', hasLosing: true },
        { label: 'Collar', id: 'collar' },
        { label: 'Galla', id: 'ghera' },
        { label: 'F/Patti', id: 'patti' },
        { label: 'Button', id: 'button' },
        { label: 'Cuff', id: 'cuff' },
        { label: 'Koni', id: 'koni' },
        { label: 'Elbow', id: 'elbow' },
        { label: 'Armor', id: 'armhole' },
        { label: 'Takki', id: 'takai' },
        { label: 'Salwar Length', id: 'salwar_length' },
        { label: 'Pancho', id: 'pancho' }
      ];

      const renderMetrics = metrics.map(m => {
        const val = data[m.id];
        if (val === null || val === undefined || val === '') return '';
        const numVal = Number(val).toFixed(2);
        
        let losingStr = '';
        if (m.hasLosing) {
          const losing = data[m.id + '_losing'];
          if (losing !== null && losing !== undefined && losing !== '') {
            losingStr = ` (${Number(losing).toFixed(2)} ${window.MEASUREMENT_CONFIG.labels[m.id + '_losing']})`;
          }
        }
        
        return `
          <div style="display:flex; justify-content:space-between; margin-bottom:4px; border-bottom:1px dashed #ccc; padding-bottom:2px;">
            <span style="font-weight:bold;">${m.label}</span>
            <span>${numVal} ${unit}${losingStr}</span>
          </div>
        `;
      }).filter(s => s !== '').join('');

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center no-print">
          <div class="text-lg font-bold text-slate-900 tracking-tight">Print Measurement Sheet</div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 bg-slate-100 overflow-y-auto flex justify-center print-container" style="max-height:60vh;">
          <div id="measurement-print-sheet" style="background:#fff; width:280px; padding:20px; box-shadow:0 0 10px rgba(0,0,0,0.1); font-family: 'Courier New', monospace; color: #000;">
             <div style="text-align:center; font-weight:bold; font-size:16px; margin-bottom:4px;">{{ \App\Services\Settings::str('store_name') }}</div>
             <div style="text-align:center; font-size:10px; margin-bottom:8px;">Customer Measurement Record</div>
             <div style="border-top:1px dashed #000; margin:8px 0;"></div>
             <div style="font-size:12px; margin-bottom:2px; display:flex; justify-content:space-between;"><span>Customer:</span><span style="font-weight:bold;">${customer}</span></div>
             <div style="font-size:12px; margin-bottom:2px; display:flex; justify-content:space-between;"><span>Garment:</span><span style="font-weight:bold;">${garment}</span></div>
             <div style="font-size:12px; margin-bottom:8px; display:flex; justify-content:space-between;"><span>Date:</span><span style="font-weight:bold;">${date}</span></div>
             <div style="border-top:1px dashed #000; margin:8px 0;"></div>
             <div style="font-size:12px;">
                ${renderMetrics || '<div style="text-align:center; color:#999; font-style:italic;">No measurement values found</div>'}
             </div>
             <div style="border-top:1px dashed #000; margin:8px 0;"></div>
             <div style="text-align:center; font-size:10px; margin-top:20px;">Customer Measurement Sheet</div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2 no-print">
          <button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 flex items-center gap-2 transition-colors" onclick="window.print()"><i class="fa-solid fa-file-pdf text-xs"></i> Save as PDF</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="window.print()"><i class="fa-solid fa-print text-xs"></i> Print</button>
        </div>`;
    },
    'confirm-delete': (data) => {
      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div class="text-lg font-bold text-slate-900 tracking-tight">Confirm Deletion</div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6">
          <div class="flex items-start gap-4 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-600 flex-shrink-0">
              <i class="fa-solid fa-trash"></i>
            </div>
            <div class="flex-1">
              <p class="text-sm text-slate-700">Are you sure you want to delete this ${data.type.toLowerCase()}? <span class="font-bold">${data.name}</span> will be permanently removed. This action cannot be undone.</p>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-600 flex items-center gap-2 transition-colors shadow-sm" onclick="executeDelete()"><i class="fa-solid fa-check text-xs"></i> Delete Permanently</button>
        </div>`;
    }
  });

  /* ============= FILTER LOGIC (status + garment + tailor + search) ============= */
  function filterMeasurements() {
    const status  = document.getElementById('statusFilter')?.value || 'all';
    const garment = document.getElementById('garmentFilter')?.value || '';
    const tailor  = document.getElementById('tailorFilter')?.value || '';
    const search  = (document.getElementById('measurementSearch')?.value || '').trim().toLowerCase();

    const rows = document.querySelectorAll('#measurementTableBody tr[data-status]');
    const paginationInfo = document.getElementById('paginationInfo');
    let count = 0;

    rows.forEach(row => {
      const matches =
        (status === 'all' || row.dataset.status === status) &&
        (!garment || row.dataset.garment === garment) &&
        (!tailor || row.dataset.tailor === tailor) &&
        (!search || (row.dataset.name || '').includes(search));

      row.style.display = matches ? '' : 'none';
      if (matches) count++;
    });

    const total = rows.length;
    const empty = document.getElementById('measurement-empty-filter');

    if (count === 0 && total > 0) {
      if (!empty) {
        const tbody = document.getElementById('measurementTableBody');
        const tr = document.createElement('tr');
        tr.id = 'measurement-empty-filter';
        tr.innerHTML = `<td colspan="6" class="p-0">${Atelier.emptyState({
          icon: 'fa-ruler-combined',
          title: 'No matching measurements',
          message: 'Try clearing a filter or searching for a different customer.'
        })}</td>`;
        tbody.appendChild(tr);
      }
    } else if (empty) {
      empty.remove();
    }

    if (paginationInfo) {
      paginationInfo.innerText = count === total
        ? `Showing ${total} of ${total} results`
        : `Showing ${count} of ${total} results`;
    }
  }

  Atelier.onPageReady(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'create') openModal('add-measurement');

    const highlight = params.get('highlight');
    if (highlight) {
      const row = document.getElementById(`row-${highlight}`);
      if (row) {
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.classList.add('bg-indigo-50');
        setTimeout(() => row.classList.remove('bg-indigo-50'), 2500);
      }
    }
  });
</script>
@endpush
