let services = [];
  let deleteContext = { id: '', name: '' };

  function confirmDelete(id, name) {
    deleteContext = { id, name };
    openModal('confirm-delete', { name });
  }

  function executeDelete() {
    services = services.filter(s => s.id !== deleteContext.id);
    renderServices();
    closeModal();
    toast(`Service "${deleteContext.name}" deleted successfully`, 'success');
  }

  function renderServices() {
    const grid = document.getElementById('services-grid');
    if (!grid) return;
    grid.innerHTML = services.map(s => `
      <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all" id="${s.id}">
        <div class="flex justify-between items-start mb-4">
          <div class="w-11 h-11 rounded-lg flex items-center justify-center ${s.iconBg} ${s.iconColor}"><i class="fa-solid ${s.icon} text-lg"></i></div>
          <div class="flex gap-1">
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 flex items-center justify-center transition-colors" onclick="openModal('add-product', ${JSON.stringify(s).replace(/"/g, '&quot;')})"><i class="fa-solid fa-pen text-xs"></i></button>
            <button class="w-8 h-8 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-500 flex items-center justify-center transition-colors" onclick="confirmDelete('${s.id}', '${s.name}')"><i class="fa-solid fa-trash text-xs"></i></button>
          </div>
        </div>
        <div class="text-base font-semibold mb-1 text-slate-900 tracking-tight">${s.name}</div>
        <div class="text-xs text-slate-500 mb-4">${s.delivery} delivery · ${s.orders} orders this year</div>
        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
          <div class="font-bold text-slate-900">₹${s.price.toLocaleString('en-IN')}</div>
          <span class="badge badge-delivered">${s.category}</span>
        </div>
      </div>
    `).join('');
  }

  window = {};
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
              <input type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. Bespoke Suit" value="${isEdit ? data.name : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Price (₹) *</label>
              <input type="number" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="18000" value="${isEdit ? data.price : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category *</label>
              <select class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors">
                ${['Suiting', 'Ethnic', 'Shirting', 'Bottoms'].map(c => `<option ${isEdit && data.category === c ? 'selected' : ''}>${c}</option>`).join('')}
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Delivery Time *</label>
              <input type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="e.g. 10-14 days" value="${isEdit ? data.delivery : ''}">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Icon Class *</label>
              <input type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors" placeholder="fa-vest" value="${isEdit ? data.icon : ''}">
            </div>
            <div class="col-span-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label>
              <textarea class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition-colors min-h-[80px]" placeholder="Detailed description of the service..."></textarea>
            </div>
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
          <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 transition-colors" onclick="closeModal()">Cancel</button>
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-2" onclick="closeModal(); toast('Service saved successfully', 'success')"><i class="fa-solid fa-check text-xs"></i> Save Service</button>
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
