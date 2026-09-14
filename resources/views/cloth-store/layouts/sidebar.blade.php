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

  /* ==========================================================================
     Collapse Button Fix
     --------------------------------------------------------------------------
     Explicit rigid sizes to prevent the icon from expanding into the dashboard.
     ========================================================================== */
  .sidebar-collapse-button svg {
    width: 20px !important;
    height: 20px !important;
    min-width: 20px !important;
    max-width: 20px !important;
    min-height: 20px !important;
    max-height: 20px !important;
    flex: 0 0 20px !important;
  }
  
  .sidebar-collapse-wrapper {
    overflow: hidden;
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

  <div class="flex-1 overflow-y-auto overflow-x-hidden py-4">

    {{-- ── Main Daily-Use ─────────────────────────────────────── --}}
    <div class="sb-label px-6 mb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Main</div>

    <a href="{{ route('cloth-store.dashboard') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.dashboard') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="dashboard" /> <span class="sb-text">Dashboard</span>
    </a>
    <a href="{{ route('cloth-store.checkout.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.checkout.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="checkout" /> <span class="sb-text">Smart Checkout</span>
    </a>
    <a href="{{ route('cloth-store.orders.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.orders.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="orders" /> <span class="sb-text">Orders</span>
    </a>
    <a href="{{ route('cloth-store.products.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.products.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="products" /> <span class="sb-text">Products</span>
    </a>
    <a href="{{ route('cloth-store.stock.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.stock.index') || request()->routeIs('cloth-store.stock.history') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="stock" /> <span class="sb-text">Stock Management</span>
    </a>
    <a href="{{ route('cloth-store.customers.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.customers.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="customers" /> <span class="sb-text">Customers</span>
    </a>
    <a href="{{ route('cloth-store.payments.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.payments.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="payments" /> <span class="sb-text">Payments</span>
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
        <span class="sb-text">More</span>
        @if($moreActive)
          <span class="sb-child-active-dot"></span>
        @endif
        <svg class="sb-dropdown-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m6 9 6 6 6-6"/>
        </svg>
      </button>

      <div class="sb-dropdown-body {{ $moreActive ? 'open' : '' }}">
        <a href="{{ route('cloth-store.returns.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.returns.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="returns" /> <span class="sb-text">Returns &amp; Exchanges</span>
        </a>
        <a href="{{ route('cloth-store.expenses.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.expenses.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="expenses" /> <span class="sb-text">Expenses</span>
        </a>
        <a href="{{ route('cloth-store.stock.alerts') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.stock.alerts*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="alert" /> <span class="sb-text">Low Stock Alerts</span>
          <span class="sb-badge ml-auto bg-red-50 text-red-600 text-[10px] font-bold px-1.5 py-0.5 rounded">4</span>
        </a>
        <a href="{{ route('cloth-store.categories.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.categories.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="categories" /> <span class="sb-text">Categories</span>
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
        <span class="sb-text">Tools &amp; Settings</span>
        @if($toolsActive)
          <span class="sb-child-active-dot"></span>
        @endif
        <svg class="sb-dropdown-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m6 9 6 6 6-6"/>
        </svg>
      </button>

      <div class="sb-dropdown-body {{ $toolsActive ? 'open' : '' }}">
        <a href="{{ route('cloth-store.discounts.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.discounts.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="discounts" /> <span class="sb-text">Discounts &amp; Offers</span>
        </a>
        <a href="{{ route('cloth-store.reports.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.reports.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="reports" /> <span class="sb-text">Reports &amp; Analytics</span>
        </a>
        @if($user?->isAdmin())
        <a href="{{ route('cloth-store.settings.index') }}" class="{{ $nav }} {{ request()->routeIs('cloth-store.settings.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="settings" /> <span class="sb-text">Settings</span>
        </a>
        @endif
      </div>
    </div>

    </div>

  <!-- Collapse Toggle -->
  <div class="mt-auto border-t border-slate-200 p-3 sidebar-collapse-wrapper">
    <button type="button" onclick="toggleSidebarCollapse()" class="{{ $nav }} sidebar-collapse-button w-full text-slate-500 justify-start hover:text-slate-900 hover:bg-slate-50 transition-colors">
      <svg class="nav-ico collapse-arrow transition-transform duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
        <line x1="9" y1="3" x2="9" y2="21"/>
        <path d="M15 9l-3 3 3 3"/>
      </svg>
      <span class="sb-collapse-text font-medium">Collapse Sidebar</span>
    </button>
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

  /* Auto-populate titles for premium collapse tooltips */
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#sidebar .nav-item, #sidebar .sb-dropdown-toggle').forEach(el => {
      if (!el.hasAttribute('title')) {
        let textNode = Array.from(el.childNodes).find(node => node.nodeType === 3 && node.textContent.trim().length > 0);
        let text = textNode ? textNode.textContent.trim() : el.innerText.trim();
        if (text) el.setAttribute('title', text);
      }
    });

    /* Restore collapse state */
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
      document.body.classList.add('sidebar-collapsed');
    }
  });

  /* === Sidebar Collapse Toggle === */
  window.toggleSidebarCollapse = function() {
    const body = document.body;
    body.classList.toggle('sidebar-collapsed');
    const isCollapsed = body.classList.contains('sidebar-collapsed');
    localStorage.setItem('sidebar-collapsed', isCollapsed ? 'true' : 'false');
  };

</script>
