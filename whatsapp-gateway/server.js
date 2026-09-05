/**
 * Atelier — free self-hosted WhatsApp gateway
 *
 * Does the same job as a paid service like UltraMsg, but runs on this machine
 * and costs nothing. It links to WhatsApp exactly the way WhatsApp Web does:
 * you scan a QR once, and the session is remembered from then on.
 *
 * Like WhatsApp Web, this uses multi-device mode — once linked, it keeps
 * working even when the phone has no internet. The phone is only needed for
 * the initial scan.
 *
 * The admin panel talks to it over a small HTTP API:
 *
 *   GET  /status   is it linked, and what is the QR if not
 *   GET  /qr       a browser page showing the pairing QR
 *   POST /send     queue one message
 *   POST /logout   unlink this device
 */

const fs = require('fs');
const path = require('path');
const express = require('express');
const pino = require('pino');
const qrcode = require('qrcode');
const { Boom } = require('@hapi/boom');

const {
  default: makeWASocket,
  useMultiFileAuthState,
  makeCacheableSignalKeyStore,
  DisconnectReason,
  fetchLatestBaileysVersion,
  Browsers,
} = require('@whiskeysockets/baileys');

// Optional: only used to draw the QR in the console. If it is missing (an
// older install that predates it), the browser page still works.
let qrterminal = null;
try {
  qrterminal = require('qrcode-terminal');
} catch (_) { /* run install.bat again to enable the console QR */ }

/* ------------------------------------------------------------------ */
/*  Config                                                             */
/* ------------------------------------------------------------------ */

const CONFIG_PATH = path.join(__dirname, 'config.json');
const AUTH_DIR = path.join(__dirname, 'session');
const LOG_PATH = path.join(__dirname, 'gateway.log');

const DEFAULTS = {
  port: 3001,
  token: 'change-this-token',
  // Messages are spaced out rather than fired in a burst. Sending 50 messages
  // in two seconds is the fastest way to get a number flagged.
  minDelayMs: 4000,
  maxDelayMs: 9000,
  // A hard ceiling per hour, as a second layer of protection.
  maxPerHour: 60,
};

