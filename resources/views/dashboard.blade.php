@extends('layouts.app')
@section('title', 'Dashboard')
@section('spaPage', 'dashboard')

@php
  use App\Services\Money;

  $greeting = now()->hour < 12 ? 'Good Morning' : (now()->hour < 17 ? 'Good Afternoon' : 'Good Evening');
  $greetingIcon = now()->hour < 12 ? '☀️' : (now()->hour < 17 ? '🌤️' : '🌙');

  /** Renders the up/down trend line under a stat card from a real delta. */
  $trend = function (array $delta) {
      $up = $delta['direction'] === 'up';
      $flat = $delta['direction'] === 'flat';
      return [
          'class' => $flat ? 'text-slate-400' : ($up ? 'text-emerald-600' : 'text-red-500'),
          'icon'  => $flat ? 'fa-minus' : ($up ? 'fa-arrow-up' : 'fa-arrow-down'),
          'value' => number_format($delta['value'], 1) . '%',
      ];
  };
@endphp

@push('styles')
<style>
  /* ==========================================================
     STAT CARDS
     A soft tinted wash per metric so the four cards read apart
     at a glance, while staying pale enough that the figure
     itself is still the loudest thing on the card.
     ========================================================== */
  .stat-card {
    position: relative;
    padding: 22px;
    border-radius: 16px;
    border: 1.5px solid var(--sc-border);
    background: var(--sc-bg);
    /* Inner highlight lifts the tint off the page; the outer shadow is barely
       there, just enough to seat the card. */
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, .7),
      0 1px 2px rgba(15, 23, 42, .04),
      0 4px 12px -6px var(--sc-glow);
    transition: transform .3s cubic-bezier(.16,1,.3,1),
                box-shadow .3s ease,
                border-color .3s ease;
  }
  .stat-card:hover {
    transform: translateY(-3px);
    border-color: var(--sc-border-hover);
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, .7),
      0 2px 4px rgba(15, 23, 42, .05),
      0 16px 28px -12px var(--sc-glow);
  }

  .stat-card .stat-icon {
    width: 32px; height: 32px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: #fff;
    color: var(--sc-accent);
    border: 1px solid var(--sc-border);
    box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
  }

  .stat-card .stat-value {
    font-variant-numeric: tabular-nums;
    letter-spacing: -.02em;
  }
  .stat-card .stat-label { letter-spacing: .1em; }

  /* Trend line sits on a faint plate so it reads as a separate note. */
  .stat-card .stat-trend {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--sc-border);
  }

  .stat-card .stat-label { color: var(--sc-label); }
  .stat-card .stat-value { color: var(--sc-value); }

  /* ---- Per-metric palettes: soft fill, clearly defined edge ---- */
  .stat-indigo  { --sc-bg:#F2F4FF; --sc-border:#CDD4F5; --sc-border-hover:#A9B4EC; --sc-accent:#4F55BE; --sc-glow:rgba(79,85,190,.22);  --sc-label:#4F55BE; --sc-value:#24285F; }
  .stat-sky     { --sc-bg:#EEF8FD; --sc-border:#C4E2F2; --sc-border-hover:#95CBE7; --sc-accent:#2F7BA6; --sc-glow:rgba(47,123,166,.22); --sc-label:#2F7BA6; --sc-value:#0F3B54; }
  .stat-emerald { --sc-bg:#EEF8F3; --sc-border:#C6E6D7; --sc-border-hover:#98D2B8; --sc-accent:#348866; --sc-glow:rgba(52,136,102,.22); --sc-label:#348866; --sc-value:#123D2C; }
  .stat-rose    { --sc-bg:#FDF2F3; --sc-border:#F3D0D5; --sc-border-hover:#E5A8B1; --sc-accent:#B85E70; --sc-glow:rgba(184,94,112,.22); --sc-label:#B85E70; --sc-value:#63242F; }

  /* ---- Dark mode: same hues, dialled right down ---- */
  html.theme-dark .stat-card {
    box-shadow: inset 0 1px 0 rgba(255,255,255,.05), 0 2px 8px -4px rgba(0,0,0,.5);
  }
  html.theme-dark .stat-card:hover {
    box-shadow: inset 0 1px 0 rgba(255,255,255,.05), 0 16px 28px -14px rgba(0,0,0,.7);
  }
  html.theme-dark .stat-card .stat-icon {
    background: rgba(255,255,255,.09);
    border-color: rgba(255,255,255,.12);
    box-shadow: none;
  }

  html.theme-dark .stat-indigo  { --sc-bg:#1B1D3D; --sc-border:#383C7A; --sc-border-hover:#4B509B; --sc-accent:#A9ADEB; --sc-label:#A9ADEB; --sc-value:#EDEEFF; }
  html.theme-dark .stat-sky     { --sc-bg:#122B3A; --sc-border:#265771; --sc-border-hover:#347A96; --sc-accent:#8FC4E0; --sc-label:#8FC4E0; --sc-value:#E4F2FA; }
  html.theme-dark .stat-emerald { --sc-bg:#132E25; --sc-border:#275C49; --sc-border-hover:#347A60; --sc-accent:#8ACCB0; --sc-label:#8ACCB0; --sc-value:#E1F4EB; }
  html.theme-dark .stat-rose    { --sc-bg:#392026; --sc-border:#6B404C; --sc-border-hover:#8A5464; --sc-accent:#E5A6B1; --sc-label:#E5A6B1; --sc-value:#FBE7EA; }

  /* The dark-mode surface overrides in the layout would otherwise flatten
     these tints back to the standard card colour. */
  html.theme-dark .stat-card { background: var(--sc-bg) !important; }
  html.theme-dark .stat-card .stat-value { color: var(--sc-value) !important; }
  html.theme-dark .stat-card .stat-label { color: var(--sc-label) !important; }

  /* Compact tables implies a denser dashboard too. */
  html.compact-tables .stat-card { padding: 14px; }

  /* ==========================================================
     REVENUE CHART — Premium Dashboard Widget
     Clean, minimal glass-card with generous whitespace,
     hero metric, and an elegant area chart.
     ========================================================== */
  .rv {
    position: relative;
    border-radius: 20px;
    background: linear-gradient(168deg, #ffffff 0%, #FAFAFF 100%);
    border: 1px solid rgba(99, 102, 241, .1);
    box-shadow:
      0 0 0 1px rgba(15, 23, 42, .03),
      0 2px 4px rgba(15, 23, 42, .03),
      0 12px 40px -12px rgba(99, 102, 241, .10);
    padding: 28px 28px 20px;
    overflow: hidden;
    transition: box-shadow .4s ease, transform .4s cubic-bezier(.22,1,.36,1);
  }
  .rv:hover {
    box-shadow:
      0 0 0 1px rgba(99, 102, 241, .06),
      0 3px 6px rgba(15, 23, 42, .04),
      0 14px 44px -14px rgba(99, 102, 241, .12);
  }

  /* Top accent line */
  .rv::after {
    content: ''; position: absolute; top: 0; left: 28px; right: 28px; height: 2px;
    background: linear-gradient(90deg, transparent, #6366F1 30%, #A78BFA 70%, transparent);
    border-radius: 0 0 2px 2px; opacity: .55;
  }

  /* ---- Header row ---- */
  .rv-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }

  .rv-title-group { display: flex; flex-direction: column; gap: 2px; }
  .rv-eyebrow {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .12em; color: #6366F1;
  }
  .rv-eyebrow i { font-size: 9px; }
  .rv-subtitle { font-size: 12px; color: #94A3B8; font-weight: 500; margin-top: 1px; }

  /* Period switcher */
  .rv-periods {
    display: flex; gap: 2px;
    background: #F1F5F9; border-radius: 10px;
    padding: 3px; border: 1px solid #E8ECF4;
  }
  .rv-period {
    padding: 5px 16px; font-size: 11px; font-weight: 600;
    border-radius: 8px; border: none; cursor: pointer;
    color: #94A3B8; background: transparent;
    transition: all .2s ease;
  }
  .rv-period:hover:not(.on) { color: #64748B; background: rgba(255,255,255,.6); }
  .rv-period.on {
    color: #1E293B; background: #ffffff;
    box-shadow: 0 1px 3px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04);
  }

  /* ---- Hero metric row ---- */
  .rv-hero { display: flex; align-items: baseline; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
  .rv-amount {
    font-size: 32px; font-weight: 800; color: #0F172A;
    letter-spacing: -.03em; font-variant-numeric: tabular-nums;
    line-height: 1;
  }
  .rv-change {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 12px; font-weight: 700; padding: 3px 10px;
    border-radius: 20px;
  }
  .rv-change.up { color: #059669; background: rgba(16, 185, 129, .1); }
  .rv-change.down { color: #DC2626; background: rgba(239, 68, 68, .08); }
  .rv-change.flat { color: #64748B; background: rgba(100, 116, 139, .08); }
  .rv-change i { font-size: 8px; }
  .rv-period-label { font-size: 12px; color: #94A3B8; font-weight: 500; }

  /* ---- Mini KPI strip ---- */
  .rv-kpis {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 0; margin-bottom: 20px;
    border: 1px solid #F1F5F9; border-radius: 14px;
    overflow: hidden; background: #FAFBFD;
  }
  .rv-kpi {
    padding: 14px 18px;
    display: flex; flex-direction: column; gap: 3px;
    position: relative;
    transition: background .2s;
  }
  .rv-kpi:not(:last-child)::after {
    content: ''; position: absolute; right: 0; top: 20%; bottom: 20%;
    width: 1px; background: #EEF0F6;
  }
  .rv-kpi:hover { background: #F4F6FB; }
  .rv-kpi-label {
    font-size: 10px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .08em; color: #94A3B8;
  }
  .rv-kpi-val {
    font-size: 15px; font-weight: 700; color: #1E293B;
    font-variant-numeric: tabular-nums; letter-spacing: -.01em;
  }
  .rv-kpi-val.best { color: #059669; }
  .rv-kpi-val.today { color: #4F46E5; }

  /* ---- Chart area ---- */
  .rv-canvas-wrap { position: relative; height: 220px; margin: 0 -8px -4px; }

  /* ---- Dark mode ---- */
  html.theme-dark .rv {
    background: linear-gradient(168deg, #111827 0%, #0F1629 100%) !important;
    border-color: rgba(99, 102, 241, .12);
    box-shadow: 0 0 0 1px rgba(255,255,255,.03), 0 2px 4px rgba(0,0,0,.2), 0 12px 40px -12px rgba(0,0,0,.4);
  }
  html.theme-dark .rv:hover {
    box-shadow: 0 0 0 1px rgba(99,102,241,.12), 0 3px 6px rgba(0,0,0,.25), 0 14px 44px -14px rgba(99,102,241,.15);
  }
  html.theme-dark .rv::after {
    background: linear-gradient(90deg, transparent, #818CF8 30%, #C4B5FD 70%, transparent);
    opacity: .3;
  }
  html.theme-dark .rv-eyebrow { color: #A5B4FC; }
  html.theme-dark .rv-subtitle { color: #64748B; }
  html.theme-dark .rv-amount { color: #F1F5F9 !important; }
  html.theme-dark .rv-change.up { color: #34D399; background: rgba(52,211,153,.1); }
  html.theme-dark .rv-change.down { color: #F87171; background: rgba(248,113,113,.1); }
  html.theme-dark .rv-change.flat { color: #94A3B8; background: rgba(148,163,184,.1); }
  html.theme-dark .rv-period-label { color: #64748B; }
  html.theme-dark .rv-periods { background: #1E293B; border-color: #334155; }
  html.theme-dark .rv-period { color: #64748B; }
  html.theme-dark .rv-period:hover:not(.on) { color: #CBD5E1; background: rgba(255,255,255,.04); }
  html.theme-dark .rv-period.on { color: #F1F5F9; background: #334155; box-shadow: 0 1px 3px rgba(0,0,0,.3); }
  html.theme-dark .rv-kpis { background: #0D1424 !important; border-color: #1E293B !important; }
  html.theme-dark .rv-kpi:hover { background: #162032 !important; }
  html.theme-dark .rv-kpi:not(:last-child)::after { background: #1E293B; }
  html.theme-dark .rv-kpi-label { color: #64748B; }
  html.theme-dark .rv-kpi-val { color: #E2E8F0 !important; }
  html.theme-dark .rv-kpi-val.best { color: #34D399 !important; }
  html.theme-dark .rv-kpi-val.today { color: #A5B4FC !important; }
</style>
@endpush

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">{{ $greeting }}, {{ auth()->user()->short_name }} {{ $greetingIcon }}</h1>
    <p class="text-sm text-slate-500 mt-0.5">{{ now()->format('l, F j') }} · You have <span data-live="due_today_count">{{ $dueToday->count() }}</span> urgent deliveries today.</p>
  </div>
  <div class="flex gap-2">
    <button class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 hover:border-slate-300 flex items-center gap-2 transition-colors shadow-sm" onclick="exportDashboard(this)"><i class="fa-solid fa-download text-[10px]"></i> Export</button>
    <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="openModal('quick-add')"><i class="fa-solid fa-plus text-[10px]"></i> Quick Action</button>
  </div>
</div>

<!-- Compact Alert Section -->
<div class="page bg-white border border-slate-200 rounded-xl p-5 mb-6 shadow-sm relative overflow-hidden">
  <div class="absolute top-0 left-0 h-full w-1 bg-red-500"></div>
  <div class="flex items-center gap-3 mb-3">
    <div class="w-9 h-9 rounded-full bg-red-50 flex items-center justify-center pulse-dot">
      <i class="fa-solid fa-triangle-exclamation text-red-500 text-sm"></i>
    </div>
    <div class="flex-1">
      <div class="text-sm font-bold text-slate-900"><span data-live="due_today_count">{{ $dueToday->count() }}</span> Orders Due Today — Deliver Now!</div>
      <div class="text-xs text-slate-500">These orders need immediate attention</div>
    </div>
    <a href="{{ route('delivery.index') }}" class="bg-white border border-red-200 text-red-600 px-3 py-1.5 rounded-md text-xs font-semibold hover:bg-red-50 transition-colors">View All</a>
  </div>
  <div class="space-y-2" id="due-today-alert-list">
    @forelse($dueToday as $due)
    <div class="flex items-center gap-3 p-2.5 bg-slate-50 rounded-lg border border-slate-100">
      <span class="w-2 h-2 rounded-full bg-red-500 pulse-dot flex-shrink-0"></span>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold text-slate-900">{{ $due->customer->name ?? 'Unknown' }} <span class="text-xs text-slate-500 ml-1 font-normal">({{ $due->display_number }})</span></div>
        <div class="text-xs text-slate-500">{{ $due->primary_item_name }} · <span class="text-red-500 font-bold">Due Today</span></div>
      </div>
      <button class="bg-white border border-slate-200 text-slate-600 px-2.5 py-1 rounded text-xs font-medium hover:bg-slate-100 flex-shrink-0 transition-colors" onclick="remindCustomer(this, {{ $due->id }})">Remind Again</button>
    </div>
    @empty
    <div class="text-sm text-slate-500 p-2 text-center">No orders due today.</div>
    @endforelse
  </div>
</div>

<!-- Refined Stat Cards -->
@php
  $customerTrend = $trend($stats['trends']['customers']);
  $orderTrend    = $trend($stats['trends']['orders']);
  $revenueTrendD = $trend($stats['trends']['revenue']);
  $duesTrend     = $trend($stats['trends']['dues']);
@endphp
<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="stat-card stat-indigo">
    <div class="flex items-center justify-between mb-3">
      <span class="stat-label text-[10px] font-bold uppercase tracking-widest">Total Customers</span>
      <div class="stat-icon"><i class="fa-solid fa-users text-[11px]"></i></div>
    </div>
    <h3 class="stat-value text-2xl font-bold tracking-tight" data-countup="{{ $stats['total_customers'] }}" data-live="total_customers">0</h3>
    <p class="stat-trend text-[11px] text-slate-500 flex items-center gap-1"><span class="{{ $customerTrend['class'] }} font-semibold flex items-center gap-0.5"><i class="fa-solid {{ $customerTrend['icon'] }} text-[7px]"></i>{{ $customerTrend['value'] }}</span> vs last week</p>
  </div>
  <div class="stat-card stat-sky">
    <div class="flex items-center justify-between mb-3">
      <span class="stat-label text-[10px] font-bold uppercase tracking-widest">Active Orders</span>
      <div class="stat-icon"><i class="fa-solid fa-box text-[11px]"></i></div>
    </div>
    <h3 class="stat-value text-2xl font-bold tracking-tight" data-countup="{{ $stats['active_orders'] }}" data-live="active_orders">0</h3>
    <p class="stat-trend text-[11px] text-slate-500 flex items-center gap-1"><span class="{{ $orderTrend['class'] }} font-semibold flex items-center gap-0.5"><i class="fa-solid {{ $orderTrend['icon'] }} text-[7px]"></i>{{ $orderTrend['value'] }}</span> vs last week</p>
  </div>
  <div class="stat-card stat-emerald">
    <div class="flex items-center justify-between mb-3">
      <span class="stat-label text-[10px] font-bold uppercase tracking-widest">Revenue (Month)</span>
      <div class="stat-icon"><i class="fa-solid fa-rupee-sign text-[11px]"></i></div>
    </div>
    <h3 class="stat-value text-2xl font-bold tracking-tight"><span data-countup="{{ (int) $stats['revenue_month'] }}" data-prefix="{{ Money::symbol() }}" data-live="revenue_month">{{ Money::symbol() }}0</span></h3>
    <p class="stat-trend text-[11px] text-slate-500 flex items-center gap-1"><span class="{{ $revenueTrendD['class'] }} font-semibold flex items-center gap-0.5"><i class="fa-solid {{ $revenueTrendD['icon'] }} text-[7px]"></i>{{ $revenueTrendD['value'] }}</span> vs last month</p>
  </div>
  <div class="stat-card stat-rose">
    <div class="flex items-center justify-between mb-3">
      <span class="stat-label text-[10px] font-bold uppercase tracking-widest">Pending Dues</span>
      <div class="stat-icon"><i class="fa-solid fa-circle-exclamation text-[11px]"></i></div>
    </div>
    <h3 class="stat-value text-2xl font-bold tracking-tight"><span data-countup="{{ (int) $stats['pending_dues'] }}" data-prefix="{{ Money::symbol() }}" data-live="pending_dues">{{ Money::symbol() }}0</span></h3>
    <p class="stat-trend text-[11px] text-slate-500 flex items-center gap-1"><span class="{{ $duesTrend['class'] }} font-semibold flex items-center gap-0.5"><i class="fa-solid {{ $duesTrend['icon'] }} text-[7px]"></i>{{ $duesTrend['value'] }}</span> vs last week</p>
  </div>
</div>

<div class="page grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
  <div class="lg:col-span-2 rv">
    {{-- Header --}}
    <div class="rv-head">
      <div class="rv-title-group">
        <span class="rv-eyebrow"><i class="fa-solid fa-chart-area"></i> Revenue Trend</span>
        <span class="rv-subtitle">Daily revenue · last <span id="rv-range-label">30</span> days</span>
      </div>
      <div class="rv-periods" id="revenue-range-tabs">
        <button data-range="7" class="rv-period">7 D</button>
        <button data-range="30" class="rv-period on">30 D</button>
        <button data-range="365" class="rv-period">1 Y</button>
      </div>
    </div>

    {{-- Hero metric --}}
    <div class="rv-hero">
      <span class="rv-amount" id="rv-total">—</span>
      <span class="rv-change flat" id="rv-change"><i class="fa-solid fa-minus"></i> 0%</span>
      <span class="rv-period-label">vs previous period</span>
    </div>

    {{-- KPI strip --}}
    <div class="rv-kpis">
      <div class="rv-kpi">
        <span class="rv-kpi-label">Daily Avg</span>
        <span class="rv-kpi-val" id="rv-avg">—</span>
      </div>
      <div class="rv-kpi">
        <span class="rv-kpi-label">Peak Day</span>
        <span class="rv-kpi-val best" id="rv-best">—</span>
      </div>
      <div class="rv-kpi">
        <span class="rv-kpi-label">Today</span>
        <span class="rv-kpi-val today" id="rv-today">—</span>
      </div>
    </div>

    {{-- Chart --}}
    <div class="rv-canvas-wrap">
      <canvas id="line-chart"></canvas>
    </div>
  </div>

  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col">
    <div class="flex justify-between items-center mb-4">
      <div>
        <h3 class="text-base font-semibold text-slate-900 tracking-tight">Live Activity Feed</h3>
        <p class="text-xs text-slate-500">Real-time system events</p>
      </div>
      <span class="w-2 h-2 rounded-full bg-emerald-500 pulse-dot"></span>
    </div>
    <div class="space-y-1 flex-1 overflow-y-auto pr-1" id="activity-feed"></div>
  </div>
</div>

<div class="page grid grid-cols-1 lg:grid-cols-3 gap-5">
  <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center">
      <div>
        <h3 class="text-base font-semibold text-slate-900 tracking-tight">Recent Orders</h3>
        <p class="text-xs text-slate-500">Latest {{ $recentOrders->count() ?: 5 }} orders in the system</p>
      </div>
      <a href="{{ route('orders.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline">View All</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
          <tr>
            <th class="px-5 py-3 text-left font-bold">Order ID</th>
            <th class="px-5 py-3 text-left font-bold">Customer</th>
            <th class="px-5 py-3 text-left font-bold">Garment</th>
            <th class="px-5 py-3 text-left font-bold">Amount</th>
            <th class="px-5 py-3 text-left font-bold">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($recentOrders as $index => $order)
          @php
            $colors = ['slate', 'pink', 'green', 'orange', 'blue', 'purple'];
            $color = $colors[$index % count($colors)];
            $badgeColor = match($order->status) {
                'Received', 'Pending' => 'badge-pending',
                'Stitching' => 'badge-progress',
                'Trial' => 'badge-trial',
                'Ready' => 'badge-ready',
                'Delivered', 'Completed' => 'badge-delivered',
                default => 'badge-overdue'
            };
          @endphp
          <tr class="hover:bg-slate-50 cursor-pointer transition-colors {{ $order->status === 'Ready' ? 'border-l-4 border-l-red-500' : '' }}" onclick="openModal('order-details', orders[{{ $index }}])">
            <td class="px-5 py-3 font-semibold text-slate-900">{{ $order->display_number }}</td>
            <td class="px-5 py-3">
              <div class="flex items-center gap-2">
                <div class="avatar sm {{ $color }}">{{ $order->customer?->initials ?? '?' }}</div>
                <span class="font-medium text-slate-600">{{ $order->customer->name ?? 'Unknown' }}</span>
              </div>
            </td>
            <td class="px-5 py-3 text-slate-600">{{ $order->primary_item_name }}</td>
            <td class="px-5 py-3 font-semibold text-slate-900">{{ Money::format($order->total) }}</td>
            <td class="px-5 py-3"><span class="badge {{ $badgeColor }}">{{ $order->status }}</span></td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="px-5 py-12 text-center">
              <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-scissors text-base"></i></div>
              <div class="text-sm font-semibold text-slate-700">No orders yet</div>
              <div class="text-xs text-slate-500 mt-1">Create your first order to see it here</div>
              <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-2 mt-4 bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 transition-colors shadow-sm"><i class="fa-solid fa-plus text-[10px]"></i> Create Order</a>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
    <div class="mb-4">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">Today's Snapshot</h3>
      <p class="text-xs text-slate-500">Daily alerts</p>
    </div>

    <div>
      <div class="text-[10px] font-bold text-red-500 uppercase mb-3 flex items-center gap-2 tracking-widest"><i class="fa-solid fa-bell text-xs"></i> Due Today</div>
      <div class="space-y-2">
        @forelse($dueToday as $due)
        <div class="p-3 rounded-lg bg-red-50 border border-red-100 flex items-center gap-3">
          <span class="w-2 h-2 rounded-full bg-red-500 pulse-dot flex-shrink-0"></span>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-slate-900">{{ $due->customer->name ?? 'Unknown' }} <span class="text-xs text-slate-500 ml-1 font-normal">({{ $due->display_number }})</span></div>
            <div class="text-xs text-slate-500">{{ $due->primary_item_name }} · <span class="text-red-500 font-bold">Due Today</span></div>
          </div>
        </div>
        @empty
        <div class="text-sm text-slate-500 text-center">All caught up!</div>
        @endforelse
      </div>
    </div>
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
@endsection

@push('scripts')
<script>
  /* `var` throughout: the SPA router re-evaluates this script per navigation. */
  var orders = @json($ordersJs);
  var activityFeed = @json($activityFeed);
  var revenueSeries = @json($revenueTrend);
  var revenueChart = null;
  var currentRange = 30;

  /* ------------------------------ Modals ---------------------------- */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'quick-add': () => `
      <div class="p-6 border-b border-slate-200">
        <div class="flex justify-between items-center">
          <div><div class="text-lg font-bold text-slate-900 tracking-tight">Quick Action</div><div class="text-xs text-slate-500 mt-1">Choose what you want to create</div></div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
      </div>
      <div class="p-6">
        <div class="grid grid-cols-2 gap-4">
          ${[
            { n: 'New Order',     i: 'fa-scissors',      c: 'indigo',  a: `closeModal(); window.location.href='{{ route('orders.index') }}?action=create'` },
            { n: 'New Customer',  i: 'fa-user-plus',     c: 'sky',     a: "closeModal();openDrawer('add-customer-drawer')" },
            { n: 'New Invoice',   i: 'fa-file-invoice',  c: 'red',     a: `closeModal(); window.location.href='{{ route('payments-billing.index') }}'` },
            { n: 'Measurement',   i: 'fa-ruler',         c: 'purple',  a: `closeModal(); window.location.href='{{ route('measurements.index') }}?action=create'` },
            { n: 'Log Expense',   i: 'fa-coins',         c: 'amber',   a: `closeModal(); window.location.href='{{ route('expenses.index') }}?action=create'` },
            { n: 'Print Receipt', i: 'fa-print',         c: 'emerald', a: `closeModal(); window.location.href='{{ route('printing-center.index') }}'` },
          ].map(o => `
            <button onclick="${o.a}" class="p-5 border border-slate-200 rounded-xl flex flex-col items-center text-center hover:border-slate-900 hover:bg-slate-50 hover:shadow-sm transition-all group">
              <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-3 bg-${o.c}-50 text-${o.c}-600 group-hover:scale-110 transition-transform"><i class="fa-solid ${o.i} text-lg"></i></div>
              <div class="text-sm font-semibold text-slate-800">${o.n}</div>
            </button>
          `).join('')}
        </div>
      </div>
    `,
    'order-details': (d) => `
      <div class="p-5 border-b border-slate-200">
        <div class="flex justify-between items-center">
          <div class="text-lg font-bold text-slate-900">Order ${d.id}</div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
      </div>
      <div class="p-6 text-sm text-slate-600">
        <p><strong>Customer:</strong> ${Atelier.escapeHtml(d.customer)}</p>
        <p><strong>Garment:</strong> ${Atelier.escapeHtml(d.garment)}</p>
        <p><strong>Amount:</strong> ${Atelier.money(d.amount)}</p>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Close</button>
        <a href="{{ route('orders.index') }}?highlight=${d.db_id}" class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm">Open in Orders</a>
      </div>
    `
  });

  /* ------------------------------ Actions --------------------------- */
  window.remindCustomer = async function (btn, orderId) {
    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.post(`/orders/${orderId}/notify`);
      toast(res.message || 'Reminder sent', 'success');
      Atelier.refreshCounters();
    } catch (err) {
      Atelier.reportError(err, 'Could not send the reminder');
    } finally {
      Atelier.setBusy(btn, false);
    }
  };

  window.exportDashboard = function (btn) {
    const rows = [['Metric', 'Value']];
    document.querySelectorAll('[data-live]').forEach(el => {
      const label = el.closest('.bg-white')?.querySelector('.uppercase')?.textContent?.trim();
      if (label) rows.push([label, el.textContent.trim()]);
    });

    if (rows.length === 1) { toast('Nothing to export yet', 'info'); return; }

    const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    const link = Object.assign(document.createElement('a'), {
      href: url, download: `dashboard-${new Date().toISOString().slice(0, 10)}.csv`
    });
    link.click();
    URL.revokeObjectURL(url);
    toast('Dashboard summary exported', 'success');
  };

  /* ------------------------------ Render ---------------------------- */
  function renderActivityFeed() {
    const container = document.getElementById('activity-feed');
    if (!container) return;

    if (!activityFeed.length) {
      container.innerHTML = Atelier.emptyState({
        icon: 'fa-wave-square',
        title: 'No activity yet',
        message: 'Actions across the system will stream in here as they happen.'
      });
      return;
    }

    container.innerHTML = activityFeed.map(a => `
      <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-slate-50 transition">
        <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background:${a.color}"></div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium text-slate-900 truncate">${Atelier.escapeHtml(a.title)}</div>
          <div class="text-xs text-slate-500 truncate">${Atelier.escapeHtml(a.desc)}</div>
          <div class="text-[10px] text-slate-400 mt-0.5">${a.time}</div>
        </div>
      </div>
    `).join('');
  }

  /* ---- Revenue chart helpers ---- */
  function rvSummary() {
    const d = revenueSeries.data || [];
    if (!d.length) return;
    const total = d.reduce((s, v) => s + v, 0);
    const avg   = Math.round(total / d.length);
    const best  = Math.max(...d);
    const today = d[d.length - 1] || 0;
    const $ = id => document.getElementById(id);
    if ($('rv-total')) $('rv-total').textContent = Atelier.money(total);
    if ($('rv-avg'))   $('rv-avg').textContent   = Atelier.money(avg);
    if ($('rv-best'))  $('rv-best').textContent   = Atelier.money(best);
    if ($('rv-today')) $('rv-today').textContent  = Atelier.money(today);

    /* Trend badge — compare first half vs second half of the series */
    const mid = Math.floor(d.length / 2) || 1;
    const firstHalf  = d.slice(0, mid).reduce((s, v) => s + v, 0);
    const secondHalf = d.slice(mid).reduce((s, v) => s + v, 0);
    const pct = firstHalf ? (((secondHalf - firstHalf) / firstHalf) * 100) : 0;
    const badge = $('rv-change');
    if (badge) {
      const dir = pct > 0.5 ? 'up' : pct < -0.5 ? 'down' : 'flat';
      const icon = dir === 'up' ? 'fa-arrow-trend-up' : dir === 'down' ? 'fa-arrow-trend-down' : 'fa-minus';
      badge.className = 'rv-change ' + dir;
      badge.innerHTML = `<i class="fa-solid ${icon}"></i> ${Math.abs(pct).toFixed(1)}%`;
    }
  }

  /* Bar-glow plugin — soft radial glow behind the hovered bar */
  const barGlowPlugin = {
    id: 'barGlow',
    afterDatasetsDraw(chart) {
      const active = chart.getActiveElements();
      if (!active || !active.length) return;
      const { ctx: c } = chart;
      const meta = chart.getDatasetMeta(0);
      const bar  = meta.data[active[0].index];
      if (!bar) return;
      const dark = document.documentElement.classList.contains('theme-dark');
      c.save();
      const glow = c.createRadialGradient(bar.x, bar.y, 0, bar.x, bar.y, bar.width * 3.5);
      glow.addColorStop(0, dark ? 'rgba(129,140,248,.18)' : 'rgba(99,102,241,.14)');
      glow.addColorStop(1, 'transparent');
      c.fillStyle = glow;
      c.fillRect(bar.x - bar.width * 3, chart.chartArea.top, bar.width * 6, chart.chartArea.bottom - chart.chartArea.top);
      c.restore();
    }
  };

  function buildBarGradient(ctx, h, dark) {
    const g = ctx.createLinearGradient(0, 0, 0, h);
    if (dark) {
      g.addColorStop(0, '#93A3F8');
      g.addColorStop(0.6, '#6366F1');
      g.addColorStop(1, '#4F46E5');
    } else {
      g.addColorStop(0, '#93A3F8');
      g.addColorStop(0.5, '#6366F1');
      g.addColorStop(1, '#4338CA');
    }
    return g;
  }

  function buildTodayGradient(ctx, h, dark) {
    const g = ctx.createLinearGradient(0, 0, 0, h);
    if (dark) {
      g.addColorStop(0, '#C4B5FD');
      g.addColorStop(0.5, '#A78BFA');
      g.addColorStop(1, '#7C3AED');
    } else {
      g.addColorStop(0, '#B4A3FB');
      g.addColorStop(0.5, '#8B5CF6');
      g.addColorStop(1, '#6D28D9');
    }
    return g;
  }

  function renderChart() {
    const el = document.getElementById('line-chart');
    if (!el) return;

    rvSummary();

    const dark = document.documentElement.classList.contains('theme-dark');
    const d = revenueSeries.data || [];
    const last = d.length - 1;
    const h = el.parentElement.offsetHeight || 220;
    const ctx2d = el.getContext('2d');

    /* Per-bar gradient: today gets a brighter violet, rest get indigo→violet */
    const barGrad   = buildBarGradient(ctx2d, h, dark);
    const todayGrad = buildTodayGradient(ctx2d, h, dark);
    const bgColors  = d.map((_, i) => i === last ? todayGrad : barGrad);

    /* Per-bar hover: brighten all on hover */
    const hoverBg = d.map((_, i) => {
      const hg = ctx2d.createLinearGradient(0, 0, 0, h);
      if (i === last) {
        hg.addColorStop(0, dark ? '#DDD6FE' : '#C4B5FD');
        hg.addColorStop(1, dark ? '#A78BFA' : '#7C3AED');
      } else {
        hg.addColorStop(0, dark ? '#C7D2FE' : '#A5B4FC');
        hg.addColorStop(1, dark ? '#818CF8' : '#6366F1');
      }
      return hg;
    });

    const dataset = {
      label: 'Revenue',
      data: d,
      backgroundColor: bgColors,
      hoverBackgroundColor: hoverBg,
      borderColor: 'transparent',
      borderWidth: 0,
      borderRadius: { topLeft: 8, topRight: 8, bottomLeft: 0, bottomRight: 0 },
      borderSkipped: 'bottom',
      barPercentage: 0.62,
      categoryPercentage: 0.74,
    };

    const chartData = { labels: revenueSeries.labels, datasets: [dataset] };

    if (revenueChart) {
      /* Rebuild gradients for updated data */
      const freshBar   = buildBarGradient(ctx2d, revenueChart.chartArea?.height || h, dark);
      const freshToday = buildTodayGradient(ctx2d, revenueChart.chartArea?.height || h, dark);
      chartData.datasets[0].backgroundColor = d.map((_, i) => i === (d.length - 1) ? freshToday : freshBar);
      revenueChart.data = chartData;
      revenueChart.update('none');
      return;
    }

    revenueChart = new Chart(ctx2d, {
      type: 'bar',
      data: chartData,
      plugins: [barGlowPlugin],
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 700, easing: 'easeOutQuart' },
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: true,
            backgroundColor: dark ? 'rgba(30,41,59,.96)' : 'rgba(15,23,42,.92)',
            titleColor: '#F8FAFC',
            bodyColor: '#E2E8F0',
            titleFont: { family: 'Inter', weight: '600', size: 11 },
            bodyFont: { family: 'Inter', weight: '800', size: 16 },
            padding: { top: 10, bottom: 10, left: 16, right: 16 },
            cornerRadius: 10,
            borderColor: dark ? 'rgba(99,102,241,.15)' : 'rgba(99,102,241,.1)',
            borderWidth: 1,
            displayColors: false,
            caretSize: 5,
            caretPadding: 6,
            callbacks: {
              title: items => items[0]?.label || '',
              label: c => Atelier.money(c.parsed.y),
            },
          },
        },
        scales: {
          y: {
            display: true, position: 'right',
            border: { display: false },
            grid: {
              color: dark ? 'rgba(148,163,184,.06)' : 'rgba(148,163,184,.08)',
              drawTicks: false, lineWidth: 1,
            },
            ticks: {
              maxTicksLimit: 5, padding: 14,
              font: { family: 'Inter', size: 10, weight: '500' },
              color: dark ? '#475569' : '#CBD5E1',
              callback: v => Atelier.money(v),
            },
            beginAtZero: true,
          },
          x: {
            border: { display: false },
            grid: { display: false },
            ticks: {
              maxTicksLimit: 10, padding: 8,
              font: { family: 'Inter', size: 9, weight: '500' },
              color: dark ? '#475569' : '#CBD5E1',
              maxRotation: 0,
            },
          },
        },
      },
    });
  }

  function applyStats(stats) {
    const map = {
      total_customers: v => v,
      active_orders: v => v,
      revenue_month: v => Atelier.money(v),
      pending_dues: v => Atelier.money(v),
      due_today_count: v => v,
    };

    Object.entries(map).forEach(([key, fmt]) => {
      document.querySelectorAll(`[data-live="${key}"]`).forEach(el => {
        el.textContent = fmt(stats[key]);
      });
    });
  }

  /* --------------------------- Bootstrapping ------------------------ */
  Atelier.onPageReady(() => {
    document.querySelectorAll('[data-countup]').forEach(el => {
      animateCountUp(el, parseInt(el.getAttribute('data-countup')), el.getAttribute('data-prefix') || '');
    });

    renderActivityFeed();
    renderChart();

    // New customer — saves, then opens a new order for them.
    Atelier.ajaxForm('#add-customer-form', {
      onSuccess: (res) => {
        closeDrawers();
        toast(res.message, 'success');
        Atelier.refreshCounters();

        setTimeout(() => {
          window.location.href = `{{ route('orders.index') }}?action=create&customer=${res.customer.db_id}&new_customer=1`;
        }, 650);
      }
    });

    document.querySelectorAll('#revenue-range-tabs button').forEach(btn => {
      btn.addEventListener('click', async () => {
        document.querySelectorAll('#revenue-range-tabs button').forEach(b => b.classList.remove('on'));
        btn.classList.add('on');

        currentRange = parseInt(btn.dataset.range, 10);
        const lbl = document.getElementById('rv-range-label');
        if (lbl) lbl.textContent = currentRange === 365 ? '365' : currentRange;

        try {
          const data = await Atelier.api.get(`{{ route('live.dashboard') }}?days=${currentRange}`);
          revenueSeries = data.revenue;
          /* Destroy + rebuild so gradient recalculates to new canvas height */
          if (revenueChart) { revenueChart.destroy(); revenueChart = null; }
          renderChart();
        } catch (err) {
          Atelier.reportError(err, 'Could not load the revenue trend');
        }
      });
    });

    // Live refresh: counters, activity feed and chart stay current.
    Atelier.poll(async () => {
      const data = await Atelier.api.get(@json(route('live.dashboard')));
      applyStats(data.stats);
      activityFeed = data.activity;
      renderActivityFeed();
      revenueSeries = data.revenue;
      renderChart();
    }, 30000);
  });
</script>
@endpush
