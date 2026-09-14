<style>
  /* Scoped tokens: sidebar appearance never changes the navbar or page theme. */
  #sidebar { color-scheme: var(--sb-scheme); border-color: var(--sb-border) !important; }
  #sidebar .sb-logo, #sidebar .sb-logo svg { color: var(--sb-logo-text) !important; }
  #sidebar .nav-item, #sidebar .sb-dropdown-toggle { color: var(--sb-muted) !important; }
  #sidebar .nav-item.active { background: var(--sb-fill) !important; color: #fff !important; }
  #sidebar .nav-item.active:hover { background: var(--sb-fill) !important; color: #fff !important; }
  #sidebar .sb-dropdown-toggle:hover, #sidebar .sb-dropdown-toggle.has-active { color: var(--sb-text) !important; }
  #sidebar .nav-item:focus-visible, #sidebar .sb-dropdown-toggle:focus-visible {
    outline: 2px solid var(--sb-accent); outline-offset: 2px;
  }
  #sidebar .sb-badge { background: var(--sb-active) !important; color: var(--sb-text) !important; }
  #sidebar .nav-item.active .sb-badge { background: #fff !important; color: var(--sb-fill) !important; }
  #sidebar .sb-child-active-dot { background: var(--sb-accent); }
  #sidebar .overflow-y-auto { scrollbar-color: #e2e8f0 transparent; scrollbar-width: thin; }
  #sidebar .overflow-y-auto::-webkit-scrollbar { width: 6px; }
  #sidebar .overflow-y-auto::-webkit-scrollbar-track { background: transparent; }
  #sidebar .overflow-y-auto::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 999px; }
  #sidebar .overflow-y-auto::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
  #sidebar .overflow-y-auto::-webkit-scrollbar-button { display: none; width: 0; height: 0; }
  sidebar-appearance-settings { display: block; min-width: 0; }
  .sa-card { container-type: inline-size; border: 1px solid #dbe2ec; border-radius: 12px; padding: 20px; background: #fff; color: #172033; }
  .sa-heading { font-size: 14px; font-weight: 600; margin: 0 0 4px; }
  .sa-help { font-size: 12px; color: #526077; margin: 0 0 18px; line-height: 1.6; }
  .sa-field { border: 0; margin: 0 0 20px; padding: 0; min-width: 0; }
  .sa-field legend { font-size: 12px; font-weight: 600; margin-bottom: 10px; }
  .sa-modes { display: flex; gap: 8px; flex-wrap: wrap; }
  .sa-mode { border: 1px solid #b9c5d5; border-radius: 8px; padding: 8px 16px; font-size: 12px; background: #fff; color: #34445d; }
  .sa-mode[aria-pressed="true"] { background: #eef2ff; color: #3730a3; border-color: #4338ca; box-shadow: inset 0 0 0 1px #4338ca; }
  .sa-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
  @@container (max-width: 580px) { .sa-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @@container (max-width: 280px) { .sa-grid { grid-template-columns: minmax(0, 1fr); } }
  .sa-option { text-align: left; border: 2px solid #dbe2ec; border-radius: 10px; overflow: hidden; background: #fff; color: #172033; position: relative; transition: border-color .15s, box-shadow .15s; }
  .sa-option:hover { border-color: #8a99af; }
  .sa-option[aria-pressed="true"] { border-color: #4338ca; box-shadow: 0 0 0 2px #e0e7ff; }
  .sa-option:focus-visible, .sa-mode:focus-visible { outline: 3px solid #4338ca; outline-offset: 3px; }
  .sa-preview { display: block; padding: 12px; background: var(--preview-bg); color: var(--preview-text); }
  .sa-preview-head { display: flex; align-items: center; gap: 7px; margin-bottom: 10px; }
  .sa-mark { width: 17px; height: 17px; background: var(--preview-fill); border-radius: 5px; }
  .sa-preview-line { width: 42%; height: 4px; border-radius: 3px; background: var(--preview-muted); }
  .sa-preview-nav { display: block; font-size: 10px; padding: 5px 7px; border-radius: 4px; margin-top: 3px; }
  .sa-preview-active { background: var(--preview-fill); color: #fff; }
  .sa-preview-idle { color: var(--preview-muted); }
  .sa-preview-hover { background: var(--preview-hover); color: var(--preview-text); }
  .sa-label { display: block; padding: 9px 10px; font-size: 12px; font-weight: 600; }
  .sa-label small { display: block; font-size: 10px; font-weight: 400; color: #526077; margin-top: 2px; }
  .sa-check { position: absolute; top: 6px; right: 6px; border-radius: 50%; background: #4338ca; color: #fff; width: 20px; height: 20px; text-align: center; font-size: 12px; border: 1px solid #fff; visibility: hidden; }
  .sa-option[aria-pressed="true"] .sa-check { visibility: visible; }
  .sa-status { font-size: 12px; color: #526077; min-height: 20px; margin: 0; }
  .sa-status[data-error="true"] { color: #b42318; }
  .sa-card button:disabled { cursor: progress; }
  html.theme-dark .sa-card { background: #172033; color: #eef2ff; border-color: #3b4a62; }
  html.theme-dark .sa-help, html.theme-dark .sa-status { color: #b9c7dc; }
  html.theme-dark .sa-status[data-error="true"] { color: #fca5a5; }
  @media (prefers-reduced-motion: reduce) { #sidebar, #sidebar *, .sa-option { transition: none !important; } }
</style>
<script>
(() => {
  const D = window.APP_DISPLAY = @json($appDisplay);
  window.SIDEBAR_PALETTES = @json(config('sidebar.palettes'));
  window.SIDEBAR_APPEARANCE_URL = @json(route('settings.sidebar-appearance.show'));
  window.applySidebarAppearance = function (appearance) {
    if (!appearance || !['light', 'dark'].includes(appearance.theme) || !Object.hasOwn(window.SIDEBAR_PALETTES, appearance.color)) return;
    D.sidebarAppearance = {...appearance};
    const root = document.documentElement;
    const tokens = window.SIDEBAR_PALETTES[appearance.color][appearance.theme];
    for (const [key, value] of Object.entries(tokens)) root.style.setProperty('--sb-' + key, value);
    root.style.setProperty('--sb-scheme', appearance.theme);
    root.dataset.sidebarTheme = appearance.theme;
    root.dataset.sidebarColor = appearance.color;
  };
  function prefersDark(mode) {
    return mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
  }
  window.applyDisplaySettings = function (overrides) {
    // Other settings may hold a stale display snapshot. Sidebar updates have
    // their own persisted lifecycle and must not be overwritten by that snapshot.
    const {sidebarAppearance, ...display} = overrides || {};
    Object.assign(D, display);
    const root = document.documentElement;
    root.classList.toggle('theme-dark', prefersDark(D.colorMode));
    root.classList.toggle('compact-tables', !!D.compactTables);
    root.style.setProperty('--brand', D.primaryColor || '#4F46E5');
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', prefersDark(D.colorMode) ? '#0B1120' : '#0F172A');
  };
  window.applySidebarAppearance(D.sidebarAppearance);
  window.applyDisplaySettings();
  matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (D.colorMode === 'system') window.applyDisplaySettings();
  });
})();
</script>
