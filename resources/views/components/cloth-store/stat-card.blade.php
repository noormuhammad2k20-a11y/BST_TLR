@props([
    'label',
    'value',
    'icon' => 'fa-chart-simple',
    'tone' => 'indigo',
    'sub'  => null,
])

@php
  /**
   * KPI tile, matching the Tailor stat cards.
   *
   * Tone classes are written out in full rather than interpolated: when the
   * app is served from a compiled Tailwind build, a class assembled at runtime
   * like "bg-{$tone}-50" is never seen by the compiler and silently renders
   * unstyled. A lookup keeps every class literal in the source.
   */
  $tones = [
      'indigo'  => 'bg-indigo-50 text-indigo-600',
      'emerald' => 'bg-emerald-50 text-emerald-600',
      'amber'   => 'bg-amber-50 text-amber-600',
      'red'     => 'bg-red-50 text-red-600',
      'sky'     => 'bg-sky-50 text-sky-600',
      'violet'  => 'bg-violet-50 text-violet-600',
      'slate'   => 'bg-slate-100 text-slate-600',
  ];

  $chip = $tones[$tone] ?? $tones['indigo'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5']) }}>
  <div class="flex items-center justify-between mb-3">
    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{{ $label }}</span>
    <div class="w-7 h-7 rounded-md {{ $chip }} flex items-center justify-center shrink-0">
      <i class="fa-solid {{ $icon }} text-[11px]"></i>
    </div>
  </div>

  <h3 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $value }}</h3>

  @if($sub)
    <p class="text-[11px] text-slate-500 mt-1">{{ $sub }}</p>
  @endif
</div>
