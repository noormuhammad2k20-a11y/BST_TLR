@extends('layouts.app')
@section('title', 'Stitching Rates')
@section('spaPage', 'products-services')

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Stitching Rates</h1>
    <p class="text-sm text-slate-500 mt-0.5">Manage tailoring categories, services and stitching prices</p>
  </div>
  <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="openModal('add-category')"><i class="fa-solid fa-folder-plus text-[10px]"></i> Add Category</button>
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
  async function saveCategory(id = null, btn = null) {
    const payload = {
      name: document.getElementById('category-name').value.trim(),
      status: document.getElementById('category-status').value,
    };

    if (!payload.name) {
      toast('Please enter a category name', 'error');
      return;
    }

    Atelier.setBusy(btn, true);
    try {
      const saved = id
        ? await Atelier.api.put(`/products-services/categories/${id}`, payload)
        : await Atelier.api.post(@json(route('products-services.categories.store')), payload);

      if (id) {
        const index = categories.findIndex(c => c.id == id);
        if (index > -1) {
          saved.rates = categories[index].rates; // preserve rates in memory
          categories[index] = saved;
        }
      } else {
        saved.rates = [];
        categories.push(saved);
      }

      renderCategories();
      closeModal();
      toast('Category saved successfully', 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not save category');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  async function saveRate(categoryId, id = null, btn = null) {
    const payload = {
      category_id: categoryId,
      measurement_profile: document.getElementById('rate-profile').value || null,
      requires_measurements: document.getElementById('rate-requires').checked,
      name: document.getElementById('rate-name').value.trim(),
      price: document.getElementById('rate-price').value,
      status: document.getElementById('rate-status').value,
      description: document.getElementById('rate-desc').value.trim(),
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
      const saved = id
        ? await Atelier.api.put(`/products-services/rates/${id}`, payload)
        : await Atelier.api.post(@json(route('products-services.rates.store')), payload);

      const catIndex = categories.findIndex(c => c.id == categoryId);
      if (catIndex > -1) {
        if (id) {
          categories[catIndex].rates = categories[catIndex].rates.map(r => r.id == id ? saved : r);
        } else {
          categories[catIndex].rates.push(saved);
        }
      }

      renderCategories();
      closeModal();
      toast('Service rate saved successfully', 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not save service rate');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  /* ============= RENDER LOGIC ============= */
  function renderCategories() {
    const container = document.getElementById('categories-container');
    if (!container) return;

    if (categories.length === 0) {
      container.innerHTML = Atelier.emptyState({
        icon: 'fa-folder-open',
        title: 'No categories yet',
        message: 'Create your first tailoring category to start adding stitching rates.'
      });
      return;
    }

    container.innerHTML = categories.map(cat => {
      const ratesHtml = cat.rates.length > 0 ? cat.rates.map(r => {
        const iData = getIconForService(r.name);
        return `
        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all flex flex-col justify-between h-full">
          <div>
            <div class="flex justify-between items-start mb-2">
              <div class="flex items-center gap-2 min-w-0">
                <div class="w-8 h-8 rounded-lg shrink-0 flex items-center justify-center ${iData.bg} ${iData.color}"><i class="fa-solid ${iData.icon} text-sm"></i></div>
                <div class="min-w-0">
                  <div class="text-sm font-bold text-slate-900 truncate" title="${Atelier.escapeHtml(r.name)}">${Atelier.escapeHtml(r.name)}</div>
                  <div class="text-[10px] text-slate-500 truncate" title="${Atelier.escapeHtml(r.description || 'No description available.')}">${Atelier.escapeHtml(r.description || 'No description available.')}</div>
                </div>
              </div>
              <div class="flex gap-1 shrink-0 ml-2">
                <button class="w-6 h-6 rounded text-slate-400 hover:bg-slate-100 hover:text-slate-900 flex items-center justify-center transition-colors" onclick="openModal('add-rate', { category_id: ${cat.id}, rate: ${JSON.stringify(r).replace(/\"/g, '&quot;')} })"><i class="fa-solid fa-pen text-[10px]"></i></button>
                <button class="w-6 h-6 rounded text-slate-400 hover:bg-red-50 hover:text-red-500 flex items-center justify-center transition-colors" onclick="confirmDeleteRate('${r.id}', '${Atelier.escapeHtml(r.name)}')"><i class="fa-solid fa-trash text-[10px]"></i></button>
              </div>
            </div>
          </div>
          <div>
            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
              <div class="flex items-center gap-1.5">
                <div class="font-bold text-slate-900 text-sm">${Atelier.money(r.price)}</div>
                <span class="${r.status === 'Active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'} px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider">${r.status}</span>
              </div>
              ${r.orders_count > 0 ? `<div class="text-[10px] text-slate-400 font-medium" title="Total Orders"><i class="fa-solid fa-chart-simple mr-0.5"></i> ${r.orders_count}</div>` : ''}
            </div>
          </div>
        </div>
        `;
      }).join('') : `<div class="col-span-full py-6 text-center text-sm text-slate-500 border border-dashed border-slate-200 rounded-xl">No services in this category.</div>`;

      return `
      <section class="category-section">
        <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <h2 class="text-base font-bold text-slate-800">${Atelier.escapeHtml(cat.name)}</h2>
            ${cat.status !== 'Active' ? `<span class="badge badge-overdue text-[10px]">Inactive</span>` : ''}
          </div>
          <div class="flex gap-2">
            <button class="text-[11px] font-medium text-slate-500 hover:text-slate-900 px-2 py-1 transition-colors" onclick="openModal('add-category', ${JSON.stringify(cat).replace(/\"/g, '&quot;')})">Edit Category</button>
            <button class="text-[11px] font-medium bg-slate-100 hover:bg-slate-200 text-slate-800 px-2 py-1 rounded transition-colors flex items-center gap-1" onclick="openModal('add-rate', { category_id: ${cat.id} })"><i class="fa-solid fa-plus text-[10px]"></i> Add Service</button>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
          ${ratesHtml}
        </div>
      </section>
      `;
    }).join('');
  }

  /* ============= MODAL OVERRIDES ============= */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'add-category': (data) => {
      const isEdit = data && data.id;
      const title = isEdit ? 'Edit Category' : 'Add New Category';
      const desc = isEdit ? `Update details for ${Atelier.escapeHtml(data.name)}` : 'Create a new stitching category';
      
      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">${title}</div>
            <div class="text-xs text-slate-500 mt-1">${desc}</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category Name *</label>
              <input type="text" id="category-name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Wash & Wear" value="${isEdit ? Atelier.escapeHtml(data.name) : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status *</label>
              <select id="category-status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                <option value="Active" ${isEdit && data.status === 'Active' ? 'selected' : ''}>Active</option>
                <option value="Inactive" ${isEdit && data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-between gap-2">
          ${isEdit ? `<button class="text-red-500 hover:bg-red-50 px-3 py-2 rounded-lg text-sm font-medium transition-colors" onclick="confirmDeleteCategory('${data.id}', '${Atelier.escapeHtml(data.name)}')">Delete</button>` : '<div></div>'}
          <div class="flex gap-2">
            <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
            <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-2" onclick="saveCategory(${isEdit ? `'${data.id}'` : 'null'}, this)"><i class="fa-solid fa-check text-xs"></i> Save Category</button>
          </div>
        </div>`;
    },
    'add-rate': (ctx) => {
      const categoryId = ctx.category_id;
      const data = ctx.rate;
      const isEdit = !!data;
      const title = isEdit ? 'Edit Stitching Service' : 'Add Stitching Service';
      const desc = isEdit ? `Update rate for ${Atelier.escapeHtml(data.name)}` : 'Create a new stitching service under this category';
      
      const p = isEdit ? data.measurement_profile : null;
      const profilesHtml = ['shalwar_kameez','sherwani','trouser','waistcoat','kurta_pajama','generic','alteration','accessory']
        .map(x => `<option value="${x}" ${p === x ? 'selected' : ''}>${x.replaceAll('_',' ')}</option>`).join('');

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">${title}</div>
            <div class="text-xs text-slate-500 mt-1">${desc}</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto">
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Service Name *</label>
              <input type="text" id="rate-name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Shalwar Kameez Stitching" value="${isEdit ? Atelier.escapeHtml(data.name) : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Price (${Atelier.currency}) *</label>
              <input type="number" id="rate-price" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="2500" value="${isEdit ? data.price : ''}" min="0">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status *</label>
              <select id="rate-status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                <option value="Active" ${isEdit && data.status === 'Active' ? 'selected' : ''}>Active</option>
                <option value="Inactive" ${isEdit && data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
              </select>
            </div>
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Measurement Profile</label>
              <select id="rate-profile" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors mb-2">
                <option value="">Automatic measurement profile</option>
                ${profilesHtml}
              </select>
              <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input id="rate-requires" type="checkbox" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" ${!isEdit || data.requires_measurements !== false ? 'checked' : ''}>
                Requires measurements
              </label>
            </div>
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description (Optional)</label>
              <textarea id="rate-desc" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors min-h-[80px]" placeholder="Detailed description of the service...">${isEdit ? Atelier.escapeHtml(data.description || '') : ''}</textarea>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-2" onclick="saveRate(${categoryId}, ${isEdit ? `'${data.id}'` : 'null'}, this)"><i class="fa-solid fa-check text-xs"></i> Save Service</button>
        </div>`;
    }
  });

  Atelier.onPageReady(() => {
    renderCategories();
  });
</script>
@endpush
