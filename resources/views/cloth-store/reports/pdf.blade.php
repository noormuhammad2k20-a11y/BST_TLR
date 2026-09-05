{{--
  Cloth Store module — downloadable report document.

  Mirrors the four on-screen tabs (Sales & Profit, Fabric & Categories,
  Inventory, Customers & Finance) in one continuous document, because a PDF
  has no tabs — the reader wants the whole picture in a single file. Every
  table is fed by the identical query the page uses for the selected period.
--}}
@extends('pdf.document')

@php
  $cur    = $doc['currency'];
  $money  = fn ($v) => $cur . number_format((float) $v);
  $meters = fn ($v) => number_format((float) $v, 2) . ' m';
  $pct    = fn ($part, $whole) => $whole > 0 ? number_format($part / $whole * 100, 1) . '%' : '—';

  $paymentsTotal  = $payments->sum('total');
  $inventoryValue = collect($inventory)->sum('value');
  $lowStockCount  = collect($inventory)->where('status', 'Low Stock')->count();
@endphp

@section('body')

{{-- ------------------------------- headline KPIs ------------------------ --}}
<table class="kpis keep">
  <tr>
    <td style="width:33.33%">
      <div class="k-label">Net Sales</div>
      <div class="k-value">{{ $money($kpis['net_sales']) }}</div>
      <div class="k-note">{{ number_format($salesReport['transactions']) }} completed transactions</div>
    </td>
    <td style="width:33.33%">
      <div class="k-label">Gross Profit</div>
      <div class="k-value up">{{ $money($kpis['gross_profit']) }}</div>
      <div class="k-note">Cost of goods {{ $money($kpis['net_sales'] - $kpis['gross_profit']) }}</div>
    </td>
    <td style="width:33.34%">
      <div class="k-label">Meters Sold</div>
      <div class="k-value">{{ number_format($kpis['meters_sold'], 2) }}</div>
      <div class="k-note">Avg {{ number_format($salesReport['average_meters'], 2) }} m per sale</div>
    </td>
  </tr>
  <tr>
    <td>
      <div class="k-label">Total Expenses</div>
      <div class="k-value down">{{ $money($kpis['total_expenses']) }}</div>
      <div class="k-note">{{ $expenses->count() }} expense categories</div>
    </td>
    <td>
      <div class="k-label">Net Profit</div>
      <div class="k-value {{ $kpis['net_profit'] >= 0 ? 'up' : 'down' }}">{{ $money($kpis['net_profit']) }}</div>
      <div class="k-note">Gross profit less expenses</div>
    </td>
    <td>
      <div class="k-label">Profit Margin</div>
      <div class="k-value">{{ number_format($kpis['profit_margin'], 1) }}%</div>
      <div class="k-note">Net profit as a share of sales</div>
    </td>
  </tr>
</table>

{{-- ------------------------------- sales summary ------------------------ --}}
<div class="section keep">
  <div class="section-title">Sales Summary</div>
  <table class="data">
    <thead>
      <tr>
        <th>Line</th><th class="num">Value</th>
        <th>Line</th><th class="num">Value</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Transactions</td>
        <td class="num">{{ number_format($salesReport['transactions']) }}</td>
        <td>Average sale value</td>
        <td class="num">{{ $money($salesReport['average_sale']) }}</td>
      </tr>
      <tr class="alt">
        <td>Average meters per sale</td>
        <td class="num">{{ number_format($salesReport['average_meters'], 2) }} m</td>
        <td>Total discounts given</td>
        <td class="num down">{{ $money($salesReport['discounts']) }}</td>
      </tr>
      <tr>
        <td>Cost of goods sold</td>
        <td class="num">{{ $money($kpis['net_sales'] - $kpis['gross_profit']) }}</td>
        <td>Gross profit</td>
        <td class="num up">{{ $money($kpis['gross_profit']) }}</td>
      </tr>
      <tr class="alt">
        <td class="b">Net profit after expenses</td>
        <td class="num b {{ $kpis['net_profit'] >= 0 ? 'up' : 'down' }}">{{ $money($kpis['net_profit']) }}</td>
        <td class="b">Stock on hand (cost value)</td>
        <td class="num b">{{ $money($inventoryValue) }}</td>
      </tr>
    </tbody>
  </table>
</div>

