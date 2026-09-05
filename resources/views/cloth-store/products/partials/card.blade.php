@php
  /**
   * One product card.
   *
   * Rendered server-side so the browser never receives the whole product
   * table. $p is an App\Models\ClothStore\Product; $i is the loop index, used
   * only to stagger the entrance animation.
   */
  $i = $i ?? 0;
  $level = $p->alert_level;

  // [text class, icon, pill classes]
  $levelStyles = [
      'Out of Stock' => ['text-red-600',    'fa-circle-exclamation',   'bg-red-50 text-red-600'],
      'Critical'     => ['text-red-600',    'fa-triangle-exclamation', 'bg-red-50 text-red-600'],
      'Low'          => ['text-amber-600',  'fa-triangle-exclamation', 'bg-amber-50 text-amber-700'],
      'Ignored'      => ['text-slate-400',  '',                        'bg-slate-100 text-slate-500'],
      'Healthy'      => ['text-slate-600',  '',                        'bg-emerald-50 text-emerald-600'],
  ];

  [$stockClass, $stockIcon, $pillClass] = $levelStyles[$level] ?? $levelStyles['Healthy'];

  $qty = (float) $p->stock_quantity;
  $threshold = max((float) $p->low_stock_threshold, 0.01);

  // Fabric sells in fractional metres — show decimals only when there are any,
  // so "3 pcs" doesn't become the odd-looking "3.00 pcs".
  $stockLabel = fmod($qty, 1.0) === 0.0 ? number_format($qty, 0) : number_format($qty, 2);

  // Fill bar is relative to twice the reorder threshold, so a healthy product
  // sits around the middle rather than pinning at 100% and telling you nothing.
  $fill = max(0, min(100, ($qty / ($threshold * 2)) * 100));

  $barColor = match ($level) {
      'Out of Stock', 'Critical' => 'bg-red-500',
      'Low'                      => 'bg-amber-500',
      'Ignored'                  => 'bg-slate-300',
      default                    => 'bg-emerald-500',
  };
@endphp

<div class="card-in group bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all overflow-hidden flex flex-col"
     style="animation-delay: {{ min($i * 40, 400) }}ms">

  <div class="p-3 flex-1">
    <div class="flex justify-between items-start mb-2 gap-2">
      <div class="flex items-center gap-2 min-w-0">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $p->category?->color_bg ?? 'bg-slate-100' }} {{ $p->category?->color_text ?? 'text-slate-600' }}">
          <i class="fa-solid {{ $p->category?->icon ?? 'fa-shirt' }} text-xs"></i>
        </div>
        <div class="min-w-0">
          <div class="text-sm font-semibold text-slate-900 tracking-tight truncate" title="{{ $p->name }}">{{ $p->name }}</div>
          <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest truncate">
            {{ $p->category?->name ?? 'Uncategorized' }}@if($p->sku) · {{ $p->sku }}@endif
          </div>
        </div>
      </div>

      {{-- Actions stay quiet until the card is hovered or keyboard-focused. --}}
      <div class="flex gap-1 shrink-0 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
        <button class="btn-cs-icon"
                data-product="{{ json_encode($p) }}"
                onclick="editProduct(this)"
                aria-label="Edit {{ $p->name }}">
          <i class="fa-solid fa-pen text-[10px]"></i>
        </button>
        <button class="btn-cs-icon danger"
                data-id="{{ $p->id }}"
                data-name="{{ $p->name }}"
                onclick="confirmDelete(this.dataset.id, this.dataset.name)"
                aria-label="Delete {{ $p->name }}">
          <i class="fa-solid fa-trash text-[10px]"></i>
        </button>
      </div>
    </div>

    {{--
      Compact meta strip.
      · Status pill always shown (Active / Inactive is core info).
      · Alert-level pill only when the level is actionable — Healthy adds no
        information the stock bar already conveys, so it was pure visual noise
        and has been removed.
      · Description only renders when the product actually has one; the old
        'No description available.' placeholder has been dropped as well.
      Wrapped in a slate-50 chip so the pills read as one grouped block — the
      "premium container" look the request asked for at this density.
    --}}
    @php
      $hasAlert = $level !== 'Healthy';
      $hasDesc  = filled($p->description);
    @endphp

    @if($hasAlert || $hasDesc)
      <div class="mb-2 rounded-lg bg-slate-50 ring-1 ring-slate-100 px-2.5 py-2 space-y-1.5">
        <div class="flex items-center gap-1.5">
          <span class="badge {{ $p->status === 'Active' ? 'badge-delivered' : 'badge-overdue' }}">{{ $p->status }}</span>
          @if($hasAlert)
            <span class="badge {{ $pillClass }}">{{ $level }}</span>
          @endif
        </div>

        @if($hasDesc)
          <p class="text-[11px] text-slate-500 line-clamp-2 leading-snug">{{ $p->description }}</p>
        @endif
      </div>
    @else
      {{-- Healthy product with no description: keep the status pill alone,
           no chip — the card stays tight without an empty container. --}}
      <div class="mb-2 flex items-center gap-1.5">
        <span class="badge {{ $p->status === 'Active' ? 'badge-delivered' : 'badge-overdue' }}">{{ $p->status }}</span>
      </div>
    @endif
  </div>

  <div class="px-3 pb-3">
    {{-- Stock level bar: reads faster than a number alone. --}}
    <div class="flex items-center justify-between mb-1">
      <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Stock</span>
      <span class="text-[10px] font-semibold {{ $stockClass }} cell-num">
        @if($stockIcon)<i class="fa-solid {{ $stockIcon }} mr-1"></i>@endif
        {{ $stockLabel }} {{ $p->unit }}
      </span>
    </div>
    <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden" role="presentation">
      <div class="h-full rounded-full {{ $barColor }} transition-all duration-500" style="width: {{ $fill }}%"></div>
    </div>

    <div class="flex items-end justify-between mt-2 pt-2 border-t border-slate-100">
      <div>
        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Selling Price</div>
        <div class="text-sm font-bold text-slate-900 cell-num">
          Rs {{ number_format((float) $p->price, 2) }}
          <span class="text-[10px] font-normal text-slate-400">/ {{ $p->unit }}</span>
        </div>
      </div>
      @if($p->cost_price)
        <div class="text-right">
          <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Margin</div>
          @php
            $margin = (float) $p->price > 0
                ? (((float) $p->price - (float) $p->cost_price) / (float) $p->price) * 100
                : 0;
          @endphp
          <div class="text-xs font-semibold cell-num {{ $margin > 0 ? 'text-emerald-600' : 'text-red-500' }}">
            {{ number_format($margin, 1) }}%
          </div>
        </div>
      @endif
    </div>
  </div>
</div>
