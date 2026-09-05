@php
  /**
   * Shop details the client runtime needs so receipts and invoices can be
   * rendered without a round-trip. Built here rather than inline in the script,
   * because Blade's @json directive cannot parse a multi-line array literal.
   */
  $shopConfig = [
      'name'     => $appSettings['store_name'] ?: 'Atelier',
      'tagline'  => $appSettings['tagline'],
      'address'  => $appSettings['address'],
      'phone'    => $appSettings['phone'],
      'whatsapp' => $appSettings['whatsapp_number'] ?: $appSettings['phone'],
      'email'    => $appSettings['email'],
      'website'  => $appSettings['website'],
      'logo'     => $appSettings['logo_path'],
      'stamp'    => $appSettings['stamp_path'],
      'footer'   => $appSettings['receipt_footer'],
      'terms'    => $appSettings['invoice_terms'],
      'currency' => $appSettings['currency'],
      'orderPrefix'   => $appSettings['order_prefix'],
      'invoicePrefix' => $appSettings['invoice_prefix'],
      // Billing rules the client needs so previews match the server exactly.
      'tax' => [
          'enabled'   => (bool) $appSettings['tax_enabled'],
          'rate'      => (float) $appSettings['tax_rate'],
          'label'     => $appSettings['tax_label'],
          'inclusive' => (bool) $appSettings['tax_inclusive'],
      ],
      'serviceCharge' => [
          'enabled' => (bool) $appSettings['service_charge_enabled'],
          'rate'    => (float) $appSettings['service_charge_rate'],
          'label'   => $appSettings['service_charge_label'],
      ],
      'allowPartial' => (bool) $appSettings['allow_partial'],
      'receipt'      => \App\Services\Settings::receipt(),
      'measurement'  => [
          'unit'     => $appSettings['measurement_unit'],
          'decimals' => (int) $appSettings['measurement_decimals'],
          'required' => \App\Services\Settings::requiredMeasurementFields(),
      ],
      'deliverySlots'    => \App\Services\Settings::list('delivery_slots'),
      'extensionReasons' => \App\Services\Settings::list('extension_reasons'),
      'whatsappEnabled'  => (bool) $appSettings['whatsapp_enabled'],
      'emailEnabled'     => (bool) $appSettings['email_enabled'],
      'smsEnabled'       => (bool) $appSettings['sms_enabled'],
  ];
