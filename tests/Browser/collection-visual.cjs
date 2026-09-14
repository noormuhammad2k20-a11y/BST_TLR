const fs=require('fs'),path=require('path'),assert=require('assert/strict'),{chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 try {for(const name of ['delivery','settings']) {
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{
 const u=new URL(route.request().url());
 if(u.pathname===`/${name}`)return route.fulfill({contentType:'text/html',body:fs.readFileSync(`storage/app/private/${name}-workflow-qa.html`,'utf8')});
 const asset=path.resolve('public','.'+u.pathname);
 if(asset.startsWith(path.resolve('public')+path.sep)&&fs.existsSync(asset)&&fs.statSync(asset).isFile())return route.fulfill({path:asset});
 if(route.request().resourceType()==='fetch')return route.fulfill({contentType:'application/json',body:'{"success":true,"notifications":[],"counts":{}}'});
 return route.abort();
 });
 await page.goto(`http://localhost/${name}`);await page.waitForTimeout(1000);
 if(name==='settings'){
 await page.getByText('Delivery & Reminders',{exact:true}).click();
 await page.locator('[data-setting="collection_reminder_days"]').fill('10');
 assert.equal(await page.evaluate(()=>collectPanel().collection_reminder_days),'10');
 } else {
 await page.locator('#select-all-chk').check();
 assert.match(await page.locator('#bulk-count').innerText(),/customers selected/);
 await page.evaluate(()=>filterDelivery('Reminder Due'));
 assert.equal(await page.evaluate(()=>selectedIds.size),0);
 await page.evaluate(()=>filterDelivery('All'));
 await page.locator('button[title="View Order"]').first().click();
 assert.equal(await page.locator('#modal-content').getByText('Mark Delivered',{exact:true}).count(),0);
 assert.equal(await page.locator('#modal-content').getByText('Collected by Customer',{exact:true}).count(),0);
 await page.screenshot({path:'storage/app/private/collection-verification-modal.png'});
 await page.evaluate(()=>closeModal());
 }
 await page.screenshot({path:`storage/app/private/${name}-workflow-qa.png`,fullPage:true});
 assert.deepEqual(errors,[]);console.log(`PASS Edge ${name}: loaded existing assets, controls, no JavaScript errors`);await page.close();
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
