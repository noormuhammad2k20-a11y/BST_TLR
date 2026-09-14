@php
  $user = auth()->user();
  $counters = $layoutCounters ?? ['unread_notifications' => 0];
@endphp
<header class="bg-white/70 backdrop-blur-xl border-b border-slate-200 px-8 h-16 flex items-center gap-4 sticky top-0 z-30">
  <div class="flex items-center pl-3.5 py-1 border-l-[3px] border-indigo-500 rounded-sm">
    <h1 class="text-base sm:text-[17px] font-bold text-slate-900 tracking-tight whitespace-nowrap" id="bc-current">@yield('title', 'Dashboard')</h1>
  </div>

  <div class="ml-8 flex-1 max-w-md relative">
    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
    <input type="text" id="global-search" autocomplete="off" placeholder="Search orders, customers, invoices..."
      class="w-full h-9 pl-10 pr-16 bg-slate-100/80 border border-transparent rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-all">
    <kbd class="absolute right-3 top-1/2 -translate-y-1/2 bg-white border border-slate-200 px-1.5 py-0.5 rounded text-[10px] text-slate-400 font-medium shadow-sm">⌘K</kbd>

    <div id="global-search-results" class="hidden absolute left-0 right-0 top-full mt-2 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden z-50 max-h-96 overflow-y-auto"></div>
  </div>

  {{-- ═══════ System Switcher Toggle ═══════ --}}
  <div class="ml-auto flex items-center gap-4">
    <div class="system-switcher" id="system-switcher">
      <div class="system-switcher__track" id="switcher-track">
        <div class="system-switcher__slider" id="switcher-slider"></div>
        <button type="button" class="system-switcher__btn active" id="btn-tailor" data-system="tailor" onclick="switchSystem('tailor')">
          <i class="fa-solid fa-scissors"></i>
          <span>Tailor</span>
        </button>
        <button type="button" class="system-switcher__btn" id="btn-cloth" data-system="cloth" onclick="switchSystem('cloth')">
          <i class="fa-solid fa-store"></i>
          <span>Cloth Store</span>
        </button>
      </div>
    </div>

    <div class="w-px h-7 bg-slate-200"></div>

    <div class="flex items-center gap-1.5">
      <button class="w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 flex items-center justify-center transition-colors" onclick="openModal('quick-add')">
        <i class="fa-solid fa-plus text-sm"></i>
      </button>
      <button class="w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 flex items-center justify-center transition-colors relative" onclick="openDrawer('notif-drawer')">
        <i class="fa-regular fa-bell text-sm"></i>
        @if($counters['unread_notifications'] > 0)
        <span class="absolute top-1 right-1 bg-red-500 text-white text-[9px] font-bold min-w-[16px] h-4 px-1 rounded-full flex items-center justify-center leading-none pulse-dot" id="header-bell-badge" data-badge="unread_notifications">{{ $counters['unread_notifications'] }}</span>
        @else
        <span class="absolute top-1.5 right-1.5 bg-red-500 w-2 h-2 rounded-full pulse-dot hidden" id="header-bell-dot"></span>
        @endif
      </button>
      <button class="w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 flex items-center justify-center transition-colors" onclick="openModal('help')" title="Keyboard shortcuts &amp; help">
        <i class="fa-regular fa-circle-question text-sm"></i>
      </button>
    </div>

    <div class="w-px h-7 bg-slate-200"></div>

    {{-- ═══════ Profile / User Dropdown ═══════ --}}
    <div class="relative" id="header-profile-wrap">
      <button
        type="button"
        class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer"
        onclick="toggleHeaderProfile(event)"
        id="header-profile-btn"
      >
        <div class="avatar sm bg-slate-900" style="width:30px;height:30px;font-size:11px;">{{ $user?->initials ?? 'AT' }}</div>
        <div class="hidden sm:block text-left min-w-0">
          <div class="text-sm font-semibold text-slate-900 truncate max-w-[120px]">{{ $user?->name ?? 'Guest' }}</div>
        </div>
        <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0 hidden sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m6 9 6 6 6-6"/>
        </svg>
      </button>

      <div id="header-profile-menu" class="hidden absolute right-0 top-full mt-2 w-56 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden z-50">
        {{-- User info header --}}
        <div class="px-4 py-3 border-b border-slate-100">
          <div class="text-sm font-semibold text-slate-900 truncate">{{ $user?->name ?? 'Guest' }}</div>
          <div class="text-xs text-slate-500 truncate">{{ $user?->title ?: ($user?->role_label ?? 'Administrator') }}</div>
        </div>

        <a href="{{ route('profile.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors">
          <x-icon name="profile" :size="16" class="menu-ico" /> My Profile
        </a>
        <a href="{{ route('notifications.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors">
          <i class="fa-regular fa-bell text-xs w-4 text-center opacity-75"></i> All Notifications
          @if($counters['unread_notifications'] > 0)
          <span class="ml-auto bg-red-500 text-white text-[9px] font-bold min-w-[16px] h-4 px-1 rounded-full flex items-center justify-center leading-none" data-badge="unread_notifications">{{ $counters['unread_notifications'] }}</span>
          @endif
        </a>
        @if($user?->isAdmin())
        <a href="{{ route('settings.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors">
          <x-icon name="settings" :size="16" class="menu-ico" /> Settings
        </a>
        @endif

        {{-- Shown only when the browser reports the app can be installed. --}}
        <button type="button" data-install-app class="hidden w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-indigo-600 hover:bg-indigo-50 transition-colors border-t border-slate-100" onclick="installApp()">
          <x-icon name="install" :size="16" class="menu-ico" /> Install App
        </button>

        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100" id="logout-form">
          @csrf
          <button type="button" onclick="confirmLogout()" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
            <x-icon name="logout" :size="16" class="menu-ico" /> Sign Out
          </button>
        </form>
      </div>
    </div>
  </div>
