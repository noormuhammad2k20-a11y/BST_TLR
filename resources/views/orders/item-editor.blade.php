  // Create and edit use one state tree; DOM changes write directly into it.
  var itemProfiles = @json(\App\Services\MeasurementProfiles::all());
  var itemPricing = @json(\App\Services\PricingService::breakdown(0));
  var itemKey = () => 'piece-' + crypto.randomUUID();
  var blankPiece = () => ({client_key:itemKey(), unit:'in', values:{}});
  var blankGarment = () => ({client_key:itemKey(), product_service_id:'', quantity:1, unit_price:'0.00', fabric:'', style_notes:'', pieces:[blankPiece()]});
  blankOrderState = () => ({customerId:null, customerName:'', customerPhone:'', garments:[blankGarment()], advance:0, date:'', slot:'', priority:'Normal', tailorId:'', notes:'', activeItem:0, activePiece:0});
  newOrderState = blankOrderState();
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
  function itemRefresh() { openModal('add-order-wizard'); }
  window.itemField = (i,key,value) => { newOrderState.garments[i][key]=value; };
  window.itemChoose = (i,id) => {
    const row = newOrderState.garments[i];
    if (row.pieces.some(p => Object.values(p.values).some(v => v !== '' && v !== null)) && !confirm('Changing garment clears incompatible measurements. Continue?')) { itemRefresh(); return; }
    const product = activeServices.find(s => s.id == id);
    row.product_service_id = Number(id); row.name = product?.name; row.unit_price = product?.price || '0.00';
    row.pieces.forEach(p => { p.values={}; delete p.measurement_id; p.profile=product?.profile; });
    itemRefresh();
  };
  window.itemQuantity = (i,value) => {
    const row = newOrderState.garments[i], qty = Math.max(1,Math.min(Math.max(20,row.originalQuantity||0),parseInt(value)||1));
    if (qty < row.pieces.length && row.pieces.slice(qty).some(p => p.id || Object.values(p.values).some(v => v !== '' && v !== null)) && !confirm('Remove the trailing pieces? Their saved history will be retained.')) { itemRefresh(); return; }
    while(row.pieces.length<qty) row.pieces.push(blankPiece());
    row.pieces.length=qty; row.quantity=qty; newOrderState.activePiece=0; itemRefresh();
  };
  window.itemRemove = i => {
    if (newOrderState.garments.length===1) return toast('Keep at least one garment.','error');
    if (!confirm('Remove this garment and its pieces? Saved history will be retained.')) return;
    newOrderState.garments.splice(i,1); newOrderState.activeItem=0; newOrderState.activePiece=0; itemRefresh();
  };
  window.itemAdd = () => { if(newOrderState.garments.length<50) newOrderState.garments.push(blankGarment()); itemRefresh(); };
  window.itemTab = (i,j) => { newOrderState.activeItem=i; newOrderState.activePiece=j; itemRefresh(); };
  window.itemMeasure = (field,value) => { const p=newOrderState.garments[newOrderState.activeItem].pieces[newOrderState.activePiece]; p.values[field]=value; delete p.measurement_id; };
  window.itemUnit = value => { const piece=newOrderState.garments[newOrderState.activeItem].pieces[newOrderState.activePiece]; piece.unit=value; delete piece.measurement_id; };
  function inferSavedProfile(sheet) {
    if(sheet.profile_key) return sheet.profile_key;
    const name=(sheet.garment_type||'').toLowerCase();
    for(const [word,key] of Object.entries({shalwar:'shalwar_kameez',sherwani:'sherwani',waistcoat:'waistcoat',kurta:'kurta_pajama',trouser:'trouser',alteration:'alteration'})) if(name.includes(word)) return key;
    return 'generic';
  }
  window.itemSaved = id => {
    const row=newOrderState.garments[newOrderState.activeItem], piece=row.pieces[newOrderState.activePiece];
    if(!id) {piece.values={}; delete piece.measurement_id; itemRefresh(); return;}
    const sheet=customers.find(c=>c.db_id==newOrderState.customerId)?.measurements.find(m=>m.id==id);
    if(!sheet || inferSavedProfile(sheet)!==rowProfile(row).key) return;
    piece.values={}; rowProfile(row).fields.forEach(f=>piece.values[f]=sheet[f]??sheet.details?.[f]??'');
    piece.unit=sheet.unit; piece.measurement_id=sheet.id; itemRefresh();
  };
  window.itemCopy = value => {
    if(!value) return;
    const [i,j]=value.split(':').map(Number), source=newOrderState.garments[i].pieces[j];
    const target=newOrderState.garments[newOrderState.activeItem].pieces[newOrderState.activePiece];
    target.values=structuredClone(source.values); target.unit=source.unit; delete target.measurement_id; itemRefresh();
  };
  window.itemGo = direction => {
    if(direction>0) {
      if(wizardStep===1 && !newOrderState.customerId) return toast('Select a customer.','error');
      if(wizardStep===2 && newOrderState.garments.some(r=>!r.product_service_id&&!r.id)) return toast('Choose a garment for every row.','error');
      if(wizardStep===3 && !newOrderState.editId && newOrderState.garments.some(r=>r.pieces.some(p=>!rowComplete(r,p)))) return toast('Complete the required measurements for every piece.','error');
      if(wizardStep===4 && (!newOrderState.date || itemTotals().balance<0)) return toast('Check the delivery date and payment amount.','error');
    }
    wizardStep=Math.max(newOrderState.editId?2:1,Math.min(5,wizardStep+direction)); itemRefresh();
  };
  window.itemSubmit = async btn => {
    const s=newOrderState, payload={priority:s.priority, delivery_date:s.date, time_slot:s.slot, staff_id:s.tailorId||null, notes:s.notes};
    if(!s.locked) payload.garments=s.garments.map(r=>({id:r.id,client_key:r.client_key,product_service_id:r.product_service_id,quantity:Number(r.quantity),unit_price:String(r.unit_price),fabric:r.fabric,style_notes:r.style_notes,pieces:r.pieces.map(p=>({id:p.id,client_key:p.client_key,unit:p.unit,measurement_id:p.measurement_id,values:p.values}))}));
    if(s.editId) {payload.edit_version=s.edit_version; payload.status=s.status;}
    else {payload.customer_id=s.customerId; payload.advance=s.advance;}
    Atelier.setBusy(btn,true);
    try {
      const result=s.editId ? await Atelier.api.put(ROUTES.update(s.editId),payload) : await Atelier.api.post(ROUTES.store,payload);
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
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div>
          <div class="text-lg font-bold text-slate-900 tracking-tight">${isEdit ? 'Edit Order' : 'Create New Order'}</div>
          <div class="text-xs text-slate-500">${s.locked ? 'Limited editing' : `Step ${wizardStep} of 5`}</div>
        </div>
        <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>`;

    /* ── Stepper ──────────────────────────────────────────────────────── */
    if (!s.locked) {
      html += `
      <div class="p-2 bg-slate-50 border-b border-slate-200">
        <div class="flex items-center justify-between max-w-2xl mx-auto px-4">
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
    html += `<div class="p-6 overflow-y-auto" style="max-height:60vh">`;

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
      html += `<h3 class="text-sm font-semibold text-slate-900 mb-3">Garment &amp; Fabric Details</h3>`;

      s.garments.forEach((r, i) => {
        html += `
        <div class="border border-slate-200 rounded-xl p-4 mb-4">
          <div class="flex justify-between items-center mb-3">
            <div class="text-sm font-bold text-slate-900">Garment ${i + 1}</div>
            ${s.garments.length > 1 ? `<button class="text-xs text-red-500 hover:text-red-700 font-medium" onclick="itemRemove(${i})"><i class="fa-solid fa-trash text-[10px] mr-1"></i>Remove</button>` : ''}
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Garment Type *</label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                ${activeServices.map(p => {
                  let icon = p.name.toLowerCase().includes('suit') ? 'fa-vest' : p.name.toLowerCase().includes('shirt') ? 'fa-shirt' : 'fa-vest-patches';
                  let isSelected = p.id == r.product_service_id;
                  return `<button class="p-3 border-2 ${isSelected ? 'border-indigo-600 bg-indigo-50 text-indigo-600' : 'border-slate-200 text-slate-500 hover:border-indigo-600'} rounded-lg text-xs font-medium flex flex-col items-center gap-1 transition-colors" onclick="itemChoose(${i}, '${p.id}')"><i class="fa-solid ${icon} text-lg"></i> <span class="text-center">${itemEsc(p.name)}</span></button>`;
                }).join('')}
                ${!rowProduct(r) && r.product_service_id ? `<button class="p-3 border-2 border-indigo-600 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-medium flex flex-col items-center gap-1" disabled><i class="fa-solid fa-vest-patches text-lg"></i> <span class="text-center">${itemEsc(r.name)} (historical)</span></button>` : ''}
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Quantity (kitne kapre) *</label>
              <input type="number" min="1" max="${Math.max(20, r.originalQuantity || 0)}" step="1"
                     class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                     value="${r.quantity}" onchange="itemQuantity(${i}, this.value)">
              <p class="mt-1 text-[11px] text-slate-400">Har piece ka apna naap agle step mein.</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Unit Price (${Atelier.currency})</label>
              <input type="number" min="0" step="0.01"
                     class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                     value="${itemEsc(r.unit_price)}" onchange="itemField(${i}, 'unit_price', this.value)">
              <p class="mt-1 text-[11px] text-slate-400">${Atelier.money(Number(r.unit_price || 0))} × ${r.quantity} = ${Atelier.money(Number(r.unit_price || 0) * r.quantity)}</p>
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Fabric Selection</label>
              <input class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                     value="${itemEsc(r.fabric)}" oninput="itemField(${i}, 'fabric', this.value)" placeholder="e.g. Italian Wool">
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">Style Notes</label>
              <textarea class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                        placeholder="e.g. Peak lapel, side vents..." oninput="itemField(${i}, 'style_notes', this.value)">${itemEsc(r.style_notes)}</textarea>
            </div>
          </div>
          ${rowProfile(r).key === 'generic' ? '<p class="text-xs text-amber-700 mt-2"><i class="fa-solid fa-info-circle mr-1"></i>Generic measurements apply. Choose a specific garment type above for tailored measurement fields.</p>' : ''}
        </div>`;
      });

      html += `
        <button class="w-full p-3 border-2 border-dashed border-slate-300 rounded-lg text-sm font-medium text-slate-500 hover:border-indigo-500 hover:text-indigo-600 transition-colors flex items-center justify-center gap-2" onclick="itemAdd()">
          <i class="fa-solid fa-plus"></i> Add Another Garment
        </button>`;

    /* ---------- Step 3 — Measurements ------------------------------- */
    } else if (wizardStep === 3) {
      const row = s.garments[s.activeItem] || s.garments[0];
      const piece = row.pieces[s.activePiece] || row.pieces[0];
      const profile = rowProfile(row);
      const saved = (customers.find(c => c.db_id == s.customerId)?.measurements || []).filter(m => inferSavedProfile(m) === profile.key);

      html += `<h3 class="text-sm font-semibold text-slate-900 mb-3">Body Measurements</h3>`;

      /* Piece / garment tabs */
      const allTabs = [];
      s.garments.forEach((r, i) => r.pieces.forEach((p, j) => allTabs.push({gi:i, pi:j, row:r, piece:p})));

      if (allTabs.length > 1) {
        html += `
        <div class="flex items-center gap-1.5 mb-3 overflow-x-auto pb-1">
          ${allTabs.map(t => {
            const on = t.gi === s.activeItem && t.pi === s.activePiece;
            const done = rowComplete(t.row, t.piece);
            return `<button onclick="itemTab(${t.gi}, ${t.pi})" class="shrink-0 px-3 py-1.5 rounded-md text-xs font-semibold border transition-colors ${on ? 'bg-indigo-600 border-indigo-600 text-white' : done ? 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:border-emerald-400' : 'bg-white border-slate-200 text-slate-500 hover:border-indigo-400'}">
              ${done && !on ? '<i class="fa-solid fa-check mr-1"></i>' : ''}${itemEsc(rowName(t.row))} · Piece ${t.pi + 1}
            </button>`;
          }).join('')}
        </div>`;
      }

      if (!profile.fields.length) {
        html += `<div class="text-center py-8 text-sm text-slate-500"><i class="fa-solid fa-check-circle text-emerald-500 text-2xl mb-2 block"></i>No measurements required for this item.</div>`;
      } else {
        /* Saved / copy / unit toolbar */
        html += `
        <div class="flex items-center justify-between gap-2 mb-4 p-2 bg-slate-50 rounded-lg flex-wrap">
          <div class="flex gap-2 flex-wrap">
            <div>
              <label class="text-xs font-semibold text-slate-500 block mb-1">Saved Measurements</label>
              <select class="text-xs border border-slate-200 rounded p-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 font-medium" onchange="itemSaved(this.value)">
                <option value="">Enter new</option>
                ${saved.map(m => `<option value="${m.id}" ${m.id == piece.measurement_id ? 'selected' : ''}>${itemEsc(m.garment_type)} #${m.id}</option>`).join('')}
              </select>
            </div>
            ${allTabs.length > 1 ? `
            <div>
              <label class="text-xs font-semibold text-slate-500 block mb-1">Copy from</label>
              <select class="text-xs border border-slate-200 rounded p-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 font-medium" onchange="itemCopy(this.value)">
                <option value="">Choose compatible piece</option>
                ${s.garments.map((r2, i2) => rowProfile(r2).key === profile.key ? r2.pieces.map((p2, j2) => i2 === s.activeItem && j2 === s.activePiece ? '' : `<option value="${i2}:${j2}">${itemEsc(rowName(r2))} · Piece ${j2 + 1}</option>`).join('') : '').join('')}
              </select>
            </div>` : ''}
          </div>
          <div class="flex items-center gap-2 pr-2">
            <label class="text-xs font-semibold text-slate-500">Unit:</label>
            <select class="text-xs border border-slate-200 rounded p-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 font-medium" onchange="itemUnit(this.value)">
              <option value="in" ${piece.unit === 'in' ? 'selected' : ''}>in</option>
              <option value="cm" ${piece.unit === 'cm' ? 'selected' : ''}>cm</option>
            </select>
          </div>
        </div>`;

        /* Measurement fields — grid matching original 2–5 column layout */
        html += `
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
          ${profile.fields.map(f => {
            const isReq = profile.required.includes(f);
            const label = profile.labels[f] || f;
            const val = piece.values[f] ?? '';
            const reqHtml = isReq ? ' <span class="text-red-500">*</span>' : '';
            const borderClass = isReq ? 'border-red-200 focus:ring-red-500' : 'border-slate-200 focus:ring-indigo-500';
            const labelClass = isReq ? 'text-slate-700 font-bold' : 'text-slate-500 font-semibold';
            return `
            <div class="col-span-1">
              <label class="block text-xs ${labelClass} mb-1.5">${itemEsc(label)}${reqHtml}</label>
              <input type="number" step="any" min="0" max="999"
                     class="w-full px-3 py-2 bg-slate-50 border ${borderClass} rounded-lg text-sm focus:outline-none focus:ring-2 text-slate-900"
                     value="${itemEsc(val)}" oninput="itemMeasure('${f}', this.value); this.classList.remove('border-red-500')">
            </div>`;
          }).join('')}
        </div>`;
      }

    /* ---------- Step 4 — Pricing & Dates ---------------------------- */
    } else if (wizardStep === 4) {
      html += `<h3 class="text-sm font-semibold text-slate-900 mb-3">Pricing &amp; Dates</h3>
      <div class="grid grid-cols-2 gap-4">`;

      /* Per-garment unit price editors */
      s.garments.forEach((r, i) => {
        html += `
        <div class="col-span-2 flex items-center justify-between bg-slate-50 p-3 rounded-lg">
          <div>
            <span class="text-sm font-medium text-slate-700">${itemEsc(rowName(r))} × ${r.quantity}</span>
            <span class="text-xs text-slate-400 ml-2">${Atelier.money(Number(r.unit_price || 0) * r.quantity)}</span>
          </div>
          <div class="flex items-center gap-2">
            <label class="text-xs text-slate-500 whitespace-nowrap">Unit Price:</label>
            <input type="number" step="0.01" min="0"
                   class="w-28 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 font-medium"
                   value="${itemEsc(r.unit_price)}" onchange="itemField(${i}, 'unit_price', this.value); itemRefresh()">
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
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Time Slot *</label>
          <select class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                  onchange="newOrderState.slot=this.value; this.classList.remove('border-red-500')">
            <option value="">Select Slot</option>
            ${s.slot && !timeSlots.includes(s.slot) ? `<option value="${itemEsc(s.slot)}" selected>${itemEsc(s.slot)}</option>` : ''}
            ${timeSlots.map(ts => `<option value="${itemEsc(ts)}" ${s.slot === ts ? 'selected' : ''}>${itemEsc(ts)}</option>`).join('')}
          </select>
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
          <span class="badge badge-pending">${isEdit ? itemEsc(s.status) : 'Pending'}</span>
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
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-between">
        <button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="closeModal()">Cancel</button>
        <div class="flex gap-2">
          ${wizardStep > minStep && !s.locked ? `<button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="itemGo(-1)">Back</button>` : ''}
          ${wizardStep < 5 && !s.locked
            ? `<button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700" onclick="itemGo(1)">Next <i class="fa-solid fa-arrow-right text-xs ml-1"></i></button>`
            : `<button class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600" onclick="itemSubmit(this)">Confirm &amp; ${isEdit ? 'Save' : 'Create'} <i class="fa-solid fa-check text-xs ml-1"></i></button>`
          }
        </div>
      </div>`;

    return html;
  }

  window.modals['add-order-wizard']=renderItemEditor;
  window.modals['edit-order']=d=>{
    newOrderState={...blankOrderState(),editId:d.db_id,edit_version:d.edit_version,locked:d.items_locked,status:d.status,customerId:d.customer_id,customerName:d.customer,garments:structuredClone(d.garments),paid:d.paid,date:new Date(d.dueDate).toISOString().slice(0,10),slot:d.slot,priority:d.priority,tailorId:d.tailor_id,notes:d.notes};
    newOrderState.garments.forEach(r=>r.originalQuantity=r.quantity);
    newOrderState.originalGarments=structuredClone(d.garments);
    newOrderState.originalTotal=d.amount;
    wizardStep=2;
    if(!newOrderState.garments.length) return '<div class="p-6">This historical order needs the verified backfill before garment editing.</div>';
    return renderItemEditor();
  };
