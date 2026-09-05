@extends('cloth-store.layouts.app')
@section('title', 'System Settings')
@section('spaPage', 'cloth-store-settings')

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">System Settings</h1>
    <p class="text-sm text-slate-500 mt-0.5">Manage configuration for your physical cloth store operations.</p>
  </div>
  <div>
    <button form="settingsForm" type="submit" class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center gap-2 shadow-sm" id="saveSettingsBtn">
      <i class="fa-solid fa-save text-xs"></i> Save Changes
    </button>
  </div>
</div>

<div class="page flex gap-6">
  <!-- Settings Sidebar -->
  <div class="w-64 shrink-0">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden sticky top-6">
      <nav class="flex flex-col" id="settingsMenu">
        <button onclick="switchTab('store')" class="settings-tab-btn flex items-center gap-3 w-full px-4 py-3 text-left text-sm font-medium border-l-4 border-indigo-500 bg-indigo-50 text-indigo-700" id="tab-store"><i class="fa-solid fa-store w-4 text-center"></i> Store Information</button>
        <button onclick="switchTab('sales')" class="settings-tab-btn flex items-center gap-3 w-full px-4 py-3 text-left text-sm font-medium border-l-4 border-transparent text-slate-600 hover:bg-slate-50" id="tab-sales"><i class="fa-solid fa-file-invoice-dollar w-4 text-center"></i> Sales & Receipts</button>
        <button onclick="switchTab('inventory')" class="settings-tab-btn flex items-center gap-3 w-full px-4 py-3 text-left text-sm font-medium border-l-4 border-transparent text-slate-600 hover:bg-slate-50" id="tab-inventory"><i class="fa-solid fa-boxes-stacked w-4 text-center"></i> Inventory & Rolls</button>
        <button onclick="switchTab('customers')" class="settings-tab-btn flex items-center gap-3 w-full px-4 py-3 text-left text-sm font-medium border-l-4 border-transparent text-slate-600 hover:bg-slate-50" id="tab-customers"><i class="fa-solid fa-users w-4 text-center"></i> Customers & Returns</button>
        <button onclick="switchTab('payments')" class="settings-tab-btn flex items-center gap-3 w-full px-4 py-3 text-left text-sm font-medium border-l-4 border-transparent text-slate-600 hover:bg-slate-50" id="tab-payments"><i class="fa-solid fa-credit-card w-4 text-center"></i> Payments & Notifs</button>
        <button onclick="switchTab('system')" class="settings-tab-btn flex items-center gap-3 w-full px-4 py-3 text-left text-sm font-medium border-l-4 border-transparent text-slate-600 hover:bg-slate-50" id="tab-system"><i class="fa-solid fa-shield-halved w-4 text-center"></i> System & Backup</button>
      </nav>
    </div>
  </div>

  <!-- Settings Content -->
  <div class="flex-1">
    <form id="settingsForm" onsubmit="saveSettings(event)" class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
      
      <!-- Panel: Store Info -->
      <div id="panel-store" class="settings-panel block">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">1. Store Information</h2>
        <div class="grid grid-cols-2 gap-6 mb-6">
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Store Name</label><input type="text" name="store_name" value="{{ $settings['store_name'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:outline-none"></div>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Owner Name</label><input type="text" name="owner_name" value="{{ $settings['owner_name'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:outline-none"></div>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Phone</label><input type="text" name="phone" value="{{ $settings['phone'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:outline-none"></div>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">WhatsApp</label><input type="text" name="whatsapp" value="{{ $settings['whatsapp'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:outline-none"></div>
          <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Address</label><input type="text" name="address" value="{{ $settings['address'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:outline-none"></div>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">City</label><input type="text" name="city" value="{{ $settings['city'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:outline-none"></div>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Currency</label><input type="text" name="currency" value="{{ $settings['currency'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:outline-none"></div>
        </div>
      </div>

      <!-- Panel: Sales & Receipts -->
      <div id="panel-sales" class="settings-panel hidden">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">2. Sales Settings</h2>
        <div class="grid grid-cols-3 gap-6 mb-8">
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Default Tax (%)</label><input type="number" name="default_tax" value="{{ $settings['default_tax'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500"></div>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Invoice Prefix</label><input type="text" name="invoice_prefix" value="{{ $settings['invoice_prefix'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500"></div>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Invoice Format</label><input type="text" name="invoice_format" value="{{ $settings['invoice_format'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500"></div>
        </div>

        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">7. Receipt Settings</h2>
        <div class="grid grid-cols-3 gap-4 mb-6">
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Receipt Width</label>
            <select name="receipt_width" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500">
              <option value="80mm" {{ $settings['receipt_width'] == '80mm' ? 'selected' : '' }}>80mm (Standard POS)</option>
              <option value="58mm" {{ $settings['receipt_width'] == '58mm' ? 'selected' : '' }}>58mm (Small POS)</option>
              <option value="A4" {{ $settings['receipt_width'] == 'A4' ? 'selected' : '' }}>A4 Size</option>
            </select>
          </div>
          <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Footer Message</label><input type="text" name="rcpt_footer" value="{{ $settings['rcpt_footer'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500"></div>
        </div>
        <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-lg border border-slate-200">
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="rcpt_show_customer" value="1" {{ $settings['rcpt_show_customer'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Show Customer Info</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="rcpt_show_payment" value="1" {{ $settings['rcpt_show_payment'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Show Payment Method</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="rcpt_show_unit" value="1" {{ $settings['rcpt_show_unit'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Show Unit (m)</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="rcpt_show_discount" value="1" {{ $settings['rcpt_show_discount'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Show Discount</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="rcpt_auto_print" value="1" {{ $settings['rcpt_auto_print'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Auto-Print on Checkout</label>
        </div>
      </div>

      <!-- Panel: Inventory & Rolls -->
      <div id="panel-inventory" class="settings-panel hidden">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">3. Fabric & Inventory Settings</h2>
        <div class="grid grid-cols-2 gap-6 mb-8">
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Default Fabric Unit</label>
            <select name="default_fabric_unit" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500">
              <option value="Meter" {{ $settings['default_fabric_unit'] == 'Meter' ? 'selected' : '' }}>Meter</option>
              <option value="Piece" {{ $settings['default_fabric_unit'] == 'Piece' ? 'selected' : '' }}>Piece</option>
              <option value="Yard" {{ $settings['default_fabric_unit'] == 'Yard' ? 'selected' : '' }}>Yard</option>
            </select>
          </div>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Decimal Precision</label>
            <select name="decimal_precision" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500">
              <option value="1" {{ $settings['decimal_precision'] == '1' ? 'selected' : '' }}>1 Decimal (e.g. 1.5m)</option>
              <option value="2" {{ $settings['decimal_precision'] == '2' ? 'selected' : '' }}>2 Decimals (e.g. 1.50m)</option>
            </select>
          </div>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Minimum Measurable Qty (m)</label><input type="number" step="0.01" name="min_measurable_qty" value="{{ $settings['min_measurable_qty'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500"></div>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Stock Warning Threshold</label><input type="number" step="0.5" name="stock_warning_threshold" value="{{ $settings['stock_warning_threshold'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500"></div>
        </div>
        <div class="space-y-3 bg-rose-50 p-4 rounded-lg border border-rose-200 mb-8">
          <label class="flex items-center gap-2 text-sm font-bold text-rose-900"><input type="checkbox" name="prevent_negative_stock" value="1" {{ $settings['prevent_negative_stock'] == '1' ? 'checked' : '' }} class="rounded text-rose-600 focus:ring-rose-500"> Prevent Negative Stock (Block checkout if out of stock)</label>
          <label class="flex items-center gap-2 text-sm font-bold text-rose-900"><input type="checkbox" name="auto_low_stock_alerts" value="1" {{ $settings['auto_low_stock_alerts'] == '1' ? 'checked' : '' }} class="rounded text-rose-600 focus:ring-rose-500"> Show auto low-stock alerts on dashboard</label>
        </div>

        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">4. Roll Management</h2>
        <div class="space-y-4">
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enable_roll_tracking" value="1" {{ $settings['enable_roll_tracking'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Enable Roll Tracking</label>
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Roll Numbering Format</label><input type="text" name="roll_number_format" value="{{ $settings['roll_number_format'] }}" class="w-1/2 px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500"></div>
        </div>
      </div>

      <!-- Panel: Customers & Returns -->
      <div id="panel-customers" class="settings-panel hidden">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">5. Customer Settings</h2>
        <div class="grid grid-cols-2 gap-4 mb-8">
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enable_customer_profiles" value="1" {{ $settings['enable_customer_profiles'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Enable Customer Profiles</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_walk_in" value="1" {{ $settings['allow_walk_in'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Allow Walk-In (No Name) Checkouts</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_customer_credit" value="1" {{ $settings['allow_customer_credit'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Allow Customer Credit / Dues</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enable_loyalty_points" value="1" {{ $settings['enable_loyalty_points'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Enable Loyalty Points</label>
          
          <div class="col-span-2 mt-2"><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Max Customer Credit Allowed (Rs)</label><input type="number" name="max_customer_credit" value="{{ $settings['max_customer_credit'] }}" class="w-1/2 px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500"></div>
        </div>

        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">8. Return Settings</h2>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div><label class="block text-xs font-bold text-slate-700 uppercase mb-1">Return Period (Days)</label><input type="number" name="return_period" value="{{ $settings['return_period'] }}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-indigo-500"></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_partial_returns" value="1" {{ $settings['allow_partial_returns'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Allow Partial Returns</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_exchange" value="1" {{ $settings['allow_exchange'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Allow Exchanges</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="return_restock_auto" value="1" {{ $settings['return_restock_auto'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Restock Inventory Automatically</label>
          <label class="flex items-center gap-2 text-sm text-rose-600 font-bold"><input type="checkbox" name="require_return_approval" value="1" {{ $settings['require_return_approval'] == '1' ? 'checked' : '' }} class="rounded text-rose-600 focus:ring-rose-500"> Require Admin Approval for Returns</label>
        </div>
      </div>

      <!-- Panel: Payments & Notifs -->
      <div id="panel-payments" class="settings-panel hidden">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">6. Payment Methods</h2>
        <div class="grid grid-cols-3 gap-4 mb-8">
          <label class="flex items-center gap-2 text-sm bg-slate-50 p-3 rounded border border-slate-200"><input type="checkbox" name="pm_cash" value="1" {{ $settings['pm_cash'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600"> Cash</label>
          <label class="flex items-center gap-2 text-sm bg-slate-50 p-3 rounded border border-slate-200"><input type="checkbox" name="pm_card" value="1" {{ $settings['pm_card'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600"> Credit/Debit Card</label>
          <label class="flex items-center gap-2 text-sm bg-slate-50 p-3 rounded border border-slate-200"><input type="checkbox" name="pm_easypaisa" value="1" {{ $settings['pm_easypaisa'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600"> EasyPaisa</label>
          <label class="flex items-center gap-2 text-sm bg-slate-50 p-3 rounded border border-slate-200"><input type="checkbox" name="pm_jazzcash" value="1" {{ $settings['pm_jazzcash'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600"> JazzCash</label>
          <label class="flex items-center gap-2 text-sm bg-slate-50 p-3 rounded border border-slate-200"><input type="checkbox" name="pm_bank" value="1" {{ $settings['pm_bank'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600"> Bank Transfer</label>
          <label class="flex items-center gap-2 text-sm bg-slate-50 p-3 rounded border border-slate-200"><input type="checkbox" name="pm_cheque" value="1" {{ $settings['pm_cheque'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600"> Cheque</label>
        </div>

        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">9. System Notifications</h2>
        <div class="space-y-3">
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notif_low_stock" value="1" {{ $settings['notif_low_stock'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Low Stock Alerts</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notif_out_stock" value="1" {{ $settings['notif_out_stock'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Out of Stock Alerts</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notif_customer_due" value="1" {{ $settings['notif_customer_due'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Customer Due / Overdue Alerts</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notif_expense_created" value="1" {{ $settings['notif_expense_created'] == '1' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500"> Notify Admin on Expense Creation</label>
        </div>
      </div>

      <!-- Panel: System & Backup -->
      <div id="panel-system" class="settings-panel hidden">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">11. Appearance</h2>
        <div class="mb-8">
          <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Theme Mode</label>
          <div class="flex gap-4">
             <label class="cursor-pointer">
                <input type="radio" name="theme_mode" value="light" {{ $settings['theme_mode'] == 'light' ? 'checked' : '' }} class="hidden peer">
                <div class="px-4 py-2 border border-slate-200 rounded-lg text-sm peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 font-medium"><i class="fa-solid fa-sun mr-1"></i> Light Mode</div>
             </label>
             <label class="cursor-pointer">
                <input type="radio" name="theme_mode" value="dark" {{ $settings['theme_mode'] == 'dark' ? 'checked' : '' }} class="hidden peer">
                <div class="px-4 py-2 border border-slate-200 rounded-lg text-sm peer-checked:border-indigo-600 peer-checked:bg-slate-900 peer-checked:text-white font-medium"><i class="fa-solid fa-moon mr-1"></i> Dark Mode</div>
             </label>
          </div>
        </div>

        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-200 pb-3 mb-5">12. Backup & Data</h2>
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-5 flex items-center justify-between mb-4">
           <div>
             <h4 class="font-bold text-indigo-900">Database Backup</h4>
             <p class="text-xs text-indigo-700 mt-1">Download a complete backup of the Cloth Store database.</p>
           </div>
           <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm hover:bg-indigo-700" onclick="alert('Backup generation started...')"><i class="fa-solid fa-download"></i> Export Database</button>
        </div>
        <div class="bg-amber-50 border border-amber-100 rounded-lg p-5 flex items-center justify-between mb-4">
           <div>
             <h4 class="font-bold text-amber-900">Restore Backup</h4>
             <p class="text-xs text-amber-700 mt-1">Upload a previous SQL backup to restore data.</p>
           </div>
           <button type="button" class="bg-amber-600 text-white px-4 py-2 rounded text-sm hover:bg-amber-700" onclick="alert('Restore module opening...')"><i class="fa-solid fa-upload"></i> Import Data</button>
        </div>
        <div class="bg-rose-50 border border-rose-100 rounded-lg p-5 flex items-center justify-between">
           <div>
             <h4 class="font-bold text-rose-900">Danger Zone</h4>
             <p class="text-xs text-rose-700 mt-1">Clear all demo data and start fresh.</p>
           </div>
           <button type="button" class="bg-rose-600 text-white px-4 py-2 rounded text-sm hover:bg-rose-700" onclick="confirm('Are you absolutely sure? This will wipe all data.')"><i class="fa-solid fa-trash"></i> Factory Reset</button>
        </div>
      </div>

    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  function switchTab(tabId) {
    // Hide all panels
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.add('hidden'));
    
    // Reset buttons
    document.querySelectorAll('.settings-tab-btn').forEach(btn => {
       btn.classList.remove('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
       btn.classList.add('border-transparent', 'text-slate-600');
    });

    // Show active panel
    document.getElementById('panel-' + tabId).classList.remove('hidden');
    
    // Set active button
    const activeBtn = document.getElementById('tab-' + tabId);
    activeBtn.classList.remove('border-transparent', 'text-slate-600');
    activeBtn.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
  }

  function saveSettings(e) {
    e.preventDefault();
    const btn = document.getElementById('saveSettingsBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Saving...';
    btn.disabled = true;

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    Atelier.fetch(`/cloth-store/settings`, {
      method: 'POST',
      body: JSON.stringify(data)
    }).then(res => res.json()).then(res => {
      if(res.success) {
         // Show success toast
         alert('Settings saved successfully!');
      } else {
         alert('Error saving settings: ' + res.message);
      }
      btn.innerHTML = '<i class="fa-solid fa-save text-xs"></i> Save Changes';
      btn.disabled = false;
    });
  }
</script>
@endpush
