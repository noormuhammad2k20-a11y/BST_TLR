@extends('layouts.app')
@section('title', 'Customers')
@section('spaPage', 'customers')

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Customer Directory</h1>
    <p class="text-sm text-slate-500 mt-0.5"><span id="total-customers-text">{{ $stats['total'] }}</span> total customers · {{ $stats['vip'] }} VIP patrons</p>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('customers.import') }}" class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 hover:border-slate-300 flex items-center gap-2 transition-colors shadow-sm"><i class="fa-solid fa-file-import text-[10px]"></i> Import</a>
    <button class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 hover:border-slate-300 flex items-center gap-2 transition-colors shadow-sm" onclick="exportCustomers()"><i class="fa-solid fa-file-export text-[10px]"></i> Export</button>
    <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="openDrawer('add-customer-drawer')"><i class="fa-solid fa-user-plus text-[10px]"></i> Add Customer</button>
  </div>
</div>

<!-- Premium Stat Cards -->
<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Customers</span>
      <div class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-users text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-stat="total">{{ number_format($stats['total']) }}</h3>
    <p class="text-[11px] text-slate-500 mt-1 flex items-center gap-1"><span class="text-emerald-600 font-semibold"><span data-stat="new_month">{{ $stats['new_month'] }}</span> new</span> this month</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">VIP Patrons</span>
      <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fa-solid fa-crown text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-stat="vip">{{ number_format($stats['vip']) }}</h3>
    <p class="text-[11px] text-slate-500 mt-1"><span data-stat="vip_share">{{ $stats['vip_share'] }}</span>% of total base</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Avg. Order Value</span>
      <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-rupee-sign text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight">{{ \App\Services\Money::format($stats['avg_order']) }}</h3>
    @php $avgDelta = $stats['avg_delta']; @endphp
    <p class="text-[11px] text-slate-500 mt-1 flex items-center gap-1"><span class="{{ $avgDelta['direction'] === 'down' ? 'text-red-500' : 'text-emerald-600' }} font-semibold">{{ $avgDelta['direction'] === 'down' ? '-' : '+' }}{{ $avgDelta['value'] }}%</span> {{ $avgDelta['direction'] === 'down' ? 'decrease' : 'increase' }}</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Customers with Dues</span>
      <div class="w-7 h-7 rounded-md bg-red-50 text-red-600 flex items-center justify-center"><i class="fa-solid fa-circle-exclamation text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-stat="with_dues">{{ number_format($stats['with_dues']) }}</h3>
    <p class="text-[11px] text-red-500 font-medium mt-1">Needs follow-up</p>
  </div>
</div>

<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
  <div class="p-5 border-b border-slate-200 flex justify-between items-center flex-wrap gap-2">
    <div class="flex gap-1 bg-slate-100 p-1 rounded-lg" id="customer-type-tabs">
      <span data-type="All" class="px-3 py-1.5 rounded-md bg-white text-slate-900 text-xs font-medium cursor-pointer shadow-sm" onclick="setCustomerType('All')">All ({{ $stats['total'] }})</span>
      <span data-type="VIP" class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:text-slate-900" onclick="setCustomerType('VIP')">VIP ({{ $stats['by_type']['VIP'] }})</span>
      <span data-type="Premium" class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:text-slate-900" onclick="setCustomerType('Premium')">Premium ({{ $stats['by_type']['Premium'] }})</span>
      <span data-type="Regular" class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:text-slate-900" onclick="setCustomerType('Regular')">Regular ({{ $stats['by_type']['Regular'] }})</span>
    </div>
    <div class="relative">
      <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
      <input id="customer-search" oninput="setCustomerSearch(this.value)" class="pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-sm w-56 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white text-slate-900 transition-all" placeholder="Search name, phone, city...">
    </div>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
        <tr>
          <th class="px-5 py-3 text-left font-bold"><input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"></th>
          <th class="px-5 py-3 text-left font-bold">Customer</th>
          <th class="px-5 py-3 text-left font-bold">Contact</th>
          <th class="px-5 py-3 text-left font-bold">Type</th>
          <th class="px-5 py-3 text-left font-bold">Loyalty</th>
          <th class="px-5 py-3 text-left font-bold">Payment Behavior</th>
          <th class="px-5 py-3 text-left font-bold">Total Spent</th>
          <th class="px-5 py-3 text-left font-bold"></th>
        </tr>
      </thead>
      <tbody id="customer-list" class="divide-y divide-slate-100">
        <!-- Rows rendered dynamically by JS -->
      </tbody>
    </table>
  </div>
  
  <!-- PAGINATION FOOTER -->
  <div class="px-6 py-3 border-t border-slate-200 flex items-center justify-between flex-wrap gap-3">
    <div class="text-xs text-slate-500" id="page-info">Showing 0 to 0 of 0 results</div>
    <div class="flex items-center gap-1" id="page-controls"></div>
  </div>
