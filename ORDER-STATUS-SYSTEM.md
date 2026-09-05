# Order Status System — Kaise Kaam Karta Hai

> Ye document batata hai ke ek order ka status kahan se kahan jata hai, kaun
> use badalta hai (insaan ya server), aur "Auto-starting..." ke peeche asal
> mein kya chal raha hai.
>
> Project: `test-fnal_telor` · Aakhri update: 20 August 2026

---

## 1. Ek Nazar Mein

Order ka safar paanch stages ka hai:

```
Pending  →  In Progress  →  Ready for Verification  →  Ready  →  Delivered
  10%          40%                  70%                100%       100%
   ▲
   └── SIRF ye ek hop automatic hai. Baaqi sab staff khud move karta hai.
```

Iske ilawa do terminal statuses hain jo workflow ke bahar hain:

| Status | Progress | Matlab |
|---|---|---|
| `Completed` | 100% | Order band, kaam mukammal |
| `Cancelled` | 0% | Order cancel |

**"Overdue" koi status nahi hai.** Ye ek *haalat* hai, stage nahi — kyunke ek
garment late bhi ho sakta hai aur usi waqt machine par bhi ho sakta hai. Isi
liye lateness `delivery_date` se on-the-fly nikaali jati hai
(`Order::$is_overdue`), status column ke upar likhi nahi jati.

**Code reference:** `app/Models/Order.php` → `WORKFLOW`, `PROGRESS_MAP`

---

## 2. Auto-Start: Pending → In Progress

Ye system ka **wahid** automatic status change hai.

### Rule

> Agar koi order `Pending` mein hai aur usay bane hue `auto_status_pending_hours`
> se zyada waqt guzar chuka hai, to server usay khud `In Progress` par le jaayega.

### Settings

Settings → **Workflow** tab mein do control hain:

| Setting key | Default | Kaam |
|---|---|---|
| `auto_status_enabled` | `1` (ON) | Auto-start on/off ka master switch |
| `auto_status_pending_hours` | `1` hour | Kitni der Pending mein rehne ke baad move ho (1–168 hours) |

Agar `auto_status_enabled` OFF hai to kuch nahi hota — orders Pending hi rehte
hain jab tak koi khud na badle.

### Chalta Kahan Se Hai (teen jagah)

Auto-start teen alag trigger points se chalta hai, taake shop ki setup chahe
jaisi bhi ho, kaam ruke nahi:

| # | Trigger | Kab chalta hai | Faida |
|---|---|---|---|
| 1 | `/orders` page load | Jab koi orders screen kholta hai | Scheduler ki zaroorat nahi |
| 2 | Background poll (`live()`) | Jab page khula ho aur poll kare | Bina reload ke badge flip ho jata hai |
| 3 | `php artisan orders:sweep` | Har 15 minute (scheduler se) | Koi app khole bina bhi chalta rahe |

Teeno ek hi method call karte hain: `OrderService::autoStartPendingOrders()`.

**Code reference:**
- `app/Services/OrderService.php` → `autoStartPendingOrders()`
- `app/Http/Controllers/OrderController.php` → `index()` aur `live()`
- `app/Console/Commands/SweepOrderStatuses.php`
- `routes/console.php` → `Schedule::command('orders:sweep')->everyFifteenMinutes()`

### Safety Limits

Do guard rails lagi hain taake ye system ko overload na kare:

- **Rate limit — 5 minute.** Cache key `orders.sweep.auto_status` rakhti hai.
  Agar 20 log ek saath page kholein, sweep phir bhi 5 minute mein sirf ek baar
  chalega. (Artisan command `force: true` ke saath chalti hai, wo rate limit
  ignore karti hai.)
- **Batch limit — 200 orders.** Ek run mein zyada se zyada 200 orders move
  honge, sabse purane pehle. Agar 500 pending hain to teen runs lagenge.

---

## 3. Auto-Start Ke Sath Kya Kya Hota Hai

Auto-start `changeStatus()` ke through jata hai — wahi method jo staff ke manual
change par chalti hai. Iska matlab hai automatic move bhi **poora** record
chhodta hai, koi shortcut nahi:

| Side effect | Kya hota hai |
|---|---|
| Status history | `order_status_histories` mein row: `Pending → In Progress`, note ke saath |
| History note | `"Auto-started after 1 hour in Pending"` |
| Progress | `10%` se `40%` ho jata hai |
| Notification | Customer ke liye status-change notification banti hai |
| Delivery record | `Delivery` row sync hoti hai (status `Scheduled` rehta hai) |
| Activity log | Audit trail mein `status_changed` entry |
| Stats cache | Flush ho jata hai, dashboard ke numbers turant sahi |

Yaani baad mein aap dekh sakte hain ke ye order kis waqt aur kyun move hua.

---

## 4. UI Countdown

Orders table mein har Pending order ke status badge ke neeche chhota sa text
aata hai. Wo `getAutoStatusCountdown()` se banta hai
(`resources/views/orders/index.blade.php`):

