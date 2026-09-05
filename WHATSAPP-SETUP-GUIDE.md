# WhatsApp Setup Guide

**Apne admin panel ko WhatsApp se kaise jorein**

Ye guide un logon ke liye hai jinke paas **simple WhatsApp Messenger** hai (normal wala jo aap roz use karte hain). WhatsApp Business ya koi special account ki zaroorat nahi.

---

## Pehle ye samajh lein: 2 tareeqay hain

Aapke panel me WhatsApp bhejne ke **do** tareeqay hain. Dono me messages aapke apne likhe hue templates se jate hain — farq sirf ye hai ke **bhejta kaun hai**.

| | **Manual Mode** | **UltraMsg Mode** |
|---|---|---|
| Kharcha | **Bilkul free** | Lagbhag **$39/month** |
| Setup | Kuch nahi karna | 10 minute ka setup |
| Message kaise jata hai | WhatsApp khulta hai, message likha hua aata hai, aap **Send dabate hain** | Panel khud bhej deta hai, aapko kuch nahi karna |
| Aapka phone | Har message pe chahiye | Sirf ek baar QR scan ke waqt |
| Account ban ka risk | **Bilkul nahi** | Thoda risk hai (neeche parhein) |
| Kis ke liye best | Chhoti dukan, din me 20-30 messages | Zyada volume, ya jab aap busy hoon |

> **Mera mashwara:** Shuru **Manual Mode** se karein. Ye free hai, foran chalta hai, aur bilkul safe hai. Jab kaam barh jaye aur manually bhejna mushkil lage, tab UltraMsg le lena.

---

# Tareeqa 1 — Manual Mode (Free, Recommended)

Isme koi account nahi banana, koi paisa nahi dena. **5 minute** ka kaam hai.

## Step 1 — WhatsApp channel on karein

1. Panel me **Settings** kholein
2. Left side se **Notifications** pe click karein
3. Neeche **Delivery Channels** section me jayein
4. **WhatsApp** wala toggle **ON** karein (neela ho jayega)
5. Upar **Save Changes** dabayein

> Agar ye toggle OFF raha to koi message nahi jayega, chahe baaqi sab sahi ho. Ye sabse zaroori step hai.

## Step 2 — Apna WhatsApp number daalein

1. Settings me **Business Profile** kholein
2. **Contact & Address** section me:
   - **Primary Phone** — dukan ka number
   - **WhatsApp Number** — jis number pe aapka WhatsApp chalta hai
3. **Save** dabayein

**Number ka sahi format:**

| Ghalat | Sahi |
|---|---|
| `0300-1234567` | `923001234567` |
| `+92 300 1234567` | `923001234567` |
| `300 1234567` | `923001234567` |

Yaani: **country code (92) lagayein, shuru ka 0 hatayein, koi dash ya space nahi.**

## Step 3 — Provider "Manual" rakhein

1. Settings me **WhatsApp & Alerts** kholein
2. **Provider** dropdown me **"Manual — open WhatsApp Web"** select karein
3. **Save** dabayein

Bas! Setup complete. Ab **Step 4** pe jayein aur messages ko apni marzi ka banayein.

## Ye kaam kaise karta hai

Jab bhi aap kisi customer ko notify karenge:

1. Aap panel me button dabayenge (jaise "Notify Customer")
2. **WhatsApp Web** ka naya tab khul jayega
3. Message pehle se **poora likha hua** hoga — customer ka naam, order number, amount, sab
4. Aap sirf **Send** ka button dabayenge

Aapko kuch type nahi karna parta — sirf send dabana hai.