{{-- ------------------------------- daily sales -------------------------- --}}
@php $dayRows = array_chunk($dailySales->all(), 3); @endphp
<div class="section">
  <div class="section-title">
    Day-by-Day Sales
    <span class="count">{{ $dailySales->count() }} trading days</span>
  </div>
  <table class="data fixed">
    <thead>
      <tr>
        @for($c = 0; $c < 3; $c++)
          <th style="width:10%">Date</th>
          <th class="num" style="width:10.3%">Meters</th>
          <th class="num" style="width:13%">Revenue</th>
        @endfor
      </tr>
    </thead>
    <tbody>
      @forelse($dayRows as $r => $row)
        <tr class="{{ $r % 2 ? 'alt' : '' }}">
          @for($c = 0; $c < 3; $c++)
            @if(isset($row[$c]))
              <td>{{ \Illuminate\Support\Carbon::parse($row[$c]->date)->format('d M') }}</td>
              <td class="num">{{ number_format((float) $row[$c]->meters, 2) }}</td>
              <td class="num">{{ $money($row[$c]->revenue) }}</td>
            @else
              <td></td><td class="num"></td><td class="num"></td>
            @endif
          @endfor
        </tr>
      @empty
        <tr><td colspan="9" class="empty">No completed sales in this period.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- ------------------------------- fabric sales ------------------------- --}}
<div class="section">
  <div class="section-title">
    Fabric Sales
    <span class="count">{{ $fabricSales->count() }} products sold</span>
  </div>
  <table class="data">
    <thead>
      <tr>
        <th class="idx">#</th>
        <th>Product</th>
        <th>Category</th>
        <th class="num">Meters</th>
        <th class="num">Revenue</th>
        <th class="num">Cost</th>
        <th class="num">Profit</th>
        <th class="num">Margin</th>
      </tr>
    </thead>
    <tbody>
      @forelse($fabricSales as $i => $f)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
          <td class="idx">{{ $i + 1 }}</td>
          <td class="b">{{ $f->product }}</td>
          <td class="muted">{{ $f->category }}</td>
          <td class="num">{{ number_format((float) $f->meters_sold, 2) }}</td>
          <td class="num">{{ $money($f->revenue) }}</td>
          <td class="num muted">{{ $money($f->purchase_cost) }}</td>
          <td class="num {{ $f->profit >= 0 ? 'up' : 'down' }}">{{ $money($f->profit) }}</td>
          <td class="num muted">{{ number_format($f->margin, 1) }}%</td>
        </tr>
      @empty
        <tr><td colspan="8" class="empty">No fabric sales in this period.</td></tr>
      @endforelse
    </tbody>
    @if($fabricSales->count())
      <tfoot>
        <tr>
          <td colspan="3">Total</td>
          <td class="num">{{ number_format((float) $fabricSales->sum('meters_sold'), 2) }}</td>
          <td class="num">{{ $money($fabricSales->sum('revenue')) }}</td>
          <td class="num">{{ $money($fabricSales->sum('purchase_cost')) }}</td>
          <td class="num">{{ $money($fabricSales->sum('profit')) }}</td>
          <td class="num">{{ $pct($fabricSales->sum('profit'), $fabricSales->sum('revenue')) }}</td>
        </tr>
      </tfoot>
    @endif
  </table>
</div>

