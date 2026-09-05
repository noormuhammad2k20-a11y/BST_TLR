function renderCart() {
        const list = document.getElementById('cart-list');
        const empty = document.getElementById('empty-cart');
        const pill = document.getElementById('cart-count-pill');

        list.innerHTML = '';

        if (cart.length === 0) {
            list.classList.add('hidden');
            if(empty) empty.classList.remove('hidden');
            document.getElementById('btn-checkout').disabled = true;
            pill.classList.add('hidden');
            pill.classList.remove('inline-flex');
            renderedCartIds = new Set();
            updateTotals();
            return;
        }

        if(empty) empty.classList.add('hidden');
        list.classList.remove('hidden');
        document.getElementById('btn-checkout').disabled = false;

        pill.classList.remove('hidden');
        pill.classList.add('inline-flex');
        pill.textContent = cart.length;

        list.innerHTML = cart.map(item => {
            const total = item.price * item.qty;
            const isMeter = item.unit === 'meter';
            const stepUp = isMeter ? 0.5 : 1;
            const isNew = !renderedCartIds.has(String(item.id));

            return `
                <li data-cart-id="${item.id}" class="${isNew ? 'cart-item-in ' : ''}group flex items-center gap-2 p-2 bg-white border border-slate-200 rounded-lg hover:border-indigo-200 hover:shadow-sm transition-all">
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-800 text-[12px] truncate leading-tight">${Atelier.escapeHtml(item.name)}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5 cell-num">Rs ${item.price.toLocaleString()} / ${isMeter ? 'm' : 'pc'}</div>
                    </div>

                    <div class="flex items-center gap-0.5 shrink-0 bg-slate-50 border border-slate-200 rounded-md p-0.5">
                        <button onclick="addDecQty(${item.id}, -${stepUp})" class="w-5 h-5 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors text-[10px]"><i class="fa-solid fa-minus"></i></button>
                        <input type="number" step="${isMeter ? 0.5 : 1}" min="0.01" max="${item.maxStock}" value="${item.qty}"
                               onchange="updateQty(${item.id}, this.value)"
                               aria-label="Quantity for ${Atelier.escapeHtml(item.name)}"
                               class="w-8 text-center bg-transparent border-0 text-xs font-bold text-slate-800 focus:outline-none focus:ring-0 cell-num">
                        <button onclick="addDecQty(${item.id}, ${stepUp})" class="w-5 h-5 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors text-[10px]"><i class="fa-solid fa-plus"></i></button>
                    </div>

                    <div class="w-16 text-right shrink-0">
                        <div class="font-bold text-slate-900 text-xs cell-num">Rs ${total.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2})}</div>
                    </div>

                    <button onclick="removeFromCart(${item.id})" class="btn-cs-icon danger shrink-0 !w-5 !h-5" aria-label="Remove ${Atelier.escapeHtml(item.name)}">
                        <i class="fa-solid fa-xmark text-[8px]"></i>
                    </button>
                </li>`;
        }).join('');

        renderedCartIds = new Set(cart.map(i => String(i.id)));
        updateTotals();
    }

    window.updateTotals = function() {
        let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        let items = cart.length;
        let meters = cart.filter(i => i.unit === 'meter').reduce((sum, i) => sum + i.qty, 0);
        
        let discount = parseFloat(document.getElementById('cart-discount').value) || 0;
        if (discount > subtotal) {
            discount = subtotal;
            document.getElementById('cart-discount').value = discount;
        }
        let total = subtotal - discount;

        document.getElementById('cart-qty-summary').innerText = `${items} / ${meters.toFixed(2)}m`;
        document.getElementById('cart-subtotal').innerText = 'Rs ' + subtotal.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
        document.getElementById('cart-total').innerText = 'Rs ' + total.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
    };

    // SPA Checkout Transition
    const cSelect = document.getElementById('pos-customer');

    cSelect.addEventListener('change', function() {
        let warn = document.getElementById('customer-due-warning');
        const selected = this.options[this.selectedIndex];
        if (!selected) { warn.classList.add('hidden'); return; }
        let due = selected.getAttribute('data-due');
        if (parseFloat(due) > 0) {
            document.getElementById('customer-due-amt').innerText = 'Rs ' + parseFloat(due).toLocaleString();
            warn.classList.remove('hidden');
        } else {
            warn.classList.add('hidden');
        }
    });

    window.goToCheckout = function() {
        if(cart.length === 0) return;
        
        let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        let discount = parseFloat(document.getElementById('cart-discount').value) || 0;
        let total = subtotal - discount;

        const summaryList = document.getElementById('checkout-summary-list');
        summaryList.innerHTML = cart.map((item, i) => `
            <div class="fade-up flex justify-between items-center gap-2 bg-white p-2 border border-slate-200 rounded-lg hover:border-indigo-200 transition-colors" style="animation-delay:${Math.min(i * 40, 300)}ms">
                <div class="min-w-0">
                    <div class="font-semibold text-slate-800 text-xs truncate">${Atelier.escapeHtml(item.name)}</div>
                    <div class="text-[10px] text-slate-500 mt-0.5 cell-num flex items-center gap-1">
                        <span>${item.qty} ${item.unit === 'meter' ? 'm' : 'pc'}</span>
                        <span class="text-slate-300">×</span>
                        <span>Rs ${item.price.toLocaleString()}</span>
                    </div>
                </div>
                <div class="font-bold text-slate-900 shrink-0 text-xs cell-num">Rs ${(item.price * item.qty).toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2})}</div>
            </div>
        `).join('');

        const meters = cart.filter(i => i.unit === 'meter').reduce((sum, i) => sum + i.qty, 0);
        document.getElementById('checkout-item-count').innerText = `${cart.length} ${cart.length === 1 ? 'line' : 'lines'}` + (meters > 0 ? ` · ${meters.toFixed(2)} m` : '');

        document.getElementById('checkout-subtotal').innerText = 'Rs ' + subtotal.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
        document.getElementById('checkout-discount').innerText = '-Rs ' + discount.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
        document.getElementById('checkout-total').innerText = 'Rs ' + total.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
        
        document.getElementById('paid-amount').value = total;
        cSelect.dispatchEvent(new Event('change')); 
        calculateChange();

        goToStep(2);

        setTimeout(() => {
            const pa = document.getElementById('paid-amount');
            pa.focus();
            pa.select();
        }, 460);
    };

    window.backToCart = function() { goToStep(1); };

    const stepPanels = {
        1: document.getElementById('step-1-pos'),
        2: document.getElementById('step-2-checkout'),
        3: document.getElementById('step-3-success'),
    };

    let currentStep = 1;

    window.goToStep = function (step) {
        currentStep = step;
        Object.entries(stepPanels).forEach(([n, el]) => {
            if (!el) return;
            const num = Number(n);
            el.classList.remove('is-active', 'is-next', 'is-previous');
            el.classList.add(num === step ? 'is-active' : (num < step ? 'is-previous' : 'is-next'));
        });

        document.querySelectorAll('.pos-stepper-step').forEach(el => {
            const num = Number(el.dataset.step);
            el.classList.toggle('is-active', num === step);
            el.classList.toggle('is-done', num < step);
        });
    };

    document.querySelectorAll('.pay-method').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.pay-method').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('selected-method').value = this.dataset.method;
        });
    });

    document.getElementById('quick-tender')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-tender]');
        if (!btn) return;
        const input = document.getElementById('paid-amount');

        if (btn.dataset.tender === 'clear') {
            input.value = '';
        } else {
            const current = parseFloat(input.value) || 0;
            input.value = current + Number(btn.dataset.tender);
        }
        calculateChange();
        input.focus();
    });

    window.setFullPayment = function() {
        let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        let discount = parseFloat(document.getElementById('cart-discount').value) || 0;
        document.getElementById('paid-amount').value = subtotal - discount;
        calculateChange();
    };

    window.calculateChange = function() {
        let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        let discount = parseFloat(document.getElementById('cart-discount').value) || 0;
        let total = subtotal - discount;
        let paid = parseFloat(document.getElementById('paid-amount').value) || 0;
        
        const changeLabel = document.getElementById('change-label');
        const changeAmt = document.getElementById('change-amount');
        const hint = document.getElementById('change-hint');

        if (paid >= total) {
            changeLabel.innerText = 'Change Return';
            changeAmt.innerText = 'Rs ' + (paid - total).toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
            changeAmt.className = 'text-2xl font-bold tracking-tight text-emerald-600 cell-num mt-1';
            if (hint) {
                hint.textContent = paid > total ? 'Hand this back to the customer' : 'Exact amount — no change due';
                hint.className = 'text-[10px] text-slate-400 mt-1.5';
            }
        } else {
            changeLabel.innerText = 'Balance Due';
            changeAmt.innerText = 'Rs ' + (total - paid).toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
            changeAmt.className = 'text-2xl font-bold tracking-tight text-red-600 cell-num mt-1';
            if (hint) {
                hint.textContent = 'Will be added to the customer ledger';
                hint.className = 'text-[10px] text-red-500 mt-1.5 font-medium';
            }
        }
    };

    window.submitCheckout = function() {
        const btn = document.getElementById('btn-submit-order');
        const originalLabel = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        let discount = parseFloat(document.getElementById('cart-discount').value) || 0;
        let total = subtotal - discount;
        let paid = parseFloat(document.getElementById('paid-amount').value) || 0;
        let method = document.getElementById('selected-method').value;
        let customer_id = document.getElementById('pos-customer').value;

        if (!customer_id) {
            toast('Select a customer before completing the sale.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalLabel;
            return;
        }

        let actualPaid = paid > total ? total : paid;
        const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

        const payload = {
            cs_customer_id: customer_id,
            subtotal: round2(subtotal),
            discount: round2(discount),
            total_amount: round2(total),
            paid_amount: round2(actualPaid),
            payment_method: method,
            items: cart.map(i => ({
                cs_product_id: i.id,
                quantity: round2(i.qty),
                unit_price: round2(i.price),
            }))
        };

        fetch('{{ route('cloth-store.checkout.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showSuccess(data.order, round2(total), round2(actualPaid), paid > total ? round2(paid - total) : 0);
            } else {
                toast(data.message || 'Error processing checkout.', 'error');
                btn.disabled = false;
                btn.innerHTML = originalLabel;
            }
        })
        .catch(err => {
            toast('Network error during checkout.', 'error');
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = originalLabel;
        });
    };

    let lastOrder = null;
    let lastChange = 0;

    function showSuccess(order, total, paidAmount, change) {
        lastOrder = order;
        lastChange = change;

        const money = (n) => 'Rs ' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        const cSelect = document.getElementById('pos-customer');
        const selected = cSelect.options[cSelect.selectedIndex];

        document.getElementById('success-invoice').textContent = order.invoice_number;
        document.getElementById('success-total').textContent = money(total);
        document.getElementById('success-paid').textContent = money(paidAmount);
        document.getElementById('success-method').textContent = document.getElementById('selected-method').value;
        document.getElementById('success-customer').textContent = order.customer?.name || (selected ? selected.text.split('(')[0].trim() : 'Walk-in Customer');

        document.getElementById('success-items').innerHTML = (order.items || []).map(i => `
            <div class="flex justify-between items-center gap-2 p-2.5 rounded-md border border-slate-200 bg-white">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-slate-800 truncate">${Atelier.escapeHtml(i.product?.name || 'Item')}</div>
                    <div class="text-[10px] text-slate-500 cell-num mt-0.5 flex items-center gap-1">
                        <span>${Number(i.quantity)} ${i.product?.unit === 'meter' ? 'm' : 'pc'}</span>
                        <span class="text-slate-300">×</span>
                        <span>${money(i.unit_price)}</span>
                    </div>
                </div>
                <div class="text-xs font-bold text-slate-900 shrink-0 cell-num">${money(i.total)}</div>
            </div>
        `).join('');

        const owing = Math.max(0, total - paidAmount);
        const balanceEl = document.getElementById('success-balance');
        const labelEl = document.getElementById('success-balance-label');
        const cardEl = document.getElementById('success-balance-card');

        if (owing > 0) {
            labelEl.textContent = 'Balance Due';
            labelEl.className = 'text-[10px] font-bold uppercase tracking-widest text-red-700';
            balanceEl.textContent = money(owing);
            balanceEl.className = 'text-2xl font-bold cell-num mt-1 text-red-600';
            cardEl.className = 'p-4 rounded-lg border bg-red-50 border-red-200';
        } else {
            labelEl.textContent = 'Change';
            labelEl.className = 'text-[10px] font-bold uppercase tracking-widest text-slate-500';
            balanceEl.textContent = money(change);
            balanceEl.className = 'text-2xl font-bold cell-num mt-1 text-slate-900';
            cardEl.className = 'p-4 rounded-lg border bg-slate-50 border-slate-200';
        }

        goToStep(3);
        Atelier.playChime?.();
        toast('Sale completed successfully!', 'success');
    }

    window.showLastReceipt = function () {
        if (lastOrder) showReceipt(lastOrder, lastChange);
    };

    window.startNewSale = function () {
        cart = [];
        renderedCartIds = new Set();
        lastOrder = null;
        lastChange = 0;

        document.getElementById('cart-discount').value = 0;
        document.getElementById('paid-amount').value = '';
        document.getElementById('pos-search').value = '';

        const submitBtn = document.getElementById('btn-submit-order');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Complete Sale';

        renderCart();
        goToStep(1);
        fetchProducts();

        setTimeout(() => document.getElementById('pos-search').focus(), 300);
    };

    document.addEventListener('keydown', (e) => {
        if (currentStep !== 3) return;
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === 'n' || e.key === 'N') {
            e.preventDefault();
            startNewSale();
        }
    }, { signal: Atelier.pageSignal() });

    