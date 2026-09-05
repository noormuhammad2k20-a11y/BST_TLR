@php
  $user = auth()->user();
  $counters = $layoutCounters ?? ['customers' => 0, 'pending_orders' => 0, 'unread_notifications' => 0];

  /* One place for the nav item classes, so active/idle stay in step. */
  $nav = 'nav-item group mx-3 px-3 py-2.5 flex items-center gap-3 text-sm rounded-lg cursor-pointer';
@endphp

<style>
  /* ==========================================================================
     Sidebar icons — Lucide-style line set
     --------------------------------------------------------------------------
     The icons are inline SVG drawn with stroke="currentColor", so they inherit
     the sidebar theme colour and dark mode automatically. Weight and opacity
     are what carry the hierarchy: idle icons sit back at 1.75 / 80%, the active
     one steps forward to 2 / 100%. Nothing here uses colour to signal state,
     which is why it survives every sidebar theme.
     ========================================================================== */
  #sidebar .nav-ico {
    flex: 0 0 auto;
    width: 18px;
    height: 18px;
    opacity: .8;
    transition: opacity .18s ease, transform .18s ease, stroke-width .18s ease;
  }
  #sidebar .nav-item:hover .nav-ico { opacity: 1; transform: translateX(1px); }
  #sidebar .nav-item.active .nav-ico { opacity: 1; stroke-width: 2; }

  /* Menu / footer icons are one notch smaller and quieter. */
  #sidebar .menu-ico { flex: 0 0 auto; width: 16px; height: 16px; opacity: .75; }
  #sidebar a:hover .menu-ico, #sidebar button:hover .menu-ico { opacity: 1; }

  /* Replaces the old .fa-ellipsis-vertical hook in the theme stylesheet. */
  #sidebar .sb-more { color: var(--sb-muted, #94a3b8) !important; display: inline-flex; }
</style>