{{-- --------------------- categories + payment methods ------------------- --}}
<table class="split section">
  <tr>
    <td class="left" style="width:55%">
      <div class="section-title">Category Performance</div>
      <table class="data">
        <thead>
          <tr><th>Category</th><th class="num">Sales</th><th class="num">Meters</th><th class="num">Revenue</th><th class="num">Share</th></tr>
        </thead>
        <tbody>
          @forelse($categorySales as $i => $c)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
              <td class="b">{{ $c->category }}</td>
              <td class="num">{{ number_format($c->transactions) }}</td>
              <td class="num">{{ number_format((float) $c->meters_sold, 2) }}</td>
              <td class="num">{{ $money($c->revenue) }}</td>
              <td class="num muted">{{ $pct($c->revenue, $kpis['net_sales']) }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty">No category sales.</td></tr>
          @endforelse
        </tbody>
      </table>
    </td>
    <td class="right" style="width:45%">
      <div class="section-title">Payment Methods</div>
      <table class="data">
        <thead><tr><th>Method</th><th class="num">Amount</th><th class="num">Share</th></tr></thead>
        <tbody>
          @forelse($payments as $i => $p)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
              <td class="b">{{ $p->payment_method ?: 'Unspecified' }}</td>
              <td class="num">{{ $money($p->total) }}</td>
              <td class="num muted">{{ $pct($p->total, $paymentsTotal) }}</td>
            </tr>
          @empty
            <tr><td colspan="3" class="empty">No payments recorded.</td></tr>
          @endforelse
        </tbody>
        @if($payments->count())
          <tfoot><tr><td>Total</td><td class="num">{{ $money($paymentsTotal) }}</td><td></td></tr></tfoot>
        @endif
      </table>
    </td>
  </tr>
</table>

{{-- ------------------------------- inventory ---------------------------- --}}
<div class="section">
  <div class="section-title">
    Inventory Valuation
    <span class="count">{{ count($inventory) }} active products · {{ $lowStockCount }} low on stock · {{ $money($inventoryValue) }}</span>
  </div>
  <table class="data">
    <thead>
      <tr>
        <th class="idx">#</th>
        <th>Product</th>
        <th>Category</th>
        <th class="num">Stock</th>
        <th class="num">Cost Rate</th>
        <th class="num">Sale Rate</th>
        <th class="num">Stock Value</th>
        <th class="mid">Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($inventory as $i => $inv)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
          <td class="idx">{{ $i + 1 }}</td>
          <td class="b">{{ $inv['product'] }}</td>
          <td class="muted">{{ $inv['category'] }}</td>
          <td class="num {{ $inv['status'] === 'Low Stock' ? 'down' : '' }}">{{ number_format((float) $inv['meters'], 2) }} m</td>
          <td class="num muted">{{ $money($inv['purchase_rate']) }}</td>
          <td class="num muted">{{ $money($inv['selling_rate']) }}</td>
          <td class="num b">{{ $money($inv['value']) }}</td>
          <td class="mid">
            <span class="pill {{ $inv['status'] === 'Low Stock' ? 'pill-warn' : '' }}">
              {{ $inv['status'] === 'Low Stock' ? 'Low · reorder ' . $inv['reorder'] : 'In Stock' }}
            </span>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="empty">No active products.</td></tr>
      @endforelse
    </tbody>
    @if(count($inventory))
      <tfoot>
        <tr>
          <td colspan="6">Total stock value at cost</td>
          <td class="num">{{ $money($inventoryValue) }}</td>
          <td></td>
        </tr>
      </tfoot>
    @endif
  </table>
</div>

{{-- ------------------------------- customers ---------------------------- --}}
<div class="section">
  <div class="section-title">
    Customers
    <span class="count">{{ $customers->count() }} active in period</span>
  </div>
  <table class="data">
    <thead>
      <tr>
        <th class="idx">#</th>
        <th>Customer</th>
        <th class="num">Visits</th>
        <th class="num">Meters</th>
        <th class="num">Spending</th>
        <th class="num">Avg / Visit</th>
        <th class="num">Outstanding Due</th>
      </tr>
    </thead>
    <tbody>
      @forelse($customers as $i => $c)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
          <td class="idx">{{ $i + 1 }}</td>
          <td class="b">{{ $c->name }}</td>
          <td class="num">{{ number_format($c->visits) }}</td>
          <td class="num">{{ number_format((float) $c->meters_purchased, 2) }}</td>
          <td class="num">{{ $money($c->total_spending) }}</td>
          <td class="num muted">{{ $c->visits > 0 ? $money($c->total_spending / $c->visits) : '—' }}</td>
          <td class="num {{ $c->due_balance > 0 ? 'down' : 'muted' }}">{{ $money($c->due_balance) }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="empty">No customer activity in this period.</td></tr>
      @endforelse
    </tbody>
    @if($customers->count())
      <tfoot>
        <tr>
          <td colspan="2">Total</td>
          <td class="num">{{ number_format($customers->sum('visits')) }}</td>
          <td class="num">{{ number_format((float) $customers->sum('meters_purchased'), 2) }}</td>
          <td class="num">{{ $money($customers->sum('total_spending')) }}</td>
          <td></td>
          <td class="num">{{ $money($customers->sum('due_balance')) }}</td>
        </tr>
      </tfoot>
    @endif
  </table>
</div>

{{-- ------------------------------- expenses ----------------------------- --}}
<div class="section keep">
  <div class="section-title">
    Expenses
    <span class="count">{{ $money($kpis['total_expenses']) }}</span>
  </div>
  <table class="data">
    <thead><tr><th>Category</th><th class="num">Amount</th><th class="num">Share of expenses</th><th class="num">Share of sales</th></tr></thead>
    <tbody>
      @forelse($expenses as $i => $e)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
          <td class="b">{{ $e->category }}</td>
          <td class="num down">{{ $money($e->total) }}</td>
          <td class="num muted">{{ $pct($e->total, $kpis['total_expenses']) }}</td>
          <td class="num muted">{{ $pct($e->total, $kpis['net_sales']) }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="empty">No expenses in this period.</td></tr>
      @endforelse
    </tbody>
    @if($expenses->count())
      <tfoot>
        <tr>
          <td>Total</td>
          <td class="num">{{ $money($kpis['total_expenses']) }}</td>
          <td class="num">100.0%</td>
          <td class="num">{{ $pct($kpis['total_expenses'], $kpis['net_sales']) }}</td>
        </tr>
      </tfoot>
    @endif
  </table>
</div>

@endsection
