@extends('layouts.app')
@section('spaPage', 'customers-import')
@section('title', 'Import Customers')

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Bulk Import</h1>
    <p class="text-sm text-slate-500 mt-0.5">Bring an existing customer book — measurements included — into the system</p>
  </div>
  <a href="{{ route('customers.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900 flex items-center gap-2">
    <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to customers
  </a>
</div>

{{-- Step rail --}}
<div class="page bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6">
  <div class="flex items-center" id="wizard-rail">
    @foreach(['Upload file', 'Match columns', 'Check the file', 'Import'] as $i => $label)
      <div class="flex items-center {{ $i < 3 ? 'flex-1' : '' }}" data-step="{{ $i + 1 }}">
        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 step-dot bg-slate-100 text-slate-400">{{ $i + 1 }}</div>
          <span class="text-xs font-semibold step-label text-slate-400 whitespace-nowrap">{{ $label }}</span>
        </div>
        @if($i < 3)
          <div class="flex-1 h-px bg-slate-200 mx-3 step-line"></div>
        @endif
      </div>
    @endforeach
  </div>
</div>

{{-- ------------------------------ STEP 1 ------------------------------ --}}
<div class="page wizard-step" data-panel="1">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-5 border-b border-slate-200">
        <h3 class="text-base font-semibold text-slate-900 tracking-tight">Choose the file</h3>
        <p class="text-xs text-slate-500 mt-0.5">Excel (.xlsx) or CSV, with the column headings in the first row</p>
      </div>
      <div class="p-5">
        <label for="import-file"
               class="block border-2 border-dashed border-slate-200 rounded-xl px-6 py-12 text-center cursor-pointer hover:border-slate-400 hover:bg-slate-50 transition-colors"
               id="drop-zone">
          <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-file-arrow-up text-lg"></i>
          </div>
          <div class="text-sm font-semibold text-slate-700" id="drop-title">Drop the file here, or click to browse</div>
          <div class="text-xs text-slate-400 mt-1" id="drop-hint">.xlsx, .xlsm or .csv — up to 20 MB</div>
        </label>
        <input type="file" id="import-file" class="hidden" accept=".xlsx,.xlsm,.csv,.txt,.tsv">

        <div class="hidden mt-4" id="upload-progress">
          <div class="flex items-center gap-3 text-sm text-slate-600">
            <i class="fa-solid fa-spinner fa-spin"></i>
            <span>Reading the file…</span>
          </div>
        </div>

        <div class="hidden mt-4 p-3 rounded-lg bg-red-50 border border-red-100 text-sm text-red-700" id="upload-error"></div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-5 border-b border-slate-200">
        <h3 class="text-base font-semibold text-slate-900 tracking-tight">Before you start</h3>
      </div>
      <div class="p-5 space-y-3 text-sm text-slate-600">
        <p>Your own column names are fine — nothing has to be renamed. The next step shows every column and lets you point it at the right field.</p>
        <p>One row per customer. Any measurement columns on that row become a measurement sheet for them.</p>
        <p class="text-xs text-slate-400">Recognised headings include Name, Mobile No, City, Chest, Losing, Galla, Koni, Pancho and many spellings of each.</p>
        <a href="{{ route('customers.import.template') }}"
           class="inline-flex items-center gap-2 text-xs font-semibold text-slate-900 hover:underline pt-1">
          <i class="fa-solid fa-download text-[10px]"></i> Download a blank template
        </a>
      </div>
    </div>
  </div>
</div>