function loadConfig() {
  if (!fs.existsSync(CONFIG_PATH)) {
    fs.writeFileSync(CONFIG_PATH, JSON.stringify(DEFAULTS, null, 2));
    console.log('[gateway] Created config.json with default settings.');
  }

  try {
    return { ...DEFAULTS, ...JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8')) };
  } catch (err) {
    console.error('[gateway] config.json is not valid JSON, using defaults.');
    return { ...DEFAULTS };
  }
}

const config = loadConfig();

/* ------------------------------------------------------------------ */
/*  Logging                                                            */
/* ------------------------------------------------------------------ */

/**
 * Everything is mirrored to gateway.log. When the gateway runs hidden at
 * startup there is no console to read, so the file is the only record of why
 * something went wrong.
 */
function log(message) {
  const line = `[${new Date().toISOString()}] ${message}`;
  console.log(line);

  try {
    // Keep the log from growing without bound.
    if (fs.existsSync(LOG_PATH) && fs.statSync(LOG_PATH).size > 2_000_000) {
      fs.renameSync(LOG_PATH, LOG_PATH + '.old');
    }
    fs.appendFileSync(LOG_PATH, line + '\n');
  } catch (_) { /* logging must never take the process down */ }
}

/**
 * A crash would stop messages until someone noticed. Nothing here is worth
 * dying over, so errors are logged and the process keeps running — the
 * reconnect logic below recovers the socket on its own.
 */
process.on('uncaughtException', (err) => log(`[fatal-guard] ${err?.stack || err}`));
process.on('unhandledRejection', (err) => log(`[fatal-guard] ${err?.stack || err}`));

/* ------------------------------------------------------------------ */
/*  Connection state                                                   */
/* ------------------------------------------------------------------ */

const state = {
  sock: null,
  connected: false,
  connecting: false,
  qr: null,            // data-URL of the current pairing QR
  qrExpiresAt: null,
  phone: null,         // the linked number, once known
  lastError: null,
  startedAt: Date.now(),
  connectedAt: null,
  sent: 0,
  failed: 0,
  reconnects: 0,
  sentThisHour: [],    // timestamps, trimmed to the last hour
};

const logger = pino({ level: 'silent' });

/**
 * Sent messages are kept briefly so Baileys can answer WhatsApp's "resend
 * that one" requests. Without this, a message that arrives during a wobbly
 * connection can silently never be delivered.
 */
const recentMessages = new Map();

function rememberMessage(key, message) {
  recentMessages.set(key, message);

  // Only the last few hundred matter; anything older has been acknowledged.
  if (recentMessages.size > 400) {
    recentMessages.delete(recentMessages.keys().next().value);
  }
}

/* ------------------------------------------------------------------ */
/*  Connect / reconnect                                                */
/* ------------------------------------------------------------------ */

/**
 * Only one socket may exist at a time. Every connect() call takes the next
 * generation number; when an older socket finally reports that it closed, it
 * sees a newer generation and stays quiet instead of starting a rival
 * reconnect loop. Duplicate sockets were the cause of the random
 * "scan the QR again" prompts.
 */
let generation = 0;
let reconnectAttempts = 0;
let reconnectTimer = null;

/** Backs off from 2s to a 60s ceiling so a long outage does not hammer WhatsApp. */
function backoffDelay() {
  return Math.min(2000 * Math.pow(1.6, reconnectAttempts), 60_000);
}

function scheduleReconnect(reason) {
  clearTimeout(reconnectTimer);

  const delay = backoffDelay();
  reconnectAttempts++;
  state.reconnects++;

  log(`[gateway] Reconnecting in ${Math.round(delay / 1000)}s — ${reason}`);
  reconnectTimer = setTimeout(connect, delay);
}

/** Wipes the saved login. Only ever called when WhatsApp has truly logged us out. */
function clearSession(why) {
  log(`[gateway] Clearing the saved session — ${why}`);

  try {
    // Kept as a backup rather than deleted outright, in case it was salvageable.
    if (fs.existsSync(AUTH_DIR)) {
      const backup = `${AUTH_DIR}.broken-${Date.now()}`;
      fs.renameSync(AUTH_DIR, backup);
    }
  } catch (_) {
    fs.rmSync(AUTH_DIR, { recursive: true, force: true });
  }
}

async function connect() {
  if (state.connecting) return;

  state.connecting = true;
  const myGeneration = ++generation;

  let sock;

  try {
    const { state: authState, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
      version,
      auth: {
        creds: authState.creds,
        // Caching the signal keys cuts disk churn dramatically, which is what
        // corrupts a session on an abrupt shutdown.
        keys: makeCacheableSignalKeyStore(authState.keys, logger),
      },
      logger,
      printQRInTerminal: false,

      // A stock browser signature is treated more kindly than a custom one.
      browser: Browsers.appropriate('Desktop'),

      // Multi-device: the phone is only needed for the initial scan. After
      // that this keeps working with the phone switched off entirely, exactly
      // like WhatsApp Web.
      markOnlineOnConnect: false,
      syncFullHistory: false,

      // Timeouts tuned for a shop PC on ordinary broadband. The keepalive is
      // what stops WhatsApp dropping a connection it thinks has gone away.
      keepAliveIntervalMs: 25_000,
      connectTimeoutMs: 60_000,
      defaultQueryTimeoutMs: 60_000,
      retryRequestDelayMs: 1_000,
      maxMsgRetryCount: 5,
      emitOwnEvents: false,

      // Lets Baileys satisfy a resend request rather than dropping a message.
      getMessage: async (key) => recentMessages.get(key.id) || undefined,
    });

    state.sock = sock;
    sock.ev.on('creds.update', saveCreds);
  } catch (err) {
    state.connecting = false;
    log(`[gateway] Could not start the socket: ${err?.message || err}`);
    scheduleReconnect('startup failed');

    return;
  }

  sock.ev.on('connection.update', async (update) => {
    // A superseded socket must not touch shared state or reconnect.
    if (myGeneration !== generation) return;

    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      state.qr = await qrcode.toDataURL(qr, { margin: 1, width: 320 });
      state.qrExpiresAt = Date.now() + 55_000;
      state.connected = false;

      // Printed here as well as served to the panel, because on a first run
      // the panel's token has usually not been saved yet.
      console.log('');
      console.log('  ================================================');
      console.log('   SCAN THIS CODE WITH YOUR PHONE');
      console.log('   WhatsApp > Settings > Linked Devices > Link a Device');
      console.log('  ================================================');
      console.log('');

      if (qrterminal) {
        qrterminal.generate(qr, { small: true });
        console.log('');
        console.log('  Hard to scan? Open this in your browser instead:');
      } else {
        console.log('  Open this in your browser to scan it:');
      }

      console.log(`  http://localhost:${config.port}/qr`);
      console.log('');
    }

    if (connection === 'open') {
      state.connected = true;
      state.connecting = false;
      state.qr = null;
      state.qrExpiresAt = null;
      state.lastError = null;
      state.connectedAt = Date.now();
      state.phone = sock.user?.id?.split(':')[0] ?? null;

      reconnectAttempts = 0;

      log(`[gateway] Connected as ${state.phone ?? 'unknown number'}. Ready to send.`);
      drain();
    }

    if (connection === 'close') {
      state.connected = false;
      state.connecting = false;

      const code = new Boom(lastDisconnect?.error)?.output?.statusCode;
      const detail = lastDisconnect?.error?.message ?? 'connection closed';

      // Each close reason needs different handling. Treating them all as
      // "logged out" was why the session kept being thrown away and the QR
      // kept coming back.
      switch (code) {
        // WhatsApp asks for a restart right after pairing. Expected, not an error.
        case DisconnectReason.restartRequired:
          log('[gateway] Restart requested after pairing — reconnecting now.');
          reconnectAttempts = 0;
          setTimeout(connect, 1000);
          return;

        // The user unlinked us, or WhatsApp revoked the device. Only here is
        // the saved session genuinely dead.
        case DisconnectReason.loggedOut:
          state.lastError = 'This device was unlinked from the phone. Scan the QR code again.';
          state.phone = null;
          clearSession('WhatsApp logged this device out');
          reconnectAttempts = 0;
          setTimeout(connect, 2000);
          return;

        // The stored credentials no longer work — a fresh scan is the only fix.
        case DisconnectReason.badSession:
          state.lastError = 'The saved session was corrupted. Scan the QR code again.';
          state.phone = null;
          clearSession('bad session file');
          reconnectAttempts = 0;
          setTimeout(connect, 2000);
          return;

        // The same session was opened somewhere else. Backing off avoids two
        // copies fighting over the connection.
        case DisconnectReason.connectionReplaced:
          state.lastError = 'Another copy of the gateway took over this session. Close the other one.';
          log('[gateway] Session replaced elsewhere — waiting 30s before retrying.');
          setTimeout(connect, 30_000);
          return;

        // Everything else is transient: network blips, timeouts, WhatsApp
        // recycling the socket. Keep the session and simply come back.
        default:
          state.lastError = detail;
          scheduleReconnect(`${detail}${code ? ` (code ${code})` : ''}`);
      }
    }
  });

  // Remember outgoing messages so WhatsApp can ask us to resend one.
  sock.ev.on('messages.upsert', ({ messages }) => {
    for (const m of messages) {
      if (m.key?.fromMe && m.key?.id && m.message) rememberMessage(m.key.id, m.message);
    }
  });
}

