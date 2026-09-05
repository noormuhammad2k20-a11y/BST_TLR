@extends('cloth-store.layouts.app')
@section('title', 'Reports & Analytics')
@section('spaPage', 'cloth-store-reports')

@section('content')
<!-- Page Header & Global Filter -->
<div class="page flex justify-between items-end mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Reports & Analytics</h1>
    <p class="text-sm text-slate-500 mt-0.5">Comprehensive insights across sales, inventory, and finances.</p>
    {{-- Print hides the period selector, so the sheet carries its own window. --}}
    <p class="print-only text-xs font-semibold text-slate-700 mt-1">
      {{ \App\Services\Settings::str('store_name') ?: 'Atelier' }} ·
      {{ $startDate->format('d M Y') }} – {{ $endDate->format('d M Y') }}
    </p>
  </div>
  <div class="no-print">
    <form data-filter-form method="GET" action="{{ route('cloth-store.reports.index') }}" class="flex items-center gap-2" id="filterForm">
      <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-lg p-1 shadow-sm">
        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-2 pr-1">Period</label>
        <select name="date_filter" class="text-xs font-semibold text-slate-700 bg-transparent py-1.5 pl-2 pr-6 border-none focus:ring-0 cursor-pointer">
          <option value="today" {{ $filter == 'today' ? 'selected' : '' }}>Today</option>
          <option value="yesterday" {{ $filter == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
          <option value="this_week" {{ $filter == 'this_week' ? 'selected' : '' }}>This Week</option>
          <option value="this_month" {{ $filter == 'this_month' ? 'selected' : '' }}>This Month</option>
          <option value="last_month" {{ $filter == 'last_month' ? 'selected' : '' }}>Last Month</option>
          <option value="this_year" {{ $filter == 'this_year' ? 'selected' : '' }}>This Year</option>
          <option value="custom" {{ $filter == 'custom' ? 'selected' : '' }}>Custom Range</option>
        </select>
      </div>
      @if($filter == 'custom')
      <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-lg p-1 shadow-sm">
         <input type="date" name="start_date" value="{{ request('start_date') }}" class="text-xs border-none py-1 focus:ring-0">
         <span class="text-slate-300">-</span>
         <input type="date" name="end_date" value="{{ request('end_date') }}" class="text-xs border-none py-1 focus:ring-0">
      </div>
      @endif
      <button type="button" class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 shadow-sm" onclick="downloadReportPdf()">
        <i class="fa-solid fa-file-pdf"></i> Download PDF
      </button>
      <button type="button" class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 flex items-center gap-2 shadow-sm" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Print
      </button>
    </form>
  </div>
</div>

<!-- Global KPI Dash -->
<div class="page grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-indigo-900 text-white p-5 rounded-xl shadow-lg relative overflow-hidden">
    <div class="absolute -right-4 -top-4 text-indigo-700/30 text-7xl"><i class="fa-solid fa-chart-line"></i></div>
    <div class="text-[10px] font-bold text-indigo-300 uppercase tracking-widest relative z-10">Net Sales</div>
    <div class="text-2xl font-black mt-1 relative z-10">Rs {{ number_format($kpis['net_sales']) }}</div>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
    <div>
      <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Gross Profit</div>
      <div class="text-xl font-bold text-emerald-600 mt-1">Rs {{ number_format($kpis['gross_profit']) }}</div>
    </div>
    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg"><i class="fa-solid fa-money-bill-trend-up"></i></div>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
    <div>
      <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Meters Sold</div>
      <div class="text-xl font-bold text-slate-900 mt-1">{{ number_format($kpis['meters_sold']) }} m</div>
    </div>
    <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg"><i class="fa-solid fa-ruler-combined"></i></div>
  </div>
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
    <div>
      <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Net Profit</div>
      <div class="text-xl font-bold {{ $kpis['net_profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }} mt-1">Rs {{ number_format($kpis['net_profit']) }}</div>
      <div class="text-[10px] text-slate-400 mt-1">Margin: {{ number_format($kpis['profit_margin'], 1) }}%</div>
    </div>
    <div class="w-10 h-10 rounded-full {{ $kpis['net_profit'] >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }} flex items-center justify-center text-lg"><i class="fa-solid fa-scale-balanced"></i></div>
  </div>
</div>

<!-- Tabs Navigation -->
<div class="page no-print border-b border-slate-200 mb-6">
  <nav class="-mb-px flex space-x-8" aria-label="Tabs" id="reportTabs">
    <button onclick="switchTab('sales')" class="tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm border-indigo-500 text-indigo-600" id="tab-sales">Sales & Profit</button>
    <button onclick="switchTab('fabric')" class="tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300" id="tab-fabric">Fabric & Categories</button>
    <button onclick="switchTab('inventory')" class="tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300" id="tab-inventory">Inventory</button>
    <button onclick="switchTab('customers')" class="tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300" id="tab-customers">Customers & Finance</button>
  </nav>
</div>

<!-- Tab: Sales & Profit -->
<div id="panel-sales" class="tab-panel page">
  <div class="grid grid-cols-3 gap-6 mb-6">
    <!-- Chart -->
    <div class="col-span-2 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
      <h3 class="text-sm font-bold text-slate-800 mb-4">Daily Revenue Trend</h3>
      <div class="h-64">
        <canvas id="salesChart"></canvas>
      </div>
    </div>
    <!-- Stats -->
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
      <h3 class="text-sm font-bold text-slate-800 mb-4">Sales Breakdown</h3>
      <div class="space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 pb-2">
          <span class="text-xs text-slate-500 font-medium">Transactions</span>
          <span class="text-sm font-bold text-slate-900">{{ number_format($salesReport['transactions']) }}</span>
        </div>
        <div class="flex justify-between items-center border-b border-slate-100 pb-2">
          <span class="text-xs text-slate-500 font-medium">Avg Sale Value</span>
          <span class="text-sm font-bold text-slate-900">Rs {{ number_format($salesReport['average_sale']) }}</span>
        </div>
        <div class="flex justify-between items-center border-b border-slate-100 pb-2">
          <span class="text-xs text-slate-500 font-medium">Avg Meters/Sale</span>
          <span class="text-sm font-bold text-slate-900">{{ number_format($salesReport['average_meters'], 2) }} m</span>
        </div>
        <div class="flex justify-between items-center border-b border-slate-100 pb-2">
          <span class="text-xs text-slate-500 font-medium">Total Discounts</span>
          <span class="text-sm font-bold text-rose-500">Rs {{ number_format($salesReport['discounts']) }}</span>
        </div>
        <div class="flex justify-between items-center border-b border-slate-100 pb-2">
          <span class="text-xs text-slate-500 font-medium">Cost of Goods (Est)</span>
          <span class="text-sm font-bold text-amber-600">Rs {{ number_format($kpis['net_sales'] - $kpis['gross_profit']) }}</span>
        </div>
        <div class="flex justify-between items-center pt-2">
          <span class="text-xs font-bold text-slate-800 uppercase">Gross Profit</span>
          <span class="text-lg font-black text-emerald-600">Rs {{ number_format($kpis['gross_profit']) }}</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tab: Fabric & Categories -->
<div id="panel-fabric" class="tab-panel page hidden">
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
      <h3 class="font-bold text-slate-800">Fabric Sales Report</h3>
      <button type="button" class="no-print text-xs text-indigo-600 font-medium hover:underline flex items-center gap-1" onclick="downloadReportPdf()"><i class="fa-solid fa-file-pdf"></i> Download PDF</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
          <tr>
            <th class="px-5 py-3 text-left">Product</th>
            <th class="px-5 py-3 text-left">Category</th>
            <th class="px-5 py-3 text-right">Meters Sold</th>
            <th class="px-5 py-3 text-right">Revenue</th>
            <th class="px-5 py-3 text-right">Profit</th>
            <th class="px-5 py-3 text-right">Margin</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          @forelse($fabricSales as $f)
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 font-bold text-slate-800">{{ $f->product }}</td>
            <td class="px-5 py-3 text-slate-500">{{ $f->category }}</td>
            <td class="px-5 py-3 text-right font-mono font-bold">{{ number_format($f->meters_sold, 2) }}</td>
            <td class="px-5 py-3 text-right text-indigo-600 font-bold">Rs {{ number_format($f->revenue) }}</td>
            <td class="px-5 py-3 text-right text-emerald-600 font-bold">Rs {{ number_format($f->profit) }}</td>
            <td class="px-5 py-3 text-right text-slate-500">{{ number_format($f->margin, 1) }}%</td>
          </tr>
          @empty
          <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">No sales data for this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50">
      <h3 class="font-bold text-slate-800">Category Performance</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
          <tr>
            <th class="px-5 py-3 text-left">Category</th>
            <th class="px-5 py-3 text-right">Transactions</th>
            <th class="px-5 py-3 text-right">Meters Sold</th>
            <th class="px-5 py-3 text-right">Revenue</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          @foreach($categorySales as $c)
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 font-bold text-slate-800">{{ $c->category }}</td>
            <td class="px-5 py-3 text-right">{{ number_format($c->transactions) }}</td>
            <td class="px-5 py-3 text-right font-mono">{{ number_format($c->meters_sold, 2) }}</td>
            <td class="px-5 py-3 text-right text-indigo-600 font-bold">Rs {{ number_format($c->revenue) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Tab: Inventory -->
<div id="panel-inventory" class="tab-panel page hidden">
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
      <h3 class="font-bold text-slate-800">Current Inventory Valuation</h3>
      <div class="text-sm font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded">
        Total Value: Rs {{ number_format($inventory->sum('value')) }}
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
          <tr>
            <th class="px-5 py-3 text-left">Product</th>
            <th class="px-5 py-3 text-right">Current Stock</th>
            <th class="px-5 py-3 text-right">Cost Rate</th>
            <th class="px-5 py-3 text-right">Selling Rate</th>
            <th class="px-5 py-3 text-right">Stock Value</th>
            <th class="px-5 py-3 text-center">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          @foreach($inventory as $inv)
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 font-bold text-slate-800">
              {{ $inv['product'] }}
              <div class="text-[10px] text-slate-400 font-normal mt-0.5">{{ $inv['category'] }}</div>
            </td>
            <td class="px-5 py-3 text-right font-mono font-bold {{ $inv['status'] == 'Low Stock' ? 'text-rose-600' : 'text-slate-700' }}">{{ $inv['meters'] }} m</td>
            <td class="px-5 py-3 text-right text-slate-500">Rs {{ number_format($inv['purchase_rate']) }}</td>
            <td class="px-5 py-3 text-right text-slate-500">Rs {{ number_format($inv['selling_rate']) }}</td>
            <td class="px-5 py-3 text-right text-slate-900 font-bold">Rs {{ number_format($inv['value']) }}</td>
            <td class="px-5 py-3 text-center">
              @if($inv['status'] == 'Low Stock') <span class="badge badge-cancelled text-[10px]">Low Stock (< {{ $inv['reorder'] }})</span>
              @else <span class="badge badge-progress text-[10px]">In Stock</span> @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Tab: Customers & Finance -->
<div id="panel-customers" class="tab-panel page hidden">
  <div class="grid grid-cols-2 gap-6 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 bg-slate-50"><h3 class="font-bold text-slate-800">Top Customers (By Spending)</h3></div>
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
          <tr>
            <th class="px-5 py-2 text-left">Customer</th>
            <th class="px-5 py-2 text-center">Visits</th>
            <th class="px-5 py-2 text-right">Spending</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          @forelse($customers as $c)
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 font-bold text-slate-800">{{ $c->name }}</td>
            <td class="px-5 py-3 text-center">{{ $c->visits }}</td>
            <td class="px-5 py-3 text-right text-emerald-600 font-bold">Rs {{ number_format($c->total_spending) }}</td>
          </tr>
          @empty
          <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">No active customers in period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 bg-slate-50"><h3 class="font-bold text-slate-800">Expenses Breakdown</h3></div>
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
          <tr>
            <th class="px-5 py-2 text-left">Category</th>
            <th class="px-5 py-2 text-right">Amount</th>
            <th class="px-5 py-2 text-right">% of Total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          @forelse($expenses as $e)
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 font-bold text-slate-700">{{ $e->category }}</td>
            <td class="px-5 py-3 text-right text-rose-600 font-bold">Rs {{ number_format($e->total) }}</td>
            <td class="px-5 py-3 text-right text-slate-500">{{ $kpis['total_expenses'] > 0 ? number_format(($e->total / $kpis['total_expenses']) * 100, 1) : 0 }}%</td>
          </tr>
          @empty
          <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">No expenses in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  /* Downloads the report for exactly the period the selector is showing —
     including a custom range the user has not submitted yet. */
  window.downloadReportPdf = function () {
    const form = document.getElementById('filterForm');
    const params = new URLSearchParams(new FormData(form));

    window.location.href = '{{ route('cloth-store.reports.pdf') }}?' + params.toString();

    if (typeof toast === 'function') toast('Preparing PDF…', 'success');
  };

  function switchTab(tabId) {
    // Hide all panels
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    // Reset all tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
       btn.classList.remove('border-indigo-500', 'text-indigo-600');
       btn.classList.add('border-transparent', 'text-slate-500');
    });
    // Show active panel
    document.getElementById('panel-' + tabId).classList.remove('hidden');
    // Set active tab style
    const activeBtn = document.getElementById('tab-' + tabId);
    activeBtn.classList.remove('border-transparent', 'text-slate-500');
    activeBtn.classList.add('border-indigo-500', 'text-indigo-600');
  }

  // Initialize Chart.js
  document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    const chartDates = {{ Illuminate\Support\Js::from($chartDates) }};
    const chartRevenues = {{ Illuminate\Support\Js::from($chartRevenues) }};
    
    if(chartDates.length === 0) {
       document.getElementById('salesChart').parentElement.innerHTML = '<div class="h-full flex items-center justify-center text-slate-400 text-sm">No sales data to graph for this period.</div>';
       return;
    }

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: chartDates,
        datasets: [{
          label: 'Revenue (Rs)',
          data: chartRevenues,
          borderColor: '#4f46e5', // indigo-600
          backgroundColor: 'rgba(79, 70, 229, 0.1)',
          borderWidth: 2,
          pointBackgroundColor: '#fff',
          pointBorderColor: '#4f46e5',
          pointRadius: 4,
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: '#f1f5f9', drawBorder: false },
            ticks: { callback: function(value) { return 'Rs ' + (value/1000) + 'k'; }, font: { size: 10 } }
          },
          x: {
            grid: { display: false, drawBorder: false },
            ticks: { font: { size: 10 } }
          }
        }
      }
    });
  });
</script>
@endpush
