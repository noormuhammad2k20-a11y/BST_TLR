import re

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Replace renderCart
render_cart_new = '''function renderCart() {
        const list = document.getElementById('cart-list');
        const pill = document.getElementById('cart-count-pill');
        if(!list) return;

        if (cart.length === 0) {
            if(pill) pill.innerText = '0';
            const btn = document.getElementById('btn-checkout');
            if(btn) btn.disabled = true;
            list.innerHTML = `<li class="text-center text-slate-400 text-xs py-4">Cart is empty</li>`;
        } else {
            if(pill) pill.innerText = cart.length;
            const btn = document.getElementById('btn-checkout');
            if(btn) btn.disabled = false;

            list.innerHTML = cart.map(item => {
                const total = item.price * item.qty;
                const isMeter = item.unit === 'meter';
                const stepUp = isMeter ? 0.5 : 1;
                return `
                <li class="group flex items-center gap-2 p-2 bg-slate-50 border border-slate-200 rounded-md hover:border-indigo-300 transition-all">
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-800 text-[11px] truncate leading-tight">${Atelier.escapeHtml(item.name)}</div>
                        <div class="text-[9px] text-slate-400 mt-0.5 cell-num">Rs ${item.price.toLocaleString()} / ${isMeter ? 'm' : 'pc'}</div>
                    </div>
                    <div class="flex items-center gap-0.5 shrink-0 bg-white border border-slate-200 rounded-md p-0.5">
                        <button onclick="addDecQty(${item.id}, -${stepUp})" class="w-5 h-5 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors text-[9px]"><i class="fa-solid fa-minus"></i></button>
                        <input type="number" onchange="updateQty(${item.id}, this.value)" value="${item.qty}" class="w-8 text-center bg-transparent border-0 text-[11px] font-bold text-slate-800 focus:outline-none cell-num">
                        <button onclick="addDecQty(${item.id}, ${stepUp})" class="w-5 h-5 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors text-[9px]"><i class="fa-solid fa-plus"></i></button>
                    </div>
                    <div class="w-14 text-right shrink-0">
                        <div class="font-bold text-slate-900 text-[11px] cell-num">Rs ${total.toLocaleString()}</div>
                    </div>
                    <button onclick="removeFromCart(${item.id})" class="ml-1 text-slate-300 hover:text-rose-500 transition-colors"><i class="fa-solid fa-xmark text-[10px]"></i></button>
                </li>`;
            }).join('');
        }
        updateTotals();
    }'''

# Replace updateTotals
update_totals_new = '''window.updateTotals = function() {
        let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const pill = document.getElementById('cart-count-pill');
        if(pill) pill.innerText = cart.length;
        const subtotalEl = document.getElementById('cart-subtotal');
        if(subtotalEl) subtotalEl.innerText = 'Rs ' + subtotal.toLocaleString();
    };'''

# Replace goToCheckout
go_to_checkout_new = '''window.goToCheckout = function() {
        if(cart.length === 0) return;
        if (typeof toggleCart === 'function' && isDockOpen) toggleCart();
        
        let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        let discountInput = document.getElementById('cart-discount');
        let discount = discountInput ? (parseFloat(discountInput.value) || 0) : 0;
        let total = subtotal - discount;

        const summaryList = document.getElementById('checkout-summary-list');
        if(summaryList) {
            summaryList.innerHTML = cart.map((item, i) => `
                <div class="fade-up flex justify-between items-center gap-2 text-[11px] p-1.5 rounded-md bg-white border border-slate-200" style="animation-delay:${Math.min(i * 40, 300)}ms">
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-800 truncate">${Atelier.escapeHtml(item.name)}</div>
                        <div class="text-[9px] text-slate-500 cell-num flex items-center gap-1">
                            <span>${item.qty} ${item.unit === 'meter' ? 'm' : 'pc'}</span>
                            <span class="text-slate-300">×</span>
                            <span>Rs ${item.price.toLocaleString()}</span>
                        </div>
                    </div>
                    <div class="font-bold text-slate-900 shrink-0 cell-num">Rs ${(item.price * item.qty).toLocaleString()}</div>
                </div>
            `).join('');
        }

        const countEl = document.getElementById('checkout-item-count');
        if(countEl) countEl.innerText = `${cart.length} items`;
        
        const subtotalEl = document.getElementById('checkout-subtotal');
        if(subtotalEl) subtotalEl.innerText = 'Rs ' + subtotal.toLocaleString();
        
        const totalEl = document.getElementById('checkout-total');
        if(totalEl) totalEl.innerText = 'Rs ' + total.toLocaleString();

        const paidInput = document.getElementById('paid-amount');
        if(paidInput) paidInput.value = total;
        
        const cSelect = document.getElementById('pos-customer');
        if(cSelect) cSelect.dispatchEvent(new Event('change')); 
        
        calculateChange();
        goToStep(2);
    };'''

text = re.sub(r'function renderCart\(\) \{.*?\n    \}', render_cart_new, text, flags=re.DOTALL)
text = re.sub(r'window\.updateTotals = function\(\) \{.*?\n    \};', update_totals_new, text, flags=re.DOTALL)
text = re.sub(r'window\.goToCheckout = function\(\) \{.*?\n    \};', go_to_checkout_new, text, flags=re.DOTALL)

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'w', encoding='utf-8') as f:
    f.write(text)

print('Functions replaced.')
