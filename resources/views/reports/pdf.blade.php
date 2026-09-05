{{--
  Tailoring module — downloadable report document.

  Every figure here comes from the same StatsService window the on-screen
  report uses, so the PDF can never disagree with the page it was exported
  from. Tables are deliberately dense (7.8pt body, 2.8px row padding) so a
  full month of activity lands on two or three sheets rather than a dozen.
--}}
@extends('pdf.document')

@php
  $cur   = $doc['currency'];
  $money = fn ($v) => $cur . number_format((float) $v);
  $money2 = fn ($v) => $cur . number_format((float) $v, 2);
  $pct   = fn ($part, $whole) => $whole > 0 ? number_format($part / $whole * 100, 1) . '%' : '—';

  $arrow = function (array $delta) {
      if ($delta['direction'] === 'up')   return ['▲', 'up'];
      if ($delta['direction'] === 'down') return ['▼', 'down'];
      return ['', 'muted'];
  };

  $k = $report['kpis'];
@endphp

@section('body')

{{-- ------------------------------- headline KPIs ------------------------ --}}
<table class="kpis keep">
  <tr>
    @php
      $cards = [
        ['Total Revenue',    $money($k['revenue']['value']),          $k['revenue']['delta']],
        ['Orders Completed', number_format($k['completed']['value']), $k['completed']['delta']],
        ['Avg. Order Value', $money($k['avg_order']['value']),        $k['avg_order']['delta']],
        ['Collection Rate',  $k['collection_rate']['value'] . '%',    $k['collection_rate']['delta']],
      ];
    @endphp
    @foreach($cards as [$label, $value, $delta])
      @php [$glyph, $cls] = $arrow($delta); @endphp
      <td>
        <div class="k-label">{{ $label }}</div>
        <div class="k-value">{{ $value }}</div>
        <div class="k-note"><span class="{{ $cls }}">{{ $glyph }} {{ $delta['value'] }}%</span> vs previous period</div>
      </td>
    @endforeach
  </tr>
</table>

{{-- ------------------------------- read-out ----------------------------- --}}
@if(!empty($analytics['insights']))
<div class="section keep">
  <div class="section-title">Period Read-out</div>
  <div class="notes">
    <ul>
      @foreach($analytics['insights'] as $line)
        <li>{{ $line['text'] }}</li>
      @endforeach
    </ul>
  </div>
</div>
@endif

{{-- ------------------------------- target ------------------------------- --}}
@php $t = $analytics['target']; @endphp
@if($t['configured'])
<div class="section keep">
  <div class="section-title">
    Revenue Target
    <span class="count">{{ $money($t['monthly']) }} per month, scaled to {{ $t['days'] }} day(s)</span>
  </div>
  <table class="data">
    <thead>
      <tr>
        <th>Target for this window</th>
        <th class="num">Collected</th>
        <th class="num">Reached</th>
        <th class="num">Still needed</th>
        <th class="num">Days left</th>
        <th class="num">Needed / day</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="b">{{ $money($t['target']) }}</td>
        <td class="num">{{ $money($t['achieved']) }}</td>
        <td class="num b">{{ $t['percent'] }}%</td>
        <td class="num">{{ $money($t['remaining']) }}</td>
        <td class="num">{{ $t['days_left'] }}</td>
        <td class="num">{{ $t['days_left'] > 0 ? $money($t['daily_needed']) : '—' }}</td>
      </tr>
    </tbody>
  </table>
  <div style="margin-top:4px">
    @php $filled = min(max((float) $t['percent'], 0), 100); @endphp
    <table class="bar">
      <tr>
        <td class="fill" style="width:{{ $filled }}%">&nbsp;</td>
        <td style="width:{{ 100 - $filled }}%">&nbsp;</td>
      </tr>
    </table>
    <div class="sub" style="margin-top:3px">
      Expected by now at an even pace: {{ $money($t['expected']) }} ({{ $t['pace_percent'] }}%) —
      {{ $t['on_track'] ? 'on pace.' : 'behind pace.' }}
    </div>
  </div>
</div>
@endif

