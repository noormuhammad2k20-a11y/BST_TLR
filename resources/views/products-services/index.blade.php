@extends('layouts.app')
@section('title', 'Stitching Rates')
@section('spaPage', 'products-services')

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Stitching Rates</h1>
    <p class="text-sm text-slate-500 mt-0.5">Manage tailoring categories, services and stitching prices</p>
  </div>
  <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="openModal('add-service')"><i class="fa-solid fa-plus text-[10px]"></i> Add Service</button>
</div>

<div id="categories-container" class="page flex flex-col gap-5">
  <!-- Categories rendered dynamically by JS -->
</div>
@endsection

@push('scripts')
<script>
  /* ============= DATA STORE ============= */
  var categories = @json($categories);
  var legacyCategories = @json($legacyCategories);

  /* ============= HELPER LOGIC ============= */
  function getIconForService(name) {
    let n = (name || '').toLowerCase();
    if (n.includes('suit') || n.includes('sherwani') || n.includes('waistcoat')) return { icon: 'fa-vest', bg: 'bg-indigo-50', color: 'text-indigo-600' };
    if (n.includes('pant') || n.includes('bottom')) return { icon: 'fa-person-walking', bg: 'bg-emerald-50', color: 'text-emerald-600' };
    if (n.includes('saree') || n.includes('gown') || n.includes('lehenga') || n.includes('ladies')) return { icon: 'fa-person-dress', bg: 'bg-pink-50', color: 'text-pink-600' };
    if (n.includes('service') || n.includes('alteration')) return { icon: 'fa-scissors', bg: 'bg-sky-50', color: 'text-sky-600' };
    return { icon: 'fa-shirt', bg: 'bg-slate-50', color: 'text-slate-600' };
  }

  /* ============= DELETE LOGIC ============= */
  var deleteContext = { type: '', id: '', name: '' };

  function confirmDeleteCategory(id, name) {
    deleteContext = { type: 'category', id, name };
    Atelier.confirm({
      variant: 'delete',
      title: `Delete Category?`,
      message: `Are you sure you want to delete the category "${name}"? This action cannot be undone.`,
      confirmLabel: 'Confirm Delete',
      onConfirm: executeDelete,
    });
  }

  function confirmDeleteRate(id, name) {
    deleteContext = { type: 'rate', id, name };
    Atelier.confirm({
      variant: 'delete',
      title: `Delete Service Rate?`,
      message: `Are you sure you want to delete the rate for "${name}"? This action cannot be undone.`,
      confirmLabel: 'Confirm Delete',
      onConfirm: executeDelete,
    });
  }

  async function executeDelete() {
    if (deleteContext.type === 'category') {
      try {
        const res = await Atelier.api.delete(`/products-services/categories/${deleteContext.id}`);
        categories = categories.filter(c => c.id != deleteContext.id);
        renderCategories();
        toast(res.message || 'Category deleted successfully', 'success');
      } catch (err) {
        Atelier.reportError(err, 'Could not delete category');
      }
    } else if (deleteContext.type === 'rate') {
      try {
        const res = await Atelier.api.delete(`/products-services/rates/${deleteContext.id}`);
        categories = categories.map(c => {
          c.rates = c.rates.filter(r => r.id != deleteContext.id);
          return c;
        });
        renderCategories();
        toast(res.message || 'Service rate deleted successfully', 'success');
      } catch (err) {
        Atelier.reportError(err, 'Could not delete service rate');
      }
    }
  }

  /* ============= SAVE LOGIC ============= */
  async function saveService(categoryId = null, rateId = null, btn = null) {
    const payload = {
      name: document.getElementById('service-name').value.trim(),
      price: document.getElementById('service-price').value,
      status: document.getElementById('service-status').value,
    };

    if (!payload.name || payload.price === '' || !payload.status) {
      toast('Please fill in all required fields', 'error');
      return;
    }

    if (parseFloat(payload.price) < 0) {
      toast('Price cannot be negative', 'error');
      return;
    }

    Atelier.setBusy(btn, true);
    try {
      if (categoryId) {
        // Updating existing
        await Atelier.api.put(`/products-services/categories/${categoryId}`, { name: payload.name, status: payload.status });
        if (rateId) {
          const savedRate = await Atelier.api.put(`/products-services/rates/${rateId}`, { ...payload, category_id: categoryId, measurement_profile: null, requires_measurements: true, description: '' });
          
          const catIndex = categories.findIndex(c => c.id == categoryId);
          if (catIndex > -1) {
            categories[catIndex].name = payload.name;
            categories[catIndex].status = payload.status;
            categories[catIndex].rates = categories[catIndex].rates.map(r => r.id == rateId ? savedRate : r);
          }
        } else {
          // Creating rate for existing category that didn't have one
          const savedRate = await Atelier.api.post(@json(route('products-services.rates.store')), { ...payload, category_id: categoryId, measurement_profile: null, requires_measurements: true, description: '' });
          const catIndex = categories.findIndex(c => c.id == categoryId);
          if (catIndex > -1) {
            categories[catIndex].name = payload.name;
            categories[catIndex].status = payload.status;
            categories[catIndex].rates = [savedRate];
          }
        }
      } else {
        // Creating new category and rate
        const savedCat = await Atelier.api.post(@json(route('products-services.categories.store')), { name: payload.name, status: payload.status });
        const savedRate = await Atelier.api.post(@json(route('products-services.rates.store')), { ...payload, category_id: savedCat.id, measurement_profile: null, requires_measurements: true, description: '' });
        
        savedCat.rates = [savedRate];
        categories.push(savedCat);
      }

      renderCategories();
      closeModal();
      toast('Service saved successfully', 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not save service');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  function confirmDeleteService(categoryId, rateIds, name) {
    deleteContext = { type: 'service', categoryId, rateIds, name };
    Atelier.confirm({
      variant: 'delete',
      title: `Delete Service?`,
      message: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
      confirmLabel: 'Confirm Delete',
      onConfirm: executeDelete,
    });
  }

  /* Override executeDelete from original logic */
  async function executeDelete() {
    if (deleteContext.type === 'service') {
      try {
        if (deleteContext.rateIds && deleteContext.rateIds.length > 0) {
            for (let id of deleteContext.rateIds) {
                try {
                    await Atelier.api.delete(`/products-services/rates/${id}`);
                } catch (rateErr) {
                    // Ignore 404 if the rate was already deleted on a previous attempt
                    if (rateErr.status !== 404) throw rateErr;
                }
            }
        }
        await Atelier.api.delete(`/products-services/categories/${deleteContext.categoryId}`);
        categories = categories.filter(c => c.id != deleteContext.categoryId);
        renderCategories();
        toast('Service deleted successfully', 'success');
      } catch (err) {
        // Show specific backend message if available (like "Category has rates")
        Atelier.reportError(err, err.data && err.data.message ? err.data.message : 'Could not delete service');
      }
    } else {
      // Fallback for any legacy calls
      toast('Unknown delete operation', 'error');
    }
  }

  /* ============= RENDER LOGIC ============= */
  /* ============= RENDER LOGIC ============= */
  function renderCategories() {
    const container = document.getElementById('categories-container');
    if (!container) return;

    if (categories.length === 0) {
      container.innerHTML = Atelier.emptyState({
        icon: 'fa-folder-open',
        title: 'No services yet',
        message: 'Create your first stitching service.'
      });
      return;
    }

    const cardsHtml = categories.map(cat => {
      const r = cat.rates.length > 0 ? cat.rates[0] : null;
      
      const priceHtml = r 
        ? `<div class="font-bold text-slate-900 text-sm">${Atelier.money(r.price)}</div>` 
        : `<div class="font-medium text-slate-400 text-xs italic">Not Set</div>`;
      
      const statusHtml = cat.status === 'Active' 
         ? `<span class="bg-emerald-50 text-emerald-600 border border-emerald-100 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider">Active</span>`
         : `<span class="bg-rose-50 text-rose-600 border border-rose-100 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider">Inactive</span>`;
      
      const btnAction = r 
        ? `openModal('add-service', { category_id: ${cat.id}, rate_id: ${r.id}, name: '${Atelier.escapeHtml(cat.name)}', price: ${r.price}, status: '${cat.status}' })`
        : `openModal('add-service', { category_id: ${cat.id}, name: '${Atelier.escapeHtml(cat.name)}', status: '${cat.status}' })`;

      const rateIds = cat.rates.map(rate => rate.id);
      const deleteAction = `confirmDeleteService(${cat.id}, [${rateIds.join(',')}], '${Atelier.escapeHtml(cat.name)}')`;

      return `
      <div class="bg-white p-4 rounded-xl border border-slate-200 hover:border-indigo-300 hover:shadow-md transition-all duration-300 flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-3 min-w-0 mb-3">
             <div class="w-10 h-10 rounded-lg shrink-0 flex items-center justify-center bg-slate-50 text-slate-600 border border-slate-100">
               <i class="fa-solid fa-shirt text-base"></i>
             </div>
             <div class="min-w-0">
               <h2 class="text-sm font-bold text-slate-800 truncate" title="${Atelier.escapeHtml(cat.name)}">${Atelier.escapeHtml(cat.name)}</h2>
               <div class="mt-1">${statusHtml}</div>
             </div>
          </div>
          
          <div class="bg-slate-50 rounded-lg p-2.5 border border-slate-100">
             <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Stitching Rate</div>
             ${priceHtml}
          </div>
        </div>
        
        <div class="mt-4 flex items-center gap-2">
           <button class="flex-1 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center gap-1.5 shadow-sm" onclick="${btnAction}">
             <i class="fa-solid fa-pen text-[10px]"></i> Edit
           </button>
           <button class="flex-1 bg-white hover:bg-red-50 border border-slate-200 text-slate-500 hover:text-red-600 hover:border-red-200 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center gap-1.5 shadow-sm" onclick="${deleteAction}">
             <i class="fa-solid fa-trash text-[10px]"></i> Delete
           </button>
        </div>
      </div>
      `;
    }).join('');

    container.innerHTML = `
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
             <h2 class="text-base font-bold text-slate-800 tracking-tight">Available Services</h2>
             <p class="text-[11px] text-slate-500 mt-0.5">Manage your tailoring categories and rates</p>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-full border border-indigo-100 shadow-sm">
              ${categories.length} Services
            </span>
          </div>
        </div>
        <div class="p-5">
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3.5">
             ${cardsHtml}
          </div>
        </div>
      </div>
    `;
  }

  /* ============= MODAL OVERRIDES ============= */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'add-service': (data) => {
      const isEdit = !!(data && data.category_id);
      const categoryId = isEdit ? data.category_id : null;
      const rateId = isEdit && data.rate_id ? data.rate_id : null;
      
      const title = isEdit ? 'Edit Service' : 'Add New Service';
      const desc = isEdit ? `Update details for ${Atelier.escapeHtml(data.name)}` : 'Create a new stitching service';
      
      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">${title}</div>
            <div class="text-xs text-slate-500 mt-1">${desc}</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="col-span-1 sm:col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Service Name *</label>
              <input type="text" id="service-name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Wash & Wear" value="${isEdit ? Atelier.escapeHtml(data.name) : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Price (${Atelier.currency}) *</label>
              <input type="number" id="service-price" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="2500" value="${isEdit && data.price !== undefined ? data.price : ''}" min="0">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status *</label>
              <select id="service-status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                <option value="Active" ${(!isEdit || data.status === 'Active') ? 'selected' : ''}>Active</option>
                <option value="Inactive" ${(isEdit && data.status === 'Inactive') ? 'selected' : ''}>Inactive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-2" onclick="saveService(${categoryId}, ${rateId}, this)"><i class="fa-solid fa-check text-xs"></i> Save Service</button>
        </div>`;
    }
  });

  Atelier.onPageReady(() => {
    renderCategories();
  });
</script>
@endpush
