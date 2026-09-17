  // Create and edit use one state tree; DOM changes write directly into it.
  var itemProfiles = @json(\App\Services\MeasurementProfiles::all());
  var itemPricing = @json(\App\Services\PricingService::breakdown(0));
  var itemKey = () => 'piece-' + crypto.randomUUID();
  var blankPiece = () => ({client_key:itemKey(), unit:'in', values:{}});
  var blankGarment = () => ({client_key:itemKey(), product_service_id:'', quantity:1, unit_price:'0.00', tailor_rate_override:'', fabric:'', style_notes:'', pieces:[blankPiece()]});
  blankOrderState = () => ({customerId:null, customerName:'', customerPhone:'', garments:[blankGarment()], advance:0, date:'', slot:'', priority:'Normal', tailorId:'', notes:'', activeItem:0, activePiece:0});
  newOrderState = blankOrderState();
  
  if (typeof window.appendMeasurementQuickAction !== 'function') {
      window.appendMeasurementQuickAction = function(targetId, text) {
          const textarea = document.getElementById(targetId);
          if (!textarea) return;
          const current = textarea.value.trim();
          const addition = current ? ', ' + text : text;
          if (current.length + addition.length > 2000) {
              if (typeof toast === 'function') toast('Notes limit reached.', 'error');
              return;
          }
          textarea.value = current ? current + addition : text;
          textarea.dispatchEvent(new Event('input', { bubbles: true }));
      };
  }
  var itemEsc = value => Atelier.escapeHtml(String(value ?? ''));
  var itemInputClass = 'w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900';
  var itemButtonClass = 'px-3 py-2 rounded-lg border border-slate-200 text-sm hover:bg-indigo-50';
  function rowProduct(row) { return activeServices.find(s => s.id == row.product_service_id); }
  function rowProfile(row) { return row.pieces[0]?.profile || rowProduct(row)?.profile || itemProfiles.generic; }
  function rowName(row) { return row.name || rowProduct(row)?.name || 'Choose garment'; }
  function rowComplete(row, piece) {
    const p = rowProfile(row), filled = f => piece.values[f] !== '' && piece.values[f] !== null && piece.values[f] !== undefined;
    return p.required.every(filled) && (!p.at_least_one || p.fields.some(filled));
  }
  function itemTotals() {
    const subtotal = newOrderState.garments.reduce((s,r) => s + Math.round(Number(r.unit_price || 0)*100)*Number(r.quantity),0)/100;
    const rate = Number(itemPricing.tax_rate)+Number(itemPricing.service_charge_rate);
    const original=newOrderState.originalGarments;
    const samePrices=original&&original.length===newOrderState.garments.length&&newOrderState.garments.every(r=>original.some(o=>o.id===r.id&&Number(o.quantity)===Number(r.quantity)&&Number(o.unit_price)===Number(r.unit_price)));
    const total = samePrices ? newOrderState.originalTotal : (itemPricing.tax_inclusive ? subtotal : Math.round(subtotal*(100+rate))/100);
    return {subtotal,total,balance:total-Number(newOrderState.editId ? newOrderState.paid : newOrderState.advance)};
  }
  function itemRefresh() { renderPage(); }
  window.itemField = (i,key,value) => { newOrderState.garments[i][key]=value; };
  window.itemChoose = (i, id, price, name) => {
    const row = newOrderState.garments[i];
    if (row.pieces.some(p => Object.values(p.values).some(v => v !== '' && v !== null)) && !confirm('Changing garment clears incompatible measurements. Continue?')) { itemRefresh(); return; }
    const product = activeServices.find(s => s.id == id);
    row.product_service_id = Number(id); row.name = name || product?.name; row.unit_price = price !== undefined ? price : (product?.price || '0.00');
    row.pieces.forEach(p => { p.values={}; delete p.measurement_id; delete p.saved_measurement_id; delete p.saved_changes; delete p.measurement_mode; p.profile=product?.profile; });
    itemRefresh();
  };
  window.itemChooseRate = (i, rateId) => {
    let rate = null;
    for (const cat of tailorCategories) {
        rate = cat.rates.find(r => r.id == rateId);
        if (rate) break;
    }
    if (!rate || !rate.service) return;
    window.itemChoose(i, rate.service.id, rate.price, rate.name);
  };
  window.itemQuantity = (i,value) => {
    const row = newOrderState.garments[i], qty = Math.max(1,Math.min(Math.max(20,row.originalQuantity||0),parseInt(value)||1));
    if (qty < row.pieces.length && row.pieces.slice(qty).some(p => p.id || Object.values(p.values).some(v => v !== '' && v !== null)) && !confirm('Remove the trailing pieces? Their saved history will be retained.')) { itemRefresh(); return; }
    while(row.pieces.length<qty) {
      const first=row.pieces[0];
      row.pieces.push({...blankPiece(), unit:first.unit, values:{...first.values}, measurement_id:first.measurement_id,
        saved_measurement_id:first.saved_measurement_id, measurement_mode:first.measurement_mode,
        saved_changes:first.saved_changes ? JSON.parse(JSON.stringify(first.saved_changes)) : undefined});
    }
    row.pieces.length=qty; row.quantity=qty; newOrderState.activePiece=0; itemRefresh();
  };
  window.itemRemove = i => {
    if (newOrderState.garments.length===1) return toast('Keep at least one garment.','error');
    if (!confirm('Remove this garment and its pieces? Saved history will be retained.')) return;
    newOrderState.garments.splice(i,1); newOrderState.activeItem=0; newOrderState.activePiece=0; itemRefresh();
  };
  window.itemAdd = () => { if(newOrderState.garments.length<50) { newOrderState.garments.push(blankGarment()); newOrderState.activeItem = newOrderState.garments.length - 1; } itemRefresh(); };
  window.itemTab = (i) => { newOrderState.activeItem=i; itemRefresh(); };
  window.itemMeasure = (field,value) => { 
    newOrderState.garments.forEach(row => {
      row.pieces.forEach(p => {
        p.values[field]=value;
        if (p.measurement_id) { p.saved_changes ||= {}; p.saved_changes.values ||= {}; p.saved_changes.values[field]=value; }
      });
    });
  };
  window.itemUnit = value => { 
    newOrderState.garments[newOrderState.activeItem].pieces.forEach(p => {
      p.unit=value;
      if (p.measurement_id) { p.saved_changes ||= {}; p.saved_changes.unit=value; }
    });
  };
  function inferSavedProfile(sheet) {
    if(sheet.profile_key) return sheet.profile_key;
    const name=(sheet.garment_type||'').toLowerCase();
    for(const [word,key] of Object.entries({shalwar:'shalwar_kameez',sherwani:'sherwani',waistcoat:'waistcoat',kurta:'kurta_pajama',trouser:'trouser',alteration:'alteration'})) if(name.includes(word)) return key;
    return 'generic';
  }
  function compatibleSavedMeasurements(row) {
    return (customers.find(c => c.db_id == newOrderState.customerId)?.measurements || [])
      .filter(m => m.customer_id == newOrderState.customerId && inferSavedProfile(m) === rowProfile(row).key)
      .sort((a,b) => (Date.parse(b.updated_at) || 0) - (Date.parse(a.updated_at) || 0) || Number(b.id) - Number(a.id));
  }
  window.itemSaved = id => {
    const row=newOrderState.garments[newOrderState.activeItem];
    const saved = compatibleSavedMeasurements(row);
    const sheet = id ? saved.find(m=>m.id==id) : saved.find(m=>m.id==row.pieces[0].measurement_id) || saved[0];
    if(!sheet && !id) {
       newOrderState.garments.forEach(r => r.pieces.forEach(p => { p.values={}; delete p.measurement_id; delete p.saved_measurement_id; delete p.saved_changes; p.measurement_mode='new'; }));
       itemRefresh(); return;
    }
    if(sheet) {
      newOrderState.garments.forEach(r => r.pieces.forEach(p => {
         // New updates the selected set and retains any edits already made.
         if (!id && p.measurement_id == sheet.id) { p.measurement_mode='new'; return; }
         p.measurement_id = sheet.id;
         p.saved_measurement_id = sheet.id;
         p.measurement_mode = id ? 'saved' : 'new';
         delete p.saved_changes;
         p.unit = sheet.unit || 'in';
         const values = {...sheet, ...(sheet.details || {})};
         p.values = Object.fromEntries(rowProfile(r).fields.map(k => [k, values[k] ?? '']));
         p.values.notes = values.notes ?? '';
      }));
      itemRefresh();
    }
  };
  window.itemGo = direction => {
    if(direction>0) {
      if(wizardStep===1 && !newOrderState.customerId) return toast('Select a customer.','error');
      if(wizardStep===2 && newOrderState.garments.some(r=>!r.product_service_id&&!r.id)) return toast('Choose a garment for every row.','error');
      if(wizardStep===3 && !newOrderState.editId && newOrderState.garments.some(r=>r.pieces.some(p=>!rowComplete(r,p)))) return toast('Complete the required measurements for every piece.','error');
      if(wizardStep===4 && (!newOrderState.date || !newOrderState.slot || itemTotals().balance<0)) return toast('Check the delivery date and payment amount.','error');
    }
    wizardStep=Math.max(newOrderState.editId?2:1,Math.min(5,wizardStep+direction)); itemRefresh();
  };
  window.itemSubmit = async btn => {
    const s=newOrderState, payload={priority:s.priority, delivery_date:s.date, delivery_time:s.slot, staff_id:s.tailorId||null, notes:s.notes};
    if(!s.locked) payload.garments=s.garments.map(r=>({id:r.id,client_key:r.client_key,product_service_id:r.product_service_id,quantity:Number(r.quantity),unit_price:String(r.unit_price),tailor_rate_override:r.tailor_rate_override ? String(r.tailor_rate_override) : null,fabric:r.fabric,style_notes:r.style_notes,pieces:r.pieces.map(p=>({id:p.id,client_key:p.client_key,unit:p.unit,measurement_id:p.measurement_id,saved_changes:p.saved_changes,values:p.values}))}));
    if(s.editId) {payload.edit_version=s.edit_version; payload.status=s.status;}
    else {payload.customer_id=s.customerId; payload.advance=s.advance;}
    Atelier.setBusy(btn,true);
    try {
      const result=s.editId ? await Atelier.api.put(ROUTES.update(s.editId),payload) : await Atelier.api.post(ROUTES.store,payload);
      s.garments.forEach(row => row.pieces.forEach(piece => {
        const sheet = customers.find(c=>c.db_id==s.customerId)?.measurements.find(m=>m.id==piece.measurement_id);
        if (!sheet || !piece.saved_changes) return;
        Object.entries(piece.saved_changes.values || {}).forEach(([field,value]) => {
          if (Object.prototype.hasOwnProperty.call(sheet,field)) sheet[field]=value;
          else { sheet.details ||= {}; sheet.details[field]=value; }
        });
        if (piece.saved_changes.unit) sheet.unit=piece.saved_changes.unit;
        sheet.updated_at=new Date().toISOString();
      }));
      upsertOrder(result.order); closeModal(); renderPage(); Atelier.refreshCounters(); toast(result.message,'success');
      newOrderState=blankOrderState(); wizardStep=1;
      if (!s.editId && result.order) setTimeout(() => window.openReceipt(result.order.db_id), 400);
    } catch(error) {Atelier.reportError(error,'Could not save the order');}
    finally {Atelier.setBusy(btn,false);}
  };

  /* ============= STYLED WIZARD RENDERER ============= */
  function renderItemEditor() {
    const s = newOrderState;
    const totals = itemTotals();
    const isEdit = !!s.editId;
    const minStep = isEdit ? 2 : 1;
    const stepLabels = ['Customer', 'Garment', 'Measurements', 'Pricing', 'Confirm'];

    /* ── Header ──────────────────────────────────────────────────────── */
    let html = `
      <div class="page flex flex-col bg-white rounded-xl shadow-sm border border-slate-200 mb-6 mx-auto w-full" style="max-width:min(1600px, 100%)">
        <div class="p-5 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-xl">
          <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">${isEdit ? 'Edit Order' : 'Create New Order'}</h1>
            <div class="text-xs text-slate-500 font-medium mt-0.5">${s.locked ? 'Limited editing mode' : `Step ${wizardStep} of 5`}</div>
          </div>
          <button class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-medium hover:bg-slate-100 transition-colors shadow-sm" onclick="currentView='list'; renderPage();"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Orders</button>
        </div>`;

    /* ── Stepper ──────────────────────────────────────────────────────── */
    if (!s.locked) {
      html += `
      <div class="p-2 bg-slate-50 border-b border-slate-200">
        <div class="flex items-center justify-between max-w-4xl mx-auto px-4">
          ${stepLabels.map((label, i) => {
            const stepNum = i + 1;
            const completed = wizardStep > stepNum || (isEdit && stepNum === 1);
            const active = wizardStep === stepNum;
            return `
            <div class="flex items-center ${i < 4 ? 'flex-1' : ''}">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold ${
                completed ? 'bg-emerald-500 text-white' :
                active    ? 'bg-indigo-600 text-white' :
                            'bg-white border border-slate-200 text-slate-400'
              }">
                ${completed ? '<i class="fa-solid fa-check"></i>' : stepNum}
              </div>
              <div class="ml-2 text-xs font-medium ${active ? 'text-slate-900' : 'text-slate-500'} hidden sm:block">${label}</div>
              ${i < 4 ? `<div class="flex-1 h-0.5 mx-2 ${completed ? 'bg-emerald-500' : 'bg-slate-200'}"></div>` : ''}
            </div>`;
          }).join('')}
        </div>
      </div>`;
    }

    /* ── Body ─────────────────────────────────────────────────────────── */
    html += `<div class="p-6 flex-1 overflow-visible">`;

    /* ---------- Locked mode ----------------------------------------- */
    if (s.locked) {
      html += `
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
          <i class="fa-solid fa-lock mr-2"></i>Garments and prices are locked after completion or credited work. Payments remain in Payments &amp; Billing.
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Order Notes</label>
          <textarea class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" rows="3" oninput="newOrderState.notes=this.value">${itemEsc(s.notes)}</textarea>
        </div>`;

    /* ---------- Step 1 — Customer ----------------------------------- */
    } else if (wizardStep === 1) {
      html += `
        <h3 class="text-sm font-semibold text-slate-900 mb-3">Select Customer</h3>
        <div class="relative mb-4">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400"></i>
          <input id="customer-search-input" autofocus
                 oninput="window.renderCustomerList(this.value); document.getElementById('clear-search-btn').classList.toggle('hidden', this.value === '')"
                 class="w-full pl-9 pr-9 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                 placeholder="Customer ka naam ya number likho...">
          <button id="clear-search-btn"
                  onclick="document.getElementById('customer-search-input').value=''; window.renderCustomerList(''); this.classList.add('hidden'); document.getElementById('customer-search-input').focus();"
                  class="hidden absolute right-2 top-1.5 text-slate-400 hover:text-slate-600 p-1"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="customer-list-container" class="max-h-60 overflow-y-auto pr-1">
          <img src="x" onerror="window.renderCustomerList(''); setTimeout(() => { const input = document.getElementById('customer-search-input'); if(input) input.focus(); }, 50);" style="display:none;" />
        </div>`;

    /* ---------- Step 2 — Garment & Fabric --------------------------- */
    } else if (wizardStep === 2) {
      html += `<div class="flex justify-between items-center mb-6">
                 <div>
                   <h3 class="text-xl font-bold text-slate-900 tracking-tight">Garment Details</h3>
                   <p class="text-sm text-slate-500 mt-1">Select the garment type, quantity, pricing, and fabric notes.</p>
                 </div>
                 ${s.garments.length > 0 ? `<button class="text-sm bg-slate-900 text-white px-5 py-2.5 rounded-xl font-semibold hover:bg-slate-800 transition-colors shadow-sm shadow-slate-900/20 flex items-center gap-2" onclick="itemAdd()"><i class="fa-solid fa-plus"></i> Add Garment</button>` : ''}
               </div>`;

      s.garments.forEach((r, i) => {
        let isExpanded = (s.activeItem === i);
        if (!isExpanded) {
           let serviceName = activeServices.find(x => x.id == r.product_service_id)?.name || 'Garment Type Not Selected';
           html += `
           <div class="border border-slate-200 bg-slate-50 hover:bg-slate-100 cursor-pointer rounded-2xl shadow-sm p-4 sm:p-5 mb-6 flex items-center justify-between transition-colors group" onclick="setItemActive(${i})">
             <div class="flex items-center gap-4">
               <div class="w-10 h-10 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-base">${i + 1}</div>
               <div>
                 <div class="font-bold text-slate-900 text-base sm:text-lg">${itemEsc(serviceName)}</div>
                 <div class="text-xs sm:text-sm text-slate-500 mt-0.5">Qty: <span class="font-bold text-slate-700">${r.quantity}</span> &bull; Subtotal: <span class="font-bold text-indigo-600">${Atelier.money(Number(r.unit_price || 0) * r.quantity)}</span></div>
               </div>
             </div>
             <div class="flex items-center gap-2 sm:gap-3">
               <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 group-hover:text-indigo-600 group-hover:border-indigo-300 transition-colors shadow-sm" title="Edit Garment"><i class="fa-solid fa-pen"></i></button>
               ${s.garments.length > 1 ? `<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 group-hover:text-red-500 group-hover:border-red-300 transition-colors shadow-sm" onclick="event.stopPropagation(); itemRemove(${i})" title="Remove Garment"><i class="fa-solid fa-trash-can"></i></button>` : ''}
             </div>
           </div>`;
           return;
        }

        html += `
        <div class="border border-indigo-200 bg-white rounded-2xl shadow-md ring-4 ring-indigo-50 p-6 mb-6 relative transition-all group">
          ${s.garments.length > 1 ? `<button class="absolute top-6 right-6 w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-colors opacity-0 group-hover:opacity-100" onclick="itemRemove(${i})" title="Remove Garment"><i class="fa-solid fa-trash-can"></i></button>` : ''}
          
          <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
            <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-sm">
              ${i + 1}
            </div>
            <h4 class="text-lg font-bold text-slate-900">Garment Details</h4>
          </div>
          
          <div class="space-y-8">
            <!-- Garment Type Grid -->
            <div>
              <label class="block text-sm font-bold text-slate-700 mb-3">Garment Type <span class="text-red-500">*</span></label>
              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                ${tailorCategories.map(cat => {
                  let html = '';
                  let isDummy = cat.rates.length === 1 && cat.rates[0].service && (cat.name.toLowerCase() === cat.rates[0].service.name.toLowerCase() || cat.name === 'Default' || cat.name === 'General');
                  
                  if (!isDummy && cat.rates.length > 0) {
                    html += `
                      <div class="col-span-full mt-2 mb-1">
                        <h5 class="text-sm font-bold text-slate-700 border-b border-slate-100 pb-2">${itemEsc(cat.name)}</h5>
                      </div>
                    `;
                  }
                  
                  html += cat.rates.map(rate => {
                    let p = rate.service;
                    if (!p) return '';
                    let isSelected = p.id == r.product_service_id && Number(r.unit_price) == Number(rate.price);
                    return `<button class="w-full px-3 py-4 border flex flex-col items-center justify-center gap-1.5 rounded-xl text-center transition-all ${isSelected ? 'border-indigo-600 bg-indigo-50 text-indigo-700 shadow-sm ring-2 ring-indigo-600/20' : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:bg-slate-50 hover:shadow-sm'}" onclick="itemChooseRate(${i}, ${rate.id})">
                        <i class="fa-solid ${p.name.toLowerCase().includes('suit') ? 'fa-vest' : p.name.toLowerCase().includes('shirt') ? 'fa-shirt' : 'fa-vest-patches'} text-2xl mb-1 ${isSelected ? 'text-indigo-600' : 'text-slate-400'} transition-colors"></i> 
                        <span class="font-bold text-sm leading-tight text-slate-800">${itemEsc(p.name)}</span>
                        <span class="${isSelected ? 'text-indigo-600' : 'text-slate-500'} text-xs font-semibold">${Atelier.money(rate.price)}</span>
                    </button>`;
                  }).join('');
                  
                  return html;
                }).join('')}
                ${!rowProduct(r) && r.product_service_id ? `<button class="w-full px-3 py-4 border flex flex-col items-center justify-center gap-1.5 rounded-xl text-center border-indigo-600 bg-indigo-50 text-indigo-700 shadow-sm" disabled><i class="fa-solid fa-vest-patches text-2xl mb-1 text-indigo-600"></i> <span class="font-bold text-sm leading-tight">${itemEsc(r.name)}</span><span class="text-[10px] font-normal opacity-75">(historical)</span></button>` : ''}
              </div>
            </div>
            
            <!-- Bottom Section: Grid for details -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5 mt-8 border-t border-slate-100 pt-6">
              
              <!-- Quantity -->
              <div class="md:col-span-3">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Quantity</label>
                <div class="flex items-center w-full">
                  <button class="w-10 h-10 flex justify-center items-center bg-slate-100 border border-slate-200 rounded-l-lg text-slate-600 hover:bg-slate-200 transition-colors" onclick="itemQuantity(${i}, ${r.quantity - 1})"><i class="fa-solid fa-minus text-[10px]"></i></button>
                  <input type="number" min="1" max="20" step="1"
                         class="w-full h-10 px-2 text-center bg-white border-y border-slate-200 text-sm focus:outline-none focus:ring-0 text-slate-900 font-bold"
                         value="${r.quantity}" onchange="itemQuantity(${i}, this.value)" readonly>
                  <button class="w-10 h-10 flex justify-center items-center bg-slate-100 border border-slate-200 rounded-r-lg text-slate-600 hover:bg-slate-200 transition-colors" onclick="itemQuantity(${i}, ${r.quantity + 1})"><i class="fa-solid fa-plus text-[10px]"></i></button>
                </div>
              </div>
              
              <!-- Unit Price -->
              <div class="md:col-span-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Unit Price (${Atelier.currency})</label>
                <input type="number" min="0" step="0.01"
                       class="w-full h-10 px-3 bg-white border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 font-medium"
                       value="${itemEsc(r.unit_price)}" onchange="itemField(${i}, 'unit_price', this.value)">
              </div>
              
              <!-- Subtotal -->
              <div class="md:col-span-5 flex flex-col justify-end">
                <label class="block text-sm font-semibold text-slate-700 mb-2 md:hidden">Subtotal</label>
                <div class="w-full h-10 bg-indigo-50 border border-indigo-100 rounded-lg flex items-center justify-between px-4">
                  <span class="text-sm font-semibold text-indigo-900/60">Subtotal</span>
                  <span class="text-base font-bold text-indigo-700">${Atelier.money(Number(r.unit_price || 0) * r.quantity)}</span>
                </div>
              </div>
              
              <!-- Fabric Details -->
              <div class="md:col-span-5">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Fabric Details</label>
                <input class="w-full h-10 px-3 bg-white border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 placeholder:text-slate-400"
                       value="${itemEsc(r.fabric)}" oninput="itemField(${i}, 'fabric', this.value)" placeholder="e.g. Italian Wool, Navy Blue">
              </div>
              
              <!-- Style Notes -->
              <div class="md:col-span-7">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Style Notes / Instructions</label>
                <input class="w-full h-10 px-3 bg-white border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 placeholder:text-slate-400"
                       value="${itemEsc(r.style_notes)}" oninput="itemField(${i}, 'style_notes', this.value)" placeholder="e.g. Peak lapel, side vents...">
              </div>
              
            </div>
          </div>
          ${rowProfile(r).key === 'generic' ? '<p class="text-xs text-amber-700 mt-6 font-bold flex items-center gap-2 bg-amber-50 px-4 py-3 rounded-xl border border-amber-200"><i class="fa-solid fa-triangle-exclamation text-amber-500 text-base"></i> Generic measurements apply. Choose a specific garment type above for tailored options.</p>' : ''}
        </div>`;
      });

      html += `
        <button class="w-full p-4 border-2 border-dashed border-slate-300 rounded-2xl text-sm font-bold text-slate-500 hover:border-indigo-500 hover:text-indigo-600 hover:bg-indigo-50 transition-colors flex items-center justify-center gap-2" onclick="itemAdd()">
          <i class="fa-solid fa-plus text-lg"></i> Add Another Garment
        </button>`;

    /* ---------- Step 3 — Measurements ------------------------------- */
    } else if (wizardStep === 3) {
      const row = s.garments[0];
      const piece = row.pieces[0]; // Measurements apply to all pieces in the garment
      const profile = rowProfile(row);
      const saved = compatibleSavedMeasurements(row);
      const sourceSaved = saved.find(m => m.id == (piece.saved_measurement_id || piece.measurement_id));
      const selectedSaved = piece.measurement_mode === 'new' ? null : sourceSaved;
      const availableSaved = sourceSaved || saved[0];

      html += `<h3 class="text-xl font-bold text-slate-900 tracking-tight mb-6">Body Measurements</h3>`;



      if (!profile.fields.length) {
        html += `<div class="text-center py-10 bg-slate-50 rounded-2xl border border-slate-200 text-sm text-slate-500"><i class="fa-solid fa-check-circle text-emerald-500 text-3xl mb-3 block"></i><span class="font-bold text-base text-slate-700 block mb-1">No measurements required</span>Generic item selected.</div>`;
      } else {
        /* Saved / Unit toolbar */
        html += `
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 p-4 bg-slate-50 rounded-2xl border border-slate-200">
          <div class="flex gap-2 flex-wrap">
            <button class="px-4 py-2.5 text-sm font-bold transition-colors ${!selectedSaved ? 'bg-indigo-50 border border-indigo-500 text-indigo-700 shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:border-indigo-400'} rounded-xl" onclick="itemSaved('')">
              <i class="fa-solid fa-pen-ruler mr-1.5"></i> New Measurement
            </button>
            ${availableSaved ? `
              <button class="px-4 py-2.5 text-sm font-bold transition-colors ${selectedSaved ? 'bg-indigo-50 border border-indigo-500 text-indigo-700 shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:border-indigo-400'} rounded-xl" onclick="itemSaved('${availableSaved.id}')">
                <i class="fa-solid fa-folder-open mr-1.5"></i> Use Saved Measurement
              </button>
            ` : ''}
          </div>
          
          <div class="flex items-center gap-3 md:pl-4 md:border-l border-slate-200 shrink-0">
            <label class="text-sm font-bold text-slate-700">Unit:</label>
            <div class="flex items-center bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
              <button class="px-4 py-2 text-sm font-bold transition-colors ${piece.unit === 'in' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50'}" onclick="itemUnit('in')">in</button>
              <button class="px-4 py-2 text-sm font-bold border-l border-slate-200 transition-colors ${piece.unit === 'cm' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50'}" onclick="itemUnit('cm')">cm</button>
            </div>
          </div>
        </div>`;

        html += `<p class="text-xs text-slate-500 mb-5">${sourceSaved
          ? 'Saved measurements loaded. Changed fields update this saved measurement when you save the order.'
          : availableSaved ? 'Use the latest saved measurements for ' + itemEsc(rowName(row)) + ', or enter new measurements.'
          : 'No saved measurements for this customer and garment. Enter new measurements below.'}</p>`;

        /* Measurement fields grid */
        html += `
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-5 mb-5">
          ${profile.fields.map(f => {
            const isReq = profile.required.includes(f);
            const label = profile.labels[f] || f;
            const val = piece.values[f] ?? '';
            const borderClass = isReq ? (val === '' ? 'border-amber-300 bg-amber-50 focus:bg-white focus:border-indigo-500 focus:ring-indigo-500' : 'border-slate-200 focus:border-indigo-500 focus:ring-indigo-500') : 'border-slate-200 focus:border-indigo-500 focus:ring-indigo-500';
            const labelClass = isReq ? 'text-slate-900 font-bold' : 'text-slate-600 font-semibold';
            return `
            <div class="col-span-1">
              <label class="block text-sm ${labelClass} mb-2">${itemEsc(label)}${isReq ? ' <span class="text-red-500">*</span>' : ''}</label>
              <input type="number" step="any" min="0" max="999"
                     class="w-full h-11 px-3 text-base font-medium bg-white border ${borderClass} rounded-xl focus:outline-none focus:ring-2 transition-all text-slate-900"
                     value="${itemEsc(val)}" oninput="itemMeasure('${f}', this.value); this.classList.remove('border-amber-300', 'bg-amber-50'); this.classList.add('border-slate-200', 'focus:border-indigo-500')">
            </div>`;
          }).join('')}
        </div>
        
        <div class="mt-6 mb-2">
          <label class="block text-sm text-slate-900 font-bold mb-2">Special Instructions / Notes</label>
          <textarea id="order-meas-notes" class="w-full px-3 py-2 text-base font-medium bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:border-indigo-500 focus:ring-indigo-500 transition-all text-slate-900"
                    rows="3"
                    placeholder="Customer ki special stitching requirements, fitting instructions, design details, loose/tight preference, collar/cuff instructions, etc."
                    oninput="itemMeasure('notes', this.value);">${itemEsc(piece.values.notes ?? '')}</textarea>
          ${ @json(view('components.measurement-note-quick-actions', ['targetId' => 'order-meas-notes'])->render()) }
        </div>`;
      }

    /* ---------- Step 4 — Pricing & Dates ---------------------------- */
    } else if (wizardStep === 4) {
      html += `<h3 class="text-sm font-semibold text-slate-900 mb-3">Pricing &amp; Dates</h3>
      <div class="grid grid-cols-2 gap-4">`;

      /* Per-garment unit price editors */
      s.garments.forEach((r, i) => {
        html += `
        <div class="col-span-2 flex items-center justify-between bg-slate-50 p-3 rounded-lg flex-wrap gap-2">
          <div>
            <span class="text-sm font-medium text-slate-700">${itemEsc(rowName(r))} × ${r.quantity}</span>
            <span class="text-xs text-slate-400 ml-2">${Atelier.money(Number(r.unit_price || 0) * r.quantity)}</span>
          </div>
          <div class="flex items-center gap-4 flex-wrap">
            <div class="flex items-center gap-2">
              <label class="text-xs text-slate-500 whitespace-nowrap">Tailor Rate Override (Optional):</label>
              <input type="number" step="0.01" min="0" placeholder="None"
                     class="w-24 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 font-medium"
                     value="${itemEsc(r.tailor_rate_override)}" onchange="itemField(${i}, 'tailor_rate_override', this.value); itemRefresh()">
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs text-slate-500 whitespace-nowrap">Unit Price:</label>
              <input type="number" step="0.01" min="0"
                     class="w-28 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 font-medium"
                     value="${itemEsc(r.unit_price)}" onchange="itemField(${i}, 'unit_price', this.value); itemRefresh()">
            </div>
          </div>
        </div>`;
      });

      /* Balance banner */
      html += `
        <div class="col-span-2 p-3 bg-emerald-50 rounded-lg">
          <div class="flex justify-between items-center mb-1">
            <span class="text-xs text-emerald-600">Subtotal: ${Atelier.money(totals.subtotal)}</span>
            <span class="text-xs text-emerald-600">Total (incl. charges): ${Atelier.money(totals.total)}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm font-semibold text-emerald-700">Balance Due:</span>
            <span class="text-lg font-bold text-emerald-700">${Atelier.money(totals.balance)}</span>
          </div>
        </div>`;

      /* Advance / Paid */
      html += `
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">${isEdit ? 'Paid (read-only)' : 'Advance Paid'} (${Atelier.currency}) *</label>
          <input type="number" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                 value="${isEdit ? s.paid : s.advance}" ${isEdit ? 'readonly' : ''}
                 oninput="this.classList.remove('border-red-500')"
                 onchange="newOrderState.advance=this.value; itemRefresh()">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Delivery Date *</label>
          <input type="date" ${isEdit ? '' : `min="${todayISO()}"`}
                 class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                 value="${itemEsc(s.date)}" oninput="newOrderState.date=this.value; this.classList.remove('border-red-500')">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Delivery Time *</label>
          <input type="time" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                 value="${itemEsc(s.slot)}" oninput="newOrderState.slot=this.value; this.classList.remove('border-red-500')">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Priority *</label>
          <select class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                  onchange="newOrderState.priority=this.value; this.classList.remove('border-red-500')">
            ${['Normal', 'High', 'Express'].map(p => `<option ${s.priority === p ? 'selected' : ''}>${p}</option>`).join('')}
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tailor</label>
          <select class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                  onchange="newOrderState.tailorId=this.value">
            <option value="">Unassigned</option>
            ${tailors.map(t => `<option value="${t.id}" ${s.tailorId == t.id ? 'selected' : ''}>${itemEsc(t.name)}</option>`).join('')}
          </select>
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Order Notes</label>
          <textarea class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                    rows="2" placeholder="Order notes..." oninput="newOrderState.notes=this.value">${itemEsc(s.notes)}</textarea>
        </div>
      </div>`;

    /* ---------- Step 5 — Review & Confirm --------------------------- */
    } else {
      html += `
      <h3 class="text-sm font-semibold text-slate-900 mb-3">Review &amp; Confirm</h3>
      <div class="bg-slate-50 rounded-xl p-5">
        <div class="flex justify-between items-start mb-4">
          <div>
            <div class="text-xs text-slate-500">${isEdit ? 'Editing Order' : 'Order ID'}</div>
            <div class="text-lg font-bold text-slate-900">${isEdit ? itemEsc(s.customerName) : 'Auto-Generated'}</div>
          </div>
          <span class="badge badge-pending">${isEdit ? itemEsc(s.status) : 'Received'}</span>
        </div>

        ${!isEdit ? `
        <div class="grid grid-cols-2 gap-4 text-sm mb-4">
          <div><div class="text-xs text-slate-500">Customer</div><div class="font-semibold text-slate-900">${itemEsc(s.customerName) || '-'}</div></div>
          <div><div class="text-xs text-slate-500">Phone</div><div class="font-semibold text-slate-900">${itemEsc(s.customerPhone) || '-'}</div></div>
        </div>` : ''}

        ${s.garments.map(r => `
        <div class="border border-slate-200 rounded-lg p-3 mb-3 bg-white">
          <div class="flex justify-between items-center">
            <div class="font-semibold text-slate-900">${itemEsc(rowName(r))} × ${r.quantity}</div>
            <span class="text-sm font-semibold text-slate-900">${Atelier.money(Number(r.unit_price) * r.quantity)}</span>
          </div>
          <div class="text-xs text-slate-500 mt-1">
            ${r.fabric ? 'Fabric: ' + itemEsc(r.fabric) : 'No fabric specified'}
            ${r.style_notes ? ' · ' + itemEsc(r.style_notes) : ''}
          </div>
          <div class="text-xs text-slate-400 mt-1">
            ${r.pieces.map((p, j) => `Piece ${j + 1}: ${rowComplete(r, p) ? '<span class="text-emerald-600">✓ complete</span>' : '<span class="text-amber-500">○ historical</span>'} (${itemEsc(p.unit)})`).join(' · ')}
          </div>
        </div>`).join('')}

        <div class="grid grid-cols-2 gap-4 text-sm mt-4">
          <div><div class="text-xs text-slate-500">Due Date</div><div class="font-semibold text-slate-900">${s.date ? itemEsc(s.date) + (s.slot ? ', ' + itemEsc(s.slot) : '') : '-'}</div></div>
          <div><div class="text-xs text-slate-500">Priority</div><div class="font-semibold text-slate-900">${itemEsc(s.priority)}</div></div>
          <div class="col-span-2"><div class="text-xs text-slate-500">Tailor</div><div class="font-semibold text-slate-900">${itemEsc((tailors.find(t => t.id == s.tailorId) || {}).name || 'Unassigned')}</div></div>
          <div><div class="text-xs text-slate-500">Total Amount</div><div class="font-semibold text-slate-900">${Atelier.money(totals.total)}</div></div>
          <div><div class="text-xs text-slate-500">${isEdit ? 'Paid' : 'Advance'}</div><div class="font-semibold text-slate-900">${Atelier.money(isEdit ? s.paid : s.advance)}</div></div>
          <div class="col-span-2"><div class="text-xs text-slate-500">Balance Due</div><div class="font-bold text-red-500 text-lg">${Atelier.money(totals.balance)}</div></div>
        </div>
        ${s.notes ? `<div class="mt-3 text-xs text-slate-500"><span class="font-semibold">Notes:</span> ${itemEsc(s.notes)}</div>` : ''}
      </div>`;
    }

    html += `</div>`;

    /* ── Footer ───────────────────────────────────────────────────────── */
    html += `
      <div class="p-5 bg-slate-50 border-t border-slate-200 flex justify-between rounded-b-xl">
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors shadow-sm" onclick="currentView='list'; renderPage();">Cancel</button>
        <div class="flex gap-2">
          ${wizardStep > minStep && !s.locked ? `<button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors shadow-sm" onclick="itemGo(-1)">Back</button>` : ''}
          ${wizardStep < 5 && !s.locked
            ? `<button class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-500/30" onclick="itemGo(1)">Next Step <i class="fa-solid fa-arrow-right text-xs ml-1.5"></i></button>`
            : `<button class="bg-emerald-500 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600 transition-colors shadow-sm shadow-emerald-500/30" onclick="itemSubmit(this)"><i class="fa-solid fa-check text-xs mr-1.5"></i> ${isEdit ? 'Save Changes' : 'Confirm & Create Order'}</button>`
          }
        </div>
      </div>
    </div>`;

    return html;
  }

  pages.orderEditor = renderItemEditor;
  
  window.editOrder = d => {
    newOrderState={...blankOrderState(),editId:d.db_id,edit_version:d.edit_version,locked:d.items_locked,status:d.status,customerId:d.customer_id,customerName:d.customer,garments:structuredClone(d.garments),paid:d.paid,date:d.schedule?.deliveryDate || '',slot:d.schedule?.deliveryTime || '',priority:d.priority,tailorId:d.tailor_id,notes:d.notes};
    newOrderState.garments.forEach(r=>r.originalQuantity=r.quantity);
    newOrderState.originalGarments=structuredClone(d.garments);
    newOrderState.originalTotal=d.amount;
    wizardStep=2;
    if(!newOrderState.garments.length) return toast('This historical order needs the verified backfill before garment editing.','error');
    currentView = 'editor';
    renderPage();
  };