{{-- ------------------------------ STEP 2 ------------------------------ --}}
<div class="page wizard-step hidden" data-panel="2">
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-5">
    <div class="p-5 border-b border-slate-200 flex flex-wrap justify-between items-center gap-3">
      <div>
        <h3 class="text-base font-semibold text-slate-900 tracking-tight">Match your columns</h3>
        <p class="text-xs text-slate-500 mt-0.5" id="mapping-note">—</p>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" onclick="wizardBack(1)" class="h-9 px-3 border border-slate-200 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-50">Use a different file</button>
        <button type="button" onclick="startPass(true)" class="h-9 px-4 bg-slate-900 text-white rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2">
          <i class="fa-solid fa-list-check text-[10px]"></i> Check the file
        </button>
      </div>
    </div>

    <div class="p-5">
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-x-8 gap-y-1" id="mapping-grid"></div>
    </div>

    <div class="px-5 pb-5">
      <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 text-xs text-slate-500" id="unmapped-note"></div>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-5">
    <div class="p-5 border-b border-slate-200">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">How should the import behave?</h3>
    </div>
    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">A customer is already on file</label>
        <select id="opt-existing" class="w-full h-9 px-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
          <option value="fill_blanks">Fill in blank fields only — never overwrite</option>
          <option value="overwrite">Replace their details with the file's</option>
          <option value="skip">Leave them alone and skip the row</option>
        </select>
        <p class="text-[11px] text-slate-400 mt-1">Matched on the phone number, ignoring spaces, dashes and +92.</p>
      </div>

      <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">A row has no phone number</label>
        <select id="opt-phone" class="w-full h-9 px-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
          <option value="skip">Skip it and list it in the report</option>
          <option value="placeholder">Import anyway with a placeholder number</option>
        </select>
        <p class="text-[11px] text-slate-400 mt-1">A placeholder can be replaced later from the customer's page.</p>
      </div>

      <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Garment when the file does not say</label>
        <input type="text" id="opt-garment" value="General" maxlength="255"
               class="w-full h-9 px-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
      </div>

      <div>
        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Measurement unit when the file does not say</label>
        <select id="opt-unit" class="w-full h-9 px-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
          <option value="in">Inches</option>
          <option value="cm">Centimetres</option>
        </select>
      </div>

      <div class="md:col-span-2">
        <label class="flex items-start gap-2.5 cursor-pointer">
          <input type="checkbox" id="opt-skip-dupe-sheets" class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
          <span class="text-sm text-slate-600">
            Don't add a measurement sheet if the customer already has one for the same garment
            <span class="block text-[11px] text-slate-400">Useful when re-running an import that was interrupted.</span>
          </span>
        </label>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200">
      <h3 class="text-base font-semibold text-slate-900 tracking-tight">First rows of your file</h3>
      <p class="text-xs text-slate-500 mt-0.5">Exactly as they were read — check nothing has shifted a column</p>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-xs">
        <thead class="bg-slate-50 text-slate-500 uppercase tracking-widest border-b border-slate-200"><tr id="preview-head"></tr></thead>
        <tbody class="divide-y divide-slate-100" id="preview-body"></tbody>
      </table>
    </div>
  </div>
</div>

