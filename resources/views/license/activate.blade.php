<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Software License — Atelier</title>
  <link rel="icon" href="{{ asset('icons/favicon-32.png') }}">
  @vite(['resources/css/app.css'])
  <style>
    *,
    *::before,
    *::after { box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      margin: 0;
    }

    h1, h2, h3, h4, .font-display {
      font-family: 'Sora', sans-serif;
    }

    /* ── Premium Background ── */
    .login-bg {
      background-color: #f0f2f7;
      background-image:
        radial-gradient(ellipse 80% 60% at 50% -10%, rgba(99, 102, 241, 0.08), transparent),
        radial-gradient(ellipse 60% 50% at 80% 110%, rgba(139, 92, 246, 0.06), transparent);
      position: relative;
    }

    /* Floating grid pattern */
    .login-bg::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(to right, rgba(148, 163, 184, 0.07) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.07) 1px, transparent 1px);
      background-size: 48px 48px;
      mask-image: radial-gradient(ellipse 70% 70% at 50% 50%, black 40%, transparent 100%);
      -webkit-mask-image: radial-gradient(ellipse 70% 70% at 50% 50%, black 40%, transparent 100%);
    }

    /* ── Premium Card ── */
    .enterprise-card {
      background: #ffffff;
      border: 1px solid rgba(226, 232, 240, 0.8);
      box-shadow:
        0 1px 3px rgba(15, 23, 42, 0.04),
        0 8px 24px rgba(15, 23, 42, 0.06),
        0 32px 64px -16px rgba(15, 23, 42, 0.08);
      backdrop-filter: blur(8px);
    }

    /* ── Ultra-Premium Inputs ── */
    .pro-input {
      background-color: #f8fafc;
      border: 1.5px solid #e2e8f0;
      color: #0f172a;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      font-weight: 500;
    }

    .pro-input::placeholder {
      color: #94a3b8;
      font-weight: 400;
    }

    .pro-input:hover {
      border-color: #a5b4fc;
      background-color: #ffffff;
    }

    .pro-input:focus {
      background-color: #ffffff;
      border-color: #6366f1;
      box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1), 0 1px 2px rgba(0, 0, 0, 0.04);
      outline: none;
    }

    /* ── Custom Checkbox ── */
    .custom-checkbox {
      appearance: none;
      -webkit-appearance: none;
      width: 18px;
      height: 18px;
      border: 1.5px solid #cbd5e1;
      border-radius: 5px;
      background: #fff;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
    }

    .custom-checkbox:checked {
      background: linear-gradient(135deg, #6366f1, #4f46e5);
      border-color: #4f46e5;
    }

    .custom-checkbox:checked::after {
      content: '';
      position: absolute;
      left: 5px;
      top: 2px;
      width: 5px;
      height: 9px;
      border: solid white;
      border-width: 0 2px 2px 0;
      transform: rotate(45deg);
    }

    .custom-checkbox:focus {
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }

    /* ── Left Panel Overlay ── */
    .image-overlay {
      background: linear-gradient(160deg,
        rgba(15, 23, 42, 0.92) 0%,
        rgba(30, 27, 75, 0.85) 35%,
        rgba(67, 56, 202, 0.55) 70%,
        rgba(99, 102, 241, 0.35) 100%);
    }

    /* ── Submit Button with Shimmer ── */
    .btn-primary {
      background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #4338ca 100%);
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .btn-primary::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
      transition: left 0.5s;
    }

    .btn-primary:hover::before {
      left: 100%;
    }

    .btn-primary:hover {
      box-shadow: 0 8px 25px -4px rgba(79, 70, 229, 0.45), 0 4px 12px -2px rgba(79, 70, 229, 0.2);
      transform: translateY(-1px);
    }

    .btn-primary:active {
      transform: translateY(0) scale(0.985);
      box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
    }

    /* ── Animations ── */
    @keyframes slideUp {
      0% {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
      }
      100% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    @keyframes fadeInLeft {
      0% {
        opacity: 0;
        transform: translateX(-20px);
      }
      100% {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes floatOrb {
      0%, 100% { transform: translate(0, 0) scale(1); }
      33% { transform: translate(15px, -20px) scale(1.05); }
      66% { transform: translate(-10px, 10px) scale(0.97); }
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      15%, 45%, 75% { transform: translateX(-6px); }
      30%, 60%, 90% { transform: translateX(6px); }
    }

    .card-anim {
      animation: slideUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .left-anim {
      animation: fadeInLeft 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
    }

    .shake-anim {
      animation: shake 0.5s ease-in-out;
    }

    .orb-float {
      animation: floatOrb 12s ease-in-out infinite;
    }

    .orb-float-delay {
      animation: floatOrb 15s ease-in-out 3s infinite;
    }

    /* ── Feature Cards ── */
    .feature-card {
      position: relative;
      background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(248,250,252,0.7));
      border: 1px solid rgba(226, 232, 240, 0.6);
      border-radius: 14px;
      padding: 14px 10px;
      text-align: center;
      cursor: default;
      transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: hidden;
    }

    .feature-card::before {
      content: '';
      position: absolute;
      inset: 0;
      border-radius: inherit;
      opacity: 0;
      transition: opacity 0.35s;
    }

    .feature-card:hover {
      transform: translateY(-3px);
      border-color: transparent;
      box-shadow: 0 8px 24px -6px rgba(99, 102, 241, 0.12), 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .feature-card.card-indigo:hover { border-color: rgba(165, 180, 252, 0.5); }
    .feature-card.card-emerald:hover { border-color: rgba(110, 231, 183, 0.5); }
    .feature-card.card-violet:hover { border-color: rgba(196, 181, 253, 0.5); }

    .feature-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 8px;
      transition: all 0.3s;
    }

    .feature-card:hover .feature-icon {
      transform: scale(1.1);
    }

    /* ── Stagger children ── */
    .stagger > *:nth-child(1) { animation-delay: 0.05s; }
    .stagger > *:nth-child(2) { animation-delay: 0.1s; }
    .stagger > *:nth-child(3) { animation-delay: 0.15s; }
    .stagger > *:nth-child(4) { animation-delay: 0.2s; }
    .stagger > *:nth-child(5) { animation-delay: 0.25s; }
    .stagger > *:nth-child(6) { animation-delay: 0.3s; }
    .stagger > *:nth-child(7) { animation-delay: 0.35s; }

    .stagger > * {
      opacity: 0;
      animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* ── Decorative dots on left panel ── */
    .dot-grid {
      position: absolute;
      width: 120px;
      height: 120px;
      background-image: radial-gradient(circle, rgba(255,255,255,0.12) 1px, transparent 1px);
      background-size: 12px 12px;
    }

    /* ── Responsive polish ── */
    @media (max-width: 767px) {
      .enterprise-card {
        border-radius: 20px;
        max-width: 420px;
        margin: 0 auto;
      }
    }
  </style>
</head>
<body class="login-bg flex items-center justify-center min-h-screen p-4 sm:p-6 relative">
  <main class="relative z-10 enterprise-card rounded-[20px] w-full max-w-xl p-6 sm:p-10">
    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mb-6">
      <x-icon name="settings" size="24" />
    </div>
    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $result->valid() ? 'Device activated' : 'Activate your workspace' }}</h1>
    <p class="text-sm text-slate-500 mt-2">{{ $result->message }}</p>
    @if(session('success'))
      <p role="status" class="mt-5 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</p>
    @endif
    @if($errors->any())
      <div role="alert" class="mt-5 p-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
    @endif
    <div class="mt-6 p-4 bg-slate-50 border border-slate-200 rounded-xl">
      <label for="machine-id" class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Machine ID</label>
      <textarea id="machine-id" readonly rows="2" class="pro-input w-full rounded-lg px-3 py-2 text-xs font-mono" aria-describedby="machine-hint">{{ $machineId ?? 'Unavailable — contact your software provider.' }}</textarea>
      <p id="machine-hint" class="text-xs text-slate-500 mt-2">Send this Machine ID to your software provider to receive your license.dat file.</p>
    </div>
    @if($result->license)
      <dl class="mt-5 text-sm space-y-3">
        <div class="flex justify-between gap-4"><dt class="text-slate-500">Client</dt><dd class="font-medium text-slate-900 break-words">{{ $result->license['client_name'] }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate-500">License Type</dt><dd class="font-medium text-slate-900">{{ $result->license['type'] }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate-500">Status</dt><dd class="font-medium text-slate-900">{{ ucfirst($result->status) }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate-500">Expiry Date</dt><dd class="font-medium text-slate-900">{{ $result->license['type'] === 'Lifetime' ? 'Never Expires' : gmdate('Y-m-d', $result->license['expires_at'] - 1).' (UTC)' }}</dd></div>
      </dl>
    @endif
    @if($result->valid())
      <a href="{{ route('dashboard') }}" class="mt-6 bg-slate-900 text-white px-4 py-3 rounded-lg text-sm font-medium hover:bg-slate-800 flex items-center justify-center gap-2">Continue to application</a>
    @endif
    <form method="POST" action="{{ route('license.install') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
      @csrf
      <label for="license" class="block text-sm font-semibold text-slate-900">{{ $result->valid() ? 'Update license' : 'Install license file' }}</label>
      <input type="file" name="license" id="license" accept=".dat" required class="pro-input w-full rounded-lg px-3 py-3 text-sm">
      <button type="submit" @disabled(!$machineId) class="w-full bg-slate-900 text-white px-4 py-3 rounded-lg text-sm font-medium hover:bg-slate-800 disabled:opacity-50">Verify and activate</button>
    </form>
    <p class="mt-6 text-xs text-slate-500 leading-relaxed">Activation works entirely offline. Your orders, customers, payments and other business data remain safely stored while activation is required.</p>
  </main>
</body>
</html>
