const fs = require('node:fs');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const dom = new JSDOM('<main></main>', {runScripts: 'outside-only', url: 'http://localhost'});
const w = dom.window;
const profile = {key:'alteration', fields:['length','chest','thigh'], required:[], labels:{}, at_least_one:true};
w.itemProfilesFixture = {alteration:profile, generic:profile};
w.Atelier = {escapeHtml: value => String(value), money: String};
w.activeServices = [{id:1,name:'Alteration and Fitting',profile,price:100},{id:2,name:'Trouser Alteration',profile,price:100}];
w.customers = [{db_id:1,measurements:[
  {id:110,customer_id:1,garment_type:'Alteration and Fitting',profile_key:'alteration',unit:'cm',length:40,chest:0,details:{thigh:23}},
  {id:111,customer_id:1,garment_type:'Alteration and Fitting',profile_key:'alteration',unit:'cm',length:41,chest:0,details:{thigh:24}},
  {id:112,customer_id:1,garment_type:'Trouser Alteration',profile_key:'alteration',length:99},
  {id:114,customer_id:2,garment_type:'Alteration and Fitting',profile_key:'alteration',length:98},
]},{db_id:2,measurements:[]}];
w.renderPage = () => {};
w.pages = {};
w.confirm = () => true;
w.toast = () => {};
w.wizardStep = 3;
let source = fs.readFileSync('resources/views/orders/item-editor.blade.php','utf8')
  .replace(/@json\(.*MeasurementProfiles::all\(\)\)/, 'itemProfilesFixture')
  .replace(/@json\(.*PricingService::breakdown\(0\)\)/, '{tax_rate:0,service_charge_rate:0}');
w.eval(source);
w.eval('newOrderState.customerId=1; itemChoose(0,1)');
assert.deepEqual(Array.from(w.compatibleSavedMeasurements(w.newOrderState.garments[0]),m=>m.id),[111,110]);
let html = w.renderItemEditor();
assert.equal((html.match(/Use Saved Measurement/g)||[]).length,1);
assert.ok(!html.includes('Use Saved Alteration'));
w.itemSaved(112);
assert.equal(w.newOrderState.garments[0].pieces[0].measurement_id,undefined);
w.itemSaved(''); // New uses the latest compatible set as its baseline.
let piece = w.newOrderState.garments[0].pieces[0];
assert.equal(piece.measurement_mode,'new');
assert.equal(piece.values.length,41);
assert.equal(piece.values.chest,0);
assert.equal(piece.values.thigh,24);
assert.equal(piece.unit,'cm');
w.itemMeasure('length','42');
assert.equal(piece.values.length,'42');
assert.equal(piece.measurement_id,111);
assert.deepEqual(Object.keys(piece.saved_changes.values),['length']);
assert.equal(piece.saved_changes.values.length,'42');
assert.match(w.renderItemEditor(),/Saved measurements loaded/);
assert.equal(w.customers[0].measurements[1].length,41);
w.itemSaved('');
assert.equal(piece.values.length,'42');
assert.equal(piece.values.thigh,24);
assert.equal(piece.values.chest,0);
w.itemSaved(111);
assert.equal(piece.saved_changes,undefined);
assert.equal(piece.values.length,41);
w.itemChoose(0,2);
assert.equal(piece.saved_measurement_id,undefined);
assert.deepEqual(Array.from(w.compatibleSavedMeasurements(w.newOrderState.garments[0]),m=>m.id),[112]);
const customerSelector = fs.readFileSync('resources/views/orders/index.blade.php','utf8')
  .match(/window\.selectWizardCustomer = function\(dbId\) \{[\s\S]*?\n  \};/)[0];
w.eval(customerSelector);
w.itemSaved(112);
w.selectWizardCustomer(2);
assert.equal(Object.keys(piece.values).length,0);
assert.equal(piece.measurement_id,undefined);
w.wizardStep=3;
assert.ok(!w.renderItemEditor().includes('Use Saved Measurement'));
assert.match(w.renderItemEditor(),/No saved measurements/);
dom.window.close();
console.log('PASS: filtering, New retains saved ID and unchanged values, changed-field payload, Use Saved resets edits, customer/garment reset.');