{{-- ------------------------------- comparison --------------------------- --}}
<div class="section">
  <div class="section-title">
    Period Comparison
    <span class="count">against the previous {{ $analytics['window']['days'] }} day(s)</span>
  </div>
  <table class="data">
    <thead>
      <tr>
        <th>Metric</th>
        <th class="num">This period</th>
        <th class="num">Previous</th>
        <th class="num">Change</th>
        <th class="num">%</th>
      </tr>
    </thead>
    <tbody>
      @foreach($analytics['comparison'] as $i => $row)
        @php
          $fmt = function ($v) use ($row, $money) {
              return match ($row['format']) {
                  'money'   => $money($v),
                  'percent' => $v . '%',
                  default   => number_format($v),
              };
          };
          $glyph = $row['delta']['direction'] === 'up' ? '▲' : ($row['delta']['direction'] === 'down' ? '▼' : '');
        @endphp
        <tr>
          <td>{{ $row['label'] }}</td>
          <td class="num b">{{ $fmt($row['current']) }}</td>
          <td class="num muted">{{ $fmt($row['previous']) }}</td>
          <td class="num">{{ $row['change'] == 0 ? '—' : ($row['change'] > 0 ? '+' : '−') . $fmt(abs($row['change'])) }}</td>
          <td class="num">{{ $glyph }} {{ $row['delta']['value'] }}%</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- ------------------------------- money summary ------------------------ --}}
<div class="section keep">
  <div class="section-title">Financial Summary</div>
  <table class="data">
    <thead>
      <tr>
        <th>Line</th>
        <th class="num">Amount</th>
        <th>Line</th>
        <th class="num">Amount</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Orders placed</td>
        <td class="num">{{ number_format($finance['orders']) }}</td>
        <td>Total invoiced</td>
        <td class="num">{{ $money($finance['invoiced']) }}</td>
      </tr>
      <tr class="alt">
        <td>Payments received</td>
        <td class="num">{{ $money($finance['collected']) }}</td>
        <td>Outstanding balance</td>
        <td class="num {{ $finance['outstanding'] > 0 ? 'down' : '' }}">{{ $money($finance['outstanding']) }}</td>
      </tr>
      <tr>
        <td>Expenses recorded</td>
        <td class="num">{{ $money($finance['expenses']) }}</td>
        <td>Avg. order value</td>
        <td class="num">{{ $money2($k['avg_order']['value']) }}</td>
      </tr>
      <tr class="alt">
        <td class="b">Net position (collected − expenses)</td>
        <td class="num b {{ $finance['net'] < 0 ? 'down' : 'up' }}">{{ $money($finance['net']) }}</td>
        <td class="b">Collection rate</td>
        <td class="num b">{{ $k['collection_rate']['value'] }}%</td>
      </tr>
    </tbody>
  </table>
</div>

{{-- ------------------------------- revenue trend ------------------------ --}}
@php
  $trend = [];
  foreach ($report['revenue_series']['labels'] as $i => $label) {
      $trend[] = ['label' => $label, 'value' => (float) ($report['revenue_series']['data'][$i] ?? 0)];
  }
  $trendTotal = array_sum(array_column($trend, 'value'));
  $trendRows  = array_chunk($trend, 3);   // three period/amount pairs per printed row
@endphp
<div class="section">
  <div class="section-title">
    Revenue Trend
    <span class="count">{{ count($trend) }} periods · total {{ $money($trendTotal) }}</span>
  </div>
  <table class="data fixed">
    <thead>
      <tr>
        @for($c = 0; $c < 3; $c++)
          <th style="width:14%">Period</th>
          <th class="num" style="width:19.3%">Revenue</th>
        @endfor
      </tr>
    </thead>
    <tbody>
      @forelse($trendRows as $r => $row)
        <tr class="{{ $r % 2 ? 'alt' : '' }}">
          @for($c = 0; $c < 3; $c++)
            <td>{{ $row[$c]['label'] ?? '' }}</td>
            <td class="num">{{ isset($row[$c]) ? $money($row[$c]['value']) : '' }}</td>
          @endfor
        </tr>
      @empty
        <tr><td colspan="6" class="empty">No revenue recorded in this period.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- ----------------------- category + status side by side --------------- --}}
