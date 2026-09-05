'use strict';
const { timingSafeEqual } = require('node:crypto');

function secureConfig(config, env = process.env) {
  const result = { ...config, host: env.GATEWAY_HOST || config.host || '127.0.0.1', token: env.GATEWAY_TOKEN || config.token };
  if (!result.token || result.token === 'change-this-token' || result.token.length < 32) {
    throw new Error('Configure a unique gateway token of at least 32 characters before starting.');
  }
  return result;
}
function authorized(req, token) {
  const supplied = req.get('X-Gateway-Token');
  if (typeof supplied !== 'string') return false;
  const a = Buffer.from(supplied); const b = Buffer.from(token);
  return a.length === b.length && timingSafeEqual(a, b);
}
module.exports = { secureConfig, authorized };
