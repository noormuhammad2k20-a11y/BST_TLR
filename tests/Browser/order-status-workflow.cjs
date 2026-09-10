const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'msedge' });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  const html = fs.readFileSync('dev/artifacts/mixed-orders-browser.html', 'utf8');
  await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
    const asset = path.resolve('public', '.' + url.pathname);
    if (asset.startsWith(path.resolve('public') + path.sep) && fs.existsSync(asset) && fs.statSync(asset).isFile()) return route.fulfill({ path: asset });
    if (route.request().resourceType() === 'fetch') return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, orders: [], counts: {}, notifications: [] }) });
    return route.abort();
  });
  try {
    await page.goto('http://127.0.0.1/');
    const expected = ['Received', 'Pending', 'Stitching', 'Ready for Verification', 'Ready', 'Delivered'];
    assert.deepEqual(await page.evaluate(() => WORKFLOW), expected);
    const stages = await page.evaluate(() => Object.keys(autoStatus.next));
    assert.deepEqual(stages, expected.slice(0, 3));
    await page.evaluate(() => {
      const base = orders.find(o => o.garments.length === 2);
      if (!base) throw Error('Render the mixed-order integration fixture first');
      window.workflowFixture = { ...base, status: 'Received', overdue: false, allowed: ['Pending'] };
      orders = [workflowFixture];
      orderFilterStatus = 'All';
      renderPage();
    });
    assert.equal(await page.locator('tbody .badge').filter({ hasText: /^Received$/ }).count(), 1);
    assert.equal(await page.locator('tbody .badge').filter({ hasText: /Overdue|In Progress/ }).count(), 0);
    await page.screenshot({ path: 'dev/artifacts/order-workflow-table.png' });
    await page.evaluate(() => openModal('order-details', { ...workflowFixture, timeline: [] }));
    const modal = page.locator('#modal-content');
    assert.equal(await modal.locator('.badge').filter({ hasText: /^Received$/ }).count(), 1);
    assert.equal(await modal.getByRole('button', { name: 'Mark Ready & Send SMS' }).isDisabled(), true);
    const stepLabels = await modal.locator('.flex.flex-col.items-center.flex-1.relative').allTextContents();
    assert.equal(stepLabels.length, 6);
    expected.forEach((status, i) => assert.ok(stepLabels[i].includes(status), `Missing ${status} step`));
    await page.screenshot({ path: 'dev/artifacts/order-workflow-details.png' });
    await page.evaluate(() => { closeModal(); newOrderState = blankOrderState(); wizardStep = 5; openModal('add-order-wizard'); });
    assert.equal(await modal.locator('.badge').filter({ hasText: /^Received$/ }).count(), 1);
    assert.deepEqual(errors, []);
    console.log('PASS: six statuses, three automated hops, Received table badge, no false overdue badge, unchanged six-step details, Received creation confirmation; no browser errors.');
  } finally {
    await browser.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
