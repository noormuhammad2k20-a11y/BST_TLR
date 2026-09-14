window.collectWithPayment = async function(orderId,onSaved) {
  try {
    const payload=await Atelier.api.get(`/orders/${orderId}/receipt`);
    const due=Number(payload.dues.current_order_due);
    const totalDue=Number(payload.dues.customer_total_due);
    const key=crypto.randomUUID();
    window.modals=window.modals || {};
    window.modals['collection-payment']=()=>`
      <form id="collection-payment-form" class="p-6">
        <h3 class="text-lg font-bold text-slate-900 mb-1">Deliver & Receive Payment</h3>
        <p class="text-sm text-slate-500 mb-4">${Atelier.escapeHtml(payload.receipt.customer || '')}</p>
        <div class="bg-slate-50 p-4 rounded-lg space-y-2 text-sm mb-4">
          <div class="flex justify-between"><span>Current Order Due</span><strong>${Atelier.money(due)}</strong></div>
          <div class="flex justify-between text-red-500"><span>Previous Due</span><strong>${Atelier.money(payload.dues.previous_due)}</strong></div>
          <div class="flex justify-between border-t border-slate-200 pt-2"><strong>Customer Total Due</strong><strong>${Atelier.money(totalDue)}</strong></div>
        </div>
        <label class="block text-xs font-semibold text-slate-500 mb-1">Payment received now</label>
        <input id="collection-payment-amount" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 w-full mb-3" type="number" min="0" max="${totalDue}" step="0.01" value="${totalDue.toFixed(2)}" required>
        <select id="collection-payment-method" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 w-full mb-3">@foreach(\App\Models\Payment::METHODS as $method)<option>{{ $method }}</option>@endforeach</select>
        <p class="text-xs text-slate-500 mb-2">Payment clears oldest dues first, including this order. Enter full, partial, or 0 payment.</p>
        <p class="text-sm font-semibold mb-4">Remaining Customer Due: <span id="collection-remaining-due">${Atelier.money(0)}</span></p>
        <p id="collection-payment-error" role="alert" class="text-sm text-red-500 mb-3"></p>
        <div class="flex justify-end gap-2"><button type="button" class="border border-slate-200 px-4 py-2 rounded-lg text-sm" onclick="closeModal()">Cancel</button><button type="submit" class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-semibold">Save & Deliver</button></div>
      </form>`;
    openModal('collection-payment');
    document.getElementById('collection-payment-amount').oninput=function() {
      document.getElementById('collection-remaining-due').textContent=Atelier.money(Math.max(0,Math.round((totalDue-Number(this.value || 0))*100)/100));
    };
    document.getElementById('collection-payment-form').onsubmit=async function(event) {
      event.preventDefault(); const button=this.querySelector('[type=submit]'); Atelier.setBusy(button,true);
      try {
        const result=await Atelier.api.post(`/delivery/orders/${orderId}/collect`,{amount:document.getElementById('collection-payment-amount').value,
          payment_method:document.getElementById('collection-payment-method').value,operation_key:key});
        closeModal();
        if(result.receipt_url) {
          document.getElementById('delivery-receipt-frame')?.remove();
          const frame=document.createElement('iframe'); frame.id='delivery-receipt-frame'; frame.title='Final receipt';
          frame.style.cssText='position:fixed;left:-10000px;top:0;width:320px;height:600px;border:0';
          frame.onload=()=>{if(frame.contentDocument?.getElementById('final-receipt')) {frame.contentWindow.focus();frame.contentWindow.print();}};
          frame.src=result.receipt_url; document.body.appendChild(frame);
        }
        toast('Delivery and payment saved.','success'); await onSaved?.(); Atelier.refreshCounters();
      } catch(error) { const el=document.getElementById('collection-payment-error'); if(el) el.textContent=Object.values(error.errors||{}).flat().join(' ')||error.message; else Atelier.reportError(error,'Delivery saved; refresh the page.'); }
      finally { Atelier.setBusy(button,false); }
    };
  } catch(error) { Atelier.reportError(error,'Could not load payment details'); }
};
