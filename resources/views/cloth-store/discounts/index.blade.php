@extends('cloth-store.layouts.app')
@section('title', 'Discounts & Offers')
@section('spaPage', 'cloth-store-discounts')

@section('content')
<x-cloth-store.page-header
    title="Discounts &amp; Offers"
    subtitle="Manage promotions, coupons and seasonal campaigns">
    <x-slot:actions>
        <button onclick="window.openDiscountModal()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> New Offer
        </button>
    </x-slot:actions>
</x-cloth-store.page-header>

<div class="page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-cloth-store.stat-card
        label="Active Offers" icon="fa-circle-check" tone="emerald"
        :value="number_format($activeCount)" sub="Running right now" />

    <x-cloth-store.stat-card
        label="Scheduled" icon="fa-clock" :tone="$scheduledCount ? 'amber' : 'slate'"
        :value="number_format($scheduledCount)" sub="Starting later" />

    <x-cloth-store.stat-card
        label="Expired" icon="fa-calendar-xmark" tone="slate"
        :value="number_format($expiredCount)" sub="Past their end date" />

    <x-cloth-store.stat-card
        label="Disabled" icon="fa-ban" :tone="$disabledCount ? 'red' : 'slate'"
        :value="number_format($disabledCount)" sub="Turned off manually" />
</div>

