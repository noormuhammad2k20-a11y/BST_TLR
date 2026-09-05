{{--
  Shared shell for every downloadable report PDF.

  Laid out for dompdf, which understands tables and absolute/fixed boxes but
  not flexbox or grid — so the whole document is built from tables. The same
  markup doubles as the print-ready HTML fallback when no PDF engine is
  installed, which is why the page counters are only emitted for the PDF path
  (browsers cannot resolve counter(page) outside an @page margin box).

  Sizing follows the standard dompdf recipe: a large @page top/bottom margin
  with the running header and footer pulled into it by negative offsets, so
  they repeat on every page without colliding with the content.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $doc['title'] }} — {{ $doc['store'] }}</title>
<style>
  /* --------------------------------------------------------------------
     Plain black-and-white report styling.

     No fills, no accent colours, no zebra shading — just black text, thin
     grey rules and white paper. It costs almost no ink, photocopies and
     faxes cleanly, and reads the same on the cheapest office laser as on a
     colour machine. Emphasis is carried by weight and rules alone, and
     direction of change by an arrow glyph rather than red/green.
     -------------------------------------------------------------------- */

  @page { margin: {{ !empty($forPdf) ? '108px 30px 70px 30px' : '12mm 10mm' }}; }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    font-family: "DejaVu Sans", sans-serif;
    font-size: 8pt;
    line-height: 1.35;
    color: #000;
    background: #fff;
  }

  /* ------------------------------ running header ------------------------ */
  #doc-header {
    border-bottom: 1.2pt solid #000;
    padding-bottom: 4px;
    margin-bottom: 10px;
  }
  body.as-pdf #doc-header {
    position: fixed;
    top: -96px; left: 0; right: 0; height: 88px;
    margin-bottom: 0;
  }
  #doc-header .brand { font-size: 15pt; font-weight: bold; letter-spacing: -0.3pt; }
  #doc-header .brand-sub { font-size: 6.8pt; color: #444; margin-top: 1px; }
  #doc-header .doc-title { font-size: 10.5pt; font-weight: bold; text-align: right; }
  #doc-header .doc-range { font-size: 7.6pt; text-align: right; margin-top: 2px; font-weight: bold; }
  #doc-header .doc-meta { font-size: 6.6pt; text-align: right; color: #444; margin-top: 1px; }
  #doc-header td { vertical-align: top; padding: 0; }
  #doc-header img { max-height: 42px; max-width: 110px; }

  /* ------------------------------ running footer ------------------------ */
  #doc-footer {
    border-top: 0.5pt solid #999;
    padding-top: 4px;
    margin-top: 14px;
    font-size: 6.6pt;
    color: #444;
  }
  body.as-pdf #doc-footer {
    position: fixed;
    bottom: -52px; left: 0; right: 0; height: 40px;
    margin-top: 0;
  }
  #doc-footer td { padding: 0; vertical-align: top; }
  #doc-footer .right { text-align: right; }
  .pageno:after    { content: counter(page); }
  .pagetotal:after { content: counter(pages); }

  /* ------------------------------ sections ------------------------------ */
  .section { margin-bottom: 11px; }
  .section-title {
    font-size: 8.4pt; font-weight: bold; text-transform: uppercase;
    letter-spacing: 0.6pt;
    border-bottom: 0.8pt solid #000;
    padding-bottom: 2.5px; margin-bottom: 4px;
  }
  .section-title .count { float: right; font-weight: normal; color: #444; letter-spacing: 0; text-transform: none; }
  .keep { page-break-inside: avoid; }
  .break-before { page-break-before: always; }

  /* ------------------------------ KPI band ------------------------------ */
  table.kpis { width: 100%; border-collapse: collapse; margin-bottom: 11px; }
  table.kpis td { border: 0.5pt solid #999; padding: 5px 7px; width: 25%; }
  table.kpis .k-label { font-size: 6.4pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #444; }
  table.kpis .k-value { font-size: 12.5pt; font-weight: bold; margin-top: 1px; letter-spacing: -0.3pt; }
  table.kpis .k-note  { font-size: 6.4pt; color: #444; margin-top: 1px; }

  /* Direction of change reads from the glyph, never from colour. */
  .up, .down { color: #000; }
  .down { font-weight: bold; }

  /* ------------------------------ data tables --------------------------- */
  table.data { width: 100%; border-collapse: collapse; }
  table.data.fixed { table-layout: fixed; }
  table.data thead { display: table-header-group; }
  table.data tr { page-break-inside: avoid; }
  table.data th {
    font-size: 6.4pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4pt;
    border-top: 0.5pt solid #000;
    border-bottom: 0.5pt solid #000;
    padding: 3.5px 4px; text-align: left;
    background: transparent;
  }
  table.data th.num,
  table.data th.mid { font-family: "DejaVu Sans", sans-serif; font-size: 6.4pt; }
  table.data th.num { text-align: right; }
  table.data th.mid { text-align: center; }
  table.data td {
    font-size: 7.8pt;
    padding: 2.8px 4px;
    border-bottom: 0.35pt solid #ccc;
  }
  table.data tfoot td {
    font-weight: bold;
    border-top: 0.7pt solid #000;
    border-bottom: 0.7pt solid #000;
  }
  .num  { text-align: right; font-family: "DejaVu Sans Mono", monospace; font-size: 7.4pt; white-space: nowrap; }
  .mid  { text-align: center; }
  .b    { font-weight: bold; }
  .muted{ color: #555; }
  .sub  { font-size: 6.4pt; color: #555; }
  .idx  { color: #777; font-size: 6.6pt; width: 16px; }
  .empty { padding: 12px 4px; text-align: center; color: #777; font-size: 7.5pt; }

  /* Two report blocks side by side rather than a whole page each. */
  table.split { width: 100%; border-collapse: collapse; }
  table.split > tbody > tr > td { vertical-align: top; padding: 0; }
  table.split > tbody > tr > td.left  { padding-right: 8px; }
  table.split > tbody > tr > td.right { padding-left: 8px; }

  .pill { font-size: 6.2pt; padding: 0.5px 3px; border: 0.5pt solid #666; color: #000; white-space: nowrap; }
  .pill-warn { border-color: #000; font-weight: bold; }

  /* Narrative block: the written read-out of the period. */
  .notes { border: 0.5pt solid #999; padding: 6px 8px; }
  .notes li { margin: 0 0 2.5px 0; font-size: 7.6pt; }
  .notes ul { margin: 0; padding-left: 12px; }

  /* Progress bar built from a table so its height is exact in every engine;
     a span-based bar overflowed its track and printed over the line below. */
  table.bar { width: 100%; border-collapse: collapse; border: 0.4pt solid #999; }
  table.bar td { padding: 0; height: 5pt; font-size: 0; line-height: 0; }
  table.bar td.fill { background: #000; }

  /* Reading the fallback page on screen before printing it. */
  body.as-page { max-width: 820px; margin: 0 auto; padding: 20px; background: #fff; }
  @media print {
    body.as-page { max-width: none; padding: 0; }
  }
</style>
</head>
<body class="{{ !empty($forPdf) ? 'as-pdf' : 'as-page' }}">

<div id="doc-header">
  <table style="width:100%; border-collapse:collapse;">
    <tr>
      <td style="width:58%;">
        @if(!empty($doc['logo']))
          <img src="{{ $doc['logo'] }}" alt="">
        @endif
        <div class="brand">{{ $doc['store'] }}</div>
        <div class="brand-sub">
          {{ collect([$doc['tagline'], $doc['address'], $doc['phone'], $doc['email']])->filter()->implode('  •  ') }}
        </div>
      </td>
      <td style="width:42%;">
        <div class="doc-title">{{ $doc['title'] }}</div>
        <div class="doc-range">{{ $doc['range_label'] }}</div>
        @if(!empty($doc['filter_label']))
          <div class="doc-meta">{{ $doc['filter_label'] }}</div>
        @endif
        <div class="doc-meta">Generated {{ $doc['generated_at'] }} by {{ $doc['generated_by'] }}</div>
      </td>
    </tr>
  </table>
</div>



@yield('body')

<div id="doc-footer">
  <table style="width:100%; border-collapse:collapse;">
    <tr>
      <td>{{ $doc['store'] }} — {{ $doc['title'] }} ({{ $doc['range_label'] }})</td>
      <td class="right">
        @if(!empty($forPdf))
          Page <span class="pageno"></span> of <span class="pagetotal"></span>
        @else
          {{ $doc['generated_at'] }}
        @endif
      </td>
    </tr>
  </table>
</div>

@if(!empty($autoPrint))
<script>
  /* No PDF engine installed — hand the document straight to the print dialog,
     where "Save as PDF" produces the same layout. */
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
</script>
@endif

</body>
</html>
