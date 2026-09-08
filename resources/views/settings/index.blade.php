@extends('layouts.app')
@section('spaPage', 'settings')
@section('title', 'Settings')

@push('styles')
<style>
  /* Settings Sidebar Tabs */
  .settings-tab { transition: all 0.2s ease; display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; color: var(--text-muted); }
  .settings-tab:hover { background-color: var(--bg-muted); color: var(--text-strong); }
  .settings-tab.active { background-color: var(--bg-muted); color: var(--text-strong); font-weight: 600; }
  .settings-tab.active i { color: var(--brand); }
  .settings-tab .tab-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--brand); margin-left: auto; opacity: 0; transition: opacity .2s; }
  .settings-tab.dirty .tab-dot { opacity: 1; }

  /* Premium Toggle Switch */
  .toggle { width: 36px; height: 20px; background: var(--border); border-radius: 11px; position: relative; cursor: pointer; transition: background .2s; flex-shrink: 0; }
  .toggle::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
  .toggle.on { background: var(--brand); } .toggle.on::after { transform: translateX(16px); }
  .toggle.disabled { opacity: .45; pointer-events: none; }

  .pro-input { transition: all 0.2s; }
  .pro-input:focus { border-color: var(--brand); box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand) 18%, transparent); outline: none; }

  .wa-bubble { background-color: #DCF8C6; border-radius: 10px 10px 10px 0; padding: 10px 12px; font-size: 13px; color: #111B21; position: relative; max-width: 95%; margin-left: 10px; box-shadow: 0 1px 0.5px rgba(0,0,0,.13); white-space: pre-wrap; word-break: break-word; }
  .wa-bubble::after { content: ''; position: absolute; top: 0; left: -8px; width: 0; height: 0; border-right: 8px solid #DCF8C6; border-top: 8px solid transparent; border-bottom: 8px solid transparent; }

  .set-card { background: var(--bg-subtle); border: 1px solid var(--border-soft); border-radius: 12px; padding: 20px; }
  .set-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px; background: var(--bg-surface); border: 1px solid var(--border-soft); border-radius: 8px; }
  .set-legend { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .1em; margin-bottom: 16px; }
  .set-label { display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
  .set-field { width: 100%; padding: 8px 12px; background: var(--bg-surface); border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-weight: 500; color: var(--text-strong); }
  .set-hint { font-size: 11px; color: var(--text-faint); margin-top: 4px; }

  .swatch { width: 34px; height: 34px; border-radius: 9px; cursor: pointer; border: 3px solid transparent; transition: transform .15s; }
  .swatch:hover { transform: scale(1.1); }
  .swatch.active { border-color: var(--text-strong); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 25%, transparent); }

  .drop-zone { border: 2px dashed var(--border); border-radius: 12px; padding: 24px; text-align: center; transition: all .2s; cursor: pointer; }
  .drop-zone:hover, .drop-zone.dragging { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 6%, transparent); }

  @media print {
    body * { visibility: hidden; }
    #print-receipt, #print-receipt * { visibility: visible; }
    #print-receipt { position: absolute; left: 0; top: 0; width: 100%; }
  }
</style>
@endpush

@section('content')
<div class="flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Settings</h1>
    <p class="text-sm text-slate-500 mt-0.5">Configure your atelier workspace</p>
  </div>
  <div class="flex items-center gap-3">
    <span id="dirty-flag" class="text-xs font-medium text-amber-600 hidden"><i class="fa-solid fa-circle-exclamation text-[10px]"></i> Unsaved changes</span>
    <button class="bg-slate-900 text-white px-3.5 py-2 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="saveSettings(this)"><i class="fa-solid fa-check text-[10px]"></i> Save Changes</button>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
  <!-- Settings Sidebar -->
  <div class="bg-white p-2 rounded-xl border border-slate-200 shadow-sm h-fit sticky top-24" id="settings-tabs"></div>

  <!-- Settings Content Panel -->
  <div class="lg:col-span-3 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" id="settings-panel"></div>
</div>
@endsection

