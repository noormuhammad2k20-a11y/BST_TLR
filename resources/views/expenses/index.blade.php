@extends('layouts.app')
@section('spaPage', 'expenses')
@section('title', 'Expenses & Finance')

@push('styles')
<style>
  .badge::before { display: none; } /* Removed dots for cleaner look */
  .badge-blue { background: #F0F9FF; color: #0EA5E9; }
  .badge-purple { background: #F5F3FF; color: #8B5CF6; }
  .badge-amber { background: #FFFBEB; color: #F59E0B; }
  .badge-sky { background: #E0F2FE; color: #0284C7; }
  .badge-rose { background: #FFF1F2; color: #E11D48; }
</style>
@endpush

@section('content')
<!-- Header -->
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Expenses & Finance</h1>
    <p class="text-sm text-slate-500 mt-0.5">Track income, expenses, and profitability</p>
  </div>
  <div class="flex gap-2">
    <button class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 hover:border-slate-300 flex items-center gap-2 transition-colors shadow-sm" onclick="exportReport()"><i class="fa-solid fa-file-export text-[10px]"></i> Download Report</button>
    <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="openModal('add-expense')"><i class="fa-solid fa-plus text-[10px]"></i> Log Expense</button>
  </div>
</div>

<!-- Stats Row -->
<div class="page grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Income</span>
      <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-sack-dollar text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight">{{ \App\Services\Money::format($summary['income']) }}</h3>
    <p class="text-[11px] text-slate-400 font-medium mt-1">This month</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Expenses</span>
      <div class="w-7 h-7 rounded-md bg-red-50 text-red-600 flex items-center justify-center"><i class="fa-solid fa-money-bill-transfer text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-summary="expenses">{{ \App\Services\Money::format($summary['expenses']) }}</h3>
    <p class="text-[11px] text-red-500 font-medium mt-1" data-summary="expense_pct">{{ $summary['expense_pct'] }}% of income</p>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Net Profit</span>
      <div class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-chart-pie text-[11px]"></i></div>
    </div>
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-summary="profit">{{ \App\Services\Money::format($summary['profit']) }}</h3>
    <p class="text-[11px] {{ $summary['profit'] >= 0 ? 'text-emerald-600' : 'text-red-500' }} font-medium mt-1 flex items-center gap-1"><i class="fa-solid {{ $summary['profit'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[7px]"></i> <span data-summary="margin_pct">{{ $summary['margin_pct'] }}% margin</span></p>
  </div>
</div>

<!-- Chart Section -->
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm mb-6 overflow-hidden">
  <div class="p-5 border-b border-slate-200 flex justify-between items-center">
    <div>
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Cash Flow Analysis</h3>
      <p class="text-xs text-slate-500 mt-0.5">Last 6 months performance</p>
    </div>
    <div class="flex gap-1 bg-slate-100 p-1 rounded-lg">
      <button class="px-3 py-1 text-xs font-medium bg-white text-slate-900 rounded-md shadow-sm">6M</button>
      <button class="px-3 py-1 text-xs font-medium text-slate-500 hover:text-slate-900 rounded-md">1Y</button>
      <button class="px-3 py-1 text-xs font-medium text-slate-500 hover:text-slate-900 rounded-md">All</button>
    </div>
  </div>
  <div class="p-5" style="height:300px"><canvas id="cashflow-chart"></canvas></div>
</div>

<!-- Filter & Table Section -->
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
  
  <!-- Advanced Filters -->
  <div class="p-4 border-b border-slate-200 flex flex-col xl:flex-row gap-3 items-center">
    <div class="relative flex-1 w-full">
      <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
      <input type="text" id="searchInput" onkeyup="handleSearch()" placeholder="Search by description or category..." class="w-full h-10 pl-10 pr-4 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition">
    </div>
    <div class="flex flex-wrap gap-2 w-full xl:w-auto justify-end">
      <select id="methodFilter" onchange="handleFilter()" class="w-full sm:w-36 h-10 px-4 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-900 font-medium transition-all">
        <option value="All">All Methods</option>
        @foreach($methods as $method)
        <option value="{{ $method }}">{{ $method }}</option>
        @endforeach
      </select>
      <select id="dateFilter" onchange="handleFilter()" class="w-full sm:w-36 h-10 px-4 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-900 font-medium transition-all">
        <option value="All">All Dates</option>
        <option value="Today">Today</option>
        <option value="Last 7 days">Last 7 days</option>
        <option value="This Month">This Month</option>
      </select>
    </div>
  </div>

  <!-- Category Pills -->
  <div class="px-4 py-3 border-b border-slate-200 flex gap-1 flex-wrap bg-slate-50" id="categoryPills">
    <span class="px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer" onclick="setCategoryFilter('All', this)">All Expenses</span>
    @foreach($categories as $category)
    <span class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors" onclick="setCategoryFilter('{{ $category }}', this)">{{ $category }}</span>
    @endforeach
  </div>

  <!-- Table -->
  <div class="overflow-x-auto min-h-[300px]">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
        <tr>
          <th class="px-5 py-3 text-left font-bold">Date</th>
          <th class="px-5 py-3 text-left font-bold">Category</th>
          <th class="px-5 py-3 text-left font-bold">Description</th>
          <th class="px-5 py-3 text-left font-bold">Method</th>
          <th class="px-5 py-3 text-right font-bold">Amount</th>
          <th class="px-5 py-3 text-right font-bold">Actions</th>
        </tr>
      </thead>
      <tbody id="expenseTableBody" class="divide-y divide-slate-100">
        <!-- Rendered dynamically -->
      </tbody>
    </table>
  </div>
  
  <!-- Pagination -->
  <div id="pagination-footer" class="px-6 py-3 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center text-sm gap-3"></div>
</div>
@endsection

@push('scripts')
{{-- Chart.js is already loaded once in the layout. --}}
<script>
  /* ============= DATA STORE ============= */
  /* `var` throughout: the SPA router re-evaluates this script per navigation. */
  var expenses = @json($expenses);
  var EXPENSE_CATEGORIES = @json($categories);
  var EXPENSE_METHODS = @json($methods);
  var CASHFLOW = @json($cashflow);
  var SUMMARY = @json($summary);
  var cashflowChart = null;

  /* ============= STATE ============= */
  var currentPage = 1;
  /* Page size comes from Settings → Theme & Display, so one number governs
     every table in the app. */
  var itemsPerPage = Atelier.rowsPerPage();
  var currentCategory = 'All';
  var currentMethod = 'All';
  var currentSearch = '';

  /* ============= EXPORT LOGIC ============= */
  window.exportReport = function() {
    const filtered = getFilteredExpenses();
    if (!filtered.length) { toast('There is nothing to export', 'info'); return; }

    const rows = [['Date', 'Category', 'Description', 'Vendor', 'Method', 'Reference', 'Amount']];
    filtered.forEach(e => rows.push([
      e.fullDate, e.category, e.description, e.vendor, e.method, e.reference, e.amount
    ]));

    const csv = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    Object.assign(document.createElement('a'), {
      href: url, download: `expenses-${new Date().toISOString().slice(0, 10)}.csv`
    }).click();
    URL.revokeObjectURL(url);

    toast(`Exported ${filtered.length} expense records`, 'success');
  }

  /* Repaints the three summary cards and the cash-flow chart in place. */
  function applyExpenseStats(stats) {
    if (!stats) return;

    const income = SUMMARY.income;
    const outflow = stats.this_month;

    const set = (sel, text) => { const el = document.querySelector(sel); if (el) el.textContent = text; };

    set('[data-summary="expenses"]', Atelier.money(outflow));
    set('[data-summary="profit"]', Atelier.money(income - outflow));
    set('[data-summary="expense_pct"]', (income > 0 ? Math.round((outflow / income) * 1000) / 10 : 0) + '% of income');
    set('[data-summary="margin_pct"]', (income > 0 ? Math.round(((income - outflow) / income) * 1000) / 10 : 0) + '% margin');

    if (cashflowChart) {
      const last = cashflowChart.data.datasets[1].data.length - 1;
      cashflowChart.data.datasets[1].data[last] = outflow;
      cashflowChart.update('none');
    }
  }

  /* ============= SAVE EXPENSE ============= */
  window.saveExpense = async function(dbId, btn) {
    const payload = {
      description:    document.getElementById('exp-description').value.trim(),
      amount:         parseFloat(document.getElementById('exp-amount').value) || 0,
      category:       document.getElementById('exp-category').value,
      payment_method: document.getElementById('exp-method').value,
      vendor:         document.getElementById('exp-vendor')?.value.trim() || null,
      reference:      document.getElementById('exp-reference')?.value.trim() || null,
      date:           document.getElementById('exp-date').value,
      notes:          document.getElementById('exp-notes')?.value.trim() || null,
    };

    if (!payload.description) { toast('Please enter a description', 'error'); return; }
    if (payload.amount <= 0)  { toast('Amount must be greater than zero', 'error'); return; }
    if (!payload.date)        { toast('Please choose a date', 'error'); return; }

    Atelier.setBusy(btn, true);
    try {
      const res = dbId
        ? await Atelier.api.put(`/expenses/${dbId}`, payload)
        : await Atelier.api.post(@json(route('expenses.store')), payload);

      if (dbId) {
        const i = expenses.findIndex(e => e.db_id === dbId);
        if (i > -1) expenses[i] = res.expense;
      } else {
        expenses.unshift(res.expense);
      }

      closeModal();
      renderExpenses();
      applyExpenseStats(res.stats);
      toast(res.message, 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not save the expense');
    } finally {
      Atelier.setBusy(btn, false);
    }
  };

  /* ============= DELETE LOGIC ============= */
  var deleteContext = { id: '', dbId: null };

  window.confirmDelete = function(id, dbId) {
    deleteContext = { id, dbId };
    const exp = expenses.find(e => e.db_id === dbId);

    Atelier.confirm({
      variant: 'delete',
      title: 'Delete this expense?',
      message: exp
        ? `"${exp.description}" (${Atelier.money(exp.amount)}) will be permanently removed. This action cannot be undone.`
        : 'This expense record will be permanently removed. This action cannot be undone.',
      confirmLabel: 'Confirm Delete',
      onConfirm: executeDelete,
    });
  }

  window.executeDelete = async function() {
    const res = await Atelier.api.delete(`/expenses/${deleteContext.dbId}`);
    expenses = expenses.filter(e => e.db_id !== deleteContext.dbId);
    renderExpenses();
    applyExpenseStats(res.stats);
    toast(res.message, 'success');
  }

  /* ============= FILTER LOGIC ============= */
  window.setCategoryFilter = function(cat, el) {
    currentCategory = cat;
    currentPage = 1;
    // Update active pill UI
    if (el) {
      document.querySelectorAll('#categoryPills span').forEach(s => {
        s.className = 'px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-200 transition-colors';
      });
      el.className = 'px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer';
    }
    renderExpenses();
  }

  var currentDateRange = 'All';

  window.handleFilter = function() {
    currentMethod = document.getElementById('methodFilter').value;
    currentDateRange = document.getElementById('dateFilter').value;
    currentPage = 1;
    renderExpenses();
  }

  function matchesDateRange(exp) {
    if (currentDateRange === 'All') return true;

    const d = new Date(exp.fullDate + 'T00:00:00');
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    if (currentDateRange === 'Today') return d.getTime() === today.getTime();
    if (currentDateRange === 'Last 7 days') {
      return d >= new Date(today.getTime() - 6 * 86400000) && d <= today;
    }
    if (currentDateRange === 'This Month') {
      return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth();
    }
    return true;
  }

  window.handleSearch = function() {
    currentSearch = document.getElementById('searchInput').value.toLowerCase();
    currentPage = 1;
    renderExpenses();
  }

  function getFilteredExpenses() {
    return expenses.filter(exp => {
      const matchCategory = currentCategory === 'All' || exp.category === currentCategory;
      const matchMethod = currentMethod === 'All' || exp.method === currentMethod;
      const matchSearch = exp.description.toLowerCase().includes(currentSearch)
        || exp.category.toLowerCase().includes(currentSearch)
        || (exp.vendor || '').toLowerCase().includes(currentSearch);
      return matchCategory && matchMethod && matchSearch && matchesDateRange(exp);
    });
  }

  /* ============= RENDER EXPENSES ============= */
  function renderExpenses() {
    const list = document.getElementById('expenseTableBody');
    const footer = document.getElementById('pagination-footer');
    if (!list) return;
    
    const filtered = getFilteredExpenses();
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    
    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const paginatedItems = filtered.slice(start, end);

    const categoryColors = {
      'Materials': 'badge-blue', 'Salaries': 'badge-purple', 'Rent': 'badge-amber',
      'Utilities': 'badge-sky', 'Maintenance': 'badge-rose'
    };

    if (paginatedItems.length === 0) {
      list.innerHTML = Atelier.emptyRow(6, {
        icon: 'fa-coins',
        title: expenses.length === 0 ? 'No expenses logged yet' : 'No matching expenses',
        message: expenses.length === 0
          ? 'Log your first expense to start tracking profitability.'
          : 'Try clearing a filter or searching for something else.'
      });
      footer.innerHTML = '';
      return;
    }

    list.innerHTML = paginatedItems.map(exp => {
      const expDataStr = JSON.stringify(exp).replace(/"/g, '&quot;');
      return `
        <tr class="hover:bg-slate-50 transition-colors" id="row-${exp.id}">
          <td class="px-5 py-3 text-slate-600 whitespace-nowrap">${exp.date}</td>
          <td class="px-5 py-3"><span class="badge ${categoryColors[exp.category] || 'badge-blue'}">${exp.category}</span></td>
          <td class="px-5 py-3 text-slate-600 font-medium">${Atelier.escapeHtml(exp.description)}${exp.vendor ? `<div class="text-xs text-slate-400">${Atelier.escapeHtml(exp.vendor)}</div>` : ''}</td>
          <td class="px-5 py-3 text-slate-500">${exp.method}</td>
          <td class="px-5 py-3 font-semibold text-red-500 text-right whitespace-nowrap">-${Atelier.money(exp.amount)}</td>
          <td class="px-5 py-3 text-right whitespace-nowrap">
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center mr-1 transition-colors" title="Edit" onclick="openModal('add-expense', ${expDataStr})"><i class="fa-solid fa-pen text-xs"></i></button>
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-500 inline-flex items-center justify-center transition-colors" title="Delete" onclick="confirmDelete('${exp.id}', ${exp.db_id})"><i class="fa-solid fa-trash text-xs"></i></button>
          </td>
        </tr>
      `;
    }).join('');

    // Pagination HTML
    const currentStart = start + 1;
    const currentEnd = Math.min(end, filtered.length);
    
    let controlsHTML = `
      <div class="text-xs text-slate-500">Showing ${currentStart} to ${currentEnd} of ${filtered.length} results</div>
      <div class="flex items-center gap-1">
        <button onclick="changeExpensePage(-1)" ${currentPage === 1 ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          <i class="fa-solid fa-chevron-left text-xs"></i>
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      controlsHTML += `
        <button onclick="goToExpensePage(${i})" class="w-8 h-8 flex items-center justify-center text-xs font-medium rounded-md transition-colors ${currentPage === i ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}">
          ${i}
        </button>
      `;
    }

    controlsHTML += `
        <button onclick="changeExpensePage(1)" ${currentPage === totalPages ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
          <i class="fa-solid fa-chevron-right text-xs"></i>
        </button>
      </div>
    `;

    footer.innerHTML = controlsHTML;
  }

  window.changeExpensePage = function(dir) {
    const filtered = getFilteredExpenses();
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    const newPage = currentPage + dir;
    if (newPage >= 1 && newPage <= totalPages) {
      currentPage = newPage;
      renderExpenses();
    }
  }

  window.goToExpensePage = function(page) {
    currentPage = page;
    renderExpenses();
  }

  /* ========== CHARTS ========== */
  function initCashflowChart() {
    const ctx = document.getElementById('cashflow-chart');
    if (!ctx) return;
    cashflowChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: CASHFLOW.labels,
        datasets: [
          { label: 'Income', data: CASHFLOW.income, backgroundColor: '#10B981', borderRadius: 6, barThickness: 18 },
          { label: 'Expenses', data: CASHFLOW.expenses, backgroundColor: '#EF4444', borderRadius: 6, barThickness: 18 }
        ]
      },
      options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { 
          legend: { position: 'top', align: 'end', labels: { color: '#94A3B8', usePointStyle: true, pointStyle: 'circle' } } 
        }, 
        scales: { 
          x: { grid: { display: false }, ticks: { color: '#94A3B8' } }, 
          y: { grid: { color: '#F1F5F9' }, ticks: { color: '#94A3B8', callback: v => Atelier.currency + (v / 1000) + 'K' } }
        },
        plugins: {
          legend: { position: 'top', align: 'end', labels: { color: '#94A3B8', usePointStyle: true, pointStyle: 'circle' } },
          tooltip: { callbacks: { label: c => `${c.dataset.label}: ${Atelier.money(c.parsed.y)}` } }
        }
      }
    });
  }

  /* ============= MODAL OVERRIDES ============= */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'add-expense': (data) => {
      const isEdit = data && data.id;
      const title = isEdit ? 'Edit Expense' : 'Log New Expense';
      
      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">${title}</div>
            <div class="text-xs text-slate-500 mt-1">Enter the details of the transaction</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto">
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Date *</label>
              <input type="date" id="exp-date" max="${new Date().toISOString().slice(0,10)}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" value="${isEdit ? data.fullDate : new Date().toISOString().slice(0,10)}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category *</label>
              <select id="exp-category" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                ${EXPENSE_CATEGORIES.map(c => `<option ${isEdit && data.category === c ? 'selected' : ''}>${c}</option>`).join('')}
              </select>
            </div>
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description *</label>
              <input type="text" id="exp-description" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Italian Wool fabric roll" value="${isEdit ? Atelier.escapeHtml(data.description) : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Amount (${Atelier.currency}) *</label>
              <input type="number" id="exp-amount" step="0.01" min="0" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="0" value="${isEdit ? data.amount : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Payment Method *</label>
              <select id="exp-method" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                ${EXPENSE_METHODS.map(m => `<option ${isEdit && data.method === m ? 'selected' : ''}>${m}</option>`).join('')}
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Vendor</label>
              <input type="text" id="exp-vendor" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Optional" value="${isEdit ? Atelier.escapeHtml(data.vendor || '') : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Reference</label>
              <input type="text" id="exp-reference" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Bill / invoice no." value="${isEdit ? Atelier.escapeHtml(data.reference || '') : ''}">
            </div>
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Notes</label>
              <textarea id="exp-notes" rows="2" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Optional notes...">${isEdit ? Atelier.escapeHtml(data.notes || '') : ''}</textarea>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-2" onclick="saveExpense(${isEdit ? data.db_id : 'null'}, this)"><i class="fa-solid fa-check text-xs"></i> Save Expense</button>
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
              <p class="text-sm text-slate-700">Are you sure you want to delete this expense record? This action cannot be undone.</p>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-600 flex items-center gap-2 transition-colors shadow-sm" onclick="executeDelete()"><i class="fa-solid fa-check text-xs"></i> Delete Permanently</button>
        </div>`;
    }
  });

  Atelier.onPageReady(() => {
    initCashflowChart();
    renderExpenses();

    if (new URLSearchParams(window.location.search).get('action') === 'create') {
      openModal('add-expense');
    }
  });
</script>
@endpush
