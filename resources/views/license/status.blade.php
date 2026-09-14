@php
  $licenseStatus = app(\App\Services\Licensing\LicenseChecker::class)->check();
  try { $licenseMachine = app(\App\Services\Licensing\MachineIdentity::class)->id(); }
  catch (\Throwable) { $licenseMachine = 'Unavailable'; }
@endphp
<section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6" aria-labelledby="license-status-title">
  <div class="flex items-center justify-between gap-4 mb-4">
    <h2 id="license-status-title" class="text-sm font-bold text-slate-900 flex items-center gap-2"><x-icon name="settings" class="text-indigo-500" />Software License</h2>
    <a href="{{ route('license.show') }}" data-no-spa class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Manage license</a>
  </div>
  <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
    <div><dt class="text-xs text-slate-500 mb-1">License Type</dt><dd class="font-medium text-slate-900">{{ $licenseStatus->license['type'] ?? 'Not activated' }}</dd></div>
    <div><dt class="text-xs text-slate-500 mb-1">Status</dt><dd class="font-medium {{ $licenseStatus->valid() ? 'text-emerald-600' : 'text-amber-600' }}">{{ $licenseStatus->valid() ? 'Active' : ucfirst($licenseStatus->status) }}</dd></div>
    <div><dt class="text-xs text-slate-500 mb-1">Expiry Date</dt><dd class="font-medium text-slate-900">{{ ($licenseStatus->license['type'] ?? null) === 'Lifetime' ? 'Never Expires' : (isset($licenseStatus->license['expires_at']) ? gmdate('Y-m-d', $licenseStatus->license['expires_at'] - 1).' (UTC)' : '—') }}</dd></div>
    <div class="sm:col-span-3"><dt class="text-xs text-slate-500 mb-1">Machine ID</dt><dd class="text-xs font-mono text-slate-700 break-all select-all">{{ $licenseMachine }}</dd></div>
  </dl>
</section>