@push('scripts')
<script>
  /* ============================================================
     SERVER STATE
     Everything below is resolved from the database — there are no
     placeholder values on this page.
     ============================================================ */
  var DB_SETTINGS       = @json($settings);
  var MEASUREMENT_FIELDS = @json($measurementFields);
  var TIMEZONES         = @json($timezones);
  var DATA_COUNTS       = @json($dataCounts);
  var BACKUP_TYPES      = @json($backupTypes);
  var TEMPLATE_VARS     = @json($templateVariables);
  var DEFAULT_TEMPLATES = @json($defaultTemplates);
  var PREVIEW_VARS      = @json($previewVariables);
  var HAS_PREVIEW_ORDER = @json((bool) $previewOrder);
  var DEFAULT_SMS_TEMPLATES = @json($defaultSmsTemplates);

  var SETTINGS_ROUTES = {
    update:        @json(route('settings.update')),
    upload:        @json(route('settings.upload')),
    removeUpload:  @json(route('settings.upload.remove')),
    reset:         @json(route('settings.reset')),
    backupCsv:     @json(route('settings.backup')),
    backupJson:    @json(route('settings.backup.json')),
    inspect:       @json(route('settings.backup.inspect')),
    restore:       @json(route('settings.backup.restore')),
    purge:         @json(route('settings.purge')),
    clearNotifs:   @json(route('settings.clear-notifications')),
    testWhatsapp:  @json(route('settings.whatsapp.test')),
    testTemplate:  @json(route('settings.whatsapp.test-template')),
    sendTest:      @json(route('settings.whatsapp.send-test')),
    reportsExport: @json(route('reports.export')),
    testSms:       @json(route('settings.sms.test')),
    sendTestSms:   @json(route('settings.sms.send-test')),
    smsBalance:    @json(route('settings.sms.balance')),
  };

  /* The last state the server confirmed. Previews mutate DB_SETTINGS so a
     choice can be judged before saving; discarding restores from here. */
  var SAVED_SETTINGS = JSON.parse(JSON.stringify(DB_SETTINGS));

  /* Working copy of the templates; edits here are saved with the panel. */
  var messageTemplates = JSON.parse(JSON.stringify(DB_SETTINGS.message_templates || []));

  /* Working copy of SMS templates. */
  var smsTemplates = JSON.parse(JSON.stringify(DB_SETTINGS.sms_templates || []));

  /* Panels, each bound to the settings group its Reset button restores. */
  var PANELS = [
    { name: 'General',           icon: 'fa-solid fa-sliders',              group: 'general',      render: panelGeneral },
    { name: 'Business Profile',  icon: 'fa-solid fa-store',                group: 'business',     render: panelBusinessProfile },
    { name: 'Invoice & Billing', icon: 'fa-solid fa-file-invoice-dollar',  group: 'invoice',      render: panelInvoice },
    { name: 'Thermal Printer',   icon: 'fa-solid fa-print',                group: 'printer',      render: panelThermalPrinter },
    { name: 'Measurements',      icon: 'fa-solid fa-ruler-combined',       group: 'measurements', render: panelMeasurements },
    { name: 'Notifications',     icon: 'fa-solid fa-bell',                 group: 'notifications', render: panelNotifications },
    { name: 'Backup & Data',     icon: 'fa-solid fa-database',             group: null,           render: panelBackup },
    { name: 'Theme & Display',   icon: 'fa-solid fa-palette',              group: 'theme',        render: panelTheme },
    { name: 'WhatsApp & Alerts', icon: 'fa-brands fa-whatsapp',            group: 'whatsapp',     render: panelWhatsApp },
    { name: 'WhatsApp Business API', icon: 'fa-brands fa-whatsapp', group: 'meta', render: panelMeta },
    { name: 'SMS Settings',      icon: 'fa-solid fa-comment-sms',           group: 'sms',          render: panelSms },
  ];

  var currentPanel = 'General';
  var dirtyPanels  = new Set();

  /* --------------------------- Accessors --------------------------- */
  var val   = (key, fallback = '') => (DB_SETTINGS[key] ?? fallback);
  var num   = (key, fallback = 0)  => Number(DB_SETTINGS[key] ?? fallback);
  var isOn  = key => DB_SETTINGS[key] === '1' || DB_SETTINGS[key] === 1 || DB_SETTINGS[key] === true;
  var esc   = s => Atelier.escapeHtml(s ?? '');
  var panelFor = name => PANELS.find(p => p.name === name);

  /* Panels holding values the DOM scan cannot express on its own. */
  var STRUCTURED = {
    'General':           () => ({ business_hours: collectBusinessHours() }),
    'Measurements':      () => ({ measurement_required: collectRequiredFields() }),
    'WhatsApp & Alerts': () => ({ message_templates: messageTemplates }),
    'SMS Settings':      () => ({ sms_templates: smsTemplates }),
    'WhatsApp Business API': () => ({ meta_templates: collectMetaMappings() }),
  };

  /* ============================================================
     SAVE / RESET
     ============================================================ */

  /** Reads every [data-setting] control in the open panel. */
  function collectPanel() {
    const payload = {};

    document.querySelectorAll('#settings-panel [data-setting]').forEach(el => {
      if (el.disabled) return;
      const key = el.dataset.setting;

      if (el.classList.contains('toggle')) {
        payload[key] = el.classList.contains('on') ? 1 : 0;
      } else if (el.type === 'checkbox') {
        payload[key] = el.checked ? 1 : 0;
      } else if (el.type === 'radio') {
        if (el.checked) payload[key] = el.value;
      } else {
        payload[key] = el.value;
      }
    });

    const structured = STRUCTURED[currentPanel];
    if (structured) Object.assign(payload, structured());

    return payload;
  }

  async function saveSettings(btn = null) {
    const payload = collectPanel();

    if (!Object.keys(payload).length) {
      toast('Nothing to save on this panel', 'info');
      return;
    }

    if (btn) Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.put(SETTINGS_ROUTES.update, payload);
      Object.assign(DB_SETTINGS, res.settings);
      document.querySelectorAll('#settings-panel input[type=password][data-setting]').forEach(input => { input.value = DB_SETTINGS[input.dataset.setting] || ''; });
      SAVED_SETTINGS = JSON.parse(JSON.stringify(DB_SETTINGS));

      markClean(currentPanel);
      syncRuntime();
      toast(res.message, 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not save the settings');
    } finally {
      if (btn) Atelier.setBusy(btn, false);
    }
  }

  /** Restores the visible panel's whole group to its shipped defaults. */
  function resetPanel() {
    const panel = panelFor(currentPanel);

    if (!panel?.group) {
      toast('This panel has no stored settings to reset', 'info');
      return;
    }

    Atelier.confirm({
      variant: 'delete',
      title: `Reset ${currentPanel} to defaults?`,
      message: 'Every field on this panel returns to its original value. Other panels are untouched.',
      confirmLabel: 'Reset Panel',
      onConfirm: async () => {
        const res = await Atelier.api.post(SETTINGS_ROUTES.reset, { group: panel.group });
        Object.assign(DB_SETTINGS, res.settings);
        SAVED_SETTINGS = JSON.parse(JSON.stringify(DB_SETTINGS));

        // Templates live outside the DOM, so refresh the working copy too.
        messageTemplates = JSON.parse(JSON.stringify(DB_SETTINGS.message_templates || []));
        smsTemplates = JSON.parse(JSON.stringify(DB_SETTINGS.sms_templates || []));

        markClean(currentPanel);
        switchSettingsPanel(currentPanel);
        syncRuntime();
        toast(res.message, 'success');
      },
    });
  }

  /* ============================================================
     LIVE APPLICATION
     Appearance and formatting choices take effect across the whole
     app the moment they are saved, with no reload.
     ============================================================ */
  function syncRuntime() {
    Atelier.refreshDisplay({
      colorMode:     val('color_mode', 'light'),
      primaryColor:  val('primary_color', '#4F46E5'),
      sidebarTheme:  val('sidebar_theme', 'white'),
      compactTables: isOn('compact_tables'),
      rowsPerPage:   num('rows_per_page', 10),
      dateFormat:    val('date_format', 'DD/MM/YYYY'),
      currency:      val('currency', '₹'),
      notificationPrefs: {
        browser:       isOn('browser_notifications'),
        sound:         isOn('sound_alerts'),
        paymentToasts: isOn('payment_toasts'),
      },
    });

    // Shop name appears in the sidebar and header on every page.
    document.querySelectorAll('.sb-name, [data-shop-name]').forEach(el => {
      el.textContent = val('store_name') || 'Atelier';
    });
  }

  /** Previews a change without saving it, so the choice can be judged. */
  function preview(key, value) {
    DB_SETTINGS[key] = value;
    syncRuntime();
    markDirty();
  }

  /* ============================================================
     DIRTY TRACKING
     ============================================================ */
  function markDirty() {
    dirtyPanels.add(currentPanel);
    const status = document.getElementById('meta-status');
    if (status) status.textContent = 'Configuration changed. Save and verify again.';
    renderTabs();
    document.getElementById('dirty-flag')?.classList.remove('hidden');
  }

  function markClean(name) {
    dirtyPanels.delete(name);
    renderTabs();
    if (!dirtyPanels.size) document.getElementById('dirty-flag')?.classList.add('hidden');
  }

  /* ============================================================
     NAVIGATION
     ============================================================ */
  function renderTabs() {
    const host = document.getElementById('settings-tabs');
    if (!host) return;

    host.innerHTML = PANELS.map(p => `
      <div class="settings-tab ${p.name === currentPanel ? 'active' : ''} ${dirtyPanels.has(p.name) ? 'dirty' : ''}"
           data-name="${esc(p.name)}" onclick="switchSettingsPanel('${p.name.replace(/'/g, "\\'")}')">
        <i class="${p.icon} w-4 text-center"></i> ${esc(p.name)}
        <span class="tab-dot" title="Unsaved changes"></span>
      </div>
    `).join('');
  }

  function switchSettingsPanel(name) {
    const panel = panelFor(name);
    if (!panel) return;

    const leave = () => {
      currentPanel = name;
      document.getElementById('settings-panel').innerHTML = panel.render();
      renderTabs();
      bindPanel();
      afterRender(name);
    };

    // Warn before abandoning edits rather than losing them silently.
    if (dirtyPanels.has(currentPanel) && currentPanel !== name) {
      Atelier.confirm({
        variant: 'info',
        title: 'Leave without saving?',
        message: `You have unsaved changes on ${currentPanel}. They will be discarded.`,
        confirmLabel: 'Discard changes',
        onConfirm: () => {
          // Roll back to the last confirmed server state, including any theme
          // choices that were only being previewed.
          DB_SETTINGS = JSON.parse(JSON.stringify(SAVED_SETTINGS));
          messageTemplates = JSON.parse(JSON.stringify(DB_SETTINGS.message_templates || []));
          smsTemplates = JSON.parse(JSON.stringify(DB_SETTINGS.sms_templates || []));

          markClean(currentPanel);
          syncRuntime();
          leave();
        },
      });
      return;
    }

    leave();
  }

  /** Any edit inside the panel flags it as unsaved. */
  function bindPanel() {
    const host = document.getElementById('settings-panel');
    if (!host) return;

    const trackSetting = event => { if (event.target.closest('[data-setting], [data-meta-event]')) markDirty(); };
    host.addEventListener('input', trackSetting);
    host.addEventListener('change', trackSetting);
    host.querySelectorAll('.toggle').forEach(t => t.addEventListener('click', markDirty));
  }

  function afterRender(name) {
    if (name === 'WhatsApp & Alerts') renderTemplates();
    if (name === 'Thermal Printer') updateReceiptPreview();
    if (name === 'SMS Settings')    renderSmsTemplates();
  }

  /* Structured collectors */
  function collectBusinessHours() {
    const hours = {};
    document.querySelectorAll('[data-day]').forEach(row => {
      hours[row.dataset.day] = {
        open: row.querySelector('.toggle').classList.contains('on'),
        from: row.querySelector('[data-hour="from"]').value,
        to:   row.querySelector('[data-hour="to"]').value,
      };
    });
    return hours;
  }

  function collectRequiredFields() {
    return [...document.querySelectorAll('[data-required-field]:checked')].map(el => el.value);
  }

  /** Consistent footer for every panel: reset alongside save. */
  function panelFooter() {
    const group = panelFor(currentPanel)?.group;

    return `
      <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-200">
        ${group ? `
          <button onclick="resetPanel()" class="text-xs font-medium text-slate-500 hover:text-red-600 transition-colors flex items-center gap-2">
            <i class="fa-solid fa-rotate-left text-[10px]"></i> Restore defaults for this panel
          </button>` : '<span></span>'}
        <button onclick="saveSettings(this)" class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm">
          <i class="fa-solid fa-check text-xs"></i> Save
        </button>
      </div>`;
  }

  /* ============================================================
     PANEL 1 — GENERAL
     ============================================================ */
  function panelGeneral() {
    const select = (key, options, onchange = '') => `
      <select data-setting="${key}" class="set-field pro-input" ${onchange}>
        ${options.map(o => {
          const [value, label] = Array.isArray(o) ? o : [o, o];
          return `<option value="${esc(value)}" ${val(key) === value ? 'selected' : ''}>${esc(label)}</option>`;
        }).join('')}
      </select>`;

    return `
      <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">General Settings</h3>
        <p class="text-sm text-slate-500 mb-6">Language, formatting and the rules that drive your order workflow.</p>

        <div class="set-card mb-6">
          <h4 class="set-legend">App Preferences</h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="set-label">App Language</label>
              ${select('app_language', ['English', 'Urdu', 'Sindhi'])}
              <p class="set-hint">Urdu and Sindhi switch the interface to right-to-left.</p>
            </div>
            <div>
              <label class="set-label">Currency Format</label>
              ${select('currency', ['₹', 'Rs.', 'PKR', '₨', '$', '€', '£', '﷼'], 'onchange="preview(\'currency\', this.value)"')}
              <p class="set-hint">Preview: <span class="font-semibold text-slate-700">${esc(val('currency'))}8,000</span></p>
            </div>
            <div>
              <label class="set-label">Date Format</label>
              ${select('date_format', ['DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD', 'D MMM YYYY'], 'onchange="preview(\'date_format\', this.value); switchSettingsPanel(\'General\')"')}
              <p class="set-hint">Today reads as <span class="font-semibold text-slate-700">${esc(Atelier.formatDate(new Date()))}</span></p>
            </div>
            <div>
              <label class="set-label">Timezone</label>
              ${select('timezone', TIMEZONES)}
              <p class="set-hint">Every date and deadline is calculated in this zone.</p>
            </div>
          </div>
        </div>

        <div class="set-card mb-6">
          <div class="flex items-start justify-between mb-4">
            <h4 class="set-legend" style="margin-bottom:0">Business Hours</h4>
            <span class="badge ${@json(\App\Services\Settings::isOpenNow()) ? 'badge-delivered' : 'badge-overdue'}">${@json(\App\Services\Settings::isOpenNow()) ? 'Open now' : 'Closed now'}</span>
          </div>
          <div class="space-y-2">
            ${['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'].map(day => {
              const h = (DB_SETTINGS.business_hours || {})[day] || { open: true, from: '10:00', to: '20:00' };
              return `
              <div class="flex items-center gap-4 bg-white p-2 rounded-lg border border-slate-100" data-day="${day}">
                <span class="text-sm font-semibold w-24 text-slate-700">${day}</span>
                <div class="toggle ${h.open ? 'on' : ''}" onclick="this.classList.toggle('on'); toggleDayInputs(this)"></div>
                <input type="time" data-hour="from" value="${esc(h.from)}" class="pro-input px-2 py-1 border border-slate-200 rounded text-xs font-medium bg-white" ${h.open ? '' : 'disabled'}>
                <span class="text-xs text-slate-400">to</span>
                <input type="time" data-hour="to" value="${esc(h.to)}" class="pro-input px-2 py-1 border border-slate-200 rounded text-xs font-medium bg-white" ${h.open ? '' : 'disabled'}>
              </div>`;
            }).join('')}
          </div>
          <div class="set-row mt-3">
            <div>
              <div class="text-sm font-semibold text-slate-800">Warn when taking orders out of hours</div>
              <div class="text-xs text-slate-500">Shows a reminder if an order is created while the shop is closed</div>
            </div>
            <div data-setting="enforce_business_hours" class="toggle ${isOn('enforce_business_hours') ? 'on' : ''}" onclick="this.classList.toggle('on')"></div>
          </div>
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">Order Workflow Rules</h4>
          <div class="space-y-3">
            <div class="set-row">
              <div>
                <div class="text-sm font-semibold text-slate-800">Move orders on automatically</div>
                <div class="text-xs text-slate-500">Advance an order once it has waited out the delay for its stage</div>
              </div>
              <div data-setting="auto_status_enabled" class="toggle ${isOn('auto_status_enabled') ? 'on' : ''}" onclick="this.classList.toggle('on')"></div>
            </div>

            <div>
              <label class="set-label">Count delays in</label>
              <select data-setting="auto_status_unit" class="set-field pro-input">
                <option value="hours" ${val('auto_status_unit') !== 'minutes' ? 'selected' : ''}>Hours</option>
                <option value="minutes" ${val('auto_status_unit') === 'minutes' ? 'selected' : ''}>Minutes</option>
              </select>
              <p class="set-hint">Applies to every delay below. Minutes is useful for a fast shop &mdash; or for watching the workflow run end to end while you test it.</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="set-label">Pending &rarr; In Progress</label>
                <input type="number" min="0" max="10080" data-setting="auto_status_pending_hours" value="${esc(val('auto_status_pending_hours'))}" class="set-field pro-input">
              </div>
              <div>
                <label class="set-label">In Progress &rarr; Ready for Verification</label>
                <input type="number" min="0" max="10080" data-setting="auto_status_progress_delay" value="${esc(val('auto_status_progress_delay'))}" class="set-field pro-input">
              </div>
              <div>
                <label class="set-label">Ready for Verification &rarr; Ready</label>
                <input type="number" min="0" max="10080" data-setting="auto_status_verify_delay" value="${esc(val('auto_status_verify_delay'))}" class="set-field pro-input">
              </div>
              <div>
                <label class="set-label">Ready &rarr; Delivered</label>
                <input type="number" min="0" max="10080" data-setting="auto_status_ready_delay" value="${esc(val('auto_status_ready_delay'))}" class="set-field pro-input">
              </div>
            </div>
            <p class="set-hint"><b>0 switches that step off.</b> Only the first step is on by default, and that is deliberate: a timer that moves a garment to &ldquo;Ready&rdquo; is telling your customer it is on the shelf whether anyone has touched it or not. Switch the later steps on only if that matches how your shop really works.</p>

            <div>
              <label class="set-label">Warn &ldquo;At Risk&rdquo; this many hours before delivery</label>
              <input type="number" min="1" max="336" data-setting="at_risk_hours" value="${esc(val('at_risk_hours'))}" class="set-field pro-input">
              <p class="set-hint">An unfinished order due within this window is flagged on the board before it is late, not after.</p>
            </div>

            <div class="set-row">
              <div>
                <div class="text-sm font-semibold text-slate-800">Auto-deliver on full payment</div>
                <div class="text-xs text-slate-500">Close the order once the balance reaches zero</div>
              </div>
              <div data-setting="auto_delivery_update" class="toggle ${isOn('auto_delivery_update') ? 'on' : ''}" onclick="this.classList.toggle('on')"></div>
            </div>
          </div>
        </div>

        <div class="set-card">
          <h4 class="set-legend">Dropdown Options</h4>
          <div class="space-y-4">
            <div>
              <label class="set-label">Delivery Time Slots</label>
              <input data-setting="delivery_slots" value="${esc(val('delivery_slots'))}" class="set-field pro-input" oninput="renderChips('slot-chips', this.value)">
              <div class="flex flex-wrap gap-1.5 mt-2" id="slot-chips"></div>
              <p class="set-hint">Separate each slot with <code class="bg-slate-100 px-1 rounded">|</code>. Used by the order wizard.</p>
            </div>
            <div>
              <label class="set-label">Delivery Extension Reasons</label>
              <input data-setting="extension_reasons" value="${esc(val('extension_reasons'))}" class="set-field pro-input" oninput="renderChips('reason-chips', this.value)">
              <div class="flex flex-wrap gap-1.5 mt-2" id="reason-chips"></div>
              <p class="set-hint">Separate with <code class="bg-slate-100 px-1 rounded">|</code>. Used by bulk date extension.</p>
            </div>
          </div>
        </div>

        ${panelFooter()}
      </div>`;
  }

  /** Live preview of a pipe-delimited list as it is typed. */
  window.renderChips = function (hostId, raw) {
    const host = document.getElementById(hostId);
    if (!host) return;

    const items = String(raw || '').split('|').map(s => s.trim()).filter(Boolean);

    host.innerHTML = items.length
      ? items.map(i => `<span class="text-[11px] bg-white border border-slate-200 text-slate-600 px-2 py-0.5 rounded-full">${esc(i)}</span>`).join('')
      : '<span class="text-[11px] text-slate-400">No options — this dropdown will be empty.</span>';
  };

  window.toggleDayInputs = function (toggleEl) {
    const row = toggleEl.closest('[data-day]');
    const open = toggleEl.classList.contains('on');
    row.querySelectorAll('input[type="time"]').forEach(i => { i.disabled = !open; });
  };

  /* ============================================================
     PANEL 2 — BUSINESS PROFILE
     ============================================================ */
  function panelBusinessProfile() {
    const field = (key, label, type = 'text', span = '') => `
      <div class="${span}">
        <label class="set-label">${label}</label>
        <input type="${type}" data-setting="${key}" class="set-field pro-input" value="${esc(val(key))}"
               ${key === 'store_name' ? 'oninput="preview(\'store_name\', this.value)"' : ''}>
      </div>`;

    return `
      <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">Business Profile</h3>
        <p class="text-sm text-slate-500 mb-6">Your shop identity, used on receipts, invoices and every WhatsApp message.</p>

        <div class="grid grid-cols-2 gap-6 mb-6 set-card">
          ${['logo', 'stamp'].map(kind => {
            const url = val(kind + '_path');
            const icon = kind === 'logo' ? 'fa-image' : 'fa-stamp';
            return `
            <div>
              <label class="set-label">Shop ${kind === 'logo' ? 'Logo' : 'Stamp'}</label>
              <div class="flex items-center gap-4">
                <div class="w-20 h-20 bg-white rounded-lg flex items-center justify-center border border-slate-200 overflow-hidden flex-shrink-0" id="${kind}-preview">
                  ${url
                    ? `<img src="${esc(url)}" alt="${kind}" class="w-full h-full object-contain">`
                    : `<i class="fa-solid ${icon} text-slate-400 text-xl"></i>`}
                </div>
                <div class="flex flex-col gap-2">
                  <label class="bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-800 cursor-pointer text-center transition-colors">
                    Upload<input type="file" accept="image/*" class="hidden" onchange="uploadBranding(event, '${kind}')">
                  </label>
                  <button onclick="removeBranding('${kind}')" class="text-red-500 text-xs hover:underline ${url ? '' : 'hidden'}" id="${kind}-remove">Remove</button>
                  <span class="text-[10px] text-slate-400">PNG/JPG/SVG · max 2MB</span>
                </div>
              </div>
            </div>`;
          }).join('')}
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">Basic Information</h4>
          <div class="grid grid-cols-2 gap-4">
            ${field('store_name', 'Shop Name')}
            ${field('tagline', 'Tagline')}
            ${field('owner_name', 'Owner Name')}
            ${field('registration_no', 'Registration No')}
          </div>
        </div>

        <div class="set-card">
          <h4 class="set-legend">Contact &amp; Address</h4>
          <div class="grid grid-cols-2 gap-4">
            ${field('phone', 'Primary Phone', 'tel')}
            ${field('whatsapp_number', 'WhatsApp Number', 'tel')}
            ${field('email', 'Email', 'email')}
            ${field('website', 'Website', 'url')}
            ${field('address', 'Address', 'text', 'col-span-2')}
          </div>
          <p class="set-hint mt-3">The WhatsApp number is where test messages are sent and what customers see as the sender.</p>
        </div>

        ${panelFooter()}
      </div>`;
  }

  /* Branding uploads go straight to the server; no save click needed. */
  window.uploadBranding = async function (event, kind) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
      toast('Image must be 2MB or smaller', 'error');
      event.target.value = '';
      return;
    }

    const body = new FormData();
    body.append('type', kind);
    body.append('file', file);

    try {
      const res = await fetch(SETTINGS_ROUTES.upload, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body,
      });

      const payload = await res.json();
      if (!res.ok) throw Object.assign(new Error(payload.message || 'Upload failed'), { errors: payload.errors });

      DB_SETTINGS[kind + '_path'] = payload.url;
      document.getElementById(kind + '-preview').innerHTML =
        `<img src="${esc(payload.url)}" alt="${kind}" class="w-full h-full object-contain">`;
      document.getElementById(kind + '-remove').classList.remove('hidden');

      toast(payload.message, 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not upload the image');
    } finally {
      event.target.value = '';
    }
  };

  window.removeBranding = async function (kind) {
    try {
      const res = await Atelier.api.post(SETTINGS_ROUTES.removeUpload, { type: kind });
      DB_SETTINGS[kind + '_path'] = '';

      document.getElementById(kind + '-preview').innerHTML =
        `<i class="fa-solid ${kind === 'logo' ? 'fa-image' : 'fa-stamp'} text-slate-400 text-xl"></i>`;
      document.getElementById(kind + '-remove').classList.add('hidden');

      toast(res.message, 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not remove the image');
    }
  };

  /* ============================================================
     PANEL 3 — INVOICE & BILLING
     ============================================================ */
  function panelInvoice() {
    return `
      <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">Invoice &amp; Billing</h3>
        <p class="text-sm text-slate-500 mb-6">Numbering, tax and the wording that appears on every invoice.</p>

        <div class="set-card mb-6">
          <h4 class="set-legend">Invoice Numbering</h4>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="set-label">Invoice Prefix</label>
              <input data-setting="invoice_prefix" class="set-field pro-input" value="${esc(val('invoice_prefix'))}" oninput="updateNumberPreview()">
            </div>
            <div>
              <label class="set-label">Order Prefix</label>
              <input data-setting="order_prefix" class="set-field pro-input" value="${esc(val('order_prefix'))}" oninput="updateNumberPreview()">
            </div>
            <div>
              <label class="set-label">Preview</label>
              <div class="px-3 py-2 text-sm text-slate-500 font-mono" id="number-preview"></div>
            </div>
          </div>
          <p class="set-hint">Applies to new records only — existing numbers never change.</p>
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">Tax &amp; Charges</h4>
          <div class="space-y-3">
            <div class="set-row">
              <div>
                <div class="text-sm font-semibold text-slate-800">Enable Tax</div>
                <div class="text-xs text-slate-500">Adds a tax line to invoices and receipts</div>
              </div>
              <div data-setting="tax_enabled" class="toggle ${isOn('tax_enabled') ? 'on' : ''}" onclick="this.classList.toggle('on'); toggleGroup('tax-fields', this); updateBillingPreview()"></div>
            </div>

            <div id="tax-fields" class="space-y-3 ${isOn('tax_enabled') ? '' : 'opacity-50'}">
              <div class="set-row">
                <div class="text-sm font-semibold text-slate-800">Tax Label</div>
                <input data-setting="tax_label" value="${esc(val('tax_label'))}" class="pro-input w-32 px-2 py-1 border border-slate-200 rounded text-xs bg-white" oninput="updateBillingPreview()">
              </div>
              <div class="set-row">
                <div class="text-sm font-semibold text-slate-800">Tax Rate (%)</div>
                <input type="number" min="0" max="100" step="0.01" data-setting="tax_rate" value="${num('tax_rate')}" class="pro-input w-24 px-2 py-1 border border-slate-200 rounded text-xs bg-white" oninput="updateBillingPreview()">
              </div>
              <div class="set-row">
                <div>
                  <div class="text-sm font-semibold text-slate-800">Prices already include tax</div>
                  <div class="text-xs text-slate-500">On: tax is shown inside the total. Off: it is added on top.</div>
                </div>
                <div data-setting="tax_inclusive" class="toggle ${isOn('tax_inclusive') ? 'on' : ''}" onclick="this.classList.toggle('on'); updateBillingPreview()"></div>
              </div>
            </div>

            <div class="set-row">
              <div>
                <div class="text-sm font-semibold text-slate-800">Enable Service Charge</div>
                <div class="text-xs text-slate-500">A separate percentage applied alongside tax</div>
              </div>
              <div data-setting="service_charge_enabled" class="toggle ${isOn('service_charge_enabled') ? 'on' : ''}" onclick="this.classList.toggle('on'); toggleGroup('charge-fields', this); updateBillingPreview()"></div>
            </div>

            <div id="charge-fields" class="space-y-3 ${isOn('service_charge_enabled') ? '' : 'opacity-50'}">
              <div class="set-row">
                <div class="text-sm font-semibold text-slate-800">Service Charge Label</div>
                <input data-setting="service_charge_label" value="${esc(val('service_charge_label'))}" class="pro-input w-32 px-2 py-1 border border-slate-200 rounded text-xs bg-white" oninput="updateBillingPreview()">
              </div>
              <div class="set-row">
                <div class="text-sm font-semibold text-slate-800">Service Charge (%)</div>
                <input type="number" min="0" max="100" step="0.01" data-setting="service_charge_rate" value="${num('service_charge_rate')}" class="pro-input w-24 px-2 py-1 border border-slate-200 rounded text-xs bg-white" oninput="updateBillingPreview()">
              </div>
            </div>

            <div class="set-row">
              <div>
                <div class="text-sm font-semibold text-slate-800">Allow partial payments</div>
                <div class="text-xs text-slate-500">When off, an invoice must be settled in full</div>
              </div>
              <div data-setting="allow_partial" class="toggle ${isOn('allow_partial') ? 'on' : ''}" onclick="this.classList.toggle('on')"></div>
            </div>
          </div>

          <div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
            <div class="set-label" style="margin-bottom:10px">If you price an order at 8,000</div>
            <div id="billing-preview" class="space-y-1 text-sm"></div>
            <p class="set-hint" id="billing-note"></p>
          </div>
        </div>

        <div class="set-card">
          <h4 class="set-legend">Invoice Content</h4>
          <div class="space-y-4">
            <div>
              <label class="set-label">Invoice Footer</label>
              <textarea data-setting="receipt_footer" class="set-field pro-input" rows="2">${esc(val('receipt_footer'))}</textarea>
              <p class="set-hint">Printed at the bottom of every receipt.</p>
            </div>
            <div>
              <label class="set-label">Terms &amp; Conditions</label>
              <textarea data-setting="invoice_terms" class="set-field pro-input" rows="4">${esc(val('invoice_terms'))}</textarea>
              <p class="set-hint">Shown on invoices, and on receipts if enabled under Thermal Printer.</p>
            </div>
          </div>
        </div>

        ${panelFooter()}
      </div>`;
  }

  /** Dims a dependent group when its master switch is off. */
  window.toggleGroup = function (id, toggleEl) {
    document.getElementById(id)?.classList.toggle('opacity-50', !toggleEl.classList.contains('on'));
  };

  window.updateNumberPreview = function () {
    const host = document.getElementById('number-preview');
    if (!host) return;

    const inv = document.querySelector('[data-setting="invoice_prefix"]')?.value ?? '';
    const ord = document.querySelector('[data-setting="order_prefix"]')?.value ?? '';

    host.innerHTML = `${esc(inv)}1001<br>${esc(ord)}1001`;
  };

  /**
   * Mirrors PricingService exactly, so what is previewed here is what the
   * server will actually put on the invoice.
   */
  window.updateBillingPreview = function () {
    const host = document.getElementById('billing-preview');
    if (!host) return;

    const read   = k => document.querySelector(`[data-setting="${k}"]`);
    const onOff  = k => read(k)?.classList.contains('on') ?? isOn(k);
    const number = k => Number(read(k)?.value ?? num(k)) || 0;
    const text   = k => read(k)?.value ?? val(k);

    const symbol = val('currency', '₹');
    const money  = n => symbol + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const pct    = n => String(Number(n)).replace(/\.?0+$/, '') || '0';

    const total     = 8000;
    const taxOn     = onOff('tax_enabled') && number('tax_rate') > 0;
    const chargeOn  = onOff('service_charge_enabled') && number('service_charge_rate') > 0;
    const inclusive = onOff('tax_inclusive');

    const taxRate    = taxOn ? number('tax_rate') : 0;
    const chargeRate = chargeOn ? number('service_charge_rate') : 0;

    const subtotal = inclusive
      ? total / (1 + taxRate / 100 + chargeRate / 100)
      : total;

    const tax    = subtotal * taxRate / 100;
    const charge = subtotal * chargeRate / 100;
    const grand  = inclusive ? total : subtotal + tax + charge;

    const rows = [];
    if (taxOn || chargeOn) rows.push([`Subtotal`, money(subtotal), true]);
    if (chargeOn) rows.push([`${esc(text('service_charge_label'))} (${pct(chargeRate)}%)`, money(charge), true]);
    if (taxOn)    rows.push([`${esc(text('tax_label'))} (${pct(taxRate)}%)${inclusive ? ' incl.' : ''}`, money(tax), true]);
    rows.push(['Total', money(grand), false]);

    host.innerHTML = rows.map(([label, amount, muted]) => `
      <div class="flex justify-between ${muted ? 'text-slate-500' : 'font-bold text-slate-900 pt-1 border-t border-slate-200'}">
        <span>${label}</span><span>${amount}</span>
      </div>`).join('');

    const note = document.getElementById('billing-note');
    if (note) {
      note.textContent = (!taxOn && !chargeOn)
        ? 'No tax or service charge is applied — the customer owes exactly what you price.'
        : inclusive
          ? 'Inclusive: the price you enter already covers these, so the customer still owes 8,000.'
          : `Exclusive: these are added on top, so the customer owes ${money(grand)}.`;
    }
  };

  /* ============================================================
     PANEL 4 — THERMAL PRINTER
     ============================================================ */
  function panelThermalPrinter() {
    const switches = [
      ['receipt_show_logo',    'Shop name / logo',   'The header block at the top of the receipt'],
      ['receipt_show_phone',   'Customer phone',     "Prints the customer's number under their name"],
      ['receipt_show_advance', 'Advance paid',       'The amount already collected'],
      ['receipt_show_balance', 'Remaining balance',  'What is still owed on collection'],
      ['receipt_show_barcode', 'Barcode',            'Scannable order reference'],
      ['receipt_show_terms',   'Terms & conditions', 'Appends the terms set under Invoice & Billing'],
      ['receipt_show_stamp',   'Shop stamp',         'Prints the stamp image from Business Profile'],
    ];

    return `
      <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">Thermal Printer</h3>
        <p class="text-sm text-slate-500 mb-6">Paper size and exactly which blocks appear on a printed receipt.</p>

        <div class="set-card mb-6">
          <h4 class="set-legend">Paper Settings</h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="set-label">Paper Width</label>
              <select data-setting="printer_width" class="set-field pro-input" onchange="updateReceiptPreview()">
                ${['58mm', '80mm'].map(w => `<option value="${w}" ${val('printer_width') === w ? 'selected' : ''}>${w}</option>`).join('')}
              </select>
            </div>
            <div>
              <label class="set-label">Currency Symbol</label>
              <input data-setting="currency" value="${esc(val('currency'))}" oninput="updateReceiptPreview()" class="set-field pro-input">
              <p class="set-hint">Shared with General — changing it here changes it everywhere.</p>
            </div>
          </div>
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">Receipt Content</h4>
          <div class="space-y-2">
            ${switches.map(([key, label, hint]) => `
              <div class="set-row">
                <div>
                  <div class="text-sm font-semibold text-slate-800">${label}</div>
                  <div class="text-xs text-slate-500">${hint}</div>
                </div>
                <div data-setting="${key}" class="toggle ${isOn(key) ? 'on' : ''}" onclick="this.classList.toggle('on'); updateReceiptPreview()"></div>
              </div>
            `).join('')}
          </div>
        </div>

        <div class="set-card">
          <h4 class="set-legend">Live Receipt Preview</h4>
          <p class="set-hint mb-4">${HAS_PREVIEW_ORDER ? 'Rendered from your most recent real order.' : 'No orders yet — showing your shop details with sample figures.'}</p>
          <div class="flex justify-center pt-2">
            <div id="print-receipt" class="bg-white p-4 shadow-md border border-slate-200" style="width: 280px; font-family: 'Courier New', monospace; font-size: 12px; color: #000;"></div>
          </div>
          <button class="mt-6 bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 w-full transition-colors" onclick="window.print()">
            <i class="fa-solid fa-print text-xs mr-1"></i> Print Test Receipt
          </button>
        </div>

        ${panelFooter()}
      </div>`;
  }

  /**
   * The preview reflects the switches live and uses the shop's real details
   * and a real order, so what is shown here is what the printer produces.
   */
  function updateReceiptPreview() {
    const container = document.getElementById('print-receipt');
    if (!container) return;

    const on = key => document.querySelector(`[data-setting="${key}"]`)?.classList.contains('on') ?? isOn(key);
    const width  = document.querySelector('[data-setting="printer_width"]')?.value || val('printer_width', '80mm');
    const symbol = document.querySelector('[data-setting="currency"]')?.value || val('currency', '₹');

    container.style.width = width === '58mm' ? '200px' : '280px';

    const v = PREVIEW_VARS;
    const strip = s => String(s ?? '').replace(/^[^\d.-]+/, '');
    const money = n => symbol + Number(String(n).replace(/[^\d.-]/g, '') || 0).toLocaleString('en-IN');

    const divider = '<div style="border-top:1px dashed #94a3b8; margin:8px 0"></div>';

    const taxOn = isOn('tax_enabled') && num('tax_rate') > 0;
    const chgOn = isOn('service_charge_enabled') && num('service_charge_rate') > 0;

    // The order total already includes tax and charge, so split it back out —
    // matching PricingService::breakdown() exactly.
    const grand = Number(strip(v.totalAmount).replace(/,/g, '')) || 8000;
    const multiplier = 1 + (taxOn ? num('tax_rate') : 0) / 100 + (chgOn ? num('service_charge_rate') : 0) / 100;

    const subtotal = grand / multiplier;
    const tax = taxOn ? subtotal * num('tax_rate') / 100 : 0;
    const chg = chgOn ? subtotal * num('service_charge_rate') / 100 : 0;

    const line = (label, amount, bold = false) =>
      `<div style="display:flex; justify-content:space-between; ${bold ? 'font-weight:700;' : ''}"><span>${label}</span><span>${amount}</span></div>`;

    container.innerHTML = `
      ${on('receipt_show_logo') ? `
        ${val('logo_path') ? `<div style="text-align:center; margin-bottom:6px"><img src="${esc(val('logo_path'))}" style="max-height:40px; max-width:100%; object-fit:contain"></div>` : ''}
        <div style="text-align:center; font-weight:700; font-size:15px">${esc((val('store_name') || 'Atelier').toUpperCase())}</div>` : ''}
      ${val('tagline') ? `<div style="text-align:center; font-size:10px; margin-top:2px">${esc(val('tagline'))}</div>` : ''}
      ${val('address') ? `<div style="text-align:center; font-size:9px; margin-top:4px">${esc(val('address'))}</div>` : ''}
      ${val('phone') ? `<div style="text-align:center; font-size:9px">${esc(val('phone'))}</div>` : ''}
      ${divider}
      <div>Order: ${esc(v.orderID || (val('order_prefix', 'ORD-') + '1001'))}</div>
      <div>${esc(v.customerName || 'Sample Customer')}</div>
      ${on('receipt_show_phone') ? `<div>${esc(v.customerPhone || '—')}</div>` : ''}
      <div>${esc(v.garmentType || 'Suit')}</div>
      ${divider}
      ${(taxOn || chgOn) ? line('Subtotal:', money(subtotal)) : ''}
      ${chgOn ? line(`${esc(val('service_charge_label'))}:`, money(chg)) : ''}
      ${taxOn ? line(`${esc(val('tax_label'))} (${num('tax_rate')}%):`, money(tax)) : ''}
      ${line('Total:', money(grand), true)}
      ${on('receipt_show_advance') ? line('Advance:', money(strip(v.advancePaid) || 3000)) : ''}
      ${on('receipt_show_balance') ? line('Baqi:', money(strip(v.remainingBalance) || 5000)) : ''}
      ${divider}
      <div>Tayyar: ${esc(v.dueDate || '—')}${v.dueTime ? ' · ' + esc(v.dueTime) : ''}</div>
      ${on('receipt_show_barcode') ? `
        <div style="text-align:center; margin:8px 0">
          <div style="font-size:9px; letter-spacing:.3em">||| || |||| | ||</div>
          <div style="font-size:8px">*${esc(v.orderID || (val('order_prefix', 'ORD-') + '1001'))}*</div>
        </div>` : ''}
      ${on('receipt_show_stamp') && val('stamp_path') ? `<div style="text-align:center; margin:8px 0"><img src="${esc(val('stamp_path'))}" style="max-height:48px; opacity:.85"></div>` : ''}
      ${on('receipt_show_terms') && val('invoice_terms') ? `<div style="font-size:8px; margin-top:6px; white-space:pre-wrap">${esc(val('invoice_terms'))}</div>` : ''}
      <div style="text-align:center; margin-top:8px; font-size:10px">${esc(val('receipt_footer') || 'Shukriya!')}</div>
    `;
  }

  /* ============================================================
     PANEL 5 — MEASUREMENTS
     ============================================================ */
  function panelMeasurements() {
    const required = DB_SETTINGS.measurement_required || [];
    const labelOf = f => f.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

    return `
      <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">Measurements Settings</h3>
        <p class="text-sm text-slate-500 mb-6">Units, precision and which fields a tailor must fill in.</p>

        <div class="set-card mb-6">
          <h4 class="set-legend">Units &amp; Precision</h4>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="set-label">Default Unit</label>
              <select data-setting="measurement_unit" class="set-field pro-input">
                <option value="in" ${val('measurement_unit') === 'in' ? 'selected' : ''}>Inches</option>
                <option value="cm" ${val('measurement_unit') === 'cm' ? 'selected' : ''}>Centimeters</option>
              </select>
              <p class="set-hint">New measurement forms open in this unit.</p>
            </div>
            <div>
              <label class="set-label">Decimal Places</label>
              <select data-setting="measurement_decimals" class="set-field pro-input" onchange="updatePrecisionHint()">
                ${[0, 1, 2, 3].map(d => `<option value="${d}" ${num('measurement_decimals', 2) === d ? 'selected' : ''}>${d}</option>`).join('')}
              </select>
              <p class="set-hint" id="precision-hint"></p>
            </div>
            <div class="flex items-end">
              <div class="text-xs text-slate-500 leading-relaxed">Values more precise than this are rejected when a measurement is saved.</div>
            </div>
          </div>
        </div>

        <div class="set-card">
          <div class="flex items-center justify-between mb-2">
            <h4 class="set-legend" style="margin-bottom:0">Required Fields</h4>
            <div class="flex gap-3 text-xs">
              <button onclick="setAllRequired(true)" class="text-slate-500 hover:text-slate-900">Select all</button>
              <button onclick="setAllRequired(false)" class="text-slate-500 hover:text-slate-900">Clear all</button>
            </div>
          </div>
          <p class="text-xs text-slate-500 mb-3">
            Ticked fields must be filled before a measurement — or an order carrying measurements — can be saved.
            Enforced by the server, not just the form.
            <span class="font-semibold text-slate-700" id="required-count">${required.length} selected</span>
          </p>
          <div class="bg-white p-4 rounded-lg border border-slate-100">
            <div class="grid grid-cols-3 gap-x-5 gap-y-3 text-sm">
              ${MEASUREMENT_FIELDS.map(field => `
                <label class="flex items-center gap-2 text-slate-700">
                  <input type="checkbox" data-required-field value="${field}" ${required.includes(field) ? 'checked' : ''}
                         onchange="updateRequiredCount()" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                  ${labelOf(field)}
                </label>`).join('')}
            </div>
          </div>
        </div>

        ${panelFooter()}
      </div>`;
  }

  window.updatePrecisionHint = function () {
    const hint = document.getElementById('precision-hint');
    if (!hint) return;

    const d = Number(document.querySelector('[data-setting="measurement_decimals"]')?.value ?? 2);
    hint.textContent = d === 0 ? 'Whole numbers only, e.g. 42' : `e.g. ${(42.5).toFixed(d)}`;
  };

  window.updateRequiredCount = function () {
    const count = document.querySelectorAll('[data-required-field]:checked').length;
    const host = document.getElementById('required-count');
    if (host) host.textContent = `${count} selected`;
  };

  window.setAllRequired = function (checked) {
    document.querySelectorAll('[data-required-field]').forEach(el => { el.checked = checked; });
    updateRequiredCount();
    markDirty();
  };

  /* ============================================================
     PANEL 6 — NOTIFICATIONS
     ============================================================ */
  function panelNotifications() {
    const row = (key, title, hint, extra = '') => `
      <div class="set-row">
        <div>
          <div class="text-sm font-semibold text-slate-800">${title}</div>
          <div class="text-xs text-slate-500">${hint}</div>
        </div>
        <div data-setting="${key}" class="toggle ${isOn(key) ? 'on' : ''}" onclick="this.classList.toggle('on'); ${extra}"></div>
      </div>`;

    const number = (key, title, hint, min, max) => `
      <div class="set-row">
        <div>
          <div class="text-sm font-semibold text-slate-800">${title}</div>
          <div class="text-xs text-slate-500">${hint}</div>
        </div>
        <input type="number" min="${min}" max="${max}" data-setting="${key}" value="${num(key)}" class="pro-input w-20 px-2 py-1 border border-slate-200 rounded text-xs bg-white">
      </div>`;

    const permission = ('Notification' in window) ? Notification.permission : 'unsupported';

    return `
      <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">Notifications Settings</h3>
        <p class="text-sm text-slate-500 mb-6">What the app tells you about, how loudly, and how often.</p>

        <div class="set-card mb-6">
          <h4 class="set-legend">How you are alerted</h4>
          <div class="space-y-3">
            ${row('browser_notifications', 'Desktop notifications', 'Pop-ups outside the browser window for new orders and payments', 'requestNotificationPermission(this)')}
            ${permission === 'denied' ? `
              <div class="flex items-start gap-3 p-3 rounded-lg bg-amber-50 border border-amber-100">
                <i class="fa-solid fa-triangle-exclamation text-amber-600 text-xs mt-0.5"></i>
                <p class="text-xs text-amber-800 leading-relaxed flex-1">Your browser is blocking notifications for this site. Allow them in the padlock menu in the address bar, then reload.</p>
              </div>` : ''}
            ${row('sound_alerts', 'Sound alerts', 'Play a chime when a notification arrives', "if (this.classList.contains('on')) Atelier.playChime()")}
            ${row('payment_toasts', 'Payment received toasts', 'Show a popup in-app when a payment is recorded')}
          </div>
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">What you are alerted about</h4>
          <div class="space-y-3">
            ${row('notify_order_created',  'New orders',        'A notification each time an order is placed')}
            ${row('notify_status_changed', 'Status changes',    'When an order moves between stages')}
            ${row('notify_payment',        'Payments',          'When money is recorded against an invoice')}
            ${row('notify_overdue',        'Overdue orders',    'When an order passes its delivery date')}
            ${row('notify_low_stock',      'Low stock',         'When a stocked item drops to its threshold')}
          </div>
          <p class="set-hint mt-3">A switch turned off here is not merely hidden — that notification is never recorded.</p>
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">Alert Rules</h4>
          <div class="space-y-3">
            ${number('alert_days_before', 'Alert when due in (days)', 'How far ahead a delivery starts reminding you', 0, 30)}
            ${row('repeat_alerts', 'Repeat due-date alerts', 'Alert again while an order stays outstanding', "toggleGroup('repeat-fields', this)")}
            <div id="repeat-fields" class="${isOn('repeat_alerts') ? '' : 'opacity-50'}">
              ${number('repeat_alert_hours', 'Repeat every (hours)', 'Minimum gap before the same order alerts again', 1, 72)}
            </div>
            ${number('low_stock_alert', 'Low stock threshold', 'Default level for items with no threshold of their own', 0, 9999)}
          </div>
        </div>

        <div class="set-card">
          <h4 class="set-legend">Delivery Channels</h4>
          <div class="space-y-3">
            ${row('whatsapp_enabled', 'WhatsApp', 'Enables the WhatsApp buttons on orders and automatic sends')}
            ${row('email_enabled',    'Email',    'Requires mail credentials in your .env')}
            ${row('sms_enabled',      'SMS',      'Send text messages via SendPK — configure under SMS Settings')}
          </div>
          <p class="set-hint mt-3">Configure WhatsApp templates under <span class="font-semibold">WhatsApp &amp; Alerts</span>, and SMS templates under <span class="font-semibold">SMS Settings</span>.</p>
        </div>

        ${panelFooter()}
      </div>`;
  }

  /** Browser notifications need explicit user permission before they work. */
  window.requestNotificationPermission = async function (toggleEl) {
    if (!toggleEl.classList.contains('on')) return;

    if (!('Notification' in window)) {
      toast('This browser does not support desktop notifications', 'warning');
      toggleEl.classList.remove('on');
      return;
    }

    if (Notification.permission === 'granted') return;

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      toggleEl.classList.remove('on');
      toast('Desktop notifications were blocked by the browser', 'warning');
    }
  };

  /* ============================================================
     PANEL 7 — BACKUP & DATA
     ============================================================ */
  function panelBackup() {
    return `
      <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">Backup &amp; Data</h3>
        <p class="text-sm text-slate-500 mb-6">Export, restore, and permanently remove records.</p>

        <div class="grid grid-cols-4 gap-3 mb-6" id="data-counts">
          ${Object.entries(BACKUP_TYPES).map(([key, t]) => `
            <div class="set-card text-center" style="padding:16px">
              <div class="text-xl mb-1"><i class="fa-solid ${t.icon} ${t.color}"></i></div>
              <div class="text-xl font-bold text-slate-900" data-count="${key}">${(DATA_COUNTS[key] ?? 0).toLocaleString('en-IN')}</div>
              <div class="text-xs text-slate-500">${esc(t.label)}</div>
            </div>`).join('')}
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">Export</h4>
          <div class="grid grid-cols-3 gap-3">
            <button onclick="exportBackup('json', this)" class="bg-white border border-slate-200 text-slate-600 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-50 flex items-center justify-center gap-2 transition-colors">
              <i class="fa-solid fa-file-code"></i> Full Backup (JSON)
            </button>
            <button onclick="exportBackup('csv', this)" class="bg-white border border-slate-200 text-slate-600 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-50 flex items-center justify-center gap-2 transition-colors">
              <i class="fa-solid fa-file-csv"></i> Full Backup (CSV)
            </button>
            <a href="${SETTINGS_ROUTES.reportsExport}?range=year" data-no-spa class="bg-white border border-slate-200 text-slate-600 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-50 flex items-center justify-center gap-2 transition-colors">
              <i class="fa-solid fa-chart-line"></i> Yearly Report
            </a>
          </div>
          <p class="set-hint mt-3">JSON is the format Restore accepts. API tokens are never written to an export.</p>
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">Restore from Backup</h4>
          <div class="drop-zone" id="restore-drop" onclick="document.getElementById('restore-file').click()">
            <i class="fa-solid fa-cloud-arrow-up text-2xl text-slate-400 mb-2"></i>
            <div class="text-sm font-semibold text-slate-700">Choose a backup file, or drop one here</div>
            <div class="text-xs text-slate-500 mt-1">Atelier JSON backup · max 20MB</div>
            <input type="file" id="restore-file" accept=".json,application/json" class="hidden" onchange="inspectBackup(event)">
          </div>
          <div id="restore-details" class="hidden mt-4"></div>
        </div>

        <div class="border-2 border-red-200 rounded-xl p-5 bg-red-50">
          <h4 class="text-[11px] font-bold text-red-500 uppercase tracking-widest mb-2">⚠️ Danger Zone</h4>
          <p class="text-xs text-red-700 mb-4 leading-relaxed">
            Deleting data cannot be undone. Export a backup first — the button above takes a few seconds.
          </p>

          <div class="bg-white rounded-lg border border-red-200 p-4 mb-3">
            <div class="set-label" style="margin-bottom:10px">Select what to delete</div>
            <div class="grid grid-cols-2 gap-x-5 gap-y-2 text-sm">
              ${Object.entries(BACKUP_TYPES).map(([key, t]) => `
                <label class="flex items-center gap-2 text-slate-700">
                  <input type="checkbox" data-purge-type value="${key}" onchange="updatePurgeSummary()" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                  ${esc(t.label)} <span class="text-xs text-slate-400" data-count-paren="${key}">(${(DATA_COUNTS[key] ?? 0).toLocaleString('en-IN')})</span>
                </label>`).join('')}
            </div>
            <div class="text-xs text-red-600 font-semibold mt-3" id="purge-summary">Nothing selected.</div>
          </div>

          <button onclick="clearAllNotifications(this)" class="bg-white border border-red-300 text-red-600 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-red-100 w-full flex items-center justify-center gap-2 transition-colors mb-3">
            <i class="fa-solid fa-bell-slash"></i> Clear all notifications
          </button>
          <button onclick="purgeData(this)" id="purge-btn" disabled class="bg-red-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-red-700 w-full flex items-center justify-center gap-2 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
            <i class="fa-solid fa-skull-crossbones"></i> Delete selected data permanently
          </button>
        </div>
      </div>`;
  }

  function exportBackup(format, btn) {
    const url = format === 'json' ? SETTINGS_ROUTES.backupJson : SETTINGS_ROUTES.backupCsv;
    toast('Preparing your backup…', 'info');
    window.location.href = url;
  }

  /* ------------------------- Restore ------------------------- */
  var restoreFile = null;

  window.inspectBackup = async function (event) {
    const file = event.target.files?.[0];
    if (!file) return;

    restoreFile = file;
    const details = document.getElementById('restore-details');
    details.classList.remove('hidden');
    details.innerHTML = '<div class="text-sm text-slate-500"><i class="fa-solid fa-spinner fa-spin"></i> Reading backup…</div>';

    const body = new FormData();
    body.append('file', file);

    try {
      const res = await fetch(SETTINGS_ROUTES.inspect, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body,
      });
      const payload = await res.json();

      if (!payload.valid) {
        details.innerHTML = `<div class="text-sm text-red-600 flex items-center gap-2"><i class="fa-solid fa-circle-xmark"></i> ${esc(payload.message)}</div>`;
        restoreFile = null;
        return;
      }

      details.innerHTML = renderRestoreOptions(payload, file);
    } catch (err) {
      details.innerHTML = '<div class="text-sm text-red-600">Could not read that file.</div>';
      restoreFile = null;
    }
  };

  function renderRestoreOptions(info, file) {
    const rows = Object.entries(BACKUP_TYPES)
      .filter(([key]) => (info.counts[key] ?? 0) > 0)
      .map(([key, t]) => `
        <label class="flex items-center justify-between bg-white p-2.5 rounded-lg border border-slate-100">
          <span class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" data-restore-type value="${key}" checked class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
            <i class="fa-solid ${t.icon} ${t.color} text-xs w-4"></i> ${esc(t.label)}
          </span>
          <span class="text-xs font-semibold text-slate-500">${info.counts[key].toLocaleString('en-IN')} records</span>
        </label>`).join('');

    return `
      <div class="bg-white rounded-lg border border-slate-200 p-4">
        <div class="flex items-center gap-2 text-sm font-semibold text-emerald-600 mb-1">
          <i class="fa-solid fa-circle-check"></i> ${esc(file.name)}
        </div>
        <div class="text-xs text-slate-500 mb-4">
          From <span class="font-semibold text-slate-700">${esc(info.shop || 'an Atelier shop')}</span>,
          exported ${esc(Atelier.formatDateTime(info.exported_at, 'at an unknown time'))}
        </div>

        <div class="set-label">What to restore</div>
        <div class="space-y-1.5 mb-4">${rows || '<div class="text-xs text-slate-400">This backup has no records.</div>'}</div>

        <div class="set-label">How to restore</div>
        <div class="grid grid-cols-2 gap-3 mb-4">
          <label class="border-2 border-slate-900 rounded-lg p-3 cursor-pointer block" onclick="selectRestoreMode(this,'merge')">
            <input type="radio" name="restore_mode" value="merge" checked class="hidden">
            <div class="text-sm font-semibold text-slate-900">Merge</div>
            <div class="text-xs text-slate-500 mt-0.5">Update matching records, keep everything else</div>
          </label>
          <label class="border-2 border-slate-200 rounded-lg p-3 cursor-pointer block" onclick="selectRestoreMode(this,'replace')">
            <input type="radio" name="restore_mode" value="replace" class="hidden">
            <div class="text-sm font-semibold text-red-600">Replace</div>
            <div class="text-xs text-slate-500 mt-0.5">Empty each selected table first</div>
          </label>
        </div>

        <button onclick="runRestore(this)" class="bg-slate-900 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-800 w-full flex items-center justify-center gap-2 transition-colors">
          <i class="fa-solid fa-rotate-left text-xs"></i> Restore this backup
        </button>
      </div>`;
  }

  window.selectRestoreMode = function (label, mode) {
    document.querySelectorAll('[name="restore_mode"]').forEach(r => {
      r.checked = r.value === mode;
      r.closest('label').className = `border-2 ${r.checked ? 'border-slate-900' : 'border-slate-200'} rounded-lg p-3 cursor-pointer block`;
    });
  };

  window.runRestore = function (btn) {
    if (!restoreFile) { toast('Choose a backup file first', 'warning'); return; }

    const types = [...document.querySelectorAll('[data-restore-type]:checked')].map(el => el.value);
    if (!types.length) { toast('Select at least one type to restore', 'warning'); return; }

    const mode = document.querySelector('[name="restore_mode"]:checked')?.value || 'merge';

    Atelier.confirm({
      variant: mode === 'replace' ? 'delete' : 'info',
      title: mode === 'replace' ? 'Replace existing data?' : 'Restore this backup?',
      message: mode === 'replace'
        ? `Every current record in the ${types.length} selected type(s) will be deleted and replaced by the backup. This cannot be undone.`
        : `Records in the backup will be added or updated across ${types.length} type(s). Nothing is deleted.`,
      confirmLabel: mode === 'replace' ? 'Replace data' : 'Restore',
      onConfirm: async () => {
        const body = new FormData();
        body.append('file', restoreFile);
        body.append('mode', mode);
        types.forEach(t => body.append('types[]', t));

        Atelier.setBusy(btn, true);
        try {
          const res = await fetch(SETTINGS_ROUTES.restore, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body,
          });
          const payload = await res.json();

          if (!payload.success) { toast(payload.message, 'error'); return; }

          applyCounts(payload.counts);
          Atelier.clearPageCache();
          toast(payload.message, 'success');

          document.getElementById('restore-details').classList.add('hidden');
          restoreFile = null;
        } finally {
          Atelier.setBusy(btn, false);
        }
      },
    });
  };

  /* ------------------------- Purge --------------------------- */
  window.updatePurgeSummary = function () {
    const picked = [...document.querySelectorAll('[data-purge-type]:checked')];
    const total = picked.reduce((sum, el) => sum + (DATA_COUNTS[el.value] ?? 0), 0);

    const summary = document.getElementById('purge-summary');
    const btn = document.getElementById('purge-btn');

    if (!picked.length) {
      summary.textContent = 'Nothing selected.';
      btn.disabled = true;
      return;
    }

    summary.textContent = `${total.toLocaleString('en-IN')} record(s) across ${picked.length} type(s) will be permanently deleted.`;
    btn.disabled = false;
  };

  window.purgeData = function (btn) {
    const types = [...document.querySelectorAll('[data-purge-type]:checked')].map(el => el.value);
    if (!types.length) return;

    const labels = types.map(t => BACKUP_TYPES[t].label).join(', ');

    Atelier.confirm({
      variant: 'delete',
      title: 'Permanently delete this data?',
      message: `${labels} will be erased. This cannot be undone — you will be asked to type DELETE to confirm.`,
      confirmLabel: 'Continue',
      onConfirm: () => {
        const typed = window.prompt(`Type DELETE to permanently erase: ${labels}`);
        if (typed === null) return;

        if (typed.trim() !== 'DELETE') {
          toast('Confirmation did not match — nothing was deleted', 'warning');
          return;
        }

        return Atelier.api.post(SETTINGS_ROUTES.purge, { types, confirm: typed.trim() })
          .then(res => {
            applyCounts(res.counts);
            document.querySelectorAll('[data-purge-type]').forEach(el => { el.checked = false; });
            updatePurgeSummary();
            Atelier.clearPageCache();
            Atelier.refreshCounters();
            toast(res.message, 'success');
          })
          .catch(err => Atelier.reportError(err, 'Could not delete the data'));
      },
    });
  };

  /** Repaints every counter on the panel from a fresh server count. */
  function applyCounts(counts) {
    if (!counts) return;
    Object.assign(DATA_COUNTS, counts);

    Object.entries(counts).forEach(([key, value]) => {
      const formatted = Number(value).toLocaleString('en-IN');

      document.querySelectorAll(`[data-count="${key}"]`)
        .forEach(el => { el.textContent = formatted; });

      document.querySelectorAll(`[data-count-paren="${key}"]`)
        .forEach(el => { el.textContent = `(${formatted})`; });
    });

    updatePurgeSummary();
  }

  async function clearAllNotifications(btn) {
    Atelier.confirm({
      variant: 'delete',
      title: 'Clear every notification?',
      message: 'The notification history will be emptied. Your orders, payments and customers are untouched.',
      confirmLabel: 'Clear notifications',
      onConfirm: async () => {
        const res = await Atelier.api.post(SETTINGS_ROUTES.clearNotifs);
        applyCounts(res.counts);
        Atelier.refreshCounters();
        toast(res.message, 'success');
      },
    });
  }


  /* ============================================================
     PANEL 8 — THEME & DISPLAY
     ============================================================ */
  var BRAND_PRESETS = ['#4F46E5', '#2563EB', '#0D9488', '#059669', '#D97706', '#DC2626', '#DB2777', '#7C3AED'];

  function panelTheme() {
    const themes = window.SIDEBAR_THEMES || {};

    return `
      <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">Theme &amp; Display</h3>
        <p class="text-sm text-slate-500 mb-6">Appearance choices are saved to your account and follow you across devices.</p>

        <div class="set-card mb-6">
          <h4 class="set-legend">Color Mode</h4>
          <div class="grid grid-cols-3 gap-4" id="color-mode-group">
            ${[
              ['light',  'fa-sun',     'text-amber-500',  'Light',  'Always the light theme'],
              ['dark',   'fa-moon',    'text-indigo-400', 'Dark',   'Always the dark theme'],
              ['system', 'fa-desktop', 'text-slate-500',  'System', 'Follows your operating system'],
            ].map(([mode, icon, color, label, hint]) => {
              const active = val('color_mode', 'light') === mode;
              return `
              <label class="border-2 ${active ? 'border-slate-900' : 'border-slate-200 hover:border-slate-300'} rounded-xl p-4 cursor-pointer text-center bg-white transition-colors block" onclick="selectColorMode('${mode}')">
                <input type="radio" name="color_mode" data-setting="color_mode" value="${mode}" ${active ? 'checked' : ''} class="hidden">
                <i class="fa-solid ${icon} text-2xl mb-2 ${color}"></i>
                <div class="text-sm font-semibold text-slate-800">${label}</div>
                <div class="text-[11px] text-slate-500 mt-0.5">${hint}</div>
              </label>`;
            }).join('')}
          </div>
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">Accent Color</h4>
          <p class="text-xs text-slate-500 mb-4">Used for active navigation, toggles, avatars and focus rings.</p>
          <div class="flex items-center gap-3 flex-wrap">
            ${BRAND_PRESETS.map(c => `
              <div class="swatch ${val('primary_color', '#4F46E5').toLowerCase() === c.toLowerCase() ? 'active' : ''}"
                   style="background:${c}" title="${c}" onclick="selectBrandColor('${c}')"></div>`).join('')}
            <label class="flex items-center gap-2 ml-2">
              <input type="color" id="brand-picker" value="${esc(val('primary_color', '#4F46E5'))}"
                     class="w-9 h-9 rounded-lg border border-slate-200 cursor-pointer bg-white p-0.5"
                     oninput="selectBrandColor(this.value)">
              <span class="text-xs text-slate-500 font-mono" id="brand-hex">${esc(val('primary_color', '#4F46E5').toUpperCase())}</span>
            </label>
          </div>
          <input type="hidden" data-setting="primary_color" id="primary-color-field" value="${esc(val('primary_color', '#4F46E5'))}">
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">Table Display</h4>
          <div class="space-y-3">
            <div class="set-row">
              <div>
                <div class="text-sm font-semibold text-slate-800">Compact tables</div>
                <div class="text-xs text-slate-500">Tighter row height to fit more on screen</div>
              </div>
              <div data-setting="compact_tables" class="toggle ${isOn('compact_tables') ? 'on' : ''}"
                   onclick="this.classList.toggle('on'); preview('compact_tables', this.classList.contains('on') ? '1' : '0')"></div>
            </div>
            <div class="set-row">
              <div>
                <div class="text-sm font-semibold text-slate-800">Rows per page</div>
                <div class="text-xs text-slate-500">Applies to every paginated table in the app</div>
              </div>
              <input type="number" min="5" max="100" data-setting="rows_per_page" value="${num('rows_per_page', 10)}"
                     class="pro-input w-20 px-2 py-1 border border-slate-200 rounded text-xs bg-white"
                     oninput="preview('rows_per_page', this.value)">
            </div>
          </div>
        </div>

        <div class="set-card">
          <h4 class="set-legend">Sidebar Color</h4>
          <p class="text-xs text-slate-500 mb-4">Saved with your account, not just this browser.</p>
          <input type="hidden" data-setting="sidebar_theme" id="sidebar-theme-field" value="${esc(val('sidebar_theme', 'white'))}">
          <div class="grid grid-cols-4 gap-3">
            ${Object.entries(themes).map(([key, t]) => {
              const active = val('sidebar_theme', 'white') === key;
              return `
              <button onclick="selectSidebarTheme('${key}')" class="group relative rounded-xl overflow-hidden border-2 ${active ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-slate-200 hover:border-slate-300'} transition-all hover:shadow-md" data-theme-card="${key}">
                <div class="p-2.5" style="background:${t.bg}">
                  <div class="flex items-center gap-1.5 mb-2">
                    <div class="w-4 h-4 rounded" style="background:${t.logoBg}"></div>
                    <div class="h-1.5 w-10 rounded-full" style="background:${t.muted}; opacity:0.5"></div>
                  </div>
                  <div class="space-y-1">
                    <div class="h-1.5 rounded-full" style="background:${t.fill}; width:85%"></div>
                    <div class="h-1.5 rounded-full" style="background:${t.hover}; width:70%; opacity:0.5"></div>
                    <div class="h-1.5 rounded-full" style="background:${t.hover}; width:60%; opacity:0.5"></div>
                  </div>
                  <div class="flex items-center gap-1 mt-2">
                    <div class="w-3 h-3 rounded-full" style="background:${t.accent}"></div>
                    <div class="h-1 w-6 rounded-full" style="background:${t.muted}; opacity:0.4"></div>
                  </div>
                </div>
                <div class="bg-white px-2 py-1.5 text-center">
                  <div class="text-[10px] font-semibold text-slate-700 truncate">${esc(t.n)}</div>
                </div>
                ${active ? '<div class="absolute top-1 right-1 w-5 h-5 rounded-full bg-indigo-500 flex items-center justify-center"><i class="fa-solid fa-check text-white" style="font-size:8px"></i></div>' : ''}
              </button>`;
            }).join('')}
          </div>
        </div>

        ${panelFooter()}
      </div>`;
  }

  /** Colour mode previews instantly; Save persists it. */
  window.selectColorMode = function (mode) {
    document.querySelectorAll('#color-mode-group input[name="color_mode"]').forEach(r => {
      r.checked = r.value === mode;
      r.closest('label').className = `border-2 ${r.checked ? 'border-slate-900' : 'border-slate-200 hover:border-slate-300'} rounded-xl p-4 cursor-pointer text-center bg-white transition-colors block`;
    });

    preview('color_mode', mode);
  };

  window.selectBrandColor = function (hex) {
    hex = String(hex || '').trim();
    if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) return;

    document.getElementById('primary-color-field').value = hex;
    document.getElementById('brand-picker').value = hex;
    document.getElementById('brand-hex').textContent = hex.toUpperCase();

    document.querySelectorAll('.swatch').forEach(s => {
      s.classList.toggle('active', s.title.toLowerCase() === hex.toLowerCase());
    });

    preview('primary_color', hex);
  };

  window.selectSidebarTheme = function (key) {
    document.getElementById('sidebar-theme-field').value = key;
    preview('sidebar_theme', key);
    switchSettingsPanel('Theme & Display');
  };

  /* ============================================================
     PANEL 9 — WHATSAPP & ALERTS
     ============================================================ */
  function panelWhatsApp() {
    return `<div class="p-6">
      <h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">WhatsApp & Alerts</h3>
      <p class="text-sm text-slate-500 mb-6">Local message previews. Automated sending uses approved templates configured under WhatsApp Business API.</p>
      <div class="set-card mb-4"><p class="set-hint">Editing these previews does not create or change an approved Meta template. SMS has its own editor.</p>
      <div class="flex flex-wrap gap-1.5 mt-3">${Object.entries(TEMPLATE_VARS).map(([name, desc]) => `<button onclick="insertVariable('${name}')" title="${esc(desc)}" class="bg-white border border-slate-200 px-2 py-0.5 rounded text-[11px] text-slate-600">{${name}}</button>`).join('')}</div></div>
      <div id="templates-container" class="space-y-6"></div>${panelFooter()}</div>`;
  }

  /* ================================================================
     PANEL — SMS SETTINGS
     ================================================================
     Mirrors the WhatsApp & Alerts panel layout but for text SMS.
     Separate templates, separate provider credentials, same variable
     system. Nothing here touches any WhatsApp code.
     ================================================================ */
  function panelSms() {
    const provider = val('sms_provider', 'veevo');

    return `
      <div class="p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">SMS Settings</h3>
            <p class="text-sm text-slate-500">Send text messages to your customers alongside or instead of WhatsApp.</p>
          </div>
          <span class="badge ${isOn('sms_enabled') ? 'badge-delivered' : 'badge-overdue'}">${isOn('sms_enabled') ? 'Enabled' : 'Disabled'}</span>
        </div>

        ${!isOn('sms_enabled') ? `
          <div class="flex items-start gap-3 p-3 rounded-lg bg-amber-50 border border-amber-100 mb-6">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 text-xs mt-0.5"></i>
            <p class="text-xs text-amber-800 leading-relaxed flex-1">
              SMS delivery is switched off under <span class="font-semibold">Notifications → Delivery Channels</span>.
              Templates can still be edited, but nothing will be sent.
            </p>
          </div>` : ''}

        <div class="set-card mb-6">
          <h4 class="set-legend">SMS Provider</h4>
          <label class="set-label">Provider</label>
          <select data-setting="sms_provider" class="set-field pro-input mb-4" onchange="switchSmsProvider(this.value)">
            <option value="veevo" ${provider === 'veevo' ? 'selected' : ''}>Veevo Tech / SPEXT — Recommended</option>
            <option value="sendpk" ${provider === 'sendpk' ? 'selected' : ''}>SendPK</option>
          </select>
          ${['veevo', 'sendpk'].map(p => `<div data-sms-provider="${p}" ${provider !== p ? 'hidden' : ''}>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div><label class="set-label">${p === 'veevo' ? 'Veevo / SPEXT' : 'SendPK'} API Key</label>
                <input type="password" autocomplete="new-password" data-setting="${p}_api_key" class="set-field pro-input" value="${esc(val(p+'_api_key'))}" ${provider !== p ? 'disabled' : ''}>
                <p class="set-hint">Obtain this key from your provider account. Saved dots mean unchanged.</p></div>
              <div><label class="set-label">Sender ID / Masking ${p === 'veevo' ? '(optional)' : ''}</label>
                <input data-setting="${p}_sender_id" class="set-field pro-input" value="${esc(val(p+'_sender_id'))}" ${provider !== p ? 'disabled' : ''}>
                <p class="set-hint">${p === 'veevo' ? 'Leave empty to use the account default sender.' : 'Use the sender approved for your SendPK account.'}</p></div>
            </div>
            <div class="flex flex-wrap items-center gap-3 mt-4">
              <button onclick="testSmsConnection(this)" class="bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-medium">${p === 'veevo' ? 'Validate configuration' : 'Test connection'}</button>
              ${p === 'sendpk' ? '<button onclick="checkSmsBalance(this)" class="bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-medium">Check balance</button>' : '<span class="set-hint">No balance or no-send credential check is available. Use an explicit test SMS to verify delivery.</span>'}
            </div>
          </div>`).join('')}
          <p class="set-hint mt-3">Save first, then test. Only the selected provider sends.</p>
          <div id="sms-balance-info" class="mt-3"></div>
        </div>

        <div class="set-card mb-6">
          <h4 class="set-legend">Send a test SMS</h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="set-label">To this number</label>
              <input id="sms-test-phone" class="set-field pro-input" value="${esc(val('whatsapp_number') || val('phone'))}" placeholder="03001234567">
            </div>
            <div>
              <label class="set-label">Message</label>
              <input id="sms-test-text" class="set-field pro-input" value="Test from ${esc(val('store_name') || 'Atelier')} — SMS is working.">
            </div>
          </div>
          <button onclick="sendSmsTest(this)" class="mt-3 bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-600 flex items-center gap-2 transition-colors shadow-sm shadow-blue-500/30">
            <i class="fa-solid fa-paper-plane text-xs"></i> Send test SMS
          </button>
          <p class="set-hint mt-2">Save first if you have just changed the API key — the test uses the saved settings.</p>
        </div>

        <div class="flex items-center justify-between mb-3">
          <h4 class="set-legend" style="margin-bottom:0">SMS Templates</h4>
          <span class="text-xs text-slate-500">${smsTemplates.filter(t => t.active).length} of ${smsTemplates.length} active</span>
        </div>

        <div class="set-card mb-4" style="padding:12px">
          <div class="flex flex-wrap gap-1.5 items-center">
            <span class="text-xs text-slate-500 mr-1">Insert a variable:</span>
            ${Object.entries(TEMPLATE_VARS).map(([name, desc]) => `
              <button onclick="insertSmsVariable('${name}')" title="${esc(desc)}"
                      class="bg-white border border-slate-200 px-2 py-0.5 rounded text-[11px] hover:bg-slate-100 hover:border-slate-300 transition-colors font-mono text-slate-600">
                {${name}}
              </button>`).join('')}
          </div>
          <p class="set-hint mt-2">Click a variable to insert it at the cursor. SMS messages should be kept short (160 chars for English).</p>
        </div>

        <div id="sms-templates-container" class="space-y-6"></div>

        ${panelFooter()}
      </div>`;
  }

  /* ------------------------ SMS Templates Rendering ------------------------ */
  var lastFocusedSmsTemplate = null;

  function renderSmsTemplates() {
    const container = document.getElementById('sms-templates-container');
    if (!container) return;

    if (!smsTemplates.length) {
      container.innerHTML = Atelier.emptyState({ icon: 'fa-comment-slash', title: 'No SMS templates', message: 'Restore this panel to bring back the defaults.' });
      return;
    }

    container.innerHTML = smsTemplates.map(t => `
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex justify-between items-center p-4 border-b border-slate-200 bg-slate-50">
          <div class="flex items-center gap-3">
            <div class="toggle ${t.active ? 'on' : ''}" onclick="toggleSmsTemplate('${t.id}')" title="${t.active ? 'Active' : 'Switched off'}"></div>
            <div>
              <h5 class="text-sm font-bold text-slate-900 tracking-tight">${esc(t.name)}</h5>
              <div class="text-[11px] text-slate-500">${esc(smsTemplateTrigger(t.id))}</div>
            </div>
          </div>
          <div class="flex gap-3">
            <button onclick="resetSmsTemplate('${t.id}')" class="text-xs text-slate-500 hover:text-slate-900 transition-colors">Reset</button>
            <button onclick="testSendSmsTemplate('${t.id}', this)" class="text-xs text-blue-600 font-semibold hover:underline">Test send</button>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-0">
          <div class="p-4 border-r border-slate-200">
            <textarea id="sms-tpl-${t.id}" maxlength="500"
                      onfocus="lastFocusedSmsTemplate='${t.id}'"
                      oninput="updateSmsPreview('${t.id}')"
                      class="pro-input w-full h-32 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono focus:bg-white transition-colors">${esc(t.text)}</textarea>
            <div class="text-right text-xs text-slate-400 mt-1" id="sms-count-${t.id}">${(t.text || '').length} / 500</div>
          </div>
          <div class="p-4 bg-slate-100 flex items-start">
            <div class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 max-w-full whitespace-pre-wrap break-words shadow-sm" id="sms-prev-${t.id}">${esc(renderSmsPreview(t.text))}</div>
          </div>
        </div>
      </div>
    `).join('');
  }

  function smsTemplateTrigger(id) {
    return {
      'order-created':    'Sent when an order is created',
      'order-ready':      'Sent when a customer is notified their order is ready',
      'payment-received': 'Sent when a partial payment is recorded',
      'due-reminder':     'Sent when an order enters its reminder window',
      'due-extended':     'Sent when a delivery date is pushed back',
      'final-receipt':    'Sent when the balance reaches zero',
    }[id] || 'Sent manually';
  }

  function renderSmsPreview(text) {
    let preview = String(text ?? '');
    Object.entries(PREVIEW_VARS).forEach(([key, value]) => {
      preview = preview.split('{' + key + '}').join(value ?? '');
    });
    return preview.replace(/\{[a-zA-Z]+\}/g, '');
  }

  window.updateSmsPreview = function (id) {
    const textarea = document.getElementById(`sms-tpl-${id}`);
    const preview  = document.getElementById(`sms-prev-${id}`);
    const count    = document.getElementById(`sms-count-${id}`);
    if (!textarea) return;

    const text = textarea.value;
    if (preview) preview.innerText = renderSmsPreview(text);
    if (count)   count.innerText = `${text.length} / 500`;

    const tpl = smsTemplates.find(t => t.id === id);
    if (tpl) tpl.text = text;
  };

  window.toggleSmsTemplate = function (id) {
    const tpl = smsTemplates.find(t => t.id === id);
    if (!tpl) return;
    tpl.active = !tpl.active;
    renderSmsTemplates();
    markDirty();
  };

  window.insertSmsVariable = function (name) {
    const id = lastFocusedSmsTemplate || smsTemplates[0]?.id;
    const textarea = id ? document.getElementById(`sms-tpl-${id}`) : null;

    if (!textarea) {
      navigator.clipboard?.writeText('{' + name + '}');
      toast(`{${name}} copied — paste it into a template`, 'info');
      return;
    }

    const token = '{' + name + '}';
    const start = textarea.selectionStart ?? textarea.value.length;
    const end   = textarea.selectionEnd ?? start;

    textarea.value = textarea.value.slice(0, start) + token + textarea.value.slice(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + token.length;

    updateSmsPreview(id);
    markDirty();
  };

  window.resetSmsTemplate = function (id) {
    const original = DEFAULT_SMS_TEMPLATES.find(t => t.id === id);
    if (!original) { toast('No default exists for this template', 'info'); return; }

    const index = smsTemplates.findIndex(t => t.id === id);
    if (index > -1) smsTemplates[index] = JSON.parse(JSON.stringify(original));

    renderSmsTemplates();
    markDirty();
    toast('SMS template reset — press Save to keep it', 'info');
  };

  window.testSendSmsTemplate = async function (id, btn) {
    if (!requireSavedMessaging()) return;
    const tpl = smsTemplates.find(t => t.id === id);
    if (!tpl) return;

    const phone = window.prompt(
      'Send this test SMS to which number?\n(Leave blank to use your shop phone number.)',
      val('whatsapp_number') || val('phone') || ''
    );
    if (phone === null) return;

    Atelier.setBusy(btn, true);
    try {
      const text = document.getElementById(`sms-tpl-${id}`)?.value ?? tpl.text;
      // Render preview variables into the text for the test
      let rendered = text;
      Object.entries(PREVIEW_VARS).forEach(([key, value]) => {
        rendered = rendered.split('{' + key + '}').join(value ?? '');
      });
      rendered = rendered.replace(/\{[a-zA-Z]+\}/g, '');

      const res = await Atelier.api.post(SETTINGS_ROUTES.sendTestSms, {
        phone: phone.trim() || val('whatsapp_number') || val('phone'),
        message: rendered,
      });

      toast(res.message, res.success ? 'success' : 'warning');
    } catch (err) {
      Atelier.reportError(err, 'Could not send the test SMS');
    } finally {
      Atelier.setBusy(btn, false);
    }
  };

  /* ---------------------- SMS Diagnostics ---------------------- */
  window.testSmsConnection = async function (btn) {
    if (!requireSavedMessaging()) return;
    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.post(SETTINGS_ROUTES.testSms, {});
      toast(res.message, res.success ? 'success' : 'warning');
    } catch (err) {
      Atelier.reportError(err, 'Could not reach the SMS provider');
    } finally {
      Atelier.setBusy(btn, false);
    }
  };

  window.sendSmsTest = async function (btn) {
    if (!requireSavedMessaging()) return;
    const phone = document.getElementById('sms-test-phone')?.value.trim();
    const text  = document.getElementById('sms-test-text')?.value.trim();

    if (!phone) { toast('Number likhein', 'error'); return; }
    if (!text)  { toast('Message likhein', 'error'); return; }

    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.post(SETTINGS_ROUTES.sendTestSms, { phone, message: text });
      toast(res.message, res.success ? 'success' : 'warning');
    } catch (err) {
      Atelier.reportError(err, 'Could not send the test SMS');
    } finally {
      Atelier.setBusy(btn, false);
    }
  };

  window.checkSmsBalance = async function (btn) {
    if (!requireSavedMessaging()) return;
    Atelier.setBusy(btn, true);
    const host = document.getElementById('sms-balance-info');

    try {
      const res = await Atelier.api.get(SETTINGS_ROUTES.smsBalance);
      const data = res.data || {};

      if (host) {
        if (res.success) {
          const balance = data.balance ?? 'Unknown';
          host.innerHTML = `
            <div class="flex items-start gap-3 p-3 rounded-lg bg-emerald-50 border border-emerald-100">
              <i class="fa-solid fa-circle-check text-emerald-600 text-xs mt-0.5"></i>
              <div class="flex-1">
                <div class="text-xs font-semibold text-emerald-800">SMS Balance</div>
                <div class="text-sm font-bold text-emerald-900 mt-0.5">${Atelier.escapeHtml(String(balance))}</div>
              </div>
            </div>`;
        } else {
          host.innerHTML = `
            <div class="flex items-start gap-3 p-3 rounded-lg bg-red-50 border border-red-100">
              <i class="fa-solid fa-circle-xmark text-red-600 text-xs mt-0.5"></i>
              <p class="text-xs text-red-800">${Atelier.escapeHtml(res.message)}</p>
            </div>`;
        }
      }

      toast(res.message, res.success ? 'success' : 'warning');
    } catch (err) {
      Atelier.reportError(err, 'Could not check the SMS balance');
      if (host) host.innerHTML = '';
    } finally {
      Atelier.setBusy(btn, false);
    }
  };


  function requireSavedMessaging() {
    if (dirtyPanels.size) { toast('Save your changes before testing the saved configuration.', 'warning'); return false; }
    return true;
  }
  window.switchSmsProvider = function (provider) {
    document.querySelectorAll('[data-sms-provider]').forEach(block => {
      block.hidden = block.dataset.smsProvider !== provider;
      block.querySelectorAll('[data-setting]').forEach(input => input.disabled = block.hidden);
    });
    markDirty();
  };
  function metaMappings() {
    return DEFAULT_TEMPLATES.map(t => Object.assign({id:t.id, active:false, name:'', language:'en_US', parameters:[]}, (DB_SETTINGS.meta_templates || []).find(m => m.id === t.id) || {}));
  }
  function collectMetaMappings() {
    return Array.from(document.querySelectorAll('[data-meta-event]')).map(el => ({
      id: el.dataset.metaEvent,
      active: el.querySelector('[data-meta-active]').checked,
      name: el.querySelector('[data-meta-name]').value.trim(),
      language: el.querySelector('[data-meta-language]').value.trim(),
      parameters: el.querySelector('[data-meta-parameters]').value.split(',').map(v => v.trim()).filter(Boolean)
    }));
  }
  function panelMeta() {
    return `<div class="p-6">
      <div class="flex justify-between items-start mb-6"><div><h3 class="text-lg font-semibold text-slate-900 mb-1 tracking-tight">WhatsApp Business API</h3>
      <p class="text-sm text-slate-500">Meta WhatsApp Cloud API · approved customer notification templates.</p></div>
      <span class="badge ${isOn('whatsapp_enabled') ? 'badge-delivered' : 'badge-overdue'}">${isOn('whatsapp_enabled') ? 'Enabled' : 'Disabled'}</span></div>
      <div class="set-card mb-6"><h4 class="set-legend">Official Meta configuration</h4>
        <p class="set-hint mb-4">Delivery is controlled under Notifications → Delivery Channels. Saved credentials are not proof of connection.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div><label class="set-label">Meta Access Token</label><input type="password" autocomplete="new-password" data-setting="meta_access_token" class="set-field pro-input" value="${esc(val('meta_access_token'))}"><p class="set-hint">Saved dots mean unchanged. Use a token authorized for your WhatsApp business.</p></div>
          <div><label class="set-label">Phone Number ID</label><input data-setting="meta_phone_number_id" class="set-field pro-input" value="${esc(val('meta_phone_number_id'))}"></div>
          <div><label class="set-label">WhatsApp Business Account ID (WABA)</label><input data-setting="meta_waba_id" class="set-field pro-input" value="${esc(val('meta_waba_id'))}"><p class="set-hint">Used to verify your approved templates.</p></div>
        </div>
        <button onclick="testWhatsappConnection(this)" class="mt-4 bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-medium">Test Connection & Templates</button>
        <div id="meta-status" class="set-hint mt-3" role="status">Not verified in this session.</div>
      </div>
      <h4 class="set-legend">Approved event templates</h4>
      <p class="set-hint mb-4">Create and approve text-body templates in Meta first. Enter ordered Tailor variable names separated by commas, matching @{{1}}, @{{2}}, and so on. Optional static footers are supported; headers and buttons are not.</p>
      <p class="set-hint mb-4">Variables: ${Object.keys(TEMPLATE_VARS).map(esc).join(', ')}</p>
      ${metaMappings().map(m => `<div class="set-card mb-4" data-meta-event="${m.id}">
        <div class="flex items-center justify-between mb-3"><h4 class="set-legend mb-0">${esc(DEFAULT_TEMPLATES.find(t=>t.id===m.id).name)}</h4><label class="text-xs text-slate-600"><input type="checkbox" data-meta-active ${m.active ? 'checked' : ''}> Enabled</label></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"><div><label class="set-label">Approved template name</label><input data-meta-name class="set-field pro-input" value="${esc(m.name)}" placeholder="bst_${m.id.replaceAll('-', '_')}"></div>
        <div><label class="set-label">Language code</label><input data-meta-language class="set-field pro-input" value="${esc(m.language)}"></div></div>
        <label class="set-label mt-3">Body variables in order</label><input data-meta-parameters class="set-field pro-input" value="${esc(m.parameters.join(', '))}" placeholder="customerName, orderID, shopName">
        <p class="set-hint mt-2" data-meta-status="${m.id}">Save and test the configuration to verify this mapping.</p>
      </div>`).join('')}
      <div class="set-card mb-6"><h4 class="set-legend">Send Test Message</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"><div><label class="set-label">Recipient</label><input id="meta-test-phone" class="set-field pro-input" placeholder="03001234567"></div>
        <div><label class="set-label">Approved event mapping</label><select id="meta-test-event" class="set-field pro-input">${DEFAULT_TEMPLATES.map(t=>`<option value="${t.id}">${esc(t.name)}</option>`).join('')}</select></div></div>
        <p class="set-hint mt-3">Uses the saved mapping and most recent order for variables. This sends a real message and may incur provider charges.</p>
        <button onclick="sendMetaTest(this)" class="mt-3 bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-600">Send Test Message</button>
      </div>${panelFooter()}</div>`;
  }
  window.testWhatsappConnection = async function (btn) {
    if (!requireSavedMessaging()) return;
    Atelier.setBusy(btn, true);
    const host = document.getElementById('meta-status');
    try {
      const res = await Atelier.api.post(SETTINGS_ROUTES.testWhatsapp, {});
      if (host) host.textContent = res.message + (res.sender ? ' Sender: '+res.sender.name+' '+res.sender.phone : '');
      (res.templates || []).forEach(t => {
        const el = document.querySelector('[data-meta-status="'+t.id+'"]');
        if (el) el.textContent = (t.active ? '' : 'Disabled. ') + (t.error || 'Approved template and parameter mapping verified.');
      });
      toast(res.message, 'success');
    } catch (err) {
      if (host) host.textContent = 'Verification failed. Review the error and saved configuration.';
      Atelier.reportError(err, 'Could not verify Meta configuration');
    } finally { Atelier.setBusy(btn, false); }
  };
  window.sendMetaTest = async function (btn) {
    if (!requireSavedMessaging()) return;
    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.post(SETTINGS_ROUTES.sendTest, {phone:document.getElementById('meta-test-phone').value, template_id:document.getElementById('meta-test-event').value});
      toast(res.message, res.success ? 'success' : 'warning');
    } catch (err) { Atelier.reportError(err, 'Meta test message failed'); }
    finally { Atelier.setBusy(btn, false); }
  };

  /* ------------------------ Templates ------------------------ */
  var lastFocusedTemplate = null;

  function renderTemplates() {
    const container = document.getElementById('templates-container');
    if (!container) return;

    if (!messageTemplates.length) {
      container.innerHTML = Atelier.emptyState({ icon: 'fa-comment-slash', title: 'No templates', message: 'Restore this panel to bring back the defaults.' });
      return;
    }

    container.innerHTML = messageTemplates.map(t => `
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
        <div class="flex justify-between items-center p-4 border-b border-slate-200 bg-slate-50">
          <div class="flex items-center gap-3">
            <div class="toggle ${t.active ? 'on' : ''}" onclick="toggleTemplate('${t.id}')" title="${t.active ? 'Active' : 'Switched off'}"></div>
            <div>
              <h5 class="text-sm font-bold text-slate-900 tracking-tight">${esc(t.name)}</h5>
              <div class="text-[11px] text-slate-500">${esc(templateTrigger(t.id))}</div>
            </div>
          </div>
          <div class="flex gap-3">
            <button onclick="resetTemplate('${t.id}')" class="text-xs text-slate-500 hover:text-slate-900 transition-colors">Reset</button>

          </div>
        </div>
        <div class="grid grid-cols-2 gap-0">
          <div class="p-4 border-r border-slate-200">
            <textarea id="tpl-${t.id}" maxlength="1000"
                      onfocus="lastFocusedTemplate='${t.id}'"
                      oninput="updateTemplatePreview('${t.id}')"
                      class="pro-input w-full h-48 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono focus:bg-white transition-colors">${esc(t.text)}</textarea>
            <div class="text-right text-xs text-slate-400 mt-1" id="count-${t.id}">${(t.text || '').length} / 1000</div>
          </div>
          <div class="p-4 bg-slate-100 flex items-start">
            <div class="wa-bubble" id="prev-${t.id}">${esc(renderTemplatePreview(t.text))}</div>
          </div>
        </div>
      </div>
    `).join('');
  }

  /** Explains when each template fires, so the list is self-documenting. */
  function templateTrigger(id) {
    return {
      'order-created':    'Sent automatically when an order is created',
      'order-ready':      'Sent when you notify a customer their order is ready',
      'payment-received': 'Sent when a partial payment is recorded',
      'due-reminder':     'Sent when an order enters its reminder window',
      'due-extended':     'Sent when a delivery date is pushed back',
      'final-receipt':    'Sent when the balance reaches zero',
    }[id] || 'Sent manually';
  }

  function renderTemplatePreview(text) {
    let preview = String(text ?? '');

    Object.entries(PREVIEW_VARS).forEach(([key, value]) => {
      preview = preview.split('{' + key + '}').join(value ?? '');
    });

    // Anything still unresolved is not a real variable.
    return preview.replace(/\{[a-zA-Z]+\}/g, '');
  }

  window.updateTemplatePreview = function (id) {
    const textarea = document.getElementById(`tpl-${id}`);
    const preview  = document.getElementById(`prev-${id}`);
    const count    = document.getElementById(`count-${id}`);
    if (!textarea) return;

    const text = textarea.value;
    preview.innerText = renderTemplatePreview(text);
    count.innerText = `${text.length} / 1000`;

    const tpl = messageTemplates.find(t => t.id === id);
    if (tpl) tpl.text = text;
  };

  window.toggleTemplate = function (id) {
    const tpl = messageTemplates.find(t => t.id === id);
    if (!tpl) return;

    tpl.active = !tpl.active;
    renderTemplates();
    markDirty();
  };

  /** Inserts a variable at the cursor in whichever template was last edited. */
  window.insertVariable = function (name) {
    const id = lastFocusedTemplate || messageTemplates[0]?.id;
    const textarea = id ? document.getElementById(`tpl-${id}`) : null;

    if (!textarea) {
      navigator.clipboard?.writeText('{' + name + '}');
      toast(`{${name}} copied — paste it into a template`, 'info');
      return;
    }

    const token = '{' + name + '}';
    const start = textarea.selectionStart ?? textarea.value.length;
    const end   = textarea.selectionEnd ?? start;

    textarea.value = textarea.value.slice(0, start) + token + textarea.value.slice(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + token.length;

    updateTemplatePreview(id);
    markDirty();
  };

  /** Restores one template to the shipped default without touching the others. */
  window.resetTemplate = function (id) {
    const original = DEFAULT_TEMPLATES.find(t => t.id === id);
    if (!original) { toast('No default exists for this template', 'info'); return; }

    const index = messageTemplates.findIndex(t => t.id === id);
    if (index > -1) messageTemplates[index] = JSON.parse(JSON.stringify(original));

    renderTemplates();
    markDirty();
    toast('Template reset — press Save to keep it', 'info');
  };

  /* ============================================================
     INIT
     ============================================================ */
  Atelier.onPageReady(() => {
    renderTabs();
    switchSettingsPanel('General');
    syncRuntime();

    // Populate the previews that depend on freshly rendered inputs.
    renderChips('slot-chips', val('delivery_slots'));
    renderChips('reason-chips', val('extension_reasons'));

    // Guard against closing the tab mid-edit.
    window.addEventListener('beforeunload', (e) => {
      if (!dirtyPanels.size) return;
      e.preventDefault();
      e.returnValue = '';
    }, { signal: Atelier.pageSignal() });
  });
</script>
@endpush
