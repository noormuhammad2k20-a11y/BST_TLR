{{--
  The white surface that wraps a table or list, matching Tailor.

  Slots:
    toolbar  — filter/search row, rendered above the content with a divider
    default  — the table (or any body content)
    footer   — usually <x-cloth-store.pagination>

  `flush` drops the body padding, which is what a full-bleed <table> wants.

  Every colour here is a plain Tailwind slate class on purpose: the layout's
  dark theme overrides exactly those class names, so using them is what makes
  the panel theme-aware. Custom hex values would not follow the theme.
--}}
@props(['flush' => true])

<div {{ $attributes->merge(['class' => 'page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden']) }}>
  @isset($toolbar)
    <div class="p-4 border-b border-slate-200 flex items-center justify-between gap-3 flex-wrap bg-white">
      {{ $toolbar }}
    </div>
  @endisset

  <div class="{{ $flush ? 'overflow-x-auto' : 'p-5' }}">
    {{ $slot }}
  </div>

  @isset($footer)
    <div class="px-5 py-3 border-t border-slate-200 bg-slate-50">
      {{ $footer }}
    </div>
  @endisset
</div>