/* ------------------------------------------------------------------ */
/*  Send queue                                                         */
/* ------------------------------------------------------------------ */

/**
 * Messages go out one at a time with a randomised gap. Bursts are the single
 * biggest trigger for a number being flagged, so throughput is deliberately
 * traded for safety.
 *
 * If the connection drops mid-queue the job is put back and waits for the
 * socket to return, rather than being lost.
 */
const queue = [];
let draining = false;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const randomDelay = () =>
  config.minDelayMs + Math.floor(Math.random() * Math.max(1, config.maxDelayMs - config.minDelayMs));

function withinHourlyLimit() {
  const cutoff = Date.now() - 3_600_000;
  state.sentThisHour = state.sentThisHour.filter((t) => t > cutoff);

  return state.sentThisHour.length < config.maxPerHour;
}

/** Waits for the socket, up to a limit, so a brief blip does not fail a send. */
async function waitForConnection(maxMs = 45_000) {
  const deadline = Date.now() + maxMs;

  while (!state.connected && Date.now() < deadline) {
    await sleep(1000);
  }

  return state.connected;
}

async function drain() {
  if (draining) return;
  draining = true;

  try {
    while (queue.length) {
      if (!state.connected && !(await waitForConnection())) break;

      if (!withinHourlyLimit()) {
        log('[gateway] Hourly limit reached, pausing for a minute.');
        await sleep(60_000);
        continue;
      }

      const job = queue.shift();

      try {
        const jid = `${job.phone}@s.whatsapp.net`;

        // Skip numbers with no WhatsApp account rather than erroring out.
        const [exists] = await state.sock.onWhatsApp(job.phone);
        if (!exists?.exists) {
          job.reject(new Error('This number is not on WhatsApp.'));
          state.failed++;
          continue;
        }

        // Typing first makes the send look like a person, not a script.
        await state.sock.presenceSubscribe(jid);
        await state.sock.sendPresenceUpdate('composing', jid);
        await sleep(1200 + Math.floor(Math.random() * 1500));
        await state.sock.sendPresenceUpdate('paused', jid);

        const sent = await state.sock.sendMessage(jid, { text: job.message });
        if (sent?.key?.id) rememberMessage(sent.key.id, sent.message);

        state.sent++;
        state.sentThisHour.push(Date.now());
        job.resolve();
      } catch (err) {
        // A connection error is not the message's fault — put it back.
        if (!state.connected && job.attempts < 3) {
          job.attempts++;
          queue.unshift(job);
          log(`[gateway] Connection lost mid-send, re-queued (attempt ${job.attempts}).`);
          await sleep(3000);
          continue;
        }

        state.failed++;
        job.reject(err);
      }

      if (queue.length) await sleep(randomDelay());
    }
  } finally {
    draining = false;
  }
}

