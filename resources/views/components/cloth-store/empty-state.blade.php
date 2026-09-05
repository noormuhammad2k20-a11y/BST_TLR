{{--
  Empty state for a table body or a grid.

  Pass `colspan` when used inside a <tbody> so it renders as a full-width row;
  omit it to render as a plain block (for card grids).
--}}
@props([
    'icon'    => 'fa-inbox',
    'title'   => 'Nothing here yet',
    'message' => null,
    'colspan' => null,
])

@if($colspan)
  <tr>
    <td colspan="{{ $colspan }}" class="p-0">
@endif

<div class="py-14 px-6 text-center">
  <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
    <i class="fa-solid {{ $icon }} text-base"></i>
  </div>
  <div class="text-sm font-semibold text-slate-700">{{ $title }}</div>
  @if($message)
    <div class="text-xs text-slate-500 mt-1 max-w-xs mx-auto leading-relaxed">{{ $message }}</div>
  @endif
  @isset($action)
    <div class="mt-4">{{ $action }}</div>
  @endisset
</div>

@if($colspan)
    </td>
  </tr>
@endif