</div>
@endsection

@section('drawers')
<!-- Add Customer Drawer -->
<div class="drawer" id="add-customer-drawer">
  <form id="add-customer-form" action="{{ route('customers.store') }}" method="POST" class="flex flex-col h-full">
    @csrf
    <div class="flex items-center justify-between p-5 border-b border-slate-200">
      <div class="text-base font-semibold text-slate-900">Add New Customer</div>
      <button type="button" class="w-8 h-8 rounded-lg text-slate-500 hover:bg-slate-100 flex items-center justify-center" onclick="closeDrawers()"><i class="fa-solid fa-xmark text-sm"></i></button>
    </div>
    <div class="p-6 overflow-y-auto flex-1">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Full Name *</label>
          <input name="name" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Aarav Kumar">
        </div>
        <div class="col-span-2">
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Phone Number *</label>
          <input name="phone" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="+91 98765 43210">
        </div>
        <div class="col-span-2">
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
          <input type="email" name="email" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="customer@email.com">
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">City</label>
          <input name="city" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Mumbai">
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type</label>
          <select name="type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
            <option value="Regular">Regular</option>
            <option value="Premium">Premium</option>
            <option value="VIP">VIP</option>
          </select>
        </div>
      </div>
    </div>
    <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
      <button type="button" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="closeDrawers()">Cancel</button>
      <button type="submit" class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 shadow-sm">Save & View Profile</button>
    </div>
  </form>
</div>

