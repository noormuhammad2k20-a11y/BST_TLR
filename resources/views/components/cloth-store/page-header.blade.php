{{--
  Standard Cloth Store page heading.

  Mirrors the Tailor system's header exactly (text-xl bold title, muted
  subtitle, right-aligned action buttons) so the two apps read as one product.

  Usage:
    <x-cloth-store.page-header title="Products" subtitle="42 products">
      <x-slot:actions>
        <button class="btn-cs-primary">Add</button>
      </x-slot:actions>
    </x-cloth-store.page-header>
--}}
@props(['title', 'subtitle' => null])

<div class="page flex justify-between items-start gap-3 flex-wrap mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">{{ $title }}</h1>
    @if($subtitle)
      <p class="text-sm text-slate-500 mt-0.5">{{ $subtitle }}</p>
    @endif
  </div>

  @isset($actions)
    <div class="flex items-center gap-2 flex-wrap">{{ $actions }}</div>
  @endisset
</div>
