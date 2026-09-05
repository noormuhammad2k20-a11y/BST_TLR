import fs from 'fs';
import { JSDOM } from 'jsdom';

const html = fs.readFileSync('rendered_view.html', 'utf8');

const dom = new JSDOM(html, {
  runScripts: 'dangerously',
  resources: 'usable'
});

dom.window.onerror = function(message, source, lineno, colno, error) {
  console.log('JSDOM ERROR:', message, error);
};

setTimeout(() => {
  console.log('window.modals keys:', Object.keys(dom.window.modals || {}));
  
  if (dom.window.openModal) {
    console.log('openModal exists. Calling openModal("add-product")');
    dom.window.openModal('add-product');
    console.log('Modal content after openModal:', dom.window.document.getElementById('modal-content').innerHTML.substring(0, 50) + '...');
  } else {
    console.log('openModal function not found');
  }
}, 1000);
