@extends('layouts.app')
@section('title', 'Notifications')
@section('spaPage', 'notifications')

@push('styles')
<style>
  /* Animations */
  @keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
  }
  .pulse-dot { animation: pulse 1.5s infinite; }

  /* Staggered List Animation */
  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .notif-item {
    animation: fadeInUp 0.4s ease forwards;
    opacity: 0;
  }

  /* Floating Action Menu */
  .notif-actions-wrapper {
    position: absolute;
    right: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    padding: 4px;
    display: flex;
    gap: 4px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
  }
  .notif-item:hover .notif-actions-wrapper {
    opacity: 1;
    pointer-events: auto;
  }
  .notif-action-btn {
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 6px; color: #64748B; font-size: 12px;
    transition: background 0.2s;
  }
  .notif-action-btn:hover { background: #F1F5F9; color: #0F172A; }
</style>
@endpush

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Notification Center</h1>
    <p class="text-sm text-slate-500 mt-0.5" id="notif-count">Loading notifications...</p>
  </div>
  <button class="bg-white border border-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 hover:border-slate-300 flex items-center gap-2 transition-colors shadow-sm" onclick="markAllNotificationsRead()"><i class="fa-solid fa-check-double text-[10px]"></i> Mark all read</button>
</div>

<!-- STAT CARDS -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 page">
  <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Unread</span>
      <div class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-envelope text-[11px]"></i></div>
    </div>
    <div class="text-2xl font-bold text-slate-900 tracking-tight" id="stat-unread">{{ $stats['unread'] }}</div>
    <div class="text-[11px] text-emerald-600 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-arrow-up text-[7px]"></i> Requires action</div>
  </div>
  <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Orders</span>
      <div class="w-7 h-7 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center"><i class="fa-solid fa-box text-[11px]"></i></div>
    </div>
    <div class="text-2xl font-bold text-slate-900 tracking-tight">{{ $stats['orders'] }}</div>
    <div class="text-[11px] text-slate-400 font-medium mt-1">Status updates</div>
  </div>
  <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Payments</span>
      <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-rupee-sign text-[11px]"></i></div>
    </div>
    <div class="text-2xl font-bold text-slate-900 tracking-tight">{{ $stats['payments'] }}</div>
    <div class="text-[11px] text-slate-400 font-medium mt-1">Received yesterday</div>
  </div>
  <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Alerts</span>
      <div class="w-7 h-7 rounded-md bg-red-50 text-red-600 flex items-center justify-center"><i class="fa-solid fa-triangle-exclamation text-[11px]"></i></div>
    </div>
    <div class="text-2xl font-bold text-slate-900 tracking-tight">{{ $stats['alerts'] }}</div>
    <div class="text-[11px] text-red-500 font-medium mt-1">Overdue items</div>
  </div>
</div>

<!-- NOTIFICATION LIST -->
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-200 flex gap-1.5 flex-wrap" id="notif-filter-pills">
    <span data-type="all" data-base-class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-100 transition-colors" class="px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer" onclick="setNotifFilter('all', this)">All</span>
    <span data-type="orders" data-base-class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-100 transition-colors" class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-100 transition-colors" onclick="setNotifFilter('orders', this)">Orders</span>
    <span data-type="payments" data-base-class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-100 transition-colors" class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-100 transition-colors" onclick="setNotifFilter('payments', this)">Payments</span>
    <span data-type="stock" data-base-class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-100 transition-colors" class="px-3 py-1.5 rounded-md text-slate-500 text-xs font-medium cursor-pointer hover:bg-slate-100 transition-colors" onclick="setNotifFilter('stock', this)">Stock</span>
    <span data-type="whatsapp" data-base-class="px-3 py-1.5 rounded-md text-emerald-600 text-xs font-medium cursor-pointer hover:bg-emerald-50 transition-colors" class="px-3 py-1.5 rounded-md text-emerald-600 text-xs font-medium cursor-pointer hover:bg-emerald-50 transition-colors" onclick="setNotifFilter('whatsapp', this)"><i class="fa-brands fa-whatsapp mr-1"></i>WhatsApp</span>
    <span data-type="alerts" data-base-class="px-3 py-1.5 rounded-md text-red-500 text-xs font-medium cursor-pointer hover:bg-red-50 transition-colors" class="px-3 py-1.5 rounded-md text-red-500 text-xs font-medium cursor-pointer hover:bg-red-50 transition-colors" onclick="setNotifFilter('alerts', this)"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Alerts</span>
  </div>
  
  <div id="notif-main-list" class="relative min-h-[300px]"></div>
  
  <!-- PAGINATION FOOTER -->
  <div id="pagination-footer" class="px-6 py-3 border-t border-slate-200 flex items-center justify-between">
    <div class="text-xs text-slate-500" id="page-info">Showing 0 to 0 of 0 results</div>
    <div class="flex items-center gap-1" id="page-controls"></div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  /* ============= DATA STORE ============= */
  /* `var` throughout: the SPA router re-evaluates this script per navigation. */
  var notifications = @json($notifications);

  /* ============= PAGINATION & FILTER STATE ============= */
  var currentPage = 1;
  /* Page size comes from Settings → Theme & Display, so one number governs
     every table in the app. */
  var itemsPerPage = Atelier.rowsPerPage();
  var notifFilter = 'all';

  function getFilteredNotifications() {
    return notifFilter === 'all'
      ? notifications
      : notifications.filter(n => n.type === notifFilter);
  }

  window.setNotifFilter = function(type, el) {
    notifFilter = type;
    currentPage = 1;

    document.querySelectorAll('#notif-filter-pills span').forEach(s => {
      s.dataset.active = String(s.dataset.type === type);
      if (s.dataset.type === type) {
        s.className = 'px-3 py-1.5 rounded-md bg-slate-900 text-white text-xs font-medium cursor-pointer';
      } else {
        s.className = s.dataset.baseClass;
      }
    });

    renderNotifications();
  };

  /* ============= NOTIFICATIONS RENDER ============= */
  function getNotifHTML(n, isMainList = false, index = 0) {
    const colorMap = {
      success: { bg: '#ECFDF5', fg: '#10B981' }, danger: { bg: '#FEF2F2', fg: '#EF4444' },
      warning: { bg: '#FFFBEB', fg: '#F59E0B' }, info: { bg: '#F0F9FF', fg: '#0EA5E9' },
      purple: { bg: '#F5F3FF', fg: '#8B5CF6' }, primary: { bg: '#EEF2FF', fg: '#4F46E5' }
    };
    const c = colorMap[n.color];
    const padding = isMainList ? 'px-6 py-4' : 'px-4 py-3';
    const iconSize = isMainList ? 'w-10 h-10 text-sm' : 'w-9 h-9 text-xs';
    
    return `
      <div class="notif-item relative ${padding} border-b border-slate-100 hover:bg-slate-50 transition-colors flex items-start gap-4 cursor-pointer ${n.unread ? 'bg-indigo-50/20' : ''}" style="animation-delay: ${index * 0.05}s" onclick="handleNotifClick('${n.action}', ${n.id})">
        <div class="${iconSize} rounded-lg flex items-center justify-center flex-shrink-0" style="background:${c.bg}; color:${c.fg}">
          <i class="${n.icon.includes('brands') ? 'fa-brands' : 'fa-solid'} ${n.icon.split(' ').pop()}"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2">
            <div class="text-sm font-semibold text-slate-900 flex items-center gap-2">
              ${n.title}
              ${n.unread ? '<span class="w-1.5 h-1.5 rounded-full bg-indigo-600 flex-shrink-0"></span>' : ''}
            </div>
            <div class="text-[11px] text-slate-400 flex-shrink-0 mt-0.5 font-medium">${n.time}</div>
          </div>
          <div class="text-[13px] text-slate-500 mt-0.5 leading-relaxed">${n.desc}</div>
        </div>
        
        ${isMainList ? `
        <div class="notif-actions-wrapper">
          <button onclick="event.stopPropagation(); markSingleRead(${n.id})" class="notif-action-btn" title="Mark as read">
            <i class="fa-solid fa-check"></i>
          </button>
          <button onclick="event.stopPropagation(); deleteNotif(${n.id})" class="notif-action-btn hover:text-red-600" title="Delete">
            <i class="fa-solid fa-trash-can"></i>
          </button>
        </div>
        ` : ''}
      </div>
    `;
  }

  function renderNotifications() {
    const mainList = document.getElementById('notif-main-list');
    if (!mainList) return;

    // Pagination Calculations
    const filtered = getFilteredNotifications();
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages; // Reset page if out of bounds
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageItems = filtered.slice(startIndex, endIndex);

    // Group by Date within the current page
    let html = '';
    let lastDate = null;
    pageItems.forEach((n, idx) => {
      if (n.date !== lastDate) {
        const dateLabel = n.date === 'today' ? 'Today' : n.date === 'yesterday' ? 'Yesterday' : 'Earlier';
        html += `<div class="px-6 py-2 bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest">${dateLabel}</div>`;
        lastDate = n.date;
      }
      html += getNotifHTML(n, true, idx);
    });

    mainList.innerHTML = html || Atelier.emptyState({
      icon: 'fa-bell',
      title: notifications.length === 0 ? "You're all caught up" : 'No notifications in this category',
      message: notifications.length === 0
        ? 'Order, payment and stock events will appear here as they happen.'
        : 'Try a different filter to see more.'
    });

    // Render Pagination Footer
    renderPagination(totalPages, startIndex, endIndex, filtered.length);

    const unreadCount = notifications.filter(n => n.unread).length;
    const notifCountEl = document.getElementById('notif-count');
    if (notifCountEl) notifCountEl.innerText = `${unreadCount} unread · ${notifications.length} total`;
    
    const statUnread = document.getElementById('stat-unread');
    if(statUnread) statUnread.innerText = unreadCount;
  }

  function renderPagination(totalPages, startIndex, endIndex, totalCount) {
    const pageInfo = document.getElementById('page-info');
    const pageControls = document.getElementById('page-controls');

    if (!totalCount) {
      pageInfo.innerText = 'Showing 0 to 0 of 0 results';
      pageControls.innerHTML = '';
      return;
    }

    const start = startIndex + 1;
    const end = Math.min(endIndex, totalCount);
    pageInfo.innerText = `Showing ${start} to ${end} of ${totalCount} results`;

    let controlsHTML = `
      <button onclick="changePage(-1)" ${currentPage === 1 ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
        <i class="fa-solid fa-chevron-left text-xs"></i>
      </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      controlsHTML += `
        <button onclick="goToPage(${i})" class="w-8 h-8 flex items-center justify-center text-xs font-medium rounded-md transition-colors ${currentPage === i ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}">
          ${i}
        </button>
      `;
    }

    controlsHTML += `
      <button onclick="changePage(1)" ${currentPage === totalPages ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
        <i class="fa-solid fa-chevron-right text-xs"></i>
      </button>
    `;

    pageControls.innerHTML = controlsHTML;
  }

  window.changePage = function(direction) {
    const totalPages = Math.ceil(getFilteredNotifications().length / itemsPerPage);
    const newPage = currentPage + direction;
    if (newPage >= 1 && newPage <= totalPages) {
      currentPage = newPage;
      renderNotifications();
    }
  }

  window.goToPage = function(page) {
    currentPage = page;
    renderNotifications();
  }

  window.handleNotifClick = async function(action, id) {
    await markSingleRead(id, true);
    if (window.innerWidth < 768) closeDrawers();
    if (action) window.location.href = action;
  }

  window.markSingleRead = async function(id, silent = false) {
    const notif = notifications.find(n => n.id === id);
    if (!notif || !notif.unread) return;

    try {
      const res = await Atelier.api.post(`/notifications/${id}/read`);
      notif.unread = false;
      renderNotifications();
      Atelier.refreshCounters();
      if (!silent) toast(res.message, 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not mark the notification as read');
    }
  }

  window.deleteNotif = async function(id) {
    try {
      const res = await Atelier.api.delete(`/notifications/${id}`);
      notifications = notifications.filter(n => n.id !== id);
      renderNotifications();
      Atelier.refreshCounters();
      toast(res.message, 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not delete the notification');
    }
  }

  window.markAllNotificationsRead = async function() {
    try {
      const res = await Atelier.api.post(@json(route('notifications.read-all')));
      notifications.forEach(n => n.unread = false);
      renderNotifications();
      Atelier.refreshCounters();
      toast(res.message, 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not mark everything as read');
    }
  }

  Atelier.onPageReady(() => {
    renderNotifications();

    // New notifications arrive from order, payment and stock activity.
    Atelier.poll(async () => {
      const data = await Atelier.api.get(@json(route('live.notifications')));
      notifications = data.notifications;
      renderNotifications();
    }, 30000);
  });
</script>
@endpush