function enqueue(phone, message) {
  return new Promise((resolve, reject) => {
    queue.push({ phone, message, resolve, reject, attempts: 0 });
    drain();
  });
}

/* ------------------------------------------------------------------ */
/*  HTTP API                                                           */
/* ------------------------------------------------------------------ */

const app = express();
app.use(express.json({ limit: '1mb' }));

/** Is this request coming from the machine the gateway runs on? */
function isLocal(req) {
  const ip = (req.ip || req.socket.remoteAddress || '').replace('::ffff:', '');

  return ip === '127.0.0.1' || ip === '::1' || ip === 'localhost';
}

/**
 * Shared-token auth on every route.
 *
 * `/` and `/qr` are exempt when opened from this machine: pairing is a
 * chicken-and-egg problem otherwise, since the panel cannot show the QR until
 * its token is saved. Anyone browsing localhost is already sitting here.
 */
app.use((req, res, next) => {
  if (req.path === '/') return next();
  if (req.path === '/qr' && isLocal(req)) return next();

  const supplied = req.get('X-Gateway-Token') || req.query.token;

  if (supplied !== config.token) {
    return res.status(401).json({ ok: false, error: 'Invalid gateway token.' });
  }

  next();
});

app.get('/', (_req, res) => {
  res.json({ ok: true, service: 'atelier-whatsapp-gateway', version: '2.0.0' });
});

app.get('/status', (_req, res) => {
  const qrValid = state.qr && state.qrExpiresAt && Date.now() < state.qrExpiresAt;

  res.json({
    ok: true,
    connected: state.connected,
    connecting: state.connecting,
    phone: state.phone,
    qr: qrValid ? state.qr : null,
    error: state.lastError,
    queued: queue.length,
    sent: state.sent,
    failed: state.failed,
    reconnects: state.reconnects,
    uptimeSeconds: Math.floor((Date.now() - state.startedAt) / 1000),
    linkedSeconds: state.connectedAt ? Math.floor((Date.now() - state.connectedAt) / 1000) : 0,
  });
});

app.post('/send', async (req, res) => {
  const phone = String(req.body?.phone ?? '').replace(/\D+/g, '');
  const message = String(req.body?.message ?? '').trim();

  if (!phone) return res.status(422).json({ ok: false, error: 'A phone number is required.' });
  if (!message) return res.status(422).json({ ok: false, error: 'A message body is required.' });

  // A brief reconnect should not fail the send — wait it out first.
  if (!state.connected && !(await waitForConnection(20_000))) {
    return res.status(503).json({
      ok: false,
      error: state.lastError || 'WhatsApp is not linked yet. Scan the QR code first.',
    });
  }

  try {
    await enqueue(phone, message);
    res.json({ ok: true, queued: queue.length });
  } catch (err) {
    res.status(502).json({ ok: false, error: err.message });
  }
});

