{{--
  POS product cards.
  Compact VERTICAL design optimized for dense grids (100+ items).
  Each card is a fixed-height tile with image, name, category, price, stock, and add button.
--}}
@foreach($products as $p)
  @php
    $stock = (float) $p->stock_quantity;
    $threshold = (float) $p->low_stock_threshold;
    $isLow = $stock > 0 && $stock <= $threshold;
    $stockLabel = fmod($stock, 1.0) === 0.0 ? number_format($stock, 0) : number_format($stock, 2);
    $imgUrl = $p->image_url ?? $p->image ?? '';
  @endphp

  <div class="pos-product-card {{ $stock <= 0 ? 'is-out' : '' }}"
       data-id="{{ $p->id }}"
       data-name="{{ $p->name }}"
       data-price="{{ (float) $p->price }}"
       data-stock="{{ $stock }}"
       data-cat="{{ $p->cs_category_id }}"
       data-unit="{{ $p->unit }}"
       data-sku="{{ $p->sku }}"
       data-barcode="{{ $p->barcode }}"
       onclick="{{ $p->unit == 'meter' ? "toggleCutUI('{$p->id}', this)" : "addToCart('{$p->id}')" }}">

      {{-- Out of stock badge --}}
      @if($stock <= 0)
        <div class="pos-card-oos-badge">OUT</div>
      @endif

      {{-- Quantity badge (toggled by JS when in cart) --}}
      <div class="pos-card-qty-badge" id="pos-qty-badge-{{ $p->id }}"></div>

      {{-- Compact image area (Textual Article Badge) --}}
      <div class="pos-card-img" style="display: flex; flex-direction: column; justify-content: center; align-items: center; background: #f8fafc; text-align: center; position: relative;">
        @php
            $name = $p->name;
            $code = '';
            $map = [
                'Supreme Boski' => 'SB',
                'King Wool' => 'KW',
                'Kashmiri Wool' => 'KW',
                'Tehzeeb' => 'TZ',
                'Fontana' => 'FN',
                'Pioneer By Miandad' => 'PBM',
                'Elegant w/w' => 'EWW',
                'Hitachi' => 'HT',
                'Makhmal' => 'MK',
                'Summer Wibe' => 'SW',
                'Shadow Pearl' => 'SP',
                'Imperial' => 'IMP',
                'Play Boy' => 'PB',
                'Master Kong' => 'MK',
                'Ivory Strom' => 'IS',
                'Crystal Wave' => 'CW',
                'Noble Frost' => 'NF',
                'London Twill' => 'LT',
                'Bell Line Cotton' => 'BLC',
                'Turkiya Cotton' => 'TC',
                'Cool Breeze' => 'CB',
                'Onyx By Wijdan' => 'OBW',
                'Passion' => 'PS',
                'Charcoal' => 'CH',
                'Gloria' => 'GL',
                'Rang-e-Mehfil D-01' => 'RM01',
                'Rang-e-Mehfil D-02' => 'RM02',
                'Rang-e-Mehfil D-03' => 'RM03',
                'Rang-e-Mehfil D-04' => 'RM04',
                'Rang-e-Mehfil D-05' => 'RM05',
                'Chairman Latha' => 'CL',
                'Bemisaal Good Luck' => 'BGL',
                'Gul 900 Castor' => 'G900',
                'GUL Panther' => 'GP',
                'Vision Opera' => 'VO',
            ];

            if (array_key_exists($name, $map)) {
                $code = $map[$name];
            } else {
                $words = explode(' ', preg_replace('/[^A-Za-z0-9 ]/', '', $name));
                foreach(array_slice($words, 0, 3) as $w) {
                    if(!empty($w)) $code .= strtoupper($w[0]);
                }
                if (empty($code)) $code = 'FAB';
            }
        @endphp
        
        <div style="position: absolute; top: 6px; left: 6px; background: #0f172a; color: #fff; font-size: 0.75rem; font-weight: 800; padding: 2px 6px; border-radius: 4px;">
          {{ $code }}
        </div>
        
        @if($p->category)
        <div style="position: absolute; top: 6px; right: 6px; background: #e2e8f0; color: #475569; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 4px; text-transform: uppercase;">
          @php
             // Simplify brand names: "Miandad Fabrics" -> "MIANDAD"
             $brandName = strtoupper(str_replace([' Fabrics', ' FABRICS'], '', $p->category->name));
          @endphp
          {{ $brandName }}
        </div>
        @endif

        <div style="font-size: 1rem; font-weight: 800; color: #1e293b; padding: 0 10px; line-height: 1.2;">
          {{ strtoupper($p->name) }}
        </div>
      </div>

      {{-- Product info --}}
      <div class="pos-card-body">
        <h3 class="pos-card-name" title="{{ $p->name }}">{{ $p->name }}</h3>
        <div class="pos-card-meta">
          <span class="pos-card-cat">{{ $p->category?->name ?? 'General' }}</span>
          <span class="pos-card-dot">·</span>
          <span class="pos-card-unit">{{ $p->unit == 'meter' ? 'Meter' : 'Piece' }}</span>
        </div>
        <div class="pos-card-footer">
          <span class="pos-card-price">Rs {{ number_format((float) $p->price) }}</span>
          @if($stock > 0)
            <span class="pos-card-stock {{ $isLow ? 'is-low' : '' }}">
              <span class="pos-card-stock-dot"></span>
              {{ $stockLabel }}
            </span>
          @endif
        </div>
      </div>

      {{-- Add button --}}
      <div class="pos-card-add">
        <i class="fa-solid {{ $p->unit == 'meter' ? 'fa-scissors' : 'fa-plus' }}"></i>
      </div>

      {{-- Inline Cut Management UI (Hidden by default, meter only) --}}
      @if($p->unit == 'meter')
      <div id="cut-ui-{{ $p->id }}" class="pos-cut-ui hidden" onclick="event.stopPropagation()">
        <div class="pos-cut-ui-inner">
          <div class="pos-cut-input-row">
             <input type="number" id="cut-input-{{ $p->id }}" class="pos-cut-input cell-num" step="0.01" min="0.01" placeholder="Length (m)" onkeydown="if(event.key==='Enter') addCut('{{ $p->id }}')">
             <button type="button" class="btn-cs-primary pos-cut-btn" onclick="addCut('{{ $p->id }}')">Add</button>
          </div>
          <div id="cut-list-{{ $p->id }}" class="pos-cut-list"></div>
          <div class="pos-cut-summary">
             <div>Total <span id="cut-total-m-{{ $p->id }}" class="cell-num font-bold text-slate-900" style="color: #0f172a;">0.00 m</span></div>
             <div id="cut-total-rs-{{ $p->id }}" class="cell-num font-bold text-slate-900" style="color: #0f172a;">Rs 0</div>
          </div>
          <div class="pos-cut-remaining">
            Avail: <span id="cut-stock-{{ $p->id }}" class="cell-num">{{ $stock }} m</span>
          </div>
        </div>
      </div>
      @endif
  </div>
@endforeach