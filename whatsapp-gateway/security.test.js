const test = require('node:test');
const assert = require('node:assert/strict');
const { secureConfig, authorized } = require('./security');
const token = 'a'.repeat(40);
test('loopback default and explicit remote binding', () => {
  assert.equal(secureConfig({token}, {}).host, '127.0.0.1');
  assert.equal(secureConfig({token}, {GATEWAY_HOST:'10.0.0.2'}).host, '10.0.0.2');
});
test('reject default and missing secrets', () => {
  assert.throws(() => secureConfig({}, {}));
  assert.throws(() => secureConfig({token:'change-this-token'}, {}));
});
test('accept only header authentication', () => {
  assert.equal(authorized({get:()=>undefined,query:{token}}, token), false);
  assert.equal(authorized({get:()=>token}, token), true);
  assert.equal(authorized({get:()=>token+'x'}, token), false);
});
