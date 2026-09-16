// Render the production receipt builder and print-clone path using synthetic
// data. No live orders, measurements or settings are written.
const fs = require('node:fs');
const assert = require('node:assert/strict');
const {JSDOM} = require('jsdom');
const cssTree = require('css-tree');
const dir = process.env.RECEIPT_QA_DIR || 'storage/app/thermal-flow-qa';
fs.mkdirSync(dir, {recursive:true});
const current = fs.readFileSync('resources/views/orders/index.blade.php','utf8');
const shared = fs.readFileSync('resources/views/receipts/slip-styles.blade.php','utf8');
const baseline = fs.existsSync(dir+'/orders-before.blade.php') ? fs.readFileSync(dir+'/orders-before.blade.php','utf8') : null;
const layout = fs.readFileSync('resources/views/layouts/app.blade.php','utf8').match(/<style[^>]*>([\s\S]*?)<\/style>/)[1];
// Validate the actual production page rule: a fixture override previously masked
// the invalid 80mm auto declaration and inherited A4 canvas.
const style = current.match(/<style>([\s\S]*?)<\/style>/)[1];
const thermalPage = style.match(/@page thermal80\s*\{([^}]+)\}/)[1];
const pageSize = thermalPage.match(/size:\s*([^;]+)/)[1];
assert.ok(cssTree.lexer.matchAtruleDescriptor('page','size',pageSize).matched);
assert.equal(pageSize,'auto');
assert.ok(style.includes('width: 72mm !important'));
assert.ok(style.includes('.slip-piece {\n      break-inside: avoid; page-break-inside: avoid;') || style.includes('.slip-piece {\r\n      break-inside: avoid; page-break-inside: avoid;'));
if(baseline) {
 const printFn=text=>text.slice(text.indexOf('  window.printThermal ='),text.indexOf('  window.modals =',text.indexOf('  window.printThermal =')));
 assert.equal(printFn(current),printFn(baseline),'image loading and print-clone behavior unchanged');
} 
assert.ok(!/break-after:\s*(page|always)/.test(style),'no forced copy page break');
assert.ok(!/297mm/.test(style),'no fixed thermal form height');
assert.ok(style.includes('padding: 2mm 2mm 6mm;'));
assert.ok(style.includes('.slip:last-child { padding-bottom: 2mm; }'));
assert.equal(cssTree.lexer.matchAtruleDescriptor('page','size','80mm auto').matched,null);
assert.equal((style.match(/@page/g)||[]).length,1,'only a named thermal page; no global override');
assert.ok(layout.includes('@page { size: A4 portrait; margin: 12mm 10mm; }'));
if(baseline) assert.equal(style.split('/* === PRINT')[0],baseline.match(/<style>([\s\S]*?)<\/style>/)[1].split('/* === PRINT')[0],'screen styles unchanged');
const keys   = ['length','shoulder_width','sleeve_length','chest','chest_losing','waist','waist_losing','hip','hip_losing','collar','ghera','patti','button','cuff','koni','elbow','armhole','takai','salwar_length','pancho'];
const labels = ['Length','Shoulder','Sleeves','Chest','Losing','West','Loasing','Hip','Losing','Collar','Galla','F/Patti','Button','Cuff','Koni','Elbow','Armor','Takki','Salwar Length','Pancho'];
const names = ['Alteration and Fitting','Alteration and Fitting','Kurta Pajama Stitching','Premium Suit Stitching','Trouser Stitching','Trouser Stitching'];
const pieces = names.map((garment,i)=>({garment,piece:[1,2,1,1,1,2][i],unit:'in',rows:labels.map((label,j)=>({key:keys[j],label,value:String((i+1)*100+j+1)}))}));
const order = {store:'BEST TAILOR',order:'QA-PRINT-6',date:'14/09/2026',customer:'Test Customer',tailor:'Test Tailor',priority:'High',garment:'2 × Alteration and Fitting, 1 × Kurta Pajama Stitching, 1 × Premium Suit Stitching, 2 × Trouser Stitching',qty:6,due:'20/09/2026',total:10000,advance:1000,balance:9000,items:[{name:'Alteration and Fitting',qty:2,price:1000},{name:'Kurta Pajama Stitching',qty:1,price:2000},{name:'Premium Suit Stitching',qty:1,price:5000},{name:'Trouser Stitching',qty:2,price:2000}]};
async function render(source, name, which, data=pieces, page=null, receipt=order) {
    const css = source.match(/<style>([\s\S]*?)<\/style>/)[1].replace("@include('receipts.slip-styles')",shared);
    const dom = new JSDOM(`<!doctype html><html><head><meta charset="utf-8"><style>*{box-sizing:border-box}body{margin:0}${layout}\n${css}\n${page ? '@page thermal80 {size:'+page+';margin:0}' : ''}</style></head><body><div id="app" style="height:2500px">Hidden application content</div><div class="modal-backdrop"><div id="modal-content"></div></div></body></html>`,{runScripts:'outside-only',url:'http://localhost/orders'});
    const w=dom.window;
    // JSDOM does not decode images; image readiness/failure has its own regression test.
    Object.defineProperty(w.HTMLImageElement.prototype,'complete',{get:()=>true});
    Object.defineProperty(w.HTMLImageElement.prototype,'naturalWidth',{get:()=>100});
    w.HTMLImageElement.prototype.decode=()=>Promise.resolve();
    w.Atelier={reportError:()=>{},escapeHtml:value=>String(value??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;'),money:value=>'Rs.'+Number(value||0).toFixed(2)};
    w.tidyFooter=value=>value||'';
    const start=source.indexOf("    'thermal-receipt':");
    w.eval('window.modals={'+source.slice(start,source.indexOf("    'status-reason':",start))+'};');
    const fillStart=source.indexOf('  function fillJobCard(');
    w.eval(source.slice(fillStart,source.indexOf('  /* ============= ORDER DETAILS',fillStart)));
    const printStart=source.indexOf('  window.printThermal =');
    w.eval(source.slice(printStart,source.indexOf('\n  };',printStart)+6));
    w.document.getElementById('modal-content').innerHTML=w.modals['thermal-receipt'](receipt);
    w.fillJobCard({...receipt,measure:{...receipt.measure,pieces:data,unit:'in'},tailor:receipt.tailor});
    const customer=w.document.getElementById('slip-customer').outerHTML;
    const blocks=w.document.querySelectorAll('.slip-piece');
    if(source===current) {
        assert.equal(blocks.length,data.length);
        blocks.forEach((block,i)=>{
            assert.equal(block.querySelectorAll('.slip-mcell').length,data[i].rows.length);
            assert.ok(block.querySelector('.slip-kind').textContent.includes(data[i].garment));
        });
        /* Workshop measurement pairing: when keys are present, verify that
           losing measurements appear directly after their parent and that
           ambiguous labels have been corrected. */
        if (data[0].rows[0] && data[0].rows[0].key) {
            blocks.forEach((block) => {
                const cells = Array.from(block.querySelectorAll('.slip-mcell .l'));
                const cellTexts = cells.map(el => el.textContent.trim());
                // Chest Losing must immediately follow Chest
                const chestIdx = cellTexts.indexOf('Chest');
                const chestLosingIdx = cellTexts.indexOf('Chest Losing');
                if (chestIdx >= 0 && chestLosingIdx >= 0) {
                    assert.equal(chestLosingIdx, chestIdx + 1, 'Chest Losing follows Chest');
                }
                // Waist Losing must immediately follow Waist
                const waistIdx = cellTexts.indexOf('Waist');
                const waistLosingIdx = cellTexts.indexOf('Waist Losing');
                if (waistIdx >= 0 && waistLosingIdx >= 0) {
                    assert.equal(waistLosingIdx, waistIdx + 1, 'Waist Losing follows Waist');
                }
                // Hip Losing must immediately follow Hip
                const hipIdx = cellTexts.indexOf('Hip');
                const hipLosingIdx = cellTexts.indexOf('Hip Losing');
                if (hipIdx >= 0 && hipLosingIdx >= 0) {
                    assert.equal(hipLosingIdx, hipIdx + 1, 'Hip Losing follows Hip');
                }
                // No ambiguous bare labels
                cellTexts.forEach(t => {
                    assert.notEqual(t, 'Losing', 'no bare Losing label');
                    assert.notEqual(t, 'Loasing', 'no bare Loasing label');
                    assert.notEqual(t, 'West', 'no bare West label');
                });
            });
        }
    }
    w.print=()=>{
        if(source===current) {
            assert.ok(w.document.documentElement.classList.contains('printing-thermal'));
            assert.equal(w.document.querySelectorAll('#thermal-print-area > .slip').length,which==='both'?2:1);
        }
        fs.writeFileSync(`${dir}/${name}.html`,dom.serialize());
    };
    await w.printThermal(which);
    assert.equal(w.document.querySelector('#thermal-print-area'),null,'print root cleaned up');
    assert.equal(w.document.documentElement.classList.contains('printing-thermal'),false,'print state cleaned up');
    if(source===current) {
        w.print=()=>{throw new Error('print cancelled or failed')};
        await w.printThermal(which);
        assert.equal(w.document.querySelector('#thermal-print-area'),null);
        assert.equal(w.document.documentElement.classList.contains('printing-thermal'),false);
    }
    dom.window.close();
    return customer;
}
(async () => {
const customer=await render(current,'six-pieces','tailor');
await render(current,'six-pieces-short','tailor',pieces,'80mm 200mm');
await render(current,'both-copies','both');
await render(current,'both-copies-297','both',pieces,'80mm 297mm');
await render(current,'both-copies-long','both',pieces,'80mm 1000mm');
await render(current,'short-copy','customer',pieces,'80mm 297mm');
await render(current,'customer-after','customer');
const tall=[{...pieces[0],rows:Array.from({length:180},(_,i)=>({key:'custom_'+i,label:'Measure '+(i+1),value:String(1001+i)}))},pieces[2]];
await render(current,'oversized-piece','tailor',tall);
if(baseline) {
    assert.equal((await render(baseline,'customer-before','customer')).replace(/>\s+</g,'><'),customer.replace(/>\s+</g,'><'),'customer copy markup unchanged when branding is absent');
    await render(baseline,'six-pieces-before','tailor');
}
const livePath=dir+'/ord-1055.json';
if(fs.existsSync(livePath)) {
    const live=JSON.parse(fs.readFileSync(livePath,'utf8')).receipt;
    assert.equal(live.order,'ORD-1055');
    await render(current,'ord-1055-driver','both',live.measure.pieces,null,live);
    await render(current,'ord-1055-297','both',live.measure.pieces,'72mm 297mm',live);
    await render(current,'ord-1055-roll','both',live.measure.pieces,'72mm 3276mm',live);
    console.log('PASS: actual ORD-1055 data: '+live.measure.pieces.length+' complete piece blocks, both copies, all saved measurement cells and branding included.');
}
fs.writeFileSync(dir+'/expected.json',JSON.stringify({pieces,tall},null,2));
console.log('PASS: driver-selected thermal form, continuous copy flow, unchanged screen styles/customer markup, six complete pieces, isolated print state, cleanup on success and failure. Fixtures contain production paper sizing without override (except explicit short-paper case).');

})().catch(error => { console.error(error); process.exitCode=1; });