<x-cloth-store.panel class="mb-8">
    <x-slot:toolbar>
        <form data-filter-form id="discounts-filter" method="GET" action="{{ route('cloth-store.discounts.index') }}" class="flex flex-wrap gap-3 items-center w-full">
            <div class="relative flex-1 min-w-[240px] max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" id="offer-search" value="{{ request('search') }}" autocomplete="off"
                       placeholder="Search offers by name or code..." class="input-cs w-full pl-9">
            </div>
            <select name="type" class="input-cs w-48">
                <option value="">All Types</option>
                <option value="percentage" {{ request('type') == 'percentage' ? 'selected' : '' }}>Percentage</option>
                <option value="fixed" {{ request('type') == 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                <option value="product_specific" {{ request('type') == 'product_specific' ? 'selected' : '' }}>Product Specific</option>
                <option value="category" {{ request('type') == 'category' ? 'selected' : '' }}>Category</option>
                <option value="coupon" {{ request('type') == 'coupon' ? 'selected' : '' }}>Coupon Code</option>
            </select>
            @if(request()->hasAny(['search', 'type']))
                <a href="{{ route('cloth-store.discounts.index') }}" class="btn-cs-ghost">
                    <i class="fa-solid fa-xmark text-[10px]"></i> Clear
                </a>
            @endif
        </form>
    </x-slot:toolbar>

        <table class="table-cs" id="offer-table">
            <thead>
                <tr>
                    <th>Offer Details</th>
                    <th>Discount</th>
                    <th>Duration</th>
                    <th class="text-center">Usage</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($discounts as $d)
                <tr class="{{ !$d->is_active ? 'opacity-60' : '' }}">
                    <td>
                        <div class="cell-strong flex items-center gap-2">
                            {{ $d->name }}
                            @if($d->type === 'coupon' && $d->code)
                                <span class="badge badge-neutral"><i class="fa-solid fa-ticket mr-1"></i>{{ $d->code }}</span>
                            @endif
                        </div>
                        <div class="cell-muted uppercase tracking-wider">{{ str_replace('_', ' ', $d->type) }}</div>
                    </td>
                    <td>
                        <div class="cell-strong cell-num">
                            @if(in_array($d->type, ['percentage', 'seasonal']))
                                {{ number_format($d->value, 0) }}% OFF
                            @else
                                Rs {{ number_format($d->value, 2) }}
                            @endif
                        </div>
                        @if($d->min_purchase > 0)
                        <div class="cell-muted cell-num">Min. spend Rs {{ number_format($d->min_purchase) }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="text-sm text-slate-600 cell-num">
                            {{ $d->start_date ? $d->start_date->format('d M Y') : 'Always' }}
                            <i class="fa-solid fa-arrow-right mx-1 text-slate-300 text-[10px]"></i>
                            {{ $d->end_date ? $d->end_date->format('d M Y') : 'Forever' }}
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="cell-strong cell-num">{{ number_format($d->usage_count) }}</div>
                        <div class="cell-muted cell-num">of {!! $d->usage_limit ? number_format($d->usage_limit) : '&infin;' !!}</div>
                    </td>
                    <td class="text-center">
                        @php
                            $status = $d->calculated_status;
                            $statusBadge = match ($status) {
                                'Active'    => 'badge-delivered',
                                'Scheduled' => 'badge-progress',
                                'Expired'   => 'badge-neutral',
                                default     => 'badge-overdue',
                            };
                        @endphp
                        <span class="badge {{ $statusBadge }}">{{ $status }}</span>
                    </td>
                    <td>
                        {{-- Note: the toggle button's colour classes used to be
                             interpolated (hover:text-{{ … }}-600). A class
                             assembled at runtime is invisible to a compiled
                             Tailwind build, so it silently rendered unstyled. --}}
                        <div class="flex items-center justify-end gap-1 row-actions">
                            <button onclick="window.toggleDiscount({{ $d->id }})" class="btn-cs-icon"
                                    title="{{ $d->is_active ? 'Disable offer' : 'Enable offer' }}">
                                <i class="fa-solid fa-power-off text-xs {{ $d->is_active ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                            </button>
                            <button onclick='window.editDiscount(@json($d, JSON_HEX_APOS | JSON_HEX_QUOT))' class="btn-cs-icon" title="Edit">
                                <i class="fa-solid fa-pen text-xs"></i>
                            </button>
                            {{-- Deletes over AJAX: a native submit here was a
                                 full page reload, and the browser confirm()
                                 blocks the whole tab. --}}
                            <button type="button" onclick="deleteDiscount({{ $d->id }})"
                                    class="btn-cs-icon danger" title="Delete">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                    <x-cloth-store.empty-state
                        :colspan="6"
                        icon="fa-tag"
                        title="No offers found"
                        message="Create a promotion, coupon or seasonal campaign to see it here.">
                        <x-slot:action>
                            <button onclick="window.openDiscountModal()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                                <i class="fa-solid fa-plus"></i> New Offer
                            </button>
                        </x-slot:action>
                    </x-cloth-store.empty-state>
                @endforelse
            </tbody>
        </table>

    <x-slot:footer>
        <x-cloth-store.pagination :paginator="$discounts" noun="offer" />
    </x-slot:footer>
</x-cloth-store.panel>

<!-- ========================================== -->
<!-- CREATE / EDIT MODAL                        -->
<!-- ========================================== -->
<div id="discount-modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4 sm:p-6 transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh] md:max-h-[85vh]">
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex items-center justify-between shrink-0">
            <h3 class="text-xl font-bold text-slate-900" id="modal-title">Create Discount Offer</h3>
            <button type="button" onclick="window.closeDiscountModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200 transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="p-6 md:p-8 overflow-y-auto flex-1">
            <form id="discount-form" method="POST" action="{{ route('cloth-store.discounts.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST" id="form-method">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                    <!-- Name & Type -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Offer Name</label>
                        <input type="text" name="name" id="f-name" required placeholder="e.g. Summer Sale 20%" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Discount Type</label>
                        <select name="type" id="f-type" required onchange="toggleFields()" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                            <option value="percentage">Percentage Discount</option>
                            <option value="fixed">Fixed Amount Discount</option>
                            <option value="coupon">Coupon Code</option>
                            <option value="product_specific">Product Specific</option>
                            <option value="category">Category Discount</option>
                            <option value="customer_specific">Customer Specific</option>
                            <option value="buy_x_get_y">Buy X Get Y (Coming Soon)</option>
                            <option value="min_order">Minimum Order Discount</option>
                            <option value="seasonal">Seasonal Campaign</option>
                        </select>
                    </div>

                    <!-- Code & Value -->
                    <div id="wrap-code" class="hidden">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Coupon Code</label>
                        <input type="text" name="code" id="f-code" placeholder="e.g. WINTER50" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white uppercase transition shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Discount Value</label>
                        <div class="relative">
                            <input type="number" name="value" id="f-value" required min="0" step="any" placeholder="10" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                        </div>
                    </div>

                    <!-- Dates -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Start Date (Optional)</label>
                        <input type="datetime-local" name="start_date" id="f-start" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">End Date (Optional)</label>
                        <input type="datetime-local" name="end_date" id="f-end" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                    </div>

                    <!-- Limits & Thresholds -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Minimum Purchase (Rs)</label>
                        <input type="number" name="min_purchase" id="f-min" min="0" placeholder="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Total Usage Limit (Overall)</label>
                        <input type="number" name="usage_limit" id="f-ulimit" min="1" placeholder="Leave empty for unlimited" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:bg-white transition shadow-sm">
                    </div>

                    <!-- Conditional Dynamic Fields -->
                    <div id="wrap-products" class="md:col-span-2 hidden">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Applicable Products</label>
                        <select name="applicable_products[]" id="f-products" multiple class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm h-32 focus:border-indigo-500 focus:bg-white transition shadow-sm">
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Hold CTRL/CMD to select multiple.</p>
                    </div>

                    <div id="wrap-categories" class="md:col-span-2 hidden">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Applicable Categories</label>
                        <select name="applicable_categories[]" id="f-categories" multiple class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm h-32 focus:border-indigo-500 focus:bg-white transition shadow-sm">
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="wrap-customers" class="md:col-span-2 hidden">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">Applicable Customers</label>
                        <select name="applicable_customers[]" id="f-customers" multiple class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm h-32 focus:border-indigo-500 focus:bg-white transition shadow-sm">
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2 mt-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_active" id="f-active" value="1" checked class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                            <span class="text-sm font-semibold text-slate-700">Offer is Active</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3 shrink-0">
            <button type="button" onclick="window.closeDiscountModal()" class="px-5 py-2.5 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition shadow-sm">Cancel</button>
            {{-- requestSubmit(), not submit(): submit() bypasses submit event
                 listeners entirely, so the AJAX handler never ran and this was
                 a full page reload. --}}
            <button type="button" id="discount-save-btn" onclick="document.getElementById('discount-form').requestSubmit()" class="px-5 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition shadow-sm flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                <i class="fa-solid fa-save"></i> Save Offer
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    window.toggleFields = function() {
        let type = document.getElementById('f-type').value;
        
        document.getElementById('wrap-code').classList.toggle('hidden', type !== 'coupon');
        document.getElementById('wrap-products').classList.toggle('hidden', type !== 'product_specific');
        document.getElementById('wrap-categories').classList.toggle('hidden', type !== 'category');
        document.getElementById('wrap-customers').classList.toggle('hidden', type !== 'customer_specific');
        
        if (type !== 'coupon') document.getElementById('f-code').value = '';
    };

    window.hideLayout = function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.style.display = 'none';
        const header = document.querySelector('header');
        if (header) header.style.display = 'none';
        const mainContent = document.querySelector('.ml-64');
        if (mainContent) {
            mainContent.classList.remove('ml-64');
            mainContent.dataset.removedMargin = 'true';
        }
    };

    window.restoreLayout = function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.style.display = '';
        const header = document.querySelector('header');
        if (header) header.style.display = '';
        const mainContent = document.querySelector('[data-removed-margin="true"]');
        if (mainContent) {
            mainContent.classList.add('ml-64');
            delete mainContent.dataset.removedMargin;
        }
    };

    window.openDiscountModal = function() {
        document.getElementById('discount-form').reset();
        document.getElementById('form-method').value = 'POST';
        document.getElementById('discount-form').action = "{{ route('cloth-store.discounts.store') }}";
        document.getElementById('modal-title').innerText = 'Create Discount Offer';
        window.toggleFields();
        window.hideLayout();
        document.getElementById('discount-modal').classList.remove('hidden');
    };

    window.editDiscount = function(data) {
        document.getElementById('form-method').value = 'PUT';
        document.getElementById('discount-form').action = "/cloth-store/discounts/" + data.id;
        document.getElementById('modal-title').innerText = 'Edit Discount Offer';
        
        document.getElementById('f-name').value = data.name;
        document.getElementById('f-type').value = data.type;
        document.getElementById('f-code').value = data.code || '';
        document.getElementById('f-value').value = data.value;
        
        if(data.start_date) document.getElementById('f-start').value = data.start_date.substring(0, 16);
        if(data.end_date) document.getElementById('f-end').value = data.end_date.substring(0, 16);
        
        document.getElementById('f-min').value = data.min_purchase || '';
        document.getElementById('f-ulimit').value = data.usage_limit || '';
        document.getElementById('f-active').checked = data.is_active ? true : false;
        
        // Multi-selects
        window.setSelectMultiple('f-products', data.applicable_products);
        window.setSelectMultiple('f-categories', data.applicable_categories);
        window.setSelectMultiple('f-customers', data.applicable_customers);
        
        window.toggleFields();
        window.hideLayout();
        document.getElementById('discount-modal').classList.remove('hidden');
    };

    window.closeDiscountModal = function() {
        document.getElementById('discount-modal').classList.add('hidden');
        window.restoreLayout();
    };

    window.setSelectMultiple = function(id, values) {
        let select = document.getElementById(id);
        if(!values) values = [];
        Array.from(select.options).forEach(opt => {
            opt.selected = values.includes(opt.value) || values.includes(parseInt(opt.value));
        });
    };

    /*
     * Create / edit submit.
     *
     * Deliberately not Atelier.ajaxForm: that helper builds its payload with
     * Object.fromEntries(new FormData(form).entries()), which keeps only the
     * LAST value of any repeated field. This form has three multi-selects
     * (applicable_products[], applicable_categories[], applicable_customers[]),
     * so that would silently reduce each list to a single id and quietly break
     * the offer's targeting. getAll() keeps them whole.
     */
    var discountSaving = false;

    Atelier.onPageReady(() => {
        const form = document.getElementById('discount-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (discountSaving) return;

            const fd = new FormData(form);
            const payload = {};

            for (const key of new Set(fd.keys())) {
                if (key === '_token' || key === '_method') continue;

                if (key.endsWith('[]')) {
                    payload[key.slice(0, -2)] = fd.getAll(key).filter(v => v !== '');
                } else {
                    payload[key] = fd.get(key);
                }
            }

            const method = (fd.get('_method') || 'POST').toUpperCase();
            const btn = document.getElementById('discount-save-btn');

            discountSaving = true;
            if (btn) btn.disabled = true;
            Atelier.clearFieldErrors(form);

            try {
                const res = await Atelier.request(form.action, { method, body: payload });

                toast(res?.message || 'Discount saved', 'success');
                closeDiscountModal();
                await Atelier.refreshPage();
            } catch (err) {
                Atelier.showFieldErrors(form, err);
                Atelier.reportError(err);
            } finally {
                discountSaving = false;
                if (btn && document.body.contains(btn)) btn.disabled = false;
            }
        });
    });

    window.deleteDiscount = function(id) {
        Atelier.confirmAction({
            title: 'Delete this offer?',
            message: 'The discount will be removed permanently.',
            confirmLabel: 'Delete',
            onConfirm: async () => {
                await Atelier.request(`/cloth-store/discounts/${id}`, { method: 'DELETE' });
                await Atelier.refreshPage();
            },
            successMessage: 'Discount offer deleted',
        });
    };

    window.toggleDiscount = async function(id) {
        try {
            let res = await fetch(`/cloth-store/discounts/${id}/toggle`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            let data = await res.json();
            if (data.success) {
                // This POST goes out via raw fetch, so it never reached the
                // cache-busting in Atelier.request — refreshPage drops the
                // cache itself before re-rendering.
                await Atelier.refreshPage();
            }
        } catch (err) {
            toast('Failed to toggle status', 'error');
        }
    };
</script>
@endpush
