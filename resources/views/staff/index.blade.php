@extends('layouts.app')
@section('spaPage', 'staff')
@section('title', 'Tailors')

@push('styles')
<style>
  .badge::before { display: none; }
  .badge-active   { background: #ECFDF5; color: #10B981; }
  .badge-inactive { background: #F1F5F9; color: #64748B; }
  .badge-paid     { background: #ECFDF5; color: #10B981; }
  .badge-partial  { background: #FFFBEB; color: #F59E0B; }
  .badge-pending  { background: #FEF2F2; color: #EF4444; }

  .modal.modal-xl { max-width: 960px; }

  /* Profile drawer tabs — same pill language as the filter rows elsewhere. */
  .sf-tab { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; color: #64748b; cursor: pointer; }
  .sf-tab.on { background: #0f172a; color: #fff; }
</style>
@endpush

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Tailors</h1>
    <p class="text-sm text-slate-500 mt-0.5" id="subheader">Loading stitching history…</p>
  </div>
  <div class="flex gap-2">
    <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="openStaffForm()"><i class="fa-solid fa-plus text-[10px]"></i> Add Tailor</button>
  </div>
</div>

<!-- Stats Row -->
<div class="page grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Tailors</span>
      <div class="w-7 h-7 rounded-md bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-users text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" id="stat-total">0</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1"><span id="stat-active">0</span> active · <span id="stat-inactive">0</span> inactive</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">This Week</span>
      <div class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-wallet text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" id="stat-week">0</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">clothes stitched</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">This Month</span>
      <div class="w-7 h-7 rounded-md bg-red-50 text-red-600 flex items-center justify-center"><i class="fa-solid fa-clock text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" id="stat-month">0</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">clothes stitched</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">All Time</span>
      <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-scissors text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" id="stat-pieces">0</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">clothes stitched</p>
  </div>
</div>

<!-- Table Section -->
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
  <div class="p-5 border-b border-slate-200 flex items-center justify-between gap-3 bg-slate-50 flex-wrap">
    <div class="flex gap-1 flex-wrap" id="staffPills">
      <span class="px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer" onclick="filterStaff('All', this)">All</span>
      <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="filterStaff('Active', this)">Active</span>
      <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="filterStaff('Inactive', this)">Inactive</span>
      <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="filterStaff('Unpaid', this)">Salary Due</span>
    </div>
    <div class="relative">
      <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
      <input type="search" id="staff-search" placeholder="Search name, phone or role…" oninput="searchStaff(this.value)"
             class="w-64 pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 transition-colors">
    </div>
  </div>

  <div class="overflow-x-auto min-h-[300px]">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
        <tr>
          <th class="px-5 py-3 text-left font-bold">Name</th>
          <th class="px-5 py-3 text-left font-bold">Role</th>
          <th class="px-5 py-3 text-left font-bold">Phone</th>
          <th class="px-5 py-3 text-left font-bold">This Week</th>
          <th class="px-5 py-3 text-left font-bold">This Month</th>
          <th class="px-5 py-3 text-left font-bold">Overall</th>
          <th class="px-5 py-3 text-left font-bold">Status</th>
          <th class="px-5 py-3 text-right font-bold">Actions</th>
        </tr>
      </thead>
      <tbody id="staffTableBody" class="divide-y divide-slate-100"></tbody>
    </table>
  </div>
  <div id="staff-pagination" class="px-6 py-3 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center text-sm gap-3"></div>
</div>
@endsection

@push('scripts')
<script>
  /* ============= DATA STORE ============= */
  var staffList   = @json($staff);
  var STAFF_STATS = @json($stats);
  var ROLE_OPTIONS   = @json($roles);
  var SALARY_TYPES   = @json($salaryTypes);
  var PAY_METHODS    = @json($methods);

  var ROUTES = {
    index: @json(route('staff.index')),
    store: @json(route('staff.store')),
  };

  /* ============= STATE ============= */
  var currentPage   = 1;
  var itemsPerPage  = Atelier.rowsPerPage();
  var staffFilter   = 'All';
  var staffQuery    = '';
  var profileTab    = 'overview';
  var profileData   = null;
  var profileCache = new Map();
  var profileRequests = new Map();

  function loadProfile(id) {
    if (profileRequests.has(id)) return profileRequests.get(id);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    const pending = Atelier.api.get(`/staff/${id}`, { signal: controller.signal })
      .then(data => {
        if (!data?.staff) throw new Error('Profile response was empty');
        profileCache.set(id, data);
        return data;
      })
      .finally(() => { clearTimeout(timeout); profileRequests.delete(id); });
    profileRequests.set(id, pending);
    return pending;
  }

  /* ============= HELPERS ============= */
  function upsertStaff(payload) {
    if (!payload) return;
    const i = staffList.findIndex(s => s.db_id === payload.db_id);
    if (i > -1) staffList[i] = payload; else staffList.unshift(payload);
  }

  function findStaff(id) {
    return staffList.find(s => s.db_id === id);
  }

  /** Current month as YYYY-MM, which is what the salary period expects. */
  function thisPeriod() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  }

  const statusBadge = { Paid: 'badge-paid', Partial: 'badge-partial', Pending: 'badge-pending' };

  /* ============= FILTER + SEARCH ============= */
  window.filterStaff = function(which, el = null) {
    staffFilter = which;
    currentPage = 1;

    document.querySelectorAll('#staffPills span').forEach(s => {
      s.className = s.textContent.trim() === (which === 'Unpaid' ? 'Salary Due' : which)
        ? 'px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer'
        : 'px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors';
    });

    renderStaff();
  }

  window.searchStaff = function(value) {
    staffQuery = (value || '').toLowerCase().trim();
    currentPage = 1;
    renderStaff();
  }

  function getFilteredStaff() {
    let list = staffList;

    if (staffFilter === 'Active')   list = list.filter(s => s.is_active);
    if (staffFilter === 'Inactive') list = list.filter(s => !s.is_active);
    if (staffFilter === 'Unpaid')   list = list.filter(s => s.due.remaining > 0);

    if (staffQuery) {
      list = list.filter(s =>
        s.name.toLowerCase().includes(staffQuery) ||
        (s.phone || '').toLowerCase().includes(staffQuery) ||
        (s.role || '').toLowerCase().includes(staffQuery));
    }

    return list;
  }

  /* ============= RENDER ============= */
  function renderStaff() {
    const body = document.getElementById('staffTableBody');
    const foot = document.getElementById('staff-pagination');
    if (!body) return;

    const filtered   = getFilteredStaff();
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;

    const start = (currentPage - 1) * itemsPerPage;
    const page  = filtered.slice(start, start + itemsPerPage);

    if (page.length === 0) {
      body.innerHTML = Atelier.emptyRow(8, {
        icon: 'fa-users',
        title: staffList.length === 0 ? 'No tailors yet' : 'No matching tailors',
        message: staffList.length === 0
          ? 'Add the tailors who stitch clothes in the shop.'
          : 'Try a different filter or search.'
      });
      foot.innerHTML = '';
      return;
    }

    body.innerHTML = page.map(s => {
      /* How this person is paid decides what is worth showing in the salary
         column — a monthly figure means nothing for someone on piece rate. */
      const salaryLine = s.salary_type === 'Per Suit'
        ? `${Atelier.money(s.per_suit_rate)} <span class="text-slate-400">/ suit</span>`
        : s.salary_type === 'Both'
          ? `${Atelier.money(s.monthly_salary)} <span class="text-slate-400">+ ${Atelier.money(s.per_suit_rate)}/suit</span>`
          : `${Atelier.money(s.monthly_salary)} <span class="text-slate-400">/ month</span>`;

      return `
        <tr class="hover:bg-slate-50 transition-colors ${s.is_active ? '' : 'opacity-60'}">
          <td class="px-5 py-3">
            <div class="flex items-center gap-3 cursor-pointer" onclick="openProfile(${s.db_id})">
              <div class="avatar sm bg-slate-900">${s.initials}</div>
              <div>
                <div class="font-semibold text-slate-900">${Atelier.escapeHtml(s.name)}</div>
                <div class="text-xs text-slate-400">Joined ${s.joined}</div>
              </div>
            </div>
          </td>
          <td class="px-5 py-3 text-slate-600">${Atelier.escapeHtml(s.role)}</td>
          <td class="px-5 py-3 text-slate-500">${Atelier.escapeHtml(s.phone || '—')}</td>
          <td class="px-5 py-3 font-semibold text-slate-900">${s.week_pieces} pcs</td>
          <td class="px-5 py-3 font-semibold text-slate-900">${s.month_pieces} pcs</td>
          <td class="px-5 py-3 font-semibold text-slate-900">${s.pieces} pcs</td>
          <td class="px-5 py-3 whitespace-nowrap">
            <span class="badge ${s.is_active ? 'badge-active' : 'badge-inactive'}">${s.is_active ? 'Active' : 'Inactive'}</span>
          </td>
          <td class="px-5 py-3 text-right whitespace-nowrap">
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="Profile" onclick="openProfile(${s.db_id})"><i class="fa-regular fa-eye text-xs"></i></button>
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 inline-flex items-center justify-center mr-1 transition-colors" title="Pay salary" onclick="openPaymentForm(${s.db_id})"><i class="fa-solid fa-money-bill-wave text-xs"></i></button>
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 inline-flex items-center justify-center mr-1 transition-colors" title="Edit" onclick="openStaffForm(${s.db_id})"><i class="fa-solid fa-pen text-xs"></i></button>
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-amber-50 hover:text-amber-600 inline-flex items-center justify-center mr-1 transition-colors" title="${s.is_active ? 'Deactivate' : 'Activate'}" onclick="toggleActive(${s.db_id})"><i class="fa-solid ${s.is_active ? 'fa-user-slash' : 'fa-user-check'} text-xs"></i></button>
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-500 inline-flex items-center justify-center transition-colors" title="Delete" onclick="confirmDeleteStaff(${s.db_id})"><i class="fa-solid fa-trash text-xs"></i></button>
          </td>
        </tr>`;
    }).join('');

    /* Pagination, same shape as every other table in the panel. */
    let html = `<div class="text-xs text-slate-500">Showing ${start + 1} to ${Math.min(start + itemsPerPage, filtered.length)} of ${filtered.length} results</div><div class="flex items-center gap-1">`;
    html += `<button onclick="staffPage(-1)" ${currentPage === 1 ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"><i class="fa-solid fa-chevron-left text-xs"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
      html += `<button onclick="staffGoto(${i})" class="w-8 h-8 flex items-center justify-center text-xs font-medium rounded-md transition-colors ${currentPage === i ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}">${i}</button>`;
    }
    html += `<button onclick="staffPage(1)" ${currentPage === totalPages ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"><i class="fa-solid fa-chevron-right text-xs"></i></button></div>`;
    foot.innerHTML = html;
  }

  window.staffPage  = function(dir)  { const t = Math.ceil(getFilteredStaff().length / itemsPerPage); const n = currentPage + dir; if (n >= 1 && n <= t) { currentPage = n; renderStaff(); } };
  window.staffGoto  = function(page) { currentPage = page; renderStaff(); };

  window.updateStaffStats = function() {
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.innerText = v; };
    const s = STAFF_STATS;

    set('stat-total', s.total);
    set('stat-active', s.active);
    set('stat-inactive', s.inactive);
    set('stat-week', s.week_pieces);
    set('stat-month', s.month_pieces);
    set('stat-pieces', s.pieces);
    set('subheader', `${s.active} active tailors · ${s.month_pieces} clothes stitched this month`);
  }

  /** Pulls fresh rows and stats without a page reload. */
  async function refreshStaff() {
    if (profileRequests.size) return;
    const fresh = await Atelier.api.get(ROUTES.index + '?json=1');
    if (fresh?.staff) {
      staffList   = fresh.staff;
      STAFF_STATS = fresh.stats;
      renderStaff();
      updateStaffStats();
      const profileId = profileData?.staff?.db_id;
      if (profileId && document.getElementById('profile-body') && document.getElementById('modal-backdrop')?.classList.contains('show')) {
        const latest = await loadProfile(profileId);
        if (profileData?.staff?.db_id === profileId && document.getElementById('profile-body')) {
          profileData = latest;
          renderProfileBody();
        }
      }
    }
  }

  /* ============= CRUD ============= */
  window.openStaffForm = function(id = null) {
    openModal('staff-form', id ? findStaff(id) : null);
  }

  window.saveStaff = async function(id, btn) {
    const val = (n) => document.getElementById('sf-' + n)?.value?.trim() ?? '';

    const payload = {
      name:           val('name'),
      phone:          val('phone'),
      address:        val('address'),
      joining_date:   val('joining') || null,
      role:           val('role'),
      salary_type:    val('salary-type').split('|')[0],
      payment_period: val('salary-type').split('|')[1] || 'Monthly',
      monthly_salary: parseFloat(val('monthly')) || 0,
      per_suit_rate:  parseFloat(val('rate')) || 0,
      is_active:      document.getElementById('sf-active')?.checked ? 1 : 0,
      notes:          val('notes'),
    };

    if (!payload.name) { toast('Name is required', 'error'); return; }

    Atelier.setBusy(btn, true);
    try {
      const res = id
        ? await Atelier.api.put(`/staff/${id}`, payload)
        : await Atelier.api.post(ROUTES.store, payload);

      upsertStaff(res.staff);
      closeModal();
      toast(res.message, 'success');
      await refreshStaff();
    } catch (err) {
      Atelier.reportError(err, 'Could not save this staff member');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  window.toggleActive = async function(id) {
    try {
      const res = await Atelier.api.patch(`/staff/${id}/active`);
      upsertStaff(res.staff);
      renderStaff();
      toast(res.message, 'success');
      await refreshStaff();
    } catch (err) {
      Atelier.reportError(err, 'Could not change the status');
    }
  }

  window.confirmDeleteStaff = function(id) {
    const s = findStaff(id);
    if (!s) return;

    Atelier.confirm({
      variant: 'delete',
      title: `Delete ${s.name}?`,
      message: 'Tailors with salary payments or completed work cannot be deleted — deactivate them instead so the history stays intact.',
      confirmLabel: 'Delete',
      onConfirm: async () => {
        try {
          const res = await Atelier.api.delete(`/staff/${id}`);
          staffList = staffList.filter(x => x.db_id !== id);
          renderStaff();
          toast(res.message, 'success');
          await refreshStaff();
        } catch (err) {
          Atelier.reportError(err, 'Could not delete this staff member');
        }
      },
    });
  }

  /* ============= PROFILE ============= */
  window.openProfile = async function(id) {
    profileTab = 'overview';
    const staff = findStaff(id);
    if (!staff) return;
    profileData = profileCache.has(id)
      ? { ...profileCache.get(id), staff, due: staff.due }
      : { staff, due: staff.due, detailsLoading: true };
    openModal('staff-profile', staff);
    renderProfileBody();

    try {
      const data = await loadProfile(id);
      if (profileData?.staff?.db_id !== id) return;
      profileData = data;
      renderProfileBody();
    } catch (err) {
      if (profileData?.staff?.db_id !== id) return;
      profileData.detailsLoading = false;
      profileData.detailsError = !profileCache.has(id);
      renderProfileBody();
    }
  }

  window.setProfileTab = function(tab) {
    profileTab = tab;
    document.querySelectorAll('#profile-tabs .sf-tab').forEach(el => {
      el.classList.toggle('on', el.dataset.tab === tab);
    });
    renderProfileBody();
  }

  function renderProfileBody() {
    const body = document.getElementById('profile-body');
    if (!body) return;

    if (!profileData) {
      body.innerHTML = '<div class="text-sm text-slate-400 text-center py-8"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading…</div>';
      return;
    }

    const s = profileData.staff;
    if (profileTab !== 'overview' && (profileData.detailsLoading || profileData.detailsError)) {
      body.innerHTML = profileData.detailsError
        ? `<div class="text-sm text-slate-500 text-center py-8">History could not load. <button class="text-indigo-600 font-semibold underline" onclick="openProfile(${Number(s.db_id)})">Retry</button></div>`
        : '<div class="text-sm text-slate-400 text-center py-8"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading history…</div>';
      return;
    }
    const money = (value) => Atelier.money(value, true);
    const esc = Atelier.escapeHtml;

    const row = (k, v) => `<div class="flex justify-between text-sm"><span class="text-slate-500">${k}</span><span class="font-semibold text-slate-900 text-right max-w-[60%]">${v}</span></div>`;

    if (profileTab === 'overview') {
      const d = profileData.due;
      body.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-3">
            <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1">Personal</h4>
            ${row('Phone', esc(s.phone || '—'))}
            ${row('Address', esc(s.address || '—'))}
            ${row('Joined', s.joined)}
            ${row('Role', esc(s.role))}
            ${row('Status', s.is_active ? '<span class="badge badge-active">Active</span>' : '<span class="badge badge-inactive">Inactive</span>')}
          </div>
          <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
            <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1">Salary · ${d.period}</h4>
            ${row('Paid as', esc(s.salary_type))}
            ${s.salary_type !== 'Per Suit' ? row('Monthly salary', money(s.monthly_salary)) : ''}
            ${s.salary_type !== 'Monthly' ? row('Per-suit rate', money(s.per_suit_rate)) : ''}
            ${row(`${d.pieces} completed pieces`, money(d.stitching))}
            ${row('Earned this period', money(d.earned))}
            ${row('Paid this month', money(d.paid))}
            <div class="flex justify-between border-t border-slate-200 pt-2 mt-2">
              <span class="font-bold text-slate-900">Remaining</span>
              <span class="font-bold ${d.remaining > 0 ? 'text-red-500' : 'text-emerald-600'}">${money(d.remaining)}</span>
            </div>
          </div>
          <div class="md:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-3">
            ${[['This week', s.week_pieces + ' pcs'], ['This month', s.month_pieces + ' pcs'], ['All time', s.pieces + ' pcs'], ['Completed orders', s.completed]]
              .map(([k, v]) => `<div class="bg-slate-50 p-3 rounded-lg border border-slate-100 text-center">
                <div class="text-lg font-bold text-slate-900">${v}</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5">${k}</div></div>`).join('')}
          </div>
        </div>`;
      return;
    }

    if (profileTab === 'orders') {
      const list = profileData.orders || [];
      body.innerHTML = list.length ? `
        <table class="w-full text-sm">
          <thead class="text-slate-500 text-[10px] uppercase tracking-widest"><tr>
            <th class="px-3 py-2 text-left font-bold">Order</th><th class="px-3 py-2 text-left font-bold">Customer</th>
            <th class="px-3 py-2 text-left font-bold">Garment</th><th class="px-3 py-2 text-left font-bold">Due</th>
            <th class="px-3 py-2 text-left font-bold">Status</th><th class="px-3 py-2 text-right font-bold">Amount</th>
          </tr></thead>
          <tbody class="divide-y divide-slate-100">
            ${list.map(o => `<tr>
              <td class="px-3 py-2 font-semibold text-slate-900">${esc(o.id)}</td>
              <td class="px-3 py-2 text-slate-600">${esc(o.customer)}</td>
              <td class="px-3 py-2 text-slate-500">${esc(o.garment)}</td>
              <td class="px-3 py-2 text-slate-500">${esc(o.due)}</td>
              <td class="px-3 py-2"><span class="badge ${Atelier.badgeClass(o.status)}">${esc(o.status)}</span></td>
              <td class="px-3 py-2 text-right text-slate-900 font-semibold">${money(o.amount)}</td>
            </tr>`).join('')}
          </tbody></table>`
        : '<div class="text-sm text-slate-400 text-center py-8">No orders assigned yet.</div>';
      return;
    }

    if (profileTab === 'work') {
      const list = profileData.work || [];
      body.innerHTML = `
        <div class="flex justify-end mb-3">
          <button class="bg-slate-900 text-white px-3 py-1.5 rounded-md text-xs font-medium hover:bg-slate-800 transition-colors" onclick="openWorkForm(${s.db_id})"><i class="fa-solid fa-plus text-[10px] mr-1"></i> Record Work</button>
        </div>
        ${list.length ? `
        <table class="w-full text-sm">
          <thead class="text-slate-500 text-[10px] uppercase tracking-widest"><tr>
            <th class="px-3 py-2 text-left font-bold">Date</th><th class="px-3 py-2 text-left font-bold">Order</th>
            <th class="px-3 py-2 text-left font-bold">Garment</th><th class="px-3 py-2 text-right font-bold">Qty</th>
            <th class="px-3 py-2 text-right font-bold">Rate</th><th class="px-3 py-2 text-right font-bold">Amount</th>
            <th class="px-3 py-2 text-right font-bold"></th>
          </tr></thead>
          <tbody class="divide-y divide-slate-100">
            ${list.map(w => `<tr>
              <td class="px-3 py-2 text-slate-500">${w.date}</td>
              <td class="px-3 py-2 text-slate-600">${esc(w.order || '—')}</td>
              <td class="px-3 py-2 text-slate-600">${esc(w.garment || '—')}</td>
              <td class="px-3 py-2 text-right text-slate-900">${w.quantity}</td>
              <td class="px-3 py-2 text-right text-slate-500">${money(w.rate)}</td>
              <td class="px-3 py-2 text-right font-semibold text-slate-900">${money(w.amount)}</td>
              <td class="px-3 py-2 text-right"><button class="text-slate-300 hover:text-red-500 transition-colors" title="Remove" onclick="deleteWork(${w.id}, ${s.db_id})"><i class="fa-solid fa-trash text-xs"></i></button></td>
            </tr>`).join('')}
          </tbody></table>`
        : '<div class="text-sm text-slate-400 text-center py-8">No completed stitching recorded yet.</div>'}`;
      return;
    }

    if (profileTab === 'salary') {
      const list = profileData.payments || [];
      body.innerHTML = `
        <div class="flex justify-end mb-3">
          <button class="bg-emerald-500 text-white px-3 py-1.5 rounded-md text-xs font-medium hover:bg-emerald-600 transition-colors" onclick="openPaymentForm(${s.db_id})"><i class="fa-solid fa-plus text-[10px] mr-1"></i> Record Payment</button>
        </div>
        ${list.length ? `
        <table class="w-full text-sm">
          <thead class="text-slate-500 text-[10px] uppercase tracking-widest"><tr>
            <th class="px-3 py-2 text-left font-bold">Date</th><th class="px-3 py-2 text-left font-bold">Period</th>
            <th class="px-3 py-2 text-left font-bold">Method</th><th class="px-3 py-2 text-left font-bold">Status</th>
            <th class="px-3 py-2 text-left font-bold">Notes</th><th class="px-3 py-2 text-right font-bold">Amount</th>
            <th class="px-3 py-2 text-right font-bold"></th>
          </tr></thead>
          <tbody class="divide-y divide-slate-100">
            ${list.map(p => `<tr>
              <td class="px-3 py-2 text-slate-500">${p.date}</td>
              <td class="px-3 py-2 text-slate-600" title="${p.summary ? esc(`${p.summary.pieces} pieces; rates: ${p.summary.rates.map(r => `${r.pieces} x ${money(r.rate)}`).join(', ')}; earned ${money(p.summary.earned)}; paid ${money(p.summary.paid)}; remaining ${money(p.summary.remaining)}`) : ''}">${esc(p.period || '—')}</td>
              <td class="px-3 py-2 text-slate-600">${esc(p.method)}</td>
              <td class="px-3 py-2"><span class="badge ${statusBadge[p.status] || 'badge-inactive'}">${esc(p.status)}</span></td>
              <td class="px-3 py-2 text-slate-500">${esc(p.notes || '—')}</td>
              <td class="px-3 py-2 text-right font-semibold text-slate-900">${money(p.amount)}</td>
              <td class="px-3 py-2 text-right">${p.can_reverse ? `<button class="text-slate-300 hover:text-red-500 transition-colors" title="Reverse" onclick="deletePayment(${p.id}, ${s.db_id})"><i class="fa-solid fa-trash text-xs"></i></button>` : ''}</td>
            </tr>`).join('')}
          </tbody></table>`
        : '<div class="text-sm text-slate-400 text-center py-8">No salary payments recorded yet.</div>'}`;
    }
  }

  /* ============= SALARY + WORK ENTRY ============= */
  window.openPaymentForm = function(id) {
    const s = findStaff(id);
    if (!s) return;
    openModal('staff-payment', s);
    const el = document.getElementById('pay-amount');
    el.dataset.operationKey = window.crypto?.randomUUID?.() || `staff-payment-${Date.now()}-${Math.random()}`;
    window.staffPaymentDue = s.due;
    previewPayment();
  }

  let paymentDueRequest = 0;
  window.previewPayment = function() {
    const due = window.staffPaymentDue;
    const el = document.getElementById('payment-summary');
    if (!due || !el) return;
    const cents = Math.round((parseFloat(document.getElementById('pay-amount').value) || 0) * 100);
    el.textContent = `${due.pieces} pieces - Earned ${Atelier.money(due.earned, true)} - Paid ${Atelier.money((Math.round(due.paid * 100) + cents) / 100, true)} - Remaining ${Atelier.money(Math.max(0, Math.round(due.remaining * 100) - cents) / 100, true)}`;
  };
  window.refreshPaymentDue = async function(id) {
    const request = ++paymentDueRequest;
    const period = document.getElementById('pay-period').value;
    window.staffPaymentDue = null;
    try {
      const res = await Atelier.api.get(`/staff/${id}?period=${encodeURIComponent(period)}`);
      if (request !== paymentDueRequest || document.getElementById('pay-period')?.value !== period) return;
      window.staffPaymentDue = res.due;
      document.getElementById('pay-amount').value = res.due.remaining || '';
      previewPayment();
    } catch (err) { Atelier.reportError(err, 'Could not calculate this period'); }
  };

  window.savePayment = async function(id, btn) {
    if (!window.staffPaymentDue) { toast('Wait for the period calculation', 'error'); return; }
    const payload = {
      amount:  parseFloat(document.getElementById('pay-amount').value),
      method:  document.getElementById('pay-method').value,
      period:  document.getElementById('pay-period').value || null,
      paid_on: document.getElementById('pay-date').value || null,
      notes:   document.getElementById('pay-notes').value.trim() || null,
      operation_key: document.getElementById('pay-amount').dataset.operationKey,
    };

    if (!payload.amount || payload.amount <= 0) { toast('Enter the amount paid', 'error'); return; }

    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.post(`/staff/${id}/payments`, payload);
      upsertStaff(res.staff);
      closeModal();
      toast(res.message, 'success');
      await refreshStaff();
      if (profileData && profileData.staff.db_id === id) openProfile(id);
    } catch (err) {
      Atelier.reportError(err, 'Could not record the payment');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  window.deletePayment = async function(paymentId, staffId) {
    try {
      const res = await Atelier.api.delete(`/staff-payments/${paymentId}`);
      upsertStaff(res.staff);
      toast(res.message, 'success');
      await refreshStaff();
      openProfile(staffId);
    } catch (err) {
      Atelier.reportError(err, 'Could not remove the payment');
    }
  }

  window.openWorkForm = function(id) {
    const s = findStaff(id);
    if (!s) return;
    openModal('staff-work', s);
  }

  window.saveWork = async function(id, btn) {
    const payload = {
      garment:      document.getElementById('wk-garment').value.trim() || null,
      quantity:     parseFloat(document.getElementById('wk-qty').value),
      rate:         parseFloat(document.getElementById('wk-rate').value) || 0,
      completed_on: document.getElementById('wk-date').value || null,
      notes:        document.getElementById('wk-notes').value.trim() || null,
    };

    if (!payload.quantity || payload.quantity <= 0) { toast('Enter how many pieces', 'error'); return; }

    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.post(`/staff/${id}/work`, payload);
      upsertStaff(res.staff);
      closeModal();
      toast(res.message, 'success');
      await refreshStaff();
      if (profileData && profileData.staff.db_id === id) openProfile(id);
    } catch (err) {
      Atelier.reportError(err, 'Could not record the work');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  window.deleteWork = async function(workId, staffId) {
    try {
      const res = await Atelier.api.delete(`/staff-work/${workId}`);
      upsertStaff(res.staff);
      toast(res.message, 'success');
      await refreshStaff();
      openProfile(staffId);
    } catch (err) {
      Atelier.reportError(err, 'Could not remove the entry');
    }
  }

  /** Live preview so the arithmetic is visible before it is saved. */
  window.previewWorkTotal = function() {
    const qty  = parseFloat(document.getElementById('wk-qty')?.value) || 0;
    const rate = parseFloat(document.getElementById('wk-rate')?.value) || 0;
    const el   = document.getElementById('wk-total');
    if (el) el.textContent = Atelier.money(qty * rate);
  }

  /* ============= MODALS ============= */
  const field = (label, inner) => `
    <div>
      <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">${label}</label>
      ${inner}
    </div>`;

  const input = (id, opts = {}) => `<input id="${id}" type="${opts.type || 'text'}" ${opts.step ? `step="${opts.step}"` : ''} ${opts.min !== undefined ? `min="${opts.min}"` : ''} value="${opts.value ?? ''}" placeholder="${opts.placeholder || ''}" ${opts.oninput ? `oninput="${opts.oninput}"` : ''} class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">`;

  window.modals = window.modals || {};
  Object.assign(window.modals, {

    'staff-form': (d) => {
      const editing = !!d;
      const v = d || { salary_type: 'Per Suit', role: 'Tailor', is_active: true, monthly_salary: 0, per_suit_rate: 0 };

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">${editing ? 'Edit Tailor' : 'Add Tailor'}</div>
            <div class="text-xs text-slate-500 mt-1">${editing ? Atelier.escapeHtml(v.name) : 'A tailor, cutter or helper working in the shop'}</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto" style="max-height:65vh">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            ${field('Full Name *', input('sf-name', { value: Atelier.escapeHtml(v.name || ''), placeholder: 'e.g. Ahmed Raza' }))}
            ${field('Phone', input('sf-phone', { value: Atelier.escapeHtml(v.phone || ''), placeholder: '03xx xxxxxxx' }))}
            ${field('Role', `<input id="sf-role" list="role-options" value="${Atelier.escapeHtml(v.role || 'Tailor')}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
              <datalist id="role-options">${ROLE_OPTIONS.map(r => `<option value="${r}">`).join('')}</datalist>`)}
            ${field('Joining Date', input('sf-joining', { type: 'date', value: v.joining_date || '' }))}
            ${field('Salary Type / Payment Period', `<select id="sf-salary-type" onchange="renderSalaryHint()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
              ${SALARY_TYPES.flatMap(t => ['Monthly', 'Weekly', 'Daily'].map(p => `<option value="${t}|${p}" ${v.salary_type === t && (v.payment_period || 'Monthly') === p ? 'selected' : ''}>${t} - ${p}</option>`)).join('')}
            </select>`)}
            ${field('Monthly Salary', input('sf-monthly', { type: 'number', min: 0, step: '0.01', value: v.monthly_salary || 0 }))}
            ${field('Per-Suit Stitching Rate', input('sf-rate', { type: 'number', min: 0, step: '0.01', value: v.per_suit_rate || 0 }))}
            <div class="flex items-end">
              <label class="chk-hit gap-2 text-sm text-slate-700">
                <input type="checkbox" id="sf-active" class="chk" ${v.is_active === false ? '' : 'checked'}> Active
              </label>
            </div>
            <div class="sm:col-span-2">
              ${field('Address', `<textarea id="sf-address" rows="2" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">${Atelier.escapeHtml(v.address || '')}</textarea>`)}
            </div>
            <div class="sm:col-span-2">
              ${field('Notes', `<textarea id="sf-notes" rows="2" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">${Atelier.escapeHtml(v.notes || '')}</textarea>`)}
            </div>
          </div>
          <p class="text-xs text-slate-500 mt-4" id="salary-hint"></p>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="saveStaff(${editing ? v.db_id : 'null'}, this)"><i class="fa-solid fa-check text-xs mr-1"></i> ${editing ? 'Save Changes' : 'Add Tailor'}</button>
        </div>`;
    },

    'staff-payment': (s) => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div>
          <div class="text-lg font-bold text-slate-900 tracking-tight">Record Salary Payment</div>
          <div id="payment-summary" class="text-xs text-slate-500 mt-1">${Atelier.escapeHtml(s.name)} · ${Atelier.money(s.due.remaining, true)} remaining for ${s.due.period}</div>
        </div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-6">
        <div class="grid grid-cols-2 gap-4">
          ${field('Amount *', input('pay-amount', { type: 'number', min: 0, step: '0.01', value: s.due.remaining || '', oninput: 'previewPayment()' }))}
          ${field('Method', `<select id="pay-method" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">${PAY_METHODS.map(m => `<option>${m}</option>`).join('')}</select>`)}
          ${field('Payment Period', input('pay-period', { type: s.payment_period === 'Weekly' ? 'week' : s.payment_period === 'Daily' ? 'date' : 'month', value: s.due.period, oninput: `refreshPaymentDue(${s.db_id})` }))}
          ${field('Paid On', input('pay-date', { type: 'date', value: @json(now()->toDateString()) }))}
          <div class="col-span-2">${field('Notes', input('pay-notes', { placeholder: 'Optional' }))}</div>
        </div>
        <p class="text-xs text-slate-500 mt-4">Tailor wages are kept entirely separate from customer payments and never appear in sales or revenue.</p>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
        <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600 transition-colors shadow-sm" onclick="savePayment(${s.db_id}, this)"><i class="fa-solid fa-check text-xs mr-1"></i> Record Payment</button>
      </div>`,

    'staff-work': (s) => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div>
          <div class="text-lg font-bold text-slate-900 tracking-tight">Record Stitching</div>
          <div class="text-xs text-slate-500 mt-1">${Atelier.escapeHtml(s.name)} · rate ${Atelier.money(s.per_suit_rate)} per piece</div>
        </div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-6">
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">${field('Garment', input('wk-garment', { placeholder: 'e.g. Shalwar Kameez' }))}</div>
          ${field('Pieces *', input('wk-qty', { type: 'number', min: 0, step: '0.5', value: 1, oninput: 'previewWorkTotal()' }))}
          ${field('Rate per piece', input('wk-rate', { type: 'number', min: 0, step: '0.01', value: s.per_suit_rate, oninput: 'previewWorkTotal()' }))}
          ${field('Completed On', input('wk-date', { type: 'date', value: @json(now()->toDateString()) }))}
          ${field('Notes', input('wk-notes', { placeholder: 'Optional' }))}
        </div>
        <div class="mt-4 bg-slate-50 border border-slate-100 rounded-lg p-3 flex justify-between items-center">
          <span class="text-sm text-slate-500">Stitching amount</span>
          <span class="text-lg font-bold text-slate-900" id="wk-total">${Atelier.money(s.per_suit_rate)}</span>
        </div>
        <p class="text-xs text-slate-500 mt-3">An assigned tailor is credited automatically when an order is marked Ready. Delivery does not count it again. Use this for alterations, repairs and counter jobs.</p>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
        <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="saveWork(${s.db_id}, this)"><i class="fa-solid fa-check text-xs mr-1"></i> Record Work</button>
      </div>`,

    'staff-profile': (s) => {
      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div class="flex items-center gap-3">
            <div class="avatar bg-slate-900">${s ? s.initials : '?'}</div>
            <div>
              <div class="text-lg font-bold text-slate-900 tracking-tight">${s ? Atelier.escapeHtml(s.name) : 'Tailor'}</div>
              <div class="text-xs text-slate-500 mt-0.5">${s ? Atelier.escapeHtml(s.role) : ''} · ${s ? Atelier.escapeHtml(s.phone || 'no phone') : ''}</div>
            </div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 flex gap-1 flex-wrap" id="profile-tabs">
          <span class="sf-tab on" data-tab="overview" onclick="setProfileTab('overview')">Overview</span>
          <span class="sf-tab" data-tab="orders" onclick="setProfileTab('orders')">Assigned Orders</span>
          <span class="sf-tab" data-tab="work" onclick="setProfileTab('work')">Stitching History</span>
          <span class="sf-tab" data-tab="salary" onclick="setProfileTab('salary')">Salary History</span>
        </div>
        <div class="p-6 overflow-y-auto" style="max-height:60vh" id="profile-body">
          <div class="text-sm text-slate-400 text-center py-8"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading…</div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-between gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Close</button>
          <div class="flex gap-2">
            <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal(); openStaffForm(${s ? s.db_id : 'null'})"><i class="fa-solid fa-pen text-xs mr-1"></i> Edit</button>
            <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600 transition-colors shadow-sm" onclick="closeModal(); openPaymentForm(${s ? s.db_id : 'null'})"><i class="fa-solid fa-money-bill-wave text-xs mr-1"></i> Pay Salary</button>
          </div>
        </div>`;
    },
  });

  /** Explains what the chosen salary type actually means for this person. */
  window.renderSalaryHint = function() {
    const type = document.getElementById('sf-salary-type')?.value.split('|')[0];
    const el = document.getElementById('salary-hint');
    if (!el) return;

    el.textContent = {
      'Monthly':  'Paid a fixed salary each month. Stitching is still tracked, but does not add to what is owed.',
      'Per Suit': 'Paid only for what they stitch — pieces × their own rate. The monthly salary field is ignored.',
      'Both':     'Paid a monthly retainer plus their per-suit rate on everything they stitch.',
    }[type] || '';
  }

  Atelier.onPageReady(() => {
    updateStaffStats();
    renderStaff();
    Atelier.poll(refreshStaff, 10000);
  });
</script>
@endpush
