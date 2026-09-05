# Atelier WhatsApp Gateway

Free self-hosted WhatsApp sender for the admin panel. Does the same job as a
paid service like UltraMsg, but runs on this machine and costs nothing.

**Poori guide (Roman Urdu me):** `../WHATSAPP-AUTO-SETUP.md`

---

## Quick start

1. Install [Node.js LTS](https://nodejs.org) — one time only
2. Double-click **`install.bat`** — one time only
3. Open **`config.json`** and change `token` to your own password
4. Double-click **`start-gateway.bat`** and leave the window open
5. In the panel: **Settings → WhatsApp & Alerts** → provider **Free Gateway**,
   paste the same token, Save, then scan the QR code that appears

---

## config.json

| Key | What it does |
|---|---|
| `port` | Port the gateway listens on. Change only if 3001 is taken. |
| `token` | Shared password. Must match the panel exactly. |
| `minDelayMs` / `maxDelayMs` | Random gap between messages. Raising these is safer, not slower in any way that matters. |
| `maxPerHour` | Hard ceiling per hour. Lower is safer. |

The delay and hourly cap exist to keep the number from being flagged. Sending
in bursts is the fastest way to get banned — leave these alone unless you have
a specific reason.

---

## HTTP API

Every route needs the `X-Gateway-Token` header.

| Route | Purpose |
|---|---|
| `GET /status` | Linked or not, plus the pairing QR when waiting |
| `POST /send` | Queue one message — `{ phone, message }` |
| `POST /logout` | Unlink the phone |

---

## Notes

- `session/` holds the WhatsApp login. Anyone who copies it can send as you.
  It is gitignored — keep it that way.
- Closing the window stops automatic sending. The panel falls back to manual
  WhatsApp Web links, so no message is lost.
- This uses the WhatsApp Web protocol, which is not officially sanctioned by
  WhatsApp. Use a separate SIM, send only order updates to your own customers,
  and never send marketing through it.
