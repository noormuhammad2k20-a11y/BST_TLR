const fs = require('node:fs');
const assert = require('node:assert/strict');
const {JSDOM} = require('jsdom');
const blade = fs.readFileSync('resources/views/customers/index.blade.php','utf8');
const dom = new JSDOM(`<main>${blade.split('@section(\'content\')')[1].split('@endsection')[0]}</main><div id="modal-content"></div>`, {runScripts:'outside-only',url:'http://localhost/customers'});
const w=dom.window;
let pendingConfirm, request, error;
const forms={};
w.Atelier={
  refreshCounters:()=>{},
  rowsPerPage:()=>10, money:String, escapeHtml:s=>String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'),
  onPageReady:fn=>fn(), ajaxForm:(selector,options)=>forms[selector]=options,
  emptyRow:(_,data)=>`<tr><td>${data.title}</td></tr>`,
  confirmAction:opts=>pendingConfirm=opts, reportError:err=>error=err,
  api:{delete:async(url,opts)=>{request={url,opts};return {message:'Done'};},post:async(url)=>{request={url};return {message:'Restored',customer:w.archivedCustomers.find(c=>url.includes(`/${c.db_id}/`))};}},
};
w.openModal=(name,data)=>w.document.getElementById('modal-content').innerHTML=w.modals[name](data);
w.closeModal=()=>{};w.closeDrawers=()=>{};w.toast=()=>{};
const source=blade.match(/<script>([\s\S]*?)<\/script>/)[1]
  .replace(/@json\(route\([^\n]+?\)\)/g,JSON.stringify('/customers/__CUSTOMER__/sms'))
  .replace('@json($customers)','[]').replace('@json($archivedCustomers)','[]').replace('@json($orders)','[]');
w.eval(source);
const customer={db_id:1,id:'C-1042',name:'Active Test',phone:'03001234567',type:'Regular',loyalty:0,orders:2,spent:1000};
const archived={...customer,db_id:2,id:'C-1043',name:'Archived Test',archived:true};
w.customers=[customer]; w.archivedCustomers=[archived];
(async()=>{try{
  w.renderCustomerTable();
  assert.match(w.document.getElementById('customer-list').textContent,/Active Test/);
  assert.doesNotMatch(w.document.getElementById('customer-list').textContent,/Archived Test/);
  w.deleteCustomer(1); assert.equal(pendingConfirm.confirmLabel,'Archive Customer');
  await pendingConfirm.onConfirm(); assert.equal(request.url,'/customers/1');
  assert.equal(w.customers.length,0); assert.equal(w.archivedCustomers.length,2);
  w.setCustomerArchiveView(true);
  assert.match(w.document.getElementById('customer-list').textContent,/Restore/);
  assert.match(w.document.getElementById('customer-list').textContent,/Delete Permanently/);
  assert.equal(w.document.querySelectorAll('#customer-list [title="Edit"]').length,0);
  w.confirmPermanentCustomerDelete(2);
  let button=w.document.getElementById('permanent-customer-delete-btn');
  assert.equal(button.disabled,true);
  const input=w.document.getElementById('customer-delete-confirmation');
  input.value='delete'; await w.permanentlyDeleteCustomer(2,button);
  assert.equal(request.url,'/customers/1');
  input.value='DELETE'; w.eval(input.getAttribute('oninput').replace('this.value',"'DELETE'"));
  assert.equal(button.disabled,false);
  await w.permanentlyDeleteCustomer(2,button);
  assert.equal(request.url,'/customers/2/permanent');
  assert.equal(request.opts.body.confirmation,'DELETE'); assert.equal(w.archivedCustomers.length,1);
  forms['#add-customer-form'].onError({payload:{archived_customer:{...customer,name:'<unsafe>'}}});
  assert.match(w.document.getElementById('modal-content').textContent,/Restore Customer/);
  assert.equal(w.document.querySelector('#modal-content unsafe'),null);
  await w.restoreCustomer(1);
  assert.equal(request.url,'/customers/1/restore'); assert.equal(w.customers.length,1); assert.equal(w.archivedCustomers.length,0);
  w.setCustomerArchiveView(false); assert.match(w.document.getElementById('customer-list').textContent,/Active Test/);
  assert.equal(error,undefined);
  console.log('PASS: customer archive, separate archive view, restore, archived-phone message, escaped names and typed permanent-delete confirmation.');
}finally{dom.window.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
