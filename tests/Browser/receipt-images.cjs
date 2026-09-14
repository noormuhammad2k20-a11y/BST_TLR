const fs=require('node:fs');
const assert=require('node:assert/strict');
const {JSDOM}=require('jsdom');
const source=fs.readFileSync('resources/views/orders/index.blade.php','utf8');
const settings=fs.readFileSync('resources/views/settings/index.blade.php','utf8');
function setup(){
 const dom=new JSDOM('<div id="modal-content"></div>',{url:'http://localhost/orders',runScripts:'outside-only'});
 const w=dom.window; const errors=[];let prints=0;
 w.Atelier={escapeHtml:x=>String(x??'').replaceAll('&','&amp;').replaceAll('"','&quot;'),money:x=>String(x||0),reportError:e=>errors.push(e)};
 w.tidyFooter=x=>x||'';
 const start=source.indexOf("    'thermal-receipt':");
 w.eval('window.modals={'+source.slice(start,source.indexOf("    'status-reason':",start))+'};');
 const printStart=source.indexOf('  window.printThermal =');
 w.eval(source.slice(printStart,source.indexOf('\n  };',printStart)+6));
 const previewStart=settings.indexOf('  window.printReceiptPreview =');
 w.eval(settings.slice(previewStart,settings.indexOf('\n  };',previewStart)+6));
 w.print=()=>{prints++;};
 return {dom,w,errors,prints:()=>prints};
}
(async()=>{
 for(const showLogo of [false,true])for(const showStamp of [false,true]){
  const t=setup(),w=t.w;
  // Exercise the real openReceipt mapping, including saved toggles.
  w.orders=[{db_id:1,id:'TEST',createdAt:'2026-09-14',garments:[]}];
  w.Atelier.shop={name:'SHOP',logo:'/storage/branding/logo.png',stamp:'/storage/branding/stamp.png',showLogo,showStamp};
  w.Atelier.api={get:()=>Promise.resolve({receipt:{}})};
  w.ROUTES={receipt:()=>'/receipt/1'};w.fillJobCard=()=>{};
  w.openModal=(type,data)=>w.document.getElementById('modal-content').innerHTML=w.modals[type](data);
  const openStart=source.indexOf('  window.openReceipt =');
  w.eval(source.slice(openStart,source.indexOf('\n  };',openStart)+6));
  w.openReceipt(1);
  assert.equal(w.document.querySelectorAll('#slip-customer img').length,Number(showLogo)+Number(showStamp));
  assert.equal(w.document.querySelectorAll('#slip-customer .slip-shop').length,Number(showLogo));
  const pending=w.printThermal('customer');
  const images=[...w.document.querySelectorAll('#thermal-print-area img')];
  assert.equal(t.prints(),0,'never print synchronously before image readiness');
  for(const [i,img] of images.entries()){
   assert.ok(img.src.startsWith('http://localhost/storage/branding/'));
   assert.ok(!img.src.includes('/storage//storage/'));
   Object.defineProperty(img,'naturalWidth',{value:100});
   img.decode=()=>Promise.resolve();
   img.dispatchEvent(new w.Event('load'));
   await Promise.resolve();
   if(i<images.length-1)assert.equal(t.prints(),0,'wait for BOTH images');
  }
  await pending;
  assert.equal(t.prints(),1);assert.equal(t.errors.length,0);
  assert.equal(w.document.querySelector('#thermal-print-area'),null);
  assert.equal(w.document.documentElement.classList.contains('printing-thermal'),false);
  t.dom.window.close();
 }
 const t=setup(),w=t.w;
 w.document.getElementById('modal-content').innerHTML=w.modals['thermal-receipt']({logo:'http://localhost/storage/branding/logo.png',stamp:'/storage/branding/stamp.png'});
 const pending=w.printThermal('customer');
 for(const img of w.document.querySelectorAll('#thermal-print-area img'))img.dispatchEvent(new w.Event('error'));
 await pending;assert.equal(t.prints(),0);assert.equal(t.errors.length,1);assert.equal(w.document.querySelector('#thermal-print-area'),null);
 // Settings preview also waits for every image's decode before printing.
 w.document.body.innerHTML='<div id="print-receipt"><img src="/storage/branding/logo.png"><img src="/storage/branding/stamp.png"></div>';
 const release=[];
 for(const img of w.document.querySelectorAll('img')){Object.defineProperty(img,'naturalWidth',{value:100});img.decode=()=>new Promise(r=>release.push(r));}
 const preview=w.printReceiptPreview();assert.equal(t.prints(),0);
 release[0]();await Promise.resolve();assert.equal(t.prints(),0);
 release[1]();await preview;assert.equal(t.prints(),1);
 t.dom.window.close();
 console.log('PASS: saved logo/stamp URLs, all toggle combinations, both images awaited, failed images block printing, cleanup, and Settings preview decode wait.');
})().catch(e=>{console.error(e);process.exitCode=1});