{{-- --------------------------- STEP 3 & 4 ----------------------------- --}}
<div class="page wizard-step hidden" data-panel="3">
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center">
      <div>
        <h3 class="text-base font-semibold text-slate-900 tracking-tight" id="pass-title">Checking the file</h3>
        <p class="text-xs text-slate-500 mt-0.5" id="pass-subtitle">Nothing is saved during this pass</p>
      </div>
      <div class="text-sm font-bold text-slate-900" id="pass-percent">0%</div>
    </div>

    <div class="p-5">
      <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
        <div class="h-full bg-slate-900 rounded-full transition-all duration-200" id="pass-bar" style="width:0%"></div>
      </div>
      <p class="text-xs text-slate-500 mt-2" id="pass-progress">Starting…</p>

      <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mt-5" id="pass-tiles"></div>

      <div class="hidden mt-5 p-4 rounded-lg border" id="pass-verdict"></div>

      <div class="flex flex-wrap items-center gap-2 mt-5 hidden" id="pass-actions"></div>
    </div>

    <div class="border-t border-slate-200 hidden" id="issues-block">
      <div class="p-5 border-b border-slate-100 flex justify-between items-center">
        <div>
          <h4 class="text-sm font-semibold text-slate-900">What needs your attention</h4>
          <p class="text-xs text-slate-500 mt-0.5" id="issues-note"></p>
        </div>
        <a href="#" id="issues-download" class="text-xs font-semibold text-slate-900 hover:underline flex items-center gap-1.5">
          <i class="fa-solid fa-download text-[10px]"></i> Download full report
        </a>
      </div>
      <div class="overflow-x-auto max-h-96">
        <table class="w-full text-xs">
          <thead class="bg-slate-50 text-slate-500 uppercase tracking-widest border-b border-slate-200 sticky top-0">
            <tr>
              <th class="px-5 py-2.5 text-left w-20">Row</th>
              <th class="px-5 py-2.5 text-left w-24">Level</th>
              <th class="px-5 py-2.5 text-left">Customer</th>
              <th class="px-5 py-2.5 text-left">What happened</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100" id="issues-body"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  var FIELDS = @json($fields);
  var CHUNK = @json($chunk);

  var wiz = {
    token: null, headers: [], preview: [], total: 0,
    mapping: {}, running: false, lastDry: true, result: null,
  };

  /* ------------------------------ step rail ------------------------------ */
  function setStep(step) {
    // Steps 3 and 4 are the same panel — the check and the real run show the
    // identical progress bar, tiles and issue list. Without this mapping the
    // real import switched to a panel that does not exist and the page went
    // blank at the very moment it had something to report.
    const panelFor = step === 4 ? 3 : step;

    document.querySelectorAll('.wizard-step').forEach(panel => {
      panel.classList.toggle('hidden', Number(panel.dataset.panel) !== panelFor);
    });

    document.querySelectorAll('#wizard-rail [data-step]').forEach(node => {
      const index = Number(node.dataset.step);
      const dot = node.querySelector('.step-dot');
      const label = node.querySelector('.step-label');
      const line = node.querySelector('.step-line');

      const active = index === step || (step === 4 && index === 3);
      const passed = index < step;

      dot.className = `w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 step-dot ${
        passed ? 'bg-emerald-500 text-white' : active ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-400'}`;
      dot.innerHTML = passed ? '<i class="fa-solid fa-check text-[10px]"></i>' : index;

      label.className = `text-xs font-semibold step-label whitespace-nowrap ${
        passed || active ? 'text-slate-900' : 'text-slate-400'}`;

      if (line) line.className = `flex-1 h-px mx-3 step-line ${passed ? 'bg-emerald-400' : 'bg-slate-200'}`;
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  window.wizardBack = function (step) {
    if (wiz.running) return;
    setStep(step);
  };

  /* ------------------------------- upload -------------------------------- */
  function bindUpload() {
    const input = document.getElementById('import-file');
    const zone = document.getElementById('drop-zone');
    if (!input || !zone) return;

    input.addEventListener('change', () => {
      if (input.files.length) sendFile(input.files[0]);
    }, { signal: Atelier.pageSignal() });

    ['dragenter', 'dragover'].forEach(type => {
      zone.addEventListener(type, (e) => {
        e.preventDefault();
        zone.classList.add('border-slate-900', 'bg-slate-50');
      }, { signal: Atelier.pageSignal() });
    });

    ['dragleave', 'drop'].forEach(type => {
      zone.addEventListener(type, (e) => {
        e.preventDefault();
        zone.classList.remove('border-slate-900', 'bg-slate-50');
      }, { signal: Atelier.pageSignal() });
    });

    zone.addEventListener('drop', (e) => {
      if (e.dataTransfer.files.length) sendFile(e.dataTransfer.files[0]);
    }, { signal: Atelier.pageSignal() });
  }

  async function sendFile(file) {
    const error = document.getElementById('upload-error');
    const busy = document.getElementById('upload-progress');

    error.classList.add('hidden');
    busy.classList.remove('hidden');
    document.getElementById('drop-title').textContent = file.name;

    const body = new FormData();
    body.append('file', file);

    try {
      const res = await fetch('{{ route('customers.import.upload') }}', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body,
      });

      const payload = await res.json();

      if (!res.ok) throw new Error(payload.message || 'The file could not be read.');

      wiz.token = payload.token;
      wiz.headers = payload.headers;
      wiz.preview = payload.preview;
      wiz.total = payload.total;
      wiz.mapping = payload.mapping;

      renderMapping();
      renderPreview();

      document.getElementById('mapping-note').textContent =
        `${payload.file} — ${payload.total.toLocaleString()} row(s), ${payload.headers.length} column(s)`;

      setStep(2);
      toast('File read. Check the column matches below.', 'success');
    } catch (err) {
      error.textContent = err.message;
      error.classList.remove('hidden');
      document.getElementById('drop-title').textContent = 'Drop the file here, or click to browse';
    } finally {
      busy.classList.add('hidden');
      document.getElementById('import-file').value = '';
    }
  }

  /* ------------------------------ mapping -------------------------------- */
  function renderMapping() {
    const grid = document.getElementById('mapping-grid');
    const groups = { customer: 'Customer details', measurement: 'Measurements' };
    let html = '';

    Object.entries(groups).forEach(([group, title]) => {
      html += `<div class="xl:col-span-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest pt-4 pb-2 first:pt-0">${title}</div>`;

      Object.entries(FIELDS).filter(([, f]) => f.group === group).forEach(([key, field]) => {
        const options = wiz.headers.map((header, i) =>
          `<option value="${i}" ${wiz.mapping[key] === i ? 'selected' : ''}>${Atelier.escapeHtml(header || `Column ${i + 1}`)}</option>`
        ).join('');

        html += `
          <div class="flex items-center gap-3 py-1.5 border-b border-slate-50">
            <label class="w-44 shrink-0 text-sm text-slate-700">
              ${Atelier.escapeHtml(field.label)}${field.required ? '<span class="text-red-500 ml-0.5">*</span>' : ''}
            </label>
            <select data-field="${key}" onchange="onMappingChange(this)"
                    class="flex-1 h-8 px-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-slate-900">
              <option value="">— not in my file —</option>
              ${options}
            </select>
            <span class="w-28 shrink-0 text-[11px] text-slate-400 truncate" data-sample="${key}"></span>
          </div>`;
      });
    });

    grid.innerHTML = html;
    Object.keys(FIELDS).forEach(updateSample);
    updateUnmapped();
  }

  window.onMappingChange = function (select) {
    const field = select.dataset.field;
    wiz.mapping[field] = select.value === '' ? null : Number(select.value);
    updateSample(field);
    updateUnmapped();
  };

  function updateSample(field) {
    const node = document.querySelector(`[data-sample="${field}"]`);
    if (!node) return;

    const index = wiz.mapping[field];

    if (index === null || index === undefined) {
      node.textContent = '';
      return;
    }

    const sample = (wiz.preview.find(row => (row[index] || '').trim() !== '') || [])[index] || '';
    node.textContent = sample ? `e.g. ${sample}` : 'all blank';
    node.className = `w-28 shrink-0 text-[11px] truncate ${sample ? 'text-slate-400' : 'text-amber-500'}`;
  }

  function updateUnmapped() {
    const used = new Set(Object.values(wiz.mapping).filter(v => v !== null && v !== undefined));
    const spare = wiz.headers.map((h, i) => (used.has(i) ? null : h || `Column ${i + 1}`)).filter(Boolean);
    const note = document.getElementById('unmapped-note');

    note.innerHTML = spare.length
      ? `<i class="fa-solid fa-circle-info mr-1.5"></i>${spare.length} column(s) will be ignored: ${spare.map(Atelier.escapeHtml).join(', ')}`
      : '<i class="fa-solid fa-circle-check text-emerald-500 mr-1.5"></i>Every column in your file is being used.';
  }

  function renderPreview() {
    document.getElementById('preview-head').innerHTML =
      wiz.headers.map(h => `<th class="px-3 py-2 text-left font-semibold whitespace-nowrap">${Atelier.escapeHtml(h || '—')}</th>`).join('');

    document.getElementById('preview-body').innerHTML = wiz.preview.map(row => `
      <tr class="hover:bg-slate-50">
        ${wiz.headers.map((_, i) => `<td class="px-3 py-1.5 whitespace-nowrap text-slate-600">${Atelier.escapeHtml(row[i] || '')}</td>`).join('')}
      </tr>`).join('');
  }

  function options() {
    return {
      existing: document.getElementById('opt-existing').value,
      missing_phone: document.getElementById('opt-phone').value,
      default_garment: document.getElementById('opt-garment').value,
      default_unit: document.getElementById('opt-unit').value,
      skip_duplicate_measurements: document.getElementById('opt-skip-dupe-sheets').checked,
    };
  }

  /* ------------------------------- passes -------------------------------- */
  window.startPass = async function (dryRun) {
    if (wiz.running) return;

    if (wiz.mapping.name === null || wiz.mapping.name === undefined) {
      toast('Choose which column holds the customer name.', 'error');
      return;
    }

    wiz.running = true;
    wiz.lastDry = dryRun;
    setStep(dryRun ? 3 : 4);

    document.getElementById('pass-title').textContent = dryRun ? 'Checking the file' : 'Importing';
    document.getElementById('pass-subtitle').textContent = dryRun
      ? 'A rehearsal — nothing is saved yet'
      : 'Writing to the database. Keep this page open until it finishes.';

    document.getElementById('pass-verdict').classList.add('hidden');
    document.getElementById('pass-actions').classList.add('hidden');
    document.getElementById('issues-block').classList.add('hidden');
    document.getElementById('issues-body').innerHTML = '';

    const totals = { read: 0, created: 0, updated: 0, skipped: 0, failed: 0, measurements: 0 };
    const issues = [];
    let offset = 0;

    renderTiles(totals, dryRun);

    try {
      while (true) {
        const res = await Atelier.api.post('{{ route('customers.import.run') }}', {
          token: wiz.token,
          offset,
          dry_run: dryRun,
          mapping: wiz.mapping,
          options: options(),
        });

        Object.keys(totals).forEach(key => { totals[key] += res.summary[key] || 0; });

        if (issues.length < 400) issues.push(...res.issues);
        offset = res.processed;

        setProgress(res.processed, res.total);
        renderTiles(totals, dryRun);
        renderIssues(issues, totals);

        if (res.done) break;
      }

      wiz.result = totals;
      finishPass(totals, dryRun);
    } catch (err) {
      document.getElementById('pass-progress').textContent = 'Stopped.';
      Atelier.reportError(err, 'The import could not be completed');
      showActions(dryRun, true);
    } finally {
      wiz.running = false;
    }
  };

  function setProgress(done, total) {
    const percent = total > 0 ? Math.min(Math.round(done / total * 100), 100) : 100;
    document.getElementById('pass-bar').style.width = percent + '%';
    document.getElementById('pass-percent').textContent = percent + '%';
    document.getElementById('pass-progress').textContent =
      `${done.toLocaleString()} of ${total.toLocaleString()} row(s)`;
  }

  function renderTiles(t, dryRun) {
    const tiles = [
      ['New customers', t.created, 'text-emerald-600'],
      [dryRun ? 'Already on file' : 'Updated', t.updated, 'text-sky-600'],
      ['Measurement sheets', t.measurements, 'text-indigo-600'],
      ['Skipped', t.skipped, 'text-slate-500'],
      ['Problems', t.failed, t.failed > 0 ? 'text-red-500' : 'text-slate-500'],
    ];

    document.getElementById('pass-tiles').innerHTML = tiles.map(([label, value, cls]) => `
      <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">${label}</div>
        <div class="text-xl font-bold ${cls} mt-0.5">${Number(value).toLocaleString()}</div>
      </div>`).join('');
  }

  function renderIssues(issues, totals) {
    if (!issues.length) return;

    const block = document.getElementById('issues-block');
    block.classList.remove('hidden');

    document.getElementById('issues-note').textContent =
      `${issues.length}${issues.length >= 400 ? '+' : ''} note(s) — the full list is in the report`;

    document.getElementById('issues-download').href =
      `{{ url('customers/import') }}/${wiz.token}/issues?dry=${wiz.lastDry ? 1 : 0}`;

    const tone = { error: 'text-red-600 bg-red-50', warning: 'text-amber-600 bg-amber-50', info: 'text-slate-500 bg-slate-100' };

    document.getElementById('issues-body').innerHTML = issues.slice(-200).map(issue => `
      <tr class="hover:bg-slate-50">
        <td class="px-5 py-2 text-slate-400 font-mono">${issue.line}</td>
        <td class="px-5 py-2"><span class="px-1.5 py-0.5 rounded text-[10px] font-semibold ${tone[issue.level] || tone.info}">${issue.level}</span></td>
        <td class="px-5 py-2 text-slate-700">${Atelier.escapeHtml(issue.name || '—')}<span class="text-slate-400 ml-1.5">${Atelier.escapeHtml(issue.phone || '')}</span></td>
        <td class="px-5 py-2 text-slate-600">${Atelier.escapeHtml(issue.message)}</td>
      </tr>`).join('');
  }

  function finishPass(t, dryRun) {
    const verdict = document.getElementById('pass-verdict');
    verdict.classList.remove('hidden');

    if (dryRun) {
      const clean = t.failed === 0;
      verdict.className = `mt-5 p-4 rounded-lg border ${clean ? 'bg-emerald-50 border-emerald-100' : 'bg-amber-50 border-amber-100'}`;
      verdict.innerHTML = `
        <div class="flex items-start gap-3">
          <i class="fa-solid ${clean ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-amber-600'} mt-0.5"></i>
          <div class="text-sm ${clean ? 'text-emerald-800' : 'text-amber-800'}">
            <strong>${clean ? 'The file is ready to import.' : 'The file can be imported, with notes.'}</strong>
            <div class="mt-1">
              ${t.created.toLocaleString()} customer(s) will be added,
              ${t.updated.toLocaleString()} already on file,
              ${t.measurements.toLocaleString()} measurement sheet(s) will be created.
              ${t.failed > 0 ? `<strong>${t.failed.toLocaleString()} row(s) will be left out</strong> — see the list below.` : ''}
            </div>
          </div>
        </div>`;
      document.getElementById('pass-title').textContent = 'Check complete';
      document.getElementById('pass-subtitle').textContent = 'Nothing has been saved yet';

      toast(clean
        ? `Check complete — ${t.created.toLocaleString()} customer(s) ready to import.`
        : `Check complete — ${t.failed.toLocaleString()} row(s) need attention.`,
        clean ? 'success' : 'info');
    } else {
      verdict.className = 'mt-5 p-4 rounded-lg border bg-emerald-50 border-emerald-100';
      verdict.innerHTML = `
        <div class="flex items-start gap-3">
          <i class="fa-solid fa-circle-check text-emerald-600 mt-0.5"></i>
          <div class="text-sm text-emerald-800">
            <strong>Import finished.</strong>
            <div class="mt-1">
              ${t.created.toLocaleString()} customer(s) added,
              ${t.updated.toLocaleString()} updated,
              ${t.measurements.toLocaleString()} measurement sheet(s) created.
            </div>
          </div>
        </div>`;
      document.getElementById('pass-title').textContent = 'Import finished';
      document.getElementById('pass-subtitle').textContent = 'Your customer book is in the system';

      const headline = `Import finished — ${t.created.toLocaleString()} added, `
        + `${t.updated.toLocaleString()} updated, ${t.measurements.toLocaleString()} measurement sheet(s).`;

      toast(headline, 'success');

      // A long import is something people walk away from, so it also rings,
      // raises a desktop notification and lands in the bell — all of which
      // respect whatever the Notifications panel has switched on.
      Atelier.notify('Customer import finished', headline);

      Atelier.api.post('{{ route('customers.import.finish') }}', {
        token: wiz.token,
        created: t.created,
        updated: t.updated,
        sheets: t.measurements,
        skipped: t.skipped,
        failed: t.failed,
      })
        .then(() => Atelier.refreshCounters())
        .catch(() => {});
    }

    showActions(dryRun, false);
  }

  function showActions(dryRun, failed) {
    const box = document.getElementById('pass-actions');
    box.classList.remove('hidden');

    if (dryRun) {
      box.innerHTML = `
        <button type="button" onclick="wizardBack(2)" class="h-9 px-3 border border-slate-200 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-50">Back to matching</button>
        <button type="button" onclick="confirmImport()" class="h-9 px-4 bg-slate-900 text-white rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2">
          <i class="fa-solid fa-database text-[10px]"></i> Import for real
        </button>`;
    } else {
      box.innerHTML = `
        <a href="{{ route('customers.index') }}" class="h-9 px-4 bg-slate-900 text-white rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2">
          <i class="fa-solid fa-users text-[10px]"></i> Open the customer directory
        </a>
        <a href="{{ route('measurements.index') }}" class="h-9 px-3 border border-slate-200 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-50 flex items-center gap-2">
          <i class="fa-solid fa-ruler text-[10px]"></i> View measurements
        </a>
        <button type="button" onclick="wizardBack(1)" class="h-9 px-3 border border-slate-200 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-50">Import another file</button>`;
    }
  }

  window.confirmImport = function () {
    const t = wiz.result || { created: 0, updated: 0 };

    Atelier.confirm({
      variant: 'approve',
      title: 'Import this file?',
      message: `${t.created.toLocaleString()} customer(s) will be added and ${t.updated.toLocaleString()} existing record(s) touched. This cannot be undone in one click.`,
      confirmLabel: 'Import now',
      onConfirm: () => startPass(false),
    });
  };

  Atelier.onPageReady(() => {
    bindUpload();
    setStep(1);
  });
</script>
@endpush