</header>

<style>
  /* ── System Switcher: Elite Class Luxury Animation Profile ── */
  .system-switcher {
    position: relative;
    user-select: none;
    -webkit-user-select: none;
    perspective: 1000px; /* Deep, luxury 3D */
  }

  .system-switcher__track {
    position: relative;
    display: flex;
    /* Sophisticated metallic/frosted gradient */
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0, #f8fafc);
    background-size: 200% 200%;
    animation: trackGradient 8s ease infinite;
    
    /* Clean, precise inner carving */
    box-shadow: 
      inset 0 3px 6px rgba(15, 23, 42, 0.1), 
      inset 0 1px 2px rgba(15, 23, 42, 0.15), 
      inset 0 -2px 3px rgba(255, 255, 255, 0.9),
      0 1px 1px rgba(255, 255, 255, 0.7);
    padding: 6px;
    border-radius: 999px;
    gap: 4px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.4s ease;
  }

  @keyframes trackGradient {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
  }

  /* Elite Anticipation Press */
  .system-switcher__track.is-switching {
    transform: scale(0.97) rotateX(3deg);
    box-shadow: 
      inset 0 8px 16px rgba(15, 23, 42, 0.15), 
      inset 0 2px 4px rgba(15, 23, 42, 0.1);
  }

  .system-switcher__slider {
    position: absolute;
    top: 6px;
    bottom: 6px;
    left: 6px;
    border-radius: 999px;
    /* Ceramic/Glass clean white */
    background: linear-gradient(180deg, #ffffff 0%, #fdfdfd 40%, #f1f5f9 100%);
    box-shadow: 
      inset 0 2px 3px rgba(255, 255, 255, 1),
      0 4px 10px rgba(15, 23, 42, 0.08),  
      0 2px 4px rgba(15, 23, 42, 0.05);   
    z-index: 1;
    /* Luxury physics curve */
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1),
                width 0.6s cubic-bezier(0.16, 1, 0.3, 1),
                box-shadow 0.4s ease;
  }

  /* High-end glass reflection */
  .system-switcher__slider::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 999px;
    background: linear-gradient(110deg, transparent 25%, rgba(255,255,255,0.8) 45%, rgba(255,255,255,0.8) 55%, transparent 75%);
    background-size: 200% 200%;
    background-position: -150% -150%;
    pointer-events: none;
    opacity: 0.6;
  }
  
  .system-switcher:hover .system-switcher__slider::after,
  .system-switcher__track.is-switching .system-switcher__slider::after {
    animation: glassShineInfinite 2.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    opacity: 1;
  }

  @keyframes glassShineInfinite {
    0% { background-position: 200% 200%; }
    100% { background-position: -200% -200%; }
  }

  /* Elite Light Trail during warp */
  .system-switcher__track.is-switching .system-switcher__slider {
    background: linear-gradient(180deg, #ffffff 0%, #eef2ff 100%);
    box-shadow: 
      inset 0 2px 3px rgba(255, 255, 255, 1),
      0 0 20px rgba(99, 102, 241, 0.5), /* Elegant wide glow */
      0 0 40px rgba(79, 70, 229, 0.3), /* Deep trail */
      0 4px 10px rgba(15, 23, 42, 0.1);
  }

  .system-switcher__btn {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 20px;
    font-size: 13.5px;
    font-weight: 700;
    color: #64748b;
    text-shadow: 0 1px 1px rgba(255, 255, 255, 0.9);
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 999px;
    transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
    outline: none;
  }

  .system-switcher__btn:hover:not(.active) {
    color: #334155;
    transform: translateY(-1px);
  }

  .system-switcher__btn.active {
    color: #0f172a; 
    text-shadow: 0 0 15px rgba(99, 102, 241, 0.4), 0 1px 2px rgba(255, 255, 255, 1);
    letter-spacing: 0.3px;
  }

  /* Sophisticated icon interaction */
  .system-switcher__btn i {
    font-size: 14px;
    width: 16px;
    text-align: center;
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), color 0.6s ease, filter 0.6s ease, opacity 0.6s ease;
    opacity: 0.6;
  }

  .system-switcher__btn.active i {
    color: var(--brand, #4F46E5);
    transform: scale(1.15) translateY(-1px);
    filter: drop-shadow(0 2px 6px rgba(79, 70, 229, 0.4));
    opacity: 1;
    animation: eliteFloat 3s ease-in-out infinite alternate;
  }

  @keyframes eliteFloat {
    0% { transform: scale(1.15) translateY(-1px); }
    100% { transform: scale(1.15) translateY(-3px); }
  }

  /* ── Dark Mode Elite Adaptation ── */
  html.theme-dark .system-switcher__track {
    background: linear-gradient(135deg, #1e293b, #0f172a, #1e293b);
    box-shadow: 
      inset 0 3px 8px rgba(0, 0, 0, 0.6), 
      inset 0 1px 3px rgba(0, 0, 0, 0.8),
      inset 0 -1px 2px rgba(255, 255, 255, 0.03),
      0 1px 1px rgba(255, 255, 255, 0.05);
    border-color: rgba(0,0,0,0.6);
  }

  html.theme-dark .system-switcher__slider {
    background: linear-gradient(180deg, #334155 0%, #1e293b 100%);
    box-shadow: 
      inset 0 1px 1px rgba(255, 255, 255, 0.1), 
      0 4px 10px rgba(0, 0, 0, 0.5);
  }
  
  html.theme-dark .system-switcher__track.is-switching .system-switcher__slider {
    box-shadow: 
      inset 0 1px 1px rgba(255, 255, 255, 0.1), 
      0 0 20px rgba(129, 140, 248, 0.4),
      0 0 40px rgba(99, 102, 241, 0.2);
  }

  html.theme-dark .system-switcher__btn {
    color: #64748b;
    text-shadow: 0 -1px 1px rgba(0, 0, 0, 0.8);
  }

  html.theme-dark .system-switcher__btn.active {
    color: #f8fafc;
    text-shadow: 0 0 15px rgba(129, 140, 248, 0.3), 0 1px 2px rgba(0, 0, 0, 0.8);
  }

  html.theme-dark .system-switcher__btn.active i {
    color: #818cf8;
    filter: drop-shadow(0 2px 6px rgba(129, 140, 248, 0.3));
  }

  /* ── Header Profile Dropdown ── */
  #header-profile-menu {
    animation: profileMenuIn .2s cubic-bezier(.16,1,.3,1) both;
  }
  @keyframes profileMenuIn {
    from { opacity: 0; transform: translateY(-6px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  html.theme-dark #header-profile-menu {
    background: var(--bg-surface);
    border-color: var(--border);
  }
  html.theme-dark #header-profile-menu a:hover,
  html.theme-dark #header-profile-menu button:hover {
    background: var(--bg-muted) !important;
  }

  /* ── Responsive ── */
  @media (max-width: 768px) {
    .system-switcher__btn span {
      display: none;
    }
    .system-switcher__btn {
      padding: 8px 14px;
    }
  }
</style>

<script>
  /**
   * System Switcher — Elite Class Animation
   */
  (function () {
    const track  = document.getElementById('switcher-track');
    const slider = document.getElementById('switcher-slider');
    const btns   = document.querySelectorAll('.system-switcher__btn');

    if (!track || !slider || !btns.length) return;

    let isSwitching = false;
    const isReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function positionSlider(activeBtn, animate, phase = 'normal') {
      const trackRect = track.getBoundingClientRect();
      const btnRect   = activeBtn.getBoundingClientRect();
      let offset      = btnRect.left - trackRect.left - 5; 
      let width       = btnRect.width;

      if (!animate || isReducedMotion) {
        slider.style.transition = 'none';
      } else if (phase === 'stretch') {
        // Elite Warp: Hyper-fast, sleek stretch with light trail
        const stretchAmount = 20; 
        width += stretchAmount; 
        offset -= stretchAmount / 2; 
        // Apple-style exponential curve for intense speed and smooth landing
        slider.style.transition = 'transform 1.4s cubic-bezier(0.85, 0, 0.15, 1), width 1.2s cubic-bezier(0.85, 0, 0.15, 1)';
      } else if (phase === 'settle') {
        // Lock into place beautifully
        slider.style.transition = 'transform 0.8s cubic-bezier(0.16, 1, 0.3, 1), width 0.8s cubic-bezier(0.16, 1, 0.3, 1)';
      } else {
        // Normal fast transition
        slider.style.transition = 'transform 0.5s ease, width 0.5s ease';
      }

      slider.style.width     = width + 'px';
      slider.style.transform = 'translateX(' + offset + 'px)';
      
      if (!animate || isReducedMotion) {
        void slider.offsetWidth; // force reflow
        slider.style.transition = '';
      }
    }

    window.switchSystem = function (system, isInitial = false) {
      if (isSwitching) return; 
      
      const currentSystem = localStorage.getItem('activeSystem') || 'tailor';
      if (!isInitial && system === currentSystem) return;

      const target = document.getElementById('btn-' + (system === 'cloth' ? 'cloth' : 'tailor'));
      if (!target) return;

      if (isInitial || isReducedMotion) {
        btns.forEach(b => b.classList.remove('active'));
        target.classList.add('active');
        positionSlider(target, false);
        return;
      }

      // ── START ELITE TRANSITION (2.5 Seconds) ──
      isSwitching = true;
      track.classList.add('is-switching');
      
      // Phase 1: Anticipation press down
      
      // Phase 2: Hyper-speed warp
      setTimeout(() => {
        btns.forEach(b => b.classList.remove('active'));
        target.classList.add('active');
        positionSlider(target, true, 'stretch');
      }, 300);
      
      // Phase 3: Perfect snap settle
      setTimeout(() => {
        positionSlider(target, true, 'settle');
      }, 1500); // Wait longer for the 1.4s warp to almost finish
      
      // Phase 4: Redirect
      setTimeout(() => {
        isSwitching = false;
        track.classList.remove('is-switching');
        localStorage.setItem('activeSystem', system);
        
        if (system === 'cloth') {
          window.location.href = '/cloth-store';
        } else {
          window.location.href = '/';
        }
      }, 2500);
    };

    // Restore persisted choice on load
    const saved = localStorage.getItem('activeSystem') || 'tailor';
    window.switchSystem(saved, true);

    // Re-position after fonts/layout settle
    window.addEventListener('load', function () {
      const activeBtn = track.querySelector('.system-switcher__btn.active');
      if (activeBtn) positionSlider(activeBtn, false);
    });

    // Re-position on resize
    window.addEventListener('resize', function () {
      const activeBtn = track.querySelector('.system-switcher__btn.active');
      if (activeBtn) positionSlider(activeBtn, false);
    });
  })();

  /* === Header Profile Dropdown === */
  window.toggleHeaderProfile = function(e) {
    e && e.stopPropagation();
    var menu = document.getElementById('header-profile-menu');
    if (!menu) return;
    menu.classList.toggle('hidden');
  };

  /* Uses the shared confirmation dialog, matching the design's logout variant. */
  window.confirmLogout = function () {
    document.getElementById('header-profile-menu')?.classList.add('hidden');

    showConfirmation({
      variant: 'logout',
      title: 'End Current Session?',
      message: 'You will be immediately logged out. Please ensure all your work is saved.',
      confirmLabel: 'Logout Now',
      onConfirm: () => document.getElementById('logout-form').submit(),
    });
  };

  /* Close profile menu when clicking outside */
  document.addEventListener('click', function(e) {
    var wrap = document.getElementById('header-profile-wrap');
    var menu = document.getElementById('header-profile-menu');
    if (menu && !menu.classList.contains('hidden') && wrap && !wrap.contains(e.target)) {
      menu.classList.add('hidden');
    }
  });

  (function () {
    const input = document.getElementById('global-search');
    const panel = document.getElementById('global-search-results');
    if (!input || !panel) return;

    let timer = null;
    let controller = null;

    const iconFor = {
      order: 'fa-scissors', customer: 'fa-user', invoice: 'fa-receipt',
      expense: 'fa-coins', service: 'fa-tag', measurement: 'fa-ruler-combined'
    };

    function hide() { panel.classList.add('hidden'); panel.innerHTML = ''; }

    function renderState(html) { panel.innerHTML = html; panel.classList.remove('hidden'); }

    function render(groups) {
      if (!groups.length) {
        renderState('<div class="px-4 py-6 text-center text-sm text-slate-400">No matching records found</div>');
        return;
      }

      renderState(groups.map(g => `
        <div class="px-4 py-2 bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-widest">${g.label}</div>
        ${g.items.map(item => `
          <a href="${item.url}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 border-b border-slate-50 transition-colors">
            <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center flex-shrink-0">
              <i class="fa-solid ${iconFor[g.type] || 'fa-circle'} text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-semibold text-slate-900 truncate">${item.title}</div>
              <div class="text-xs text-slate-500 truncate">${item.subtitle || ''}</div>
            </div>
            ${item.meta ? `<div class="text-xs font-semibold text-slate-600 flex-shrink-0">${item.meta}</div>` : ''}
          </a>
        `).join('')}
      `).join(''));
    }

    input.addEventListener('input', function () {
      const q = this.value.trim();
      clearTimeout(timer);

      if (q.length < 2) { hide(); return; }

      renderState('<div class="px-4 py-6 text-center text-sm text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Searching…</div>');

      timer = setTimeout(async () => {
        if (controller) controller.abort();
        controller = new AbortController();

        try {
          const res = await fetch(`{{ route('search') }}?q=${encodeURIComponent(q)}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal
          });
          if (!res.ok) throw new Error('Search failed');
          render((await res.json()).groups || []);
        } catch (err) {
          if (err.name !== 'AbortError') {
            renderState('<div class="px-4 py-6 text-center text-sm text-red-500">Search is unavailable right now</div>');
          }
        }
      }, 220);
    });

    document.addEventListener('click', e => {
      if (!panel.contains(e.target) && e.target !== input) hide();
    });

    document.addEventListener('keydown', e => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        input.focus();
        input.select();
      }
      if (e.key === 'Escape') { hide(); input.blur(); }
    });
  })();
</script>