| Screen par | Matlab |
|---|---|
| `Auto → In Progress in 2h 15m` | Abhi 2 ghante 15 minute baaqi hain |
| `Auto → In Progress in 40m` | 40 minute baaqi |
| `Auto-starting...` | Waqt poora ho chuka — agla sweep isay move kar dega |
| *(kuch nahi)* | Ya to `auto_status_enabled` OFF hai, ya order Pending mein nahi hai |

> **Zaroori:** `Auto-starting...` ka matlab "ho gaya" nahi, balke "queue mein hai"
> hai. Ye label agle sweep par (max 5 minute, ya page reload par) `In Progress`
> ban jayega.

---

## 5. Baaqi Stages — Manual Kyun Hain?

`In Progress` ke baad ka har step **physical kaam** ke bare mein ek daawa hai:

- *Ready for Verification* = "silai ho gayi, check karo"
- *Ready* = "garment shelf par hai, customer aa sakta hai"
- *Delivered* = "customer le gaya"

Server ko ye pata nahi ho sakta. Agar clock ye khud badal de to board jhoot
bolne lagega — customer ko "ready" ka message chala jayega jabke garment abhi
machine par hai. Isi liye ye teeno hop sirf staff ke haath se hote hain.

Sirf `Pending → In Progress` mehfooz hai, kyunke uska matlab hi "kaam queue se
nikal kar shuru ho gaya" hai — aur wo waqt guzarne se khud ba khud sach ho jata
hai.

---

## 6. Ek Aur Automatic Cheez: Auto-Delivery

Ye status *workflow* se alag hai lekin isi family ka hai:

| Setting | Default | Kaam |
|---|---|---|
| `auto_delivery_update` | `1` (ON) | Jab order ka balance poora ada ho jaye **aur** status `Ready` ho, to usay khud `Delivered` kar do |

Ye tab chalta hai jab koi payment record hoti hai
(`OrderService::recalculateBalance()`). Shart dono hain — sirf paisa poora hona
kaafi nahi, garment `Ready` bhi hona chahiye.

---

## 7. Overdue Alert

Ye status nahi badalta, sirf **alert** uthata hai.

`OrderService::flagOverdueOrders()` un orders ko dhoondta hai jinki
`delivery_date` guzar chuki hai aur status abhi bhi khula hai (`Pending`,
`In Progress`, `Ready for Verification`, `Ready`). Har aise order ke liye ek
notification banti hai — status ko haath nahi lagaya jata.

Ye bhi wahi teen trigger points use karta hai (page load, `orders:sweep`), aur
iski apni 5-minute rate limit hai (`orders.sweep.overdue`).

---

## 8. Testing / Troubleshooting

### Manual test

```bash
php artisan optimize:clear
php artisan orders:sweep
```

Output aisa aayega:

```
Auto-started 3 order(s).
Flagged 0 order(s) as overdue.
```

Phir `/orders` reload karein — wo orders `In Progress` mein honge.

### Scheduler background mein chalane ke liye

```bash
php artisan schedule:work
```

Ye har 15 minute par `orders:sweep` chalata rahega, chahe koi app khole ya na
khole. `withoutOverlapping()` lagi hui hai, to do runs takrayenge nahi.

### Agar auto-start kaam na kare — checklist

1. **Setting ON hai?** Settings → Workflow → `auto_status_enabled` check karein.
2. **Waqt poora hua?** `auto_status_pending_hours` dekhen. Agar 24 set hai to
   order ko 24 ghante lagenge.
3. **Rate limit to nahi?** 5 minute wali cache. `php artisan cache:clear`
   chalayen aur dobara try karein.
4. **Order waqai `Pending` hai?** Auto-start sirf `Pending` par lagta hai.
5. **200 se zyada orders hain?** Command dobara chalayen — har run 200 karta hai.
6. **Config cache purani?** `php artisan optimize:clear`.

---

## 9. Quick Reference — Files

| File | Kya hai us mein |
|---|---|
| `app/Models/Order.php` | `WORKFLOW`, `PROGRESS_MAP`, `progressFor()`, `is_overdue` |
| `app/Services/OrderService.php` | `autoStartPendingOrders()`, `changeStatus()`, `flagOverdueOrders()`, `recalculateBalance()` |
| `app/Http/Controllers/OrderController.php` | `index()` aur `live()` — sweep trigger points |
| `app/Console/Commands/SweepOrderStatuses.php` | `orders:sweep` artisan command |
| `routes/console.php` | 15-minute schedule |
| `app/Services/Settings.php` | `auto_status_*`, `auto_delivery_update` defaults |
| `resources/views/orders/index.blade.php` | `getAutoStatusCountdown()` — UI countdown |

---

## 10. Pehli Baar Chalane Par — Dhyan Rakhein

Agar aapke database mein purane Pending orders jama hain (jaise ORD-1021,
ORD-1022), to **pehli sweep par wo sab ek saath move honge**. Har ek ki apni
notification banegi, to notification list mein achanak kaafi entries aa
sakti hain.

Ye normal hai — backlog ek dafa clear ho jayega. Agar aap chahte hain ke pehli
baar ye chup-chaap ho (bina notification ke), to `orders:sweep` par ek `--quiet`
flag add kiya ja sakta hai.
