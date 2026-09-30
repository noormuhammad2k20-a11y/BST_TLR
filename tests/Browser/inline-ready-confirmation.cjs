const fs = require('node:fs');
const assert = require('node:assert/strict');
const {JSDOM} = require('jsdom');
const dom = new JSDOM('<div id="page-container"></div>', {
  runScripts:'outside-only', url:'http://localhost/orders', pretendToBeVisual:true,
});
const w = dom.window;
const source = fs.readFileSync('resources/views/orders/index.blade.php','utf8').replace(/\r\n/g,'\n');
let clock = 0, timerId = 0, requests = [], errors = [], toasts = [], hidden = false;
const timers = new Map();
let pageScope = new w.AbortController();
Object.defineProperty(w.performance, 'now', {value:()=>clock});
Object.defineProperty(w.document, 'hidden', {get:()=>hidden});
w.setTimeout = (fn, delay) => {const id=++timerId; timers.set(id,{fn,at:clock+delay}); return id;};
w.clearTimeout = id => timers.delete(id);
function advance(ms) {
  const end=clock+ms;
  while (true) {
    const next=[...timers].filter(([,t])=>t.at<=end).sort((a,b)=>a[1].at-b[1].at)[0];
    if (!next) break;
    clock=next[1].at; timers.delete(next[0]); next[1].fn();
  }
  clock=end;
}
w.Atelier = {
  pageSignal:()=>pageScope.signal, escapeHtml:String, refreshCounters:()=>{},
  reportError:error=>errors.push(error),
  api:{post:(url,payload)=>new Promise((resolve,reject)=>requests.push({url,payload,resolve,reject}))},
};
w.toast=(message,type)=>toasts.push({message,type});
w.closeModal=()=>{};
w.openModal=()=>assert.fail('Inline confirmation must not open a modal');
w.ROUTES={notify:id=>`/orders/${id}/notify`};
w.currentView='list'; w.viewMode='table'; w.orderSearchTerm='';
w.updateBulkSmsButtonState=()=>{}; w.bindKanbanDragDrop=()=>{};
w.orders=[1,2,3].map(id=>({db_id:id,status:id===3?'Stitching':'Ready for Verification',smsState:'not_sent',schedule:{text:'Due Tomorrow'}}));
w.upsertOrder=order=>{w.orders[w.orders.findIndex(o=>o.db_id===order.db_id)]=order;};
w.eval(source.slice(source.indexOf('  var readyRequests ='),source.indexOf('  async function startBulkExtend()')));
w.pages={orders:()=>`<table><tbody>${w.orders.map(o=>`<tr><td>${w.tableOrderStatus(o)}</td></tr>`).join('')}</tbody></table>`,orderEditor:()=>'<form>Editor</form>'};
w.eval(source.slice(source.indexOf('  function renderPage() {'), source.indexOf('  /**\n   * Handles hand-offs')));
// Status stays a single control, with timing and errors kept out of its visible text.
for (const fixture of [
  {...w.orders[0],schedule:{overdue:true,text:'Overdue by 31h 43m'},smsState:'failed'},
  {...w.orders[0],smsState:'unknown'},
  {...w.orders[0],status:'Ready',notified:true},
  {...w.orders[0],status:'Ready',notified:false},
  {...w.orders[0],status:'Delivered',notified:true},
  {...w.orders[0],status:'Pending'},
]) {
  const cell=w.document.createElement('td'); cell.innerHTML=w.tableOrderStatus(fixture);
  assert.equal(cell.children.length,1);
  assert.equal(cell.querySelectorAll('.badge').length,1);
  assert.doesNotMatch(cell.textContent,/Overdue|Due Tomorrow|SMS Failed|READY & SEND SMS/);
  if (fixture.smsState==='unknown') assert.equal(cell.querySelector('button').disabled,true);
  if (fixture.status==='Ready') assert.equal(cell.textContent,fixture.notified?'Ready · Notified ✓':'Ready');
}
w.bindReadyConfirmation();
w.renderPage();
const button=id=>w.document.querySelector(`[data-ready-action][data-order-id="${id}"]`);
const text=id=>button(id).textContent.trim();
const flush=async()=>{await Promise.resolve();await Promise.resolve();};
const click=id=>button(id) && !button(id).disabled ? w.confirmReadyAndSend(id) : Promise.resolve();

