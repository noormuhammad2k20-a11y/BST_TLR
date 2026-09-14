const fs = require('fs');
const assert = require('node:assert/strict');
const { JSDOM, VirtualConsole } = require('jsdom');
const payload = JSON.parse(fs.readFileSync('storage/app/order-auto-qa.json', 'utf8'));
const errors = [];
const vc = new VirtualConsole();
vc.on('jsdomError', e => errors.push(e.message));
const dom = new JSDOM(fs.readFileSync('storage/app/order-auto-qa.html','utf8'), {
  url: 'http://localhost/orders', runScripts:'dangerously', pretendToBeVisual:true, virtualConsole:vc,
  beforeParse(w) {
    w.matchMedia = () => ({matches:false,addEventListener(){},removeEventListener(){}});
    w.fetch = async () => ({ok:true,headers:{get:()=> 'application/json'},json:async()=>structuredClone(payload)});
    w.scrollTo = () => {};
  }
});
(async () => {
  await new Promise(resolve => dom.window.addEventListener('load', resolve));
  const w = dom.window;
  assert.equal(errors.length,0,errors.join('\n'));
  assert.equal(w.eval('WORKFLOW.join(" > ")'),'Received > Pending > Stitching > Ready for Verification > Ready > Delivered');
  const target = payload.orders.find(o => o.db_id === 50);
  assert.equal(target.status,'Ready for Verification');
  w.eval('orders.find(o => o.db_id === 50).status = "Stitching"; openOrderDetails(50)');
  await w.eval('refreshOrders()');
  assert.match(w.document.querySelector('[data-order-detail-id="50"]').textContent,/Ready for Verification/);
  w.eval('closeModal(); currentView = "editor"; document.getElementById("page-container").innerHTML="<input id=qa-draft value=preserved>"');
  await w.eval('refreshOrders()');
  assert.equal(w.document.getElementById('qa-draft').value,'preserved');
  assert.equal(errors.length,0,errors.join('\n'));
  console.log('PASS: live order status, six stages, open modal refresh, and draft preservation.');
})().catch(e => { console.error(e); process.exitCode=1; }).finally(()=>dom.window.close());
