@extends('cloth-store.layouts.app')
@section('title', 'Expenses Management')
@section('spaPage', 'cloth-store-expenses')

@section('content')
@php
  $topCategory = $kpis['highest_category'];
  $topSub = $topCategory
      ? 'Rs ' . number_format($topCategory->total) . ' this month'
      : 'No spend recorded yet';
@endphp

<x-cloth-store.page-header
  title="Expenses"
  subtitle="Track and manage operational shop expenses">
  <x-slot:actions>
    <button class="btn-cs-ghost" onclick="window.print()">
      <i class="fa-solid fa-print text-[10px]"></i> Print
    </button>
    <button class="btn-cs-primary" onclick="openDrawer('expense-form')">
      <i class="fa-solid fa-plus text-[10px]"></i> Record Expense
    </button>
  </x-slot:actions>
</x-cloth-store.page-header>

<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
  <x-cloth-store.stat-card
    label="Today" icon="fa-calendar-day" tone="indigo"
    :value="'Rs ' . number_format($kpis['today'])" sub="Spent today" />

  <x-cloth-store.stat-card
    label="This Month" icon="fa-calendar" tone="sky"
    :value="'Rs ' . number_format($kpis['this_month'])" :sub="now()->format('F Y')" />

  <x-cloth-store.stat-card
    label="Last Month" icon="fa-calendar-minus" tone="slate"
    :value="'Rs ' . number_format($kpis['last_month'])" :sub="now()->subMonth()->format('F Y')" />

  <x-cloth-store.stat-card
    label="Year to Date" icon="fa-chart-line" tone="violet"
    :value="'Rs ' . number_format($kpis['yearly'])" :sub="now()->format('Y') . ' total'" />

  <x-cloth-store.stat-card
    label="Highest Category" icon="fa-tag" tone="amber"
    :value="$topCategory ? $topCategory->category : 'N/A'" :sub="$topSub" />
</div>

<!-- Charts -->
<div class="page grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-slate-900 mb-4">Expenses by Category</h3>
    <div class="h-48"><canvas id="chartCat"></canvas></div>
  </div>
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-slate-900 mb-4">Daily Trend <span class="text-slate-400 font-normal">· 30 days</span></h3>
    <div class="h-48"><canvas id="chartDaily"></canvas></div>
  </div>
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-slate-900 mb-4">Monthly Trend <span class="text-slate-400 font-normal">· 12 months</span></h3>
    <div class="h-48"><canvas id="chartMonthly"></canvas></div>
  </div>
</div>

