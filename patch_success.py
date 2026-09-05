import re

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'r', encoding='utf-8') as f:
    text = f.read()

show_success_new = '''function showSuccess(order, total, paidAmount, change) {
        lastOrder = order;
        lastChange = change;

        const money = (n) => 'Rs ' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        const cSelect = document.getElementById('pos-customer');
        const selected = cSelect.options[cSelect.selectedIndex];

        // Update step 3 text nodes
        const invoiceEl = document.querySelector('#step-3-success p span.cell-num');
        if(invoiceEl) invoiceEl.textContent = order.invoice_number;
        
        const custEl = document.querySelector('#step-3-success p');
        if(custEl && selected) {
            const custName = order.customer?.name || selected.text.split('(')[0].trim();
            custEl.innerHTML = `Invoice <span class="font-bold text-slate-700 cell-num">${order.invoice_number}</span> · ${custName}`;
        }

        const totalEl = document.getElementById('success-total');
        if(totalEl) totalEl.textContent = money(total);
        
        const paidEl = document.getElementById('success-paid');
        if(paidEl) paidEl.textContent = money(paidAmount);
        
        const changeEl = document.getElementById('success-change');
        if(changeEl) changeEl.textContent = money(change);
        
        const itemCountEl = document.getElementById('success-item-count');
        if(itemCountEl) itemCountEl.textContent = `${cart.length} items`;

        const itemsEl = document.getElementById('success-items');
        if(itemsEl) {
            itemsEl.innerHTML = (order.items || []).map(i => `
                <div class="flex justify-between items-center gap-2 p-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50 transition-colors">
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold text-slate-800 truncate">${Atelier.escapeHtml(i.product?.name || 'Item')}</div>
                        <div class="text-[9px] text-slate-500 cell-num mt-0.5 flex items-center gap-1">
                            <span>${Number(i.quantity)} ${i.product?.unit === 'meter' ? 'm' : 'pc'}</span>
                            <span class="text-slate-300">×</span>
                            <span>${money(i.unit_price)}</span>
                        </div>
                    </div>
                    <div class="text-[11px] font-bold text-slate-900 shrink-0 cell-num">${money(i.total)}</div>
                </div>
            `).join('');
        }

        goToStep(3);
        if(typeof Atelier !== 'undefined' && Atelier.playChime) Atelier.playChime();
        if(typeof toast === 'function') toast('Sale completed successfully!', 'success');
    }'''

text = re.sub(r'function showSuccess\(order, total, paidAmount, change\) \{.*?\n    \}', show_success_new, text, flags=re.DOTALL)

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'w', encoding='utf-8') as f:
    f.write(text)

print('showSuccess replaced.')
