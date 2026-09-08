@extends('layouts.app')
@section('title', 'Orders')
@section('spaPage', 'orders')

@push('styles')
<style>
  /* Kanban Drag & Drop */
  .kanban-card {
    cursor: grab;
    transition: transform .1s, box-shadow .1s;
  }
  .kanban-card:active {
    cursor: grabbing;
    transform: rotate(2deg);
  }
  .kanban-col.drag-over {
    background-color: #EEF2FF;
    border: 2px dashed #4F46E5;
  }
  
  /* ==========================================================================
     THERMAL SLIP — 80mm
     --------------------------------------------------------------------------
     Two slips are rendered per order: the customer's receipt and the workshop
     job card. They share this stylesheet so the shop's paper looks like one
     system.

     The previous version drew its rules with a literal string of hyphens. On
     80mm paper that string is wider than the printable area, so every divider
     wrapped onto a second line — the ragged "-----" tails in the old output.
     Rules here are CSS borders, which cannot wrap by definition.

     Width is 72mm, not 80mm: an 80mm head prints ~72mm and the rest is the
     mechanical margin. Setting the slip to the paper width pushes the right
     column off the edge.
     ========================================================================== */
  .slip {
    width: 72mm;
    box-sizing: border-box;
    padding: 3mm 2mm 4mm;
    background: #fff;
    color: #000;
    font-family: 'Courier New', ui-monospace, 'Cascadia Mono', monospace;
    font-size: 12px;
    line-height: 1.45;
    -webkit-font-smoothing: none;
  }

  /* Rules ------------------------------------------------------------------ */
  .slip-rule    { border-top: 1px dashed #000; margin: 2mm 0; }
  .slip-rule-s  { border-top: 1px solid  #000; margin: 2mm 0; }
  .slip-rule-d  { border-top: 3px double #000; margin: 2mm 0; }

  /* Header ----------------------------------------------------------------- */
  .slip-hd     { text-align: center; }
  .slip-logo   { max-width: 34mm; max-height: 16mm; margin: 0 auto 1.5mm; display: block; }
  .slip-shop   { font-size: 17px; font-weight: 700; letter-spacing: .06em; line-height: 1.2; }
  .slip-tag    { font-size: 10px; font-style: italic; margin-top: .5mm; }
  .slip-meta   { font-size: 10px; line-height: 1.35; margin-top: 1mm; }

  /* The copy marker. Inverted so the tailor can tell the two slips apart at a
     glance from across the workshop, without reading anything. */
  .slip-kind {
    background: #000; color: #fff;
    text-align: center; font-weight: 700;
    font-size: 11px; letter-spacing: .22em;
    padding: 1mm 0; margin: 2mm 0;
  }
  .slip-kind.ghost {
    background: #fff; color: #000;
    border: 1px solid #000; letter-spacing: .18em;
  }

  /* Rows ------------------------------------------------------------------- */
  .slip-row {
    display: flex; justify-content: space-between;
    align-items: baseline; gap: 3mm;
  }
  .slip-row .k { flex: 1 1 auto; min-width: 0; word-break: break-word; }
  .slip-row .v {
    flex: 0 0 auto; text-align: right; white-space: nowrap;
    font-variant-numeric: tabular-nums;
  }
  .slip-sec {
    font-size: 10px; font-weight: 700; letter-spacing: .14em;
    margin: 2mm 0 1mm;
  }
  .slip-sub  { font-size: 10px; padding-left: 3mm; }
  .slip-bold { font-weight: 700; }

  /* The one number the customer looks for. */
  .slip-total {
    display: flex; justify-content: space-between; align-items: baseline;
    font-size: 15px; font-weight: 700; padding: 1.5mm 0;
  }

  /* Deadline block on the job card — deliberately the loudest thing on it. */
  .slip-due {
    text-align: center; border: 2px solid #000;
    padding: 1.5mm 1mm; margin: 2mm 0;
  }
  .slip-due .lbl { font-size: 9px; letter-spacing: .2em; }
  .slip-due .val { font-size: 14px; font-weight: 700; line-height: 1.25; }

  /* Measurements ----------------------------------------------------------- */
  .slip-mgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 3mm; }
  .slip-mcell {
    display: flex; justify-content: space-between; gap: 1mm;
    border-bottom: 1px dotted #666;
    padding: .6mm 0; font-size: 11px;
  }
  .slip-mcell .l {
    flex: 1 1 auto; min-width: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .slip-mcell .n {
    flex: 0 0 auto; padding-left: 2mm;
    font-weight: 700; font-variant-numeric: tabular-nums;
  }

  /* Hanging indent, so a wrapped instruction lines up under its own text
     rather than under the bullet. */
  .slip-note { font-size: 11px; padding-left: 3mm; text-indent: -3mm; }

  /* Sign-off boxes the workshop ticks as the garment moves along. */
  .slip-sign { display: flex; gap: 2mm; margin-top: 3mm; text-align: center; font-size: 9px; }
  .slip-sign > div { flex: 1; }
  .slip-sign .line { border-bottom: 1px solid #000; height: 7mm; }

  /* pre-line so a footer the shop typed across several lines actually prints
     across several lines, instead of collapsing into one run-on paragraph. */
  .slip-foot {
    text-align: center; font-size: 10px; line-height: 1.4;
    white-space: pre-line;
  }

  /* Software credit. Deliberately the smallest thing on the slip: it belongs
     to us, not to the customer's transaction. Four fixed lines — the developer
     name appears here and nowhere else on the slip. */
  .slip-credit {
    text-align: center; font-size: 9px; line-height: 1.45;
    margin-top: 2.5mm; color: #444;
  }
  /* The two facts a customer might actually need again — who built it and the
     number to call — carry the weight. The connective wording around them
     stays light so the block reads in one glance. */
  .slip-credit .name  { font-weight: 700; color: #000; }
  .slip-credit .sys   { font-weight: 600; font-size: 8.5px; letter-spacing: .03em; color: #222; }
  .slip-credit .tel   { font-weight: 700; color: #000; letter-spacing: .04em; }
  .slip-credit .ty    { font-size: 9.5px; color: #444; margin-top: 1mm; }
  .slip-code {
    text-align: center; font-size: 13px; font-weight: 700;
    letter-spacing: .18em; margin-top: 2mm;
  }

  /* On-screen preview only — never printed. */
  .slip-preview {
    box-shadow: 0 1px 3px rgba(15, 23, 42, .12), 0 8px 24px rgba(15, 23, 42, .08);
    border-radius: 2px;
  }
  .slip-label {
    font-family: Inter, system-ui, sans-serif;
    font-size: 11px; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; color: #64748b;
    text-align: center; margin-bottom: 8px;
  }

  /* === PRINT =============================================================== */
  @media print {
    body * { visibility: hidden; }

    #thermal-print-area, #thermal-print-area * { visibility: visible; }

    #thermal-print-area {
      position: absolute; left: 0; top: 0;
      width: 80mm; margin: 0; padding: 0;
      background: #fff !important;
      display: block !important;
    }

    #thermal-print-area .slip {
      width: 76mm;
      box-shadow: none !important;
      border-radius: 0 !important;
      padding: 2mm 2mm 6mm;
    }

    /* Each slip is its own page, so the printer cuts between the customer's
       receipt and the workshop copy instead of handing over one long strip. */
    #thermal-print-area .slip { break-after: page; page-break-after: always; }
    #thermal-print-area .slip:last-child { break-after: auto; page-break-after: auto; }

    @page { size: 80mm auto; margin: 0; }
  }

</style>
@endpush

@section('content')
<div id="page-container" class="flex-1 w-full"></div>
{{-- The toast and confirmation dialog now live in the layout, so every page
     shares one implementation. --}}
@endsection

@push('scripts')
<script>
  /* Toast and confirmation dialog are provided globally by the layout.
     `showCustomToast` stays as an alias so existing calls keep working. */
  var showCustomToast = toast;

  /* ============= DATA STORE (server-driven) ============= */
  /* `var` throughout: the SPA router re-evaluates this script per navigation,
     and `let`/`const` cannot legally be redeclared at global scope. */
  var customers = @json($customers);
  var activeServices = @json($activeServices);
  var tailors = @json($tailors);
  var timeSlots = @json($timeSlots);

  var ROUTES = {
    index:      @json(route('orders.index')),
    store:      @json(route('orders.store')),
    live:       @json(route('live.orders')),
    bulkNotify: @json(route('orders.bulk-notify')),
    bulkExtend: @json(route('orders.bulk-extend')),
    bulkStatus: @json(route('orders.bulk-status')),
    show:    id => `/orders/${id}`,
    update:  id => `/orders/${id}`,
    destroy: id => `/orders/${id}`,
    status:  id => `/orders/${id}/status`,
    notify:  id => `/orders/${id}/notify`,
    receipt: id => `/orders/${id}/receipt`,
  };

  var hydrate = o => ({
    ...o,
    dueDate: new Date(o.dueDate),
    createdAt: new Date(o.createdAt),
    stageSince: o.stageSince ? new Date(o.stageSince) : new Date(o.createdAt),
  });

  var orders = @json($orders).map(hydrate);

  /* Replace an order in place after a server mutation, keeping list order. */
  function upsertOrder(payload) {
    const fresh = hydrate(payload);
    const index = orders.findIndex(o => o.db_id === fresh.db_id);
    if (index > -1) orders[index] = fresh; else orders.unshift(fresh);
    return fresh;
  }

  function removeOrder(dbId) {
    orders = orders.filter(o => o.db_id !== dbId);
  }

  /* ============= WIZARD STATE ============= */
  var blankOrderState = () => ({
    customerId: null,
    customerName: '',
    customerPhone: '',
    garmentId: '',
    garmentName: '',
    fabric: '',
    styleNotes: '',
    unit: 'cm',
    measurements: {},
    savedMeasurementId: null,
    quantity: 1,
    unitPrice: 0,
    // One measurement map per garment. pieces[0] is the first suit, pieces[1]
    // the second, and so on — activePiece is the tab currently on screen.
    pieces: [{}],
    activePiece: 0,
    price: 0,
    advance: 0,
    date: '',
    slot: '',
    priority: 'Normal',
    tailorId: '',
    measurementsMode: 'new'
  });

  var newOrderState = blankOrderState();

  /* Mirrors the `req: true` flags in the measurement layout below, so a piece
     the user never opened can still be checked before the order is submitted. */
  var WIZARD_REQUIRED_MEASUREMENTS = ['chest_losing', 'waist_losing', 'hip_losing'];

  /* Grow or shrink the per-piece measurement list to match the quantity,
     keeping whatever was already typed for the pieces that survive. */
  function syncWizardPieces() {
    const qty = Math.max(parseInt(newOrderState.quantity, 10) || 1, 1);

    if (!Array.isArray(newOrderState.pieces)) newOrderState.pieces = [];

    while (newOrderState.pieces.length < qty) newOrderState.pieces.push({});
    newOrderState.pieces.length = qty;

    if (newOrderState.activePiece >= qty) newOrderState.activePiece = qty - 1;
    if (newOrderState.activePiece < 0) newOrderState.activePiece = 0;
  }

  /* Read whatever is on screen into the piece being edited, without judging it
     — validation happens on Next, but switching tabs must never lose typing. */
  function captureActivePiece() {
    const piece = {};

    document.querySelectorAll('.wizard-measurement-input').forEach(inp => {
      const val = inp.value.trim();
      if (val !== '') piece[inp.dataset.label] = val;
    });

    syncWizardPieces();
    newOrderState.pieces[newOrderState.activePiece] = piece;
    newOrderState.measurements = newOrderState.pieces[0] || {};
  }

  window.selectWizardPiece = function(index) {
    captureActivePiece();
    newOrderState.activePiece = index;
    openModal('add-order-wizard');
  };

  window.updateWizardQuantity = function(input) {
    const qty = Math.min(Math.max(parseInt(input.value, 10) || 1, 1), 20);

    newOrderState.quantity = qty;
    input.classList.remove('border-red-500');

    // The garment's own rate drives the total, so changing the count repeats
    // the price instead of making the counter do the arithmetic.
    if (newOrderState.unitPrice) newOrderState.price = newOrderState.unitPrice * qty;

    syncWizardPieces();

    const box = document.getElementById('wizard-qty-total');
    if (box) box.textContent = Atelier.money(newOrderState.price || 0);
  };

  window.selectWizardGarment = function(id, name, price) {
    newOrderState.garmentId = id;
    newOrderState.garmentName = name;
    newOrderState.unitPrice = price;
    newOrderState.price = price * Math.max(newOrderState.quantity || 1, 1);
    openModal('add-order-wizard');
  };

  window.wizardNext = function() {
    if (wizardStep === 1) {
      if (!newOrderState.customerName) {
        showCustomToast('Please select a customer first', 'warning');
        return;
      }
    } else if (wizardStep === 2) {
      const fabricInput = document.querySelector('input[placeholder="e.g. Italian Wool"]');
      if (fabricInput) newOrderState.fabric = fabricInput.value;
      if (!newOrderState.garmentName) {
        showCustomToast('Please select a garment type', 'warning');
        return;
      }
      const styleNotes = document.querySelector('textarea[placeholder="e.g. Peak lapel, side vents..."]');
      if (styleNotes) newOrderState.styleNotes = styleNotes.value;

      const qtyInput = document.getElementById('wizard-qty');
      const qty = qtyInput ? parseInt(qtyInput.value, 10) : newOrderState.quantity;

      if (!qty || qty < 1 || qty > 20) {
        if (qtyInput) qtyInput.classList.add('border-red-500');
        showCustomToast('Quantity 1 se 20 ke darmiyan honi chahiye', 'error');
        return;
      }

      newOrderState.quantity = qty;
      if (newOrderState.unitPrice) newOrderState.price = newOrderState.unitPrice * qty;
      newOrderState.activePiece = 0;
      syncWizardPieces();

      const activeCustomer = customers.find(c => c.db_id === newOrderState.customerId);
      const allMeasurements = (activeCustomer && activeCustomer.measurements) || [];
      const saved = allMeasurements.find(m => m.garment_type === newOrderState.garmentName);
      newOrderState.savedMeasurementId = saved ? saved.id : null;
      newOrderState.measurementsMode = saved ? 'saved' : 'new';
    } else if (wizardStep === 3) {
      const unitSelect = document.getElementById('wizard-unit');
      if (unitSelect) newOrderState.unit = unitSelect.value;

      const inputs = document.querySelectorAll('.wizard-measurement-input');
      let valid = true;
      let numericError = false;
      const piece = {};

      inputs.forEach(inp => {
        const val = inp.value.trim();
        const isRequired = inp.dataset.required === 'true';
        inp.classList.remove('border-red-500');

        if (isRequired && !val) {
          valid = false;
          inp.classList.add('border-red-500');
        } else if (val) {
          const num = parseFloat(val);
          if (isNaN(num) || num < 0) {
            valid = false;
            numericError = true;
            inp.classList.add('border-red-500');
          } else {
            piece[inp.dataset.label] = val;
          }
        }
      });

      // Store the piece on screen before looking at the others, so nothing the
      // user typed is thrown away by the checks that follow.
      syncWizardPieces();
      newOrderState.pieces[newOrderState.activePiece] = piece;
      newOrderState.measurements = newOrderState.pieces[0] || {};

      if (!valid) {
        if (numericError) {
          showCustomToast('Measurement values numeric aur positive hone chahiye', 'error');
        } else {
          showCustomToast('Chest Losing, Waist Losing, aur Hip Losing required hain', 'error');
        }
        return;
      }

      // A piece the user never opened is still going to be stitched, so every
      // piece is checked here — not just the one that happens to be visible.
      if (newOrderState.measurementsMode !== 'saved') {
        const missing = newOrderState.pieces.findIndex(pc =>
          WIZARD_REQUIRED_MEASUREMENTS.some(key => !pc || !pc[key])
        );

        if (missing > -1) {
          newOrderState.activePiece = missing;
          openModal('add-order-wizard');
          showCustomToast(`Piece ${missing + 1} ki required measurements baaqi hain`, 'error');
          return;
        }
      }
    } else if (wizardStep === 4) {
      const amount = document.getElementById('wizard-amount');
      const advance = document.getElementById('wizard-advance');
      const date = document.getElementById('wizard-date');
      const slot = document.getElementById('wizard-slot');
      const priority = document.getElementById('wizard-priority');
      // Optional on purpose: an order can be booked before the shop decides
      // who stitches it, exactly like the edit modal's "Unassigned".
      const tailor = document.getElementById('wizard-tailor');

      let valid = true;
      [amount, date, slot, priority, advance].forEach(el => {
        if(el) el.classList.remove('border-red-500');
      });

      if (!amount || !amount.value || parseFloat(amount.value) <= 0) { valid = false; if(amount) amount.classList.add('border-red-500'); }
      if (!advance || advance.value === '') { valid = false; if(advance) advance.classList.add('border-red-500'); }
      if (!date || !date.value) { valid = false; if(date) date.classList.add('border-red-500'); }
      if (!slot || !slot.value) { valid = false; if(slot) slot.classList.add('border-red-500'); }
      if (!priority || !priority.value) { valid = false; if(priority) priority.classList.add('border-red-500'); }

      if (!valid) {
        showCustomToast('Ye field zaruri hai', 'error');
        return;
      }

      // A delivery promised for a day that has already passed is never what the
      // counter meant. The `min` attribute can be typed straight past, and a
      // wizard left open overnight carries yesterday's `min`, so the date is
      // re-checked here against the clock as it is right now.
      if (date.value < todayISO()) {
        date.classList.add('border-red-500');
        showCustomToast('Delivery date guzri hui tareekh nahi ho sakti', 'error');
        return;
      }

      newOrderState.price = parseFloat(amount.value);
      newOrderState.advance = parseFloat(advance.value);
      newOrderState.date = date.value;
      newOrderState.slot = slot.value;
      newOrderState.priority = priority.value;
      newOrderState.tailorId = tailor ? tailor.value : '';
    }

    wizardStep++;
    openModal('add-order-wizard');
  };

  window.confirmOrderCreation = async function(btn) {
    if (btn) Atelier.setBusy(btn, true);

    const payload = {
      customer_id:        newOrderState.customerId,
      customer_name:      newOrderState.customerName,
      customer_phone:     newOrderState.customerPhone,
      product_service_id: newOrderState.garmentId || null,
      measurement_id:     newOrderState.measurementsMode === 'saved' ? newOrderState.savedMeasurementId : null,
      quantity:           newOrderState.quantity || 1,
      staff_id:           newOrderState.tailorId || null,
      garment:            newOrderState.garmentName,
      fabric:             newOrderState.fabric,
      style_notes:        newOrderState.styleNotes,
      unit:               newOrderState.unit,
      total:              newOrderState.price,
      advance:            newOrderState.advance,
      priority:           newOrderState.priority,
      delivery_date:      newOrderState.date,
      time_slot:          newOrderState.slot,
      measurements:       newOrderState.measurementsMode === 'saved' ? null : (newOrderState.pieces[0] || {}),
      // One sheet per garment. A saved sheet covers every piece, so `pieces`
      // is left out entirely in that mode.
      pieces:             newOrderState.measurementsMode === 'saved' ? null : newOrderState.pieces,
    };

    try {
      const res = await Atelier.api.post(ROUTES.store, payload);
      const created = upsertOrder(res.order);

      closeModal();
      showCustomToast(res.message || 'Order created successfully', 'success');

      // Business-hours notice, only shown when the shop asked to be warned.
      if (res.warning) setTimeout(() => toast(res.warning, 'warning'), 1200);

      newOrderState = blankOrderState();
      wizardStep = 1;
      renderPage();
      Atelier.refreshCounters();

      setTimeout(() => window.openReceipt(created.db_id), 400);
    } catch (err) {
      Atelier.reportError(err, 'Could not create the order');
    } finally {
      if (btn) Atelier.setBusy(btn, false);
    }
  };

  window.updateWizardBalance = function() {
    const amt = parseFloat(document.getElementById('wizard-amount').value) || 0;
    const adv = parseFloat(document.getElementById('wizard-advance').value) || 0;
    const balEl = document.getElementById('wizard-balance');
    if (balEl) balEl.textContent = Atelier.money(amt - adv);
  };

  window.updateEditPrice = function(selectElem) {
    const option = selectElem.options[selectElem.selectedIndex];
    if (option && option.dataset.price) {
      const amtInput = document.getElementById('edit-amount');
      if (amtInput) amtInput.value = option.dataset.price;
    }
  };

  var extensionReasons = @json($extensionReasons);
  var autoStatus = @json($autoStatus);
  /* Mirrors Order::WORKFLOW. Used only to tell a forward move from a backward
     one; the server owns the actual rules. */
  var WORKFLOW = ['Pending', 'In Progress', 'Ready for Verification', 'Ready', 'Delivered'];

  var viewMode = 'table';
  var wizardStep = 1;
  var orderFilterStatus = 'All';
  var orderSearchTerm = '';
  var orderSort = { key: 'createdAt', dir: 'desc' };
  var selectedOrderIds = new Set();
  var isLoading = false;

  /* ============= PAGINATION STATE ============= */
  var currentPage = 1;
  /* Page size comes from Settings → Theme & Display, so one number governs
     every table in the app. */
  var itemsPerPage = Atelier.rowsPerPage();
  var avatarClasses = ['slate', 'pink', 'green', 'orange', 'blue', 'purple'];

  /* ============= HELPER FUNCTIONS ============= */
  /* Today as YYYY-MM-DD in local time. `toISOString()` is deliberately not
     used: it converts to UTC first, which hands back yesterday's date for
     anyone east of Greenwich for part of every day. */
  function todayISO() {
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  function formatTimeRemaining(dueDate) {
    const now = new Date();
    const diff = dueDate - now;
    if (diff <= 0) return 'Overdue';
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    if (hours > 24) return `${Math.floor(hours / 24)}d ${hours % 24}h remaining`;
    if (hours > 0) return `${hours}h ${mins}m remaining`;
    return `${mins}m remaining`;
  }

  function getAutoStatusCountdown(order) {
    if (!autoStatus.enabled) return null;

    const next  = (autoStatus.next || {})[order.status];
    const delay = (autoStatus.delays || {})[order.status];

    // No next hop, or a delay of zero: this stage is not automated at all, so
    // there is nothing honest to count down to.
    if (!next || !delay) return null;

    const since = order.stageSince || order.createdAt;
    const step  = autoStatus.unit === 'minutes' ? 60 * 1000 : 60 * 60 * 1000;
    const remaining = since.getTime() + (delay * step) - Date.now();

    if (remaining <= 0) return `Auto → ${next}…`;

    const mins = Math.floor(remaining / (1000 * 60));
    if (mins > 60) return `Auto → ${next} in ${Math.floor(mins / 60)}h ${mins % 60}m`;
    if (mins >= 1) return `Auto → ${next} in ${mins}m`;
    return `Auto → ${next} in under a minute`;
  }

  function isDueToday(order) {
    const today = new Date();
    const dueDate = order.dueDate;
    return dueDate.getDate() === today.getDate() &&
      dueDate.getMonth() === today.getMonth() &&
      dueDate.getFullYear() === today.getFullYear() &&
      order.status !== 'Delivered';
  }

  function generateWhatsAppLink(phone, message) {
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    const encodedMsg = encodeURIComponent(message);
    return `https://wa.me/${cleanPhone}?text=${encodedMsg}`;
  }

  async function confirmReadyAndSend(dbId) {
    closeModal();

    try {
      const res = await Atelier.api.post(ROUTES.notify(dbId), { mark_ready: true });
      upsertOrder(res.order);


      toast(res.message, res.notification?.sent === false ? 'warning' : 'success');
      renderPage();
      Atelier.refreshCounters();
    } catch (err) {
      Atelier.reportError(err, 'Could not notify the customer');
    }
  }

  async function startBulkExtend() {
    const daysInput = document.getElementById('bulk-extend-days');
    const reasonInput = document.getElementById('bulk-extend-reason');

    const days = parseInt(daysInput?.value, 10);
    const reason = reasonInput?.value;

    if (!days || days < 1) { toast('Enter how many days to extend by', 'error'); return; }
    if (!reason) { toast('Select a reason for the extension', 'error'); return; }

    document.getElementById('bulk-extend-progress')?.classList.remove('hidden');
    document.getElementById('bulk-extend-form')?.classList.add('hidden');

    try {
      const res = await Atelier.api.post(ROUTES.bulkExtend, { days, reason });
      await refreshOrders();
      closeModal();
      toast(res.message, 'success');
    } catch (err) {
      closeModal();
      Atelier.reportError(err, 'Could not extend the orders');
    }
  }

  /* ========== ORDER FILTERING & BULK SELECTION ========== */
  function filterOrders(status) {
    orderFilterStatus = status;
    currentPage = 1;
    renderPage();
  }

  function getFilteredOrders() {
    let list = orders;

    if (orderFilterStatus === 'Due Today')      list = list.filter(o => o.dueToday);
    else if (orderFilterStatus === 'Overdue')   list = list.filter(o => o.overdue);
    else if (orderFilterStatus !== 'All')       list = list.filter(o => o.status === orderFilterStatus);

    if (orderSearchTerm) {
      const q = orderSearchTerm.toLowerCase();
      list = list.filter(o =>
        (o.id || '').toLowerCase().includes(q) ||
        (o.customer || '').toLowerCase().includes(q) ||
        (o.phone || '').toLowerCase().includes(q) ||
        (o.garment || '').toLowerCase().includes(q) ||
        (o.fabric || '').toLowerCase().includes(q)
      );
    }

    const { key, dir } = orderSort;
    const factor = dir === 'asc' ? 1 : -1;

    return [...list].sort((a, b) => {
      let av = a[key], bv = b[key];
      if (av instanceof Date) { av = av.getTime(); bv = bv.getTime(); }
      if (typeof av === 'string') return factor * av.localeCompare(bv || '');
      return factor * ((av || 0) - (bv || 0));
    });
  }

  window.setOrderSearch = function(value) {
    orderSearchTerm = (value || '').trim();
    currentPage = 1;
    renderPage();
  };

  window.setOrderSort = function(key) {
    orderSort = orderSort.key === key
      ? { key, dir: orderSort.dir === 'asc' ? 'desc' : 'asc' }
      : { key, dir: 'asc' };
    renderPage();
  };

  /* Pull the latest server state without a page reload. */
  async function refreshOrders() {
    try {
      const data = await Atelier.api.get(ROUTES.live);
      orders = data.orders.map(hydrate);
      renderPage();
    } catch (err) { /* polling stays quiet */ }
  }

  function toggleSelectAll(source) {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => {
      cb.checked = source.checked;
      if (source.checked) {
        selectedOrderIds.add(cb.value);
      } else {
        selectedOrderIds.delete(cb.value);
      }
    });
    updateBulkSmsButtonState();
  }

  function toggleSelectOrder(orderId, isChecked) {
    if (isChecked) {
      selectedOrderIds.add(orderId);
    } else {
      selectedOrderIds.delete(orderId);
    }
    updateBulkSmsButtonState();

    const selectAllChk = document.getElementById('select-all-chk');
    if (selectAllChk) {
      const checkboxes = document.querySelectorAll('.order-checkbox');
      selectAllChk.checked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
    }
  }

  function updateBulkStatusState() {
    const has = selectedOrderIds.size > 0;

    ['bulkStatusSelect', 'bulkStatusBtn'].forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      el.disabled = !has;
      el.classList.toggle('opacity-50', !has);
      el.classList.toggle('cursor-not-allowed', !has);
    });
  }

  /**
   * Move every selected order to one status.
   *
   * Orders that cannot legally reach it are skipped by the server rather than
   * failing the batch, and the reply says how many were left behind — so a
   * partial result is never mistaken for a complete one.
   */
  window.confirmBulkStatus = function(btn) {
    const select = document.getElementById('bulkStatusSelect');
    const status = select ? select.value : '';

    if (!status) { toast('Pehle status chunein', 'error'); return; }
    if (selectedOrderIds.size === 0) { toast('Pehle orders select karein', 'error'); return; }

    const ids = orders.filter(o => selectedOrderIds.has(o.id)).map(o => o.db_id);
    const eligible = orders.filter(o => selectedOrderIds.has(o.id) && Array.isArray(o.allowed) && o.allowed.includes(status));

    if (eligible.length === 0) {
      toast(`Selected orders mein se koi bhi ${status} par nahi ja sakta`, 'error');
      return;
    }

    const skipped = ids.length - eligible.length;

    Atelier.confirmAction({
      title: `Move ${eligible.length} order(s) to ${status}?`,
      message: skipped > 0
        ? `${skipped} of the ${ids.length} selected cannot move there and will be left alone.`
        : `All ${ids.length} selected orders will be moved.`,
      confirmLabel: 'Move them',
      variant: status === 'Cancelled' ? 'delete' : 'approve',
      onConfirm: async () => {
        Atelier.setBusy(btn, true);
        try {
          const res = await Atelier.api.post(ROUTES.bulkStatus, {
            status,
            order_ids: ids,
            note: `Bulk moved to ${status}`
          });

          toast(res.message, res.moved > 0 ? 'success' : 'warning');
          selectedOrderIds.clear();
          await refreshOrders();
          Atelier.refreshCounters();
        } catch (err) {
          Atelier.reportError(err, 'Could not move the orders');
        } finally {
          Atelier.setBusy(btn, false);
        }
      }
    });
  };

  function updateBulkSmsButtonState() {
    updateBulkStatusState();

    const btn = document.getElementById('bulkSmsBtn');
    if (!btn) return;

    if (selectedOrderIds.size > 0) {
      btn.disabled = false;
      btn.classList.remove('opacity-50', 'cursor-not-allowed');

      const notifiableCount = notifiableSelection().length;
      const selectedText = document.getElementById('selected-count-text');
      if (selectedText) {
        if (notifiableCount > 0) {
          selectedText.textContent = `${selectedOrderIds.size} order(s) selected | ${notifiableCount} verified & ready to notify`;
          selectedText.classList.remove('text-slate-500');
          selectedText.classList.add('text-emerald-600', 'font-medium');
        } else {
          selectedText.textContent = `${selectedOrderIds.size} order(s) selected | none awaiting verification`;
          selectedText.classList.remove('text-emerald-600');
          selectedText.classList.add('text-amber-600', 'font-medium');
        }
      }
    } else {
      btn.disabled = true;
      btn.classList.add('opacity-50', 'cursor-not-allowed');
      const selectedText = document.getElementById('selected-count-text');
      if (selectedText) {
        selectedText.textContent = '';
      }
    }
  }

  /**
   * The orders a bulk notification may legitimately go out for.
   *
   * Only "Ready for Verification" qualifies. That status means the garments are
   * physically back in the shop and a member of staff has checked them — which
   * is exactly the point at which it is safe to tell a customer to come in.
   * Telling someone their order is ready because a delivery slot elapsed is how
   * a customer arrives to an empty counter.
   */
  function notifiableSelection() {
    return orders.filter(o => selectedOrderIds.has(o.id) && o.status === 'Ready for Verification');
  }

  function confirmBulkSms() {
    if (selectedOrderIds.size === 0) return;

    if (notifiableSelection().length === 0) {
      toast('Select orders that are "Ready for Verification". Verify the garments first, then notify.', 'warning');
      return;
    }

    openModal('bulk-sms-confirm');
  }

  async function startBulkSms() {
    closeModal();
    openModal('bulk-sms-progress');

    const readyOrders = notifiableSelection();
    const total = readyOrders.length;

    const bar = document.getElementById('bulk-sms-progress-bar');
    const count = document.getElementById('bulk-sms-progress-count');
    if (count) count.innerText = `0 out of ${total} processed`;

    try {
      const res = await Atelier.api.post(ROUTES.bulkNotify, {
        order_ids: readyOrders.map(o => o.db_id)
      });

      if (bar) bar.style.width = '100%';
      if (count) count.innerText = `${res.sent} out of ${total} processed`;

      // Open each prefilled WhatsApp thread; browsers allow this right after a
      // user-initiated action, and we cap it so nothing floods the screen.

      await refreshOrders();

      setTimeout(() => {
        closeModal();
        toast(res.message, 'success');
        selectedOrderIds.clear();
        renderPage();
        Atelier.refreshCounters();
      }, 600);
    } catch (err) {
      closeModal();
      Atelier.reportError(err, 'Could not send the notifications');
    }
  }

  /* ============= RENDER LOGIC ============= */
  function renderPagination(filteredOrders) {
    const totalPages = Math.ceil(filteredOrders.length / itemsPerPage);
    if (filteredOrders.length === 0) {
      return `
        <div class="px-6 py-3 border-t border-slate-200 flex items-center justify-between flex-wrap gap-3">
          <div class="text-xs text-slate-500">Showing 0 to 0 of 0 results</div>
        </div>
      `;
    }

    const currentStart = ((currentPage - 1) * itemsPerPage) + 1;
    const currentEnd = Math.min(currentPage * itemsPerPage, filteredOrders.length);

    let controlsHTML = `
      <div class="px-6 py-3 border-t border-slate-200 flex items-center justify-between flex-wrap gap-3">
        <div class="text-xs text-slate-500">Showing ${currentStart} to ${currentEnd} of ${filteredOrders.length} results</div>
        <div class="flex items-center gap-1">
          <button onclick="changeOrderPage(-1)" ${currentPage === 1 ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
            <i class="fa-solid fa-chevron-left text-xs"></i>
          </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      controlsHTML += `
        <button onclick="goToOrderPage(${i})" class="w-8 h-8 flex items-center justify-center text-xs font-medium rounded-md transition-colors ${currentPage === i ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}">
          ${i}
        </button>
      `;
    }

    controlsHTML += `
          <button onclick="changeOrderPage(1)" ${currentPage === totalPages ? 'disabled' : ''} class="w-8 h-8 flex items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
            <i class="fa-solid fa-chevron-right text-xs"></i>
          </button>
        </div>
      </div>
    `;

    return controlsHTML;
  }

  function changeOrderPage(dir) {
    const filteredOrders = getFilteredOrders();
    const totalPages = Math.ceil(filteredOrders.length / itemsPerPage);
    const newPage = currentPage + dir;
    if (newPage >= 1 && newPage <= totalPages) {
      currentPage = newPage;
      renderPage();
    }
  }

  function goToOrderPage(page) {
    currentPage = page;
    renderPage();
  }

  async function saveOrderUpdates(dbId, btn) {
    const garmentSelect = document.getElementById('edit-garment');
    const selectedOption = garmentSelect?.options[garmentSelect.selectedIndex];

    const payload = {
      product_service_id: selectedOption?.dataset.id || null,
      garment:  garmentSelect?.value || null,
      fabric:   document.getElementById('edit-fabric').value,
      status:   document.getElementById('edit-status').value,
      priority: document.getElementById('edit-priority').value,
      staff_id: document.getElementById('edit-tailor')?.value || null,
      total:    parseFloat(document.getElementById('edit-amount').value) || 0,
      advance:  parseFloat(document.getElementById('edit-advance').value) || 0,
      notes:    document.getElementById('edit-notes').value,
    };

    if (payload.advance > payload.total) {
      toast('The advance cannot be more than the total amount', 'error');
      return;
    }

    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.put(ROUTES.update(dbId), payload);
      upsertOrder(res.order);
      closeModal();
      toast(res.message, 'success');
      renderPage();
      Atelier.refreshCounters();
    } catch (err) {
      Atelier.reportError(err, 'Could not update the order');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  /* ============= STATUS TRANSITIONS (kanban + quick actions) ============= */

  /* The same rulebook the server enforces, mirrored here so an impossible move
     is refused before it becomes a failed request. The server is still the
     authority — this only saves the round trip and gives a better message. */
  function statusNeedsReason(from, to) {
    if (to === 'Cancelled') return true;
    const f = WORKFLOW.indexOf(from);
    const t = WORKFLOW.indexOf(to);
    return f > -1 && t > -1 && t < f;
  }

  function sendStatusChange(dbId, status, note) {
    return Atelier.api.patch(ROUTES.status(dbId), { status, note })
      .then(res => {
        upsertOrder(res.order);
        toast(res.message, 'success');
        // Delivering an unpaid order is allowed, but it is never silent.
        if (res.warning) toast(res.warning, 'warning');
        renderPage();
        Atelier.refreshCounters();
      })
      .catch(err => {
        Atelier.reportError(err, 'Could not update the status');
        refreshOrders();
      });
  }

  window.changeOrderStatus = async function(dbId, status, note = null) {
    const order = orders.find(o => o.db_id === dbId);

    if (order && Array.isArray(order.allowed) && !order.allowed.includes(status)) {
      toast(`${order.id} ${order.status} se seedha ${status} par nahi ja sakta`, 'error');
      renderPage();
      return;
    }

    // An unpaid hand-over is the shop's decision, so it is confirmed, not blocked.
    if (order && status === 'Delivered' && (order.balance || 0) > 0 && !note) {
      Atelier.confirmAction({
        title: `Deliver ${order.id} with a balance?`,
        message: `${Atelier.money(order.balance)} is still outstanding on this order. Hand the garment over anyway?`,
        confirmLabel: 'Yes, deliver',
        variant: 'approve',
        onConfirm: () => sendStatusChange(dbId, status, note)
      });
      return;
    }

    if (!note && order && statusNeedsReason(order.status, status)) {
      openModal('status-reason', { db_id: dbId, id: order.id, from: order.status, to: status });
      return;
    }

    await sendStatusChange(dbId, status, note);
  };

  window.submitStatusReason = function(btn, dbId, status) {
    const field = document.getElementById('status-reason-input');
    const note = field ? field.value.trim() : '';

    if (!note) {
      if (field) field.classList.add('border-red-500');
      toast('Wajah likhna zaroori hai', 'error');
      return;
    }

    Atelier.setBusy(btn, true);
    closeModal();
    sendStatusChange(dbId, status, note).finally(() => Atelier.setBusy(btn, false));
  };

  window.deleteOrder = function(dbId, label) {
    Atelier.confirmAction({
      title: `Delete Order ${label}?`,
      message: 'This will permanently erase all data related to this order. This action cannot be undone.',
      confirmLabel: 'Confirm Delete',
      danger: true,
      onConfirm: async () => {
        try {
          const res = await Atelier.api.delete(ROUTES.destroy(dbId));
          removeOrder(dbId);
          toast(res.message, 'success');
          renderPage();
          Atelier.refreshCounters();
        } catch (err) {
          Atelier.reportError(err, 'Could not delete the order');
        }
      }
    });
  };

  /* ============= RECEIPT ============= */

  /**
   * Lines the shop should never have to print twice.
   *
   * The developer credit lives in exactly one place — the fixed block at the
   * bottom of the customer slip. Shops that typed their own version of it into
   * Settings > Receipt Footer would otherwise get it on the paper twice, which
   * is what happened here. Anything in the stored footer that reads as a
   * software credit, a hand-drawn divider, or a bare "thank you" is dropped, so
   * only the shop's own message to the customer survives.
   */
  var FOOTER_NOISE = /(designed\s*&?\s*developed|developed\s+by|powered\s+by|system\s+developed|management\s+system|hingorjo|\bpos\b)/i;

  /* The support number printed in the credit block. Any line in the stored
     footer carrying it is a leftover copy, not new information. */
  var CREDIT_TEL = '03034980786';

  var digitsOf = (t) => String(t).replace(/\D/g, '');

  function tidyFooter(text) {
    return String(text || '')
      .split(/\r?\n/)
      .map(line => line.trim())
      .filter(line => line !== '')
      .filter(line => !/^[\s_\-–—=~.*]+$/.test(line))       // hand-drawn dividers
      .filter(line => !FOOTER_NOISE.test(line))              // duplicate credits
      .filter(line => !/^thank\s*you[\s!.]*$/i.test(line))   // duplicate sign-off
      .filter(line => !digitsOf(line).includes(CREDIT_TEL))  // duplicate phone
      // A line that is nothing but a phone number, whosever it is: the shop's
      // own number already prints in the header.
      .filter(line => !(/^[\s()+\-.\d]+$/.test(line) && digitsOf(line).length >= 7))
      .join('\n');
  }

  window.openReceipt = function(dbId) {
    const o = orders.find(x => x.db_id === dbId);
    if (!o) return;

    const shop = Atelier.shop;

    /* Opens on the in-memory order so the slip is on screen in the same frame
       as the click. The measurements are the one thing the page does not
       already hold, so they arrive a moment later and drop into the job card
       in place — the customer copy is complete and printable either way. */
    openModal('thermal-receipt', {
      store:       shop.name,
      tagline:     shop.tagline,
      address:     shop.address,
      phone:       shop.phone,
      logo:        shop.logo,
      footer:      shop.footer,
      terms:       shop.terms,
      order:       o.id,
      invoice:     o.invoice,
      date:        new Date(o.createdAt).toLocaleDateString('en-IN'),
      customer:    o.customer,
      customer_ph: o.phone,
      garment:     o.garment,
      fabric:      o.fabric,
      total:       o.amount,
      qty:         o.qty,
      unitPrice:   o.unitPrice,
      advance:     o.paid,
      balance:     o.balance,
      due:         o.due + (o.slot ? ', ' + o.slot : ''),
      tailor:      o.tailor,
      priority:    o.priority,
      notes:       o.notes,
      measure:     null,
    });

    Atelier.api.get(ROUTES.receipt(dbId))
      .then(res => fillJobCard(res.receipt || {}))
      .catch(() => {
        const body = document.getElementById('job-measure-body');
        if (body) {
          body.innerHTML = '<div style="text-align:center;font-size:10px">'
            + 'Measurements not available &mdash; check the sheet on file</div>';
        }
      });
  };

  /**
   * Fills the parts of the workshop copy that had to come from the server.
   * Written as a patch rather than a re-render so a print already in progress
   * is never yanked out from under the user.
   */
  function fillJobCard(r) {
    const m = r.measure || {};

    const body = document.getElementById('job-measure-body');
    if (body) {
      const grid = rows => `<div class="slip-mgrid">${rows.map(x => `
            <div class="slip-mcell">
              <span class="l">${Atelier.escapeHtml(x.label)}</span>
              <span class="n">${Atelier.escapeHtml(x.value)}</span>
            </div>`).join('')}</div>`;

      // An order for several garments carries a sheet per garment. Each one
      // prints under its own heading so the cutter never has to work out which
      // numbers belong to which suit; a single-garment job prints as before.
      const pieces = (m.pieces || []).filter(pc => pc.rows && pc.rows.length);
      const rows = m.rows || [];

      if (pieces.length > 1) {
        body.innerHTML = pieces.map(pc => `
          <div class="slip-kind ghost" style="margin:1.5mm 0 1mm">PIECE ${pc.piece}</div>
          ${grid(pc.rows)}
          ${pc.notes ? `<div class="slip-note">&bull; ${Atelier.escapeHtml(pc.notes)}</div>` : ''}
        `).join('');
      } else if (rows.length) {
        body.innerHTML = grid(rows);
      } else {
        body.innerHTML = '<div style="text-align:center;font-size:10px">No measurements recorded for this customer</div>';
      }
    }

    const unit = document.getElementById('job-measure-unit');
    if (unit && m.unit) unit.textContent = '(' + m.unit + ')';

    /* style_notes is what the customer asked for on this garment; the
       measurement sheet's own note is a standing instruction for the cutter.
       Both matter in the workshop, so both print. */
    const notes = document.getElementById('job-notes');
    if (notes) {
      const parts = [];
      if (r.style_notes) parts.push(r.style_notes);
      if (m.notes)       parts.push(m.notes);
      if (r.notes)       parts.push(r.notes);

      if (parts.length) {
        notes.innerHTML = '<div class="slip-rule"></div>'
          + '<div class="slip-sec">INSTRUCTIONS</div>'
          + parts.map(t => `<div class="slip-note">&bull; ${Atelier.escapeHtml(t)}</div>`).join('');
      }
    }

    const tailor = document.getElementById('job-tailor');
    if (tailor && r.tailor) tailor.textContent = r.tailor;
  }

  /* ============= ORDER DETAILS ============= */
  function renderTimeline(entries) {
    if (!entries || !entries.length) {
      return `<div class="relative"><div class="absolute -left-[18px] top-1.5 w-3 h-3 rounded-full bg-white border-2 border-indigo-600"></div><div class="text-sm text-slate-400">No timeline entries yet</div></div>`;
    }

    return entries.map((t, i, arr) => `
      <div class="relative ${i < arr.length - 1 ? 'pb-4' : ''}">
        <div class="absolute -left-[18px] top-1.5 w-3 h-3 rounded-full bg-white border-2 border-indigo-600"></div>
        <div class="text-xs text-slate-500 font-medium">${t.at}</div>
        <div class="text-sm font-medium text-slate-900 mt-0.5">${Atelier.escapeHtml(t.label || '')}</div>
        ${t.note ? `<div class="text-[11px] text-slate-500 mt-0.5">${Atelier.escapeHtml(t.note)}</div>` : ''}
        <div class="text-[11px] text-slate-400 mt-0.5">
          ${t.actor ? `by ${Atelier.escapeHtml(t.actor)}` : ''}${t.actor && t.duration ? ' &middot; ' : ''}${t.duration ? `${t.running ? 'in this stage for ' : 'took '}${Atelier.escapeHtml(t.duration)}` : ''}
        </div>
      </div>
    `).join('');
  }

  /**
   * Opens instantly from the in-memory order, then quietly fills in the
   * timeline once the server responds. Nothing ever blocks on the network.
   */
  window.openOrderDetails = function(dbId) {
    const local = orders.find(o => o.db_id === dbId);
    if (!local) return;

    openModal('order-details', local);

    Atelier.api.get(ROUTES.show(dbId))
      .then(res => {
        const body = document.getElementById('order-timeline-body');
        if (body) body.innerHTML = renderTimeline(res.timeline);
      })
      .catch(() => { /* the modal is already usable; a missing timeline is not worth an alert */ });
  };

  /* ============= CUSTOMER SEARCH LOGIC ============= */
  window.renderCustomerList = function(query = '') {
    const q = query.toLowerCase().trim();
    const listContainer = document.getElementById('customer-list-container');
    if (!listContainer) return;
    
    if (q === '') {
      let recentNames = [];
      if (typeof orders !== 'undefined' && orders.length > 0) {
        recentNames = [...new Set([...orders].sort((a,b) => b.createdAt - a.createdAt).map(o => o.customer))].slice(0, 4);
      }
      let recentCusts = customers.filter(c => recentNames.includes(c.name));
      if (recentCusts.length === 0) recentCusts = customers.slice(0, 4);
      const recentNamesSet = new Set(recentCusts.map(c => c.name));
      const remainingCusts = customers.filter(c => !recentNamesSet.has(c.name));
      
      let html = '';
      if (recentCusts.length > 0) {
        html += '<div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 mt-2">Recent Customers</div>';
        html += recentCusts.map(c => generateCustomerCard(c, true)).join('');
      }
      if (remainingCusts.length > 0) {
        html += '<div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 mt-4">All Customers</div>';
        html += remainingCusts.map(c => generateCustomerCard(c, false)).join('');
      }
      listContainer.innerHTML = html;
      return;
    }
    
    const filtered = customers.filter(c => c.name.toLowerCase().includes(q) || c.phone.includes(q));
    
    if (filtered.length === 0) {
      listContainer.innerHTML = '<div class="text-center py-6 text-sm text-slate-500">Koi customer nahi mila 🔍</div>';
      return;
    }
    
    listContainer.innerHTML = filtered.map(c => generateCustomerCard(c, false)).join('');
  };

  window.selectWizardCustomer = function(dbId) {
    const c = customers.find(x => x.db_id === dbId);
    if (!c) return;

    newOrderState.customerId = c.db_id;
    newOrderState.customerName = c.name;
    newOrderState.customerPhone = c.phone;
    wizardStep = 2;
    openModal('add-order-wizard');
  };

  function generateCustomerCard(c, isRecent) {
    const initials = c.name.split(' ').map(n => n[0]).join('').slice(0, 2);
    return `
      <div class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 hover:border-indigo-600 hover:bg-indigo-50 cursor-pointer mb-2" onclick="selectWizardCustomer(${c.db_id})">
        <div class="avatar sm slate">${initials}</div>
        <div class="flex-1 flex justify-between items-center">
          <div>
            <div class="text-sm font-semibold text-slate-900">${c.name}</div>
            <div class="text-xs text-slate-500">${c.phone} &middot; ${c.orders} previous orders</div>
          </div>
          ${isRecent ? '<span class="badge badge-progress px-1.5 py-0.5 text-[10px]">Recent</span>' : ''}
        </div>
      </div>
    `;
  }

  /* ============= THERMAL PRINT FUNCTION ============= */
  /**
   * Prints one or both slips.
   *
   * The slips are cloned into a bare container rather than printed in place:
   * the modal sits inside a scrolling, transformed ancestor, and print layout
   * inside a transformed parent is unreliable across browsers.
   *
   * @param {'both'|'customer'|'tailor'} which
   */
  window.printThermal = function(which = 'both') {
    const ids = which === 'customer' ? ['slip-customer']
              : which === 'tailor'   ? ['slip-tailor']
              : ['slip-customer', 'slip-tailor'];

    const slips = ids.map(id => document.getElementById(id)).filter(Boolean);
    if (!slips.length) return;

    const area = document.createElement('div');
    area.id = 'thermal-print-area';

    slips.forEach(slip => {
      const copy = slip.cloneNode(true);
      copy.removeAttribute('id');
      copy.classList.remove('slip-preview');
      area.appendChild(copy);
    });

    document.body.appendChild(area);
    window.print();
    document.body.removeChild(area);
  };

  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'order-details': (d) => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div class="flex items-center gap-3">
          <div class="text-lg font-bold text-slate-900 tracking-tight">Order ${d ? d.id : 'Details'}</div>
          <span class="badge ${d.status === 'Pending' ? 'badge-pending' : d.status === 'In Progress' ? 'badge-progress' : d.status === 'Ready for Verification' ? 'badge-trial' : d.status === 'Ready' ? 'badge-ready' : d.status === 'Delivered' ? 'badge-delivered' : 'badge-overdue'}">${d.status}</span>
        </div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-0">
        <div class="lg:col-span-2 p-6 border-r border-slate-200">
          <div class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3">Auto Status Timeline</div>
          <div class="bg-slate-50 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
              ${(() => {
                /* The shop's workflow, in order. Index 0 is "Received", which is
                   not a stored status — an order row existing at all means the
                   material came in, so it is always complete.

                   `done` is derived from the order's position rather than being
                   hardcoded, which is what makes the trail behind the current
                   step fill in green as the order progresses. */
                const STEPS = [
                  { label: 'Received', icon: 'fa-box', status: null },
                  { label: 'Pending', icon: 'fa-clock', status: 'Pending' },
                  { label: 'Stitching', icon: 'fa-scissors', status: 'In Progress' },
                  { label: 'Ready for Verification', icon: 'fa-clipboard-check', status: 'Ready for Verification' },
                  { label: 'Ready', icon: 'fa-check', status: 'Ready' },
                  { label: 'Delivered', icon: 'fa-truck', status: 'Delivered' },
                ];

                /* Where the order stands. "Completed" is Delivered by another
                   name; anything unrecognised sits at Received rather than
                   claiming progress it cannot prove. */
                let at = STEPS.findIndex(st => st.status === d.status);
                if (at < 0) at = d.status === 'Completed' ? STEPS.length - 1 : 0;

                /* Cancelled stops the trail where it is: nothing after the
                   current point should read as achieved. */
                const cancelled = d.status === 'Cancelled';

                return STEPS.map((s, i) => ({
                  ...s,
                  done: !cancelled && i < at,
                  active: !cancelled && i === at,
                }));
              })().map((step, i, arr) => `
                <div class="flex flex-col items-center flex-1 relative">
                  <div class="w-10 h-10 rounded-full flex items-center justify-center ${step.active ? 'bg-indigo-600 text-white ring-4 ring-indigo-50 animate-pulse' : step.done ? 'bg-emerald-500 text-white' : 'bg-white border-2 border-slate-200 text-slate-400'}">
                    <i class="fa-solid ${step.icon} text-xs"></i>
                  </div>
                  <div class="text-[10px] mt-1 text-center ${step.active ? 'font-bold text-indigo-600' : 'text-slate-500'}">${step.label}</div>
                  ${i < arr.length - 1 ? `<div class="absolute top-5 left:1/2 w-full h-0.5 ${step.done ? 'bg-emerald-500' : 'bg-slate-200'}" style="z-index:0"></div>` : ''}
                </div>
              `).join('')}
            </div>
          </div>
          
          <div class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Order Timeline</div>
          <div class="relative pl-6 mb-4">
            <div class="absolute left-[7px] top-2 bottom-2 w-0.5 bg-slate-200"></div>
            <div id="order-timeline-body">
              ${d.timeline
                ? renderTimeline(d.timeline)
                : `<div class="relative pb-4">
                     <div class="absolute -left-[18px] top-1.5 w-3 h-3 rounded-full bg-white border-2 border-slate-200"></div>
                     <div class="h-2.5 w-24 bg-slate-100 rounded animate-pulse"></div>
                     <div class="h-3 w-40 bg-slate-100 rounded mt-2 animate-pulse"></div>
                   </div>
                   <div class="relative">
                     <div class="absolute -left-[18px] top-1.5 w-3 h-3 rounded-full bg-white border-2 border-slate-200"></div>
                     <div class="h-2.5 w-20 bg-slate-100 rounded animate-pulse"></div>
                     <div class="h-3 w-32 bg-slate-100 rounded mt-2 animate-pulse"></div>
                   </div>`}
            </div>
          </div>
        </div>
        <div class="p-6">
          <div class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3">Customer Details</div>
          <div class="flex items-center gap-3 mb-4 cursor-pointer hover:bg-slate-50 p-2 rounded-lg" onclick="closeModal(); window.location.href='/customers'">
            <div class="avatar lg slate">${d.customer.split(' ').map(n => n[0]).join('').slice(0, 2)}</div>
            <div>
              <div class="text-base font-bold text-slate-900">${d.customer}</div>
              <div class="text-xs text-slate-500">${d.phone}</div>
            </div>
          </div>
          <div class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3 mt-4">Payment Summary</div>
          <div class="bg-slate-50 p-4 rounded-lg space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Total:</span> <span class="font-semibold text-slate-900">${Atelier.money(d.amount)}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Advance:</span> <span class="font-semibold text-slate-900">${Atelier.money(d.paid !== undefined ? d.paid : d.advance)}</span></div>
            <div class="flex justify-between border-t border-slate-200 pt-2"><span class="font-bold text-red-500">Balance Due:</span> <span class="font-bold text-red-500">${Atelier.money(d.balance !== undefined ? d.balance : (d.amount - d.advance))}</span></div>
          </div>
          ${d.notes ? `
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 mt-4">Order Notes</div>
            <div class="bg-amber-50 text-amber-700 text-xs p-3 rounded-lg">${d.notes}</div>
          ` : ''}
        </div>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 flex items-center gap-2 transition-colors" onclick="closeModal(); window.openReceipt(${d.db_id})"><i class="fa-solid fa-print text-xs"></i> Print Receipt</button>
        <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="closeModal(); openModal('edit-order', ${JSON.stringify(d).replace(/"/g, '&quot;')})"><i class="fa-solid fa-pen-to-square text-xs"></i> Edit Details</button>
        <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600 flex items-center gap-2 transition-colors shadow-sm shadow-emerald-500/30" onclick="confirmReadyAndSend(${d.db_id})"><i class="fa-brands fa-whatsapp text-xs"></i> Mark Ready & Send WA</button>
      </div>
    `,
    'edit-order': (d) => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div class="flex items-center gap-3">
          <div class="text-lg font-bold text-slate-900 tracking-tight">Edit Order ${d.id}</div>
        </div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-6 overflow-y-auto">
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div class="col-span-2">
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Garment Type *</label>
            <select id="edit-garment" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" onchange="window.updateEditPrice(this)">
              <option value="${Atelier.escapeHtml(d.garment || '')}">${Atelier.escapeHtml(d.garment || 'Custom/Other')}</option>
              ${activeServices.filter(s => s.name !== d.garment).map(s => `<option value="${Atelier.escapeHtml(s.name)}" data-id="${s.id}" data-price="${s.price}">${Atelier.escapeHtml(s.name)}</option>`).join('')}
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Fabric</label>
            <input id="edit-fabric" type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" value="${d.fabric}">
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status *</label>
            <select id="edit-status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
              ${['Pending', 'In Progress', 'Ready for Verification', 'Ready', 'Delivered', 'Cancelled'].map(s => `<option ${d.status === s ? 'selected' : ''}>${s}</option>`).join('')}
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Priority *</label>
            <select id="edit-priority" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
              ${['Normal', 'High', 'Express'].map(p => `<option ${d.priority === p ? 'selected' : ''}>${p}</option>`).join('')}
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Total Amount (${Atelier.currency})</label>
            <input id="edit-amount" type="number" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" value="${d.amount}">
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Advance Paid (${Atelier.currency})</label>
            <input id="edit-advance" type="number" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" value="${d.advance}">
          </div>
          <div class="col-span-2">
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Assigned Tailor</label>
            <select id="edit-tailor" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
              <option value="">Unassigned</option>
              ${tailors.map(t => `<option value="${t.id}" ${d.tailor_id == t.id ? 'selected' : ''}>${Atelier.escapeHtml(t.name)}</option>`).join('')}
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Notes</label>
            <textarea id="edit-notes" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" rows="3" placeholder="Order notes...">${Atelier.escapeHtml(d.notes || '')}</textarea>
          </div>
        </div>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
        <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="saveOrderUpdates(${d.db_id}, this)">Save Changes</button>
      </div>
    `,
    'add-order-wizard': () => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div><div class="text-lg font-bold text-slate-900 tracking-tight">Create New Order</div><div class="text-xs text-slate-500">Step ${wizardStep} of 5</div></div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-2 bg-slate-50 border-b border-slate-200">
        <div class="flex items-center justify-between max-w-2xl mx-auto px-4">
          ${['Customer', 'Garment', 'Measurements', 'Pricing', 'Confirm'].map((s, i) => `
            <div class="flex items-center ${i < 4 ? 'flex-1' : ''}">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold ${wizardStep > i ? 'bg-emerald-500 text-white' : wizardStep === i + 1 ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-400'}">
                ${wizardStep > i ? '<i class="fa-solid fa-check"></i>' : i + 1}
              </div>
              <div class="ml-2 text-xs font-medium ${wizardStep === i + 1 ? 'text-slate-900' : 'text-slate-500'} hidden sm:block">${s}</div>
              ${i < 4 ? `<div class="flex-1 h-0.5 mx-2 ${wizardStep > i + 1 ? 'bg-emerald-500' : 'bg-slate-200'}"></div>` : ''}
            </div>
          `).join('')}
        </div>
      </div>
      <div class="p-6 overflow-y-auto" style="max-height:60vh">
        ${wizardStep === 1 ? `
          <h3 class="text-sm font-semibold text-slate-900 mb-3">Select Customer</h3>
          <div class="relative mb-4">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400"></i>
            <input id="customer-search-input" autofocus oninput="window.renderCustomerList(this.value); document.getElementById('clear-search-btn').classList.toggle('hidden', this.value === '')" class="w-full pl-9 pr-9 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" placeholder="Customer ka naam ya number likho...">
            <button id="clear-search-btn" onclick="document.getElementById('customer-search-input').value=''; window.renderCustomerList(''); this.classList.add('hidden'); document.getElementById('customer-search-input').focus();" class="hidden absolute right-2 top-1.5 text-slate-400 hover:text-slate-600 p-1"><i class="fa-solid fa-xmark"></i></button>
          </div>
          <div id="customer-list-container" class="max-h-60 overflow-y-auto pr-1">
            <img src="x" onerror="window.renderCustomerList(''); setTimeout(() => { const input = document.getElementById('customer-search-input'); if(input) input.focus(); }, 50);" style="display:none;" />
          </div>
        ` : wizardStep === 2 ? `
          <h3 class="text-sm font-semibold text-slate-900 mb-3">Garment & Fabric Details</h3>
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Garment Type *</label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                ${activeServices.map(s => {
                   let icon = s.name.toLowerCase().includes('suit') ? 'fa-vest' : s.name.toLowerCase().includes('shirt') ? 'fa-shirt' : 'fa-vest-patches';
                   let isSelected = newOrderState.garmentId == s.id;
                   return `<button class="p-3 border-2 ${isSelected ? 'border-indigo-600 bg-indigo-50 text-indigo-600' : 'border-slate-200 text-slate-500 hover:border-indigo-600'} rounded-lg text-xs font-medium flex flex-col items-center gap-1 transition-colors" onclick="window.selectWizardGarment('${s.id}', '${s.name.replace(/'/g, "\\'")}', ${s.price})"><i class="fa-solid ${icon} text-lg"></i> <span class="text-center">${s.name}</span></button>`;
                }).join('')}
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Quantity (kitne kapre) *</label>
              <input type="number" id="wizard-qty" min="1" max="20" step="1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" value="${newOrderState.quantity || 1}" oninput="window.updateWizardQuantity(this)">
              <p class="mt-1 text-[11px] text-slate-400">Har piece ka apna naap agle step mein.</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Total (auto)</label>
              <div class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-lg text-sm font-semibold text-slate-900" id="wizard-qty-total">${Atelier.money(newOrderState.price || 0)}</div>
              <p class="mt-1 text-[11px] text-slate-400">${Atelier.money(newOrderState.unitPrice || 0)} \u00d7 ${newOrderState.quantity || 1} \u2014 step 4 par badla ja sakta hai.</p>
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Fabric Selection *</label>
              <input class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" placeholder="e.g. Italian Wool">
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Style Notes</label>
              <textarea class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" placeholder="e.g. Peak lapel, side vents..."></textarea>
            </div>
          </div>
        ` : wizardStep === 3 ? `
          <h3 class="text-sm font-semibold text-slate-900 mb-3">Body Measurements</h3>
          ${(newOrderState.quantity || 1) > 1 ? (
            newOrderState.measurementsMode === 'saved'
              ? `<div class="mb-3 p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                   Saved measurements sab ${newOrderState.quantity} pieces par lagenge. Har piece ka alag naap dena ho to <b>Enter New</b> chunein.
                 </div>`
              : `<div class="flex items-center gap-1.5 mb-3 overflow-x-auto pb-1">
                   ${newOrderState.pieces.map((pc, i) => {
                     const done = WIZARD_REQUIRED_MEASUREMENTS.every(k => pc && pc[k]);
                     const on = newOrderState.activePiece === i;
                     return `<button onclick="window.selectWizardPiece(${i})" class="shrink-0 px-3 py-1.5 rounded-md text-xs font-semibold border transition-colors ${on ? 'bg-indigo-600 border-indigo-600 text-white' : done ? 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:border-emerald-400' : 'bg-white border-slate-200 text-slate-500 hover:border-indigo-400'}">
                               ${done && !on ? '<i class="fa-solid fa-check mr-1"></i>' : ''}Piece ${i + 1}
                             </button>`;
                   }).join('')}
                 </div>`
          ) : ''}
          <div class="flex items-center justify-between gap-2 mb-4 p-2 bg-slate-50 rounded-lg">
            <div class="flex gap-2 w-full max-w-xs">
              <button onclick="newOrderState.measurementsMode='saved'; openModal('add-order-wizard')" class="flex-1 px-3 py-1.5 text-xs font-medium rounded-md ${newOrderState.measurementsMode === 'saved' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-white'}">Use Saved Measurements</button>
              <button onclick="newOrderState.measurementsMode='new'; openModal('add-order-wizard')" class="flex-1 px-3 py-1.5 text-xs font-medium rounded-md ${newOrderState.measurementsMode === 'new' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-white'}">Enter New</button>
            </div>
            <div class="flex items-center gap-2 pr-2">
              <label class="text-xs font-semibold text-slate-500">Unit:</label>
              <select id="wizard-unit" class="text-xs border border-slate-200 rounded p-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 font-medium">
                <option value="cm" ${newOrderState.unit === 'cm' ? 'selected' : ''}>cm</option>
                <option value="in" ${newOrderState.unit === 'in' ? 'selected' : ''}>in</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            ${(() => {
              const activeCustomer = customers.find(c => c.db_id === newOrderState.customerId);
              const allMeasurements = (activeCustomer && activeCustomer.measurements) || [];
              const savedVals = allMeasurements.find(m => m.garment_type === newOrderState.garmentName) || {};
              
              if (newOrderState.measurementsMode === 'saved' && savedVals.unit && !newOrderState._unitLoaded) {
                 newOrderState.unit = savedVals.unit;
                 newOrderState._unitLoaded = true; 
                 setTimeout(() => {
                    const uSel = document.getElementById('wizard-unit');
                    if (uSel) uSel.value = savedVals.unit;
                 }, 0);
              }

              const layout = [
                { key: 'length', label: 'Length' },
                { key: 'shoulder_width', label: 'Shoulder Width' },
                { key: 'sleeve_length', label: 'Sleeve Length' },
                { key: 'chest', label: 'Chest' },
                { key: 'chest_losing', label: 'Chest Losing', req: true },
                { key: 'waist', label: 'Waist' },
                { key: 'waist_losing', label: 'Waist Losing', req: true },
                { key: 'hip', label: 'Hip' },
                { key: 'hip_losing', label: 'Hip Losing', req: true },
                { key: 'collar', label: 'Collar' },
                { key: 'ghera', label: 'Ghera' },
                { key: 'patti', label: 'Patti' },
                { key: 'button', label: 'Button' },
                { key: 'cuff', label: 'Cuff' },
                { key: 'koni', label: 'Koni' },
                { key: 'elbow', label: 'Elbow' },
                { key: 'armhole', label: 'Armhole' },
                { key: 'takai', label: 'Takai' },
                { key: 'salwar_length', label: 'Salwar Length' },
                { key: 'pancho', label: 'Pancho' }
              ];
              
              return layout.map(f => {
                 const activePiece = newOrderState.pieces[newOrderState.activePiece] || {};
                 let val = newOrderState.measurementsMode === 'saved' ? (savedVals[f.key] || '') : (activePiece[f.key] !== undefined ? activePiece[f.key] : '');
                 let reqHtml = f.req ? ' <span class="text-red-500">*</span>' : '';
                 let borderClass = f.req ? 'border-red-200 focus:ring-red-500' : 'border-slate-200 focus:ring-indigo-500';
                 let reqAttr = f.req ? 'data-required="true"' : '';
                 let labelClass = f.req ? 'text-slate-700 font-bold' : 'text-slate-500 font-semibold';
                 return `
                   <div class="col-span-1">
                     <label class="block text-xs ${labelClass} mb-1.5">${f.label}${reqHtml}</label>
                     <input type="number" step="any" min="0" data-label="${f.key}" ${reqAttr} class="wizard-measurement-input w-full px-3 py-2 bg-slate-50 border ${borderClass} rounded-lg text-sm focus:outline-none focus:ring-2 text-slate-900" value="${val}" oninput="this.classList.remove('border-red-500')">
                   </div>
                 `;
              }).join('');
            })()}
          </div>
        ` : wizardStep === 4 ? `
          <h3 class="text-sm font-semibold text-slate-900 mb-3">Pricing & Dates</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Total Amount (${Atelier.currency}) *</label>
              ${(newOrderState.quantity || 1) > 1 ? `<p class="mb-1.5 text-[11px] text-slate-400">${newOrderState.quantity} \u00d7 ${Atelier.money(newOrderState.unitPrice || 0)}</p>` : ''}
              <input type="number" id="wizard-amount" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" value="${newOrderState.price || ''}" oninput="this.classList.remove('border-red-500'); window.updateWizardBalance()">
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Advance Paid (${Atelier.currency}) *</label>
              <input type="number" id="wizard-advance" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" value="${newOrderState.advance !== undefined ? newOrderState.advance : 0}" oninput="this.classList.remove('border-red-500'); window.updateWizardBalance()">
            </div>
            <div class="col-span-2 p-3 bg-emerald-50 rounded-lg flex justify-between items-center">
              <span class="text-sm font-semibold text-emerald-700">Balance Due:</span>
              <span class="text-lg font-bold text-emerald-700" id="wizard-balance">${Atelier.money((newOrderState.price || 0) - (newOrderState.advance || 0))}</span>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Delivery Date *</label>
              <input type="date" id="wizard-date" min="${todayISO()}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" value="${newOrderState.date || ''}" oninput="this.classList.remove('border-red-500')">
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Time Slot *</label>
              <select id="wizard-slot" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" onchange="this.classList.remove('border-red-500')">
                <option value="">Select Slot</option>
                ${timeSlots.map(s => `<option value="${Atelier.escapeHtml(s)}" ${newOrderState.slot === s ? 'selected' : ''}>${Atelier.escapeHtml(s)}</option>`).join('')}
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Priority *</label>
              <select id="wizard-priority" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" onchange="this.classList.remove('border-red-500')">
                <option value="Normal" ${newOrderState.priority === 'Normal' ? 'selected' : ''}>Normal</option>
                <option value="High" ${newOrderState.priority === 'High' ? 'selected' : ''}>High</option>
                <option value="Express" ${newOrderState.priority === 'Express' ? 'selected' : ''}>Express</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tailor</label>
              <select id="wizard-tailor" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900">
                <option value="">Unassigned</option>
                ${tailors.map(t => `<option value="${t.id}" ${newOrderState.tailorId == t.id ? 'selected' : ''}>${Atelier.escapeHtml(t.name)}</option>`).join('')}
              </select>
            </div>
          </div>
        ` : `
          <h3 class="text-sm font-semibold text-slate-900 mb-3">Review & Confirm</h3>
          <div class="bg-slate-50 rounded-xl p-5">
            <div class="flex justify-between items-start mb-4">
              <div>
                <div class="text-xs text-slate-500">Order ID</div>
                <div class="text-lg font-bold text-slate-900">Auto-Generated</div>
              </div>
              <span class="badge badge-pending">Pending</span>
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div><div class="text-xs text-slate-500">Customer</div><div class="font-semibold text-slate-900">${newOrderState.customerName || '-'}</div></div>
              <div><div class="text-xs text-slate-500">Garment</div><div class="font-semibold text-slate-900">${newOrderState.garmentName || '-'}${(newOrderState.quantity || 1) > 1 ? ` \u00d7 ${newOrderState.quantity}` : ''}</div></div>
              <div><div class="text-xs text-slate-500">Fabric</div><div class="font-semibold text-slate-900">${newOrderState.fabric || 'N/A'}</div></div>
              <div><div class="text-xs text-slate-500">Due Date</div><div class="font-semibold text-slate-900">${newOrderState.date ? newOrderState.date + ', ' + newOrderState.slot : '-'}</div></div>
              <div class="col-span-2"><div class="text-xs text-slate-500">Tailor</div><div class="font-semibold text-slate-900">${Atelier.escapeHtml((tailors.find(t => t.id == newOrderState.tailorId) || {}).name || 'Unassigned')}</div></div>
              <div><div class="text-xs text-slate-500">Total Amount</div><div class="font-semibold text-slate-900">${Atelier.money(newOrderState.price || 0)}</div></div>
              <div><div class="text-xs text-slate-500">Advance Paid</div><div class="font-semibold text-slate-900">${Atelier.money(newOrderState.advance || 0)}</div></div>
              <div class="col-span-2"><div class="text-xs text-slate-500">Balance Due</div><div class="font-bold text-red-500 text-lg">${Atelier.money((newOrderState.price || 0) - (newOrderState.advance || 0))}</div></div>
            </div>
          </div>
        `}
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-between">
        <button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="closeModal()">Cancel</button>
        <div class="flex gap-2">
          ${wizardStep > 1 ? `<button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="wizardStep--; openModal('add-order-wizard')">Back</button>` : ''}
          ${wizardStep < 5 ? `<button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700" onclick="wizardNext()">Next <i class="fa-solid fa-arrow-right text-xs ml-1"></i></button>` : `<button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600" onclick="confirmOrderCreation(this)">Confirm & Create <i class="fa-solid fa-check text-xs ml-1"></i></button>`}
        </div>
      </div>
    `,
    'bulk-sms-confirm': () => `
      <div class="p-5 border-b border-slate-200">
        <div class="flex justify-between items-center">
          <div class="text-lg font-bold text-slate-900 tracking-tight">Send Notifications?</div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
      </div>
      <div class="p-6">
        <div class="flex items-start gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
            <i class="fa-solid fa-paper-plane"></i>
          </div>
          <div class="flex-1">
            <p class="text-sm text-slate-700">You are about to notify every selected customer whose order is <span class="font-bold text-emerald-600">Ready for Verification</span>. Once the notice goes out, those orders are marked <span class="font-bold text-emerald-600">Ready</span> automatically. Do you want to continue?</p>
          </div>
        </div>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="closeModal()">Cancel</button>
        <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600 flex items-center gap-2 transition-colors shadow-sm" onclick="startBulkSms()"><i class="fa-solid fa-check text-xs"></i> Confirm & Send</button>
      </div>
    `,
    'bulk-sms-progress': () => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div class="text-lg font-bold text-slate-900 tracking-tight">Sending SMS...</div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-6">
        <div class="text-center py-8">
          <i class="fa-solid fa-spinner fa-spin text-indigo-600 text-3xl mb-4"></i>
          <h4 class="text-base font-semibold text-slate-900">Processing Notifications...</h4>
          <p class="text-sm text-slate-500 mt-1" id="bulk-sms-progress-count">0 out of 0 processed</p>
          <div class="w-full bg-slate-200 rounded-full h-2 mt-4 max-w-xs mx-auto">
            <div class="bg-emerald-500 h-2 rounded-full transition-all duration-100" id="bulk-sms-progress-bar" style="width:0%"></div>
          </div>
        </div>
      </div>
    `,
    /* ------------------------------------------------------------------
     | Order slips — customer receipt + workshop job card
     |------------------------------------------------------------------
     | Two slips, one modal. The customer's copy carries the money; the
     | workshop copy carries the measurements and deliberately carries no
     | pricing at all — the tailor needs to know what to cut and by when, and
     | the shop would rather its margins did not travel around the workshop.
     */
    'thermal-receipt': (o = {}) => {
      const esc = Atelier.escapeHtml;
      const money = Atelier.money;
      const urgent = (o.priority || '').toLowerCase() === 'urgent'
                  || (o.priority || '').toLowerCase() === 'high';

      /* ------------------------------- customer ------------------------- */
      const customerSlip = `
        <div class="slip slip-preview" id="slip-customer">
          <div class="slip-hd">
            ${o.logo ? `<img src="/storage/${esc(o.logo)}" class="slip-logo" alt="">` : ''}
            <div class="slip-shop">${esc((o.store || 'Atelier').toUpperCase())}</div>
            ${o.tagline ? `<div class="slip-tag">${esc(o.tagline)}</div>` : ''}
            <div class="slip-meta">
              ${o.address ? `<div>${esc(o.address)}</div>` : ''}
              ${o.phone ? `<div>Ph: ${esc(o.phone)}</div>` : ''}
            </div>
          </div>

          <div class="slip-kind">CUSTOMER COPY</div>

          <div class="slip-row"><span class="k">Order</span><span class="v slip-bold">${esc(o.order || '')}</span></div>
          ${o.invoice ? `<div class="slip-row"><span class="k">Invoice</span><span class="v">${esc(o.invoice)}</span></div>` : ''}
          <div class="slip-row"><span class="k">Date</span><span class="v">${esc(o.date || '')}</span></div>

          <div class="slip-rule"></div>

          <div class="slip-row"><span class="k">Customer</span><span class="v slip-bold">${esc(o.customer || '')}</span></div>
          ${o.customer_ph ? `<div class="slip-row"><span class="k">Phone</span><span class="v">${esc(o.customer_ph)}</span></div>` : ''}

          <div class="slip-rule"></div>

          <div class="slip-sec">ITEM</div>
          <div class="slip-row">
            <span class="k">${esc(o.garment || 'Stitching')}${(o.qty || 1) > 1 ? ` \u00d7 ${o.qty}` : ''}</span>
            <span class="v">${money(o.total)}</span>
          </div>
          ${(o.qty || 1) > 1 ? `<div class="slip-sub">${o.qty} \u00d7 ${money(o.unitPrice || 0)}</div>` : ''}
          ${o.fabric ? `<div class="slip-sub">Fabric: ${esc(o.fabric)}</div>` : ''}

          <div class="slip-rule"></div>

          <div class="slip-row"><span class="k">Total</span><span class="v">${money(o.total)}</span></div>
          <div class="slip-row"><span class="k">Advance Paid</span><span class="v">${money(o.advance)}</span></div>

          <div class="slip-rule-s"></div>
          <div class="slip-total"><span>BALANCE</span><span>${money(o.balance)}</span></div>
          <div class="slip-rule-d"></div>

          <div class="slip-due">
            <div class="lbl">DELIVERY</div>
            <div class="val">${esc(o.due || 'To be confirmed')}</div>
          </div>

          ${o.terms ? `<div class="slip-foot" style="margin-top:2mm">${esc(o.terms)}</div>` : ''}

          <div class="slip-rule"></div>
          ${(() => { const msg = tidyFooter(o.footer); return msg ? `<div class="slip-foot">${esc(msg)}</div>` : ''; })()}
          <div class="slip-credit">
            <div>Designed &amp; Developed by <span class="name">Noor M Hingorjo</span></div>
            <div class="sys">TAILORING &amp; CLOTH HOUSE MANAGEMENT SYSTEM</div>
            <div class="tel">0303 4980786</div>
            <div class="ty">Thank You!</div>
          </div>
          <div class="slip-code">* ${esc(o.order || '')} *</div>
        </div>`;

      /* -------------------------------- tailor -------------------------- */
      const tailorSlip = `
        <div class="slip slip-preview" id="slip-tailor">
          <div class="slip-hd">
            <div class="slip-shop" style="font-size:14px">${esc((o.store || 'Atelier').toUpperCase())}</div>
          </div>

          <div class="slip-kind">WORKSHOP COPY</div>

          <div class="slip-row"><span class="k">Order</span><span class="v slip-bold">${esc(o.order || '')}</span></div>
          <div class="slip-row"><span class="k">Booked</span><span class="v">${esc(o.date || '')}</span></div>
          <!-- Workshop copy carries the customer's name only. The phone number
               is a customer-contact detail: the tailor never needs it, and a
               slip that walks around the workshop should not carry it. -->
          <div class="slip-row"><span class="k">Customer</span><span class="v slip-bold">${esc(o.customer || '')}</span></div>
          <div class="slip-row"><span class="k">Tailor</span><span class="v slip-bold" id="job-tailor">${esc(o.tailor || 'Unassigned')}</span></div>

          ${urgent ? `<div class="slip-kind ghost" style="margin:2mm 0 0">! ${esc((o.priority || 'URGENT').toUpperCase())} !</div>` : ''}

          <div class="slip-rule"></div>

          <div class="slip-row"><span class="k">Garment</span><span class="v slip-bold">${esc(o.garment || '')}</span></div>
          <div class="slip-row"><span class="k">Pieces</span><span class="v slip-bold">${o.qty || 1}</span></div>
          ${o.fabric ? `<div class="slip-row"><span class="k">Fabric</span><span class="v">${esc(o.fabric)}</span></div>` : ''}

          <div class="slip-due">
            <div class="lbl">DELIVER BY</div>
            <div class="val">${esc(o.due || 'To be confirmed')}</div>
          </div>

          <div class="slip-rule"></div>
          <div class="slip-sec">MEASUREMENTS <span id="job-measure-unit"></span></div>
          <div id="job-measure-body">
            <div style="text-align:center;font-size:10px">Loading measurements&hellip;</div>
          </div>

          <div id="job-notes"></div>

          <div class="slip-rule"></div>
          <div class="slip-sign">
            <div><div class="line"></div>CUT</div>
            <div><div class="line"></div>STITCH</div>
            <div><div class="line"></div>CHECK</div>
          </div>

          <div class="slip-rule"></div>
          <div class="slip-foot">Not a bill &mdash; workshop use only</div>
          <div class="slip-code">* ${esc(o.order || '')} *</div>
        </div>`;

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center no-print">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">Order Slips</div>
            <div class="text-xs text-slate-500 mt-1">Two copies &mdash; one for the customer, one for the workshop</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>

        <div class="p-6 bg-slate-100 overflow-y-auto print-container" style="max-height:62vh">
          <div class="flex flex-wrap gap-8 justify-center items-start">
            <div>
              <div class="slip-label no-print">Customer Copy</div>
              ${customerSlip}
            </div>
            <div>
              <div class="slip-label no-print">Workshop Copy</div>
              ${tailorSlip}
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-slate-200 flex flex-wrap justify-between gap-2 bg-white no-print">
          <button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="closeModal()">Close</button>
          <div class="flex flex-wrap gap-2">
            <button class="bg-white border border-slate-200 text-slate-600 px-3 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 flex items-center gap-2" onclick="window.printThermal('customer')">
              <i class="fa-solid fa-print text-xs"></i> Customer only
            </button>
            <button class="bg-white border border-slate-200 text-slate-600 px-3 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 flex items-center gap-2" onclick="window.printThermal('tailor')">
              <i class="fa-solid fa-print text-xs"></i> Workshop only
            </button>
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 flex items-center gap-2" onclick="window.printThermal('both')">
              <i class="fa-solid fa-print text-xs"></i> Print both
            </button>
          </div>
        </div>
      `;
    },
    'status-reason': (d = {}) => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div>
          <div class="text-lg font-bold text-slate-900 tracking-tight">Why the change?</div>
          <div class="text-xs text-slate-500 mt-1">${Atelier.escapeHtml(d.id || '')} &middot; ${Atelier.escapeHtml(d.from || '')} &rarr; ${Atelier.escapeHtml(d.to || '')}</div>
        </div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-6">
        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Reason *</label>
        <textarea id="status-reason-input" rows="3" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Customer ne design change karwaya"></textarea>
        <p class="mt-2 text-[11px] text-slate-400">Ye wajah order ki timeline mein hamesha ke liye record ho jayegi.</p>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="closeModal()">Cancel</button>
        <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 shadow-sm" onclick="window.submitStatusReason(this, ${d.db_id}, '${d.to}')">Save &amp; Move</button>
      </div>
    `,
    'bulk-extend': () => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div>
          <div class="text-lg font-bold text-slate-900 tracking-tight">Extend Delivery Dates</div>
          <div class="text-xs text-slate-500 mt-1">Applies to every order that is still open</div>
        </div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-6" id="bulk-extend-form">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Extend By (days) *</label>
            <input type="number" id="bulk-extend-days" min="1" max="90" value="1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Reason *</label>
            <select id="bulk-extend-reason" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
              ${extensionReasons.map(r => `<option value="${Atelier.escapeHtml(r)}">${Atelier.escapeHtml(r)}</option>`).join('')}
            </select>
          </div>
        </div>
      </div>
      <div class="p-6 hidden" id="bulk-extend-progress">
        <div class="text-center py-6">
          <i class="fa-solid fa-spinner fa-spin text-indigo-600 text-3xl mb-4"></i>
          <h4 class="text-base font-semibold text-slate-900">Updating orders…</h4>
          <p class="text-sm text-slate-500 mt-1" id="bulk-progress-count"></p>
          <div class="w-full bg-slate-200 rounded-full h-2 mt-4 max-w-xs mx-auto">
            <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" id="bulk-progress-bar" style="width:60%"></div>
          </div>
        </div>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="closeModal()">Cancel</button>
        <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="startBulkExtend()"><i class="fa-solid fa-check text-xs"></i> Apply Extension</button>
      </div>
    `
  });

  var pages = {
    orders: () => {
      const filteredOrders = getFilteredOrders();
      const counts = {
        'All': orders.length,
        'Pending': orders.filter(o => o.status === 'Pending').length,
        'In Progress': orders.filter(o => o.status === 'In Progress').length,
        /* Overdue is a condition, not a status: an order can be overdue while
           it is still being stitched. Counted from the date, never the column. */
        'Overdue': orders.filter(o => o.overdue).length,
        'Due Today': orders.filter(o => o.dueToday).length
      };

      const openCount    = orders.filter(o => !['Delivered', 'Completed', 'Cancelled'].includes(o.status)).length;
      const toVerify     = orders.filter(o => o.status === 'Ready for Verification').length;

      const totalPages = Math.ceil(filteredOrders.length / itemsPerPage);
      if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
      const start = (currentPage - 1) * itemsPerPage;
      const end = start + itemsPerPage;
      const paginatedOrders = filteredOrders.slice(start, end);

      return `
        <div class="page flex justify-between items-center mb-6">
          <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Order Management</h1>
            <p class="text-sm text-slate-500 mt-0.5">${openCount} active orders · ${toVerify} awaiting verification · ${counts['Overdue']} overdue</p>
          </div>
          <div class="flex gap-2">
            <div class="flex bg-white border border-slate-200 rounded-lg p-1 shadow-sm">
              <button class="px-3 py-1 text-xs font-medium ${viewMode === 'table' ? 'bg-slate-900 text-white' : 'text-slate-500'} rounded-md transition-colors" onclick="viewMode='table'; renderPage()"><i class="fa-solid fa-table text-[10px]"></i> Table</button>
              <button class="px-3 py-1 text-xs font-medium ${viewMode === 'kanban' ? 'bg-slate-900 text-white' : 'text-slate-500'} rounded-md transition-colors" onclick="viewMode='kanban'; renderPage()"><i class="fa-solid fa-columns text-[10px]"></i> Kanban</button>
            </div>
            <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="newOrderState = blankOrderState(); wizardStep=1; openModal('add-order-wizard')"><i class="fa-solid fa-plus text-[10px]"></i> Create Order</button>
          </div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6 page">
          ${['All', 'Pending', 'In Progress', 'Overdue', 'Due Today'].map(status => `
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all ${orderFilterStatus === status ? 'border-slate-900 ring-2 ring-slate-100' : ''}" onclick="filterOrders('${status}')">
              <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">${status}</p>
              <h3 class="text-xl font-bold text-slate-900 mt-1 tracking-tight">${counts[status]}</h3>
            </div>
          `).join('')}
        </div>
        
        ${viewMode === 'table' ? `
          <div class="page bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200 flex justify-between items-center flex-wrap gap-2">
              <h3 class="text-base font-semibold text-slate-900 tracking-tight">Orders Table</h3>
              <div class="flex gap-2 items-center">
                <div class="relative">
                  <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
                  <input id="orders-search" type="text" value="${Atelier.escapeHtml(orderSearchTerm)}" oninput="setOrderSearch(this.value)" placeholder="Search orders..." class="w-48 h-8 pl-8 pr-3 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-all">
                </div>
                <span class="text-xs text-slate-500 mr-2" id="selected-count-text"></span>
                <select id="bulkStatusSelect" class="h-8 px-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                  <option value="">Move selected to…</option>
                  ${['In Progress', 'Ready for Verification', 'Ready', 'Delivered', 'Completed', 'Cancelled'].map(st => `<option value="${st}">${st}</option>`).join('')}
                </select>
                <button id="bulkStatusBtn" class="bg-slate-900 text-white px-4 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm" onclick="confirmBulkStatus(this)" disabled>
                  <i class="fa-solid fa-arrow-right-arrow-left text-[10px]"></i> Move
                </button>
                <button id="bulkSmsBtn" class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-xs font-medium hover:bg-emerald-600 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm shadow-emerald-500/30" onclick="confirmBulkSms()" disabled>
                  <i class="fa-solid fa-paper-plane text-[10px]"></i> Bulk Send Notifications
                </button>
              </div>
            </div>
            <div class="overflow-x-auto min-h-[300px]">
              <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest">
                  <tr>
                    <th class="chk-col py-3 text-left font-bold"><label class="chk-hit"><input type="checkbox" id="select-all-chk" class="chk" onchange="toggleSelectAll(this)"></label></th>
                    <th class="px-5 py-3 text-left font-bold cursor-pointer select-none" onclick="setOrderSort('id')">Order ID</th>
                    <th class="px-5 py-3 text-left font-bold cursor-pointer select-none" onclick="setOrderSort('customer')">Customer</th>
                    <th class="px-5 py-3 text-left font-bold cursor-pointer select-none" onclick="setOrderSort('garment')">Garment</th>
                    <th class="px-5 py-3 text-left font-bold cursor-pointer select-none" onclick="setOrderSort('priority')">Priority</th>
                    <th class="px-5 py-3 text-left font-bold cursor-pointer select-none" onclick="setOrderSort('amount')">Amount</th>
                    <th class="px-5 py-3 text-left font-bold cursor-pointer select-none" onclick="setOrderSort('dueDate')">Due</th>
                    <th class="px-5 py-3 text-left font-bold cursor-pointer select-none" onclick="setOrderSort('status')">Status</th>
                    <th class="px-5 py-3 text-left font-bold">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  ${paginatedOrders.map((o, i) => {
                    const dueToday = isDueToday(o) || o.due === 'Today';
                    const autoCD = getAutoStatusCountdown(o);
                    const isSelected = selectedOrderIds.has(o.id);
                    const avClass = avatarClasses[i % avatarClasses.length];
                    const orderDataStr = JSON.stringify(o).replace(/"/g, '&quot;');
                    return `
                    <tr class="hover:bg-slate-50 transition-colors ${dueToday && o.status !== 'Delivered' ? 'border-l-4 border-l-red-500' : ''} ${isSelected ? 'bg-indigo-50/30' : ''}">
                      <td class="chk-col py-3"><label class="chk-hit"><input type="checkbox" class="chk order-checkbox" value="${o.id}" onchange="toggleSelectOrder('${o.id}', this.checked)" ${isSelected ? 'checked' : ''}></label></td>
                      <td class="px-5 py-3 font-semibold text-slate-900">${o.id}</td>
                      <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                          <div class="avatar sm ${avClass}">${o.customer.split(' ').map(n => n[0]).join('').slice(0, 2)}</div>
                          <span class="font-medium text-slate-600">${o.customer}</span>
                        </div>
                      </td>
                      <td class="px-5 py-3 text-slate-600">${o.garment}<div class="text-xs text-slate-400">${o.fabric}</div></td>
                      <td class="px-5 py-3">${o.priority === 'Express' ? '<span class="badge badge-overdue">Express</span>' : o.priority === 'High' ? '<span class="badge badge-pending">High</span>' : '<span class="text-xs text-slate-500">Normal</span>'}</td>
                      <td class="px-5 py-3 font-semibold text-slate-900">${Atelier.money(o.amount)}</td>
                      <td class="px-5 py-3 text-slate-600 ${dueToday && o.status !== 'Delivered' ? 'text-red-500 font-semibold' : ''}">${o.due}</td>
                      <td class="px-5 py-3">
                        <div class="flex flex-col gap-1">
                          <span class="badge ${o.status === 'Pending' ? 'badge-pending' : o.status === 'In Progress' ? 'badge-progress' : o.status === 'Ready for Verification' ? 'badge-trial' : o.status === 'Ready' ? 'badge-ready' : o.status === 'Delivered' ? 'badge-delivered' : 'badge-overdue'}">${o.status}</span>
                          ${o.overdue ? '<span class="badge badge-overdue text-[9px]">Overdue</span>' : o.atRisk ? '<span class="badge badge-pending text-[9px]"><i class="fa-solid fa-triangle-exclamation mr-1"></i>At Risk</span>' : ''}
                          ${autoCD ? `<span class="text-[9px] text-slate-400" data-countdown="${o.id}">${autoCD}</span>` : ''}
                          ${o.notified ? '<span class="badge badge-notified text-[9px]"><i class="fa-brands fa-whatsapp mr-1"></i>Notified</span>' : ''}
                        </div>
                      </td>
                      <td class="px-5 py-3 text-right whitespace-nowrap flex justify-end">
                        <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 inline-flex items-center justify-center transition-colors mr-1" title="View" onclick="openOrderDetails(${o.db_id})"><i class="fa-regular fa-eye text-xs"></i></button>
                        <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 inline-flex items-center justify-center transition-colors mr-1" title="Edit" onclick="openModal('edit-order', ${orderDataStr})"><i class="fa-solid fa-pen-to-square text-xs"></i></button>
                        <span class="inline-block m-0">
                          <button type="button" onclick="deleteOrder(${o.db_id}, '${o.id}')" class="w-8 h-8 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 inline-flex items-center justify-center transition-colors" title="Delete">
                            <i class="fa-solid fa-trash text-xs"></i>
                          </button>
                        </span>
                      </td>
                    </tr>
                    `;
                  }).join('') || `<tr><td colspan="9" class="p-0">${Atelier.emptyState({
                      icon: 'fa-scissors',
                      title: orderSearchTerm || orderFilterStatus !== 'All' ? 'No matching orders' : 'No orders yet',
                      message: orderSearchTerm || orderFilterStatus !== 'All'
                        ? 'Try a different search term or clear the active filter.'
                        : 'Create your first order and it will appear here.'
                    })}</td></tr>`}
                </tbody>
              </table>
            </div>
            ${renderPagination(filteredOrders)}
          </div>
        ` : `
          <div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            ${['Pending', 'In Progress', 'Ready for Verification', 'Ready', 'Delivered'].map(status => {
              const columnOrders = filteredOrders.filter(o => o.status === status);
              return `
              <div class="bg-white rounded-xl border border-slate-200 p-3 min-h-[400px] kanban-col shadow-sm" data-status="${status}">
                <div class="flex justify-between items-center mb-3">
                  <h4 class="font-semibold text-slate-900 text-sm tracking-tight">${status}</h4>
                  <span class="bg-slate-100 text-slate-500 text-xs px-2 py-0.5 rounded-full font-medium">${columnOrders.length}</span>
                </div>
                <div class="space-y-3">
                  ${columnOrders.map((o, i) => {
                    const avClass = avatarClasses[i % avatarClasses.length];
                    const orderDataStr = JSON.stringify(o).replace(/"/g, '&quot;');
                    return `
                    <div class="bg-slate-50 p-3 rounded-lg shadow-sm kanban-card border-l-4 ${o.priority === 'Express' ? 'border-red-500' : o.priority === 'High' ? 'border-amber-500' : 'border-indigo-600'}" draggable="true" data-order-id="${o.db_id}" onclick="openOrderDetails(${o.db_id})">
                      <div class="flex justify-between items-start mb-2">
                        <div class="text-xs font-bold text-slate-900">${o.id}</div>
                        <div class="flex items-center gap-1">
                           ${o.notified ? '<i class="fa-brands fa-whatsapp text-emerald-500 text-xs"></i>' : ''}
                           <button class="text-slate-300 hover:text-indigo-600 transition-colors" onclick="event.stopPropagation(); openModal('edit-order', ${orderDataStr})"><i class="fa-solid fa-pen-to-square text-[10px]"></i></button>
                           <button type="button" onclick="event.stopPropagation(); deleteOrder(${o.db_id}, '${o.id}')" class="text-slate-300 hover:text-red-600 transition-colors"><i class="fa-solid fa-trash text-[10px]"></i></button>
                        </div>
                      </div>
                      <div class="flex items-center gap-2 mb-2">
                        <div class="avatar sm ${avClass}" style="width:24px;height:24px;font-size:10px">${Atelier.initials(o.customer)}</div>
                        <div class="text-xs text-slate-600 truncate">${Atelier.escapeHtml(o.customer)}</div>
                      </div>
                      <div class="text-xs text-slate-500 mb-2 truncate">${Atelier.escapeHtml(o.garment)}</div>
                      <div class="flex justify-between items-center text-xs">
                        <span class="font-semibold text-slate-900">${Atelier.money(o.amount)}</span>
                        <span class="${o.dueToday ? 'text-red-500 font-bold' : 'text-slate-500'}">${o.due}</span>
                      </div>
                      <div class="w-full h-1 bg-slate-200 rounded-full mt-2"><div class="h-full rounded-full bg-indigo-600" style="width:${o.progress}%"></div></div>
                    </div>
                  `}).join('') || `<div class="text-center py-8 text-xs text-slate-400">Drop an order here</div>`}
                </div>
                <div class="mt-3 text-center text-xs font-bold text-slate-900">${Atelier.money(columnOrders.reduce((s, o) => s + o.amount, 0))}</div>
              </div>
            `}).join('')}
          </div>
        `}
      `;
    }
  };

  /* ============= KANBAN DRAG & DROP → PATCH /orders/{id}/status ============= */
  var draggedOrderId = null;

  function bindKanbanDragDrop() {
    document.querySelectorAll('.kanban-card').forEach(card => {
      card.addEventListener('dragstart', e => {
        draggedOrderId = parseInt(card.dataset.orderId, 10);
        e.dataTransfer.effectAllowed = 'move';
        card.style.opacity = '0.5';
      });
      card.addEventListener('dragend', () => {
        card.style.opacity = '';
        draggedOrderId = null;
      });
    });

    document.querySelectorAll('.kanban-col').forEach(col => {
      col.addEventListener('dragover', e => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        col.classList.add('drag-over');
      });
      col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
      col.addEventListener('drop', async e => {
        e.preventDefault();
        col.classList.remove('drag-over');

        const id = draggedOrderId;
        const status = col.dataset.status;
        if (!id || !status) return;

        const order = orders.find(o => o.db_id === id);
        if (!order || order.status === status) return;

        // No note is passed for a backward drag on purpose: changeOrderStatus
        // will ask for the reason rather than inventing one.
        await window.changeOrderStatus(
          id,
          status,
          statusNeedsReason(order.status, status) ? null : 'Moved on the kanban board'
        );
      });
    });
  }

  function renderPage() {
    const container = document.getElementById('page-container');
    if (!container) return;

    container.innerHTML = pages.orders();
    updateBulkSmsButtonState();

    if (viewMode === 'kanban') bindKanbanDragDrop();

    // Preserve the search caret when the list re-renders under the user.
    const search = document.getElementById('orders-search');
    if (search && document.activeElement !== search && orderSearchTerm) {
      search.value = orderSearchTerm;
    }
  }

  /**
   * Handles hand-offs from other pages, e.g. "customer just created — start
   * their first order". Recognised query params:
   *
   *   ?action=create            open the wizard
   *   &customer=<id>            pre-select that customer and skip to step 2
   *   &new_customer=1           confirm the customer was just saved
   *   ?highlight=<id>           open that order's details
   */
  function handleIncomingIntent() {
    const params = new URLSearchParams(window.location.search);
    let handled = false;

    if (params.get('action') === 'create') {
      newOrderState = blankOrderState();

      const customerId = parseInt(params.get('customer'), 10);
      const customer = customerId ? customers.find(c => c.db_id === customerId) : null;

      if (customer) {
        if (params.get('new_customer')) {
          toast(`${customer.name} added — let's create their first order`, 'success');
        }
        // Customer is already known, so step 1 has nothing left to ask.
        window.selectWizardCustomer(customer.db_id);
      } else {
        if (customerId) {
          toast('That customer could not be found — please pick one below', 'warning');
        }
        wizardStep = 1;
        openModal('add-order-wizard');
      }

      handled = true;
    }

    const highlight = parseInt(params.get('highlight'), 10);
    if (highlight) {
      openOrderDetails(highlight);
      handled = true;
    }

    // Clean the URL so a refresh or back-navigation doesn't repeat the action.
    if (handled) {
      window.history.replaceState({}, '', @json(route('orders.index')));
    }
  }

  Atelier.onPageReady(() => {
    renderPage();
    handleIncomingIntent();

    // Live sync: statuses change from other devices and auto-transitions.
    Atelier.poll(refreshOrders, 45000);
  });
</script>
@endpush