const fs = require('fs');
let content = fs.readFileSync('resources/views/staff/index.blade.php', 'utf8');
let m = content.match(/<script>([\s\S]*?)<\/script>/);
if(m) {
  let js = m[1].replace(/@json\(.*?\)/g, '\"\"');
  fs.writeFileSync('test.js', js);
}
