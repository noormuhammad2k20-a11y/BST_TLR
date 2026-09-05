@extends('cloth-store.layouts.app')
@section('title', 'Categories')
@section('spaPage', 'cloth-store-categories')

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Categories</h1>
    <p class="text-sm text-slate-500 mt-0.5">Manage product categories for your cloth store</p>
  </div>
  <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="openModal('add-category')"><i class="fa-solid fa-plus text-[10px]"></i> Add Category</button>
</div>

<div class="page">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div id="categories-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3.5">
          <!-- Cards rendered dynamically by JS -->
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
  /* ============= DATA STORE ============= */
  var categories = @json($categories);

  /* ============= DELETE LOGIC ============= */
  var deleteContext = { id: '', name: '' };

  function confirmDelete(id, name) {
    deleteContext = { id, name };

    Atelier.confirmAction({
      title: `Delete ${name}?`,
      message: 'This will permanently remove the category. It cannot be undone.',
      confirmLabel: 'Confirm Delete',
      danger: true,
      onConfirm: executeDelete,
    });
  }

  async function executeDelete() {
    try {
      const res = await Atelier.api.delete(`/cloth-store/categories/${deleteContext.id}`);
      categories = categories.filter(c => c.id != deleteContext.id);
      renderCategories();
      toast(res.message || `Category deleted successfully`, 'success');
    } catch(err) {
      Atelier.reportError(err, 'Could not delete category');
    }
  }

  /* ============= SAVE LOGIC ============= */
  async function saveCategory(id = null, btn = null) {
    const payload = {
      name:        document.getElementById('cat-name').value.trim(),
      icon:        document.getElementById('cat-icon').value.trim(),
      color_bg:    document.getElementById('cat-bg').value.trim(),
      color_text:  document.getElementById('cat-text').value.trim(),
      description: document.getElementById('cat-desc').value.trim(),
    };

    if (!payload.name) {
      toast('Category name is required', 'error');
      return;
    }

    Atelier.setBusy(btn, true);
    try {
      const saved = id
        ? await Atelier.api.put(`/cloth-store/categories/${id}`, payload)
        : await Atelier.api.post(@json(route('cloth-store.categories.store')), payload);

      if (id) {
        categories = categories.map(c => c.id == id ? saved.category : c);
      } else {
        saved.category.products_count = 0;
        categories.push(saved.category);
      }

      renderCategories();
      closeModal();
      toast('Category saved successfully', 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not save the category');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  /* ============= RENDER CATEGORIES ============= */
  function renderCategories() {
    const grid = document.getElementById('categories-grid');
    if (!grid) return;
    grid.innerHTML = categories.map(c => {
      const bg = c.color_bg || 'bg-slate-50';
      const color = c.color_text || 'text-slate-600';
      const icon = c.icon || 'fa-tags';
      return `
      <div class="bg-slate-50/50 hover:bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm hover:shadow-lg hover:shadow-indigo-500/10 hover:border-indigo-300 hover:ring-1 hover:ring-indigo-100 hover:-translate-y-0.5 transition-all duration-200 ease-out flex flex-col h-full group">
        <div class="flex justify-between items-start mb-2.5 gap-2">
          <div class="flex items-center gap-2.5 min-w-0">
             <div class="w-8 h-8 rounded-lg flex items-center justify-center ${bg} ${color} shrink-0"><i class="fa-solid ${icon} text-xs"></i></div>
             <div class="text-sm font-bold text-slate-900 tracking-tight truncate">${c.name}</div>
          </div>
          <div class="flex gap-0.5 shrink-0">
            <button class="w-7 h-7 rounded-md text-slate-400 hover:bg-slate-200 hover:text-slate-900 flex items-center justify-center transition-colors" onclick="openModal('add-category', ${JSON.stringify(c).replace(/\"/g, '&quot;')})"><i class="fa-solid fa-pen text-[9px]"></i></button>
            <button class="w-7 h-7 rounded-md text-slate-400 hover:bg-red-100 hover:text-red-600 flex items-center justify-center transition-colors" onclick="confirmDelete('${c.id}', '${c.name.replace(/'/g, "\\'")}')"><i class="fa-solid fa-trash text-[9px]"></i></button>
          </div>
        </div>
        <div class="text-[11px] text-slate-500 mb-2.5 line-clamp-2 leading-relaxed flex-1">${Atelier.escapeHtml(c.description || 'No description available.')}</div>
        <div class="flex items-center justify-between pt-2 border-t border-slate-200/60">
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">${c.products_count} Products</span>
        </div>
      </div>
    `}).join('') || Atelier.emptyState({
      icon: 'fa-tags',
      title: 'No categories yet',
      message: 'Create a category to organize your cloth store inventory.'
    });
  }

  /* ============= MODAL OVERRIDES ============= */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'add-category': (data) => {
      const isEdit = data && data.id;
      const title = isEdit ? 'Edit Category' : 'Add New Category';
      
      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div class="text-lg font-bold text-slate-900 tracking-tight">${title}</div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto">
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category Name *</label>
              <input type="text" id="cat-name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Unstitched Suits" value="${isEdit ? Atelier.escapeHtml(data.name) : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Icon (FontAwesome)</label>
              <input type="text" id="cat-icon" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. fa-shirt" value="${isEdit ? Atelier.escapeHtml(data.icon||'') : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Background Color (Tailwind)</label>
              <input type="text" id="cat-bg" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. bg-indigo-50" value="${isEdit ? Atelier.escapeHtml(data.color_bg||'') : ''}">
            </div>
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Text Color (Tailwind)</label>
              <input type="text" id="cat-text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. text-indigo-600" value="${isEdit ? Atelier.escapeHtml(data.color_text||'') : ''}">
            </div>
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label>
              <textarea id="cat-desc" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors min-h-[80px]" placeholder="Optional description...">${isEdit ? Atelier.escapeHtml(data.description||'') : ''}</textarea>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-2" onclick="saveCategory(${isEdit ? `'${data.id}'` : 'null'}, this)"><i class="fa-solid fa-check text-xs"></i> Save Category</button>
        </div>`;
    }
  });

  Atelier.onPageReady(() => {
    renderCategories();
  });
</script>
@endpush
