# Auto WhatsApp Setup — Free

**Ab aapko har message pe Send nahi dabana parega. Sab kuch khud ba khud jayega.**

Ye guide 15-20 minute me poori ho jati hai. Ek baar setup, phir hamesha chalta rahega.

---

## Kya laga hai

Aapke panel me ek **free gateway** laga diya hai. Ye wohi kaam karta hai jo UltraMsg $39/month me karta tha — lekin **bilkul muft**, kyunki ye aapke apne computer pe chalta hai.

**Aur ek safety bhi hai:** agar kabhi gateway band ho jaye, to message **zaya nahi hota** — WhatsApp Web khul jata hai aur aap khud bhej dete hain. Isi liye ise **Hybrid** kehte hain.

---

# Setup — 4 Steps

## Step 1 — Node.js install karein (ek hi baar)

Gateway chalane ke liye Node.js chahiye. Free hai.

1. [nodejs.org](https://nodejs.org) kholein
2. Bara green button — **LTS** wala — download karein
3. File chalayein, sab **Next → Next → Install** dabate jayein
4. Computer **restart** kar lein

> Agar pehle se Node.js laga hai to ye step chhor dein.

## Step 2 — Gateway install karein (ek hi baar)

1. Apne project folder me jayein
2. **`whatsapp-gateway`** folder kholein
3. **`install.bat`** pe **double-click** karein
4. Ek kaali screen khulegi aur download shuru ho jayega — **1 se 2 minute** lagenge
5. Jab likha aaye **"Done. Now run: start-gateway.bat"**, to koi bhi key daba kar band kar dein

> Agar likha aaye **"Node.js is not installed"** — matlab Step 1 reh gaya ya restart nahi kiya.

## Step 3 — Token set karein

Ye aapke WhatsApp ka password jaisa hai. Koi aur aapke WhatsApp se message na bhej sake, is liye zaroori hai.

1. Usi `whatsapp-gateway` folder me **`config.json`** file kholein (Notepad se)
2. Aisa kuch dikhega:

```json
{
  "port": 3001,
  "token": "change-this-token",
  "minDelayMs": 4000,
  "maxDelayMs": 9000,
  "maxPerHour": 60
}
```

3. `change-this-token` ki jagah **apna koi bhi password** likhein. Misaal:

```json
  "token": "meri-dukan-2026-xyz",
```

4. File **save** kar dein (Ctrl+S)
5. Ye token **yaad rakhein** — agle step me chahiye

> **Dhyan:** quotes `" "` na hatayein, aur `,` bhi rehne dein. Sirf beech ka lafz badalna hai.

## Step 4 — Phone jorein (QR scan)

1. **`start-gateway.bat`** pe double-click karein
2. Ek kaali window khulegi. **Ise band na karein** — ye khuli rehni chahiye

### Ab browser me QR kholein

3. Browser me ye address kholein:

```
http://localhost:3001/qr
```

4. QR code aa jayega. Ab apna phone lein:
   - WhatsApp kholein
   - **Settings → Linked Devices**
   - **Link a Device** dabayein
   - Screen wala **QR scan** karein
5. Page khud ba khud **hara** ho jayega: **"Connected"**

**Phone jur gaya!**

> QR **CMD window me bhi** dikhta hai. Agar wahan chhota lag raha ho ya theek se na dikhe, to browser wala tareeqa use karein — wo zyada asaan hai.

## Step 5 — Panel ko batayein

Ab panel ko batana hai ke gateway use karna hai.

1. Panel kholein: **Settings → WhatsApp & Alerts**
2. **Provider** me **"Free Gateway — send automatically"** select karein
3. Do khane bharein:
   - **Gateway Address** — `http://localhost:3001` (waise hi rehne dein)
   - **Gateway Token** — jo Step 3 me `config.json` me likha tha, bilkul wohi
4. **Save** dabayein
5. Neeche hara box aa jayega: **"Connected and sending automatically"**

**Ho gaya! Ab sab messages khud ba khud jayenge.**

---

# Step 6 — Auto start (buhat zaroori)

Taake aapko **kabhi bhi** `start-gateway.bat` na chalana pare.

1. `whatsapp-gateway` folder me **`install-autostart.bat`** pe **right-click** karein
2. **"Run as administrator"** chunein
3. Screen pe **[OK] Done** aa jayega

**Bas. Ab kabhi kuch nahi karna.**

Ab jab bhi computer on hoga:
- Gateway **khud chalu** ho jayega
- **Koi window nahi** khulegi — background me chalega
- Agar kabhi band ho jaye to **khud dobara chalu** ho jayega

> Ye ek hi baar karna hai. Wapas band karna ho to `uninstall-autostart.bat` (as administrator).

## Sab theek chal raha hai ya nahi?

Jab bhi check karna ho, **`check-status.bat`** pe double-click karein. Ye bata dega:
- Gateway chal raha hai ya nahi
- Auto start on hai ya nahi
- Aakhri 15 log lines

---

# Roz ka istemal

**Auto start laga diya to kuch nahi karna.** Sab automatic hai: 

| Kab | Kya hota hai |
|---|---|
| Naya order banaya | Customer ko confirmation chala jata hai |
| Order ready kiya | "Aap ka kapra tayyar hai" chala jata hai |
| Payment li | Receipt chali jati hai |
| Delivery date qareeb | Reminder chala jata hai |
| Date aage barhayi | Maafi ka message chala jata hai |
| Poora hisaab clear | Final receipt chali jati hai |

Aapko **kuch nahi karna**.

## Mobile ka internet band ho to?

**Koi farq nahi parta — messages chalte rahenge.**

Bilkul jaise official WhatsApp Web chalti hai: ek baar QR scan ho jaye, uske baad aapka phone band ho, internet off ho, ya phone ghar pe reh jaye — **gateway apna kaam karta rahega**, kyunki wo laptop ke internet se chal raha hai.

Phone sirf **pehli baar QR scan** ke waqt chahiye. Uske baad nahi.

## Har hafte dobara scan karna parega?

**Nahi.** Ek baar scan, phir bhool jayein.

Sirf do soorton me dobara scan karna parega:
- Aap khud phone se **Linked Devices** me ja kar unlink kar dein
- Gateway **14 din** se zyada band raha ho (WhatsApp purane devices khud hata deta hai)

Auto start laga hone ke baad gateway hamesha chalta rahega, is liye ye kabhi nahi hoga.

---

# Agar gateway band ho jaye

**Ghabrayein nahi — kuch zaya nahi hota.**

Panel me setting hai: **"Fall back to manual if the gateway is off"** — ye **ON** hai.

Iska matlab:

- Gateway chal raha hai → message **khud** chala jata hai
- Gateway band hai → **WhatsApp Web khul jata hai**, message likha hua, aap Send dabate hain

Yaani kaam kabhi **rukta nahi**.

---

# Ban se bachne ke liye

Ye tareeqa WhatsApp ke rules ke mutabiq **officially allowed nahi** hai. Risk kam hai, lekin hai. Neeche wali baatein zaroor karein:

## 1. Alag SIM lein — sabse zaroori

Rs. 200 ki nayi SIM lein aur **sirf** isi kaam ke liye rakhein.

Agar kabhi ban ho bhi gaya, to aapka **asli number bilkul safe** rahega. Ye sabse bari hifazat hai.

## 2. Sirf apne customers ko bhejein

Jinhon ne aapse kaam karwaya hai, sirf unhein. Anjaan logon ko **kabhi nahi**.

## 3. Marketing na bhejein

"Sale lag gayi", "20% discount" — aisa **kuch nahi**. Sirf order updates. Log aise messages **Report** karte hain, aur report se ban hota hai.

## 4. Speed pehle se control hai

Maine gateway me safety pehle se laga di hai:

| Cheez | Setting |
|---|---|
| Do messages ke beech | 4 se 9 second ka waqfa |
| Ek ghante me zyada se zyada | 60 messages |
| Bhejne se pehle | "typing..." dikhta hai, jaise koi insaan likh raha ho |

Ye sab is liye hai ke WhatsApp ko lage ke koi **insaan** bhej raha hai, machine nahi.

> Agar aapko kabhi zyada messages bhejne hon, to `config.json` me `maxPerHour` barha sakte hain — **lekin mera mashwara hai na barhayein**. Jitna dheere, utna mehfooz.

## 5. Shuru me thora, phir dheere dheere

Pehle hafte din ke **20-30 messages** se zyada nahi. Phir dheere dheere barhayein. Naya number aur achanak 200 messages — ye pakka ban hai.

---

# Masail aur Hal

### Kaali window pe "Gateway is not running" aa raha hai

`start-gateway.bat` band ho gaya hai. Dobara double-click karein.

### CMD window me QR nahi dikh raha

**Ye bilkul normal hai.** QR browser me kholein:

```
http://localhost:3001/qr
```

Agar CMD me likha hai **"Scan the QR code to link WhatsApp"** — matlab QR **tayyar hai**, bas browser me kholna hai.

> Agar aap ne pehle install kiya tha aur CMD me QR nahi aata, to ek baar **`install.bat`** dobara chala lein — ek naya package add hua hai jo QR ko CMD me draw karta hai. Ya seedha browser wala tareeqa use karein.

### Browser me `{"ok":true,"service":"atelier-whatsapp-gateway"}` aa raha hai

Ye bhi **theek hai** — matlab gateway zinda hai. Aap ne `http://localhost:3001` khola hai.

QR ke liye aakhir me **`/qr`** lagayein:

```
http://localhost:3001/qr
```

### Panel me QR ya green box nahi aa raha

1. Kaali window khuli hai? Agar nahi, `start-gateway.bat` chalayein
2. Panel me **Token** wohi hai jo `config.json` me hai? Dono bilkul same hone chahiye
3. **Save** dabaya tha? Bina save kiye kaam nahi karega
4. Provider **"Free Gateway"** select kiya hai?

### "The gateway rejected the token"

Token match nahi kar raha.

1. `config.json` kholein, token **copy** karein
2. Panel me Token wale khane me **paste** karein
3. **Save** dabayein

> Dots `••••` dikhein to ghabrayein nahi — matlab token save ho chuka hai. Badalna ho to dots hata kar naya paste karein.

### QR scan kiya lekin hara nahi ho raha

- Phone ka **internet** on hai?
- Phone me **WhatsApp khula** hai?
- QR purana ho gaya ho — 30 second intezaar karein, naya QR khud aa jayega
- Phone me **Settings → Linked Devices** dekhein, "Atelier Admin Panel" dikh raha hai?

### Baar baar disconnect hota hai / dobara QR maangta hai

**Ye purani version ka bug tha, ab theek ho gaya hai.**

Masla ye tha ke jab bhi connection thora sa toot-ta tha, purana code samajhta tha ke "WhatsApp ne nikal diya" aur **saved login mita deta tha** — is liye naya QR maangta tha.

Ab har wajah ko alag alag samjha jata hai:
- Network ka masla → login **mehfooz rehta hai**, khud dobara jur jata hai
- Sach me unlink hua → tabhi naya QR maangta hai

Agar ab bhi ho, to `check-status.bat` chalayein aur log dekhein — wahan wajah likhi hoti hai.

### Ek se zyada baar gateway chal gaya

Agar galti se do baar chalu ho jaye, to dono aapas me lartay hain aur connection toot-ta rehta hai.

Ab ye khud handle ho jata hai — doosra khud band ho jata hai. `check-status.bat` se confirm kar lein.

### Messages ja rahe hain lekin bohat dheere

**Ye jaan bujh kar hai.** Har message me 4-9 second lagte hain — safety ke liye. Agar 20 messages bhej rahe hain to lagbhag 2 minute lagenge.

Jaldi na karein. Tez bhejna = ban.

### "This number is not on WhatsApp"

Us customer ka number WhatsApp pe nahi hai, ya ghalat likha hai.

Customer ka record kholein aur number theek karein — format: `923001234567` (bina 0, bina dash).

---

# Sab se zaroori baatein

1. **`install-autostart.bat` ek baar chalayein** (as administrator) — phir kabhi kuch nahi karna
2. **Alag SIM use karein** — dukan ka main number nahi
3. **Sirf order updates bhejein** — marketing bilkul nahi
4. **Speed na barhayein** — jo settings hain wohi mehfooz hain
5. **Phone band ho to bhi chalta hai** — laptop on hona chahiye, phone nahi
6. **Kuch check karna ho to `check-status.bat`** — sab kuch ek jagah bata deta hai

---

# Aage jaa kar

Jab kaam barh jaye aur client thora paisa de sake (**Rs. 200-400 per month**), to **Meta Cloud API** pe shift kar sakte hain. Usme:

- Ban ka **bilkul risk nahi** — wo official system hai
- Computer band ho to bhi chalta hai
- Koi window khuli rakhne ki zaroorat nahi

Maine code aise likha hai ke shift karna sirf **ek setting badalne** jitna asaan hoga. Jab kehna ho, bata dena.

---

*Koi masla ho to Settings → WhatsApp & Alerts kholein. Wahan green/red box se foran pata chal jata hai ke kya masla hai.*
