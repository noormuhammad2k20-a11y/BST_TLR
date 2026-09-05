@extends('cloth-store.layouts.app')
@section('title', 'Products')
@section('spaPage', 'cloth-store-products')

@section('content')
@php $filtered = request()->hasAny(['search','category','status','unit','stock_status','min_price','max_price']); @endphp

<x-cloth-store.page-header
  title="Products Inventory"
  :subtitle="number_format($stats['total']) . ' products · ' . number_format($stats['active']) . ' active' . ($stats['low_stock'] ? ' · ' . $stats['low_stock'] . ' need restocking' : '')">
  <x-slot:actions>
    <a href="{{ route('cloth-store.stock.index') }}" class="btn-cs-ghost">
      <i class="fa-solid fa-warehouse text-[10px]"></i> Stock
    </a>
    <button class="btn-cs-primary" onclick="openModal('add-product')">
      <i class="fa-solid fa-plus text-[10px]"></i> Add Product
    </button>
  </x-slot:actions>
</x-cloth-store.page-header>

<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <x-cloth-store.stat-card
    label="Total Products" icon="fa-box-open" tone="indigo"
    :value="number_format($stats['total'])"
    :sub="number_format($stats['active']) . ' currently active'" />

  <x-cloth-store.stat-card
    label="Stock Value" icon="fa-sack-dollar" tone="emerald"
    :value="'Rs ' . number_format($stats['stock_value'])"
    sub="Valued at cost price" />

  @php
    // Built in PHP: a bound attribute is passed through without HTML-decoding,
    // so &quot; entities written inline would render literally.
    $lowSub = $stats['low_stock']
        ? '<span class="text-amber-600 font-semibold">Needs reordering</span>'
        : 'All levels healthy';
  @endphp
  <x-cloth-store.stat-card
    label="Low Stock" icon="fa-triangle-exclamation" :tone="$stats['low_stock'] ? 'amber' : 'slate'"
    :value="number_format($stats['low_stock'])"
    :sub="$lowSub" />

  <x-cloth-store.stat-card
    label="Categories" icon="fa-tags" tone="violet"
    :value="number_format(count($categories))"
    sub="Across the catalogue" />
</div>

<x-cloth-store.panel :flush="false" class="mb-6">
  {{--
    data-filter-form is picked up by the SPA router: typing debounces, selects
    apply instantly, and only <main> is swapped — no full page reload.
  --}}
  <form data-filter-form method="GET" action="{{ route('cloth-store.products.index') }}"
        class="flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[220px]">
      <label class="label-cs">Search</label>
      <div class="relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
        <input type="search" name="search" value="{{ request('search') }}" autocomplete="off"
               placeholder="Name, SKU or barcode..." class="input-cs w-full pl-9">
      </div>
    </div>

    <div class="w-40">
      <label class="label-cs">Category</label>
      <select name="category" class="input-cs w-full">
        <option value="">All Categories</option>
        @foreach($categories as $id => $name)
          <option value="{{ $id }}" @selected(request('category') == $id)>{{ $name }}</option>
        @endforeach
      </select>
    </div>

    <div class="w-36">
      <label class="label-cs">Stock</label>
      <select name="stock_status" class="input-cs w-full">
        <option value="">Any Level</option>
        <option value="healthy" @selected(request('stock_status') === 'healthy')>Healthy</option>
        <option value="low"     @selected(request('stock_status') === 'low')>Low Stock</option>
        <option value="out"     @selected(request('stock_status') === 'out')>Out of Stock</option>
      </select>
    </div>

    <div class="w-28">
      <label class="label-cs">Unit</label>
      <select name="unit" class="input-cs w-full">
        <option value="">All</option>
        @foreach($units as $u)
          <option value="{{ $u }}" @selected(request('unit') === $u)>{{ $u }}</option>
        @endforeach
      </select>
    </div>

    <div class="w-28">
      <label class="label-cs">Status</label>
      <select name="status" class="input-cs w-full">
        <option value="">All</option>
        <option value="Active"   @selected(request('status') === 'Active')>Active</option>
        <option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option>
      </select>
    </div>

    <div class="w-40">
      <label class="label-cs">Sort</label>
      <select name="sort" class="input-cs w-full">
        <option value="latest"     @selected(request('sort', 'latest') === 'latest')>Newest First</option>
        <option value="oldest"     @selected(request('sort') === 'oldest')>Oldest First</option>
        <option value="name"       @selected(request('sort') === 'name')>Name A–Z</option>
        <option value="price_low"  @selected(request('sort') === 'price_low')>Price: Low to High</option>
        <option value="price_high" @selected(request('sort') === 'price_high')>Price: High to Low</option>
        <option value="stock_low"  @selected(request('sort') === 'stock_low')>Stock: Low to High</option>
        <option value="stock_high" @selected(request('sort') === 'stock_high')>Stock: High to Low</option>
      </select>
    </div>

    @if($filtered || request('sort'))
      <a href="{{ route('cloth-store.products.index') }}" class="btn-cs-ghost">
        <i class="fa-solid fa-xmark text-[10px]"></i> Clear
      </a>
    @endif
  </form>
