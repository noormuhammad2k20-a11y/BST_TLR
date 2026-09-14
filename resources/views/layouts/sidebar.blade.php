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

  <div class="flex-1 overflow-y-auto overflow-x-hidden py-4 sidebar-scroll-area">

    <div class="sb-label px-6 mb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Main</div>

    <a href="{{ route('dashboard') }}" class="{{ $nav }} {{ request()->routeIs('dashboard') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="dashboard" /> <span class="sb-text">Dashboard</span>
    </a>
    <a href="{{ route('customers.index') }}" class="{{ $nav }} {{ request()->routeIs('customers.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="customers" /> <span class="sb-text">Customers</span>
      <span class="sb-badge ml-auto bg-slate-100 text-slate-600 text-[10px] font-bold px-1.5 py-0.5 rounded" data-badge="customers">{{ $counters['customers'] }}</span>
    </a>
    <a href="{{ route('orders.index') }}" class="{{ $nav }} {{ request()->routeIs('orders.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="tailoring" /> <span class="sb-text">Orders</span>
      <span class="ml-auto bg-red-50 text-red-600 text-[10px] font-bold px-1.5 py-0.5 rounded" data-badge="pending_orders">{{ $counters['pending_orders'] }}</span>
    </a>
    <a href="{{ route('measurements.index') }}" class="{{ $nav }} {{ request()->routeIs('measurements.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="measurements" /> <span class="sb-text">Measurements</span>
    </a>
    <a href="{{ route('products-services.index') }}" class="{{ $nav }} {{ request()->routeIs('products-services.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="services" /> <span class="sb-text">Products &amp; Services</span>
    </a>
    <a href="{{ route('staff.index') }}" class="{{ $nav }} {{ request()->routeIs('staff.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="team" /> <span class="sb-text">Tailors</span>
    </a>
    <a href="{{ route('delivery.index') }}" class="{{ $nav }} {{ request()->routeIs('delivery.*') ? 'active text-slate-900' : 'text-slate-600' }}">
      <x-icon name="delivery" /> <span class="sb-text">Delivery</span>
    </a>

    {{-- More ▾ dropdown --}}
    @php
      $moreIsActive = request()->routeIs('payments-billing.*', 'expenses.*', 'reports.*', 'printing-center.*');
    @endphp
    <div class="mt-3" id="sb-more-dropdown">
      <button type="button" onclick="toggleSbMore()" class="{{ $nav }} w-[calc(100%-1.5rem)] justify-between {{ $moreIsActive ? 'text-slate-900' : 'text-slate-600' }}">
        <span class="flex items-center gap-3">
          <x-icon name="more" /> <span class="sb-text">More</span>
        </span>
        <svg id="sb-more-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-200" style="flex:0 0 auto;opacity:.6;{{ $moreIsActive ? 'transform:rotate(180deg)' : '' }}"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div id="sb-more-panel" class="overflow-hidden transition-all duration-200 ease-in-out" style="{{ $moreIsActive ? '' : 'max-height:0;opacity:0;' }}">
        <a href="{{ route('payments-billing.index') }}" class="{{ $nav }} pl-6 {{ request()->routeIs('payments-billing.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="billing" /> <span class="sb-text">Payments &amp; Billing</span>
        </a>
        <a href="{{ route('expenses.index') }}" class="{{ $nav }} pl-6 {{ request()->routeIs('expenses.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="expenses" /> <span class="sb-text">Expenses</span>
        </a>
        <a href="{{ route('reports.index') }}" class="{{ $nav }} pl-6 {{ request()->routeIs('reports.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="reports" /> <span class="sb-text">Reports</span>
        </a>
        <a href="{{ route('printing-center.index') }}" class="{{ $nav }} pl-6 {{ request()->routeIs('printing-center.*') ? 'active text-slate-900' : 'text-slate-600' }}">
          <x-icon name="printing" /> <span class="sb-text">Printing Center</span>
        </a>
      </div>
    </div>

    {{-- Settings (standalone) --}}
    @if($user?->isAdmin())
    <div class="mt-3">
      <a href="{{ route('settings.index') }}" class="{{ $nav }} {{ request()->routeIs('settings.*') ? 'active text-slate-900' : 'text-slate-600' }}">
        <x-icon name="settings" /> <span class="sb-text">Settings</span>
      </a>
    </div>
    @endif
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

    /* Auto-populate titles for premium collapse tooltips */
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
