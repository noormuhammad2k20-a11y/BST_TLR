@extends('layouts.app')
@section('spaPage', 'reports')
@section('title', 'Reports & Analytics')

@section('content')
<!-- Header -->
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Reports & Analytics</h1>
    <p class="text-sm text-slate-500 mt-0.5">Business intelligence and performance insights</p>
  </div>
  {{-- Only ever visible on paper: the screen already shows the range in the
       filter bar, which print hides. --}}
  <div class="print-only text-right">
    <div class="text-sm font-semibold text-slate-900">{{ \App\Services\Settings::str('store_name') ?: 'Atelier' }}</div>
    <div class="text-xs text-slate-500" id="print-range-label">{{ $report['label'] }}</div>
  </div>
</div>

<!-- Interactive Filter Bar -->
<div class="page no-print bg-white rounded-xl border border-slate-200 shadow-sm p-3 mb-6 flex items-center justify-between flex-wrap gap-3">
  <div class="flex bg-slate-100 p-1 rounded-lg" id="range-tabs">
    @foreach(['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $key => $label)
    <button data-range="{{ $key }}" onclick="setRange('{{ $key }}')" class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $range === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">{{ $label }}</button>
    @endforeach
    <button data-range="custom" onclick="toggleCustomRange()" class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors flex items-center gap-1 {{ $range === 'custom' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}"><i class="fa-solid fa-calendar text-[10px]"></i> Custom</button>
  </div>

  <div class="flex items-center gap-2 flex-wrap">
    <div id="custom-range-inputs" class="hidden items-center gap-2">
      <input type="date" id="range-from" value="{{ $report['start'] }}" class="h-8 px-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-slate-900">
      <span class="text-xs text-slate-400">to</span>
      <input type="date" id="range-to" value="{{ $report['end'] }}" class="h-8 px-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-slate-900">
      <button onclick="applyCustomRange()" class="h-8 px-3 bg-slate-900 text-white rounded-lg text-xs font-medium hover:bg-slate-800 transition-colors">Apply</button>
    </div>
    <div class="flex items-center gap-2 text-xs text-slate-500 font-medium px-3 py-1.5 bg-slate-50 rounded-lg border border-slate-200">
      <i class="fa-regular fa-calendar text-slate-400"></i>
      <span id="range-label">{{ $report['label'] }}</span>
    </div>

    {{-- Export controls live here, inside <main>, rather than in the page
         header: the SPA router only swaps <main>, so anything placed in the
         header would go stale the moment you navigated between pages. --}}
    <div class="flex items-center gap-2">
      <button type="button" onclick="handleExport('PDF')"
              class="h-8 px-3 bg-slate-900 text-white rounded-lg text-xs font-medium hover:bg-slate-800 transition-colors flex items-center gap-2 shadow-sm">
        <i class="fa-solid fa-file-pdf text-[11px]"></i> Download PDF
      </button>
      <button type="button" onclick="handleExport('CSV')"
              class="h-8 px-3 bg-white border border-slate-200 text-slate-600 rounded-lg text-xs font-medium hover:bg-slate-50 transition-colors flex items-center gap-2"
              title="Download the same report as a CSV file, which Excel opens natively">
        <i class="fa-solid fa-file-csv text-[11px]"></i> CSV
      </button>
      <button type="button" onclick="handleExport('Print')"
              class="h-8 w-8 bg-white border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50 transition-colors flex items-center justify-center"
              title="Print this report">
        <i class="fa-solid fa-print text-[11px]"></i>
      </button>
    </div>
  </div>
</div>

<!-- KPI Cards Row -->
<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Revenue</span>
      <div class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-indian-rupee-sign text-[11px]"></i></div>
    </div>
    @php $k = $report['kpis']['revenue']; @endphp
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-kpi="revenue">{{ \App\Services\Money::format($k['value']) }}</h3>
    <div class="flex items-center justify-between mt-2">
      <p class="text-[11px] {{ $k['delta']['direction'] === 'down' ? 'text-red-500' : 'text-emerald-600' }} font-medium flex items-center gap-1" data-kpi-delta="revenue"><i class="fa-solid {{ $k['delta']['direction'] === 'down' ? 'fa-arrow-down' : 'fa-arrow-up' }} text-[7px]"></i> {{ $k['delta']['value'] }}%</p>
      <div class="w-20 h-1 bg-slate-100 rounded-full"><div class="h-full bg-indigo-600 rounded-full" data-kpi-bar="revenue" style="width:{{ $k['bar'] }}%"></div></div>
    </div>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Orders Completed</span>
      <div class="w-7 h-7 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center"><i class="fa-solid fa-check-double text-[11px]"></i></div>
    </div>
    @php $k = $report['kpis']['completed']; @endphp
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-kpi="completed">{{ number_format($k['value']) }}</h3>
    <div class="flex items-center justify-between mt-2">
      <p class="text-[11px] {{ $k['delta']['direction'] === 'down' ? 'text-red-500' : 'text-emerald-600' }} font-medium flex items-center gap-1" data-kpi-delta="completed"><i class="fa-solid {{ $k['delta']['direction'] === 'down' ? 'fa-arrow-down' : 'fa-arrow-up' }} text-[7px]"></i> {{ $k['delta']['value'] }}%</p>
      <div class="w-20 h-1 bg-slate-100 rounded-full"><div class="h-full bg-sky-500 rounded-full" data-kpi-bar="completed" style="width:{{ $k['bar'] }}%"></div></div>
    </div>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Avg. Order Value</span>
      <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-chart-line text-[11px]"></i></div>
    </div>
    @php $k = $report['kpis']['avg_order']; @endphp
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-kpi="avg_order">{{ \App\Services\Money::format($k['value']) }}</h3>
    <div class="flex items-center justify-between mt-2">
      <p class="text-[11px] {{ $k['delta']['direction'] === 'down' ? 'text-red-500' : 'text-emerald-600' }} font-medium flex items-center gap-1" data-kpi-delta="avg_order"><i class="fa-solid {{ $k['delta']['direction'] === 'down' ? 'fa-arrow-down' : 'fa-arrow-up' }} text-[7px]"></i> {{ $k['delta']['value'] }}%</p>
      <div class="w-20 h-1 bg-slate-100 rounded-full"><div class="h-full bg-emerald-500 rounded-full" data-kpi-bar="avg_order" style="width:{{ $k['bar'] }}%"></div></div>
    </div>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Collection Rate</span>
      <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fa-solid fa-percent text-[11px]"></i></div>
    </div>
    @php $k = $report['kpis']['collection_rate']; @endphp
    <h3 class="text-2xl font-bold text-slate-900 tracking-tight" data-kpi="collection_rate">{{ $k['value'] }}%</h3>
    <div class="flex items-center justify-between mt-2">
      <p class="text-[11px] {{ $k['delta']['direction'] === 'down' ? 'text-red-500' : 'text-emerald-600' }} font-medium flex items-center gap-1" data-kpi-delta="collection_rate"><i class="fa-solid {{ $k['delta']['direction'] === 'down' ? 'fa-arrow-down' : 'fa-arrow-up' }} text-[7px]"></i> {{ $k['delta']['value'] }}%</p>
      <div class="w-20 h-1 bg-slate-100 rounded-full"><div class="h-full bg-amber-500 rounded-full" data-kpi-bar="collection_rate" style="width:{{ $k['bar'] }}%"></div></div>
    </div>
  </div>
</div>

<!-- Target & Insights -->
<div class="page grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
  <!-- Revenue target -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center">
      <div>
        <h3 class="text-base font-semibold text-slate-900 tracking-tight">Revenue Target</h3>
        <p class="text-xs text-slate-500 mt-0.5" id="target-scope">Scaled to the selected period</p>
      </div>
      @if($canSetTarget)
      <button type="button" onclick="toggleTargetEditor()" class="no-print text-xs font-semibold text-slate-900 hover:underline" id="target-edit-btn">Set</button>
      @endif
    </div>
    <div class="p-5" id="target-body"></div>
    @if($canSetTarget)
    <div class="px-5 pb-5 hidden no-print" id="target-editor">
      <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Monthly revenue target</label>
      <div class="flex items-center gap-2">
        <input type="number" min="0" step="100" id="target-input"
               class="h-9 flex-1 px-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
               placeholder="0">
        <button type="button" onclick="saveTarget()" class="h-9 px-3 bg-slate-900 text-white rounded-lg text-xs font-medium hover:bg-slate-800 transition-colors">Save</button>
      </div>
      <p class="text-[11px] text-slate-400 mt-1.5">The period target is this figure divided across the month, so a single day is judged against a single day's share.</p>
    </div>
    @endif
  </div>

  <!-- What the numbers say -->
  <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center">
      <div>
        <h3 class="text-base font-semibold text-slate-900 tracking-tight">What the numbers say</h3>
        <p class="text-xs text-slate-500 mt-0.5">Written from this period's figures — no guesswork</p>
      </div>
      <i class="fa-solid fa-lightbulb text-amber-400"></i>
    </div>
    <div class="p-5" id="insights-list"></div>
  </div>
</div>

<!-- This period vs the previous one -->
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
  <div class="p-5 border-b border-slate-200 flex justify-between items-center">
    <div>
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Period Comparison</h3>
      <p class="text-xs text-slate-500 mt-0.5" id="comparison-note">Against the previous window of the same length</p>
    </div>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
        <tr>
          <th class="px-5 py-3 text-left">Metric</th>
          <th class="px-5 py-3 text-right">This period</th>
          <th class="px-5 py-3 text-right">Previous</th>
          <th class="px-5 py-3 text-right">Change</th>
          <th class="px-5 py-3 text-right">%</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100" id="comparison-body"></tbody>
    </table>
  </div>
</div>

<!-- Receivables & retention -->
<div class="page grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center">
      <div>
        <h3 class="text-base font-semibold text-slate-900 tracking-tight">Outstanding Dues</h3>
        <p class="text-xs text-slate-500 mt-0.5">Everything still unpaid today, by age</p>
      </div>
      <div class="text-right">
        <div class="text-lg font-bold text-slate-900" id="dues-total">—</div>
        <div class="text-[11px] text-slate-500" id="dues-count"></div>
      </div>
    </div>
    <div class="p-5 space-y-3" id="ageing-list"></div>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Customer Retention</h3>
      <p class="text-xs text-slate-500 mt-0.5">Who ordered in this period, and whether they had before</p>
    </div>
    <div class="p-5" id="retention-body"></div>
  </div>
</div>

<!-- Workshop performance & rhythm -->
<div class="page grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Delivery Performance</h3>
      <p class="text-xs text-slate-500 mt-0.5">Turnaround, punctuality and what is still open</p>
    </div>
    <div class="p-5" id="delivery-body"></div>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Busiest Days</h3>
      <p class="text-xs text-slate-500 mt-0.5">Orders taken by day of the week</p>
    </div>
    <div class="p-5 space-y-2.5" id="weekday-body"></div>
  </div>
</div>

<!-- Charts Grid 1 -->
<div class="page grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
  <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center">
      <div>
        <h3 class="text-base font-semibold text-slate-900 tracking-tight">Revenue Trend</h3>
        <p class="text-xs text-slate-500 mt-0.5">Daily revenue for this month</p>
      </div>
      <span class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md">+12.4% Growth</span>
    </div>
    <div class="p-5" style="height:320px"><canvas id="revenue-chart"></canvas></div>
  </div>
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Sales by Category</h3>
      <p class="text-xs text-slate-500 mt-0.5">Garment distribution</p>
    </div>
    <div class="p-5 flex items-center justify-center" style="height:320px"><canvas id="category-chart"></canvas></div>
  </div>
</div>

<!-- Charts Grid 2 -->
<div class="page grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Order Status Breakdown</h3>
      <p class="text-xs text-slate-500 mt-0.5">Current pipeline status</p>
    </div>
    <div class="p-5 flex items-center justify-center" style="height:320px"><canvas id="status-chart"></canvas></div>
  </div>
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Customer Growth</h3>
      <p class="text-xs text-slate-500 mt-0.5">New vs Returning customers</p>
    </div>
    <div class="p-5" style="height:320px"><canvas id="growth-chart"></canvas></div>
  </div>
</div>

<!-- Top Performers Tables -->
<div class="page grid grid-cols-1 lg:grid-cols-2 gap-5">
  <!-- Top Customers -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Top Customers</h3>
      <a href="{{ route('customers.index') }}" class="text-xs font-semibold text-slate-900 hover:underline">View All</a>
    </div>
    <div class="p-5 space-y-4" id="top-customers">
      @php $gradients = ['from-indigo-500 to-purple-600', 'from-emerald-500 to-teal-600', 'from-rose-500 to-pink-600', 'from-sky-500 to-blue-600']; @endphp
      @forelse($report['top_customers'] as $i => $c)
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full bg-gradient-to-br {{ $gradients[$i % count($gradients)] }} text-white flex items-center justify-center text-xs font-bold">{{ $c['initials'] }}</div>
          <div><div class="text-sm font-semibold text-slate-900">{{ $c['name'] }}</div><div class="text-xs text-slate-500">{{ $c['orders'] }} Orders</div></div>
        </div>
        <div class="text-sm font-bold text-slate-900">{{ \App\Services\Money::format($c['spent']) }}</div>
      </div>
      @empty
      <div class="text-center text-sm text-slate-400 py-8">No customer activity in this period</div>
      @endforelse
    </div>
  </div>

  <!-- Tailor Performance -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Tailor Performance</h3>
      <a href="{{ route('orders.index') }}" class="text-xs font-semibold text-slate-900 hover:underline">View All</a>
    </div>
    <div class="p-5 space-y-4" id="tailor-performance">
      @php
        $palette = [
          ['bg-indigo-50 text-indigo-600', 'bg-indigo-600'],
          ['bg-sky-50 text-sky-600', 'bg-sky-500'],
          ['bg-emerald-50 text-emerald-600', 'bg-emerald-500'],
          ['bg-amber-50 text-amber-600', 'bg-amber-500'],
        ];
      @endphp
      @forelse($report['tailors'] as $i => $t)
      @php [$iconClass, $barClass] = $palette[$i % count($palette)]; @endphp
      <div>
        <div class="flex items-center justify-between mb-1">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg {{ $iconClass }} flex items-center justify-center text-xs font-bold"><i class="fa-solid fa-scissors"></i></div>
            <div class="text-sm font-semibold text-slate-900">{{ $t['name'] }}</div>
          </div>
          <div class="text-xs font-semibold text-slate-700">{{ $t['orders'] }} Orders <span class="{{ $t['rate'] >= 90 ? 'text-emerald-600' : ($t['rate'] >= 75 ? 'text-amber-600' : 'text-red-500') }} ml-2">{{ $t['rate'] }}%</span></div>
        </div>
        <div class="w-full h-1.5 bg-slate-100 rounded-full"><div class="h-full {{ $barClass }} rounded-full" style="width:{{ $t['rate'] }}%"></div></div>
      </div>
      @empty
      <div class="text-center text-sm text-slate-400 py-8">No orders assigned to tailors in this period</div>
      @endforelse
    </div>
  </div>
</div>
<!-- Top garments -->
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mt-6">
  <div class="p-5 border-b border-slate-200">
    <h3 class="text-base font-semibold text-slate-900 tracking-tight">Garments &amp; Services</h3>
    <p class="text-xs text-slate-500 mt-0.5">What was ordered in this period, by value</p>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
        <tr>
          <th class="px-5 py-3 text-left">Garment / service</th>
          <th class="px-5 py-3 text-right">Orders</th>
          <th class="px-5 py-3 text-right">Value</th>
          <th class="px-5 py-3 text-right">Average</th>
          <th class="px-5 py-3 text-right w-40">Share</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100" id="garments-body"></tbody>
    </table>
  </div>
</div>

<!-- Row-level detail -->
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mt-6">
  <div class="p-5 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Detailed Records</h3>
      <p class="text-xs text-slate-500 mt-0.5" id="detail-note">Every row behind the figures above</p>
    </div>
    <div class="flex items-center gap-2 no-print">
      <div class="relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400"></i>
        <input type="search" id="detail-search" placeholder="Search records…"
               class="h-8 w-52 pl-8 pr-3 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-slate-900">
      </div>
      <select id="detail-per" class="h-8 px-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-slate-900">
        <option value="10">10 rows</option>
        <option value="15" selected>15 rows</option>
        <option value="25">25 rows</option>
        <option value="50">50 rows</option>
      </select>
    </div>
  </div>

  <div class="px-5 pt-3 no-print">
    <div class="flex bg-slate-100 p-1 rounded-lg w-max" id="detail-tabs">
      @foreach(['orders' => 'Orders', 'payments' => 'Payments', 'expenses' => 'Expenses', 'dues' => 'Outstanding'] as $key => $label)
      <button data-detail="{{ $key }}" onclick="setDetailType('{{ $key }}')"
              class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $key === 'orders' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">{{ $label }}</button>
      @endforeach
    </div>
  </div>

  <div class="overflow-x-auto mt-3">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-y border-slate-200">
        <tr id="detail-head"></tr>
      </thead>
      <tbody class="divide-y divide-slate-100" id="detail-body"></tbody>
    </table>
  </div>

  <div class="p-4 border-t border-slate-200 flex items-center justify-between gap-3 no-print">
    <div class="text-xs text-slate-500" id="detail-meta">—</div>
    <div class="flex items-center gap-1.5">
      <button type="button" onclick="detailPage(-1)" id="detail-prev"
              class="h-8 px-3 border border-slate-200 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
      <button type="button" onclick="detailPage(1)" id="detail-next"
              class="h-8 px-3 border border-slate-200 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
{{-- Chart.js is already loaded once in the layout. --}}
<script>
  /* ========== STATE ========== */
  /* `var` throughout: the SPA router re-evaluates this script per navigation. */
  var report = @json($report);
  var analytics = @json($analytics);
  var canSetTarget = @json($canSetTarget);
  var currentRange = @json($range);
  var charts = {};

  /* ================= ANALYTICS RENDERING =================
     Everything below is driven by the `analytics` payload, so the initial
     server render and every later range switch go through one code path. */

  var pct = (v) => (Number(v) || 0).toFixed(1) + '%';
  var num = (v) => Number(v || 0).toLocaleString('en-IN');

  function deltaMarkup(delta, higherIsBetter) {
    if (!delta || delta.direction === 'flat') {
      return '<span class="text-slate-400">—</span>';
    }

    const rising = delta.direction === 'up';
    const good = higherIsBetter === false ? !rising : rising;
    const cls = good ? 'text-emerald-600' : 'text-red-500';
    const icon = rising ? 'fa-arrow-up' : 'fa-arrow-down';

    return `<span class="${cls} font-medium whitespace-nowrap"><i class="fa-solid ${icon} text-[7px]"></i> ${delta.value}%</span>`;
  }

  function formatBy(value, format) {
    if (format === 'money') return Atelier.money(value);
    if (format === 'percent') return (Number(value) || 0) + '%';
    return num(value);
  }

  /* ---------------- Revenue target ---------------- */
  function renderTarget() {
    const t = analytics.target;
    const body = document.getElementById('target-body');
    if (!body) return;

    const scope = document.getElementById('target-scope');
    if (scope) {
      scope.textContent = t.configured
        ? `${Atelier.money(t.monthly)} a month, scaled to ${t.days} day(s)`
        : 'No target set yet';
    }

    if (!t.configured) {
      body.innerHTML = `
        <div class="text-center py-6">
          <i class="fa-regular fa-circle-dot text-slate-300 text-2xl"></i>
          <p class="text-sm text-slate-500 mt-2">No monthly revenue target set.</p>
          <p class="text-xs text-slate-400 mt-1">${canSetTarget ? 'Use “Set” above to add one.' : 'An administrator can add one.'}</p>
        </div>`;
      return;
    }

    const reached = Math.min(t.percent, 100);
    const paceCls = t.on_track ? 'bg-emerald-500' : 'bg-amber-500';

    body.innerHTML = `
      <div class="flex items-baseline justify-between">
        <div class="text-2xl font-bold text-slate-900 tracking-tight">${Atelier.money(t.achieved)}</div>
        <div class="text-xs text-slate-500">of ${Atelier.money(t.target)}</div>
      </div>

      <div class="relative w-full h-2.5 bg-slate-100 rounded-full mt-3 overflow-hidden">
        <div class="absolute inset-y-0 left-0 ${paceCls} rounded-full" style="width:${reached}%"></div>
      </div>
      <div class="relative w-full mt-1" style="height:14px">
        <div class="absolute top-0 text-[9px] text-slate-400 -translate-x-1/2 whitespace-nowrap"
             style="left:${Math.min(Math.max(t.pace_percent, 6), 94)}%">
          <i class="fa-solid fa-caret-up"></i> pace ${t.pace_percent}%
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 mt-3">
        <div>
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Reached</div>
          <div class="text-sm font-bold ${t.on_track ? 'text-emerald-600' : 'text-amber-600'}">${t.percent}%</div>
        </div>
        <div>
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Still needed</div>
          <div class="text-sm font-bold text-slate-900">${Atelier.money(t.remaining)}</div>
        </div>
        <div>
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Days left</div>
          <div class="text-sm font-bold text-slate-900">${t.days_left}</div>
        </div>
        <div>
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Needed / day</div>
          <div class="text-sm font-bold text-slate-900">${t.days_left > 0 ? Atelier.money(t.daily_needed) : '—'}</div>
        </div>
      </div>`;
  }

  window.toggleTargetEditor = function () {
    const box = document.getElementById('target-editor');
    if (!box) return;

    box.classList.toggle('hidden');

    if (!box.classList.contains('hidden')) {
      const input = document.getElementById('target-input');
      input.value = analytics.target.monthly || '';
      input.focus();
    }
  };

  window.saveTarget = async function () {
    const input = document.getElementById('target-input');
    const value = Number(input.value);

    if (!isFinite(value) || value < 0) { toast('Enter a valid amount', 'error'); return; }

    try {
      const res = await Atelier.api.post(`{{ route('reports.target') }}?${exportParams()}`, {
        monthly_revenue_target: value,
      });

      analytics.target = res.target;
      renderTarget();
      renderInsights();
      document.getElementById('target-editor').classList.add('hidden');
      toast(res.message || 'Revenue target saved.', 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not save the target');
    }
  };

  /* ---------------- Written read-out ---------------- */
  function renderInsights() {
    const box = document.getElementById('insights-list');
    if (!box) return;

    const tones = {
      good: ['fa-circle-check', 'text-emerald-600', 'bg-emerald-50'],
      warn: ['fa-triangle-exclamation', 'text-amber-600', 'bg-amber-50'],
      flat: ['fa-circle-info', 'text-slate-500', 'bg-slate-50'],
    };

    box.innerHTML = (analytics.insights || []).map(item => {
      const [icon, colour, bg] = tones[item.tone] || tones.flat;
      return `
        <div class="flex items-start gap-3 py-1.5">
          <div class="w-5 h-5 rounded-md ${bg} ${colour} flex items-center justify-center shrink-0 mt-0.5">
            <i class="fa-solid ${icon} text-[10px]"></i>
          </div>
          <p class="text-sm text-slate-700 leading-relaxed">${Atelier.escapeHtml(item.text)}</p>
        </div>`;
    }).join('') || '<p class="text-sm text-slate-400 text-center py-6">Nothing to report for this period.</p>';
  }

  /* ---------------- Period comparison ---------------- */
  function renderComparison() {
    const body = document.getElementById('comparison-body');
    if (!body) return;

    body.innerHTML = (analytics.comparison || []).map(row => {
      const change = row.change;
      const sign = change > 0 ? '+' : (change < 0 ? '−' : '');
      const magnitude = formatBy(Math.abs(change), row.format);

      return `
        <tr class="hover:bg-slate-50">
          <td class="px-5 py-2.5 font-medium text-slate-700">${Atelier.escapeHtml(row.label)}</td>
          <td class="px-5 py-2.5 text-right font-bold text-slate-900">${formatBy(row.current, row.format)}</td>
          <td class="px-5 py-2.5 text-right text-slate-500">${formatBy(row.previous, row.format)}</td>
          <td class="px-5 py-2.5 text-right text-slate-600">${change === 0 ? '—' : sign + magnitude}</td>
          <td class="px-5 py-2.5 text-right">${deltaMarkup(row.delta, row.higher_is_better)}</td>
        </tr>`;
    }).join('');

    const note = document.getElementById('comparison-note');
    if (note) note.textContent = `Against the previous ${analytics.window.days} day(s), ending the day before this window starts`;
  }

  /* ---------------- Receivables ageing ---------------- */
  function renderAgeing() {
    const list = document.getElementById('ageing-list');
    if (!list) return;

    const d = analytics.dues;
    document.getElementById('dues-total').textContent = Atelier.money(d.total);
    document.getElementById('dues-count').textContent =
      d.orders ? `${d.orders} unpaid order(s) · oldest ${d.oldest_days} day(s)` : 'Everything is paid';

    if (!d.orders) {
      list.innerHTML = '<p class="text-sm text-slate-400 text-center py-6">No outstanding balances.</p>';
      return;
    }

    const max = Math.max(...d.buckets.map(b => b.amount), 1);
    const palette = ['bg-emerald-500', 'bg-sky-500', 'bg-amber-500', 'bg-red-500'];

    list.innerHTML = d.buckets.map((b, i) => `
      <div>
        <div class="flex items-baseline justify-between mb-1 gap-3">
          <span class="text-xs font-medium text-slate-600">${Atelier.escapeHtml(b.label)}</span>
          <span class="flex items-baseline gap-3">
            <span class="text-[11px] text-slate-400">${b.orders} order(s)</span>
            <span class="text-sm font-bold text-slate-900">${Atelier.money(b.amount)}</span>
          </span>
        </div>
        <div class="w-full h-1.5 bg-slate-100 rounded-full">
          <div class="h-full ${palette[i % palette.length]} rounded-full" style="width:${(b.amount / max * 100).toFixed(1)}%"></div>
        </div>
      </div>`).join('') + `
      <div class="pt-2 mt-1 border-t border-slate-100 flex items-center justify-between">
        <span class="text-xs text-slate-500">Over 60 days old</span>
        <span class="text-sm font-bold ${d.over_60 > 0 ? 'text-red-500' : 'text-slate-900'}">${Atelier.money(d.over_60)}</span>
      </div>`;
  }

  /* ---------------- Retention ---------------- */
  function renderRetention() {
    const box = document.getElementById('retention-body');
    if (!box) return;

    const r = analytics.retention;
    const rows = [
      ['Customers who ordered', num(r.active)],
      ['Returning (ordered before)', `${num(r.returning)} · ${pct(r.return_rate)}`],
      ['First-time customers', num(r.new)],
      ['Ordered more than once', `${num(r.repeat_buyers)} · ${pct(r.repeat_rate)}`],
      ['Orders per customer', r.orders_per_client],
      ['Value per customer', Atelier.money(r.value_per_client)],
      ['Not seen in 90 days', num(r.lapsed_90)],
    ];

    box.innerHTML = `
      <div class="flex items-center gap-2 mb-4">
        <div class="flex-1 h-2.5 bg-slate-100 rounded-full overflow-hidden flex">
          <div class="h-full bg-indigo-600" style="width:${r.return_rate}%"></div>
          <div class="h-full bg-emerald-500" style="width:${(100 - r.return_rate).toFixed(1)}%"></div>
        </div>
      </div>
      <div class="flex items-center gap-4 text-[11px] text-slate-500 mb-4">
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-indigo-600"></span> Returning</span>
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> New</span>
      </div>
      <div class="space-y-2">
        ${rows.map(([label, value]) => `
          <div class="flex items-center justify-between border-b border-slate-100 pb-1.5 last:border-0">
            <span class="text-xs text-slate-500">${label}</span>
            <span class="text-sm font-semibold text-slate-900">${value}</span>
          </div>`).join('')}
      </div>`;
  }

  /* ---------------- Delivery performance ---------------- */
  function renderDelivery() {
    const box = document.getElementById('delivery-body');
    if (!box) return;

    const d = analytics.delivery;
    const rateCls = d.on_time_rate >= 90 ? 'text-emerald-600' : (d.on_time_rate >= 75 ? 'text-amber-600' : 'text-red-500');

    box.innerHTML = `
      <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">On-time rate</div>
          <div class="text-xl font-bold ${rateCls} mt-0.5">${pct(d.on_time_rate)}</div>
          <div class="text-[10px] text-slate-400">${num(d.on_time)} of ${num(d.measured)} with a promised date</div>
        </div>
        <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
          <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Avg. turnaround</div>
          <div class="text-xl font-bold text-slate-900 mt-0.5">${d.avg_days} <span class="text-xs font-medium text-slate-400">days</span></div>
          <div class="text-[10px] text-slate-400">fastest ${d.fastest_days}d · slowest ${d.slowest_days}d</div>
        </div>
      </div>
      <div class="space-y-2">
        ${[
          ['Delivered in this period', num(d.delivered), ''],
          ['Open orders right now', num(d.open_orders), ''],
          ['Past their delivery date', num(d.overdue), d.overdue > 0 ? 'text-red-500' : ''],
          ['Due today', num(d.due_today), ''],
          ['Due within 7 days', num(d.due_7_days), ''],
        ].map(([label, value, cls]) => `
          <div class="flex items-center justify-between border-b border-slate-100 pb-1.5 last:border-0">
            <span class="text-xs text-slate-500">${label}</span>
            <span class="text-sm font-semibold ${cls || 'text-slate-900'}">${value}</span>
          </div>`).join('')}
      </div>`;
  }

  /* ---------------- Weekday rhythm ---------------- */
  function renderWeekdays() {
    const box = document.getElementById('weekday-body');
    if (!box) return;

    const days = analytics.weekdays || [];
    const max = Math.max(...days.map(d => d.orders), 1);
    const busiest = days.reduce((a, b) => (b.orders > (a?.orders ?? -1) ? b : a), null);

    box.innerHTML = days.map(d => `
      <div class="flex items-center gap-3">
        <span class="w-10 text-xs font-medium ${d === busiest && d.orders > 0 ? 'text-slate-900' : 'text-slate-500'}">${d.short}</span>
        <div class="flex-1 h-4 bg-slate-100 rounded">
          <div class="h-full ${d === busiest && d.orders > 0 ? 'bg-slate-900' : 'bg-slate-300'} rounded" style="width:${(d.orders / max * 100).toFixed(1)}%"></div>
        </div>
        <span class="w-8 text-right text-xs font-semibold text-slate-700">${d.orders}</span>
        <span class="w-24 text-right text-xs text-slate-400">${Atelier.money(d.value)}</span>
      </div>`).join('');
  }

  /* ---------------- Garments ---------------- */
  function renderGarments() {
    const body = document.getElementById('garments-body');
    if (!body) return;

    const rows = analytics.garments || [];
    const total = rows.reduce((sum, g) => sum + g.value, 0);

    body.innerHTML = rows.map(g => {
      const share = total > 0 ? g.value / total * 100 : 0;
      return `
        <tr class="hover:bg-slate-50">
          <td class="px-5 py-2.5 font-semibold text-slate-800">${Atelier.escapeHtml(g.garment)}</td>
          <td class="px-5 py-2.5 text-right text-slate-600">${num(g.orders)}</td>
          <td class="px-5 py-2.5 text-right font-bold text-slate-900">${Atelier.money(g.value)}</td>
          <td class="px-5 py-2.5 text-right text-slate-500">${Atelier.money(g.avg)}</td>
          <td class="px-5 py-2.5">
            <div class="flex items-center gap-2 justify-end">
              <div class="w-24 h-1.5 bg-slate-100 rounded-full"><div class="h-full bg-indigo-600 rounded-full" style="width:${share.toFixed(1)}%"></div></div>
              <span class="text-xs text-slate-500 w-10 text-right">${share.toFixed(1)}%</span>
            </div>
          </td>
        </tr>`;
    }).join('') || '<tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">No orders in this period.</td></tr>';
  }

  function renderAnalytics() {
    renderTarget();
    renderInsights();
    renderComparison();
    renderAgeing();
    renderRetention();
    renderDelivery();
    renderWeekdays();
    renderGarments();
  }

  /* ================= DETAILED RECORDS =================
     Paged and searched on the server, so a busy year never has to travel to
     the browser in one piece. */

  var detail = { type: 'orders', q: '', sort: 'created_at', dir: 'desc', page: 1, per: 15 };
  var detailTimer = null;

  var DETAIL_COLUMNS = {
    orders: [
      { key: 'order_number', label: 'Order', sortable: true, render: r => `<span class="font-semibold text-slate-800">${Atelier.escapeHtml(r.order_number || '—')}</span>` },
      { key: 'created_at', label: 'Placed', sortable: true, render: r => showDate(r.created_at) },
      { key: 'customer', label: 'Customer', sortable: true, render: r => Atelier.escapeHtml(r.customer || 'Walk-in') },
      { key: 'garment', label: 'Garment', render: r => `<span class="text-slate-500">${Atelier.escapeHtml(r.garment || '—')}</span>` },
      { key: 'status', label: 'Status', sortable: true, render: r => `<span class="badge ${Atelier.badgeClass(r.status)} text-[10px]">${Atelier.escapeHtml(r.status)}</span>` },
      { key: 'total', label: 'Total', sortable: true, align: 'right', render: r => `<span class="font-semibold">${Atelier.money(r.total)}</span>` },
      { key: 'balance', label: 'Balance', sortable: true, align: 'right', render: r => `<span class="${Number(r.balance) > 0 ? 'text-red-500 font-semibold' : 'text-slate-400'}">${Atelier.money(r.balance)}</span>` },
      { key: 'delivery_date', label: 'Delivery', sortable: true, align: 'right', render: r => showDate(r.delivery_date) },
    ],
    payments: [
      { key: 'date', label: 'Date', sortable: true, render: r => showDate(r.date) },
      { key: 'customer', label: 'Customer', sortable: true, render: r => `<span class="font-semibold text-slate-800">${Atelier.escapeHtml(r.customer || 'Walk-in')}</span>` },
      { key: 'order_number', label: 'Order', render: r => `<span class="text-slate-500">${Atelier.escapeHtml(r.order_number || '—')}</span>` },
      { key: 'method', label: 'Method', sortable: true, render: r => Atelier.escapeHtml(r.method || 'Unspecified') },
      { key: 'reference', label: 'Reference', render: r => `<span class="text-slate-400">${Atelier.escapeHtml(r.reference || r.invoice_id || '—')}</span>` },
      { key: 'amount', label: 'Amount', sortable: true, align: 'right', render: r => `<span class="font-semibold text-emerald-600">${Atelier.money(r.amount)}</span>` },
    ],
    expenses: [
      { key: 'date', label: 'Date', sortable: true, render: r => showDate(r.date) },
      { key: 'description', label: 'Description', render: r => `<span class="font-semibold text-slate-800">${Atelier.escapeHtml(r.description || '—')}</span>` },
      { key: 'category', label: 'Category', sortable: true, render: r => Atelier.escapeHtml(r.category || '—') },
      { key: 'vendor', label: 'Vendor', sortable: true, render: r => `<span class="text-slate-500">${Atelier.escapeHtml(r.vendor || '—')}</span>` },
      { key: 'method', label: 'Paid by', render: r => Atelier.escapeHtml(r.method || '—') },
      { key: 'amount', label: 'Amount', sortable: true, align: 'right', render: r => `<span class="font-semibold text-red-500">${Atelier.money(r.amount)}</span>` },
    ],
    dues: [
      { key: 'order_number', label: 'Order', render: r => `<span class="font-semibold text-slate-800">${Atelier.escapeHtml(r.order_number || '—')}</span>` },
      { key: 'created_at', label: 'Placed', sortable: true, render: r => showDate(r.created_at) },
      { key: 'customer', label: 'Customer', sortable: true, render: r => Atelier.escapeHtml(r.customer || 'Walk-in') },
      { key: 'phone', label: 'Phone', render: r => `<span class="text-slate-500">${Atelier.escapeHtml(r.phone || '—')}</span>` },
      { key: 'status', label: 'Status', render: r => `<span class="badge ${Atelier.badgeClass(r.status)} text-[10px]">${Atelier.escapeHtml(r.status)}</span>` },
      { key: 'age', label: 'Age', align: 'right', render: r => `${ageInDays(r.created_at)} d` },
      { key: 'total', label: 'Total', sortable: true, align: 'right', render: r => Atelier.money(r.total) },
      { key: 'balance', label: 'Outstanding', sortable: true, align: 'right', render: r => `<span class="font-bold text-red-500">${Atelier.money(r.balance)}</span>` },
    ],
  };

  /* These rows come straight from the database, so a datetime arrives as
     "2026-08-20 10:15:00". Not every browser parses that space-separated
     form; swapping in the "T" makes it a shape they all accept. */
  function asDate(value) {
    return typeof value === 'string' ? value.replace(' ', 'T') : value;
  }

  function showDate(value) {
    return Atelier.formatDate(asDate(value));
  }

  function ageInDays(value) {
    if (!value) return 0;
    const then = new Date(asDate(value));
    if (isNaN(then.getTime())) return 0;
    return Math.max(Math.floor((Date.now() - then.getTime()) / 86400000), 0);
  }

  var DETAIL_DEFAULT_SORT = { orders: 'created_at', payments: 'date', expenses: 'date', dues: 'balance' };

  window.setDetailType = function (type) {
    if (detail.type === type) return;

    detail.type = type;
    detail.sort = DETAIL_DEFAULT_SORT[type];
    detail.dir = 'desc';
    detail.page = 1;

    document.querySelectorAll('#detail-tabs button').forEach(btn => {
      const active = btn.dataset.detail === type;
      btn.className = `px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${active ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'}`;
    });

    const note = document.getElementById('detail-note');
    if (note) {
      note.textContent = type === 'dues'
        ? 'Every unpaid order — a running position, not limited to the selected period'
        : 'Every row behind the figures above, for the selected period';
    }

    loadDetail();
  };

  window.detailSort = function (key) {
    if (detail.sort === key) {
      detail.dir = detail.dir === 'asc' ? 'desc' : 'asc';
    } else {
      detail.sort = key;
      detail.dir = 'desc';
    }

    detail.page = 1;
    loadDetail();
  };

  window.detailPage = function (step) {
    detail.page = Math.max(detail.page + step, 1);
    loadDetail();
  };

  function renderDetailHead() {
    const head = document.getElementById('detail-head');
    if (!head) return;

    head.innerHTML = DETAIL_COLUMNS[detail.type].map(col => {
      const align = col.align === 'right' ? 'text-right' : 'text-left';

      if (!col.sortable) {
        return `<th class="px-5 py-3 ${align}">${col.label}</th>`;
      }

      const active = detail.sort === col.key;
      const icon = !active ? 'fa-sort text-slate-300' : (detail.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down');

      return `<th class="px-5 py-3 ${align}">
        <button type="button" onclick="detailSort('${col.key}')" class="inline-flex items-center gap-1 uppercase tracking-widest ${active ? 'text-slate-900' : 'hover:text-slate-700'}">
          ${col.label} <i class="fa-solid ${icon} text-[9px]"></i>
        </button>
      </th>`;
    }).join('');
  }

  async function loadDetail() {
    const body = document.getElementById('detail-body');
    if (!body) return;

    const columns = DETAIL_COLUMNS[detail.type];
    renderDetailHead();
    body.innerHTML = Atelier.skeletonRows(5, columns.length);

    const params = exportParams();
    params.set('type', detail.type);
    params.set('q', detail.q);
    params.set('sort', detail.sort);
    params.set('dir', detail.dir);
    params.set('page', detail.page);
    params.set('per', detail.per);

    try {
      const res = await Atelier.api.get(`{{ route('reports.table') }}?${params}`);

      detail.page = res.meta.page;

      body.innerHTML = res.rows.map(row => `
        <tr class="hover:bg-slate-50">
          ${columns.map(col => `<td class="px-5 py-2.5 ${col.align === 'right' ? 'text-right' : ''}">${col.render(row)}</td>`).join('')}
        </tr>`).join('') || `<tr><td colspan="${columns.length}" class="px-5 py-12 text-center text-sm text-slate-400">No records match this period${detail.q ? ' and search' : ''}.</td></tr>`;

      const meta = document.getElementById('detail-meta');
      if (meta) {
        meta.textContent = res.meta.total
          ? `Showing ${res.meta.from}–${res.meta.to} of ${num(res.meta.total)}`
          : 'No records';
      }

      document.getElementById('detail-prev').disabled = res.meta.page <= 1;
      document.getElementById('detail-next').disabled = res.meta.page >= res.meta.pages;
    } catch (err) {
      body.innerHTML = `<tr><td colspan="${columns.length}" class="px-5 py-12 text-center text-sm text-red-500">Could not load these records.</td></tr>`;
      Atelier.reportError(err, 'Could not load the detailed records');
    }
  }

  function bindDetailControls() {
    const search = document.getElementById('detail-search');
    if (search) {
      search.addEventListener('input', (e) => {
        clearTimeout(detailTimer);
        detail.q = e.target.value.trim();
        detail.page = 1;
        detailTimer = setTimeout(loadDetail, 300);
      }, { signal: Atelier.pageSignal() });
    }

    const per = document.getElementById('detail-per');
    if (per) {
      per.addEventListener('change', (e) => {
        detail.per = Number(e.target.value) || 15;
        detail.page = 1;
        loadDetail();
      }, { signal: Atelier.pageSignal() });
    }
  }


  /* The window the user is looking at, in a form the export routes accept. */
  function exportParams() {
    const params = new URLSearchParams({ range: currentRange });

    if (currentRange === 'custom') {
      params.set('from', document.getElementById('range-from').value);
      params.set('to', document.getElementById('range-to').value);
    }

    return params;
  }

  window.handleExport = function(type) {
    if (type === 'Print') {
      toast('Opening print dialog...', 'info');
      setTimeout(() => window.print(), 400);
      return;
    }

    // The PDF is rendered server-side from the same figures on screen, so the
    // file downloads straight away rather than going through a print dialog.
    if (type === 'PDF') {
      window.location.href = `{{ route('reports.pdf') }}?${exportParams()}`;
      toast('Preparing PDF…', 'success');
      return;
    }

    window.location.href = `{{ route('reports.export') }}?${exportParams()}`;
    toast(`${type} report downloading…`, 'success');
  }

  /* ========== RANGE SWITCHING ========== */
  window.toggleCustomRange = function() {
    const el = document.getElementById('custom-range-inputs');
    el.classList.toggle('hidden');
    el.classList.toggle('flex');
  };

  window.applyCustomRange = function() {
    const from = document.getElementById('range-from').value;
    const to = document.getElementById('range-to').value;

    if (!from || !to) { toast('Choose both a start and end date', 'error'); return; }
    if (from > to) { toast('The start date must be before the end date', 'error'); return; }

    loadReport('custom', from, to);
  };

  window.setRange = function(range) {
    document.getElementById('custom-range-inputs').classList.add('hidden');
    document.getElementById('custom-range-inputs').classList.remove('flex');
    loadReport(range);
  };

  async function loadReport(range, from = null, to = null) {
    const params = new URLSearchParams({ range });
    if (from) params.set('from', from);
    if (to) params.set('to', to);

    try {
      const res = await Atelier.api.get(`{{ route('reports.data') }}?${params}`);
      report = res.report;
      currentRange = range;

      document.querySelectorAll('#range-tabs button').forEach(b => {
        const active = b.dataset.range === range;
        const isCustom = b.dataset.range === 'custom';
        b.className = `px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${isCustom ? 'flex items-center gap-1 ' : ''}${active ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'}`;
        if (isCustom) b.innerHTML = '<i class="fa-solid fa-calendar text-[10px]"></i> Custom';
      });

      document.getElementById('range-label').textContent = report.label;

      const printLabel = document.getElementById('print-range-label');
      if (printLabel) printLabel.textContent = report.label;

      analytics = res.analytics || analytics;

      applyKpis();
      renderCharts();
      renderTables();
      renderAnalytics();

      detail.page = 1;
      loadDetail();
    } catch (err) {
      Atelier.reportError(err, 'Could not load the report');
    }
  }

  function applyKpis() {
    const formatters = {
      revenue: v => Atelier.money(v),
      completed: v => Number(v).toLocaleString('en-IN'),
      avg_order: v => Atelier.money(v),
      collection_rate: v => v + '%',
    };

    Object.entries(report.kpis).forEach(([key, k]) => {
      const value = document.querySelector(`[data-kpi="${key}"]`);
      if (value) value.textContent = formatters[key](k.value);

      const delta = document.querySelector(`[data-kpi-delta="${key}"]`);
      if (delta) {
        const down = k.delta.direction === 'down';
        delta.className = `text-[11px] ${down ? 'text-red-500' : 'text-emerald-600'} font-medium flex items-center gap-1`;
        delta.innerHTML = `<i class="fa-solid ${down ? 'fa-arrow-down' : 'fa-arrow-up'} text-[7px]"></i> ${k.delta.value}%`;
      }

      const bar = document.querySelector(`[data-kpi-bar="${key}"]`);
      if (bar) bar.style.width = k.bar + '%';
    });
  }

  function renderTables() {
    const gradients = ['from-indigo-500 to-purple-600', 'from-emerald-500 to-teal-600', 'from-rose-500 to-pink-600', 'from-sky-500 to-blue-600'];
    const palette = [
      ['bg-indigo-50 text-indigo-600', 'bg-indigo-600'],
      ['bg-sky-50 text-sky-600', 'bg-sky-500'],
      ['bg-emerald-50 text-emerald-600', 'bg-emerald-500'],
      ['bg-amber-50 text-amber-600', 'bg-amber-500'],
    ];

    const customers = document.getElementById('top-customers');
    if (customers) {
      customers.innerHTML = report.top_customers.map((c, i) => `
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br ${gradients[i % gradients.length]} text-white flex items-center justify-center text-xs font-bold">${c.initials}</div>
            <div><div class="text-sm font-semibold text-slate-900">${Atelier.escapeHtml(c.name)}</div><div class="text-xs text-slate-500">${c.orders} Orders</div></div>
          </div>
          <div class="text-sm font-bold text-slate-900">${Atelier.money(c.spent)}</div>
        </div>
      `).join('') || '<div class="text-center text-sm text-slate-400 py-8">No customer activity in this period</div>';
    }

    const tailors = document.getElementById('tailor-performance');
    if (tailors) {
      tailors.innerHTML = report.tailors.map((t, i) => {
        const [iconClass, barClass] = palette[i % palette.length];
        const rateColor = t.rate >= 90 ? 'text-emerald-600' : t.rate >= 75 ? 'text-amber-600' : 'text-red-500';
        return `
          <div>
            <div class="flex items-center justify-between mb-1">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg ${iconClass} flex items-center justify-center text-xs font-bold"><i class="fa-solid fa-scissors"></i></div>
                <div class="text-sm font-semibold text-slate-900">${Atelier.escapeHtml(t.name)}</div>
              </div>
              <div class="text-xs font-semibold text-slate-700">${t.orders} Orders <span class="${rateColor} ml-2">${t.rate}%</span></div>
            </div>
            <div class="w-full h-1.5 bg-slate-100 rounded-full"><div class="h-full ${barClass} rounded-full" style="width:${t.rate}%"></div></div>
          </div>`;
      }).join('') || '<div class="text-center text-sm text-slate-400 py-8">No orders assigned to tailors in this period</div>';
    }
  }

  /* ========== CHARTS ========== */
  function renderCharts() {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#94A3B8';

    const configs = {
      'revenue-chart': {
        type: 'line',
        data: {
          labels: report.revenue_series.labels,
          datasets: [{
            label: 'Revenue',
            data: report.revenue_series.data,
            borderColor: '#4F46E5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            tension: 0.4, fill: true, borderWidth: 3,
            pointRadius: report.revenue_series.labels.length > 20 ? 0 : 4,
            pointBackgroundColor: '#fff', pointBorderColor: '#4F46E5', pointBorderWidth: 2
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => Atelier.money(c.parsed.y) } } },
          scales: {
            x: { grid: { display: false }, ticks: { maxTicksLimit: 12 } },
            y: { grid: { color: '#F1F5F9' }, ticks: { callback: v => Atelier.currency + (v / 1000) + 'K' } }
          }
        }
      },
      'category-chart': {
        type: 'doughnut',
        data: {
          labels: report.category_series.labels,
          datasets: [{
            data: report.category_series.data,
            backgroundColor: ['#4F46E5', '#EF4444', '#0EA5E9', '#8B5CF6', '#10B981', '#F59E0B'],
            borderWidth: 0, hoverOffset: 8
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false, cutout: '70%',
          plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 15 } } }
        }
      },
      'status-chart': {
        type: 'polarArea',
        data: {
          labels: report.status_series.labels,
          datasets: [{
            data: report.status_series.data,
            backgroundColor: ['rgba(16, 185, 129, 0.7)', 'rgba(14, 165, 233, 0.7)', 'rgba(139, 92, 246, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(239, 68, 68, 0.7)', 'rgba(100, 116, 139, 0.7)'],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { position: 'right', labels: { usePointStyle: true, pointStyle: 'circle', padding: 12 } } },
          scales: { r: { grid: { color: '#F1F5F9' }, ticks: { display: false } } }
        }
      },
      'growth-chart': {
        type: 'line',
        data: {
          labels: report.growth_series.labels,
          datasets: [
            { label: 'New', data: report.growth_series.new, borderColor: '#4F46E5', backgroundColor: 'rgba(79, 70, 229, 0.1)', tension: .4, fill: true, borderWidth: 2.5, pointRadius: 3 },
            { label: 'Returning', data: report.growth_series.returning, borderColor: '#10B981', backgroundColor: 'transparent', tension: .4, borderWidth: 2.5, pointRadius: 3 }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { position: 'top', align: 'end', labels: { usePointStyle: true, pointStyle: 'circle' } } },
          scales: { x: { grid: { display: false } }, y: { grid: { color: '#F1F5F9' } } }
        }
      }
    };

    Object.entries(configs).forEach(([id, config]) => {
      const ctx = document.getElementById(id);
      if (!ctx) return;

      if (charts[id]) {
        charts[id].data = config.data;
        charts[id].update('none');
      } else {
        charts[id] = new Chart(ctx, config);
      }
    });
  }

  Atelier.onPageReady(() => {
    renderCharts();
    renderAnalytics();
    bindDetailControls();
    loadDetail();
    if (currentRange === 'custom') toggleCustomRange();
  });
</script>
@endpush
