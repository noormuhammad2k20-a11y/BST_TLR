const fs = require('node:fs');
const assert = require('node:assert/strict');
const {JSDOM} = require('jsdom');
const dom = new JSDOM('<div id="modal"></div>', {runScripts:'outside-only',url:'http://localhost/orders',pretendToBeVisual:true});
const w = dom.window;
const source = fs.readFileSync('resources/views/orders/index.blade.php','utf8');
let requests = 0, resolveRequest, modalOpens = 0;
w.Atelier = {pageSignal:()=>new w.AbortController().signal,escapeHtml:s=>String(s),refreshCounters:()=>{},reportError:e=>{throw e;},api:{post:()=>{
  requests++; return new Promise(resolve=>resolveRequest=resolve);
}}};
w.renderPage=()=>{}; w.closeModal=()=>{}; w.toast=()=>{};
w.upsertOrder=o=>w.updated=o; w.ROUTES={notify:id=>`/orders/${id}/notify`};
w.openModal=()=>{modalOpens++;};
w.eval(source.slice(source.indexOf('  var readyRequests ='),source.indexOf('  async function startBulkExtend()')));
const order={db_id:1,status:'Ready for Verification',smsState:'not_sent',schedule:{text:'Due in 2 Days'}};
w.orders=[order];
assert.match(w.readyAction(order),/READY &amp; SEND SMS|READY & SEND SMS/);
assert.match(w.readyAction({...order,status:'Stitching'}),/FINISHED/);
assert.equal(w.readyAction({...order,status:'Ready'}),'');
assert.match(w.readyAction({...order,smsState:'failed'}),/SMS Failed/);

(async()=>{try {
  await w.confirmReadyAndSend(1);
  assert.equal(requests,0);
  await new Promise(resolve=>w.setTimeout(resolve,810));
  const first=w.confirmReadyAndSend(1);
  await w.confirmReadyAndSend(1);
  assert.equal(requests,1);
  assert.match(w.readyAction(order),/disabled/);
  assert.equal(modalOpens,0);
  resolveRequest({order:{...order,status:'Ready'},message:'Accepted',notification:{sent:true}});
  await first;
  assert.equal(w.updated.status,'Ready');
  assert.equal(w.readyRequests.size,0);

  w.customers=[{db_id:10,name:'Ali',phone:'03001234567'},{db_id:11,name:'Ahmed',parent_customer_id:10,relationship:'Son',phone:'03001234567'}];
  w.newOrderState={customerId:10,notes:'Order note',date:'2026-10-20',advance:50,garments:[{unit_price:100,fabric:'Cotton',pieces:[{values:{chest:40,notes:'Ali only'},measurement_id:4}]}]};
  w.eval(source.match(/window\.selectWizardCustomer = function\(dbId\) \{[\s\S]*?\n  \};/)[0]);
  w.eval(source.slice(source.indexOf('  window.addOrderFamilyMember ='),source.indexOf('  function generateCustomerCard')));
  assert.match(w.orderFamilySelector(),/Ahmed/);
  w.selectWizardCustomer(11);
  assert.equal(w.newOrderState.customerId,11);
  assert.deepEqual(Object.keys(w.newOrderState.garments[0].pieces[0].values),[]);
  assert.equal(w.newOrderState.garments[0].unit_price,100);
  assert.equal(w.newOrderState.notes,'Order note');

  const formSource=fs.readFileSync('resources/views/components/family-member-form.blade.php','utf8')
    .replace(/<\/?script>/g,'')
    .replace(/@json\(\\App\\Models\\Customer::RELATIONSHIPS\)/g,JSON.stringify(['Son','Father','Brother','Uncle','Cousin','Other']))
    .replace(/@json\(route\('customers.store'\)\)/g,JSON.stringify('/customers'));
  w.eval(formSource);
  w.openModal=(name,data)=>w.document.getElementById('modal').innerHTML=w.modals[name](data);
  w.addOrderFamilyMember(10);
  const form=w.document.querySelector('form');
  assert.equal(form.elements.phone.required,false);
  form.elements.name.value='Usman'; form.elements.relationship.value='Son';
  w.Atelier.api.post=async(url,payload)=>{
    assert.equal(url,'/customers'); assert.equal(payload.phone,null); assert.equal(payload.parent_customer_id,10);
    return {customer:{db_id:12,name:'Usman',parent_customer_id:10,relationship:'Son',effective_phone:'03001234567'}};
  };
  await w.saveFamilyMember({preventDefault:()=>{},target:form},10);
  assert.equal(w.newOrderState.customerId,12);
  assert.equal(w.newOrderState.advance,50);
  assert.equal(w.newOrderState.date,'2026-10-20');
  assert.equal(w.newOrderState.garments[0].fabric,'Cotton');
  assert.equal(w.customers.at(-1).measurements.length,0);
  console.log('PASS: confirmed ready action, double-click guard, local update, family selection, optional phone and quick add preserve order data.');
} finally {dom.window.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
