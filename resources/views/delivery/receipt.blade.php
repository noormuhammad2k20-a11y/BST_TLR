<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Final Receipt — {{ $receipt['order'] }}</title>
  <style>
    @include('receipts.slip-styles')
    body { margin:0; padding:24px; background:#f8fafc; font-family:Inter,system-ui,sans-serif; }
    .slip { width:{{ $receipt['width'] === '58mm' ? '50mm' : '72mm' }}; margin:auto; overflow-wrap:anywhere; }
    .slip-row { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.3fr); gap:2mm; }
    .slip-row .k { word-break:normal; }
    .slip-row .v { white-space:normal; overflow-wrap:anywhere; }
    .slip-credit .name { display:block; }
    .receipt-actions { text-align:center; margin:0 0 20px; }
    .receipt-actions button { background:#0f172a; color:white; padding:10px 18px; border:0; border-radius:8px; font:inherit; cursor:pointer; }
    @media print {
      @page { margin:3mm; }
      body { background:white; padding:0; }
      .receipt-actions { display:none; }
      .slip { margin:0; box-shadow:none; }
      .slip-kind { background:white; color:black; border:1px solid black; }
    }
  </style>
</head>
<body>
  <div class="receipt-actions"><button onclick="window.print()">Print Final Receipt</button></div>
  <main class="slip slip-preview" id="final-receipt">
    <div class="slip-hd">
      @if($receipt['logo'])<img class="slip-logo" src="{{ $receipt['logo'] }}" alt="Shop logo">@endif
      @if($receipt['store'])<div class="slip-shop">{{ $receipt['store'] }}</div>@endif
      @if($receipt['tagline'])<div class="slip-tag">{{ $receipt['tagline'] }}</div>@endif
      <div class="slip-meta">{{ $receipt['address'] }}<br>{{ $receipt['phone'] }}</div>
    </div>
    <div class="slip-kind">FINAL RECEIPT</div>
    <div class="slip-row"><span class="k">Order</span><strong class="v">{{ $receipt['order'] }}</strong></div>
    <div class="slip-row"><span class="k">Invoice</span><span class="v">{{ $receipt['invoice'] }}</span></div>
    <div class="slip-row"><span class="k">Collected</span><span class="v">{{ \App\Services\Dates::formatWithTime($order->delivered_at) }}</span></div>
    <div class="slip-rule"></div>
    <div class="slip-row"><span class="k">Customer</span><strong class="v">{{ $receipt['customer'] }}</strong></div>
    @if($receipt['customer_ph'])<div class="slip-row"><span class="k">Phone</span><span class="v">{{ $receipt['customer_ph'] }}</span></div>@endif
    <div class="slip-rule"></div>
    <div class="slip-sec">ITEMS COLLECTED</div>
    @foreach($receipt['items'] as $item)
      <div class="slip-row"><span class="k">{{ $item['name'] }} × {{ $item['qty'] }}</span><span class="v">{{ \App\Services\Money::format($item['price'], true) }}</span></div>
      @if(!empty($item['desc']))<div class="slip-sub">{{ $item['desc'] }}</div>@endif
    @endforeach
    <div class="slip-rule"></div>
    @foreach($receipt['lines'] as $line)
      <div class="slip-row"><span class="k">{{ $line['label'] }}</span><span class="v">{{ \App\Services\Money::format($line['amount'], true) }}</span></div>
    @endforeach
    @if($receipt['advance'] !== null)<div class="slip-row"><span class="k">Total Paid</span><span class="v">{{ \App\Services\Money::format($receipt['advance'], true) }}</span></div>@endif
    @if($receipt['balance'] !== null)<div class="slip-total"><span>BALANCE</span><span>{{ \App\Services\Money::format($receipt['balance'], true) }}</span></div>@endif
    <div class="slip-rule-d"></div>
    @isset($dues)
    <div class="slip-row"><span class="k">Current Order Due</span><span class="v">{{ \App\Services\Money::format($dues['current_order_due'],true) }}</span></div>
    <div class="slip-row"><span class="k">Previous Due</span><span class="v">{{ \App\Services\Money::format($dues['previous_due'],true) }}</span></div>
    <div class="slip-row slip-bold"><span class="k">Customer Total Due</span><span class="v">{{ \App\Services\Money::format($dues['customer_total_due'],true) }}</span></div>
    <div class="slip-rule"></div>
    @endisset
    <div class="slip-foot">Collected by customer · {{ $order->payment_status === 'Paid' ? 'Fully Paid' : 'Payment Outstanding' }}</div>
    @if($receipt['stamp'])<img class="slip-logo" src="{{ $receipt['stamp'] }}" alt="Shop stamp">@endif
    @if($receipt['terms'])<div class="slip-foot">{{ $receipt['terms'] }}</div>@endif
    @php
      // Keep the shop message while printing the existing software credit only once.
      $footer = collect(preg_split('/\R/u', $receipt['footer'] ?? ''))->map(fn($line) => trim($line))
        ->reject(fn($line) => preg_match('/developed|designed|powered|management system|hingorjo|\bPOS\b|^thank\s*you[!. ]*$|^[\s─_\-–—=~.*]+$/iu', $line)
          || str_contains(preg_replace('/\D/', '', $line), '03034980786'))->filter()->implode("\n");
    @endphp
    @if($footer)<div class="slip-foot">{{ $footer }}</div>@endif
    @if($receipt['barcode'])<div class="slip-code">* {{ $receipt['barcode'] }} *</div>@endif
    <div class="slip-rule"></div>
    <footer class="slip-credit">
      <div>Designed &amp; Developed by <span class="name">Noor M Hingorjo</span></div>
      <div class="sys">TAILORING &amp; CLOTH HOUSE MANAGEMENT SYSTEM</div>
      <div class="tel">0303 4980786</div>
      <div class="ty">Thank You!</div>
    </footer>
  </main>
</body>
</html>
