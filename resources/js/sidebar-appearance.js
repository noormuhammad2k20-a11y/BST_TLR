// Shared by both layouts and Settings screens, including SPA navigation.
if (window.SIDEBAR_PALETTES && !window.sidebarAppearanceRuntime) {
    window.sidebarAppearanceRuntime = true;
    const palettes = window.SIDEBAR_PALETTES;
    let saved = {...window.APP_DISPLAY.sidebarAppearance};
    let pending = false;
    let fetching = false;
    let generation = 0;
    let status = 'Changes save automatically for both admin panels.';
    let error = false;
    const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('atelier-sidebar-appearance') : null;
    const valid = value => value && ['light', 'dark'].includes(value.theme) && Object.hasOwn(palettes, value.color);
    const same = (a, b) => a.theme === b.theme && a.color === b.color;

    function updateControls() {
        document.querySelectorAll('sidebar-appearance-settings').forEach(control => control.update());
    }

    function apply(value) {
        window.applySidebarAppearance(value);
        updateControls();
    }

    async function refresh() {
        if (pending || fetching || document.hidden) return;
        fetching = true;
        const started = generation;
        try {
            const response = await fetch(window.SIDEBAR_APPEARANCE_URL, {
                headers: {Accept: 'application/json'}, cache: 'no-store', credentials: 'same-origin',
                signal: AbortSignal.timeout(10000),
            });
            if (!response.ok) return;
            const value = await response.json();
            if (pending || started !== generation || !valid(value)) return;
            if (!same(saved, value)) {
                saved = value;
                status = 'Updated globally. Both panels use this appearance.';
                error = false;
                apply(saved);
            }
        } catch (_) {
            // Offline pages retain their last server-confirmed appearance.
        } finally { fetching = false; }
    }

    async function save(value) {
        if (pending || same(saved, value)) return;
        pending = true;
        generation++;
        status = 'Saving appearance…';
        error = false;
        apply(value);
        try {
            const response = await fetch(window.SIDEBAR_APPEARANCE_URL, {
                method: 'PUT', credentials: 'same-origin',
                headers: {Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify(value), signal: AbortSignal.timeout(15000),
            });
            if (!response.ok) throw new Error('save');
            const confirmed = await response.json();
            if (!valid(confirmed)) throw new Error('response');
            saved = confirmed;
            status = 'Saved globally. Applied to both admin panels.';
            // An invalidation signal, not a second browser source of truth.
            channel?.postMessage('refresh');
        } catch (_) {
            error = true;
            status = 'Could not confirm the save. Previous appearance restored; check your connection and try again.';
        } finally {
            pending = false;
            apply(saved);
            // Also reconciles an uncertain timeout if the server committed it.
            if (error) void refresh();
        }
    }

    class SidebarAppearanceSettings extends HTMLElement {
        connectedCallback() {
            this.innerHTML = `<section class="sa-card" aria-label="Global sidebar appearance">
                <h4 class="sa-heading">Sidebar Appearance</h4>
                <p class="sa-help">One shared appearance for Tailor and Cloth Store, across sessions and devices. Changes save automatically.</p>
                <fieldset class="sa-field"><legend>Sidebar Theme</legend><div class="sa-modes">
                    <button type="button" class="sa-mode" data-mode="light" aria-pressed="false">☀ &nbsp;Light</button>
                    <button type="button" class="sa-mode" data-mode="dark" aria-pressed="false">☾ &nbsp;Dark</button>
                </div></fieldset>
                <fieldset class="sa-field"><legend>Sidebar Colors</legend><div class="sa-grid">
                ${Object.entries(palettes).map(([key, p]) => `<button type="button" class="sa-option" data-color="${key}" aria-label="${p.name} sidebar color" aria-pressed="false">
                    <span class="sa-preview" aria-hidden="true"><span class="sa-preview-head"><span class="sa-mark"></span><span class="sa-preview-line"></span></span>
                    <span class="sa-preview-nav sa-preview-active">▦ &nbsp;Dashboard</span>
                    <span class="sa-preview-nav sa-preview-idle">◇ &nbsp;Orders</span>
                    <span class="sa-preview-nav sa-preview-hover">◷ &nbsp;Reports</span></span>
                    <span class="sa-label">${p.name}<small>${p.description}</small></span><span class="sa-check" aria-hidden="true">✓</span>
                </button>`).join('')}
                </div></fieldset>
                <p class="sa-status" role="status" aria-live="polite" aria-atomic="true"></p>
            </section>`;
            this.onclick = event => {
                const button = event.target.closest('button');
                if (!button || !this.contains(button) || pending) return;
                if (button.dataset.mode) void save({...saved, theme: button.dataset.mode});
                if (button.dataset.color) void save({...saved, color: button.dataset.color});
            };
            this.update();
            void refresh();
        }

        update() {
            const current = window.APP_DISPLAY.sidebarAppearance;
            this.querySelectorAll('[data-mode]').forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.mode === current.theme));
                button.disabled = pending;
            });
            this.querySelectorAll('[data-color]').forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.color === current.color));
                button.disabled = pending;
                const t = palettes[button.dataset.color][current.theme];
                for (const key of ['bg', 'text', 'muted', 'fill', 'hover']) button.style.setProperty('--preview-' + key, t[key]);
            });
            const message = this.querySelector('.sa-status');
            if (message) { message.textContent = status; message.dataset.error = String(error); }
        }
    }
    customElements.define('sidebar-appearance-settings', SidebarAppearanceSettings);
    channel?.addEventListener('message', () => void refresh());
    window.addEventListener('focus', () => void refresh());
    window.addEventListener('online', () => void refresh());
    window.addEventListener('pageshow', () => void refresh());
    document.addEventListener('visibilitychange', () => void refresh());
    // Other devices cannot use BroadcastChannel; reconcile visible pages with
    // the database every three seconds, without reloading or touching content.
    setInterval(() => void refresh(), 3000);
}
