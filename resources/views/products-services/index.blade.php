@extends('layouts.app')
@section('title', 'Products & Services')
@section('spaPage', 'products-services')

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Products & Services</h1>
    <p class="text-sm text-slate-500 mt-0.5">Tailoring services, packages, and add-ons</p>
  </div>
  <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="openModal('add-product')"><i class="fa-solid fa-plus text-[10px]"></i> Add Service</button>
</div>

<div id="services-grid" class="page grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
  <!-- Cards rendered dynamically by JS -->
</div>
@endsection

@push('scripts')
<script>
  /* ============= DATA STORE ============= */
  /* Top-level bindings use `var` so the SPA router can re-evaluate this
     script on every navigation without a redeclaration error. */
  var services = @json($services);
  var SERVICE_CATEGORIES = @json($categories);

  /* ============= HELPER LOGIC ============= */
  function getIconForCategory(cat, name) {
    let n = (cat + ' ' + name).toLowerCase();
    if (n.includes('suit') || n.includes('sherwani') || n.includes('waistcoat')) return { icon: 'fa-vest', bg: 'bg-indigo-50', color: 'text-indigo-600' };
    if (n.includes('pant') || n.includes('bottom')) return { icon: 'fa-person-walking', bg: 'bg-emerald-50', color: 'text-emerald-600' };
    if (n.includes('saree') || n.includes('gown') || n.includes('lehenga') || n.includes('ladies')) return { icon: 'fa-person-dress', bg: 'bg-pink-50', color: 'text-pink-600' };
    if (n.includes('service') || n.includes('alteration')) return { icon: 'fa-scissors', bg: 'bg-sky-50', color: 'text-sky-600' };
    return { icon: 'fa-shirt', bg: 'bg-slate-50', color: 'text-slate-600' };
  }

  /* ============= DELETE LOGIC ============= */
  var deleteContext = { id: '', name: '' };

  function confirmDelete(id, name) {
    deleteContext = { id, name };

    Atelier.confirm({
      variant: 'delete',
      title: `Delete ${name}?`,
      message: 'This will permanently remove the item from your catalogue. This action cannot be undone.',
      confirmLabel: 'Confirm Delete',
      onConfirm: executeDelete,
    });
  }

  async function executeDelete() {
    const res = await Atelier.api.delete(`/products-services/${deleteContext.id}`);
    services = services.filter(s => s.id != deleteContext.id);
    renderServices();
    toast(res.message || `Service "${deleteContext.name}" deleted successfully`, 'success');
  }

  /* ============= SAVE LOGIC ============= */
  async function saveService(id = null, btn = null) {
    const payload = {
      measurement_profile: document.getElementById('service-profile').value || null,
      requires_measurements: document.getElementById('service-requires').checked,
      name:                document.getElementById('service-name').value.trim(),
      price:               document.getElementById('service-price').value,
      cost_price:          document.getElementById('service-cost')?.value || null,
      category:            document.getElementById('service-category').value,
      status:              document.getElementById('service-status').value,
      description:         document.getElementById('service-desc').value.trim(),
      sku:                 document.getElementById('service-sku')?.value.trim() || null,
      stock_quantity:      document.getElementById('service-stock')?.value || null,
      low_stock_threshold: document.getElementById('service-threshold')?.value || null,
      unit:                document.getElementById('service-unit')?.value.trim() || null,
      duration_days:       document.getElementById('service-duration')?.value || null,
    };

    if (!payload.name || payload.price === '' || !payload.category || !payload.status) {
      toast('Please fill in all required fields', 'error');
      return;
    }

    if (parseFloat(payload.price) < 0) {
      toast('Price cannot be negative', 'error');
      return;
    }

    Atelier.setBusy(btn, true);
    try {
      const saved = id
        ? await Atelier.api.put(`/products-services/${id}`, payload)
        : await Atelier.api.post(@json(route('products-services.store')), payload);

      if (id) {
        services = services.map(s => s.id == id ? saved : s);
      } else {
        services.push(saved);
      }

      renderServices();
      closeModal();
      toast('Service saved successfully', 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not save the service');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  /* ============= RENDER SERVICES ============= */
  function renderServices() {
    const grid = document.getElementById('services-grid');
    if (!grid) return;
    grid.innerHTML = services.map(s => {
      const iData = getIconForCategory(s.category, s.name);
      return `
      <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all" id="srv-${s.id}">
        <div class="flex justify-between items-start mb-4">
          <div class="w-11 h-11 rounded-lg flex items-center justify-center ${iData.bg} ${iData.color}"><i class="fa-solid ${iData.icon} text-lg"></i></div>
          <div class="flex gap-1">
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 flex items-center justify-center transition-colors" onclick="openModal('add-product', ${JSON.stringify(s).replace(/\"/g, '&quot;')})"><i class="fa-solid fa-pen text-xs"></i></button>
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-500 flex items-center justify-center transition-colors" onclick="confirmDelete('${s.id}', '${s.name}')"><i class="fa-solid fa-trash text-xs"></i></button>
          </div>
        </div>
        <div class="flex justify-between items-start mb-1">
          <div class="text-base font-semibold text-slate-900 tracking-tight">${s.name}</div>
          <span class="badge ${s.status === 'Active' ? 'badge-delivered' : 'badge-overdue'}">${s.status}</span>
        </div>
        <div class="text-xs text-slate-500 mb-4 line-clamp-2 h-8">${Atelier.escapeHtml(s.description || 'No description available.')}</div>
        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
          <div class="font-bold text-slate-900">${Atelier.money(s.price)}</div>
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">${s.category}</span>
        </div>
        ${(s.stock_quantity !== null && s.stock_quantity !== undefined) || s.orders_count > 0 ? `
        <div class="flex items-center justify-between pt-2 mt-2 border-t border-slate-100 text-[11px]">
          ${s.stock_quantity !== null && s.stock_quantity !== undefined
            ? `<span class="${s.is_low_stock ? 'text-red-500 font-semibold' : 'text-slate-500'}">
                 ${s.is_low_stock ? '<i class="fa-solid fa-triangle-exclamation mr-1"></i>' : ''}${s.stock_quantity} ${Atelier.escapeHtml(s.unit || 'in stock')}
               </span>`
            : '<span></span>'}
          ${s.orders_count > 0 ? `<span class="text-slate-400">${s.orders_count} order${s.orders_count === 1 ? '' : 's'}</span>` : '<span></span>'}
        </div>` : ''}
      </div>
    `}).join('') || Atelier.emptyState({
      icon: 'fa-tag',
      title: 'No products or services yet',
      message: 'Add your first tailoring service or stocked item to build the catalogue.'
    });
  }

  /* ============= MODAL OVERRIDES ============= */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'add-product': (data) => {
      const isEdit = data && data.id;
      const title = isEdit ? 'Edit Service' : 'Add New Service';
      const desc = isEdit ? `Update details for ${data.name}` : 'Create a new tailoring service or package';
      
      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">${title}</div>
            <div class="text-xs text-slate-500 mt-1">${desc}</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto">
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Service Name *</label>
              <input type="text" id="service-name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Bespoke Suit" value="${isEdit ? data.name : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Price (₹) *</label>
              <input type="number" id="service-price" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="18000" value="${isEdit ? data.price : ''}" min="0">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category *</label>
              <select id="service-profile" class="w-full px-3 py-2 border rounded-lg mb-2"><option value="">Automatic measurement profile</option>${['shalwar_kameez','sherwani','trouser','waistcoat','kurta_pajama','generic','alteration','accessory'].map(p=>`<option value="${p}" ${isEdit&&data.measurement_profile===p?'selected':''}>${p.replaceAll('_',' ')}</option>`).join('')}</select><label class="block mb-2"><input id="service-requires" type="checkbox" ${!isEdit||data.requires_measurements!==false?'checked':''}> Requires measurements</label><select id="service-category" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                ${SERVICE_CATEGORIES.map(c => `<option ${isEdit && data.category === c ? 'selected' : ''}>${c}</option>`).join('')}
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Cost Price (${Atelier.currency})</label>
              <input type="number" id="service-cost" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Optional" value="${isEdit ? (data.cost_price ?? '') : ''}" min="0">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">SKU</label>
              <input type="text" id="service-sku" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. FAB-001" value="${isEdit ? Atelier.escapeHtml(data.sku || '') : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Stock Qty</label>
              <input type="number" id="service-stock" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Leave blank for services" value="${isEdit ? (data.stock_quantity ?? '') : ''}" min="0">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Low Stock Alert</label>
              <input type="number" id="service-threshold" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="10" value="${isEdit ? (data.low_stock_threshold ?? '') : ''}" min="0">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Unit</label>
              <input type="text" id="service-unit" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. metre, set" value="${isEdit ? Atelier.escapeHtml(data.unit || '') : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Turnaround (days)</label>
              <input type="number" id="service-duration" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Optional" value="${isEdit ? (data.duration_days ?? '') : ''}" min="0">
            </div>
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status *</label>
              <select id="service-status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                <option value="Active" ${isEdit && data.status === 'Active' ? 'selected' : ''}>Active</option>
                <option value="Inactive" ${isEdit && data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
              </select>
            </div>
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label>
              <textarea id="service-desc" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors min-h-[80px]" placeholder="Detailed description of the service...">${isEdit ? (data.description || '') : ''}</textarea>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-2" onclick="saveService(${isEdit ? `'${data.id}'` : 'null'}, this)"><i class="fa-solid fa-check text-xs"></i> Save Service</button>
        </div>`;
    },
    'confirm-delete': (data) => {
      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div class="text-lg font-bold text-slate-900 tracking-tight">Confirm Deletion</div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6">
          <div class="flex items-start gap-4 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-600 flex-shrink-0">
              <i class="fa-solid fa-trash"></i>
            </div>
            <div class="flex-1">
              <p class="text-sm text-slate-700">Are you sure you want to delete this service? <span class="font-bold">${data.name}</span> will be permanently removed. This action cannot be undone.</p>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-600 flex items-center gap-2 transition-colors shadow-sm" onclick="executeDelete()"><i class="fa-solid fa-check text-xs"></i> Delete Permanently</button>
        </div>`;
    }
  });

  Atelier.onPageReady(() => {
    renderServices();
  });
</script>
@endpush
