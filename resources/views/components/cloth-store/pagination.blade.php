{{--
  Pagination footer matching the Tailor system.

  Laravel's stock pagination view is styled for a generic Tailwind app and
  looks nothing like the rest of this product. This renders the same
  "Showing X to Y of Z results" + compact page buttons the Tailor tables use.

  Every link is a plain <a href>, so the SPA router intercepts the click and
  swaps <main> without a full page reload — no extra JS needed here.

  Usage:  <x-cloth-store.pagination :paginator="$products" noun="product" />
--}}
@props(['paginator', 'noun' => 'result'])

@php
  // A window of pages around the current one, so 400 pages don't render 400
  // buttons. Always includes first and last for quick jumps.
  $current = $paginator->currentPage();
  $last    = $paginator->lastPage();
  $window  = 1;

  $pages = collect(range(1, $last))
      ->filter(fn ($p) => $p === 1
          || $p === $last
          || abs($p - $current) <= $window)
      ->values()
      ->all();
@endphp

<div class="flex items-center justify-between gap-3 flex-wrap">
  <div class="text-xs text-slate-500">
    @if($paginator->total() > 0)
      Showing
      <span class="font-semibold text-slate-700">{{ number_format($paginator->firstItem()) }}</span>
      to
      <span class="font-semibold text-slate-700">{{ number_format($paginator->lastItem()) }}</span>
      of
      <span class="font-semibold text-slate-700">{{ number_format($paginator->total()) }}</span>
      {{ \Illuminate\Support\Str::plural($noun, $paginator->total()) }}
    @else
      No {{ \Illuminate\Support\Str::plural($noun) }} found
    @endif
  </div>

  @if($paginator->hasPages())
    <div class="flex items-center gap-1">
      {{-- Previous --}}
      @if($paginator->onFirstPage())
        <span class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-300 cursor-not-allowed border border-slate-200">
          <i class="fa-solid fa-chevron-left text-[10px]"></i>
        </span>
      @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page"
           class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-600 border border-slate-200 hover:bg-slate-100 hover:text-slate-900 transition-colors">
          <i class="fa-solid fa-chevron-left text-[10px]"></i>
        </a>
      @endif

      {{-- Numbered pages --}}
      @php $previousPage = 0; @endphp
      @foreach($pages as $page)
        @if($page - $previousPage > 1)
          <span class="w-8 h-8 flex items-center justify-center text-slate-400 text-xs select-none">…</span>
        @endif

        @if($page == $current)
          <span aria-current="page"
                class="min-w-8 h-8 px-2 rounded-lg flex items-center justify-center bg-slate-900 text-white text-xs font-semibold shadow-sm">
            {{ $page }}
          </span>
        @else
          <a href="{{ $paginator->url($page) }}"
             class="min-w-8 h-8 px-2 rounded-lg flex items-center justify-center text-slate-600 border border-slate-200 text-xs font-medium hover:bg-slate-100 hover:text-slate-900 transition-colors">
            {{ $page }}
          </a>
        @endif

        @php $previousPage = $page; @endphp
      @endforeach

      {{-- Next --}}
      @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page"
           class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-600 border border-slate-200 hover:bg-slate-100 hover:text-slate-900 transition-colors">
          <i class="fa-solid fa-chevron-right text-[10px]"></i>
        </a>
      @else
        <span class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-300 cursor-not-allowed border border-slate-200">
          <i class="fa-solid fa-chevron-right text-[10px]"></i>
        </span>
      @endif
    </div>
  @endif
</div>
