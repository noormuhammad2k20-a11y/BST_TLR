const {JSDOM} = require('jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');

const palettes = JSON.parse(execFileSync('php', ['-r', "echo json_encode((require 'config/sidebar.php')['palettes']);"], {encoding: 'utf8'}));
const source = fs.readFileSync('resources/js/sidebar-appearance.js', 'utf8');
const blade = fs.readFileSync('resources/views/appearance/sidebar.blade.php', 'utf8');
let database = {theme: 'light', color: 'classic'};
let fail = false;
let writes = 0;
const peers = [];
const windows = [];
const tick = () => new Promise(resolve => setTimeout(resolve, 20));

function panel() {
    const initial = {sidebarAppearance: {...database}, colorMode: 'light', primaryColor: '#4F46E5'};
    const rendered = blade.replace('@json($appDisplay)', JSON.stringify(initial))
        .replace("@json(config('sidebar.palettes'))", JSON.stringify(palettes))
        .replace("@json(route('settings.sidebar-appearance.show'))", JSON.stringify('/settings/sidebar-appearance'));
    const dom = new JSDOM(`<html><head><meta name="csrf-token" content="test">${rendered}</head><body><aside id="sidebar"></aside><sidebar-appearance-settings></sidebar-appearance-settings></body></html>`, {
        url: 'http://localhost/settings', runScripts: 'dangerously', pretendToBeVisual: true,
        beforeParse(w) {
            w.matchMedia = () => ({matches: false, addEventListener() {}});
            w.BroadcastChannel = class {
                constructor() { peers.push(this); }
                addEventListener(_, listener) { this.listener = listener; }
                postMessage(data) { peers.filter(p => p !== this).forEach(p => p.listener?.({data})); }
            };
            w.fetch = async (_, options = {}) => {
                if (options.method === 'PUT') {
                    if (fail) return {ok: false};
                    writes++;
                    database = JSON.parse(options.body);
                    assert.equal(options.headers['X-CSRF-TOKEN'], 'test');
                }
                return {ok: true, json: async () => ({...database})};
            };
        },
    });
    dom.window.eval(source);
    windows.push(dom.window);
    return dom.window;
}

(async () => {
    const tailor = panel(), cloth = panel();
    await tick();
    assert.equal(tailor.document.querySelectorAll('.sa-option').length, 8);
    assert.equal(tailor.document.querySelectorAll('.sa-mode').length, 2);
    tailor.document.querySelector('[data-mode="dark"]').click();
    assert.equal(tailor.document.documentElement.dataset.sidebarTheme, 'dark', 'optimistic immediate preview');
    assert.ok(tailor.document.querySelector('[data-mode="dark"]').disabled, 'prevent overlapping saves');
    await tick();
    assert.equal(database.theme, 'dark');
    assert.equal(cloth.document.documentElement.dataset.sidebarTheme, 'dark', 'other panel synchronizes');
    assert.ok(!tailor.document.documentElement.classList.contains('theme-dark'), 'page theme unchanged');
    cloth.document.querySelector('[data-color="teal"]').click();
    await tick();
    assert.equal(tailor.document.documentElement.dataset.sidebarColor, 'teal');
    assert.equal(tailor.document.querySelector('[data-color="teal"]').getAttribute('aria-pressed'), 'true');
    assert.equal(writes, 2);

    fail = true;
    cloth.document.querySelector('[data-color="bronze"]').click();
    await tick();
    assert.equal(cloth.document.documentElement.dataset.sidebarColor, 'teal', 'failed save rolls back');
    assert.equal(cloth.document.querySelector('.sa-status').dataset.error, 'true');
    assert.equal(database.color, 'teal');
    fail = false;

    // Cross-device database change, without a same-browser channel.
    database = {theme: 'light', color: 'forest'};
    tailor.dispatchEvent(new tailor.Event('focus'));
    await tick();
    assert.equal(tailor.document.documentElement.dataset.sidebarColor, 'forest');
    const reloaded = panel();
    assert.equal(reloaded.document.documentElement.dataset.sidebarColor, 'forest', 'new sessions start from DB');

    // Settings panel mounted after SPA navigation upgrades without a new script.
    const mounted = tailor.document.createElement('sidebar-appearance-settings');
    tailor.document.body.append(mounted);
    assert.equal(mounted.querySelectorAll('.sa-option').length, 8);
    assert.equal(mounted.querySelector('[data-color="forest"]').getAttribute('aria-pressed'), 'true');
    tailor.applyDisplaySettings({colorMode: 'dark', sidebarAppearance: {theme: 'dark', color: 'navy'}});
    assert.equal(tailor.document.documentElement.dataset.sidebarColor, 'forest', 'unrelated stale display save cannot reset sidebar');
    for (const color of Object.keys(palettes)) for (const theme of ['light', 'dark']) {
        tailor.applySidebarAppearance({theme, color});
        assert.equal(tailor.document.documentElement.style.getPropertyValue('--sb-bg'), palettes[color][theme].bg);
    }
    assert.equal(tailor.localStorage.length, 0);
    console.log('PASS: shared autosave, cross-tab and cross-device refresh, DB reload, rollback, SPA controls, all 16 palettes, no localStorage.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => windows.forEach(w => w.close()));