@php
  $catTotal    = array_sum($report['category_series']['data'] ?? []);
  $statusTotal = array_sum($report['status_series']['data'] ?? []);
@endphp
<table class="split section">
  <tr>
    <td class="left" style="width:50%">
      <div class="section-title">Sales by Category <span class="count">{{ number_format($catTotal) }} orders</span></div>
      <table class="data">
        <thead><tr><th>Category</th><th class="num">Orders</th><th class="num">Share</th></tr></thead>
        <tbody>
          @forelse($report['category_series']['labels'] as $i => $label)
            @php $v = (int) ($report['category_series']['data'][$i] ?? 0); @endphp
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
              <td>{{ $label }}</td>
              <td class="num">{{ number_format($v) }}</td>
              <td class="num muted">{{ $pct($v, $catTotal) }}</td>
            </tr>
          @empty
            <tr><td colspan="3" class="empty">No categorised orders.</td></tr>
          @endforelse
        </tbody>
      </table>
    </td>
    <td class="right" style="width:50%">
      <div class="section-title">Order Status <span class="count">{{ number_format($statusTotal) }} orders</span></div>
      <table class="data">
        <thead><tr><th>Status</th><th class="num">Orders</th><th class="num">Share</th></tr></thead>
        <tbody>
          @forelse($report['status_series']['labels'] as $i => $label)
            @php $v = (int) ($report['status_series']['data'][$i] ?? 0); @endphp
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
              <td>{{ $label }}</td>
              <td class="num">{{ number_format($v) }}</td>
              <td class="num muted">{{ $pct($v, $statusTotal) }}</td>
            </tr>
          @empty
            <tr><td colspan="3" class="empty">No orders on record.</td></tr>
          @endforelse
        </tbody>
      </table>
    </td>
  </tr>
</table>

