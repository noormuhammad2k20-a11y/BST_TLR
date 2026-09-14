const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'msedge' });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [];
  let livePayload = { success: true, orders: [], counts: {}, notifications: [] };
  const requests = [];
  page.on('pageerror', e => errors.push(e.message));
  const html = fs.readFileSync('dev/artifacts/mixed-orders-browser.html', 'utf8');
  await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
    const asset = path.resolve('public', '.' + url.pathname);
    if (asset.startsWith(path.resolve('public') + path.sep) && fs.existsSync(asset) && fs.statSync(asset).isFile()) return route.fulfill({ path: asset });
    if (route.request().resourceType() === 'fetch') {
      requests.push(route.request().method());
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify(livePayload) });
    }
    return route.abort();
  });
  try {
    await page.goto('http://127.0.0.1/');
    const expected = ['Received', 'In Progress', 'Ready', 'Delivered'];
    assert.deepEqual(await page.evaluate(() => WORKFLOW), expected);
    assert.equal(await page.evaluate(() => typeof autoStatus), 'undefined');
    await page.evaluate(() => {
      const base = orders.find(o => o.garments.length === 2);
      if (!base) throw Error('Render the mixed-order integration fixture first');
      window.workflowFixture = { ...base, status: 'Received', overdue: false, allowed: ['In Progress', 'Ready'] };
      orders = [workflowFixture];
      orderFilterStatus = 'All';
      renderPage();
    });
    assert.equal(await page.locator('tbody .badge').filter({ hasText: /^Received$/ }).count(), 1);
    assert.equal(await page.locator('tbody .badge').filter({ hasText: /Overdue|In Progress/ }).count(), 0);
    await page.screenshot({ animations: 'disabled', path: 'dev/artifacts/order-workflow-table.png' });
    await page.evaluate(() => openModal('order-details', { ...workflowFixture, timeline: [] }));
    const modal = page.locator('#modal-content');
    assert.equal(await modal.locator('.badge').filter({ hasText: /^Received$/ }).count(), 1);
    assert.equal(await modal.getByRole('button', { name: 'Mark Ready & Send SMS' }).isDisabled(), false);
    const stepLabels = await modal.locator('.flex.flex-col.items-center.flex-1.relative').allTextContents();
    assert.equal(stepLabels.length, 4);
    expected.forEach((status, i) => assert.ok(stepLabels[i].includes(status), `Missing ${status} step`));
    await page.screenshot({ animations: 'disabled', path: 'dev/artifacts/order-workflow-details.png' });
    await page.evaluate(() => { closeModal(); newOrderState = blankOrderState(); wizardStep = 5; openModal('add-order-wizard'); });
    assert.equal(await modal.locator('.badge').filter({ hasText: /^Received$/ }).count(), 1);
    await page.evaluate(() => { closeModal(); wizardStep = 4; openModal('add-order-wizard'); });
    assert.equal(await modal.locator('input[type="time"][required]').count(), 1);
    await page.evaluate(() => closeModal());
    const fixture = await page.evaluate(() => workflowFixture);
    for (const status of ['In Progress', 'Ready', 'Delivered']) {
      livePayload = { orders: [{ ...fixture, status }], counts: {}, timestamp: new Date().toISOString() };
      await page.evaluate(() => refreshOrders());
      assert.equal(await page.locator('tbody .badge').filter({ hasText: new RegExp(`^${status}$`) }).count(), 1);
    }
    assert.ok(requests.length >= 3);
    assert.ok(requests.every(method => method === 'GET'), 'Live refresh must be read-only');
    const sortResult = await page.evaluate(() => {
      orders = [
        {...workflowFixture, db_id:1, status:'Ready', dueToday:true, overdue:false, dueDate:new Date('2026-09-14T14:00:00+05:00')},
        {...workflowFixture, db_id:2, status:'In Progress', dueToday:true, overdue:true, dueDate:new Date('2026-09-14T10:00:00+05:00')},
        {...workflowFixture, db_id:3, status:'Received', dueToday:false, overdue:false, dueDate:new Date('2026-09-15T09:00:00+05:00')}
      ];
      orderFilterStatus='Due Today';
      return getFilteredOrders().map(o=>o.db_id);
    });
    assert.deepEqual(sortResult,[2,1]);
    assert.deepEqual(errors, []);
    console.log('PASS: four statuses, no elapsed-stage timer, Received table badge, manual early Ready enabled, four-step details, Received creation confirmation, exact time input, read-only live status updates, Today sorting including Ready; no browser errors.');
  } finally {
    await browser.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
