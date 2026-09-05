@extends('cloth-store.layouts.app')
@section('title', 'Dashboard')
@section('spaPage', 'cloth-store-dashboard')

@php
  $greeting = now()->hour < 12 ? 'Good Morning' : (now()->hour < 17 ? 'Good Afternoon' : 'Good Evening');
  $greetingIcon = now()->hour < 12 ? '☀️' : (now()->hour < 17 ? '🌤️' : '🌙');
@endphp

@push('styles')
<style>
  /* ==========================================================
     STAT CARDS (Exact match to Tailor System)
     ========================================================== */
  .stat-card {
    position: relative; padding: 22px; border-radius: 16px;
    border: 1.5px solid var(--sc-border); background: var(--sc-bg);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .7), 0 1px 2px rgba(15, 23, 42, .04), 0 4px 12px -6px var(--sc-glow);
    transition: transform .3s cubic-bezier(.16,1,.3,1), box-shadow .3s ease, border-color .3s ease;
  }
  .stat-card:hover {
    transform: translateY(-3px); border-color: var(--sc-border-hover);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .7), 0 2px 4px rgba(15, 23, 42, .05), 0 16px 28px -12px var(--sc-glow);
  }
  .stat-card .stat-icon {
    width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    background: #fff; color: var(--sc-accent); border: 1px solid var(--sc-border); box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
  }
  .stat-card .stat-value { font-variant-numeric: tabular-nums; letter-spacing: -.02em; color: var(--sc-value); }
  .stat-card .stat-label { letter-spacing: .1em; color: var(--sc-label); }
  
  .stat-indigo  { --sc-bg:#F2F4FF; --sc-border:#CDD4F5; --sc-border-hover:#A9B4EC; --sc-accent:#4F55BE; --sc-glow:rgba(79,85,190,.22);  --sc-label:#4F55BE; --sc-value:#24285F; }
  .stat-sky     { --sc-bg:#EEF8FD; --sc-border:#C4E2F2; --sc-border-hover:#95CBE7; --sc-accent:#2F7BA6; --sc-glow:rgba(47,123,166,.22); --sc-label:#2F7BA6; --sc-value:#0F3B54; }
  .stat-emerald { --sc-bg:#EEF8F3; --sc-border:#C6E6D7; --sc-border-hover:#98D2B8; --sc-accent:#348866; --sc-glow:rgba(52,136,102,.22); --sc-label:#348866; --sc-value:#123D2C; }
  .stat-rose    { --sc-bg:#FDF2F3; --sc-border:#F3D0D5; --sc-border-hover:#E5A8B1; --sc-accent:#B85E70; --sc-glow:rgba(184,94,112,.22); --sc-label:#B85E70; --sc-value:#63242F; }
  .stat-amber   { --sc-bg:#FFF9F0; --sc-border:#FDE0B2; --sc-border-hover:#FBC02D; --sc-accent:#D97706; --sc-glow:rgba(217,119,6,.22); --sc-label:#B45309; --sc-value:#78350F; }

  html.theme-dark .stat-card { box-shadow: inset 0 1px 0 rgba(255,255,255,.05), 0 2px 8px -4px rgba(0,0,0,.5); }
  html.theme-dark .stat-card:hover { box-shadow: inset 0 1px 0 rgba(255,255,255,.05), 0 16px 28px -14px rgba(0,0,0,.7); }
  html.theme-dark .stat-card .stat-icon { background: rgba(255,255,255,.09); border-color: rgba(255,255,255,.12); box-shadow: none; }
  html.theme-dark .stat-indigo  { --sc-bg:#1B1D3D; --sc-border:#383C7A; --sc-border-hover:#4B509B; --sc-accent:#A9ADEB; --sc-label:#A9ADEB; --sc-value:#EDEEFF; }
  html.theme-dark .stat-sky     { --sc-bg:#122B3A; --sc-border:#265771; --sc-border-hover:#347A96; --sc-accent:#8FC4E0; --sc-label:#8FC4E0; --sc-value:#E4F2FA; }
  html.theme-dark .stat-emerald { --sc-bg:#132E25; --sc-border:#275C49; --sc-border-hover:#347A60; --sc-accent:#8ACCB0; --sc-label:#8ACCB0; --sc-value:#E1F4EB; }
  html.theme-dark .stat-rose    { --sc-bg:#392026; --sc-border:#6B404C; --sc-border-hover:#8A5464; --sc-accent:#E5A6B1; --sc-label:#E5A6B1; --sc-value:#FBE7EA; }
  html.theme-dark .stat-amber   { --sc-bg:#33220A; --sc-border:#784814; --sc-border-hover:#A16207; --sc-accent:#FBBF24; --sc-label:#FBBF24; --sc-value:#FEF3C7; }
  html.theme-dark .stat-card { background: var(--sc-bg) !important; }
  html.theme-dark .stat-card .stat-value { color: var(--sc-value) !important; }
  html.theme-dark .stat-card .stat-label { color: var(--sc-label) !important; }

  /* Premium Chart Wrappers */
  .chart-card {
    background: #fff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 20px; box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
  }
  html.theme-dark .chart-card { background: #1E293B; border-color: #334155; }
</style>
@endpush

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">{{ $greeting }}, {{ auth()->user()->short_name ?? 'Admin' }} {{ $greetingIcon }}</h1>
    <p class="text-sm text-slate-500 mt-0.5">{{ now()->format('l, F j') }} · Cloth Store Operations</p>
  </div>
  <div class="flex gap-2">
    <button class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 flex items-center gap-2 shadow-sm" onclick="Atelier.refreshPage()"><i class="fa-solid fa-arrows-rotate"></i> Refresh</button>
    <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 shadow-sm" onclick="openModal('quick-add')"><i class="fa-solid fa-bolt text-[10px]"></i> Quick Action</button>
  </div>
</div>

<!-- Sales Overview Filters -->
<div class="page bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between mb-6">
  <div class="text-sm font-semibold text-slate-900"><i class="fa-solid fa-filter text-indigo-600 mr-2"></i> Sales Overview</div>
  <select id="date-filter" class="text-sm border border-slate-200 bg-slate-50 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="SpaRouter.navigate(location.pathname + '?range=' + encodeURIComponent(this.value), { scroll: false })">
    <option value="today" {{ $range == 'today' ? 'selected' : '' }}>Today</option>
    <option value="yesterday" {{ $range == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
    <option value="week" {{ $range == 'week' ? 'selected' : '' }}>This Week</option>
    <option value="month" {{ $range == 'month' ? 'selected' : '' }}>This Month</option>
    <option value="last_month" {{ $range == 'last_month' ? 'selected' : '' }}>Last Month</option>
    <option value="all" {{ $range == 'all' ? 'selected' : '' }}>All Time</option>
  </select>
</div>

<!-- 12 KPI Cards -->
<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="stat-card stat-emerald">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Total Sales</span><div class="stat-icon"><i class="fa-solid fa-wallet"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">Rs {{ number_format($kpis['sales']) }}</h3>
  </div>
  <div class="stat-card stat-indigo">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Meters Sold</span><div class="stat-icon"><i class="fa-solid fa-ruler-horizontal"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">{{ number_format($kpis['meters_sold'], 1) }} m</h3>
  </div>
  <div class="stat-card stat-sky">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Transactions</span><div class="stat-icon"><i class="fa-solid fa-receipt"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">{{ number_format($kpis['transactions']) }}</h3>
  </div>
  <div class="stat-card stat-emerald">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Gross Profit</span><div class="stat-icon"><i class="fa-solid fa-arrow-trend-up"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">Rs {{ number_format($kpis['gross_profit']) }}</h3>
  </div>

  <div class="stat-card stat-amber">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Total Stock (Meters)</span><div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">{{ number_format($kpis['total_stock_meters']) }} m</h3>
  </div>
  <div class="stat-card stat-indigo">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Total Stock Value</span><div class="stat-icon"><i class="fa-solid fa-vault"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">Rs {{ number_format($kpis['total_stock_value']) }}</h3>
  </div>
  <div class="stat-card stat-rose">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Low Stock Fabrics</span><div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">{{ number_format($kpis['low_stock_count']) }}</h3>
  </div>
  <div class="stat-card stat-rose">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Customer Dues</span><div class="stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">Rs {{ number_format($kpis['customer_dues']) }}</h3>
  </div>

  <div class="stat-card stat-sky">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Total Products</span><div class="stat-icon"><i class="fa-solid fa-box-open"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">{{ number_format($kpis['total_products']) }}</h3>
  </div>
  <div class="stat-card stat-indigo">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Total Customers</span><div class="stat-icon"><i class="fa-solid fa-users"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">{{ number_format($kpis['total_customers']) }}</h3>
  </div>

  <div class="stat-card stat-amber">
    <div class="flex items-center justify-between mb-3"><span class="stat-label text-[10px] font-bold uppercase tracking-widest">Total Expenses</span><div class="stat-icon"><i class="fa-solid fa-coins"></i></div></div>
    <h3 class="stat-value text-2xl font-bold tracking-tight">Rs {{ number_format($kpis['expenses']) }}</h3>
  </div>
</div>

<!-- Sales Overview Minor Stats -->
<div class="page grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-white p-4 rounded-xl border border-slate-200 text-center shadow-sm"><div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Avg Sale Value</div><div class="text-lg font-bold text-slate-900 mt-1">Rs {{ number_format($kpis['avg_sale_value']) }}</div></div>
  <div class="bg-white p-4 rounded-xl border border-slate-200 text-center shadow-sm"><div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Avg Meters/Trx</div><div class="text-lg font-bold text-slate-900 mt-1">{{ number_format($kpis['avg_meters_per_trx'], 1) }} m</div></div>
  <div class="bg-white p-4 rounded-xl border border-slate-200 text-center shadow-sm"><div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Discounts</div><div class="text-lg font-bold text-slate-900 mt-1">Rs {{ number_format($kpis['total_discounts']) }}</div></div>
  <div class="bg-white p-4 rounded-xl border border-slate-200 text-center shadow-sm"><div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Returns</div><div class="text-lg font-bold text-slate-900 mt-1">Rs {{ number_format($kpis['total_returns']) }}</div></div>
</div>

<!-- CHARTS GRID -->
<div class="page grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
  <!-- Chart 1 -->
  <div class="chart-card">
    <h3 class="text-sm font-bold text-slate-900 mb-4">1. Daily Sales (Last 30 Days)</h3>
    <div class="h-64"><canvas id="chart1"></canvas></div>
  </div>
  <!-- Chart 2 -->
  <div class="chart-card">
    <h3 class="text-sm font-bold text-slate-900 mb-4">2. Daily Meters Sold (Last 30 Days)</h3>
    <div class="h-64"><canvas id="chart2"></canvas></div>
  </div>
  <!-- Chart 3 -->
  <div class="chart-card">
    <h3 class="text-sm font-bold text-slate-900 mb-4">3. Monthly Revenue (Last 12 Months)</h3>
    <div class="h-64"><canvas id="chart3"></canvas></div>
  </div>
  <!-- Chart 4 -->
  <div class="chart-card">
    <h3 class="text-sm font-bold text-slate-900 mb-4">4. Monthly Profit (Last 12 Months)</h3>
    <div class="h-64"><canvas id="chart4"></canvas></div>
  </div>
  <!-- Chart 5 -->
  <div class="chart-card">
    <h3 class="text-sm font-bold text-slate-900 mb-4">5. Sales by Fabric Category</h3>
    <div class="h-64"><canvas id="chart5"></canvas></div>
  </div>
  <!-- Chart 6 -->
  <div class="chart-card">
    <h3 class="text-sm font-bold text-slate-900 mb-4">6. Sales by Payment Method</h3>
    <div class="h-64"><canvas id="chart6"></canvas></div>
  </div>
</div>

<!-- TABLES GRID -->
<div class="page grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
  
  <!-- 7. Top Selling (Revenue) -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200"><h3 class="text-sm font-bold text-slate-900">7. Top Selling Fabrics (by Revenue)</h3></div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
          <tr><th class="px-4 py-2 text-left">Product</th><th class="px-4 py-2 text-right">Revenue</th><th class="px-4 py-2 text-right">Profit</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($topRevenue as $item)
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-semibold text-slate-900">{{ $item->product->name ?? 'Unknown' }}</td>
            <td class="px-4 py-3 text-right font-bold text-emerald-600">Rs {{ number_format($item->revenue) }}</td>
            <td class="px-4 py-3 text-right text-slate-600">Rs {{ number_format($item->profit) }}</td>
          </tr>
          @empty <tr><td colspan="3" class="p-4 text-center text-slate-500">No sales data</td></tr> @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- 8. Top Selling (Meters) -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200"><h3 class="text-sm font-bold text-slate-900">8. Top Selling Fabrics (by Meters)</h3></div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
          <tr><th class="px-4 py-2 text-left">Product</th><th class="px-4 py-2 text-right">Meters Sold</th><th class="px-4 py-2 text-right">Revenue</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($topMeters as $item)
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-semibold text-slate-900">{{ $item->product->name ?? 'Unknown' }}</td>
            <td class="px-4 py-3 text-right font-bold text-indigo-600">{{ number_format($item->meters, 1) }} m</td>
            <td class="px-4 py-3 text-right text-slate-600">Rs {{ number_format($item->revenue) }}</td>
          </tr>
          @empty <tr><td colspan="3" class="p-4 text-center text-slate-500">No sales data</td></tr> @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Low Stock -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden col-span-1 lg:col-span-2">
    <div class="p-4 border-b border-slate-200"><h3 class="text-sm font-bold text-slate-900"><i class="fa-solid fa-triangle-exclamation text-red-500 mr-1"></i> Low Stock Alerts</h3></div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
          <tr><th class="px-4 py-2 text-left">Fabric / Product</th><th class="px-4 py-2 text-left">Category</th><th class="px-4 py-2 text-right">Current Stock</th><th class="px-4 py-2 text-right">Reorder Lvl</th><th class="px-4 py-2 text-center">Action</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($lowStock as $p)
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-semibold text-slate-900">{{ $p->name }}</td>
            <td class="px-4 py-3 text-slate-600">{{ $p->category->name ?? '-' }}</td>
            <td class="px-4 py-3 text-right font-bold text-red-500">{{ $p->stock_quantity }} {{ $p->unit }}</td>
            <td class="px-4 py-3 text-right text-slate-500">{{ $p->low_stock_threshold }} {{ $p->unit }}</td>
            <td class="px-4 py-3 text-center"><button class="bg-slate-900 text-white px-3 py-1 rounded text-xs font-medium hover:bg-slate-800" onclick="alert('Reorder feature coming soon!')">Reorder</button></td>
          </tr>
          @empty <tr><td colspan="5" class="p-4 text-center text-slate-500">Stock levels are healthy!</td></tr> @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Sales -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200"><h3 class="text-sm font-bold text-slate-900">Recent Sales (Invoices)</h3></div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
          <tr><th class="px-4 py-2 text-left">Invoice</th><th class="px-4 py-2 text-left">Customer</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2 text-right">Date</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($recentSales as $s)
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-semibold text-slate-900">{{ $s->invoice_number }}</td>
            <td class="px-4 py-3 text-slate-600">{{ $s->customer->name ?? 'Walk-in' }}</td>
            <td class="px-4 py-3 text-right font-bold text-slate-900">Rs {{ number_format($s->total_amount) }}</td>
            <td class="px-4 py-3 text-right text-xs text-slate-500">{{ $s->created_at->format('M d, H:i') }}</td>
          </tr>
          @empty <tr><td colspan="4" class="p-4 text-center text-slate-500">No recent sales</td></tr> @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Customers -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200"><h3 class="text-sm font-bold text-slate-900">Recent Customers</h3></div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
          <tr><th class="px-4 py-2 text-left">Name</th><th class="px-4 py-2 text-left">Phone</th><th class="px-4 py-2 text-right">Total Purchased</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($recentCustomers as $c)
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-semibold text-slate-900">{{ $c->name }}</td>
            <td class="px-4 py-3 text-slate-600">{{ $c->phone }}</td>
            <td class="px-4 py-3 text-right font-bold text-emerald-600">Rs {{ number_format($c->total_purchases) }}</td>
          </tr>
          @empty <tr><td colspan="3" class="p-4 text-center text-slate-500">No customers yet</td></tr> @endforelse
        </tbody>
      </table>
    </div>
  </div>



  <!-- Customer Dues -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200 flex justify-between items-center"><h3 class="text-sm font-bold text-slate-900">Customer Dues</h3> <span class="badge badge-progress">Rs {{ number_format($kpis['customer_dues']) }}</span></div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
          <tr><th class="px-4 py-2 text-left">Customer</th><th class="px-4 py-2 text-right">Outstanding Due</th><th class="px-4 py-2 text-center">Action</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($customerDuesList as $cd)
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-semibold text-slate-900">{{ $cd->name }}</td>
            <td class="px-4 py-3 text-right font-bold text-amber-600">Rs {{ number_format($cd->due_balance) }}</td>
            <td class="px-4 py-3 text-center"><button class="bg-slate-900 text-white px-3 py-1 rounded text-xs font-medium hover:bg-slate-800" onclick="alert('Collect feature coming soon!')">Collect</button></td>
          </tr>
          @empty <tr><td colspan="3" class="p-4 text-center text-slate-500">No outstanding customer dues</td></tr> @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  var chartsData = @json($charts);
  
  /* Quick Add Modal Overlay */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'quick-add': () => `
      <div class="p-6 border-b border-slate-200">
        <div class="flex justify-between items-center">
          <div><div class="text-lg font-bold text-slate-900 tracking-tight">Quick Action</div><div class="text-xs text-slate-500 mt-1">Manage shop operations</div></div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
      </div>
      <div class="p-6 grid grid-cols-2 gap-4">
        ${[
          { n: 'New Sale', i: 'fa-cart-shopping', c: 'indigo' },
          { n: 'Add Product', i: 'fa-box-open', c: 'sky' },
          { n: 'Add Customer', i: 'fa-user-plus', c: 'emerald' },
          { n: 'Receive Payment', i: 'fa-hand-holding-dollar', c: 'amber' },
          { n: 'Create Purchase', i: 'fa-truck', c: 'purple' },
          { n: 'Add Expense', i: 'fa-coins', c: 'rose' },
        ].map(o => `
          <button onclick="alert('Coming in next phase!')" class="p-5 border border-slate-200 rounded-xl flex flex-col items-center text-center hover:border-slate-900 hover:bg-slate-50 hover:shadow-sm transition-all group">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-3 bg-${o.c}-50 text-${o.c}-600 group-hover:scale-110 transition-transform"><i class="fa-solid ${o.i} text-lg"></i></div>
            <div class="text-sm font-semibold text-slate-800">${o.n}</div>
          </button>
        `).join('')}
      </div>
    `
  });

  Atelier.onPageReady(() => {
    // 1. Daily Sales Line Chart
    new Chart(document.getElementById('chart1'), {
      type: 'line',
      data: { labels: chartsData.daily_labels, datasets: [{ label: 'Sales (Rs)', data: chartsData.daily_sales, borderColor: '#4F46E5', backgroundColor: 'rgba(79, 70, 229, 0.1)', fill: true, tension: 0.4 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    // 2. Daily Meters Bar Chart
    new Chart(document.getElementById('chart2'), {
      type: 'bar',
      data: { labels: chartsData.daily_labels, datasets: [{ label: 'Meters Sold', data: chartsData.daily_meters, backgroundColor: '#38BDF8', borderRadius: 4 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    // 3. Monthly Revenue Bar Chart
    new Chart(document.getElementById('chart3'), {
      type: 'bar',
      data: { labels: chartsData.monthly_labels, datasets: [{ label: 'Revenue (Rs)', data: chartsData.monthly_revenue, backgroundColor: '#10B981', borderRadius: 4 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    // 4. Monthly Profit Line Chart
    new Chart(document.getElementById('chart4'), {
      type: 'line',
      data: { labels: chartsData.monthly_labels, datasets: [{ label: 'Profit (Rs)', data: chartsData.monthly_profit, borderColor: '#10B981', backgroundColor: 'rgba(16, 185, 129, 0.1)', fill: true, tension: 0.4 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    // 5. Sales by Category Doughnut
    new Chart(document.getElementById('chart5'), {
      type: 'doughnut',
      data: { labels: chartsData.cat_labels, datasets: [{ data: chartsData.cat_data, backgroundColor: ['#4F46E5', '#38BDF8', '#10B981', '#F59E0B', '#E11D48'], borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'right' } } }
    });

    // 6. Sales by Payment Method Pie
    new Chart(document.getElementById('chart6'), {
      type: 'pie',
      data: { labels: chartsData.pay_labels, datasets: [{ data: chartsData.pay_data, backgroundColor: ['#8B5CF6', '#14B8A6', '#F43F5E'], borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });
  });
</script>
@endpush