(async()=>{try {
  const original=button(1), originalClass=original.className;
  original.getBoundingClientRect=()=>({width:165,height:32});
  await click(1);
  assert.equal(requests.length,0);
  assert.equal(button(1),original,'First click must update the same element');
  assert.equal(text(1),'Confirm Ready & SMS');
  assert.equal(button(1).disabled,true);
  assert.equal(button(1).className,originalClass);
  assert.equal(button(1).style.width,'165px');
  assert.equal(button(1).style.height,'32px');
  advance(100);
  await w.confirmReadyAndSend(1); // Even a queued/programmatic second click is rejected.
  advance(699);
  await w.confirmReadyAndSend(1);
  assert.equal(requests.length,0);
  assert.equal(button(1).disabled,true);
  advance(1);
  assert.equal(button(1).disabled,false);
  advance(5200);
  assert.equal(text(1),'Ready for Verification');
  assert.equal(requests.length,0);
  assert.equal(button(1).style.width,'');

  await click(1);
  w.document.dispatchEvent(new w.KeyboardEvent('keydown',{key:'Escape'}));
  assert.equal(text(1),'Ready for Verification');
  assert.equal(timers.size,0);
  await click(1); advance(800); await click(2);
  assert.equal(text(1),'Ready for Verification');
  assert.equal(text(2),'Confirm Ready & SMS');
  assert.equal(button(2).disabled,true);
  advance(800);
  const send=click(2);
  assert.equal(requests.length,1);
  assert.equal(requests[0].url,'/orders/2/notify');
  assert.equal(requests[0].payload.mark_ready,true);
  assert.equal(text(2),'Sending...');
  assert.equal(button(2).disabled,true);
  assert.equal(w.orders[1].status,'Ready for Verification');
  await w.confirmReadyAndSend(2); w.renderPage(); await w.confirmReadyAndSend(2);
  assert.equal(text(2),'Sending...');
  assert.equal(requests.length,1);
  requests[0].resolve({order:{...w.orders[1],status:'Ready',notified:true,smsState:'sent'},message:'SMS accepted',notification:{sent:true}});
  await send;
  assert.equal(button(2),null);
  assert.match(w.document.body.textContent,/Ready · Notified ✓/);
  await w.confirmReadyAndSend(2);
  assert.equal(requests.length,1);

  // Provider failure uses the returned order and existing warning; retry needs two clicks again.
  await click(1); advance(800); const failed=click(1);
  requests[1].resolve({order:{...w.orders[0],status:'Ready for Verification',smsState:'failed'},message:'Provider failed',notification:{sent:false}});
  await failed;
  assert.equal(text(1),'Ready for Verification');
  assert.equal(button(1).disabled,false);
  assert.equal(w.orders[0].status,'Ready for Verification');
  assert.equal(toasts.at(-1).type,'warning');
  await click(1); advance(800); const network=click(1);
  requests[2].reject(new Error('Network unavailable')); await network;
  assert.equal(errors.length,1);
  assert.equal(text(1),'Ready for Verification');

  // Every replacement row works without re-binding listeners, and cancels stale intent.
  for (let i=0;i<3;i++) {
    await click(1); w.renderPage();
    assert.equal(text(1),'Ready for Verification');
    assert.equal(timers.size,0);
  }
  await click(1); w.currentView='editor'; w.renderPage();
  assert.equal(w.readyConfirmation,null);
  w.currentView='list'; w.viewMode='kanban'; w.renderPage();
  await click(3);
  assert.equal(text(3),'Confirm Ready & SMS');
  w.dispatchEvent(new w.Event('blur'));
  assert.equal(text(3),'Stitching');
  await click(3); hidden=true; w.document.dispatchEvent(new w.Event('visibilitychange'));
  assert.equal(text(3),'Stitching');
  hidden=false; advance(7000);
  await click(3); w.dispatchEvent(new w.Event('pagehide'));
  assert.equal(w.readyConfirmation,null);
  await click(3); pageScope.abort();
  assert.equal(w.readyConfirmation,null);
  assert.equal(timers.size,0);
  pageScope=new w.AbortController(); w.bindReadyConfirmation();
  await click(3); advance(800); const early=click(3);
  assert.equal(text(3),'Sending...');
  assert.equal(requests.length,4);
  // Navigating away cannot let a late response mutate the next page.
  pageScope.abort();
  w.document.getElementById('page-container').innerHTML='Another page';
  w.eval(source.slice(source.indexOf('  var readyRequests ='),source.indexOf('  async function startBulkExtend()')));
  assert.equal(w.readyRequests.has(3),true,'In-flight guard survives SPA re-entry');
  await w.confirmReadyAndSend(3);
  assert.equal(requests.length,4);
  requests[3].resolve({order:{...w.orders[2],status:'Ready'},message:'Accepted',notification:{sent:true}});
  await early; await flush();
  assert.equal(w.document.getElementById('page-container').textContent,'Another page');
  assert.equal(timers.size,0);
  console.log('PASS: inline arming, 800ms guard, 6s expiry, Esc, row switching, one request, success/failure, rerenders, early finish and page lifecycle cleanup.');
} finally {dom.window.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
