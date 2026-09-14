const fs = require('fs');
const html = fs.readFileSync('rendered.html', 'utf8');
const scripts = html.match(/<script\b[^>]*>([\s\S]*?)<\/script>/gi) || [];
scripts.forEach((s, i) => {
  const js = s.replace(/<\/?script[^>]*>/gi, '');
  try {
    const { Script } = require('vm');
    new Script(js);
  } catch(e) {
    console.error('Script ' + i + ' Syntax Error: ' + e.message);
    const lines = js.split('\n');
    const m = e.stack.match(/evalmachine\.<anonymous>:(\d+)/);
    if (m) {
      const n = parseInt(m[1]);
      console.log('Line ' + n + ':', lines[n-1]);
    }
  }
});
