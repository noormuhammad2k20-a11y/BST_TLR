import re

blade_content = """{{-- 
  POS product cards.
  Matches tess.html exactly.
--}}
@foreach($products as $p)
  @php
    $stock = (float) $p->stock_quantity;
    $threshold = (float) $p->low_stock_threshold;
    $isLow = $stock > 0 && $stock <= $threshold;
    $stockLabel = fmod($stock, 1.0) === 0.0 ? number_format($stock, 0) : number_format($stock, 2);
    $imgUrl = $p->image_url ?? $p->image ?? ''; // Fallback for image
  @endphp
  
  <div class="product-card relative flex items-center gap-2.5 p-2.5 bg-white rounded-lg border border-slate-200 cursor-pointer transition-all duration-200 group hover:border-indigo-400 hover:shadow-sm {{ $stock <= 0 ? 'opacity-50 pointer-events-none' : '' }}"
       data-id="{{ $p->id }}"
       data-name="{{ $p->name }}"
       data-price="{{ (float) $p->price }}"
       data-stock="{{ $stock }}"
       data-cat="{{ $p->cs_category_id }}"
       data-unit="{{ $p->unit }}"
       data-sku="{{ $p->sku }}"
       data-barcode="{{ $p->barcode }}"
       onclick="addToCart('{{ $p->id }}')">
       
      @if($stock <= 0)
          <div class="absolute top-1 right-1 z-10 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider text-white bg-rose-500 rounded">Out</div>
      @endif
      <div class="w-11 h-11 shrink-0 rounded-md overflow-hidden bg-slate-50 border border-slate-100 flex items-center justify-center">
          @if($imgUrl)
            <img src="{{ $imgUrl }}" alt="{{ $p->name }}" class="w-full h-full object-cover">
          @else
            <i class="fa-solid fa-tag text-slate-300 text-sm"></i>
          @endif
      </div>
      <div class="flex-1 min-w-0">
          <h3 class="text-[13px] font-semibold text-slate-800 truncate group-hover:text-indigo-600 transition-colors">{{ $p->name }}</h3>
          <div class="flex items-center gap-1.5 mt-0.5">
              <span class="text-[9px] font-medium text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">{{ $p->category?->name ?? 'Uncategorized' }}</span>
              @if($stock > 0)
                <span class="text-[9px] font-medium flex items-center gap-1 {{ $isLow ? 'text-rose-500' : 'text-emerald-500' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $isLow ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                    {{ $stockLabel }} {{ $p->unit == 'meter' ? 'm' : 'pcs' }}
                </span>
              @endif
          </div>
          <p class="text-[13px] font-bold text-slate-900 mt-1">Rs {{ number_format((float) $p->price) }}</p>
      </div>
      <div class="shrink-0 w-7 h-7 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center transition-all group-hover:bg-indigo-600 group-hover:text-white shadow-sm">
          <i class="fa-solid fa-plus text-[10px]"></i>
      </div>
  </div>
@endforeach
"""

with open(r'resources\views\cloth-store\checkout\partials\product-cards.blade.php', 'w', encoding='utf-8') as f:
    f.write(blade_content)

print("Updated product-cards.blade.php successfully")