<!-- Edit Customer Drawer -->
<div class="drawer" id="edit-customer-drawer">
  <form id="edit-customer-form" action="" method="POST" class="flex flex-col h-full">
    @csrf
    @method('PUT')
    <div class="flex items-center justify-between p-5 border-b border-slate-200">
      <div class="text-base font-semibold text-slate-900">Edit Customer</div>
      <button type="button" class="w-8 h-8 rounded-lg text-slate-500 hover:bg-slate-100 flex items-center justify-center" onclick="closeDrawers()"><i class="fa-solid fa-xmark text-sm"></i></button>
    </div>
    <div class="p-6 overflow-y-auto flex-1">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Full Name *</label>
          <input id="edit-name" name="name" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Aarav Kumar">
        </div>
        <div class="col-span-2">
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Phone Number *</label>
          <input id="edit-phone" name="phone" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="+91 98765 43210">
        </div>
        <div class="col-span-2">
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
          <input id="edit-email" type="email" name="email" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="customer@email.com">
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">City</label>
          <input id="edit-city" name="city" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Mumbai">
        </div>
        <div>
          <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type</label>
          <select id="edit-type" name="type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
            <option value="Regular">Regular</option>
            <option value="Premium">Premium</option>
            <option value="VIP">VIP</option>
          </select>
        </div>
      </div>
    </div>
    <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
      <button type="button" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="closeDrawers()">Cancel</button>
      <button type="submit" class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 shadow-sm">Update Customer</button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  /* ============= DATA STORE ============= */
  /* `var` throughout: the SPA router re-evaluates this script per navigation,
     and `let`/`const` cannot legally be redeclared at global scope. */
  window.sendCustomerSms = async function (btn) {
    const customer = selectedCustomerFor360;
    if (!customer) return;
    const message = window.prompt('SMS message for ' + customer.name + ' (maximum 2000 characters):');
    if (message === null) return;
    if (!message.trim() || message.length > 2000) {
      toast('Enter a message of 1–2000 characters.', 'warning'); return;
    }
    Atelier.setBusy(btn, true);
    try {
      const url = @json(route('customers.sms', ['customer' => '__CUSTOMER__'])).replace('__CUSTOMER__', customer.db_id);
      const res = await Atelier.api.post(url, { message: message.trim() });
      toast(res.message, 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not send the SMS');
    } finally { Atelier.setBusy(btn, false); }
  };

  var customers = @json($customers);
  var orders = @json($orders);

  var selectedCustomerFor360 = null;
  var customer360Data = null;
  var customer360Tab = 'orders';

  /* ============= FILTER / SORT / PAGINATION STATE ============= */
  var currentPage = 1;
  var customerType = 'All';
  var customerSearch = '';
  var customerSort = { key: 'name', dir: 'asc' };
  /* Page size comes from Settings → Theme & Display, so one number governs
     every table in the app. */
  var itemsPerPage = Atelier.rowsPerPage();
  var avatarClasses = ['slate', 'pink', 'green', 'orange', 'blue', 'purple'];

  function getFilteredCustomers() {
    let list = customers;

    if (customerType !== 'All') list = list.filter(c => c.type === customerType);

    if (customerSearch) {
      const q = customerSearch.toLowerCase();
      list = list.filter(c =>
        (c.name || '').toLowerCase().includes(q) ||
        (c.phone || '').toLowerCase().includes(q) ||
        (c.email || '').toLowerCase().includes(q) ||
        (c.city || '').toLowerCase().includes(q) ||
        (c.id || '').toLowerCase().includes(q)
      );
    }

    const { key, dir } = customerSort;
    const factor = dir === 'asc' ? 1 : -1;

    return [...list].sort((a, b) => {
      const av = a[key], bv = b[key];
      if (typeof av === 'string') return factor * av.localeCompare(bv || '');
      return factor * ((av || 0) - (bv || 0));
    });
  }

  window.setCustomerType = function(type) {
    customerType = type;
    currentPage = 1;

    document.querySelectorAll('#customer-type-tabs span').forEach(el => {
      const active = el.dataset.type === type;
      el.className = active
        ? 'px-3 py-1.5 rounded-md bg-white text-slate-900 text-xs font-medium cursor-pointer shadow-sm'
        : 'px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:text-slate-900';
    });

    renderCustomerTable();
  };

  window.setCustomerSearch = function(value) {
    customerSearch = (value || '').trim();
    currentPage = 1;
    renderCustomerTable();
  };

  window.setCustomerSort = function(key) {
    customerSort = customerSort.key === key
      ? { key, dir: customerSort.dir === 'asc' ? 'desc' : 'asc' }
      : { key, dir: 'asc' };
    renderCustomerTable();
  };

  /* ============= AJAX FORMS (no page reload) ============= */
  function applyCustomerStats(stats) {
    if (!stats) return;
    Object.entries(stats).forEach(([key, value]) => {
      document.querySelectorAll(`[data-stat="${key}"]`).forEach(el => {
        el.textContent = typeof value === 'number' ? value.toLocaleString('en-IN') : value;
      });
    });

    const header = document.getElementById('total-customers-text');
    if (header && stats.total !== undefined) header.textContent = stats.total;
  }

  /** Recompute the header counters locally so the cards react instantly. */
  function recalcStats() {
    const total = customers.length;
    const vip = customers.filter(c => c.type === 'VIP').length;

    applyCustomerStats({
      total,
      vip,
      vip_share: total ? Math.round((vip / total) * 1000) / 10 : 0,
      with_dues: customers.filter(c => c.due > 0).length,
    });

    document.querySelectorAll('#customer-type-tabs span').forEach(el => {
      const type = el.dataset.type;
      const count = type === 'All' ? total : customers.filter(c => c.type === type).length;
      el.textContent = `${type === 'All' ? 'All' : type} (${count})`;
    });

    Atelier.refreshCounters();
  }

  window.exportCustomers = function() {
    const rows = [['Code', 'Name', 'Phone', 'Email', 'Type', 'City', 'Orders', 'Total Spent', 'Outstanding', 'Since']];
    getFilteredCustomers().forEach(c => rows.push([
      c.id, c.name, c.phone, c.email || '', c.type, c.city || '', c.orders, c.spent, c.due, c.since
    ]));

    const csv = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    Object.assign(document.createElement('a'), {
      href: url, download: `customers-${new Date().toISOString().slice(0, 10)}.csv`
    }).click();
    URL.revokeObjectURL(url);
    toast(`Exported ${rows.length - 1} customers`, 'success');
  };

  /* ============= CUSTOMER TABLE RENDER ============= */
  function getCustomerRowHTML(c, index) {
    const avatarClass = avatarClasses[index % avatarClasses.length];
    const initials = (c.name || 'Unknown').split(' ').map(n => n[0]).join('').slice(0, 2);
    const stars = '★'.repeat(Math.round(c.loyalty || 0));
    const typeBadge = c.type === 'VIP' ? `<span class="badge badge-vip"><i class="fa-solid fa-crown text-[9px] mr-1"></i>VIP</span>` :
                      c.type === 'Premium' ? `<span class="badge badge-ready">Premium</span>` :
                      `<span class="badge badge-delivered">Regular</span>`;
    const behaviorColor = c.behavior === 'Always Pays' ? 'text-emerald-600' :
                          c.behavior === 'Payment Pending' ? 'text-red-500' :
                          c.behavior === 'New Customer' ? 'text-slate-500' : 'text-amber-600';

    return `
      <tr class="hover:bg-slate-50 cursor-pointer transition-colors" onclick="openCustomer360('${c.id}')">
        <td class="px-5 py-3" onclick="event.stopPropagation()"><input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"></td>
        <td class="px-5 py-3">
          <div class="flex items-center gap-3">
            <div class="avatar sm ${avatarClass}">${initials}</div>
            <div>
              <div class="font-semibold text-slate-900">${Atelier.escapeHtml(c.name)}</div>
              <div class="text-xs text-slate-500">${c.id} · Since ${c.since || 'Unknown'}</div>
            </div>
          </div>
        </td>
        <td class="px-5 py-3">
          <div class="text-sm text-slate-600">${Atelier.escapeHtml(c.phone || '')}</div>
          <div class="text-xs text-slate-500">${Atelier.escapeHtml(c.email || '')}</div>
        </td>
        <td class="px-5 py-3">${typeBadge}</td>
        <td class="px-5 py-3">
          <div class="flex items-center gap-1 text-amber-400">
            ${stars}<span class="text-slate-400 text-xs ml-1">${c.loyalty || 0}</span>
          </div>
        </td>
        <td class="px-5 py-3">
          <span class="${behaviorColor} text-xs font-semibold">${c.behavior}</span>
        </td>
        <td class="px-5 py-3 font-semibold text-slate-900">${Atelier.money(c.spent)}</td>
        <td class="px-5 py-3 text-right whitespace-nowrap" onclick="event.stopPropagation()">
          <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center transition-colors" title="View" onclick="openCustomer360('${c.id}')"><i class="fa-regular fa-eye text-xs"></i></button>

          <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-amber-50 hover:text-amber-600 inline-flex items-center justify-center transition-colors" title="Edit" onclick="editCustomer('${c.id}')"><i class="fa-solid fa-pen text-xs"></i></button>

          <button type="button" onclick="deleteCustomer(${c.db_id})" class="w-8 h-8 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 inline-flex items-center justify-center transition-colors" title="Delete"><i class="fa-solid fa-trash text-xs"></i></button>
        </td>
      </tr>
    `;
  }

  window.deleteCustomer = function(dbId) {
    const c = customers.find(x => x.db_id === dbId);
    if (!c) return;

    Atelier.confirmAction({
      title: `Delete ${c.name}?`,
      message: c.orders > 0
        ? `${c.name} has ${c.orders} order(s) on record. Customers with order history cannot be deleted.`
        : 'This will permanently remove the customer from your directory. This action cannot be undone.',
      confirmLabel: 'Delete Customer',
      danger: true,
      onConfirm: async () => {
        try {
          const res = await Atelier.api.delete(`/customers/${dbId}`);
          customers = customers.filter(x => x.db_id !== dbId);
          renderCustomerTable();
          recalcStats();
          toast(res.message, 'success');
        } catch (err) {
          Atelier.reportError(err, 'Could not delete the customer');
        }
      }
    });
  };

  function renderCustomerTable() {
    const list = document.getElementById('customer-list');
    const pageInfo = document.getElementById('page-info');
    const pageControls = document.getElementById('page-controls');

    const filtered = getFilteredCustomers();

    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;

    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const pageItems = filtered.slice(start, end);

    list.innerHTML = pageItems.map((c, idx) => getCustomerRowHTML(c, idx + start)).join('')
      || Atelier.emptyRow(8, {
        icon: 'fa-users',
        title: customerSearch || customerType !== 'All' ? 'No matching customers' : 'No customers yet',
        message: customerSearch || customerType !== 'All'
          ? 'Try a different search term or switch the type filter.'
          : 'Add your first customer to start building the directory.'
      });

    const totalCustomersText = document.getElementById('total-customers-text');
    if(totalCustomersText) totalCustomersText.innerText = customers.length;

    if (filtered.length === 0) {
      pageInfo.innerText = 'Showing 0 to 0 of 0 results';
      pageControls.innerHTML = '';
      return;
    }

    const currentStart = start + 1;
    const currentEnd = Math.min(end, filtered.length);
    pageInfo.innerText = `Showing ${currentStart} to ${currentEnd} of ${filtered.length} results`;

    let controlsHTML = `
      <button onclick="changeCustomerPage(-1)" ${currentPage === 1 ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
        <i class="fa-solid fa-chevron-left text-xs"></i>
      </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      controlsHTML += `
        <button onclick="goToCustomerPage(${i})" class="w-8 h-8 flex items-center justify-center text-xs font-medium rounded-md transition-colors ${currentPage === i ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}">
          ${i}
        </button>
      `;
    }

    controlsHTML += `
      <button onclick="changeCustomerPage(1)" ${currentPage === totalPages ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
        <i class="fa-solid fa-chevron-right text-xs"></i>
      </button>
    `;

    pageControls.innerHTML = controlsHTML;
  }

  function changeCustomerPage(dir) {
    const totalPages = Math.ceil(getFilteredCustomers().length / itemsPerPage);
    const newPage = currentPage + dir;
    if (newPage >= 1 && newPage <= totalPages) {
      currentPage = newPage;
      renderCustomerTable();
    }
  }

  function goToCustomerPage(page) {
    currentPage = page;
    renderCustomerTable();
  }

  /* ============= CUSTOMER 360 VIEW ============= */
  /* Cached per customer so reopening a profile is instant. Cleared by the
     router's page cache rules whenever anything is written. */
  var customer360Cache = new Map();

  function openCustomer360(id) {
    const c = customers.find(x => x.id === id);
    if (!c) return;

    selectedCustomerFor360 = c;
    customer360Tab = 'orders';

    // Seed from the local row plus any orders we already hold, so the modal is
    // fully populated on open rather than showing a spinner.
    customer360Data = customer360Cache.get(c.db_id) || {
      orders: orders.filter(o => o.cid === c.id),
      measurements: null,
      payments: null,
    };

    openModal('customer-360');

    if (customer360Cache.has(c.db_id)) return;

    Atelier.api.get(`/customers/${c.db_id}/summary`)
      .then(res => {
        customer360Cache.set(c.db_id, res);

        // Only refresh if this profile is still the one on screen.
        if (selectedCustomerFor360 && selectedCustomerFor360.db_id === c.db_id) {
          selectedCustomerFor360 = res.customer;
          customer360Data = res;
          openModal('customer-360');
        }
      })
      .catch(err => Atelier.reportError(err, 'Could not load the customer profile'));
  }

  window.setCustomer360Tab = function(tab) {
    customer360Tab = tab;
    openModal('customer-360');
  };

  /* ============= EDIT CUSTOMER ============= */
  function editCustomer(id) {
    const c = customers.find(x => x.id === id);
    if (!c) return;

    Atelier.clearFieldErrors(document.getElementById('edit-customer-form'));
    document.getElementById('edit-customer-form').action = '/customers/' + c.db_id;
    document.getElementById('edit-name').value = c.name || '';
    document.getElementById('edit-phone').value = c.phone || '';
    document.getElementById('edit-email').value = c.email || '';
    document.getElementById('edit-city').value = c.city || '';
    document.getElementById('edit-type').value = c.type || 'Regular';
    
    openDrawer('edit-customer-drawer');
  }

  /* ============= MODALS ============= */
  window.modals = window.modals || {};
  window.modals['customer-360'] = () => `
    ${selectedCustomerFor360 ? `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div class="flex items-center gap-3">
          <div class="avatar lg slate">${selectedCustomerFor360.name.split(' ').map(n => n[0]).join('').slice(0, 2)}</div>
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">${selectedCustomerFor360.name}</div>
            <div class="text-xs text-slate-500">${selectedCustomerFor360.id} · Customer since ${selectedCustomerFor360.since}</div>
          </div>
        </div>
        <div class="flex gap-2">
          <a href="tel:${selectedCustomerFor360.phone}" class="w-9 h-9 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-900 flex items-center justify-center transition-colors"><i class="fa-solid fa-phone text-sm"></i></a>
          <button type="button" onclick="sendCustomerSms(this)" title="Send SMS" class="w-9 h-9 rounded-lg border border-slate-200 text-emerald-500 hover:bg-emerald-50 flex items-center justify-center"><i class="fa-solid fa-comment-sms text-sm"></i></button>
          <button class="w-9 h-9 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center transition-colors" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-0">
        <div class="lg:col-span-1 p-6 bg-slate-50 border-r border-slate-200">
          <div class="space-y-4">
            <div>
              <div class="text-[11px] text-slate-500 uppercase font-bold tracking-widest">Contact Info</div>
              <div class="text-sm text-slate-900 mt-1 font-medium">${selectedCustomerFor360.phone}</div>
              <div class="text-sm text-slate-500">${selectedCustomerFor360.email}</div>
              <div class="text-sm text-slate-500 mt-1 flex items-center gap-1"><i class="fa-solid fa-location-dot text-xs"></i> ${selectedCustomerFor360.city}</div>
            </div>
            <div class="pt-4 border-t border-slate-200">
              <div class="text-[11px] text-slate-500 uppercase font-bold tracking-widest">Loyalty Score</div>
              <div class="flex items-center gap-1 text-amber-400 mt-1 text-lg">
                ${'★'.repeat(Math.round(selectedCustomerFor360.loyalty))}<span class="text-slate-400 text-sm ml-1">${selectedCustomerFor360.loyalty}/5</span>
              </div>
            </div>
            <div class="pt-4 border-t border-slate-200">
              <div class="text-[11px] text-slate-500 uppercase font-bold tracking-widest">Payment Behavior</div>
              <span class="${selectedCustomerFor360.behavior === 'Always Pays' ? 'text-emerald-600' : selectedCustomerFor360.behavior === 'Has Dues' ? 'text-red-500' : 'text-amber-600'} text-sm font-semibold mt-1 block">${selectedCustomerFor360.behavior}</span>
            </div>
            ${selectedCustomerFor360.notes ? `
              <div class="pt-4 border-t border-slate-200">
                <div class="text-[11px] text-slate-500 uppercase font-bold tracking-widest">Notes</div>
                <div class="text-xs text-slate-600 mt-1 bg-amber-50 text-amber-700 p-2 rounded">${selectedCustomerFor360.notes}</div>
              </div>
            ` : ''}
          </div>
        </div>
        <div class="lg:col-span-3 p-6">
          <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-slate-50 p-3 rounded-lg text-center">
              <div class="text-[10px] text-slate-500 uppercase font-bold tracking-widest">Total Orders</div>
              <div class="text-xl font-bold text-slate-900 mt-1">${selectedCustomerFor360.orders}</div>
            </div>
            <div class="bg-slate-50 p-3 rounded-lg text-center">
              <div class="text-[10px] text-slate-500 uppercase font-bold tracking-widest">Total Spent</div>
              <div class="text-xl font-bold text-slate-900 mt-1">${Atelier.money(selectedCustomerFor360.spent)}</div>
            </div>
            <div class="bg-slate-50 p-3 rounded-lg text-center">
              <div class="text-[10px] text-slate-500 uppercase font-bold tracking-widest">Avg Order Value</div>
              <div class="text-xl font-bold text-slate-900 mt-1">${Atelier.money(selectedCustomerFor360.avg || 0)}</div>
            </div>
          </div>
          
          <div class="flex gap-4 border-b border-slate-200 mb-4">
            ${[['orders', 'Order History'], ['measurements', 'Measurements'], ['payments', 'Payment History']].map(([key, label]) => `
              <div class="py-2 text-sm cursor-pointer ${customer360Tab === key ? 'font-semibold text-slate-900 border-b-2 border-slate-900' : 'font-medium text-slate-500 hover:text-slate-900'}" onclick="setCustomer360Tab('${key}')">${label}</div>
            `).join('')}
          </div>

          <div class="space-y-2 max-h-[300px] overflow-y-auto">
            ${!customer360Data ? `
              <div class="text-center py-10"><i class="fa-solid fa-spinner fa-spin text-indigo-600 text-xl mb-2"></i><div class="text-xs text-slate-500">Loading history…</div></div>
            ` : customer360Tab === 'orders' ? (
              customer360Data.orders.map(o => `
                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50 hover:bg-slate-100 cursor-pointer transition-colors" onclick="closeModal(); window.location.href='/orders?highlight=${o.db_id}'">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-400">
                      <i class="fa-solid fa-scissors text-xs"></i>
                    </div>
                    <div>
                      <div class="text-sm font-semibold text-slate-900">${o.id} · ${Atelier.escapeHtml(o.garment)}</div>
                      <div class="text-xs text-slate-500">Due: ${o.due} · ${Atelier.money(o.amount)}${o.balance > 0 ? ` · <span class="text-red-500 font-semibold">${Atelier.money(o.balance)} due</span>` : ''}</div>
                    </div>
                  </div>
                  <span class="badge ${Atelier.badgeClass(o.status)}">${o.status}</span>
                </div>
              `).join('') || '<div class="text-center text-slate-400 py-8">No orders found</div>'
            ) : customer360Tab === 'measurements' ? (
              !customer360Data.measurements
                ? '<div class="text-center py-10"><i class="fa-solid fa-spinner fa-spin text-indigo-600 text-xl mb-2"></i><div class="text-xs text-slate-500">Loading measurements…</div></div>'
                : customer360Data.measurements.map(m => `
                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50 hover:bg-slate-100 cursor-pointer transition-colors" onclick="closeModal(); window.location.href='/measurements?highlight=${m.id}'">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-400">
                      <i class="fa-solid fa-ruler-combined text-xs"></i>
                    </div>
                    <div>
                      <div class="text-sm font-semibold text-slate-900">${Atelier.escapeHtml(m.garment_type || 'Measurement')}</div>
                      <div class="text-xs text-slate-500">${m.date} · ${m.unit} · ${m.tailor ? Atelier.escapeHtml(m.tailor) : 'Unassigned'}</div>
                    </div>
                  </div>
                  <span class="text-xs font-semibold text-slate-600">${m.completeness}% complete</span>
                </div>
              `).join('') || '<div class="text-center text-slate-400 py-8">No measurements recorded</div>'
            ) : (
              !customer360Data.payments
                ? '<div class="text-center py-10"><i class="fa-solid fa-spinner fa-spin text-indigo-600 text-xl mb-2"></i><div class="text-xs text-slate-500">Loading payments…</div></div>'
                : customer360Data.payments.map(p => `
                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-emerald-500">
                      <i class="fa-solid fa-receipt text-xs"></i>
                    </div>
                    <div>
                      <div class="text-sm font-semibold text-slate-900">${Atelier.money(p.amount)} · ${Atelier.escapeHtml(p.method)}</div>
                      <div class="text-xs text-slate-500">${p.invoice || ''} · ${p.date || ''}</div>
                    </div>
                  </div>
                  <span class="badge badge-delivered">${p.status}</span>
                </div>
              `).join('') || '<div class="text-center text-slate-400 py-8">No payments recorded</div>'
            )}
          </div>
        </div>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Close</button>
        <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="closeModal(); window.location.href='/orders?action=create&customer=${selectedCustomerFor360.db_id}'"><i class="fa-solid fa-plus text-xs"></i> New Order for ${Atelier.escapeHtml(selectedCustomerFor360.name.split(' ')[0])}</button>
      </div>
    ` : ''}
  `;

  /* ⌘K and Escape are already handled globally in the layout, so this page
     no longer binds its own duplicates. */

  /* ========== INITIALIZATION ========== */
  Atelier.onPageReady(() => {
    renderCustomerTable();

    // Add customer — saves, then hands straight over to a new order for them.
    Atelier.ajaxForm('#add-customer-form', {
      onSuccess: (res) => {
        customers.unshift(res.customer);
        customerSearch = '';
        currentPage = 1;
        const search = document.getElementById('customer-search');
        if (search) search.value = '';

        renderCustomerTable();
        recalcStats();
        closeDrawers();
        toast(res.message, 'success');

        // Short pause so the confirmation is readable before we move on.
        setTimeout(() => {
          window.location.href = `{{ route('orders.index') }}?action=create&customer=${res.customer.db_id}&new_customer=1`;
        }, 650);
      }
    });

    // Edit customer — updates the row in place.
    Atelier.ajaxForm('#edit-customer-form', {
      reset: false,
      onSuccess: (res) => {
        const i = customers.findIndex(c => c.db_id === res.customer.db_id);
        if (i > -1) customers[i] = res.customer;

        renderCustomerTable();
        recalcStats();
        closeDrawers();
        toast(res.message, 'success');
      }
    });

    const highlight = parseInt(new URLSearchParams(window.location.search).get('highlight'), 10);
    if (highlight) {
      const c = customers.find(x => x.db_id === highlight);
      if (c) openCustomer360(c.id);
    }
  });
</script>
@endpush