{{-- ----------------------- payments + expenses side by side ------------- --}}
<table class="split section">
  <tr>
    <td class="left" style="width:50%">
      <div class="section-title">Payments by Method <span class="count">{{ $money($finance['collected']) }}</span></div>
      <table class="data">
        <thead><tr><th>Method</th><th class="num">Count</th><th class="num">Amount</th><th class="num">Share</th></tr></thead>
        <tbody>
          @forelse($paymentMethods as $i => $p)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
              <td>{{ $p['method'] }}</td>
              <td class="num">{{ number_format($p['count']) }}</td>
              <td class="num">{{ $money($p['total']) }}</td>
              <td class="num muted">{{ $pct($p['total'], $finance['collected']) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="empty">No payments in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </td>
    <td class="right" style="width:50%">
      <div class="section-title">Expenses by Category <span class="count">{{ $money($finance['expenses']) }}</span></div>
      <table class="data">
        <thead><tr><th>Category</th><th class="num">Count</th><th class="num">Amount</th><th class="num">Share</th></tr></thead>
        <tbody>
          @forelse($expenseCategories as $i => $e)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
              <td>{{ $e['category'] }}</td>
              <td class="num">{{ number_format($e['count']) }}</td>
              <td class="num">{{ $money($e['total']) }}</td>
              <td class="num muted">{{ $pct($e['total'], $finance['expenses']) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="empty">No expenses in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </td>
  </tr>
</table>

{{-- ------------------------------- top customers ------------------------ --}}
<div class="section">
  <div class="section-title">
    Customers
    <span class="count">{{ count($topCustomers) }} active in period</span>
  </div>
  <table class="data">
    <thead>
      <tr>
        <th class="idx">#</th>
        <th>Customer</th>
        <th>Phone</th>
        <th class="num">Orders</th>
        <th class="num">Spend</th>
        <th class="num">Avg / Order</th>
        <th class="num">Share</th>
      </tr>
    </thead>
    <tbody>
      @forelse($topCustomers as $i => $c)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
          <td class="idx">{{ $i + 1 }}</td>
          <td class="b">{{ $c['name'] }}</td>
          <td class="muted">{{ $c['phone'] ?? '—' }}</td>
          <td class="num">{{ number_format($c['orders']) }}</td>
          <td class="num">{{ $money($c['spent']) }}</td>
          <td class="num muted">{{ $c['orders'] > 0 ? $money($c['spent'] / $c['orders']) : '—' }}</td>
          <td class="num muted">{{ $pct($c['spent'], $finance['invoiced']) }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="empty">No customer activity in this period.</td></tr>
      @endforelse
    </tbody>
    @if(count($topCustomers))
      <tfoot>
        <tr>
          <td colspan="3">Total ({{ count($topCustomers) }} customers)</td>
          <td class="num">{{ number_format(collect($topCustomers)->sum('orders')) }}</td>
          <td class="num">{{ $money(collect($topCustomers)->sum('spent')) }}</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    @endif
  </table>
</div>

{{-- ------------------------------- tailor output ------------------------ --}}
<div class="section">
  <div class="section-title">
    Tailor Performance
    <span class="count">{{ count($tailors) }} tailors</span>
  </div>
  <table class="data">
    <thead>
      <tr>
        <th class="idx">#</th>
        <th>Tailor</th>
        <th class="num">Orders</th>
        <th class="num">On-time</th>
        <th class="num">On-time %</th>
        <th class="num">Share of workload</th>
      </tr>
    </thead>
    <tbody>
      @php $tailorOrders = collect($tailors)->sum('orders'); @endphp
      @forelse($tailors as $i => $t)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
          <td class="idx">{{ $i + 1 }}</td>
          <td class="b">{{ $t['name'] }}</td>
          <td class="num">{{ number_format($t['orders']) }}</td>
          <td class="num">{{ number_format((int) round($t['orders'] * $t['rate'] / 100)) }}</td>
          <td class="num {{ $t['rate'] >= 90 ? 'up' : ($t['rate'] < 75 ? 'down' : '') }}">{{ $t['rate'] }}%</td>
          <td class="num muted">{{ $pct($t['orders'], $tailorOrders) }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="empty">No orders assigned to tailors in this period.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- ------------------------- ageing + operations ------------------------ --}}
@php $dues = $analytics['dues']; $ret = $analytics['retention']; $del = $analytics['delivery']; @endphp
<table class="split section keep">
  <tr>
    <td class="left" style="width:50%">
      <div class="section-title">
        Outstanding Dues
        <span class="count">{{ $money($dues['total']) }}</span>
      </div>
      <table class="data">
        <thead><tr><th>Age of order</th><th class="num">Orders</th><th class="num">Amount</th><th class="num">Share</th></tr></thead>
        <tbody>
          @foreach($dues['buckets'] as $b)
            <tr>
              <td>{{ $b['label'] }}</td>
              <td class="num">{{ number_format($b['orders']) }}</td>
              <td class="num">{{ $money($b['amount']) }}</td>
              <td class="num muted">{{ $pct($b['amount'], $dues['total']) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <td>Total unpaid</td>
            <td class="num">{{ number_format($dues['orders']) }}</td>
            <td class="num">{{ $money($dues['total']) }}</td>
            <td class="num">{{ $dues['oldest_days'] }}d oldest</td>
          </tr>
        </tfoot>
      </table>
    </td>
    <td class="right" style="width:50%">
      <div class="section-title">Customers &amp; Delivery</div>
      <table class="data">
        <thead><tr><th>Measure</th><th class="num">Value</th></tr></thead>
        <tbody>
          <tr><td>Customers who ordered</td><td class="num">{{ number_format($ret['active']) }}</td></tr>
          <tr><td>Returning / first-time</td><td class="num">{{ number_format($ret['returning']) }} / {{ number_format($ret['new']) }}</td></tr>
          <tr><td>Returning rate</td><td class="num b">{{ $ret['return_rate'] }}%</td></tr>
          <tr><td>Orders per customer</td><td class="num">{{ $ret['orders_per_client'] }}</td></tr>
          <tr><td>Value per customer</td><td class="num">{{ $money($ret['value_per_client']) }}</td></tr>
          <tr><td>Not seen in 90 days</td><td class="num">{{ number_format($ret['lapsed_90']) }}</td></tr>
          <tr><td>On-time delivery rate</td><td class="num b">{{ $del['on_time_rate'] }}%</td></tr>
          <tr><td>Average turnaround</td><td class="num">{{ $del['avg_days'] }} days</td></tr>
          <tr><td>Open orders / overdue</td><td class="num">{{ number_format($del['open_orders']) }} / {{ number_format($del['overdue']) }}</td></tr>
          <tr><td>Due today / within 7 days</td><td class="num">{{ number_format($del['due_today']) }} / {{ number_format($del['due_7_days']) }}</td></tr>
        </tbody>
      </table>
    </td>
  </tr>
</table>

{{-- ------------------------------- who owes ----------------------------- --}}
@if(!empty($dues['customers']))
<div class="section">
  <div class="section-title">
    Customers With a Balance
    <span class="count">{{ count($dues['customers']) }} listed</span>
  </div>
  <table class="data">
    <thead>
      <tr>
        <th class="idx">#</th>
        <th>Customer</th>
        <th>Phone</th>
        <th class="num">Unpaid orders</th>
        <th class="num">Oldest</th>
        <th class="num">Outstanding</th>
        <th class="num">Share</th>
      </tr>
    </thead>
    <tbody>
      @foreach($dues['customers'] as $i => $c)
        <tr>
          <td class="idx">{{ $i + 1 }}</td>
          <td class="b">{{ $c['name'] }}</td>
          <td class="muted">{{ $c['phone'] ?: '—' }}</td>
          <td class="num">{{ number_format($c['orders']) }}</td>
          <td class="num">{{ $c['oldest'] }} d</td>
          <td class="num b">{{ $money($c['amount']) }}</td>
          <td class="num muted">{{ $pct($c['amount'], $dues['total']) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- ------------------------------- garments ----------------------------- --}}
@php $garmentTotal = collect($analytics['garments'])->sum('value'); @endphp
<table class="split section">
  <tr>
    <td class="left" style="width:60%">
      <div class="section-title">
        Garments &amp; Services
        <span class="count">{{ count($analytics['garments']) }} lines</span>
      </div>
      <table class="data">
        <thead><tr><th>Garment / service</th><th class="num">Orders</th><th class="num">Value</th><th class="num">Average</th><th class="num">Share</th></tr></thead>
        <tbody>
          @forelse($analytics['garments'] as $g)
            <tr>
              <td class="b">{{ $g['garment'] }}</td>
              <td class="num">{{ number_format($g['orders']) }}</td>
              <td class="num">{{ $money($g['value']) }}</td>
              <td class="num muted">{{ $money($g['avg']) }}</td>
              <td class="num muted">{{ $pct($g['value'], $garmentTotal) }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty">No orders in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </td>
    <td class="right" style="width:40%">
      <div class="section-title">Orders by Weekday</div>
      <table class="data">
        <thead><tr><th>Day</th><th class="num">Orders</th><th class="num">Value</th></tr></thead>
        <tbody>
          @foreach($analytics['weekdays'] as $d)
            <tr>
              <td>{{ $d['day'] }}</td>
              <td class="num">{{ number_format($d['orders']) }}</td>
              <td class="num">{{ $money($d['value']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </td>
  </tr>
</table>

{{-- ------------------------------- customer growth ---------------------- --}}
<div class="section keep">
  <div class="section-title">Customer Growth <span class="count">last 6 months</span></div>
  <table class="data">
    <thead>
      <tr>
        <th>Month</th>
        @foreach($report['growth_series']['labels'] as $label)
          <th class="num">{{ $label }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="b">New customers</td>
        @foreach($report['growth_series']['new'] as $v)
          <td class="num">{{ number_format($v) }}</td>
        @endforeach
      </tr>
      <tr class="alt">
        <td class="b">Returning customers</td>
        @foreach($report['growth_series']['returning'] as $v)
          <td class="num">{{ number_format($v) }}</td>
        @endforeach
      </tr>
    </tbody>
  </table>
</div>

@endsection
