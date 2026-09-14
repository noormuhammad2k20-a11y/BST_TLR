const {JSDOM,VirtualConsole}=require('jsdom');
const fs=require('fs');
const assert=require('node:assert/strict');
const errors=[];
const vc=new VirtualConsole();vc.on('jsdomError',e=>errors.push(e.message));
const dom=new JSDOM(fs.readFileSync('storage/app/staff-modal-qa.html','utf8'),{url:'http://localhost/staff',runScripts:'dangerously',pretendToBeVisual:true,virtualConsole:vc,beforeParse(w){w.matchMedia=()=>({matches:false,addEventListener(){}});w.fetch=()=>new Promise(()=>{});w.scrollTo=()=>{};}});
dom.window.addEventListener('load',async()=>{try{
 const w=dom.window;
 const staff={db_id:123,name:'QA',salary_type:'Per Suit',payment_period:'Weekly',is_active:true,per_suit_rate:125.35,due:{period:'2026-W37',pieces:5,earned:626.75,paid:200.25,remaining:426.5}};
 w.openModal('staff-form',staff);
 assert.equal(w.document.getElementById('sf-salary-type').value,'Per Suit|Weekly');
 assert.equal(w.document.getElementById('sf-salary-type').options.length,9);
 w.openModal('staff-payment',staff);
 w.staffPaymentDue=staff.due;
 const amount=w.document.getElementById('pay-amount');amount.value='100.25';w.previewPayment();
 assert.match(w.document.getElementById('payment-summary').textContent,/326.25/);
 assert.match(w.document.getElementById('payment-summary').textContent,/300.50/);
 assert.equal(w.document.getElementById('pay-period').type,'week');
 let requested='';w.Atelier.api.get=async url=>{requested=url;return {due:{...staff.due,period:'2026-W38',remaining:50}}};
 w.document.getElementById('pay-period').value='2026-W38';
 await w.refreshPaymentDue(123);
 assert.match(requested,/2026-W38/);assert.equal(amount.value,'50');
 for(const [frequency,type,period] of [['Daily','date','2026-09-11'],['Monthly','month','2026-09']]) {
   w.openModal('staff-payment',{...staff,payment_period:frequency,due:{...staff.due,period}});
   assert.equal(w.document.getElementById('pay-period').type,type);
   assert.equal(w.document.getElementById('pay-period').value,period);
 }
 assert.deepEqual(errors,[]);
 console.log('PASS: existing modal layout, cycle configuration, live balance preview, period refresh, daily/weekly/monthly inputs.');
}catch(e){console.error(e);process.exitCode=1;}finally{dom.window.close();}});