@endphp
<!DOCTYPE html>
<html lang="{{ $appDisplay['locale'] }}" dir="{{ $appDisplay['direction'] }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Atelier — Admin Suite')</title>

  {{-- Installable desktop app --}}
  <link rel="manifest" href="{{ route('pwa.manifest') }}">
  <meta name="theme-color" content="#0F172A">
  <meta name="application-name" content="{{ $appSettings['store_name'] ?: 'Atelier' }}">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="{{ $appSettings['store_name'] ?: 'Atelier' }}">
  <meta name="mobile-web-app-capable" content="yes">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
  {{-- Tailwind, Font Awesome, Inter and Chart.js are all bundled locally, so
       the app renders identically with no internet connection. If the build
       has not been run yet we fall back to the CDNs. --}}
  @if (file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  @else
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  @endif
  <style id="app-base-styles">
    /* ==========================================================
       THEME TOKENS
       Light values are the defaults; `html.theme-dark` swaps the
       same names so every surface below follows automatically.
       ========================================================== */
    :root {
      --brand: #4F46E5;
      --brand-contrast: #ffffff;

      --bg-app: #F8FAFC;
      --bg-surface: #ffffff;
      --bg-subtle: #F8FAFC;
      --bg-muted: #F1F5F9;
      --bg-inset: #F8FAFC;

      --text-strong: #0F172A;
      --text-body: #334155;
      --text-muted: #64748B;
      --text-faint: #94A3B8;

      --border: #E2E8F0;
      --border-soft: #F1F5F9;

      --shadow-sm: 0 1px 2px rgba(15, 23, 42, .06);
      --shadow-md: 0 4px 12px rgba(15, 23, 42, .08);
      --shadow-lg: 0 25px 50px -12px rgba(15, 23, 42, .25);

      --row-py: 0.75rem;
    }

    html.theme-dark {
      --brand-contrast: #ffffff;

      --bg-app: #0B1120;
      --bg-surface: #111827;
      --bg-subtle: #0F172A;
      --bg-muted: #1E293B;
      --bg-inset: #0F172A;

      --text-strong: #F1F5F9;
      --text-body: #CBD5E1;
      --text-muted: #94A3B8;
      --text-faint: #64748B;

      --border: #1E293B;
      --border-soft: #172033;

      --shadow-sm: 0 1px 2px rgba(0, 0, 0, .4);
      --shadow-md: 0 4px 12px rgba(0, 0, 0, .45);
      --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, .7);

      color-scheme: dark;
    }

    /* Compact tables: one token, applied by every table rule below. */
    html.compact-tables { --row-py: 0.375rem; }

    body {
      font-family: 'Inter', sans-serif;
      background-color: var(--bg-app);
      color: var(--text-body);
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      transition: background-color .25s ease, color .25s ease;
    }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--text-faint); }

    /* ==========================================================
       DARK MODE SURFACE MAPPING
       The pages are written in Tailwind utilities. Rather than
       rewrite every page, the utilities that describe a surface
       are re-pointed at the theme tokens while dark is active.
       ========================================================== */
    html.theme-dark .bg-white,
    html.theme-dark .bg-slate-50,
    html.theme-dark .bg-slate-100 { background-color: var(--bg-surface) !important; }

    html.theme-dark main,
    html.theme-dark .bg-slate-50\/50 { background-color: var(--bg-app) !important; }

    /* Nested panels sit one step lighter than the card they're inside. */
    html.theme-dark .bg-white .bg-slate-50,
    html.theme-dark .bg-white .bg-slate-100,
    html.theme-dark .bg-slate-50 .bg-white { background-color: var(--bg-muted) !important; }

    html.theme-dark .text-slate-900,
    html.theme-dark .text-slate-800 { color: var(--text-strong) !important; }
    html.theme-dark .text-slate-700,
    html.theme-dark .text-slate-600 { color: var(--text-body) !important; }
    html.theme-dark .text-slate-500 { color: var(--text-muted) !important; }
    html.theme-dark .text-slate-400,
    html.theme-dark .text-slate-300 { color: var(--text-faint) !important; }

    html.theme-dark .border-slate-200,
    html.theme-dark .border-slate-100,
    html.theme-dark .border-slate-300,
    html.theme-dark .divide-slate-200 > * + *,
    html.theme-dark .divide-slate-100 > * + * { border-color: var(--border) !important; }

    html.theme-dark .hover\:bg-slate-50:hover,
    html.theme-dark .hover\:bg-slate-100:hover { background-color: var(--bg-muted) !important; }

    /* Form controls */
    html.theme-dark input,
    html.theme-dark select,
    html.theme-dark textarea {
      background-color: var(--bg-inset) !important;
      border-color: var(--border) !important;
      color: var(--text-strong) !important;
    }
    html.theme-dark input::placeholder,
    html.theme-dark textarea::placeholder { color: var(--text-faint) !important; }
    html.theme-dark select option { background-color: var(--bg-surface); color: var(--text-strong); }
    html.theme-dark input[type="date"]::-webkit-calendar-picker-indicator,
    html.theme-dark input[type="time"]::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.6); }

    /* Dark buttons need to invert or they vanish into the background. */
    html.theme-dark .bg-slate-900 { background-color: var(--brand) !important; color: #fff !important; }
    html.theme-dark .hover\:bg-slate-800:hover { background-color: var(--brand) !important; filter: brightness(1.15); }

    /* Status badges keep their hue but lose the bright paper backgrounds. */
    html.theme-dark .badge-pending   { background: rgba(245, 158, 11, .15); color: #FBBF24; }
    html.theme-dark .badge-progress  { background: rgba(14, 165, 233, .15); color: #38BDF8; }
    html.theme-dark .badge-trial     { background: rgba(139, 92, 246, .15); color: #A78BFA; }
    html.theme-dark .badge-ready     { background: rgba(2, 132, 199, .15);  color: #38BDF8; }
    html.theme-dark .badge-delivered { background: rgba(16, 185, 129, .15); color: #34D399; }
    html.theme-dark .badge-overdue   { background: rgba(239, 68, 68, .15);  color: #F87171; }
    html.theme-dark .badge-vip       { background: rgba(217, 119, 6, .15);  color: #FBBF24; border-color: rgba(217,119,6,.35); }
    html.theme-dark .badge-notified  { background: rgba(5, 150, 105, .15);  color: #34D399; border-color: rgba(5,150,105,.35); }

    /* Tinted callouts */
    html.theme-dark .bg-indigo-50  { background-color: rgba(79, 70, 229, .12) !important; }
    html.theme-dark .bg-emerald-50 { background-color: rgba(16, 185, 129, .12) !important; }
    html.theme-dark .bg-rose-50,
    html.theme-dark .bg-red-50     { background-color: rgba(239, 68, 68, .12) !important; }
    html.theme-dark .bg-amber-50   { background-color: rgba(245, 158, 11, .12) !important; }
    html.theme-dark .bg-sky-50     { background-color: rgba(14, 165, 233, .12) !important; }
    html.theme-dark .bg-violet-50  { background-color: rgba(139, 92, 246, .12) !important; }
    html.theme-dark .border-indigo-100 { border-color: rgba(79, 70, 229, .3) !important; }
    html.theme-dark .border-red-200,
    html.theme-dark .border-red-300    { border-color: rgba(239, 68, 68, .35) !important; }
    html.theme-dark .text-indigo-800   { color: #C7D2FE !important; }

    /* Chrome */
    html.theme-dark .modal,
    html.theme-dark .modal-card,
    html.theme-dark .drawer { background: var(--bg-surface); color: var(--text-body); }
    html.theme-dark .drawer { border-left-color: var(--border); }
    html.theme-dark .modal-backdrop,
    html.theme-dark .modal-overlay { background: rgba(2, 6, 23, .7); }
    html.theme-dark thead { background-color: var(--bg-muted) !important; }
    html.theme-dark canvas { filter: brightness(.95); }

    /* Brand colour: driven by the Theme & Display picker. */
    .nav-item.active::before { background-color: var(--brand); }
    #sidebar .nav-item.active { background-color: var(--brand) !important; }
    .avatar { background: var(--brand); }
    .toggle.on { background: var(--brand); }

    /* Compact tables */
    table td, table th { padding-top: var(--row-py); padding-bottom: var(--row-py); }
    html.compact-tables table td,
    html.compact-tables table th { padding-top: var(--row-py) !important; padding-bottom: var(--row-py) !important; }
    html.compact-tables .avatar { width: 28px; height: 28px; font-size: 11px; }
    html.compact-tables .avatar.sm { width: 24px; height: 24px; font-size: 10px; }

    /* Right-to-left languages */
    html[dir="rtl"] .ml-64 { margin-left: 0; margin-right: 16rem; }
    html[dir="rtl"] #sidebar { left: auto; right: 0; border-right: none; border-left: 1px solid var(--sb-border, #e2e8f0); }
    html[dir="rtl"] .drawer { right: auto; left: 0; border-left: none; border-right: 1px solid var(--border); transform: translateX(-105%); }
    html[dir="rtl"] .drawer.show { transform: translateX(0); }
    html[dir="rtl"] .nav-item.active::before { left: auto; right: 0; border-radius: 3px 0 0 3px; }
    html[dir="rtl"] #toast { right: auto; left: 2rem; }

    .nav-item {
      transition: all 0.2s ease;
      position: relative;
    }
    .nav-item.active {
      background-color: #F1F5F9;
      color: #0F172A;
      font-weight: 600;
    }
    .nav-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 60%;
      background-color: #4F46E5;
      border-radius: 0 3px 3px 0;
    }
    .nav-item:not(.active):hover {
      background-color: #F8FAFC;
      color: #0F172A;
    }

    /* Badges */
    .badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 3px 8px; border-radius: 6px;
      font-size: 11px; font-weight: 600;
    }
    .badge::before {
      content: ''; width: 6px; height: 6px;
      border-radius: 50%; background: currentColor;
    }
    .badge-pending { background: #FFFBEB; color: #F59E0B; }
    .badge-progress { background: #F0F9FF; color: #0EA5E9; }
    .badge-trial { background: #F5F3FF; color: #8B5CF6; }
    .badge-ready { background: #E0F2FE; color: #0284C7; }
    .badge-delivered { background: #ECFDF5; color: #10B981; }
    .badge-overdue { background: #FEF2F2; color: #EF4444; }
    .badge-vip { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }
    .badge-vip::before { display: none; }
    .badge-notified { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
    .badge-notified::before { display: none; }

    /* Avatar */
    .avatar {
      width: 36px; height: 36px; border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 600; color: #fff; background: #4F46E5;
    }
    .avatar.sm { width: 32px; height: 32px; font-size: 12px; }
    .avatar.lg { width: 48px; height: 48px; font-size: 16px; }
    .avatar.pink { background: #EC4899; }
    .avatar.green { background: #10B981; }
    .avatar.orange { background: #F59E0B; }
    .avatar.blue { background: #3B82F6; }
    .avatar.purple { background: #8B5CF6; }

    /* Modal */
    .modal-backdrop {
      position: fixed; inset: 0;
      background: rgba(15, 23, 42, .5);
      backdrop-filter: blur(8px);
      z-index: 50; display: flex;
      align-items: center; justify-content: center;
      opacity: 0; pointer-events: none;
      transition: opacity .2s;
      padding: 1rem;
    }
    .modal-backdrop.show { opacity: 1; pointer-events: auto; }
    .modal {
      background: #fff; border-radius: 16px;
      width: 100%; max-width: 560px;
      max-height: 88vh; overflow: hidden;
      display: flex; flex-direction: column;
      transform: scale(.96) translateY(10px);
      transition: transform .3s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    .modal.modal-lg { max-width: 800px; }
    .modal.modal-xl { max-width: 1000px; }
    .modal-backdrop.show .modal { transform: scale(1) translateY(0); }

    /* Drawer */
    .drawer {
      position: fixed; top: 0; right: 0; bottom: 0;
      width: 400px; background: #fff;
      border-left: 1px solid #E2E8F0; z-index: 45;
      transform: translateX(105%);
      transition: transform .3s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex; flex-direction: column;
      box-shadow: -10px 0 40px -5px rgba(0, 0, 0, 0.1);
    }
    .drawer.show { transform: translateX(0); }
    .drawer.drawer-centered {
      top: 50%; right: auto; left: 50%; bottom: auto;
      transform: translate(-50%, -50%) scale(0.95) !important;
      border: 1px solid var(--border); border-radius: 1rem;
      max-height: calc(100vh - 2rem);
      width: auto;
      max-width: 95vw;
      opacity: 0;
      pointer-events: none;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .drawer.drawer-centered.show {
      transform: translate(-50%, -50%) scale(1) !important;
      opacity: 1;
      pointer-events: auto;
    }
    .drawer-overlay {
      position: fixed; inset: 0;
      background: rgba(15, 23, 42, .3);
      backdrop-filter: blur(2px);
      z-index: 44; opacity: 0; pointer-events: none;
      transition: opacity .2s;
    }
    .drawer-overlay.show { opacity: 1; pointer-events: auto; }

    /* === PROFESSIONAL TOAST STYLES === */
    .font-display { font-family: 'Sora', sans-serif; }

    .toast-success, .toast-info, .toast-warning, .toast-error {
      color: #ffffff;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 10px -2px rgba(0, 0, 0, 0.04);
      position: relative;
      overflow: hidden;
    }
    .toast-success::before, .toast-info::before, .toast-warning::before, .toast-error::before {
      content: '';
      position: absolute;
      top: 0; bottom: 0; left: 0;
      width: 4px;
      background: rgba(255, 255, 255, 0.3);
    }
    .toast-success { background: #047857; }
    .toast-info    { background: #4338ca; }
    .toast-warning { background: #b45309; }
    .toast-error   { background: #be123c; }

    @keyframes toastIn {
      0% { opacity: 0; transform: translateX(120%) scale(0.95); }
      100% { opacity: 1; transform: translateX(0) scale(1); }
    }
    .toast-anim-in { animation: toastIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

    /* === CONFIRMATION MODAL STYLES === */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background-color: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 120;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      padding: 1rem;
    }
    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    .modal-card {
      background: #ffffff;
      border-radius: 1rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      width: 100%;
      max-width: 440px;
      padding: 2rem;
      transform: scale(0.95) translateY(20px);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      overflow: hidden;
    }
    .modal-overlay.active .modal-card {
      transform: scale(1) translateY(0);
    }

    /* Toggle */
    .toggle {
      width: 38px; height: 22px;
      background: #E2E8F0; border-radius: 11px;
      position: relative; cursor: pointer;
      transition: background .2s; flex-shrink: 0;
    }
    .toggle::after {
      content: ''; position: absolute;
      top: 2px; left: 2px;
      width: 18px; height: 18px;
      background: #fff; border-radius: 50%;
      transition: transform .2s;
      box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
    }
    .toggle.on { background: #4F46E5; }
    .toggle.on::after { transform: translateX(16px); }

    /* === INSTALLED APP MODE ===
       Applied only when launched from the desktop icon, never in a browser
       tab, so the in-browser design is untouched. */
    html.app-standalone {
      /* Kill the rubber-band overscroll that makes a PWA feel like a webpage. */
      overscroll-behavior: none;
    }
    html.app-standalone body {
      /* Text selection is for documents, not app chrome. */
      -webkit-user-select: none;
      user-select: none;
      cursor: default;
    }
    /* Anywhere the user genuinely reads or edits, selection stays on. */
    html.app-standalone input,
    html.app-standalone textarea,
    html.app-standalone select,
    html.app-standalone [contenteditable],
    html.app-standalone table,
    html.app-standalone .thermal-receipt,
    html.app-standalone #printable-receipt,
    html.app-standalone #printable-invoice {
      -webkit-user-select: text;
      user-select: text;
    }
    /* Native apps don't show a focus ring on mouse clicks. */
    html.app-standalone :focus:not(:focus-visible) { outline: none; }

    /* Page Transition */
    @keyframes pageIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .page { animation: pageIn 0.4s ease forwards; }

    /* ==========================================================
       CLOTH STORE — shared control styles

       These exist so pages stop re-declaring a dozen Tailwind classes for
       every button and input, which is how the module drifted out of step
       with the Tailor system in the first place. Colours are expressed as
       theme tokens, so all of it follows dark mode automatically.
       ========================================================== */

    /* --- Buttons --- */
    .btn-cs,
    .btn-cs-primary,
    .btn-cs-ghost,
    .btn-cs-danger {
      display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
      padding: .5rem .875rem;
      border-radius: .5rem;
      font-size: .75rem;
      font-weight: 500;
      line-height: 1.25rem;
      white-space: nowrap;
      cursor: pointer;
      transition: background-color .15s ease, border-color .15s ease,
                  color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .btn-cs-primary {
      background: var(--text-strong);
      color: var(--bg-surface);
      border: 1px solid transparent;
      box-shadow: var(--shadow-sm);
    }
    .btn-cs-primary:hover { filter: brightness(1.15); }

    .btn-cs-ghost {
      background: var(--bg-surface);
      color: var(--text-body);
      border: 1px solid var(--border);
      box-shadow: var(--shadow-sm);
    }
    .btn-cs-ghost:hover { background: var(--bg-muted); color: var(--text-strong); }

    .btn-cs-danger {
      background: #E11D48; color: #fff; border: 1px solid transparent;
      box-shadow: var(--shadow-sm);
    }
    .btn-cs-danger:hover { filter: brightness(1.08); }

    .btn-cs-primary:active,
    .btn-cs-ghost:active,
    .btn-cs-danger:active { transform: translateY(1px); }

    .btn-cs-primary:disabled,
    .btn-cs-ghost:disabled { opacity: .6; cursor: not-allowed; transform: none; }

    /* Small square icon button used in table action columns. */
    .btn-cs-icon {
      width: 2rem; height: 2rem;
      display: inline-flex; align-items: center; justify-content: center;
      border-radius: .5rem;
      color: var(--text-faint);
      transition: background-color .15s ease, color .15s ease;
      cursor: pointer;
    }
    .btn-cs-icon:hover { background: var(--bg-muted); color: var(--text-strong); }
    .btn-cs-icon.danger:hover { background: rgba(225, 29, 72, .1); color: #E11D48; }

    /* --- Form controls ---

       Deliberately NOT setting width here. This stylesheet is inlined after
       the compiled Tailwind CSS, so `.input-cs { width: 100% }` outranked
       every `w-40` / `w-44` utility (same specificity, later wins) and every
       sized filter control stretched to full width, stacking the toolbars
       into one control per row.

       Width is now the caller's job: use `w-full` for a full-width field, or
       a sized wrapper for a narrow one.
    */
    .input-cs {
      padding: .5rem .75rem;
      background: var(--bg-inset);
      border: 1px solid var(--border);
      border-radius: .5rem;
      font-size: .875rem;
      color: var(--text-strong);
      transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
    }
    .input-cs:focus {
      outline: none;
      background: var(--bg-surface);
      border-color: var(--text-strong);
      box-shadow: 0 0 0 2px color-mix(in srgb, var(--text-strong) 18%, transparent);
    }
    .input-cs::placeholder { color: var(--text-faint); }

    .label-cs {
      display: block;
      font-size: .6875rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: .05em;
      margin-bottom: .375rem;
    }

    /* --- Tables --- */
    /* One place that sets table rhythm, so every Cloth Store table matches. */
    .table-cs { width: 100%; font-size: .875rem; text-align: left; border-collapse: collapse; }

    .table-cs thead th {
      background: var(--bg-subtle);
      color: var(--text-muted);
      font-size: .625rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .1em;
      padding: .75rem 1.25rem;
      white-space: nowrap;
      border-bottom: 1px solid var(--border);
    }

    .table-cs tbody td {
      padding: .75rem 1.25rem;
      color: var(--text-body);
      vertical-align: middle;
    }

    .table-cs tbody tr { border-top: 1px solid var(--border-soft); }
    .table-cs tbody tr:first-child { border-top: none; }
    .table-cs tbody tr { transition: background-color .12s ease; }
    .table-cs tbody tr:hover { background: var(--bg-subtle); }

    /* Reveal row actions on hover — keeps dense tables calm at rest. */
    .table-cs .row-actions { opacity: 0; transition: opacity .15s ease; }
    .table-cs tbody tr:hover .row-actions,
    .table-cs tbody tr:focus-within .row-actions { opacity: 1; }
    @media (hover: none) { .table-cs .row-actions { opacity: 1; } }

    .cell-strong { color: var(--text-strong); font-weight: 600; }
    .cell-muted  { color: var(--text-muted); font-size: .75rem; }
    .cell-num    { font-variant-numeric: tabular-nums; }

    /* --- Misc --- */
    .badge-neutral { background: var(--bg-muted); color: var(--text-muted); }

    /* Staggered entrance for card grids. */
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(8px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .card-in { animation: cardIn .35s cubic-bezier(.16,1,.3,1) both; }

    /* ==========================================================
       POINT OF SALE — motion

       The till is the one screen used hundreds of times a day, so the
       feedback has to be immediate and legible: a line lands in the cart,
       a quantity ticks up, a sale lands. Durations are deliberately short
       (150–450ms) — anything slower gets in a cashier's way.
       ========================================================== */

    /* A line arriving in the cart. */
    @keyframes cartItemIn {
      from { opacity: 0; transform: translateX(24px) scale(.97); }
      to   { opacity: 1; transform: translateX(0) scale(1); }
    }
    .cart-item-in { animation: cartItemIn .28s cubic-bezier(.16,1,.3,1) both; }

    /* A line leaving. Collapsing the height stops the list from jumping. */
    @keyframes cartItemOut {
      from { opacity: 1; transform: translateX(0); max-height: 120px; margin-bottom: .5rem; }
      to   { opacity: 0; transform: translateX(40px); max-height: 0; margin-bottom: 0;
             padding-top: 0; padding-bottom: 0; border-width: 0; }
    }
    .cart-item-out { animation: cartItemOut .25s ease-in forwards; overflow: hidden; }

    /* Existing line topped up rather than added — pulse instead of re-entering. */
    @keyframes cartBump {
      0%   { transform: scale(1); }
      40%  { transform: scale(1.03); background-color: color-mix(in srgb, #6366F1 8%, transparent); }
      100% { transform: scale(1); }
    }
    .cart-bump { animation: cartBump .35s ease-out; }

    /* Product tile acknowledging a tap. */
    @keyframes tileTap {
      0%   { transform: scale(1); }
      45%  { transform: scale(.95); }
      100% { transform: scale(1); }
    }
    .tile-tap { animation: tileTap .22s ease-out; }

    /* Totals recalculating. */
    @keyframes totalPop {
      0%   { transform: scale(1); }
      50%  { transform: scale(1.06); }
      100% { transform: scale(1); }
    }
    .total-pop { animation: totalPop .3s cubic-bezier(.16,1,.3,1); }

    /* --- Step transition --- */
    .pos-step {
      position: absolute; inset: 0;
      transition: transform .45s cubic-bezier(.65,0,.35,1), opacity .3s ease;
    }
    .pos-step.is-active   { transform: translateX(0);     opacity: 1; pointer-events: auto; }
    .pos-step.is-next     { transform: translateX(100%);  opacity: 0; pointer-events: none; }
    .pos-step.is-previous { transform: translateX(-6%);   opacity: 0; pointer-events: none; }

    /* --- Stepper --- */
    .pos-stepper-dot {
      width: 1.75rem; height: 1.75rem;
      display: inline-flex; align-items: center; justify-content: center;
      border-radius: 9999px;
      font-size: .6875rem; font-weight: 700;
      background: var(--bg-muted); color: var(--text-faint);
      border: 1px solid var(--border);
      transition: background-color .25s ease, color .25s ease,
                  border-color .25s ease, transform .25s ease;
    }
    .pos-stepper-step.is-active .pos-stepper-dot {
      background: var(--text-strong); color: var(--bg-surface);
      border-color: var(--text-strong); transform: scale(1.08);
    }
    .pos-stepper-step.is-done .pos-stepper-dot {
      background: #10B981; color: #fff; border-color: #10B981;
    }
    .pos-stepper-step.is-active .pos-stepper-label { color: var(--text-strong); font-weight: 600; }
    /* Fixed-width connectors keep the three steps evenly spaced. With flex:1
       they stretched to fill whatever space was left, so the gaps changed
       size depending on the label lengths and the steps looked misaligned. */
    .pos-stepper-line {
      flex: 0 0 auto; width: 2.5rem; height: 2px;
      background: var(--border); border-radius: 2px;
      position: relative; overflow: hidden;
    }
    @media (min-width: 640px) { .pos-stepper-line { width: 3.5rem; } }
    .pos-stepper-line::after {
      content: ''; position: absolute; inset: 0;
      background: #10B981; transform: scaleX(0); transform-origin: left;
      transition: transform .45s cubic-bezier(.65,0,.35,1);
    }
    .pos-stepper-step.is-done + .pos-stepper-line::after { transform: scaleX(1); }

    /* --- Payment method cards --- */
    .pay-method {
      border: 2px solid var(--border);
      background: var(--bg-surface);
      border-radius: 1rem;
      padding: 1rem;
      display: flex; align-items: center; gap: 1rem;
      text-align: left; cursor: pointer;
      transition: border-color .2s ease, background-color .2s ease,
                  transform .15s ease, box-shadow .2s ease;
    }
    .pay-method:hover { border-color: var(--text-faint); transform: translateY(-2px); }
    .pay-method.active {
      border-color: var(--text-strong);
      background: color-mix(in srgb, var(--text-strong) 4%, transparent);
      box-shadow: var(--shadow-md);
    }
    /* Stacked variant: icon above the label, so four methods fit across a
       wide payment pane without the labels truncating. */
    .pay-method.stacked {
      flex-direction: column;
      align-items: flex-start;
      gap: .625rem;
    }

    .pay-method .pay-icon {
      width: 2.5rem; height: 2.5rem; border-radius: .75rem;
      display: flex; align-items: center; justify-content: center;
      background: var(--bg-muted); color: var(--text-muted);
      transition: background-color .2s ease, color .2s ease, transform .2s ease;
    }
    .pay-method.active .pay-icon {
      background: var(--text-strong); color: var(--bg-surface); transform: scale(1.06);
    }

    /* --- Success screen --- */
    @keyframes successPop {
      0%   { transform: scale(.4); opacity: 0; }
      60%  { transform: scale(1.08); opacity: 1; }
      100% { transform: scale(1); opacity: 1; }
    }
    .success-pop { animation: successPop .5s cubic-bezier(.16,1,.3,1) both; }

    /* Expanding ring behind the tick. */
    @keyframes successRing {
      from { transform: scale(.6); opacity: .55; }
      to   { transform: scale(2.1); opacity: 0; }
    }
    .success-ring { animation: successRing 1s ease-out .2s both; }

    /* Draws the tick itself. */
    @keyframes drawCheck { to { stroke-dashoffset: 0; } }
    .success-check path {
      stroke-dasharray: 48; stroke-dashoffset: 48;
      animation: drawCheck .4s ease-out .35s forwards;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .fade-up { animation: fadeUp .45s cubic-bezier(.16,1,.3,1) both; }

    /* Skeleton shimmer while the product grid loads. */
    @keyframes shimmer { 100% { transform: translateX(100%); } }
    .skeleton-tile {
      position: relative; overflow: hidden;
      background: var(--bg-muted); border-radius: 1rem;
    }
    .skeleton-tile::after {
      content: ''; position: absolute; inset: 0; transform: translateX(-100%);
      background: linear-gradient(90deg, transparent,
                  color-mix(in srgb, var(--bg-surface) 60%, transparent), transparent);
      animation: shimmer 1.4s infinite;
    }

    @media (prefers-reduced-motion: reduce) {
      .page, .card-in, .cart-item-in, .cart-bump, .tile-tap, .total-pop,
      .success-pop, .success-ring, .fade-up, .skeleton-tile::after { animation: none; }
      .success-check path { stroke-dashoffset: 0; animation: none; }
      .cart-item-out { animation-duration: .01s; }
      .pos-step { transition-duration: .01s; }
      .btn-cs-primary:active, .btn-cs-ghost:active, .btn-cs-danger:active { transform: none; }
      .pay-method:hover { transform: none; }
    }

    /* MOD 1: Thermal Receipt */
    .thermal-receipt {
      max-width: 320px; margin: 0 auto;
      font-family: 'Courier New', Courier, monospace;
      text-align: center;
      border-top: 2px dashed #cbd5e1;
      border-bottom: 2px dashed #cbd5e1;
      padding: 16px 0; color: #111;
    }
    .thermal-receipt div { margin-bottom: 4px; font-size: 13px; }

    /* MOD 5: Rubber Stamp */
    .stamp {
      transform: rotate(-15deg);
      border: 3px solid #10B981;
      color: #10B981;
      padding: 4px 12px;
      font-weight: 800; font-size: 18px;
      text-transform: uppercase;
      border-radius: 4px; opacity: 0.8;
      display: inline-block;
      font-family: 'Courier New', Courier, monospace;
    }

    /* MOD 3: Pulsing Dot */
    @keyframes pulse {
      0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
      70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
      100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .pulse-dot { animation: pulse 1.5s infinite; }

    /* === SIDEBAR THEME SYSTEM === */
    #sidebar {
      background-color: var(--sb-bg, #ffffff) !important;
      border-right-color: var(--sb-border, #e2e8f0) !important;
      transition: background-color 0.35s ease, border-color 0.35s ease;
    }
    #sidebar .sb-head { border-bottom-color: var(--sb-border, #e2e8f0) !important; transition: border-color 0.35s; }
    #sidebar .sb-logo { background-color: var(--sb-logo-bg, #0f172a) !important; transition: background-color 0.35s; }
    #sidebar .sb-name { color: var(--sb-text, #0f172a) !important; transition: color 0.35s; }
    #sidebar .sb-sub  { color: var(--sb-muted, #94a3b8) !important; transition: color 0.35s; }
    #sidebar .sb-label { color: var(--sb-muted, #94a3b8) !important; transition: color 0.35s; }
    #sidebar .nav-item { color: var(--sb-muted, #64748b) !important; }
    #sidebar .nav-item:not(.active):hover { background-color: var(--sb-hover, #f8fafc) !important; color: var(--sb-text, #0f172a) !important; }
    #sidebar .nav-item.active { background-color: var(--sb-fill, #4F46E5) !important; color: #fff !important; font-weight: 600; border-radius: 8px; }
    #sidebar .nav-item.active::before { display: none; }
    #sidebar .sb-badge { background-color: var(--sb-active, #f1f5f9) !important; color: var(--sb-muted, #64748b) !important; transition: all 0.35s; }
    #sidebar .sb-foot { border-top-color: var(--sb-border, #e2e8f0) !important; transition: border-color 0.35s; }
    #sidebar .sb-user-link:hover { background-color: var(--sb-hover, #f8fafc) !important; }
    #sidebar .avatar { background-color: var(--sb-logo-bg, #0f172a) !important; transition: background-color 0.35s; }
    #sidebar .sb-uname { color: var(--sb-text, #0f172a) !important; transition: color 0.35s; }
    #sidebar .sb-urole { color: var(--sb-muted, #94a3b8) !important; transition: color 0.35s; }
    #sidebar .fa-ellipsis-vertical { color: var(--sb-muted, #94a3b8) !important; }

    /* Quick palette popover & swatches */
    #sb-palette-popover {
      position: absolute; bottom: calc(100% + 8px); left: 50%;
      transform: translateX(-50%) scale(0.95); opacity: 0; pointer-events: none;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); z-index: 60;
    }
    #sb-palette-popover.show { transform: translateX(-50%) scale(1); opacity: 1; pointer-events: auto; }
    .sb-swatch {
      width: 36px; height: 36px; border-radius: 10px; cursor: pointer;
      border: 3px solid transparent; transition: all 0.15s ease; position: relative;
    }
    .sb-swatch:hover { transform: scale(1.15); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .sb-swatch.active { border-color: #4F46E5; box-shadow: 0 0 0 3px rgba(79,70,229,0.25); }
    .sb-swatch .sb-check {
      position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
      font-size: 11px; opacity: 0; transition: opacity 0.15s;
    }
    .sb-swatch.active .sb-check { opacity: 1; }

    /* ==================================================================
       Print
       ------------------------------------------------------------------
       Printing this app used to reproduce the entire interface on paper:
       the sidebar, the sticky header, drawers, toasts, modals and every
       button came out alongside the report. On paper only the content is
       wanted, so the chrome is dropped, the fixed shell is unpinned and
       backgrounds are forced to render instead of printing as blank.

       Anything that is interface rather than content can opt out with
       class="no-print"; anything that belongs only on paper (a heading
       that repeats the selected date range, for instance) uses
       class="print-only".
       ================================================================== */
    .print-only { display: none !important; }

    @media print {
      @page { size: A4 portrait; margin: 12mm 10mm; }

      html, body {
        background: #fff !important;
        width: auto !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      /* Keep badge and header fills instead of printing empty boxes. */
      *, *::before, *::after {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      /* --- application chrome, never printed --------------------------- */
      #sidebar,
      #app > div > header,
      .drawer,
      .drawer-overlay,
      #spa-drawers,
      #toast,
      #confirmation-modal,
      .modal-overlay,
      .modal-backdrop,
      #sb-palette-popover,
      .no-print,
      [data-no-print] { display: none !important; }

      /* --- unpin the fixed app shell ----------------------------------- */
      #app { display: block !important; min-height: 0 !important; }
      #app > div { margin-left: 0 !important; width: 100% !important; }
      .ml-64 { margin-left: 0 !important; }
      #spa-main, main {
        padding: 0 !important;
        background: #fff !important;
        overflow: visible !important;
      }

      .print-only { display: block !important; }

      /* --- content ----------------------------------------------------- */
      .shadow-sm, .shadow, .shadow-md, .shadow-lg, .shadow-xl { box-shadow: none !important; }
      .rounded-xl, .rounded-2xl { border-radius: 4px !important; }
      .hover\:-translate-y-0\.5:hover { transform: none !important; }

      /* Rows, cards and charts should not be sliced across two sheets. */
      table { width: 100% !important; page-break-inside: auto; }
      thead { display: table-header-group; }
      tr, img, canvas { page-break-inside: avoid; }
      h1, h2, h3 { page-break-after: avoid; }

      /* Denser tables so a period's rows fit on far fewer sheets. */
      td, th { padding: 3px 6px !important; font-size: 10px !important; }

      /* Scroll containers must spill their full contents onto the page. */
      .overflow-x-auto, .overflow-y-auto, .overflow-hidden, .overflow-auto { overflow: visible !important; }

      a[href]::after { content: none !important; }

      /* The reports page hides all but the open tab; on paper there is
         nothing to click, so every panel has to be visible at once. */
      .tab-panel { display: block !important; page-break-inside: auto; }
      .tab-panel + .tab-panel { margin-top: 14px; }
    }
  </style>
  <script id="sidebar-theme-init">
    // Sidebar Theme — applied before first paint to prevent flash.
    (function() {
      var T = window.SIDEBAR_THEMES = {
        white:    { n:'Classic White', bg:'#ffffff', text:'#0f172a', muted:'#64748b', border:'#e2e8f0', hover:'#f8fafc', active:'#eef2ff', accent:'#4F46E5', fill:'#4F46E5', logoBg:'#0f172a', logoText:'#fff', swatch:'#ffffff' },
        slate:    { n:'Slate',         bg:'#0f172a', text:'#e2e8f0', muted:'#94a3b8', border:'#1e293b', hover:'#1e293b', active:'#1e2544', accent:'#818cf8', fill:'#4f46e5', logoBg:'#1e293b', logoText:'#e2e8f0', swatch:'#0f172a' },
        graphite: { n:'Graphite',      bg:'#1c1c1e', text:'#e5e5e7', muted:'#8e8e93', border:'#2c2c2e', hover:'#2c2c2e', active:'#2a2a30', accent:'#64b5f6', fill:'#2563eb', logoBg:'#2c2c2e', logoText:'#e5e5e7', swatch:'#1c1c1e' },
        navy:     { n:'Navy',          bg:'#1b2a4a', text:'#dce4f0', muted:'#8899b3', border:'#243556', hover:'#243556', active:'#253e68', accent:'#7dd3fc', fill:'#2563eb', logoBg:'#243556', logoText:'#dce4f0', swatch:'#1b2a4a' },
        midnight: { n:'Midnight',      bg:'#111827', text:'#e5e7eb', muted:'#9ca3af', border:'#1f2937', hover:'#1f2937', active:'#1f1b3d', accent:'#a78bfa', fill:'#7c3aed', logoBg:'#1f2937', logoText:'#e5e7eb', swatch:'#111827' },
        espresso: { n:'Espresso',      bg:'#1a1512', text:'#e8e0d8', muted:'#a09080', border:'#2a231e', hover:'#2a231e', active:'#352a1e', accent:'#d4a574', fill:'#92400e', logoBg:'#2a231e', logoText:'#e8e0d8', swatch:'#1a1512' },
        steel:    { n:'Steel',         bg:'#293548', text:'#dce2ec', muted:'#8494a7', border:'#354460', hover:'#354460', active:'#3a5068', accent:'#5eead4', fill:'#0d9488', logoBg:'#354460', logoText:'#dce2ec', swatch:'#293548' },
        onyx:     { n:'Onyx',          bg:'#09090b', text:'#e4e4e7', muted:'#71717a', border:'#18181b', hover:'#18181b', active:'#1e1e22', accent:'#fbbf24', fill:'#b45309', logoBg:'#18181b', logoText:'#e4e4e7', swatch:'#09090b' },
      };

      /* Display preferences live in the database so they follow the account
         across devices. They are applied here, before first paint, to avoid a
         flash of the previous theme. */
      var D = window.APP_DISPLAY = @json($appDisplay);

      function applySidebar(key) {
        var t = T[key] || T.white;
        var s = document.documentElement.style;
        s.setProperty('--sb-bg', t.bg);
        s.setProperty('--sb-text', t.text);
        s.setProperty('--sb-muted', t.muted);
        s.setProperty('--sb-border', t.border);
        s.setProperty('--sb-hover', t.hover);
        s.setProperty('--sb-active', t.active);
        s.setProperty('--sb-accent', t.accent);
        s.setProperty('--sb-logo-bg', t.logoBg);
        s.setProperty('--sb-logo-text', t.logoText);
        s.setProperty('--sb-fill', t.fill);
        D.sidebarTheme = key;
      }

      /** Resolves light/dark/system into the class the stylesheet keys off. */
      function prefersDark(mode) {
        return mode === 'dark'
          || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
      }

      function applyDisplay(overrides) {
        Object.assign(D, overrides || {});

        var root = document.documentElement;
        root.classList.toggle('theme-dark', prefersDark(D.colorMode));
        root.classList.toggle('compact-tables', !!D.compactTables);
        root.style.setProperty('--brand', D.primaryColor || '#4F46E5');

        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', prefersDark(D.colorMode) ? '#0B1120' : '#0F172A');

        applySidebar(D.sidebarTheme);
      }

      window.applySidebarTheme = function (key) { applyDisplay({ sidebarTheme: key }); };
      window.getActiveSidebarTheme = function () { return D.sidebarTheme || 'white'; };
      window.applyDisplaySettings = applyDisplay;

      applyDisplay();

      // "System" has to keep tracking the OS after the page has loaded.
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if (D.colorMode === 'system') applyDisplay();
      });
    })();
  </script>
  @stack('styles')
</head>

<body class="text-slate-800">
  <div id="app" class="flex min-h-screen">
    @include('cloth-store.layouts.sidebar')

    <div class="flex-1 ml-64 flex flex-col">
      @include('cloth-store.layouts.header')

      <main class="p-8 flex-1 bg-slate-50" id="spa-main">
        @if(session('success'))
          <script>
            document.addEventListener('DOMContentLoaded', function() {
              toast('{{ session('success') }}', 'success');
            });
          </script>
        @endif
        @if(session('error'))
          <script>
            document.addEventListener('DOMContentLoaded', function() {
              toast('{{ session('error') }}', 'error');
            });
          </script>
        @endif
        @yield('content')
      </main>
    </div>
  </div>

  <div class="drawer-overlay" id="drawer-overlay" onclick="closeDrawers()"></div>

  <!-- Notifications Drawer -->
  @php
    $drawerNotifications = $layoutNotifications ?? collect();
    $drawerUnread = $layoutCounters['unread_notifications'] ?? 0;
    $notifPalette = [
      'success' => ['#ECFDF5', '#10B981'], 'danger'  => ['#FEF2F2', '#EF4444'],
      'warning' => ['#FFFBEB', '#F59E0B'], 'info'    => ['#F0F9FF', '#0EA5E9'],
      'purple'  => ['#F5F3FF', '#8B5CF6'], 'primary' => ['#EEF2FF', '#4F46E5'],
    ];
  @endphp
  <div class="drawer" id="notif-drawer">
    <div class="flex items-center justify-between p-5 border-b border-slate-200">
      <div>
        <div class="text-base font-semibold text-slate-900">Notifications</div>
        <div class="text-xs text-slate-500"><span data-badge="unread_notifications">{{ $drawerUnread }}</span> unread</div>
      </div>
      <button class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-500 hover:bg-slate-100" onclick="closeDrawers()"><i class="fa-solid fa-xmark text-sm"></i></button>
    </div>
    <div class="flex-1 overflow-y-auto" id="notif-list">
        @forelse($drawerNotifications as $n)
        @php
          [$bg, $fg] = $notifPalette[$n->color] ?? $notifPalette['info'];
          $iconClass = $n->icon ?: 'fa-solid fa-circle-info';
        @endphp
        <div class="px-4 py-3 border-b border-slate-100 cursor-pointer hover:bg-slate-50 transition flex items-start gap-3" onclick="Atelier.openNotification({{ $n->id }}, '{{ $n->action_url }}')">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:{{ $bg }}; color:{{ $fg }}">
            <i class="{{ $iconClass }} text-xs"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
              <div class="text-sm font-semibold text-slate-900">{{ $n->title ?: $n->type }}</div>
              @if(!$n->is_read)
              <span class="w-2 h-2 rounded-full bg-indigo-600 flex-shrink-0 mt-1.5"></span>
              @endif
            </div>
            <div class="text-xs mt-0.5 text-slate-500">{{ $n->message }}</div>
            <div class="text-[11px] mt-1 text-slate-400">{{ $n->created_at->diffForHumans() }}</div>
          </div>
        </div>
        @empty
        <div class="px-4 py-10 text-center">
          <div class="w-11 h-11 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3"><i class="fa-regular fa-bell text-sm"></i></div>
          <div class="text-sm font-medium text-slate-500">You're all caught up</div>
          <div class="text-xs text-slate-400 mt-0.5">New notifications will appear here</div>
        </div>
        @endforelse
    </div>
  </div>

  <div id="spa-drawers">@yield('drawers')</div>

  <!-- Modals -->
  <div class="modal-backdrop" id="modal-backdrop" onclick="if(event.target===this)closeModal()">
    <div class="modal" id="modal-content"></div>
  </div>

  <!-- ============================================== -->
  <!--        GLOBAL CONFIRMATION MODAL               -->
  <!-- ============================================== -->
  <div id="confirmation-modal" class="modal-overlay">
    <div class="modal-card">
      <!-- Close Button (Top Right) -->
      <button onclick="closeConfirmation()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-900 transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-100">
        <i class="fa-solid fa-xmark text-base"></i>
      </button>

      <!-- Modal Content -->
      <div class="flex flex-col items-center text-center">
        <div id="modal-icon-bg" class="w-16 h-16 rounded-full flex items-center justify-center mb-5">
          <i id="modal-icon" class="fa-solid fa-trash-can text-2xl"></i>
        </div>
        <h3 id="modal-title" class="text-xl font-bold text-slate-900 font-display tracking-tight mb-2">Are you sure?</h3>
        <p id="modal-msg" class="text-sm text-slate-500 leading-relaxed mb-8 max-w-xs">This action cannot be undone.</p>
      </div>

      <!-- Modal Footer Buttons -->
      <div class="flex justify-end gap-3">
        <!-- Cancel Button (Left) -->
        <button onclick="closeConfirmation()" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-semibold py-2.5 px-5 rounded-lg transition">
          Cancel
        </button>
        <!-- Confirm Button (Right) -->
        <button id="modal-confirm-btn" onclick="confirmAction()" class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold py-2.5 px-5 rounded-lg transition flex items-center gap-2 shadow-sm">
          <i class="fa-solid fa-check text-xs"></i> Confirm
        </button>
      </div>
    </div>
  </div>

  <!-- Floating Toast Element -->
  <div id="toast" class="fixed top-8 right-8 z-[130] hidden" style="transform: translateX(120%);">
    <div id="toast-wrapper" class="px-5 py-3.5 rounded-lg flex items-center gap-3 min-w-[280px]">
      <i id="toast-icon" class="fa-solid fa-circle-check text-white text-base"></i>
      <span id="toast-msg" class="text-sm font-medium tracking-tight text-white">Success!</span>
    </div>
  </div>

  <script id="app-runtime">
    /* ==========================================
       TOAST — single global notification
       ========================================== */
    function toast(message, type = 'info') {
      const toastEl = document.getElementById('toast');
      const wrapper = document.getElementById('toast-wrapper');
      const icon = document.getElementById('toast-icon');
      const msg = document.getElementById('toast-msg');

      if (!toastEl) return;

      // Legacy aliases used around the app.
      if (type === 'danger') type = 'error';
      if (!['success', 'info', 'warning', 'error'].includes(type)) type = 'info';

      toastEl.classList.remove('toast-anim-in');
      msg.textContent = message;
      wrapper.className = 'px-5 py-3.5 rounded-lg flex items-center gap-3 min-w-[280px]';

      if (type === 'success') {
        wrapper.classList.add('toast-success');
        icon.className = 'fa-solid fa-circle-check text-white text-base';
      } else if (type === 'info') {
        wrapper.classList.add('toast-info');
        icon.className = 'fa-solid fa-circle-info text-white text-base';
      } else if (type === 'warning') {
        wrapper.classList.add('toast-warning');
        icon.className = 'fa-solid fa-triangle-exclamation text-white text-base';
      } else if (type === 'error') {
        wrapper.classList.add('toast-error');
        icon.className = 'fa-solid fa-circle-xmark text-white text-base';
      }

      toastEl.classList.remove('hidden');
      void toastEl.offsetWidth;
      toastEl.classList.add('toast-anim-in');

      clearTimeout(window.toastTimer);
      window.toastTimer = setTimeout(() => {
        toastEl.style.transform = 'translateX(120%) scale(0.95)';
        toastEl.style.opacity = '0';
        toastEl.style.transition = 'all 0.3s ease-out';
        setTimeout(() => {
          toastEl.classList.add('hidden');
          toastEl.style.transform = '';
          toastEl.style.opacity = '';
          toastEl.style.transition = '';
        }, 300);
      }, 3000);
    }

    /* ==========================================
       CONFIRMATION MODAL — one dialog, many variants
       ========================================== */
    let currentActionConfig = null;

    /** Preset looks, matching the design's delete / approve / logout variants. */
    const CONFIRM_VARIANTS = {
      delete: {
        icon: 'fa-trash-can',
        iconColor: 'text-rose-600',
        iconBg: 'bg-rose-50',
        confirmText: 'Confirm Delete',
        confirmClass: 'bg-rose-600 hover:bg-rose-700 text-white shadow-rose-500/30',
      },
      approve: {
        icon: 'fa-circle-check',
        iconColor: 'text-emerald-600',
        iconBg: 'bg-emerald-50',
        confirmText: 'Approve',
        confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-500/30',
      },
      logout: {
        icon: 'fa-arrow-right-from-bracket',
        iconColor: 'text-amber-600',
        iconBg: 'bg-amber-50',
        confirmText: 'Logout Now',
        confirmClass: 'bg-amber-600 hover:bg-amber-700 text-white shadow-amber-500/30',
      },
      info: {
        icon: 'fa-circle-question',
        iconColor: 'text-indigo-600',
        iconBg: 'bg-indigo-50',
        confirmText: 'Confirm',
        confirmClass: 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-500/30',
      },
    };

    /**
     * showConfirmation({ variant, title, message, confirmLabel, onConfirm })
     *
     * `onConfirm` may be async; the dialog closes first, then it runs.
     */
    function showConfirmation(options = {}) {
      const modal = document.getElementById('confirmation-modal');
      const iconBg = document.getElementById('modal-icon-bg');
      const icon = document.getElementById('modal-icon');
      const title = document.getElementById('modal-title');
      const msg = document.getElementById('modal-msg');
      const confirmBtn = document.getElementById('modal-confirm-btn');

      if (!modal) return;

      const preset = CONFIRM_VARIANTS[options.variant] || CONFIRM_VARIANTS.delete;
      currentActionConfig = { ...preset, ...options };
      const c = currentActionConfig;

      iconBg.className = `w-16 h-16 rounded-full flex items-center justify-center mb-5 ${c.iconBg}`;
      icon.className = `fa-solid ${c.icon} text-2xl ${c.iconColor}`;
      title.textContent = c.title || 'Are you sure?';
      msg.textContent = c.message || 'This action cannot be undone.';

      confirmBtn.className = `${c.confirmClass} text-sm font-semibold py-2.5 px-5 rounded-lg transition flex items-center gap-2 shadow-md`;
      confirmBtn.innerHTML = `<i class="fa-solid fa-check text-xs"></i> ${c.confirmLabel || c.confirmText}`;

      modal.classList.add('active');
    }

    function closeConfirmation() {
      document.getElementById('confirmation-modal')?.classList.remove('active');
      currentActionConfig = null;
    }

    async function confirmAction() {
      const config = currentActionConfig;
      if (!config) return;

      closeConfirmation();

      // Let the close animation start before anything else happens.
      setTimeout(async () => {
        try {
          await config.onConfirm?.();
          if (config.successMessage) toast(config.successMessage, config.successType || 'success');
        } catch (err) {
          if (window.Atelier) Atelier.reportError(err);
          else toast('Something went wrong', 'error');
        }
      }, 150);
    }

    // Close when clicking the backdrop.
    document.addEventListener('click', function (e) {
      if (e.target === document.getElementById('confirmation-modal')) closeConfirmation();
    });

    // Close on Escape.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeConfirmation();
    });

    function openModal(name, data) {
      const content = document.getElementById('modal-content');
      if (typeof window.modals !== 'undefined' && window.modals[name]) {
        content.innerHTML = window.modals[name](data);
      } else {
        content.innerHTML = '<div class="p-6">Unknown modal</div>';
      }
      content.classList.remove('modal-xl', 'modal-lg');
      if (name === 'add-order-wizard' || name === 'order-details' || name === 'customer-360') {
        content.classList.add('modal-xl');
      } else if (name === 'add-measurement' || name === 'view-measurement') {
        content.classList.add('modal-lg');
      }
      document.getElementById('modal-backdrop').classList.add('show');
    }

    function closeModal() { document.getElementById('modal-backdrop').classList.remove('show') }

    function openDrawer(id, data = null) {
      if (window.drawers && window.drawers[id]) {
        let container = document.getElementById('spa-dynamic-drawer');
        if (!container) {
          container = document.createElement('div');
          container.id = 'spa-dynamic-drawer';
          document.getElementById('spa-drawers').appendChild(container);
        }
        
        container.className = 'drawer';
        const centeredDrawers = ['expense-form', 'due-collection'];
        if (centeredDrawers.includes(id)) {
          container.classList.add('drawer-centered');
        }

        container.innerHTML = window.drawers[id](data);
        id = 'spa-dynamic-drawer';
      }
      document.getElementById('drawer-overlay').classList.add('show');
      document.getElementById(id).classList.add('show');
    }
    
    function closeDrawers() {
      document.getElementById('drawer-overlay').classList.remove('show');
      document.querySelectorAll('.drawer').forEach(d => d.classList.remove('show'));
    }

    // Several pages (expenses, payments) call the singular name from inline
    // onclick handlers. Without this alias those Cancel/close buttons throw a
    // ReferenceError and the drawer stays stuck open.
    function closeDrawer() { closeDrawers(); }

    function animateCountUp(el, target, prefix = '', suffix = '') {
      if (!el) return;
      const duration = 1200;
      const start = 0;
      const startTime = performance.now();
      function update(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.floor(start + (target - start) * eased);
        el.textContent = prefix + current.toLocaleString('en-IN') + suffix;
        if (progress < 1) requestAnimationFrame(update);
      }
      requestAnimationFrame(update);
    }

    /* ==================================================================
       Atelier — shared client runtime
       Single place for API calls, state rendering and live polling so
       every page behaves consistently without duplicating logic.
       ================================================================== */
    window.Atelier = (function () {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

      /* Every configurable value the client needs, resolved server-side. */
      const display = window.APP_DISPLAY;
      const notificationPrefs = @json($notificationPrefs);
      const currency = display.currency || '₹';

      /* ---------------------------- API ---------------------------- */
      async function request(url, { method = 'GET', body = null, signal = null } = {}) {
        const options = {
          method,
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
          },
          signal,
        };

        if (body !== null) {
          options.headers['Content-Type'] = 'application/json';
          options.body = JSON.stringify(body);
        }

        const res = await fetch(url, options);

        // Any write invalidates every cached page.
        if (method !== 'GET') clearPageCache();

        const type = res.headers.get('content-type') || '';
        const payload = type.includes('application/json') ? await res.json() : null;

        if (!res.ok) {
          const error = new Error(payload?.message || 'Request failed');
          error.status = res.status;
          error.errors = payload?.errors || {};
          error.payload = payload;
          throw error;
        }

        return payload;
      }

      const api = {
        get:    (url, opts)       => request(url, { ...opts }),
        post:   (url, body, opts) => request(url, { method: 'POST', body, ...opts }),
        put:    (url, body, opts) => request(url, { method: 'PUT', body, ...opts }),
        patch:  (url, body, opts) => request(url, { method: 'PATCH', body, ...opts }),
        delete: (url, opts)       => request(url, { method: 'DELETE', ...opts }),
      };

      /**
       * Raw fetch with CSRF and JSON headers attached, returning the Response
       * untouched so callers can do `.then(res => res.json())`.
       *
       * Several Cloth Store pages (expenses, payments, purchase orders,
       * suppliers, settings) were already written against `Atelier.fetch`,
       * but it was never defined on this runtime — every one of those calls
       * threw "Atelier.fetch is not a function", which silently broke
       * recording a payment, receiving a purchase order, saving an expense
       * and saving settings.
       *
       * Prefer `Atelier.api.*` in new code: it parses JSON and throws typed
       * errors. This exists so the existing call sites work as written.
       */
      function rawFetch(url, { method = 'GET', body = null, headers = {}, ...rest } = {}) {
        const options = {
          method,
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
            ...headers,
          },
          ...rest,
        };

        if (body != null) {
          // Let FormData set its own multipart boundary.
          if (!(body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
          }
          options.body = body;
        }

        if (method !== 'GET') clearPageCache();

        return fetch(url, options);
      }

      /* ------------------------ Error surfacing --------------------- */
      function reportError(err, fallback = 'Something went wrong. Please try again.') {
        if (err?.name === 'AbortError') return;

        if (err?.errors && Object.keys(err.errors).length) {
          toast(Object.values(err.errors)[0][0], 'error');
          return;
        }

        toast(err?.message || fallback, 'error');
      }

      /* ------------------------- UI states -------------------------- */
      function skeletonRows(rows = 5, cols = 6) {
        return Array.from({ length: rows }).map(() => `
          <tr class="animate-pulse">
            ${Array.from({ length: cols }).map(() => `
              <td class="px-5 py-3"><div class="h-3 bg-slate-100 rounded w-full max-w-[120px]"></div></td>
            `).join('')}
          </tr>
        `).join('');
      }

      function emptyState({ icon = 'fa-inbox', title = 'Nothing here yet', message = '', action = '' } = {}) {
        return `
          <div class="py-14 px-6 text-center">
            <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
              <i class="fa-solid ${icon} text-base"></i>
            </div>
            <div class="text-sm font-semibold text-slate-700">${title}</div>
            ${message ? `<div class="text-xs text-slate-500 mt-1 max-w-xs mx-auto leading-relaxed">${message}</div>` : ''}
            ${action ? `<div class="mt-4">${action}</div>` : ''}
          </div>
        `;
      }

      function emptyRow(colspan, options = {}) {
        return `<tr><td colspan="${colspan}" class="p-0">${emptyState(options)}</td></tr>`;
      }

      function setBusy(el, busy) {
        if (!el) return;
        el.disabled = busy;
        el.classList.toggle('opacity-60', busy);
        el.classList.toggle('cursor-not-allowed', busy);

        if (busy) {
          el.dataset.originalHtml = el.innerHTML;
          el.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i>';
        } else if (el.dataset.originalHtml) {
          el.innerHTML = el.dataset.originalHtml;
          delete el.dataset.originalHtml;
        }
      }

      /* ------------------------ Formatting -------------------------- */
      const money = (v, decimals = false) =>
        currency + Number(v || 0).toLocaleString('en-IN', {
          minimumFractionDigits: decimals ? 2 : 0,
          maximumFractionDigits: decimals ? 2 : 0,
        });

      const initials = (name = '') =>
        (name.trim().split(/\s+/).filter(Boolean).map(n => n[0]).join('').slice(0, 2) || '?').toUpperCase();

      const escapeHtml = (str = '') => String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

      const badgeClass = (status) => ({
        'Pending': 'badge-pending', 'In Progress': 'badge-progress', 'Ready for Verification': 'badge-trial',
        'Ready': 'badge-ready', 'Delivered': 'badge-delivered', 'Completed': 'badge-delivered',
      }[status] || 'badge-overdue');

      /* ------------------------- Dates ------------------------------ */
      /**
       * Renders a date in the format chosen in Settings, in the shop's
       * timezone — so 'DD/MM/YYYY' really does change every date on screen.
       */
      const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

      function formatDate(value, fallback = '—') {
        if (!value) return fallback;

        const d = value instanceof Date ? value : new Date(value);
        if (isNaN(d.getTime())) return fallback;

        // Read the parts in the configured timezone, not the browser's.
        const parts = new Intl.DateTimeFormat('en-GB', {
          timeZone: display.timezone, year: 'numeric', month: '2-digit', day: '2-digit',
        }).formatToParts(d).reduce((acc, p) => (acc[p.type] = p.value, acc), {});

        const { day: DD, month: MM, year: YYYY } = parts;

        switch (display.dateFormat) {
          case 'MM/DD/YYYY': return `${MM}/${DD}/${YYYY}`;
          case 'YYYY-MM-DD': return `${YYYY}-${MM}-${DD}`;
          case 'D MMM YYYY': return `${Number(DD)} ${MONTHS[Number(MM) - 1]} ${YYYY}`;
          default:           return `${DD}/${MM}/${YYYY}`;
        }
      }

      function formatDateTime(value, fallback = '—') {
        if (!value) return fallback;

        const d = value instanceof Date ? value : new Date(value);
        if (isNaN(d.getTime())) return fallback;

        const time = new Intl.DateTimeFormat('en-US', {
          timeZone: display.timezone, hour: 'numeric', minute: '2-digit', hour12: true,
        }).format(d);

        return `${formatDate(d, fallback)}, ${time}`;
      }

      /* ----------------------- Pagination --------------------------- */
      /** Page size from Settings, so every table honours one number. */
      function rowsPerPage() {
        return Number(display.rowsPerPage) || 10;
      }

      /** Slices a list to the requested page using the configured size. */
      function paginate(rows, page = 1, size = null) {
        const perPage = size || rowsPerPage();
        const pages = Math.max(1, Math.ceil(rows.length / perPage));
        const current = Math.min(Math.max(1, page), pages);
        const start = (current - 1) * perPage;

        return {
          rows: rows.slice(start, start + perPage),
          page: current, pages, perPage, total: rows.length,
          from: rows.length ? start + 1 : 0,
          to: Math.min(start + perPage, rows.length),
        };
      }

      /* --------------------- Notification prefs --------------------- */
      /**
       * A desktop notification, but only if the shop enabled them and the
       * browser has already granted permission.
       */
      function notify(title, body, options = {}) {
        if (notificationPrefs.sound) playChime();

        if (!notificationPrefs.browser) return null;
        if (!('Notification' in window) || Notification.permission !== 'granted') return null;

        try {
          return new Notification(title, { body, icon: '/icons/favicon-32.png', ...options });
        } catch (e) {
          return null;
        }
      }

      /** Payment toast, suppressed when the shop switched them off. */
      function paymentToast(message, type = 'success') {
        if (notificationPrefs.paymentToasts) toast(message, type);
      }

      /* -------------------------- Polling --------------------------- */
      /**
       * Pollers are page-scoped: the router stops every one of them before it
       * swaps in a new page, so intervals never pile up across navigations.
       */
      let pollers = [];

      function poll(fn, interval = 30000) {
        let stopped = false;

        async function tick() {
          if (stopped || document.hidden) return;
          try { await fn(); } catch (e) { /* stay silent: polling must never nag */ }
        }

        const timer = setInterval(tick, interval);
        const stop = () => { stopped = true; clearInterval(timer); };

        pollers.push(stop);

        // Refresh immediately when the tab regains focus.
        document.addEventListener('visibilitychange', () => {
          if (!document.hidden) tick();
        }, { signal: pageSignal() });

        return stop;
      }

      function stopAllPolling() {
        pollers.forEach(stop => { try { stop(); } catch (e) {} });
        pollers = [];
      }

      /* --------------------- Page lifecycle hooks -------------------- */
      /**
       * Page scripts must not rely on DOMContentLoaded, because it only fires
       * on a hard load. `onPageReady` runs the callback on a hard load *and*
       * on every SPA navigation into that page.
       */
      let pageController = new AbortController();
      let pageReadyQueue = [];
      let pageIsReady = document.readyState !== 'loading';

      /** Pass to addEventListener so the router can detach page listeners. */
      function pageSignal() {
        return pageController.signal;
      }

      function onPageReady(callback) {
        if (pageIsReady) {
          // Defer a tick so the rest of the page script finishes defining things.
          Promise.resolve().then(() => { try { callback(); } catch (e) { console.error(e); } });
        } else {
          pageReadyQueue.push(callback);
        }
      }

      function runPageReady() {
        pageIsReady = true;
        const queue = pageReadyQueue;
        pageReadyQueue = [];
        queue.forEach(cb => { try { cb(); } catch (e) { console.error(e); } });
      }

      /** Called by the router immediately before a page is torn down. */
      function resetPageScope() {
        stopAllPolling();
        pageController.abort();
        pageController = new AbortController();
        pageIsReady = false;
        pageReadyQueue = [];
      }

      document.addEventListener('DOMContentLoaded', runPageReady);

      /* --------------------- Live sidebar counters ------------------- */
      async function refreshCounters() {
        const data = await api.get(@json(route('live.counters')));
        Object.entries(data.counters || {}).forEach(([key, value]) => {
          document.querySelectorAll(`[data-badge="${key}"]`).forEach(el => { el.textContent = value; });
        });

        const dot = document.getElementById('header-bell-dot');
        if (dot) dot.classList.toggle('hidden', !(data.counters?.unread_notifications > 0));
      }

      async function openNotification(id, url) {
        try { await api.post(`/notifications/${id}/read`); refreshCounters(); } catch (e) { /* non-blocking */ }
        if (url) window.location.href = url;
      }

      /* --------------------------- Forms ---------------------------- */
      /**
       * Turns any normal <form> into an AJAX submit: no navigation, no reload.
       * Method comes from the form's _method input or its method attribute.
       *
       *   Atelier.ajaxForm('#my-form', {
       *     onSuccess: (res, form) => { ... },
       *   });
       */
      function ajaxForm(selector, { onSuccess, onError, reset = true } = {}) {
        const form = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!form || form.dataset.ajaxBound) return form;

        form.dataset.ajaxBound = '1';

        form.addEventListener('submit', async (e) => {
          e.preventDefault();

          const submitBtn = form.querySelector('[type="submit"]');
          const data = Object.fromEntries(new FormData(form).entries());

          const method = (data._method || form.getAttribute('method') || 'POST').toUpperCase();
          delete data._method;
          delete data._token;

          clearFieldErrors(form);
          setBusy(submitBtn, true);

          try {
            const res = await request(form.action, {
              method: method === 'GET' ? 'GET' : method,
              body: method === 'GET' ? null : data,
            });

            if (reset && method === 'POST') form.reset();
            onSuccess?.(res, form);
          } catch (err) {
            showFieldErrors(form, err);
            if (onError) onError(err, form);
            else reportError(err, 'Could not save. Please check the form.');
          } finally {
            setBusy(submitBtn, false);
          }
        });

        return form;
      }

      /** Paints Laravel validation errors onto the matching inputs. */
      function showFieldErrors(form, err) {
        Object.entries(err?.errors || {}).forEach(([field, messages]) => {
          const input = form.querySelector(`[name="${field}"]`);
          if (!input) return;

          input.classList.add('border-red-500');

          const hint = document.createElement('div');
          hint.className = 'field-error text-[11px] text-red-500 mt-1';
          hint.textContent = messages[0];
          input.insertAdjacentElement('afterend', hint);
        });
      }

      function clearFieldErrors(form) {
        form.querySelectorAll('.field-error').forEach(el => el.remove());
        form.querySelectorAll('.border-red-500').forEach(el => el.classList.remove('border-red-500'));
      }

      /* --------------------------- Sound ---------------------------- */
      /**
       * Short confirmation chime, synthesised with the Web Audio API so no
       * audio asset has to ship or load.
       */
      function playChime() {
        try {
          const Ctx = window.AudioContext || window.webkitAudioContext;
          if (!Ctx) return;

          const ctx = new Ctx();
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();

          osc.type = 'sine';
          osc.frequency.setValueAtTime(880, ctx.currentTime);
          osc.frequency.setValueAtTime(1320, ctx.currentTime + 0.08);

          gain.gain.setValueAtTime(0.0001, ctx.currentTime);
          gain.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + 0.02);
          gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.3);

          osc.connect(gain).connect(ctx.destination);
          osc.start();
          osc.stop(ctx.currentTime + 0.32);
          setTimeout(() => ctx.close(), 500);
        } catch (e) { /* audio is a nicety, never an error */ }
      }

      /* ------------------------- Confirmations ---------------------- */
      /**
       * Thin wrapper over the global confirmation dialog so every page uses
       * one consistent design. `danger` maps to the delete variant.
       */
      function confirmAction({
        title = 'Are you sure?',
        message = '',
        confirmLabel = 'Confirm',
        danger = true,
        variant = null,
        successMessage = null,
        onConfirm,
      }) {
        showConfirmation({
          variant: variant || (danger ? 'delete' : 'info'),
          title,
          message,
          confirmLabel,
          successMessage,
          onConfirm,
        });
      }

      /* ------------------------ Page prefetching -------------------- */
      /**
       * Laravel sends `Cache-Control: private, no-cache` on session-backed
       * responses, so `<link rel="prefetch">` is thrown away and the click still
       * costs a full round-trip. Instead we fetch the HTML ourselves and hold it
       * in memory, which the router then serves instantly.
       *
       * Entries are short-lived and wiped after any write, so a page can never
       * render figures that a mutation has already invalidated.
       */
      const PAGE_TTL = 30000;
      const pageCache = new Map();
      const inFlight = new Map();

      function cacheableUrl(url) {
        try {
          const target = new URL(url, location.origin);
          if (target.origin !== location.origin) return null;
          if (/\/(export|backup|receipt|invoice|logout|login)(\/|$|\?)/.test(target.pathname)) return null;
          if (target.pathname.startsWith('/live/')) return null;
          return target.href;
        } catch { return null; }
      }

      function readPageCache(url) {
        const entry = pageCache.get(url);
        if (!entry) return null;

        if (Date.now() - entry.at > PAGE_TTL) {
          pageCache.delete(url);
          return null;
        }

        return entry.html;
      }

      function prefetch(url) {
        const href = cacheableUrl(url);
        if (!href || readPageCache(href) || inFlight.has(href)) return inFlight.get(href) || null;

        const request = fetch(href, {
          headers: { 'X-Requested-With': 'SpaPrefetch' },
          credentials: 'same-origin',
        })
          .then(res => (res.ok && !res.redirected ? res.text() : null))
          .then(html => {
            if (html) pageCache.set(href, { html, at: Date.now() });
            return html;
          })
          .catch(() => null)
          .finally(() => inFlight.delete(href));

        inFlight.set(href, request);
        return request;
      }

      /** Any write makes every cached page suspect. */
      function clearPageCache() {
        pageCache.clear();
      }

      /**
       * Re-render the current page after a write, without a browser reload.
       *
       * This is the one supported way to "refresh" from a page script.
       * window.location.reload() is never correct here: it re-downloads the
       * document, every stylesheet and every script, drops scroll position and
       * tears down the SPA, which costs the better part of a second on a page
       * that only needed its <main> swapped.
       *
       * Ordering matters — the cache is dropped BEFORE navigating, otherwise
       * the router happily serves the pre-write copy it prefetched moments ago
       * and the user sees their own change missing.
       *
       * Safe to call before SpaRouter exists (very early page errors); it
       * degrades to a hard reload only in that impossible case.
       */
      async function refreshPage({ scroll = false } = {}) {
        clearPageCache();

        if (!window.SpaRouter?.navigate) return window.location.reload();

        await window.SpaRouter.navigate(window.location.href, { push: false, scroll });
      }

      function bindPrefetch() {
        // The sidebar is the main navigation surface — warm it immediately so
        // the very first click is already instant.
        if ('requestIdleCallback' in window) {
          requestIdleCallback(() => {
            document.querySelectorAll('aside a[href]').forEach(a => prefetch(a.href));
          }, { timeout: 2000 });
        }

        // Hovering anything else warms it too.
        document.addEventListener('mouseover', (e) => {
          const a = e.target.closest?.('a[href]');
          if (a && !a.target && !a.hasAttribute('download')) prefetch(a.href);
        }, { passive: true });

        // Touch has no hover; pointerdown still buys us ~100ms before the tap.
        document.addEventListener('pointerdown', (e) => {
          const a = e.target.closest?.('a[href]');
          if (a && !a.target && !a.hasAttribute('download')) prefetch(a.href);
        }, { passive: true });
      }

      document.addEventListener('DOMContentLoaded', () => {
        poll(refreshCounters, 45000);
        bindPrefetch();
      });

      return {
        api, request, fetch: rawFetch, reportError, poll, refreshCounters, openNotification,
        skeletonRows, emptyState, emptyRow, setBusy,
        money, initials, escapeHtml, badgeClass, confirmAction, currency,
        ajaxForm, clearFieldErrors, showFieldErrors,
        onPageReady, pageSignal, resetPageScope, runPageReady,
        prefetch, readPageCache, clearPageCache, refreshPage, playChime,
        confirm: showConfirmation,

        /* Settings-aware helpers */
        formatDate, formatDateTime, rowsPerPage, paginate,
        notify, paymentToast,
        display, notificationPrefs,
        shop: @json($shopConfig),

        /** Re-reads display settings after Settings saves them. */
        refreshDisplay(next) {
          Object.assign(display, next || {});
          Object.assign(notificationPrefs, next?.notificationPrefs || {});
          window.applyDisplaySettings(display);
        },
      };
    })();

    /* Shared modals available on every page */
    window.modals = window.modals || {};
    Object.assign(window.modals, {
      'help': () => `
        <div class="p-5 border-b border-slate-200 flex justify-between items-center">
          <div>
            <div class="text-lg font-bold text-slate-900 tracking-tight">Help & Shortcuts</div>
            <div class="text-xs text-slate-500 mt-1">Get around the suite faster</div>
          </div>
          <button class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <div class="p-6">
          <div class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3">Keyboard Shortcuts</div>
          <div class="space-y-2">
            ${[
              ['⌘ K / Ctrl K', 'Focus global search'],
              ['Esc', 'Close any modal, drawer or search'],
              ['⌘ P / Ctrl P', 'Print the open receipt or invoice'],
            ].map(([key, desc]) => `
              <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-lg border border-slate-100">
                <span class="text-sm text-slate-600">${desc}</span>
                <kbd class="bg-white border border-slate-200 px-2 py-0.5 rounded text-[10px] text-slate-500 font-semibold shadow-sm">${key}</kbd>
              </div>
            `).join('')}
          </div>
          <div class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3 mt-6">Support</div>
          <div class="text-sm text-slate-600 leading-relaxed">
            Reach the shop administrator for account access, or check <span class="font-semibold text-slate-900">Settings</span> for store, receipt and notification preferences.
          </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
          <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm" onclick="closeModal()">Got it</button>
        </div>
      `,
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { closeModal(); closeDrawers(); }
    });

    /* ==================================================================
       SPA router
       Swaps <main>, the drawers and the page scripts without a reload.

       A page opts in by declaring @section('spaPage', '<name>'). Pages that
       have not opted in fall back to an ordinary browser navigation, so the
       app keeps working while the migration is in progress.
       ================================================================== */
    window.SpaRouter = (function () {
      const MAIN = '#spa-main';
      const DRAWERS = '#spa-drawers';
      const SCRIPTS = '#spa-scripts';

      let controller = null;
      let navigating = false;

      /* ------------------------ Eligibility ------------------------- */
      function isInternalLink(a) {
        if (!a || !a.href) return false;
        if (a.target && a.target !== '_self') return false;
        if (a.hasAttribute('download')) return false;
        if (a.dataset.noSpa !== undefined) return false;
        if (a.getAttribute('href')?.startsWith('#')) return false;

        let url;
        try { url = new URL(a.href, location.origin); } catch { return false; }

        if (url.origin !== location.origin) return false;
        if (url.pathname === location.pathname && url.search === location.search) return false;

        // Never intercept file responses or state-changing endpoints.
        if (/\/(export|backup|receipt|invoice|logout|login)(\/|$|\?)/.test(url.pathname)) return false;

        return true;
      }

      /* -------------------------- Progress -------------------------- */
      /**
       * Only dims the page if the fetch is actually slow. A warmed page swaps
       * within a frame, and flashing a loading state for 16ms looks worse than
       * showing nothing at all.
       */
      let loadingTimer = null;

      function setLoading(on) {
        clearTimeout(loadingTimer);
        const main = document.querySelector(MAIN);
        if (!main) return;

        if (on) {
          loadingTimer = setTimeout(() => {
            main.style.transition = 'opacity .15s ease';
            main.style.opacity = '0.55';
            main.style.pointerEvents = 'none';
          }, 250);
        } else {
          main.style.opacity = '';
          main.style.pointerEvents = '';
        }
      }

      /* --------------------------- Swap ----------------------------- */
      function swapPageStyles(doc) {
        document.querySelectorAll('head style[data-spa-style]').forEach(el => el.remove());

        doc.querySelectorAll('head style').forEach(style => {
          if (style.id === 'app-base-styles') return;
          const clone = document.createElement('style');
          clone.setAttribute('data-spa-style', '');
          clone.textContent = style.textContent;
          document.head.appendChild(clone);
        });
      }

      function swapSidebar(doc) {
        // Only the active-state classes and badges differ between pages.
        const incoming = doc.querySelector('aside');
        const current = document.querySelector('aside');
        if (!incoming || !current) return;

        incoming.querySelectorAll('.nav-item').forEach((link, i) => {
          const target = current.querySelectorAll('.nav-item')[i];
          if (target) target.className = link.className;
        });
      }

      /**
       * Page scripts are re-evaluated at global scope on purpose: their inline
       * onclick= handlers need the functions to stay global. That is safe
       * because every converted page declares its top-level bindings with
       * `var`, which may legally be redeclared.
       */
      function runScripts(container) {
        container.querySelectorAll('script').forEach(script => {
          if (script.src) {
            if (document.querySelector(`script[src="${script.src}"]`)) return;
            const tag = document.createElement('script');
            tag.src = script.src;
            document.body.appendChild(tag);
            return;
          }

          try {
            (0, eval)(script.textContent);
          } catch (err) {
            console.error('[SpaRouter] page script failed:', err);
            throw err;
          }
        });
      }

      /* ------------------------- Navigation ------------------------- */
      async function navigate(url, { push = true, scroll = true } = {}) {
        if (navigating) controller?.abort();

        navigating = true;
        controller = new AbortController();
        setLoading(true);

        let html = Atelier.readPageCache(url);

        // Warmed by hover? Then there is nothing to wait for.
        if (!html) {
          try {
            // If a prefetch for this URL is already in flight, ride along with
            // it rather than firing a second identical request.
            html = await (Atelier.prefetch(url) || Promise.resolve(null));

            if (!html) {
              const res = await fetch(url, {
                headers: { 'X-Requested-With': 'SpaRouter' },
                signal: controller.signal,
                credentials: 'same-origin',
              });

              // A redirect to login, or any error, is best handled by the browser.
              if (!res.ok || res.redirected) return hardNavigate(url);

              html = await res.text();
            }
          } catch (err) {
            if (err.name === 'AbortError') return;
            return hardNavigate(url);
          } finally {
            navigating = false;
          }
        } else {
          navigating = false;
        }

        const doc = new DOMParser().parseFromString(html, 'text/html');

        const incomingMain = doc.querySelector(MAIN);
        const incomingScripts = doc.querySelector(SCRIPTS);

        // Not an app page, or a page that has not opted in yet.
        if (!incomingMain || !incomingScripts?.dataset.spaPage) {
          return hardNavigate(url);
        }

        // Tear the old page down before anything from the new one runs.
        closeModal();
        closeDrawers();
        Atelier.resetPageScope();

        try {
          document.title = doc.title;
          swapPageStyles(doc);
          swapSidebar(doc);

          document.querySelector(MAIN).innerHTML = incomingMain.innerHTML;

          const drawers = doc.querySelector(DRAWERS);
          document.querySelector(DRAWERS).innerHTML = drawers ? drawers.innerHTML : '';

          const breadcrumb = document.getElementById('bc-current');
          const incomingCrumb = doc.getElementById('bc-current');
          if (breadcrumb && incomingCrumb) breadcrumb.textContent = incomingCrumb.textContent;

          const scriptHost = document.querySelector(SCRIPTS);
          scriptHost.dataset.spaPage = incomingScripts.dataset.spaPage;

          runScripts(incomingScripts);
        } catch (err) {
          // Never leave the user on a half-swapped page.
          console.error('[SpaRouter] swap failed, falling back to a full load', err);
          return hardNavigate(url);
        }

        if (push) history.pushState({ spa: true }, '', url);
        if (scroll) window.scrollTo({ top: 0, behavior: 'instant' in window ? 'instant' : 'auto' });

        Atelier.runPageReady();
        Atelier.refreshCounters();
        setLoading(false);
      }

      function hardNavigate(url) {
        window.location.href = url;
      }

      /* ----------------------- Filter forms -------------------------
         Any <form data-filter-form> becomes a live, no-reload filter.

         List pages filter and paginate on the server (the query string is
         the state), but a native form submit is a full page load, which
         throws away scroll position and re-runs every script on the page.
         Routing the submit through navigate() keeps the same server-side
         query while swapping only <main>.

         Behaviour:
           - typing debounces (400ms), selects/dates apply immediately
           - blank fields are dropped so URLs stay clean
           - changing a filter resets to page 1, otherwise you can land on
             page 7 of a 2-page result and see an empty table
      -------------------------------------------------------------- */
      async function submitFilterForm(form) {
        const action = form.getAttribute('action') || location.pathname;
        const url = new URL(action, location.origin);

        new FormData(form).forEach((value, key) => {
          const v = typeof value === 'string' ? value.trim() : value;
          if (v !== '' && v != null) url.searchParams.append(key, v);
        });

        // Any filter change invalidates the current page number.
        url.searchParams.delete('page');

        if (url.href === location.href) return;

        // navigate() replaces <main>, destroying the element being typed in.
        // Remember which field had focus so the caret can be put back —
        // without this the search box drops focus mid-word on every keystroke
        // batch and the page is unusable.
        const active = document.activeElement;
        const restore = active && form.contains(active) && active.name
          ? { name: active.name, start: active.selectionStart, end: active.selectionEnd }
          : null;

        await navigate(url.href);

        if (!restore) return;

        const next = document.querySelector(`form[data-filter-form] [name="${restore.name}"]`);
        if (!next) return;

        next.focus();
        // setSelectionRange throws on inputs that don't support it (date, select).
        try { next.setSelectionRange(restore.start, restore.end); } catch { /* not a text field */ }
      }

      function bindFilterForms() {
        document.addEventListener('submit', (e) => {
          const form = e.target.closest?.('form[data-filter-form]');
          if (!form) return;
          e.preventDefault();
          submitFilterForm(form);
        });

        let typingTimer = null;

        // Controls that represent a deliberate choice rather than typing.
        const INSTANT = 'select, input[type="date"], input[type="checkbox"], input[type="radio"]';

        document.addEventListener('input', (e) => {
          const form = e.target.closest?.('form[data-filter-form]');
          if (!form || e.target.matches(INSTANT)) return;

          clearTimeout(typingTimer);
          typingTimer = setTimeout(() => submitFilterForm(form), 400);
        });

        document.addEventListener('change', (e) => {
          const form = e.target.closest?.('form[data-filter-form]');
          if (!form) return;
          // Apply at once, and cancel any debounce still pending from the
          // search box so the two cannot race.
          if (e.target.matches(INSTANT)) {
            clearTimeout(typingTimer);
            submitFilterForm(form);
          }
        });

        // Enter in a filter field should filter, not reload the page.
        document.addEventListener('keydown', (e) => {
          if (e.key !== 'Enter') return;
          const form = e.target.closest?.('form[data-filter-form]');
          if (!form) return;
          e.preventDefault();
          clearTimeout(typingTimer);
          submitFilterForm(form);
        });
      }

      /* --------------------------- Wiring --------------------------- */
      function start() {
        document.addEventListener('click', (e) => {
          if (e.defaultPrevented || e.button !== 0) return;
          if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

          const a = e.target.closest?.('a[href]');
          if (!isInternalLink(a)) return;

          e.preventDefault();
          navigate(a.href);
        });

        bindFilterForms();

        window.addEventListener('popstate', () => {
          navigate(location.href, { push: false });
        });

        history.replaceState({ spa: true }, '', location.href);
      }

      return { navigate, start, isInternalLink, submitFilterForm };
    })();

    document.addEventListener('DOMContentLoaded', () => SpaRouter.start());

    /* ==================================================================
       Desktop app (PWA)
       Registers the service worker, flags standalone mode, and offers the
       install prompt through the existing confirmation dialog so no new
       chrome is added to the design.
       ================================================================== */
    (function () {
      const STANDALONE = window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: minimal-ui)').matches
        || window.navigator.standalone === true;

      if (STANDALONE) document.documentElement.classList.add('app-standalone');

      /* ------------------------ Service worker ---------------------- */
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .then((registration) => {
              // Silently pick up a new build on the next launch.
              registration.addEventListener('updatefound', () => {
                const worker = registration.installing;
                if (!worker) return;

                worker.addEventListener('statechange', () => {
                  if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                    worker.postMessage('SKIP_WAITING');
                  }
                });
              });
            })
            .catch((err) => console.warn('[PWA] service worker registration failed:', err));
        });
      }

      /* -------------------------- Install --------------------------- */
      const DISMISSED_KEY = 'atelier.install.dismissed';
      let deferredPrompt = null;

      function showInstallEntry(show) {
        document.querySelectorAll('[data-install-app]').forEach(el => {
          el.classList.toggle('hidden', !show);
        });
      }

      window.addEventListener('beforeinstallprompt', (e) => {
        // Take control of when the prompt appears.
        e.preventDefault();
        deferredPrompt = e;

        showInstallEntry(true);

        // Offer once, unhurried, and never nag again if declined.
        if (localStorage.getItem(DISMISSED_KEY)) return;

        setTimeout(() => {
          if (!deferredPrompt) return;

          showConfirmation({
            variant: 'info',
            title: 'Install Atelier on this device?',
            message: 'Runs in its own window without the browser, with a desktop and taskbar icon.',
            confirmLabel: 'Install App',
            onConfirm: () => window.installApp(),
          });

          // Treat "Cancel" as a decline so we ask only once.
          localStorage.setItem(DISMISSED_KEY, '1');
        }, 4000);
      });

      window.installApp = async function () {
        if (!deferredPrompt) {
          toast('This app is already installed, or your browser does not support installing', 'info');
          return;
        }

        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        deferredPrompt = null;

        if (outcome === 'accepted') {
          showInstallEntry(false);
          localStorage.removeItem(DISMISSED_KEY);
        }
      };

      window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        showInstallEntry(false);
        localStorage.removeItem(DISMISSED_KEY);
        toast('Atelier installed — you can now open it from your desktop', 'success');
      });
    })();
  </script>
  <div id="spa-scripts" data-spa-page="@yield('spaPage', '')">
    @stack('scripts')
  </div>
</body>
</html>