app.post('/logout', async (_req, res) => {
  try {
    await state.sock?.logout();
  } catch (_) { /* the socket may already be gone */ }

  clearSession('unlinked from the panel');

  state.connected = false;
  state.phone = null;
  state.qr = null;

  res.json({ ok: true });
  setTimeout(connect, 1500);
});

/** A plain page that shows the pairing QR, refreshing itself until linked. */
app.get('/qr', (_req, res) => {
  const qrValid = state.qr && state.qrExpiresAt && Date.now() < state.qrExpiresAt;

  const body = state.connected
    ? `<div class="ok">
         <div class="tick">&#10003;</div>
         <h1>Connected</h1>
         <p>Linked to <b>${state.phone ?? 'your phone'}</b></p>
         <p class="muted">You can close this tab. Messages now send automatically,
            and will keep sending even with your phone switched off.</p>
       </div>`
    : qrValid
      ? `<h1>Scan with your phone</h1>
         <ol>
           <li>Open <b>WhatsApp</b> on your phone</li>
           <li>Tap <b>Settings &rsaquo; Linked Devices</b></li>
           <li>Tap <b>Link a Device</b></li>
           <li>Point the camera at this code</li>
         </ol>
         <img src="${state.qr}" alt="WhatsApp pairing QR code">
         <p class="muted">The code refreshes on its own. This page updates when linked.</p>`
      : `<h1>Starting up&hellip;</h1>
         <p class="muted">Waiting for WhatsApp to respond. This page refreshes on its own.</p>`;

  res.type('html').send(`<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Link WhatsApp</title>
<meta http-equiv="refresh" content="5">
<style>
  body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:#F1F3FF;
         display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding:24px; }
  .card { background:#fff; border:1.5px solid #CDD4F5; border-radius:16px; padding:32px;
          max-width:420px; width:100%; text-align:center; box-shadow:0 4px 16px -8px rgba(79,85,190,.3); }
  h1 { font-size:19px; font-weight:600; color:#24285F; margin:0 0 16px; }
  ol { text-align:left; font-size:14px; color:#4A4F73; line-height:1.9; margin:0 0 20px; padding-left:20px; }
  img { width:260px; height:260px; border:1px solid #E2E6FB; border-radius:12px; }
  .muted { font-size:12px; color:#8A8FB0; margin-top:16px; line-height:1.6; }
  .tick { width:56px; height:56px; border-radius:50%; background:#EDF7F2; color:#348866;
          font-size:28px; line-height:56px; margin:0 auto 12px; }
  .ok h1 { color:#174533; }
  b { color:#24285F; }
</style>
</head><body><div class="card">${body}</div></body></html>`);
});

/* ------------------------------------------------------------------ */
/*  Start                                                              */
/* ------------------------------------------------------------------ */

const server = app.listen(config.port, () => {
  console.log('');
  console.log('  Atelier WhatsApp Gateway');
  console.log(`  Listening on http://localhost:${config.port}`);
  console.log('');
  console.log('  TO LINK YOUR PHONE, open this in your browser:');
  console.log(`  http://localhost:${config.port}/qr`);
  console.log('');

  if (config.token === DEFAULTS.token) {
    console.log('  WARNING: you are still using the default token.');
    console.log('  Change "token" in config.json and paste the same value into the panel.');
    console.log('');
  }

  connect();
});

server.on('error', (err) => {
  if (err.code === 'EADDRINUSE') {
    log(`[gateway] Port ${config.port} is already in use — the gateway is probably already running.`);
    process.exit(0);
  }

  log(`[gateway] Server error: ${err.message}`);
});

/** A heartbeat in the log, so an unattended gateway can be checked after the fact. */
setInterval(() => {
  log(`[heartbeat] connected=${state.connected} phone=${state.phone ?? '-'} sent=${state.sent} failed=${state.failed} queued=${queue.length} reconnects=${state.reconnects}`);
}, 15 * 60_000);
