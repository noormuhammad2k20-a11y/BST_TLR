<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Final Receipt — {{ $receipt['order'] }}</title>
  <style>
    @include('receipts.slip-styles')
    
    @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap');

    body { margin:0; padding:24px; background:#f8fafc; display:flex; flex-direction:column; align-items:center; font-family: 'Space Grotesk', sans-serif; -webkit-font-smoothing: antialiased; }
    
    .receipt-actions { text-align:center; margin:0 0 20px; }
    .receipt-actions button { background:#0f172a; color:white; padding:10px 18px; border:0; border-radius:8px; font:inherit; cursor:pointer; }
    
    /* CUSTOMER COPY RECEIPT DESIGN */
    .rc {
      width: {{ $receipt['width'] === '58mm' ? '58mm' : '302px' }};
      background: #fff;
      color: #141414;
      padding: 19px 15px 21px;
      font: 400 10px/1.5 'IBM Plex Mono', monospace;
      border: 1px solid #BFBAB0; /* screen only */
    }
    .rc-head { text-align: center; }
    .rc-name { font: 700 23px/1.1 'Space Grotesk', sans-serif; letter-spacing: .03em; color: #000; }
    .rc-tag { display: flex; align-items: center; gap: 8px; margin: 9px 0 0; }
    .rc-tag::before, .rc-tag::after { content: ''; flex: 1; height: 1px; background: #000; }
    .rc-tag span { font: 600 7.5px 'Space Grotesk', sans-serif; letter-spacing: .32em; margin-right: -.32em; text-transform: uppercase; white-space: nowrap; }
    .rc-addr { font: 500 9.5px/1.55 'IBM Plex Mono', monospace; color: #000; margin-top: 8px; }
    .rc-ph { font: 600 8.5px 'IBM Plex Mono', monospace; margin-top: 2px; }
    .rc-rule { height: 1.5px; background: #000; border: 0; margin: 12px 0 14px; }
    .rc-doc { display: flex; justify-content: center; margin: 3px 0 13px; }
    .rc-doc b { font: 700 8.5px 'Space Grotesk', sans-serif; letter-spacing: .3em; margin-right: -.3em; text-transform: uppercase; color: #000; }
    .rc-meta { display: flex; flex-direction: column; gap: 6px; margin: 0 0 3px; }
    .rc-m { display: flex; align-items: baseline; gap: 6px; }
    .rc-m .k { font: 600 8px 'Space Grotesk', sans-serif; letter-spacing: .12em; color: #5c5c5c; white-space: nowrap; text-transform: uppercase; }
    .rc-m .dots { flex: 1; min-width: 12px; border-bottom: 1px dotted #9a9a9a; transform: translateY(-3px); }
    .rc-m .v { font: 500 10.5px 'IBM Plex Mono', monospace; color: #000; word-break: break-word; overflow-wrap: anywhere;}
    .rc-m .v.b { font-weight: 700; }
    .rc-sec { display: flex; align-items: center; gap: 8px; margin: 16px 0 8px; }
    .rc-sec::before, .rc-sec::after { content: ''; flex: 1; height: 1px; background: #000; }
    .rc-sec span { font: 700 8px 'Space Grotesk', sans-serif; letter-spacing: .3em; margin-right: -.3em; text-transform: uppercase; }
    .rc-item { padding: 7px 0; }
    .rc-item + .rc-item { border-top: 1px dashed #d5d5d5; }
    .rc-i1 { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
    .rc-i1 .nm { font: 600 11px 'Space Grotesk', sans-serif; color: #000; word-break: break-word; overflow-wrap: anywhere;}
    .rc-i1 .qt { font: 500 9px 'IBM Plex Mono', monospace; color: #555; white-space: nowrap; }
    .rc-i2 { display: flex; align-items: baseline; gap: 6px; margin-top: 2px; }
    .rc-i2 .rt { font: 400 8.5px 'IBM Plex Mono', monospace; color: #5a5a5a; white-space: nowrap; }
    .rc-i2 .dots { flex: 1; min-width: 10px; border-bottom: 1px dotted #a5a5a5; transform: translateY(-3px); }
    .rc-i2 .tt { font: 700 11px 'IBM Plex Mono', monospace; white-space: nowrap;}
    .rc-tot { margin-top: 3px; }
    .rc-tr { display: flex; align-items: baseline; gap: 6px; padding: 3.5px 0; }
    .rc-tr .k { font: 600 8px 'Space Grotesk', sans-serif; letter-spacing: .14em; color: #555; white-space: nowrap; text-transform: uppercase; }
    .rc-tr .dots { flex: 1; min-width: 10px; border-bottom: 1px dotted #a5a5a5; transform: translateY(-3px); }
    .rc-tr .v { font: 600 10.5px 'IBM Plex Mono', monospace; white-space: nowrap;}
    .rc-tr.due .k { color: #000; }
    .rc-tr.due .v { font-weight: 700; font-size: 11.5px; }
    .rc-tr.sub .k { color: #666; }
    .rc-tr.sub .v { font-weight: 500; font-size: 9.5px; }
    .rc-tb { border: 1.5px solid #000; border-radius: 3px; margin: 10px 0 8px; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; }
    .rc-tb span { font: 700 9px 'Space Grotesk', sans-serif; letter-spacing: .24em; text-transform: uppercase; }
    .rc-tb b { font: 700 15px 'IBM Plex Mono', monospace; white-space: nowrap;}
    .rc-stat { margin-top: 13px; text-align: center; font: 700 7.5px 'Space Grotesk', sans-serif; letter-spacing: .18em; border: 1px solid #000; border-radius: 3px; padding: 6px 4px; text-transform: uppercase; }
    .rc-note { text-align: center; font: 400 8.5px/1.65 'IBM Plex Mono', monospace; color: #333; margin-top: 13px; }
    .rc-credit { margin-top: 15px; border: 1px solid #000; border-radius: 3px; padding: 10px 10px 12px; text-align: center; }
    .rc-credit .c1 { font: 600 7px 'IBM Plex Mono', monospace; color: #000; letter-spacing: .28em; margin-right: -.28em; text-transform: uppercase; }
    .rc-credit .c2 { font: 700 7.5px/1.6 'Space Grotesk', sans-serif; letter-spacing: .04em; margin-top: 4px; color: #000; text-transform: uppercase; }
    .rc-credit .cline { display: block; width: 24px; height: 1.5px; background: #000; margin: 7px auto; }
    .rc-credit .c3 { font: 700 8.5px 'Space Grotesk', sans-serif; letter-spacing: .08em; color: #000; text-transform: uppercase; }
    .rc-credit .c3 span { font: 600 7.5px 'IBM Plex Mono', monospace; color: #000; letter-spacing: .08em; margin-right: 6px; }
    .rc-credit .c4 { font: 600 9px 'IBM Plex Mono', monospace; color: #000; letter-spacing: .03em; margin-top: 5px; }
    .rc-credit .c4 span { font: 600 7.5px 'IBM Plex Mono', monospace; color: #000; letter-spacing: .08em; margin-right: 6px; text-transform: uppercase; }
    .rc-thx { display: flex; align-items: center; gap: 8px; margin-top: 13px; }
    .rc-thx::before, .rc-thx::after { content: ''; flex: 1; height: 1px; background: #000; }
    .rc-thx span { font: 700 8.5px 'Space Grotesk', sans-serif; letter-spacing: .4em; margin-right: -.4em; text-transform: uppercase; color: #000; }

    @media print {
      @page { margin: 0; size: 80mm auto; }
      body { background: white !important; padding: 0 !important; display: block !important; }
      .receipt-actions { display: none !important; }
      
      .rc {
        width: {{ $receipt['width'] === '58mm' ? '58mm' : '80mm' }};
        box-sizing: border-box;
        border: none;
      }
      
      *, *::before, *::after {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
  </style>
</head>
<body>
  <div class="receipt-actions"><button onclick="window.print()">Print Final Receipt</button></div>
  <main class="rc" id="final-receipt">
    <header class="rc-head">
      @if($receipt['store'])<div class="rc-name">{{ $receipt['store'] }}</div>@endif
      @if($receipt['tagline'])<div class="rc-tag"><span>{{ $receipt['tagline'] }}</span></div>@endif
      <div class="rc-addr">{{ $receipt['address'] }}</div>
      @if($receipt['phone'])<div class="rc-ph">PH · {{ $receipt['phone'] }}</div>@endif
    </header>
    
    <div class="rc-rule"></div>
    <div class="rc-doc"><b>FINAL RECEIPT · CUSTOMER COPY</b></div>
    <div class="rc-meta">
      <div class="rc-m">
        <span class="k">Order</span><i class="dots"></i><span class="v b">{{ $receipt['order'] }}</span>
      </div>
      @if($receipt['invoice'])
      <div class="rc-m">
        <span class="k">Invoice</span><i class="dots"></i><span class="v">{{ $receipt['invoice'] }}</span>
      </div>
      @endif
      <div class="rc-m">
        <span class="k">Collected</span><i class="dots"></i><span class="v">{{ \App\Services\Dates::formatWithTime($order->delivered_at) }}</span>
      </div>
      <div class="rc-m">
        <span class="k">Customer</span><i class="dots"></i><span class="v b">{{ $receipt['customer'] }}</span>
      </div>
      @if($receipt['customer_ph'])
      <div class="rc-m">
        <span class="k">Phone</span><i class="dots"></i><span class="v">{{ $receipt['customer_ph'] }}</span>
      </div>
      @endif
    </div>
    
    <div class="rc-sec"><span>Items Collected</span></div>
    <div class="rc-items">
      @foreach($receipt['items'] as $item)
        @php
          $qty = max((int)($item['qty'] ?? 1), 1);
          $unit = ($item['price'] ?? 0) / $qty;
        @endphp
        <div class="rc-item">
          <div class="rc-i1">
            <span class="nm">{{ $item['name'] }}</span>
            <span class="qt">× {{ $item['qty'] }}</span>
          </div>
          <div class="rc-i2">
            <span class="rt">{{ \App\Services\Money::format($unit, true) }} each</span>
            <i class="dots"></i>
            <span class="tt">{{ \App\Services\Money::format($item['price'], true) }}</span>
          </div>
        </div>
      @endforeach
    </div>
    
    <div class="rc-sec"><span>Payment</span></div>
    <div class="rc-tot">
      @foreach($receipt['lines'] as $line)
        @if(strtolower($line['label']) === 'total')
          <div class="rc-tb"><span>Total</span><b>{{ \App\Services\Money::format($line['amount'], true) }}</b></div>
        @else
          <div class="rc-tr sub"><span class="k">{{ $line['label'] }}</span><i class="dots"></i><span class="v">{{ \App\Services\Money::format($line['amount'], true) }}</span></div>
        @endif
      @endforeach
      
      @if($receipt['advance'] !== null)
      <div class="rc-tr">
        <span class="k">Total Paid</span><i class="dots"></i><span class="v">{{ \App\Services\Money::format($receipt['advance'], true) }}</span>
      </div>
      @endif
      
      @if($receipt['balance'] !== null)
      <div class="rc-tr due">
        <span class="k">Balance</span><i class="dots"></i><span class="v">{{ \App\Services\Money::format($receipt['balance'], true) }}</span>
      </div>
      @endif
      
      @isset($dues)
      <div class="rc-tr due">
        <span class="k">Current Order Due</span><i class="dots"></i><span class="v">{{ \App\Services\Money::format($dues['current_order_due'], true) }}</span>
      </div>
      <div class="rc-tr due">
        <span class="k">Previous Due</span><i class="dots"></i><span class="v">{{ \App\Services\Money::format($dues['previous_due'], true) }}</span>
      </div>
      <div class="rc-tr due">
        <span class="k">Customer Total Due</span><i class="dots"></i><span class="v">{{ \App\Services\Money::format($dues['customer_total_due'], true) }}</span>
      </div>
      @endisset
    </div>
    
    <div class="rc-stat">COLLECTED BY CUSTOMER · {{ $order->payment_status === 'Paid' ? 'FULLY PAID' : 'PAYMENT PENDING' }}</div>
    
    @if($receipt['stamp'])
      <div style="text-align:center; margin:8px 0">
        <img src="{{ $receipt['stamp'] }}" style="max-height:48px; max-width:100%; opacity:.85" alt="Shop stamp">
      </div>
    @endif
    
    @if($receipt['terms'])
      <div class="rc-note" style="margin-top:2mm; text-align:center">{{ $receipt['terms'] }}</div>
    @endif
    
    @php
      // Keep the shop message while printing the existing software credit only once.
      $footer = collect(preg_split('/\R/u', $receipt['footer'] ?? ''))->map(fn($line) => trim($line))
        ->reject(fn($line) => preg_match('/developed|designed|powered|management system|hingorjo|\bPOS\b|^thank\s*you[!. ]*$|^[\s─_\-–—=~.*]+$/iu', $line)
          || str_contains(preg_replace('/\D/', '', $line), '03034980786'))->filter()->implode("\n");
    @endphp
    @if($footer)
      <div class="rc-note" style="text-align:center; white-space:pre-line">{{ $footer }}</div>
    @endif
    
    <p class="rc-note">
      Thank you for choosing us.
    </p>

    <footer class="rc-credit">
      <div class="c3" style="line-height:1.6; margin-top:0;">
        <span style="display:block; margin-right:0; margin-bottom:2px;">DESIGNED &amp; DEVELOPED BY</span>
        NOOR M. HINGORJO
      </div>
      <div class="c4" style="margin-top:6px;">SOFTWARE SUPPORT: 0303 4980786</div>
    </footer>
    
    <div class="rc-thx"><span>Thank You</span></div>
    
    @if($receipt['barcode'])
      <div class="rc-note" style="margin-top:4px">* {{ $receipt['barcode'] }} *</div>
    @endif
  </main>
</body>
</html>