<x-cloth-store.panel class="mb-6">
  <x-slot:toolbar>
    <form data-filter-form id="filterForm" method="GET" action="{{ route('cloth-store.expenses.index') }}" class="flex flex-wrap gap-3 items-center w-full">
      <div class="relative flex-1 min-w-[220px] max-w-xs">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
        <input type="search" name="search" id="searchInput" value="{{ request('search') }}" autocomplete="off"
               placeholder="Search expenses..." class="input-cs w-full pl-9">
      </div>
      <select name="category" class="input-cs w-44">
        <option value="all">All Categories</option>
        @foreach(['Shop Rent', 'Electricity', 'Water', 'Gas', 'Internet', 'Salaries', 'Transport', 'Loading/Unloading', 'Packaging', 'Shop Maintenance', 'Furniture', 'Marketing', 'Cleaning', 'Tea/Refreshments', 'Repairs', 'Miscellaneous'] as $cat)
          <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
        @endforeach
      </select>
      <div class="flex items-center gap-1.5">
        <input type="date" name="start_date" value="{{ request('start_date') }}" class="input-cs w-36">
        <span class="text-slate-400 text-xs">to</span>
        <input type="date" name="end_date" value="{{ request('end_date') }}" class="input-cs w-36">
      </div>
      @if(request()->hasAny(['search', 'start_date', 'end_date']) || (request('category') && request('category') !== 'all'))
        <a href="{{ route('cloth-store.expenses.index') }}" class="btn-cs-ghost">
          <i class="fa-solid fa-xmark text-[10px]"></i> Clear
        </a>
      @endif
    </form>
  </x-slot:toolbar>

    <table class="table-cs" id="expensesTable">
      <thead>
        <tr>
          <th>Date</th>
          <th>Category &amp; Details</th>
          <th class="text-right">Amount</th>
          <th>Payment Info</th>
          <th class="text-center">Status</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($expenses as $ex)
        @php
          $statusBadge = match ($ex->status) {
              'Approved' => 'badge-delivered',
              'Pending'  => 'badge-pending',
              default    => 'badge-overdue',
          };
        @endphp
        <tr>
          <td class="whitespace-nowrap text-slate-600 cell-num">{{ $ex->expense_date->format('d M Y') }}</td>
          <td>
            <div class="cell-strong">{{ $ex->category }}</div>
            <div class="cell-muted line-clamp-1 max-w-[300px]" title="{{ $ex->description }}">{{ $ex->description }}</div>
          </td>
          <td class="text-right cell-strong cell-num">Rs {{ number_format((float) $ex->amount) }}</td>
          <td>
            <span class="badge badge-neutral">{{ $ex->payment_method }}</span>
            <div class="cell-muted mt-1">By {{ $ex->paid_by ?: '—' }} · Ref {{ $ex->reference ?: '—' }}</div>
          </td>
          <td class="text-center"><span class="badge {{ $statusBadge }}">{{ $ex->status }}</span></td>
          <td>
            <div class="flex items-center justify-end gap-1 row-actions">
              @if($ex->status === 'Pending')
                <button onclick="updateStatus({{ $ex->id }}, 'Approved')" class="btn-cs-icon" title="Approve"><i class="fa-solid fa-check text-xs"></i></button>
                <button onclick="updateStatus({{ $ex->id }}, 'Rejected')" class="btn-cs-icon danger" title="Reject"><i class="fa-solid fa-xmark text-xs"></i></button>
              @endif
              <button onclick="editExpense({{ $ex->id }})" class="btn-cs-icon" title="Edit"><i class="fa-solid fa-pen text-xs"></i></button>
              <button onclick="deleteExpense({{ $ex->id }})" class="btn-cs-icon danger" title="Delete"><i class="fa-solid fa-trash text-xs"></i></button>
            </div>
          </td>
        </tr>
        @empty
          <x-cloth-store.empty-state
            :colspan="6"
            icon="fa-file-invoice-dollar"
            title="No expenses found"
            message="Try widening the date range, or record your first expense." />
        @endforelse
      </tbody>
    </table>

  <x-slot:footer>
    <x-cloth-store.pagination :paginator="$expenses" noun="expense" />
  </x-slot:footer>
