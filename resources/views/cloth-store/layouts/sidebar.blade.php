@php
  $user = auth()->user();
  $counters = $layoutCounters ?? ['customers' => 0, 'pending_orders' => 0, 'unread_notifications' => 0];

  /* One place for the nav item classes, so active/idle stay in step. */
  $nav = 'nav-item group mx-3 px-3 py-2.5 flex items-center gap-3 text-sm rounded-lg cursor-pointer';

  /* Detect if a child inside a dropdown is active, so we auto-open it. */
  $moreActive = request()->routeIs('cloth-store.returns.*')
             || request()->routeIs('cloth-store.expenses.*')
             || request()->routeIs('cloth-store.stock.alerts*')
             || request()->routeIs('cloth-store.categories.*');

  $toolsActive = request()->routeIs('cloth-store.discounts.*')
              || request()->routeIs('cloth-store.reports.*')
              || request()->routeIs('cloth-store.settings.*');
@endphp

<style>
  /* ==========================================================================
     Sidebar icons — Lucide-style line set
     --------------------------------------------------------------------------
     The icons are inline SVG drawn with stroke="currentColor", so they inherit
     the sidebar theme colour and dark mode automatically. Weight and opacity
     are what carry the hierarchy: idle icons sit back at 1.75 / 85%, the active
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

  /* ==========================================================================
     Sidebar Dropdown — "More" & "Tools & Settings"
     --------------------------------------------------------------------------
     Pure CSS height transition via max-height + overflow. The toggle button
     inherits the same .nav-item base so it sits visually in step with the
     rest of the nav. The chevron rotates when the section is open.
     ========================================================================== */
  #sidebar .sb-dropdown-toggle {
    width: calc(100% - 1.5rem);
    margin-left: .75rem;
    margin-right: .75rem;
    padding: .625rem .75rem;
    display: flex;
    align-items: center;
    gap: .75rem;
    font-size: .875rem;
    border-radius: .5rem;
    cursor: pointer;
    color: var(--sb-muted, #64748b);
    background: transparent;
    border: none;
    transition: background-color .18s ease, color .18s ease;
    position: relative;
  }
  #sidebar .sb-dropdown-toggle:hover {
    background-color: var(--sb-hover, #f8fafc);
    color: var(--sb-text, #0f172a);
  }
  #sidebar .sb-dropdown-toggle.has-active {
    color: var(--sb-text, #0f172a);
    font-weight: 600;
  }

  #sidebar .sb-dropdown-chevron {
    margin-left: auto;
    width: 14px;
    height: 14px;
    opacity: .55;
    transition: transform .25s cubic-bezier(.4,0,.2,1), opacity .18s ease;
    flex-shrink: 0;
  }
  #sidebar .sb-dropdown-toggle:hover .sb-dropdown-chevron { opacity: .85; }
  #sidebar .sb-dropdown-toggle[aria-expanded="true"] .sb-dropdown-chevron {
    transform: rotate(180deg);
    opacity: .85;
  }

  #sidebar .sb-dropdown-body {
    max-height: 0;
    overflow: hidden;
    transition: max-height .3s cubic-bezier(.4,0,.2,1), opacity .25s ease;
    opacity: 0;
  }
  #sidebar .sb-dropdown-body.open {
    max-height: 400px;  /* generous ceiling; real content is ~200px */
    opacity: 1;
  }

  /* Dot indicator on the toggle when a child inside is active but the
     dropdown is collapsed. Gives the user a hint "active page is in here". */
  #sidebar .sb-dropdown-toggle .sb-child-active-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--sb-fill, #4F46E5);
    flex-shrink: 0;
    margin-left: .25rem;
    transition: opacity .2s ease;
  }
  #sidebar .sb-dropdown-toggle[aria-expanded="true"] .sb-child-active-dot {
    opacity: 0;
  }
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

    {{-- ── Main Daily-Use ─────────────────────────────────────── --}}
    <div class="sb-label px-6 mb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Main</div>

    <a href="{{ route('cloth-store.dashboard') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.dashboard') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="dashboard" /> Dashboard
    </a>
    <a href="{{ route('cloth-store.checkout.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.checkout.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="checkout" /> Smart Checkout
    </a>
    <a href="{{ route('cloth-store.orders.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.orders.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="orders" /> Orders
    </a>
    <a href="{{ route('cloth-store.products.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.products.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="products" /> Products
    </a>
    <a href="{{ route('cloth-store.stock.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.stock.index') || request()->routeIs('cloth-store.stock.history') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="stock" /> Stock Management
    </a>
    <a href="{{ route('cloth-store.customers.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.customers.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="customers" /> Customers
    </a>
    <a href="{{ route('cloth-store.payments.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.payments.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="payments" /> Payments
    </a>

    {{-- ── More dropdown ──────────────────────────────────────── --}}
    <div class="mt-4">
      <button
        type="button"
        class="sb-dropdown-toggle {{ $moreActive ? 'has-active' : '' }}"
        aria-expanded="{{ $moreActive ? 'true' : 'false' }}"
        onclick="toggleSbDropdown(this)"
      >
        <svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
        </svg>
        <span>More</span>
        @if($moreActive)
          <span class="sb-child-active-dot"></span>
        @endif
        <svg class="sb-dropdown-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m6 9 6 6 6-6"/>
        </svg>
      </button>

      <div class="sb-dropdown-body {{ $moreActive ? 'open' : '' }}">
        <a href="{{ route('cloth-store.returns.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.returns.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="returns" /> Returns &amp; Exchanges
        </a>
        <a href="{{ route('cloth-store.expenses.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.expenses.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="expenses" /> Expenses
        </a>
        <a href="{{ route('cloth-store.stock.alerts') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.stock.alerts*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="alert" /> Low Stock Alerts
          <span class="sb-badge ml-auto bg-red-50 text-red-600 text-[10px] font-bold px-1.5 py-0.5 rounded">4</span>
        </a>
        <a href="{{ route('cloth-store.categories.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.categories.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="categories" /> Categories
        </a>
      </div>
    </div>

    {{-- ── Tools & Settings dropdown ──────────────────────────── --}}
    <div class="mt-2">
      <button
        type="button"
        class="sb-dropdown-toggle {{ $toolsActive ? 'has-active' : '' }}"
        aria-expanded="{{ $toolsActive ? 'true' : 'false' }}"
        onclick="toggleSbDropdown(this)"
      >
        <svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
        </svg>
        <span>Tools &amp; Settings</span>
        @if($toolsActive)
          <span class="sb-child-active-dot"></span>
        @endif
        <svg class="sb-dropdown-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m6 9 6 6 6-6"/>
        </svg>
      </button>

      <div class="sb-dropdown-body {{ $toolsActive ? 'open' : '' }}">
        <a href="{{ route('cloth-store.discounts.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.discounts.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="discounts" /> Discounts &amp; Offers
        </a>
        <a href="{{ route('cloth-store.reports.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.reports.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="reports" /> Reports &amp; Analytics
        </a>
        @if($user?->isAdmin())
        <a href="{{ route('cloth-store.settings.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.settings.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="settings" /> Settings
        </a>
        @endif
      </div>
    </div>

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
      @if($user?->isAdmin())
      {{-- Cloth Store settings, matching the System link in the nav above.
           This pointed at the Tailor system's settings page. --}}
      <a href="{{ route('cloth-store.settings.index') }}" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors">
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
  /* === Sidebar Dropdown Toggle ===
     Smoothly opens/closes the "More" and "Tools & Settings" sections.
     Uses max-height CSS transition — no JS animation frames needed. */
  window.toggleSbDropdown = function(btn) {
    var expanded = btn.getAttribute('aria-expanded') === 'true';
    var body = btn.nextElementSibling;
    if (!body) return;

    if (expanded) {
      btn.setAttribute('aria-expanded', 'false');
      body.classList.remove('open');
    } else {
      btn.setAttribute('aria-expanded', 'true');
      body.classList.add('open');
    }
  };

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