> **Zaroori:** Pehli baar aapko computer pe WhatsApp Web login karna hoga. [web.whatsapp.com](https://web.whatsapp.com) kholein → phone se **WhatsApp → Settings → Linked Devices → Link a Device** → QR scan karein. Ye ek hi baar karna hai.

---

# Tareeqa 2 — UltraMsg (Automatic, Paid)

Isme panel khud message bhej deta hai. Aapko kuch nahi karna parta.

## Pehle ye zaroor parh lein

UltraMsg **WhatsApp ka official partner nahi** hai. Ye WhatsApp Web ke tareeqe se aapka account connect karta hai.

**Iska matlab:**

- Aapka **normal WhatsApp Messenger** chalega — koi special account nahi chahiye
- Lekin WhatsApp ke rules ke mutabiq ye **allowed nahi** hai
- Agar aap bohat zyada messages bhejenge, ya log aapko **Report/Block** karenge, to WhatsApp aapka **number ban kar sakta hai**

**Risk kam karne ke liye:**

- Sirf **apne customers** ko bhejein, jinhon ne aapse kaam karwaya hai
- Marketing ya promotional messages **na bhejein** — sirf order updates
- Shuru me thore messages bhejein, dheere dheere barhayein
- **Dukan ka main number use na karein** — alag SIM lein. Agar ban ho gaya to aapka asli number safe rahega

> Agar aap risk nahi lena chahte, to **Manual Mode hi behtareen hai**. Wo 100% safe hai.

## Step 1 — UltraMsg account banayein

1. [ultramsg.com](https://ultramsg.com) kholein
2. **Sign Up** karein (email aur password)
3. Email verify karein

## Step 2 — Instance banayein aur QR scan karein

1. UltraMsg dashboard me **Create Instance** pe click karein
2. Screen pe ek **QR code** aayega
3. Ab apna phone lein:
   - WhatsApp kholein
   - **Settings → Linked Devices** me jayein
   - **Link a Device** dabayein
   - Screen wala QR code **scan** karein
4. Thora intezaar karein. UltraMsg pe likha aa jayega: **`Auth Status: authenticated`**

Agar `authenticated` likha aa gaya — **connect ho gaya!**

## Step 3 — Instance ID aur Token copy karein

UltraMsg dashboard pe aapko do cheezein milengi:

| Cheez | Kaisi dikhti hai |
|---|---|
| **Instance ID** | `instance12345` |
| **Token** | `abcd1234efgh5678ijkl` (lambi si line) |

Dono ko **copy** kar lein.

> **Token kisi ko na dein.** Ye aapke WhatsApp ka password jaisa hai.

## Step 4 — Panel me daalein

1. Panel me **Settings → WhatsApp & Alerts** kholein
2. **Provider** dropdown me **"UltraMsg API — send automatically"** select karein
3. Do naye khane khul jayenge:
   - **UltraMsg Instance ID** — yahan Instance ID paste karein
   - **API Token** — yahan Token paste karein
4. **Save** dabayein

## Step 5 — Test karein

1. Usi page pe **"Test connection"** button pe click karein
2. Agar sab sahi hai to green message aayega: **"Connected to UltraMsg"**
3. Agar error aaye to neeche **Masail aur Hal** dekhein

Setup complete! Ab har message khud ba khud chala jayega.

---

# Step 4 (dono tareeqon ke liye) — Messages ko apni marzi ka banayein

Panel me **6 ready-made messages** hain, Roman Urdu me. Aap inhe apni dukan ke hisaab se badal sakte hain.

**Settings → WhatsApp & Alerts** kholein, neeche scroll karein.

## Kaun sa message kab jata hai

| Message | Kab jata hai |
|---|---|
| **ORDER CREATED** | Naya order banate hi |
| **ORDER READY** | Jab aap "Notify Customer" dabate hain |
| **PAYMENT RECEIVED** | Jab customer kuch paisay deta hai |
| **DUE DATE REMINDER** | Delivery ke qareeb, khud ba khud |
| **DUE DATE EXTENDED** | Jab aap delivery date aage barhate hain |
| **FINAL RECEIPT** | Jab poora hisaab clear ho jaye |

Har message ke saath ek **toggle** hai. Jo message aap nahi bhejna chahte, uska toggle **OFF** kar dein.

## Message me customer ka naam khud kaise aata hai

Message me `{customerName}` jaisi cheezein likhi hoti hain. Ye **automatically** asli information se badal jati hain.

Misaal:

**Aap ne likha:**
```
Assalam o Alaikum {customerName}!
Aap ka {garmentType} tayyar hai.
Baqi raqam: {remainingBalance}
```

**Customer ko ye pohanchta hai:**
```
Assalam o Alaikum Ahmad Ali!
Aap ka Suit tayyar hai.
Baqi raqam: Rs.5,000
```

## Sab se zyada kaam aane wale variables

| Variable | Kya banta hai |
|---|---|
| `{customerName}` | Customer ka naam |
| `{orderID}` | Order number |
| `{garmentType}` | Kapra / suit / shirt |
| `{dueDate}` | Delivery ki tareekh |
| `{totalAmount}` | Kul raqam |
| `{advancePaid}` | Advance jo mila |
| `{remainingBalance}` | Baqi raqam |
| `{shopName}` | Aapki dukan ka naam |
| `{shopPhone}` | Aapka number |

Panel me **upar variables ki poori list** hai. Kisi bhi variable pe **click** karein — wo seedha message me lag jayega jahan cursor hai.

## Message ka preview

Har message ke **right side** me green WhatsApp bubble dikhta hai. Ye batata hai ke customer ko **bilkul kaisa** message milega — aapke asli purane order ki information ke saath.

Jaise jaise aap type karenge, preview bhi badalta rahega.

## Test message bhejein

Har message ke upar **"Test send"** ka button hai.

1. **Test send** dabayein
2. Apna number daalein (ya khali chhor dein — dukan ka number use hoga)
3. Message aapko aa jayega

Isse aap pehle khud dekh sakte hain ke message kaisa lagta hai, phir customer ko bhejein.

---

# Masail aur Hal (Troubleshooting)

### Koi message nahi ja raha

Ye teen cheezein check karein:

1. **Settings → Notifications → Delivery Channels → WhatsApp** — toggle **ON** hai?
2. **Settings → WhatsApp & Alerts** — jo message aap bhejna chahte hain, uska toggle **ON** hai?
3. Customer ka **phone number** uske record me sahi mojood hai?

### "This customer has no usable phone number"

Customer ke record me number nahi hai, ya ghalat format me hai.
**Customers** page kholein → customer edit karein → sahi number daalein (`923001234567` wale format me).

### WhatsApp Web khulta hai lekin message khali hai

Aapka WhatsApp Web **logged out** ho gaya hai.
[web.whatsapp.com](https://web.whatsapp.com) kholein aur dobara QR scan karein.

### UltraMsg: "Test connection" fail ho raha hai

| Error | Wajah aur hal |
|---|---|
| `credentials are missing` | Instance ID ya Token khali hai. Dobara paste karein aur **Save** dabayein |
| `HTTP 401` ya `HTTP 403` | Token ghalat hai. UltraMsg dashboard se **naya token copy** karein |
| `Scan the QR code` | Phone ka connection toot gaya. UltraMsg pe jayein aur **dobara QR scan** karein |
| `Could not reach UltraMsg` | Internet ka masla, ya UltraMsg down hai. Thori der baad koshish karein |

### UltraMsg pe "authenticated" nahi aa raha

- Phone ka **internet on** hai?
- Phone me WhatsApp **khula** hai?
- **Linked Devices** me UltraMsg dikh raha hai? Agar nahi, to dobara scan karein
- Phone zyada der **band** to nahi raha? WhatsApp Web ka connection toot jata hai

### Token save nahi ho raha / dots (••••) dikh rahe hain

Ye **bilkul theek hai**. Security ke liye token kabhi wapas nahi dikhaya jata.
Dots ka matlab hai **token save ho chuka hai**. Agar badalna ho to dots hata kar naya token paste karein.

### Message me `{customerName}` waisa hi ja raha hai

Variable ka naam ghalat likha hai. Dhyan rakhein:
- Bilkul **wahi spelling** honi chahiye
- **Chhote bare letters** ka farq parta hai — `{customerName}` sahi hai, `{customername}` ghalat
- Behtar ye hai ke **variable button pe click** karein, khud na likhein

---

# Roz ka istemal

Setup ke baad aapko sirf ye karna hai:

**Manual Mode me:**
1. Order ready ho jaye → panel me **Notify Customer** dabayein
2. WhatsApp Web khulega, message likha hoga
3. **Send** dabayein

**UltraMsg Mode me:**
1. Order ready ho jaye → panel me **Notify Customer** dabayein
2. Bas. Message chala gaya.

Order banate waqt, payment lete waqt, aur date badalte waqt bhi message **khud** chala jata hai (agar wo template ON hai).

---

# Yaad rakhne wali baatein

1. **WhatsApp toggle** (Notifications me) sab se zaroori hai — ye OFF ho to kuch nahi chalega
2. **Number ka format** — `923001234567`, bina 0, bina dash
3. **Manual Mode free aur safe hai** — shuru isi se karein
4. **UltraMsg pe ban ka risk hai** — alag SIM use karein, main number nahi
5. **Test send** se pehle khud dekh lein, phir customer ko bhejein
6. **Token kisi ko na dein**

---

*Koi masla ho to Settings → WhatsApp & Alerts kholein aur "Test connection" ya "Test send" se check karein — wahan se pata chal jata hai ke masla kahan hai.*

**Sources:**
- [UltraMsg — WhatsApp API gateway](https://ultramsg.com/)
- [UltraMsg Documentation](https://docs.ultramsg.com/)
- [UltraMsg — Instance connection troubleshoot](https://blog.ultramsg.com/instance-connection-troubleshoot/)
- [UltraMsg FAQ](https://blog.ultramsg.com/whatsapp-api-by-ultramsg-faq/)
