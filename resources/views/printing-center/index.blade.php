@extends('layouts.app')
@section('title', 'Printing Center')
@section('spaPage', 'printing-center')

@push('styles')
<style>
  /* Realistic Print Styles */
  .printable-area { box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-radius: 4px; overflow: hidden; }
  
  .thermal-paper { font-family: 'Courier New', monospace; padding: 16px 12px; width: 220px; background: #FAFAFA; color: #000; }
  .thermal-paper-80 { font-family: 'Courier New', monospace; padding: 20px 16px; width: 320px; background: #FAFAFA; color: #000; }
  .dashed-line { border-top: 1px dashed #000; margin: 8px 0; opacity: 0.7; }

  .cust-card { width: 340px; height: 214px; border-radius: 16px; background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); color: #fff; padding: 24px; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3); }
  .cust-card::after { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; }
  .cust-card::before { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 40px; background: #000; }

  /* CSS Barcode */
  .barcode-container { display: flex; align-items: flex-end; justify-content: center; height: 40px; gap: 1px; background: white; padding: 4px; }
  .barcode-bar { background: #000; width: 2px; height: 100%; }
  .barcode-bar.wide { width: 4px; }

  @media print {
    body * { visibility: hidden; }
    #printable-area, #printable-area * { visibility: visible; }
    #printable-area { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; border: none; border-radius: 0; }
    .no-print { display: none !important; }
  }
</style>
@endpush

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Printing Center</h1>
    <p class="text-sm text-slate-500 mt-0.5">Thermal receipts, invoices, and ID cards</p>
  </div>
  <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="checkPrinterStatus()"><i class="fa-solid fa-wifi text-[10px]"></i> Check Printer Status</button>
</div>

<!-- Print Options Grid -->
<div class="page grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
  
  <!-- 58mm Thermal -->
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col">
    <div class="flex items-start justify-between mb-4">
      <div class="w-11 h-11 rounded-lg flex items-center justify-center bg-indigo-50 text-indigo-600 text-lg"><i class="fa-solid fa-receipt"></i></div>
      <span class="badge">58mm</span>
    </div>
    <div class="text-base font-semibold text-slate-900 mb-1 tracking-tight">58mm Thermal Receipt</div>
    <div class="text-xs text-slate-500 mb-4 flex items-center gap-1.5"><i class="fa-solid fa-print text-[10px]"></i> {{ $printCounts['58mm'] }} prints this month</div>
    <div class="flex gap-2 mt-auto">
      <button class="flex-1 bg-white border border-slate-200 text-slate-600 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 transition-colors" onclick="openPrintPreview('58mm')">Preview</button>
      <button class="flex-1 bg-slate-900 text-white py-2 rounded-lg text-xs font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="openPrintPreview('58mm', true)">Print</button>
    </div>
  </div>

  <!-- 80mm Thermal -->
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col">
    <div class="flex items-start justify-between mb-4">
      <div class="w-11 h-11 rounded-lg flex items-center justify-center bg-sky-50 text-sky-600 text-lg"><i class="fa-solid fa-receipt"></i></div>
      <span class="badge">80mm</span>
    </div>
    <div class="text-base font-semibold text-slate-900 mb-1 tracking-tight">80mm Thermal Receipt</div>
    <div class="text-xs text-slate-500 mb-4 flex items-center gap-1.5"><i class="fa-solid fa-print text-[10px]"></i> {{ $printCounts['80mm'] }} prints this month</div>
    <div class="flex gap-2 mt-auto">
      <button class="flex-1 bg-white border border-slate-200 text-slate-600 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 transition-colors" onclick="openPrintPreview('80mm')">Preview</button>
      <button class="flex-1 bg-slate-900 text-white py-2 rounded-lg text-xs font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="openPrintPreview('80mm', true)">Print</button>
    </div>
  </div>

  <!-- Customer VIP Card -->
  <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col">
    <div class="flex items-start justify-between mb-4">
      <div class="w-11 h-11 rounded-lg flex items-center justify-center bg-purple-50 text-purple-600 text-lg"><i class="fa-solid fa-id-card"></i></div>
      <span class="badge">85×54mm</span>
    </div>
    <div class="text-base font-semibold text-slate-900 mb-1 tracking-tight">VIP Member Card</div>
    <div class="text-xs text-slate-500 mb-4 flex items-center gap-1.5"><i class="fa-solid fa-print text-[10px]"></i> {{ $printCounts['customer'] }} prints this month</div>
    <div class="flex gap-2 mt-auto">
      <button class="flex-1 bg-white border border-slate-200 text-slate-600 py-2 rounded-lg text-xs font-medium hover:bg-slate-50 transition-colors" onclick="openPrintPreview('customer')">Preview</button>
      <button class="flex-1 bg-slate-900 text-white py-2 rounded-lg text-xs font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="openPrintPreview('customer', true)">Print</button>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
  /* ============= GENERATE CSS BARCODE ============= */
  function generateBarcode() {
    let html = '<div class="barcode-container">';
    for (let i = 0; i < 40; i++) {
      const isWide = Math.random() > 0.7;
      const isSpace = i % 2 === 0;
      if (isSpace) {
        html += `<div style="width: ${isWide ? 4 : 2}px; height: 100%; background: transparent;"></div>`;
      } else {
        html += `<div class="barcode-bar ${isWide ? 'wide' : ''}"></div>`;
      }
    }
    html += '</div>';
    return html;
  }

  /* ============= SOURCE DATA ============= */
  /* `var` throughout: the SPA router re-evaluates this script per navigation. */
  var RECENT_ORDERS = @json($recentOrders);
  var CARD_CUSTOMERS = @json($customers);

  var printSelection = {
    order_id: RECENT_ORDERS.length ? RECENT_ORDERS[0].id : null,
    customer_id: CARD_CUSTOMERS.length ? CARD_CUSTOMERS[0].id : null,
  };

  window.checkPrinterStatus = function () {
    // The browser's print pipeline is what we actually rely on, so report on it.
    if (typeof window.print === 'function') {
      toast('Browser print pipeline ready — use Print Now on any preview', 'success');
    } else {
      toast('Printing is not available in this browser', 'error');
    }
  };

  /* ============= PRINT PREVIEWS (live records) ============= */
  async function openPrintPreview(type, autoPrint = false) {
    const modalContent = document.getElementById('modal-content');

    modalContent.className = 'modal max-w-sm';
    modalContent.innerHTML = `
      <div class="p-10 text-center">
        <i class="fa-solid fa-spinner fa-spin text-indigo-600 text-2xl mb-3"></i>
        <div class="text-sm text-slate-500">Preparing preview…</div>
      </div>`;
    document.getElementById('modal-backdrop').classList.add('show');

    let data;
    try {
      const params = new URLSearchParams({ type });
      if (type === 'customer') {
        if (printSelection.customer_id) params.set('customer_id', printSelection.customer_id);
      } else if (printSelection.order_id) {
        params.set('order_id', printSelection.order_id);
      }

      data = await Atelier.api.get(`{{ route('printing-center.render') }}?${params}`);
    } catch (err) {
      closeModal();
      Atelier.reportError(err, 'No record available to preview yet');
      return;
    }

    const barcodeHtml = generateBarcode();
    const shop = data.shop;
    const r = data.receipt;
    const card = data.card;

    let title = '';
    let content = '';
    let modalWidth = 'max-w-xl';
    let picker = '';

    if (type === '58mm' || type === '80mm') {
      picker = `
        <div class="px-4 pt-3 no-print">
          <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Order</label>
          <select onchange="printSelection.order_id = this.value; openPrintPreview('${type}')" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-slate-900">
            ${RECENT_ORDERS.map(o => `<option value="${o.id}" ${o.id == printSelection.order_id ? 'selected' : ''}>${Atelier.escapeHtml(o.label)}</option>`).join('')}
          </select>
        </div>`;
    } else {
      picker = `
        <div class="px-4 pt-3 no-print">
          <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Customer</label>
          <select onchange="printSelection.customer_id = this.value; openPrintPreview('customer')" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-slate-900">
            ${CARD_CUSTOMERS.map(c => `<option value="${c.id}" ${c.id == printSelection.customer_id ? 'selected' : ''}>${Atelier.escapeHtml(c.label)}</option>`).join('')}
          </select>
        </div>`;
    }

    if (type === '58mm') {
      title = '58mm Thermal Receipt';
      modalWidth = 'max-w-sm';
      content = `
        <div class="printable-area thermal-paper mx-auto">
          <div class="text-center font-bold text-sm">${Atelier.escapeHtml(shop.name.toUpperCase())}</div>
          <div class="text-center text-[9px]">Premium Bespoke Services</div>
          <div class="text-center text-[9px]">${Atelier.escapeHtml(shop.phone)}</div>
          <div class="dashed-line"></div>
          <div class="text-[10px]">Order: ${Atelier.escapeHtml(r.order)}</div>
          <div class="text-[10px]">Date: ${Atelier.escapeHtml(r.date)}</div>
          <div class="text-[10px]">Cust: ${Atelier.escapeHtml(r.customer)}</div>
          <div class="dashed-line"></div>
          <div class="text-[10px]">${Atelier.escapeHtml(r.garment)}</div>
          ${r.fabric ? `<div class="text-[10px]">${Atelier.escapeHtml(r.fabric)}</div>` : ''}
          <div class="dashed-line"></div>
          <div class="flex justify-between text-[10px] font-bold"><span>Total:</span><span>${Atelier.escapeHtml(r.total)}</span></div>
          <div class="flex justify-between text-[10px]"><span>Advance:</span><span>${Atelier.escapeHtml(r.advance)}</span></div>
          <div class="flex justify-between text-[10px] font-bold"><span>Baqi:</span><span>${Atelier.escapeHtml(r.balance)}</span></div>
          <div class="dashed-line"></div>
          <div class="text-center text-[10px]">Delivery: ${Atelier.escapeHtml(r.delivery)}</div>
          <div class="mt-4 text-center text-[10px] font-bold">*** SHUKRIYA ***</div>
          <div class="mt-2 flex justify-center">${barcodeHtml}</div>
          <div class="text-center text-[8px] mt-1">*${Atelier.escapeHtml(r.order)}*</div>
        </div>`;
    }
    else if (type === '80mm') {
      title = '80mm Thermal Receipt';
      modalWidth = 'max-w-md';
      content = `
        <div class="printable-area thermal-paper-80 mx-auto">
          <div class="text-center"><div class="inline-block w-10 h-10 rounded-lg bg-slate-900 flex items-center justify-center mb-1"><i class="fa-solid fa-scissors text-white"></i></div></div>
          <div class="text-center font-bold text-base">${Atelier.escapeHtml(shop.name.toUpperCase())}</div>
          <div class="text-center text-xs">${Atelier.escapeHtml(shop.address)}</div>
          <div class="text-center text-xs">Phone: ${Atelier.escapeHtml(shop.phone)}</div>
          <div class="dashed-line"></div>
          <div class="flex justify-between text-xs"><span>Order ID:</span><span>${Atelier.escapeHtml(r.order)}</span></div>
          <div class="flex justify-between text-xs"><span>Date:</span><span>${Atelier.escapeHtml(r.date)}</span></div>
          <div class="flex justify-between text-xs"><span>Customer:</span><span>${Atelier.escapeHtml(r.customer)}</span></div>
          <div class="dashed-line"></div>
          <div class="text-xs font-bold mb-1">ORDER DETAILS</div>
          <div class="flex justify-between text-xs"><span>${Atelier.escapeHtml(r.garment)}${r.fabric ? ' (' + Atelier.escapeHtml(r.fabric) + ')' : ''}</span><span>${Atelier.escapeHtml(r.total)}</span></div>
          <div class="dashed-line"></div>
          <div class="flex justify-between text-sm font-bold"><span>TOTAL:</span><span>${Atelier.escapeHtml(r.total)}</span></div>
          <div class="flex justify-between text-xs"><span>Advance Paid:</span><span>${Atelier.escapeHtml(r.advance)}</span></div>
          <div class="flex justify-between text-sm font-bold text-red-700"><span>BALANCE:</span><span>${Atelier.escapeHtml(r.balance)}</span></div>
          <div class="dashed-line"></div>
          <div class="flex justify-between text-xs"><span>Delivery Date:</span><span>${Atelier.escapeHtml(r.delivery)}</span></div>
          <div class="mt-4 text-center text-xs font-bold">${Atelier.escapeHtml(shop.footer)}</div>
          <div class="text-center text-[10px] text-slate-500 mt-1">Powered by ${Atelier.escapeHtml(shop.name)} Admin Suite</div>
          <div class="mt-3 flex justify-center">${barcodeHtml}</div>
        </div>`;
    }
    else {
      title = 'VIP Member Card';
      modalWidth = 'max-w-sm';
      content = `
        <div class="printable-area cust-card mx-auto">
          <div class="flex justify-between items-start z-10 relative">
            <div>
              <div class="text-lg font-bold leading-tight">${Atelier.escapeHtml(shop.name)}</div>
              <div class="text-[10px] uppercase tracking-widest opacity-80">${Atelier.escapeHtml(card.type || 'Member')} Card</div>
            </div>
            <i class="fa-solid fa-scissors text-2xl opacity-30"></i>
          </div>
          <div class="z-10 relative">
            <div class="text-[10px] uppercase tracking-wider opacity-80">Cardholder</div>
            <div class="text-xl font-semibold tracking-wide">${Atelier.escapeHtml(card.name)}</div>
            <div class="text-xs opacity-90 mt-1">ID: ${Atelier.escapeHtml(card.code)} | Since: ${Atelier.escapeHtml(card.since || '')}</div>
          </div>
          <div class="z-10 relative flex justify-between items-end">
            <div class="text-[10px] opacity-80">${Atelier.escapeHtml(shop.website || '')}</div>
            <div class="w-16 h-8 bg-white/20 rounded flex items-center justify-center text-[8px] tracking-widest">||| | || ||</div>
          </div>
        </div>`;
    }

    modalContent.className = `modal ${modalWidth}`;
    modalContent.innerHTML = `
      <div class="p-4 border-b border-slate-200 flex justify-between items-center no-print">
        <div class="text-lg font-bold text-slate-900 tracking-tight">${title}</div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      ${picker}
      <div class="p-6 bg-slate-100 overflow-y-auto flex justify-center items-center flex-1" id="printable-area">
        ${content}
      </div>
      <div class="p-4 bg-white border-t border-slate-200 flex justify-end gap-2 no-print">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors flex items-center gap-2" onclick="window.print()"><i class="fa-solid fa-download text-xs"></i> Download PDF</button>
        <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors flex items-center gap-2" onclick="window.print()"><i class="fa-solid fa-print text-xs"></i> Print Now</button>
      </div>
    `;

    if (autoPrint) setTimeout(() => window.print(), 300);
  }
</script>
@endpush
