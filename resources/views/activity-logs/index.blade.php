@extends('layouts.app')
@section('title', 'Activity Logs')

@push('styles')
<style>
  /* Toggle */
  .toggle { width: 38px; height: 22px; background: #E2E8F0; border-radius: 11px; position: relative; cursor: pointer; transition: background .2s; flex-shrink: 0; }
  .toggle::after { content: ''; position: absolute; top: 2px; left: 2px; width: 18px; height: 18px; background: #fff; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
  .toggle.on { background: #4F46E5; }
  .toggle.on::after { transform: translateX(16px); }

  .pulse-dot { animation: pulse 1.5s infinite; }
  @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
</style>
@endpush

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Activity Logs</h1>
    <p class="text-sm text-slate-500 mt-1">Complete audit trail of system events</p>
  </div>
  <a href="{{ route('activity-logs.export', request()->only('category', 'q')) }}" class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 flex items-center gap-2"><i class="fa-solid fa-download text-xs"></i> Export</a>
</div>

<div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
  <div class="p-5 border-b border-slate-200 flex gap-4 overflow-x-auto items-center" id="log-tabs">
    @foreach($tabs as $key => $tab)
    <a href="{{ route('activity-logs.index', array_filter(['category' => $key === 'all' ? null : $key, 'q' => $search])) }}"
       data-category="{{ $key }}" onclick="event.preventDefault(); loadLogs('{{ $key }}')"
       class="text-sm {{ $category === $key ? 'font-semibold text-indigo-600 border-b-2 border-indigo-600 pb-1' : 'font-medium text-slate-500 hover:text-slate-900' }} cursor-pointer whitespace-nowrap">
      {{ $tab['label'] }}
      @if($tab['count'] > 0)<span class="ml-1 text-[10px] text-slate-400">{{ $tab['count'] }}</span>@endif
    </a>
    @endforeach

    <div class="ml-auto relative flex-shrink-0">
      <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
      <input type="text" id="log-search" value="{{ $search }}" placeholder="Search activity..." class="w-52 h-8 pl-8 pr-3 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-all">
    </div>
  </div>
  <div class="p-6" id="log-timeline">
    @include('activity-logs.partials.timeline')
  </div>
</div>
@endsection

@push('scripts')
<script>
  let logCategory = @json($category);
  let logSearch = @json($search ?? '');
  let logTimer = null;

  /* Swaps only the timeline; the page never navigates. */
  window.loadLogs = async function (category = logCategory, page = 1) {
    logCategory = category;

    const container = document.getElementById('log-timeline');
    container.style.opacity = '0.5';

    const params = new URLSearchParams({ partial: 1, category, page });
    if (logSearch) params.set('q', logSearch);

    try {
      const res = await Atelier.api.get(`{{ route('activity-logs.index') }}?${params}`);
      container.innerHTML = res.html;

      document.querySelectorAll('#log-tabs a').forEach(a => {
        const active = a.dataset.category === category;
        a.className = `text-sm ${active
          ? 'font-semibold text-indigo-600 border-b-2 border-indigo-600 pb-1'
          : 'font-medium text-slate-500 hover:text-slate-900'} cursor-pointer whitespace-nowrap`;
      });

      // Keep the export link in step with what is on screen.
      const exportLink = document.querySelector('a[href*="activity-logs/export"]');
      if (exportLink) {
        const p = new URLSearchParams({ category });
        if (logSearch) p.set('q', logSearch);
        exportLink.href = `{{ route('activity-logs.export') }}?${p}`;
      }
    } catch (err) {
      Atelier.reportError(err, 'Could not load the activity log');
    } finally {
      container.style.opacity = '';
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('log-search')?.addEventListener('input', function () {
      logSearch = this.value.trim();
      clearTimeout(logTimer);
      logTimer = setTimeout(() => loadLogs(logCategory), 300);
    });

    // Paginator links stay in-page too.
    document.getElementById('log-timeline').addEventListener('click', (e) => {
      const link = e.target.closest('a[href*="page="]');
      if (!link) return;
      e.preventDefault();
      const page = new URL(link.href).searchParams.get('page') || 1;
      loadLogs(logCategory, page);
    });
  });
</script>
@endpush
