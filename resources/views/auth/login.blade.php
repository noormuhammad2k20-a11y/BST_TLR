<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Sign In — {{ \App\Models\Setting::getValue('store_name', 'Atelier') }}</title>

  {{-- Keeps the app installable from the sign-in screen too. --}}
  <link rel="manifest" href="{{ route('pwa.manifest') }}">
  <meta name="theme-color" content="#0F172A">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="{{ \App\Models\Setting::getValue('store_name', 'Atelier') }}">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
  @if (file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css'])
  @endif
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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

<body class="login-bg flex items-center justify-center min-h-screen p-4 sm:p-6 overflow-hidden relative">

  <!-- Floating Ambient Orbs -->
  <div class="absolute top-[-10%] left-[-5%] w-[450px] h-[450px] bg-indigo-200/30 rounded-full filter blur-[100px] pointer-events-none orb-float"></div>
  <div class="absolute bottom-[-10%] right-[-5%] w-[400px] h-[400px] bg-violet-200/25 rounded-full filter blur-[100px] pointer-events-none orb-float-delay"></div>
  <div class="absolute top-[40%] right-[20%] w-[250px] h-[250px] bg-blue-100/20 rounded-full filter blur-[80px] pointer-events-none orb-float"></div>

  <!-- Split Login Card -->
  <div class="relative z-10 grid grid-cols-1 md:grid-cols-[1.1fr_1fr] max-w-[920px] w-full enterprise-card rounded-[20px] overflow-hidden card-anim" style="min-height: 560px;">

    <!-- ═══ Left Panel: Hero Branding ═══ -->
    <div class="hidden md:flex relative bg-slate-900 overflow-hidden">
      <img src="https://images.unsplash.com/photo-1591348278863-a8fb3887e2aa?q=80&w=1974&auto=format&fit=crop"
        class="absolute inset-0 w-full h-full object-cover opacity-50 scale-105" alt="Bespoke Tailoring"
        style="object-position: center 30%;">

      <!-- Gradient Overlay -->
      <div class="absolute inset-0 image-overlay"></div>

      <!-- Decorative Dots -->
      <div class="dot-grid top-8 right-8 opacity-40"></div>
      <div class="dot-grid bottom-12 left-8 opacity-30"></div>

      <!-- Decorative ring -->
      <div class="absolute -bottom-16 -left-16 w-48 h-48 rounded-full border border-white/[0.06]"></div>
      <div class="absolute -bottom-24 -left-24 w-64 h-64 rounded-full border border-white/[0.04]"></div>

      <div class="relative z-10 w-full flex flex-col justify-between p-10 text-white left-anim">

        <!-- Top Logo -->
        <div class="flex items-center gap-3">
          <div class="w-11 h-11 rounded-[13px] bg-white/[0.08] backdrop-blur-xl flex items-center justify-center border border-white/[0.15] shadow-lg shadow-black/10">
            <i class="fa-solid fa-scissors text-[15px] text-white/90"></i>
          </div>
          <div>
            <div class="text-[17px] font-bold tracking-tight font-display leading-none">{{ strtoupper(\App\Models\Setting::getValue('store_name', 'ATELIER')) }}</div>
            <div class="text-[9px] uppercase tracking-[0.2em] text-white/40 font-medium mt-0.5">Tailor Suite</div>
          </div>
        </div>

        <!-- Center Content -->
        <div class="py-6">
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white/[0.07] backdrop-blur-md border border-white/[0.12] rounded-full text-[11px] font-medium mb-7 tracking-wide">
            <span class="w-[6px] h-[6px] bg-emerald-400 rounded-full animate-pulse shadow-sm shadow-emerald-400/50"></span>
            Enterprise Edition v2.0
          </div>
          <h2 class="text-[34px] font-extrabold mb-5 font-display leading-[1.15] tracking-tight">
            The future of<br>
            <span class="bg-gradient-to-r from-indigo-300 via-violet-300 to-purple-300 bg-clip-text text-transparent">bespoke tailoring.</span>
          </h2>
          <p class="text-slate-300/80 text-[13px] max-w-[300px] leading-relaxed font-light">
            Streamline your measurements, orders, and client management with precision-built software for modern ateliers.
          </p>
        </div>

        <!-- Bottom Stats Bar -->
        <div class="border-t border-white/[0.08] pt-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3.5">
              <div class="flex -space-x-2.5">
                <div class="w-8 h-8 rounded-full border-2 border-slate-900/80 bg-gradient-to-br from-indigo-400 to-indigo-600 flex items-center justify-center text-[10px] font-bold shadow-sm">AK</div>
                <div class="w-8 h-8 rounded-full border-2 border-slate-900/80 bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-[10px] font-bold shadow-sm">RM</div>
                <div class="w-8 h-8 rounded-full border-2 border-slate-900/80 bg-gradient-to-br from-violet-400 to-violet-600 flex items-center justify-center text-[10px] font-bold shadow-sm">SK</div>
                <div class="w-8 h-8 rounded-full border-2 border-slate-900/80 bg-white/10 backdrop-blur flex items-center justify-center text-[9px] font-semibold text-white/70">+47</div>
              </div>
              <div>
                <div class="text-[11px] font-semibold text-white/90">500+ tailors</div>
                <div class="text-[10px] text-slate-400/80">12 countries</div>
              </div>
            </div>
            <div class="flex items-center gap-1.5 px-2.5 py-1 bg-emerald-500/10 border border-emerald-400/20 rounded-full">
              <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span>
              <span class="text-[10px] font-medium text-emerald-300">99.9% uptime</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Right Panel: Login Form ═══ -->
    <div class="p-7 sm:p-10 md:p-11 flex flex-col justify-center bg-white relative">

      <!-- Subtle top-right glow on form side -->
      <div class="absolute top-0 right-0 w-40 h-40 bg-indigo-50/50 rounded-full filter blur-[60px] pointer-events-none"></div>

      <div class="relative z-10 stagger">

        <!-- Mobile Logo -->
        <div class="flex md:hidden items-center gap-2.5 mb-8">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center shadow-lg shadow-indigo-500/20">
            <i class="fa-solid fa-scissors text-white text-sm"></i>
          </div>
          <div>
            <span class="text-lg font-bold tracking-tight text-slate-800 font-display block leading-none">{{ strtoupper(\App\Models\Setting::getValue('store_name', 'ATELIER')) }}</span>
            <span class="text-[9px] uppercase tracking-[0.18em] text-slate-400 font-medium">Tailor Suite</span>
          </div>
        </div>

        <!-- Header -->
        <div class="mb-7">
          <h1 class="text-[22px] sm:text-2xl font-bold text-slate-900 mb-1.5 font-display tracking-tight leading-tight">Welcome back</h1>
          <p class="text-slate-400 text-[13px]">Sign in to access your dashboard</p>
        </div>

        {{-- Server-side validation errors --}}
        @if ($errors->any())
          <div class="mb-5 flex items-start gap-2.5 p-3.5 rounded-xl bg-red-50/80 border border-red-200/60 shake-anim backdrop-blur-sm">
            <div class="w-5 h-5 rounded-full bg-red-100 flex items-center justify-center shrink-0 mt-0.5">
              <i class="fa-solid fa-exclamation text-red-500 text-[10px]"></i>
            </div>
            <div class="text-[13px] text-red-600 font-medium leading-relaxed">{{ $errors->first() }}</div>
          </div>
        @endif

        <!-- Login Form -->
        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-5" id="login-form">
          @csrf

          <!-- Email Field -->
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-2 uppercase tracking-[0.08em]">Email Address</label>
            <div class="relative group">
              <div class="absolute left-0 top-0 bottom-0 w-11 flex items-center justify-center pointer-events-none">
                <i class="fa-solid fa-envelope text-slate-300 text-[13px] group-focus-within:text-indigo-500 transition-colors duration-200"></i>
              </div>
              <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="pro-input w-full pl-11 pr-4 py-[11px] rounded-xl text-sm" placeholder="you@company.com"
                id="email-input">
            </div>
          </div>

          <!-- Password Field -->
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-2 uppercase tracking-[0.08em]">Password</label>
            <div class="relative group">
              <div class="absolute left-0 top-0 bottom-0 w-11 flex items-center justify-center pointer-events-none">
                <i class="fa-solid fa-lock text-slate-300 text-[13px] group-focus-within:text-indigo-500 transition-colors duration-200"></i>
              </div>
              <input type="password" name="password" id="password-input" required autocomplete="current-password"
                class="pro-input w-full pl-11 pr-12 py-[11px] rounded-xl text-sm" placeholder="••••••••">
              <button type="button" id="toggle-password"
                class="absolute right-1.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg text-slate-300 hover:text-indigo-500 hover:bg-indigo-50 flex items-center justify-center transition-all duration-200">
                <i class="fa-regular fa-eye text-[13px]"></i>
              </button>
            </div>
          </div>

          <!-- Remember Me -->
          <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2.5 cursor-pointer select-none group">
              <input type="checkbox" name="remember" value="1" class="custom-checkbox">
              <span class="text-[12px] text-slate-500 font-medium group-hover:text-slate-700 transition-colors">Remember for 30 days</span>
            </label>
          </div>

          <!-- Submit Button -->
          <button type="submit" id="login-btn"
            class="btn-primary w-full text-white py-[12px] rounded-xl text-sm font-semibold flex items-center justify-center gap-2.5 group mt-1 shadow-lg shadow-indigo-500/20">
            <span>Sign In</span>
            <i class="fa-solid fa-arrow-right text-[11px] group-hover:translate-x-1 transition-transform duration-200"></i>
          </button>
        </form>

        <!-- Feature Cards -->
        <div class="mt-8 pt-6 border-t border-slate-100/80 grid grid-cols-3 gap-2">
          <div class="feature-card card-indigo">
            <div class="feature-icon bg-indigo-50">
              <i class="fa-solid fa-gauge-high text-indigo-500 text-[13px]"></i>
            </div>
            <div class="text-[10px] font-bold text-slate-700 leading-tight">Real-time</div>
            <div class="text-[9px] text-slate-400 mt-0.5 font-medium">Live Dashboard</div>
          </div>
          <div class="feature-card card-emerald">
            <div class="feature-icon bg-emerald-50">
              <i class="fa-solid fa-ruler-combined text-emerald-500 text-[13px]"></i>
            </div>
            <div class="text-[10px] font-bold text-slate-700 leading-tight">Precision</div>
            <div class="text-[9px] text-slate-400 mt-0.5 font-medium">Measurements</div>
          </div>
          <div class="feature-card card-violet">
            <div class="feature-icon bg-violet-50">
              <i class="fa-solid fa-chart-pie text-violet-500 text-[13px]"></i>
            </div>
            <div class="text-[10px] font-bold text-slate-700 leading-tight">Analytics</div>
            <div class="text-[9px] text-slate-400 mt-0.5 font-medium">Smart Reports</div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script>
    // Password Toggle
    const togglePass = document.getElementById('toggle-password');
    const passInput = document.getElementById('password-input');

    togglePass.addEventListener('click', () => {
      if (passInput.type === 'password') {
        passInput.type = 'text';
        togglePass.innerHTML = '<i class="fa-regular fa-eye-slash text-[13px]"></i>';
      } else {
        passInput.type = 'password';
        togglePass.innerHTML = '<i class="fa-regular fa-eye text-[13px]"></i>';
      }
    });

    // Submit button loading state
    const loginForm = document.getElementById('login-form');
    const loginBtn = document.getElementById('login-btn');

    loginForm.addEventListener('submit', () => {
      loginBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-sm"></i> <span>Signing in...</span>';
      loginBtn.disabled = true;
      loginBtn.style.opacity = '0.75';
      loginBtn.style.cursor = 'not-allowed';
      loginBtn.style.transform = 'none';
      loginBtn.style.boxShadow = 'none';
    });

    // Input focus glow effect
    document.querySelectorAll('.pro-input').forEach(input => {
      input.addEventListener('focus', () => {
        input.closest('.group')?.classList.add('focused');
      });
      input.addEventListener('blur', () => {
        input.closest('.group')?.classList.remove('focused');
      });
    });
  </script>
</body>

</html>