</x-cloth-store.panel>

@if($products->isEmpty())
  <x-cloth-store.panel :flush="false">
    <x-cloth-store.empty-state
      icon="fa-box-open"
      :title="$filtered ? 'No products match those filters' : 'No products yet'"
      :message="$filtered
          ? 'Try widening your search or clearing the filters.'
          : 'Add your first cloth product to start building your inventory.'">
      @if(!$filtered)
        <x-slot:action>
          <button class="btn-cs-primary" onclick="openModal('add-product')">
            <i class="fa-solid fa-plus text-[10px]"></i> Add Product
          </button>
        </x-slot:action>
      @endif
    </x-cloth-store.empty-state>
  </x-cloth-store.panel>
@else
  <div id="products-grid" class="page grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 xl:gap-4">
    @foreach($products as $i => $p)
      @include('cloth-store.products.partials.card', ['p' => $p, 'i' => $i])
    @endforeach
  </div>

  <div class="page mt-6 bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-3">
    <x-cloth-store.pagination :paginator="$products" noun="product" />
  </div>
@endif
@endsection

@push('scripts')
<script>
  /* Categories drive the modal's select. This is a small lookup list, not the
     product table — the products themselves are rendered and paginated by the
     server so the browser never holds the full inventory. */
  var categories = @json($categories);

  /* ============= DELETE LOGIC ============= */
  var deleteContext = { id: '', name: '' };

  function confirmDelete(id, name) {
    deleteContext = { id, name };

    Atelier.confirmAction({
      title: `Delete ${name}?`,
      message: 'This will permanently remove the product from your inventory. Products that appear on past sales cannot be deleted.',
      confirmLabel: 'Confirm Delete',
      danger: true,
      onConfirm: executeDelete,
    });
  }

  async function executeDelete() {
    try {
      const res = await Atelier.api.delete(`/cloth-store/products/${deleteContext.id}`);
      toast(res.message || `Product "${deleteContext.name}" deleted successfully`, 'success');
      // Re-fetch the current page so pagination, counts and the grid stay
      // truthful — removing the card client-side would leave a 12-card page
      // showing 11 and the total count stale.
      await refreshGrid();
    } catch (err) {
      Atelier.reportError(err, 'Could not delete the product');
    }
  }

  /* ============= SAVE LOGIC ============= */
  async function saveProduct(id = null, btn = null) {
    const payload = {
      name:                document.getElementById('prod-name').value.trim(),
      sku:                 document.getElementById('prod-sku').value.trim() || null,
      cs_category_id:      document.getElementById('prod-category').value,
      price:               document.getElementById('prod-price').value,
      cost_price:          document.getElementById('prod-cost').value || null,
      stock_quantity:      document.getElementById('prod-stock').value || 0,
      low_stock_threshold: document.getElementById('prod-threshold').value || 10,
      unit:                document.getElementById('prod-unit').value || 'pcs',
      status:              document.getElementById('prod-status').value,
      description:         document.getElementById('prod-desc').value.trim(),
    };

    if (!payload.name || payload.price === '' || !payload.cs_category_id) {
      toast('Please fill in all required fields', 'error');
      return;
    }

    if (parseFloat(payload.price) < 0) {
      toast('Price cannot be negative', 'error');
      return;
    }

    Atelier.setBusy(btn, true);
    try {
      if (id) {
        await Atelier.api.put(`/cloth-store/products/${id}`, payload);
      } else {
        await Atelier.api.post(@json(route('cloth-store.products.store')), payload);
      }

      closeModal();
      toast('Product saved successfully', 'success');
      await refreshGrid();
    } catch (err) {
      Atelier.reportError(err, 'Could not save the product');
    } finally {
      Atelier.setBusy(btn, false);
    }
  }

  /* ============= GRID REFRESH =============
     Re-renders the current URL (filters, sort and page intact) through the
     SPA router, so a save or delete updates the grid without a page reload
     and without losing where the user was. */
  function refreshGrid() {
    return SpaRouter.navigate(location.href, { push: false, scroll: false });
  }

  /* ============= MODAL OVERRIDES ============= */
  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'add-product': (data) => {
      const isEdit = data && data.id;
      const title = isEdit ? 'Edit Product' : 'Add New Product';
      const desc = isEdit ? `Update details for ${data.name}` : 'Add a new product to your cloth store inventory';

      const catOptions = Object.entries(categories).map(([id, name]) => {
        return `<option value="${id}" ${isEdit && data.cs_category_id == id ? 'selected' : ''}>${Atelier.escapeHtml(name)}</option>`;
      }).join('');

      const unitOption = (value, label) =>
        `<option value="${value}" ${isEdit && data.unit === value ? 'selected' : ''}>${label}</option>`;

      return `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">${title}</div>
            <div class="text-xs text-slate-500 mt-1">${Atelier.escapeHtml(desc)}</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6 overflow-y-auto">
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Product Name *</label>
              <input type="text" id="prod-name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Premium Lawn 3-Piece" value="${isEdit ? Atelier.escapeHtml(data.name) : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category *</label>
              <select id="prod-category" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                ${catOptions}
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">SKU</label>
              <input type="text" id="prod-sku" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. GA-001" value="${isEdit ? Atelier.escapeHtml(data.sku || '') : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Selling Price (${Atelier.currency}) *</label>
              <input type="number" step="0.01" inputmode="decimal" id="prod-price" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="850.00" value="${isEdit ? data.price : ''}" min="0">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Cost Price (${Atelier.currency})</label>
              <input type="number" step="0.01" inputmode="decimal" id="prod-cost" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="Optional" value="${isEdit ? (data.cost_price ?? '') : ''}" min="0">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Current Stock Qty</label>
              <input type="number" step="0.01" inputmode="decimal" id="prod-stock" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. 120.50" value="${isEdit ? (data.stock_quantity ?? '0') : ''}" min="0">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Low Stock Alert at</label>
              <input type="number" step="0.01" inputmode="decimal" id="prod-threshold" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="10" value="${isEdit ? (data.low_stock_threshold ?? '10') : '10'}" min="0">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Unit</label>
              <select id="prod-unit" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                ${unitOption('pcs', 'Pieces (pcs)')}
                ${unitOption('meter', 'Meters')}
                ${unitOption('suit', 'Suits')}
                ${unitOption('yard', 'Yards')}
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status *</label>
              <select id="prod-status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                <option value="Active" ${isEdit && data.status === 'Active' ? 'selected' : ''}>Active</option>
                <option value="Inactive" ${isEdit && data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
              </select>
            </div>
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label>
              <textarea id="prod-desc" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors min-h-[80px]" placeholder="Detailed description of the product...">${isEdit ? Atelier.escapeHtml(data.description || '') : ''}</textarea>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-2" onclick="saveProduct(${isEdit ? `'${data.id}'` : 'null'}, this)"><i class="fa-solid fa-check text-xs"></i> Save Product</button>
        </div>`;
    }
  });

  /* Edit buttons carry their product as JSON on the element, so the grid can
     be server-rendered without also shipping a parallel JS array. */
  function editProduct(btn) {
    openModal('add-product', JSON.parse(btn.dataset.product));
  }
</script>
@endpush
