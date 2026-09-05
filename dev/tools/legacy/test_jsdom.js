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

console.log('JSDOM initialized. window.modals keys:', Object.keys(dom.window.modals || {}));
setTimeout(() => {
  console.log('After timeout, window.modals keys:', Object.keys(dom.window.modals || {}));
}, 1000);