</x-cloth-store.panel>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  var chartsData = @json($charts);
  var expensesData = @json($expenses->items());

  window.drawers = window.drawers || {};
  var currentExpenseId = null;

  // Add/Edit Expense Drawer
  window.drawers['expense-form'] = () => `
    <div class="flex flex-col h-full bg-white shadow-2xl w-full sm:w-[450px]">
      <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
        <div>
          <h2 class="text-lg font-bold text-slate-900 tracking-tight" id="drawerTitle">Record Expense</h2>
          <p class="text-xs text-slate-500 mt-0.5">Enter operational shop costs</p>
        </div>
        <button onclick="closeDrawer()" class="w-8 h-8 rounded-full hover:bg-slate-200 flex items-center justify-center text-slate-500"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="flex-1 overflow-y-auto p-6">
        <form id="expenseForm" onsubmit="saveExpense(event)">
          <div class="space-y-4">
            
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Date *</label><input type="date" name="expense_date" required value="${new Date().toISOString().split('T')[0]}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500"></div>
              <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category *</label>
                <select name="category" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500">
                  <option value="Shop Rent">Shop Rent</option>
                  <option value="Electricity">Electricity</option>
                  <option value="Water">Water</option>
                  <option value="Gas">Gas</option>
                  <option value="Internet">Internet</option>
                  <option value="Salaries">Salaries</option>
                  <option value="Transport">Transport</option>
                  <option value="Loading/Unloading">Loading/Unloading</option>
                  <option value="Packaging">Packaging</option>
                  <option value="Shop Maintenance">Shop Maintenance</option>
                  <option value="Furniture">Furniture</option>
                  <option value="Marketing">Marketing</option>
                  <option value="Cleaning">Cleaning</option>
                  <option value="Tea/Refreshments">Tea/Refreshments</option>
                  <option value="Repairs">Repairs</option>
                  <option value="Miscellaneous">Miscellaneous</option>
                </select>
              </div>
            </div>

            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description *</label><input type="text" name="description" required placeholder="What was this for?" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500"></div>

            <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Amount (Rs) *</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-medium text-sm">Rs</span>
                <input type="number" name="amount" min="1" required class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-lg font-bold text-slate-900 focus:outline-none focus:border-indigo-500">
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Payment Method *</label>
                <select name="payment_method" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500">
                  <option value="Cash">Cash</option>
                  <option value="Bank">Bank</option>
                  <option value="Card">Card</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500">
                  <option value="Approved">Approved (Auto)</option>
                  <option value="Pending">Pending Review</option>
                  <option value="Rejected">Rejected</option>
                </select>
              </div>
            </div>

          </div>
        </form>
      </div>
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex justify-end gap-2">
        <button class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50" type="button" onclick="closeDrawer()">Cancel</button>
        <button form="expenseForm" type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-lg hover:bg-slate-800 shadow-sm flex items-center gap-2" id="saveBtn"><i class="fa-solid fa-check"></i> Save Expense</button>
      </div>
    </div>
  `;

  /* filterTable() removed — it hid rows in the current page only, so it could
     never find an expense outside the 50 rows on screen. Search is now a
     database query on the server (see ExpenseController@index). */

  function editExpense(id) {
    currentExpenseId = id;
    let ex = expensesData.find(e => e.id === id);
    if(!ex) return;
    
    openDrawer('expense-form');
    document.getElementById('drawerTitle').innerText = 'Edit Expense';
    
    let form = document.getElementById('expenseForm');
    form.expense_date.value = ex.expense_date.split('T')[0];
    form.category.value = ex.category;
    form.description.value = ex.description;
    form.amount.value = ex.amount;
    form.payment_method.value = ex.payment_method;
    form.status.value = ex.status;
  }

  // Guards against a double-submit creating two identical expenses when the
  // save button is clicked twice before the first response lands.
  var expenseSaving = false;

  async function saveExpense(e) {
    e.preventDefault();
    if (expenseSaving) return;

    const data = Object.fromEntries(new FormData(e.target));
    const url = currentExpenseId ? `/cloth-store/expenses/${currentExpenseId}` : `/cloth-store/expenses`;
    const method = currentExpenseId ? 'PUT' : 'POST';

    const btn = document.getElementById('saveBtn');
    expenseSaving = true;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    try {
      // Atelier.request drops the page cache on any non-GET, so the refresh
      // below can never be served a pre-write copy of this page.
      const res = await Atelier.request(url, { method, body: data });

      toast(res.message || 'Expense saved', 'success');
      closeDrawer();
      await Atelier.refreshPage();
    } catch (err) {
      Atelier.reportError(err);
    } finally {
      expenseSaving = false;
      if (document.body.contains(btn)) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Save Expense';
      }
    }
  }

  function deleteExpense(id) {
    Atelier.confirmAction({
      title: 'Delete this expense?',
      message: 'The expense will be removed permanently.',
      confirmLabel: 'Delete',
      onConfirm: async () => {
        await Atelier.request(`/cloth-store/expenses/${id}`, { method: 'DELETE' });
        await Atelier.refreshPage();
      },
      successMessage: 'Expense deleted',
    });
  }

  function updateStatus(id, status) {
    Atelier.confirmAction({
      title: `Mark expense as ${status}?`,
      message: 'This updates the expense status immediately.',
      confirmLabel: 'Update',
      danger: false,
      onConfirm: async () => {
        await Atelier.request(`/cloth-store/expenses/${id}/status`, {
          method: 'PUT',
          body: { status },
        });
        await Atelier.refreshPage();
      },
      successMessage: `Expense marked as ${status}`,
    });
  }

  Atelier.onPageReady(() => {
    // Colors for categories
    const catColors = ['#4F46E5', '#38BDF8', '#10B981', '#F59E0B', '#E11D48', '#8B5CF6', '#14B8A6', '#F43F5E', '#84CC16', '#06B6D4'];

    // 1. Expenses by Category Doughnut
    new Chart(document.getElementById('chartCat'), {
      type: 'doughnut',
      data: { labels: chartsData.cat_labels, datasets: [{ data: chartsData.cat_data, backgroundColor: catColors, borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: {size: 10} } } } }
    });

    // 2. Daily Trend Bar Chart
    new Chart(document.getElementById('chartDaily'), {
      type: 'bar',
      data: { labels: chartsData.daily_labels, datasets: [{ label: 'Expense (Rs)', data: chartsData.daily_data, backgroundColor: '#38BDF8', borderRadius: 4 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    // 3. Monthly Trend Line Chart
    new Chart(document.getElementById('chartMonthly'), {
      type: 'line',
      data: { labels: chartsData.monthly_labels, datasets: [{ label: 'Expense (Rs)', data: chartsData.monthly_data, borderColor: '#F59E0B', backgroundColor: 'rgba(245, 158, 11, 0.1)', fill: true, tension: 0.4 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
  });
</script>
@endpush