<aside id="sidebar" class="w-64 bg-white border-r border-slate-200 fixed top-0 bottom-0 left-0 z-40 flex flex-col">
  <div class="sb-head px-5 py-4 flex items-center gap-3 border-b border-slate-200 h-16">
    <div class="sb-logo w-9 h-9 rounded-xl flex items-center justify-center bg-slate-900 shadow-md">
      <x-icon name="tailoring" :size="18" :stroke="1.9" class="text-white" />
    </div>
    <div>
      <div class="sb-name text-base font-bold text-slate-900 leading-none tracking-tight" data-shop-name>{{ $appSettings['store_name'] ?: 'Atelier' }}</div>
      <div class="sb-sub text-[10px] tracking-[0.18em] uppercase mt-1 text-slate-400 font-semibold">Admin Suite</div>
    </div>
  </div>

  <div class="flex-1 overflow-y-auto py-4">

    <div class="sb-label px-6 mb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Main</div>

    <a href="{{ route('dashboard') }}" class="{{ $nav }} {{ request()->routeIs('dashboard') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="dashboard" /> Dashboard
    </a>
    <a href="{{ route('customers.index') }}" class="{{ $nav }} {{ request()->routeIs('customers.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="customers" /> Customers
      <span class="sb-badge ml-auto bg-slate-100 text-slate-600 text-[10px] font-bold px-1.5 py-0.5 rounded" data-badge="customers">{{ $counters['customers'] }}</span>
    </a>
    <a href="{{ route('orders.index') }}" class="{{ $nav }} {{ request()->routeIs('orders.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="tailoring" /> Orders
      <span class="ml-auto bg-red-50 text-red-600 text-[10px] font-bold px-1.5 py-0.5 rounded" data-badge="pending_orders">{{ $counters['pending_orders'] }}</span>
    </a>
    <a href="{{ route('measurements.index') }}" class="{{ $nav }} {{ request()->routeIs('measurements.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="measurements" /> Measurements
    </a>
    <a href="{{ route('products-services.index') }}" class="{{ $nav }} {{ request()->routeIs('products-services.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="services" /> Products &amp; Services
    </a>
    <a href="{{ route('staff.index') }}" class="{{ $nav }} {{ request()->routeIs('staff.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="team" /> Staff
    </a>
    <a href="{{ route('delivery.index') }}" class="{{ $nav }} {{ request()->routeIs('delivery.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="delivery" /> Delivery
    </a>

    {{-- More ▾ dropdown --}}
    @php
      $moreIsActive = request()->routeIs('payments-billing.*', 'expenses.*', 'reports.*', 'printing-center.*');
    @endphp
    <div class="mt-3" id="sb-more-dropdown">
      <button type="button" onclick="toggleSbMore()" class="{{ $nav }} w-full justify-between {{ $moreIsActive ? 'text-slate-900' : 'text-slate-600' }}">
        <span class="flex items-center gap-3">
          <x-icon name="more" /> More
        </span>
        <svg id="sb-more-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-200" style="flex:0 0 auto;opacity:.6;{{ $moreIsActive ? 'transform:rotate(180deg)' : '' }}"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div id="sb-more-panel" class="overflow-hidden transition-all duration-200 ease-in-out" style="{{ $moreIsActive ? '' : 'max-height:0;opacity:0;' }}">
        <a href="{{ route('payments-billing.index') }}" class="{{ $nav }} pl-6 {{ request()->routeIs('payments-billing.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="billing" /> Payments &amp; Billing
        </a>
        <a href="{{ route('expenses.index') }}" class="{{ $nav }} pl-6 {{ request()->routeIs('expenses.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="expenses" /> Expenses
        </a>
        <a href="{{ route('reports.index') }}" class="{{ $nav }} pl-6 {{ request()->routeIs('reports.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="reports" /> Reports
        </a>
        <a href="{{ route('printing-center.index') }}" class="{{ $nav }} pl-6 {{ request()->routeIs('printing-center.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="printing" /> Printing Center
        </a>
      </div>
    </div>

    {{-- Settings (standalone) --}}
    @if($user?->isAdmin())
    <div class="mt-3">
      <a href="{{ route('settings.index') }}" class="{{ $nav }} {{ request()->routeIs('settings.*') ? 'active text-slate-900' : 'text-slate-600' }}">
        <x-icon name="settings" /> Settings
      </a>
    </div>
    @endif
  </div>

  <!-- Quick Sidebar Palette -->
  <div class="px-3 pb-1 relative" id="sb-palette-wrap">
    <button onclick="toggleSidebarPalette(event)" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition-all" style="color: var(--sb-muted, #64748b);" title="Change sidebar color">
      <x-icon name="palette" :size="16" class="menu-ico" style="color: var(--sb-accent, #4F46E5); opacity:1;" />
      <span>Sidebar Color</span>
      <span class="ml-auto w-4 h-4 rounded-full shadow-sm" style="background: var(--sb-bg, #ffffff); border: 2px solid var(--sb-accent, #4F46E5);"></span>
    </button>
    <div id="sb-palette-popover">
      <div class="bg-white rounded-xl shadow-2xl p-3 w-56 border border-slate-200">
        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2.5 px-0.5">Sidebar Theme</div>
        <div class="grid grid-cols-4 gap-2" id="sb-swatches"></div>
      </div>
    </div>
  </div>

  <div class="sb-foot p-3 border-t border-slate-200 relative">
    <div id="sidebar-user-menu" class="hidden absolute bottom-full left-3 right-3 mb-2 bg-white border border-slate-200 rounded-lg shadow-lg overflow-hidden z-50">
      <a href="{{ route('profile.index') }}" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors">
        <x-icon name="profile" :size="16" class="menu-ico" /> My Profile
      </a>
      @if($user?->isAdmin())
      <a href="{{ route('settings.index') }}" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors">
        <x-icon name="settings" :size="16" class="menu-ico" /> Settings
      </a>
      @endif
      {{-- Shown only when the browser reports the app can be installed. --}}
      <button type="button" data-install-app class="hidden w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-indigo-600 hover:bg-indigo-50 transition-colors border-t border-slate-100" onclick="installApp()">
        <x-icon name="install" :size="16" class="menu-ico" /> Install App
      </button>

      <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100" id="logout-form">
        @csrf
        <button type="button" onclick="confirmLogout()" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
          <x-icon name="logout" :size="16" class="menu-ico" /> Sign Out
        </button>
      </form>
    </div>

    <a href="{{ route('profile.index') }}" class="sb-user-link flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 cursor-pointer">
      <div class="avatar sm bg-slate-900">{{ $user?->initials ?? 'AT' }}</div>
      <div class="flex-1 min-w-0">
        <div class="sb-uname text-sm font-semibold text-slate-900 truncate">{{ $user?->name ?? 'Guest' }}</div>
        <div class="sb-urole text-xs text-slate-500 truncate">{{ $user?->title ?: ($user?->role_label ?? 'Administrator') }}</div>
      </div>
      <span class="sb-more text-slate-400" onclick="event.preventDefault(); event.stopPropagation(); document.getElementById('sidebar-user-menu').classList.toggle('hidden');">
        <x-icon name="more" :size="16" class="menu-ico" />
      </span>
    </a>
  </div>
</aside>

<script>
  document.addEventListener('click', function (e) {
    const menu = document.getElementById('sidebar-user-menu');
    if (menu && !menu.classList.contains('hidden') && !menu.contains(e.target)) {
      menu.classList.add('hidden');
    }
  });

  /* Uses the shared confirmation dialog, matching the design's logout variant. */
  window.confirmLogout = function () {
    document.getElementById('sidebar-user-menu')?.classList.add('hidden');

    showConfirmation({
      variant: 'logout',
      title: 'End Current Session?',
      message: 'You will be immediately logged out. Please ensure all your work is saved.',
      confirmLabel: 'Logout Now',
      onConfirm: () => document.getElementById('logout-form').submit(),
    });
  };

  /* === More ▾ dropdown toggle === */
  window.toggleSbMore = function () {
    var panel = document.getElementById('sb-more-panel');
    var chevron = document.getElementById('sb-more-chevron');
    if (!panel) return;
    var isOpen = panel.style.maxHeight && panel.style.maxHeight !== '0px';
    if (isOpen) {
      panel.style.maxHeight = '0px';
      panel.style.opacity = '0';
      if (chevron) chevron.style.transform = 'rotate(0deg)';
    } else {
      panel.style.maxHeight = panel.scrollHeight + 'px';
      panel.style.opacity = '1';
      if (chevron) chevron.style.transform = 'rotate(180deg)';
    }
  };

  /* Auto-expand More panel on page load if a child route is active */
  document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('sb-more-panel');
    if (panel && panel.querySelector('.nav-item.active')) {
      panel.style.maxHeight = panel.scrollHeight + 'px';
      panel.style.opacity = '1';
      var chevron = document.getElementById('sb-more-chevron');
      if (chevron) chevron.style.transform = 'rotate(180deg)';
    }
  });

  /* === Quick Sidebar Palette === */
  window.toggleSidebarPalette = function(e) {
    e && e.stopPropagation();
    var pop = document.getElementById('sb-palette-popover');
    if (!pop) return;
    pop.classList.toggle('show');
    if (pop.classList.contains('show')) renderSbSwatches();
  };

  /* The tick inside a swatch is the same line icon used in the nav, drawn
     inline here because this markup is built by JS rather than Blade. */
  var SB_CHECK_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';

  function renderSbSwatches() {
    var c = document.getElementById('sb-swatches');
    if (!c || !window.SIDEBAR_THEMES) return;
    var cur = window.getActiveSidebarTheme();
    c.innerHTML = Object.entries(SIDEBAR_THEMES).map(function(e) {
      var k = e[0], t = e[1], on = k === cur;
      var chk = k === 'white' ? '#0f172a' : '#fff';
      var bdr = k === 'white' ? '#e2e8f0' : t.border;
      return '<button class="sb-swatch' + (on ? ' active' : '') + '" style="background:' + t.swatch + ';border-color:' + bdr + '" onclick="pickSbTheme(\'' + k + '\')" title="' + t.n + '"><span class="sb-check" style="color:' + chk + '">' + SB_CHECK_SVG + '</span></button>';
    }).join('');
  }

  window.pickSbTheme = function(k) {
    window.applySidebarTheme(k);
    renderSbSwatches();
    toast(((window.SIDEBAR_THEMES || {})[k]?.n || 'Theme') + ' applied!', 'success');
  };

  document.addEventListener('click', function(e) {
    var pop = document.getElementById('sb-palette-popover');
    var wrap = document.getElementById('sb-palette-wrap');
    if (pop && pop.classList.contains('show') && wrap && !wrap.contains(e.target)) {
      pop.classList.remove('show');
    }
  });
</script>
