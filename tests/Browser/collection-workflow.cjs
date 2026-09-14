const fs=require('node:fs'),assert=require('node:assert/strict'),{JSDOM}=require('jsdom');
for(const page of ['delivery','settings']) {
 const html=fs.readFileSync(`storage/app/private/${page}-workflow-qa.html`,'utf8');
 const dom=new JSDOM(html,{runScripts:'outside-only',url:`http://localhost/${page}`}),w=dom.window;
 let error; const controller=new w.AbortController();
 w.Atelier={rowsPerPage:()=>10,money:String,escapeHtml:s=>String(s??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;'),
 onPageReady:()=>{},poll:()=>{},refreshCounters:()=>{},pageSignal:()=>controller.signal,applySettings:()=>{},setBusy:()=>{},
 emptyRow:(n,d)=>`<tr><td>${d.title}</td></tr>`,reportError:e=>error=e,api:{}};
 w.toast=()=>{};w.closeDrawers=()=>{};w.openModal=(name,data)=>w.document.getElementById('modal-content').innerHTML=w.modals[name](data);
 const scripts=[...w.document.querySelectorAll('script:not([src])')];
 const source=scripts.find(s=>s.textContent.includes(page==='delivery'?'var deliveries':'function panelCollectionAlerts')).textContent;
 w.eval(source);
 if(page==='delivery') {
  w.updateStats();w.renderDeliveries();
  assert(w.deliveries.length>0,'Real DB rows render');
  w.toggleSelectAll(true);
  assert.equal(w.selectedIds.size,w.visiblePageItems().filter(w.canNotify).length);
  w.filterDelivery('Reminder Due');assert.equal(w.selectedIds.size,0);
  w.filterDelivery('Notification Needed');w.toggleSelectAll(true);
  assert.match(w.document.getElementById('bulk-count').textContent,/customers selected/);
  const unverified=w.deliveries.find(d=>d.status==='Ready for Verification');
  assert(unverified,'Verification order from real database');
  const modal=w.modals['delivery-details'](unverified);
  assert(!modal.includes('Mark Delivered') && !modal.includes('Collected by Customer'));
  assert.match(modal,/Send Ready SMS/);assert.match(modal,/View Customer/);
  w.filterDelivery('Delivered');
  assert(!w.document.getElementById('deliveryTableBody').innerHTML.includes('Mark Delivered'));
  // Pure view-state tests: no test rows are inserted into the application DB.
  const ready={...unverified,status:'Ready',canNotify:false,notified:true};
  assert.match(w.modals['delivery-details'](ready),/Mark Delivered/);
  assert(!w.modals['delivery-details'](ready).includes('Send Reminder'));
  assert.match(w.modals['delivery-details']({...ready,canNotify:true,smsReason:'collection-reminder',reminderDue:true}),/Send Reminder/);
  const completed=w.modals['delivery-details']({...ready,status:'Delivered',canNotify:false});
  assert(!completed.includes('Mark Delivered') && !completed.includes('Send Reminder'));
  assert.match(completed,/Collected by Customer/);assert.match(completed,/SMS History/);
    const result=w.modals['notify-results']({sent:2,failed:[{customer:'A',order:'1',reason:'failed'}],skipped:[{customer:'B',order:'2',reason:'cooldown'}],results:[]});
  assert.match(result,/2 sent · 1 failed · 1 orders skipped/);
 } else {
  w.switchSettingsPanel('Delivery & Reminders');
  const panel=w.document.getElementById('settings-panel');
  assert.match(panel.textContent,/Delivery & Customer Reminder Alerts/);
  assert(panel.querySelector('[data-setting="collection_reminder_days"]'),'Reminder field exists');
 }
 assert.equal(error,undefined);dom.window.close();console.log(`PASS ${page}: rendered real database page and operator controls`);
}
