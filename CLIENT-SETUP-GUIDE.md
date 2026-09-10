# Atelier — Complete Setup Guide (Zero se Full Working tak)

**Yeh guide un logon ke liye hai jinke computer par kuch bhi install nahi hai.**
Har step number wise hai. Upar se neeche, ek ek karke follow karein. Koi step skip
na karein.

Project ka naam: **Atelier** — Tailor Shop + Cloth Store management system
(Laravel 12 + MySQL + Tailwind + Text SMS).

---

## Fehrist (Table of Contents)

| # | Section | Kitna time |
|---|---|---|
| 0 | Zaroori cheezein aur system requirements | 2 min padhne mein |
| 1 | XAMPP install karna (PHP + MySQL + Apache) | 10 min |
| 2 | Composer install karna | 5 min |
| 3 | Node.js install karna | 5 min |
| 4 | Project files ko sahi jagah rakhna | 2 min |
| 5 | Database banana (phpMyAdmin) | 5 min |
| 6 | `.env` file configure karna | 5 min |
| 7 | Project install commands chalana | 10 min |
| 8 | Project ko chalana (run karna) | 2 min |
| 9 | Pehli dafa login karna | 3 min |
| 10 | Shuruaati settings (store ka naam, logo, currency) | 10 min |
| 11 | Official notification setup (optional) | 15 min |
| 12 | Sab kuch auto-start karna (computer on hote hi) | 10 min |
| 13 | Roz ka istemal — kaise chalayein | 3 min |
| 14 | Backup aur restore | 5 min |
| 15 | Masail aur unka hal (Troubleshooting) | zaroorat par |
| 16 | Final checklist | 3 min |

---

# Section 0 — Zaroori cheezein aur system requirements

## 0.1 Computer ki requirement

| Cheez | Kam se kam | Behtar |
|---|---|---|
| Operating System | Windows 10 (64-bit) | Windows 11 |
| RAM | 4 GB | 8 GB |
| Disk space | 5 GB khali | 10 GB khali |
| Internet | Sirf install ke waqt zaroori | SMS ke liye hamesha |

> **Note:** Project chalane ke liye internet zaroori NAHI hai. Sab kuch computer ke
> andar chalta hai. Internet sirf 2 kaamon ke liye chahiye — (a) shuru mein software
> download karne ke liye, (b) SMS messages bhejne ke liye.

## 0.2 Kya kya install karna hoga (kul 3 cheezein)

1. **XAMPP** — isme PHP, MySQL database aur Apache server teeno aa jaate hain
2. **Composer** — PHP ki libraries download karta hai
3. **Node.js** — design (CSS/JS) build karta hai

Bas. Iske alawa kuch nahi chahiye.

## 0.3 Ek ahem baat — folder ka path

Project **hamesha** is jagah rakhna hai:

```
D:\Xamp\htdocs\test-fnal_telor\test-fnal_telor
```

Agar aapke computer par XAMPP `C:` drive par install hua hai to path ye ho jayega:

```
C:\xampp\htdocs\test-fnal_telor\test-fnal_telor
```

Is guide mein jahan bhi `D:\Xamp\htdocs\...` likha ho, wahan apna asli path
istemal karein.

---

# Section 1 — XAMPP install karna

XAMPP ke andar PHP, MySQL (database) aur Apache — teeno aate hain. Ye sabse pehle
install hoga.

## 1.1 Download

1. Browser kholein aur jaayein: **https://www.apachefriends.org/download.html**
2. **XAMPP for Windows** wale section mein **PHP 8.2** ya us se upar wala version
   chunein (8.2, 8.3 ya 8.4 — teeno chalein ge)

   > ⚠️ **PHP 8.1 ya us se purana bilkul kaam nahi karega.** Project ko kam se kam
   > PHP 8.2 chahiye.

3. `Download` par click karein. File taqreeban 150 MB ki hai.

## 1.2 Install

1. Download hui file par **right-click → Run as administrator**
2. Agar "User Account Control" ya antivirus warning aaye to **Yes / Allow** karein
3. Components wale screen par ye zaroor tick hone chahiye:
   - ✅ Apache
   - ✅ MySQL
   - ✅ PHP
   - ✅ phpMyAdmin
   
   Baaki (FileZilla, Mercury, Tomcat, Perl) ka tick hata dein — zaroorat nahi.
4. Install folder mein `D:\Xamp` likh dein (ya `C:\xampp` rehne dein)
5. **Next → Next → Install** dabate jaayein. 5-10 minute lagenge.
6. Aakhir mein "Do you want to start the Control Panel now?" — **tick rehne dein → Finish**

## 1.3 Apache aur MySQL start karna

XAMPP Control Panel khul jayega. Usme:

1. **Apache** ke saamne **Start** dabayein → naam green ho jana chahiye
2. **MySQL** ke saamne **Start** dabayein → naam green ho jana chahiye

![Dono green hone chahiye]

**Agar green na ho aur Apache band ho jaye:** Section 15.1 padhein (port ka masla).

## 1.4 Check karein ke chal raha hai

Browser mein kholein: **http://localhost**

Agar XAMPP ka welcome page khul gaya — ✅ **kaam ho gaya.**

## 1.5 PHP ka version check karein

1. Keyboard par **Windows + R** dabayein
2. `cmd` likh kar Enter dabayein (black window khulegi — ise **Command Prompt** kehte hain)
3. Ye likh kar Enter dabayein:

```cmd
D:\Xamp\php\php.exe -v
```

Aisa kuch dikhna chahiye: `PHP 8.2.12 (cli)` — agar 8.2 ya us se bada number hai to theek hai.

## 1.6 PHP ko "PATH" mein daalna (bohot zaroori)

Isse aap kahin se bhi sirf `php` likh kar command chala sakenge.

1. Start menu mein likhein: **Edit the system environment variables** → khol lein
2. Neeche **Environment Variables...** button dabayein
3. Neeche wale box (**System variables**) mein **Path** dhoondein → select karein → **Edit**
4. **New** dabayein → ye likhein:

```
D:\Xamp\php
```

5. **OK → OK → OK** — teeno windows band kar dein
6. **Command Prompt band karke dobara kholein** (ye zaroori hai, warna change asar nahi karega)
7. Ab test karein:

```cmd
php -v
```

Version dikh gaya? ✅ Aage barhein.

## 1.7 Zaroori PHP extensions on karein

1. XAMPP Control Panel mein Apache ke saamne **Config → PHP (php.ini)** par click karein
2. Notepad khulega. **Ctrl + F** dabayein aur `extension=` dhoondein
3. In lines ke shuru se semicolon `;` hata dein (agar laga hua ho):

```ini
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
extension=gd
extension=intl
```

4. **Ctrl + S** se save karein, Notepad band karein
5. XAMPP mein Apache ko **Stop** phir **Start** karein

---

# Section 2 — Composer install karna

Composer PHP ke liye libraries download karta hai. Iske bagair project nahi chalega.

## 2.1 Download aur install

1. Kholein: **https://getcomposer.org/download/**
2. **Composer-Setup.exe** download karein
3. Double-click karke chalayein
4. **Install for all users (recommended)** chunein
5. Jab PHP ka path pooche to ye de dein:

```
D:\Xamp\php\php.exe
```

6. Proxy wala step **khali** chhod dein → Next
7. **Install → Finish**

## 2.2 Check karein

**Command Prompt band karke naya kholein**, phir:

```cmd
composer -V
```

`Composer version 2.x.x` dikhna chahiye. ✅

---

# Section 3 — Node.js install karna

Node.js frontend design (CSS/JS) build karta hai. Notifications Laravel backend se send hoti hain.

## 3.1 Download aur install

1. Kholein: **https://nodejs.org**
2. Bade green button par **LTS** likha hoga — wohi download karein
   (LTS = sabse stable version. "Current" wala mat lein.)
3. Double-click → **Next → I accept → Next → Next → Install**
4. Agar "Tools for Native Modules" ka checkbox aaye to **tick na karein** — zaroorat nahi
5. **Finish**

## 3.2 Check karein

**Naya Command Prompt** kholein:

```cmd
node -v
npm -v
```

Kuch aisa dikhna chahiye:
```
v20.11.0
10.2.4
```

Node ka version **18 se bada** hona chahiye. ✅

---

# Section 4 — Project files ko sahi jagah rakhna

## 4.1 Folder banayein

1. **File Explorer** kholein
2. Jaayein: `D:\Xamp\htdocs\`
3. Wahan project folder paste karein taake final path ye bane:

```
D:\Xamp\htdocs\test-fnal_telor\test-fnal_telor
```

Is folder ke andar `artisan`, `composer.json`, `app`, `public` — sab dikhna chahiye.

## 4.2 Command Prompt ko is folder mein le jaana (baar baar zaroorat paregi)

**Sabse aasan tareeqa:**

1. Project folder kholein (`D:\Xamp\htdocs\test-fnal_telor\test-fnal_telor`)
2. Upar address bar mein click karein (jahan path likha hota hai)
3. Poora path mit jayega — wahan `cmd` likh kar **Enter** dabayein
4. Command Prompt seedha isi folder mein khul jayega ✅

**Ya phir manually:**

```cmd
D:
cd D:\Xamp\htdocs\test-fnal_telor\test-fnal_telor
```

> 📌 **Aage jitni bhi commands hain, sab isi folder wale Command Prompt mein chalengi.**
> Jahan alag jagah chahiye hogi, wahan alag se likha jayega.

---

# Section 5 — Database banana

## 5.1 phpMyAdmin kholein

1. XAMPP mein **Apache** aur **MySQL** dono green hone chahiye
2. Browser mein kholein: **http://localhost/phpmyadmin**

## 5.2 Naya database banayein

1. Bayein taraf (left side) **New** par click karein
2. Database ka naam likhein — bilkul yehi, chhote letters mein:

```
test_fnal_telor
```

3. Saath wale dropdown mein chunein: **utf8mb4_unicode_ci**
4. **Create** dabayein

Bayein list mein `test_fnal_telor` dikhne lagega. ✅ (Abhi khali hoga — tables Section 7 mein banenge.)

## 5.3 MySQL ka port note karein (ahem!)

XAMPP ka MySQL aam taur par port **3306** par chalta hai, lekin kabhi kabhi
**3307** par set hota hai. Ye jaan-na zaroori hai:

1. XAMPP Control Panel dekhein
2. MySQL ki line mein **Port(s)** column mein number likha hoga — `3306` ya `3307`
3. **Ye number likh lein** — agle section mein chahiye hoga

---

# Section 6 — `.env` file configure karna

`.env` file project ki settings rakhti hai — database ka password, naam, wagera.

## 6.1 File dhoondein

Project folder mein `.env` naam ki file honi chahiye.

**Agar `.env` nahi dikh rahi:**
- File Explorer mein upar **View** tab → **Show** → **Hidden items** tick karein
- Ya `.env.example` ko copy karke naam `.env` rakh dein

**Agar `.env` bilkul mojood hi nahi:**

```cmd
copy .env.example .env
```

## 6.2 Kholein aur badlein

`.env` par right-click → **Open with → Notepad**

In lines ko dhoondein aur bilkul aise kar dein:

```env
APP_NAME="Atelier"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=test_fnal_telor
DB_USERNAME=root
DB_PASSWORD=
```

**Dhyan dein:**

| Line | Kya likhna hai |
|---|---|
| `DB_PORT` | Wohi number jo Section 5.3 mein XAMPP mein dekha tha (3306 ya 3307) |
| `DB_DATABASE` | `test_fnal_telor` — bilkul wohi jo phpMyAdmin mein banaya |
| `DB_USERNAME` | `root` (XAMPP ka default) |
| `DB_PASSWORD` | **Khali chhod dein** — kuch na likhein (XAMPP ka default password khali hai) |

**Ctrl + S** se save karein, Notepad band karein.

## 6.3 APP_KEY ke bare mein

`APP_KEY=` wali line agar khali hai to fikar na karein — Section 7 mein khud ban jayegi.

---

# Section 7 — Project install commands chalana

Ab asli kaam. Command Prompt project folder mein khula hona chahiye (Section 4.2 dekhein).

**Har command ek ek karke chalayein. Ek ke khatam hone ka intezaar karein, phir agli.**

## Step 7.1 — PHP libraries download karein

```cmd
composer install
```

⏱ 3-8 minute. Bohot saari lines chalengi — ye normal hai.
Aakhir mein "Generating optimized autoload files" jaisa kuch dikhega.

> ❌ Agar error aaye: Section 15.3 dekhein.

## Step 7.2 — Security key banayein

```cmd
php artisan key:generate
```

Ye dikhna chahiye: `INFO Application key set successfully.` ✅

## Step 7.3 — Database ki tables banayein

```cmd
php artisan migrate
```

Agar pooche **"Do you really wish to run this command?"** to `yes` likh kar Enter.

Bohot saari lines aayengi jaise `2026_08_07_233007_create_customers_table ... DONE`.
Sab ke aage **DONE** ya **✓** hona chahiye. ✅

> ❌ "Connection refused" ya "Access denied" aaye? Section 15.4 dekhein.

## Step 7.4 — Shuruaati data daalein (users, settings)

```cmd
php artisan db:seed
```

Isse ye cheezein ban jayengi:
- Login ke accounts (admin, staff, tailor)
- Store ki default settings
- Kuch demo customers aur orders (baad mein delete kar sakte hain)

## Step 7.5 — Cloth Store ka data daalein

Cloth Store (kapre ki dukan) wala hissa alag seeders se aata hai. Ye teen commands chalayein:

```cmd
php artisan db:seed --class=ClothStoreRolesSeeder
php artisan db:seed --class=ClothStoreSeeder
php artisan db:seed --class=FabricProductsSeeder
```

Isse categories, products, suppliers, roles aur permissions ban jayenge.

## Step 7.6 — Design ke files download karein

```cmd
npm install
```

⏱ 2-5 minute. Warnings aayein to ignore karein — sirf **error** matter karta hai.

## Step 7.7 — Design build karein (bohot ahem!)

```cmd
npm run build
```

⏱ 30-60 second. Aakhir mein `✓ built in ...` dikhna chahiye.

> ⚠️ **Ye step skip mat karein.** Iske bagair page bilkul bina design ke — plain
> white, tooti hui — dikhega. Tailwind CSS, icons, fonts aur charts sab isi step
> mein bante hain.

## Step 7.8 — Storage folder link karein (photos ke liye)

```cmd
php artisan storage:link
```

Isse logo aur product photos browser mein dikhne lagenge.

## Step 7.9 — Cache saaf karein

```cmd
php artisan optimize:clear
```

---

✅ **Installation mukammal! Ab project chalane ke liye tayyar hai.**

---

# Section 8 — Project ko chalana (run karna)

## 8.1 Sabse aasan tareeqa (rozana ke liye yehi istemal karein)

1. **XAMPP Control Panel** kholein → **MySQL** ko **Start** karein
   
   > Apache start karna zaroori nahi agar aap neeche wala tareeqa istemal kar rahe hain.
   > Sirf MySQL chahiye.

2. Project folder mein Command Prompt kholein (Section 4.2)
3. Ye command chalayein:

```cmd
php artisan serve
```

4. Ye dikhega:

```
INFO  Server running on [http://127.0.0.1:8000]
Press Ctrl+C to stop the server
```

5. Browser mein kholein: **http://localhost:8000**

> 🔴 **Ye black window band mat karein!** Jab tak ye khuli hai, website chalti rahegi.
> Band karte hi website band ho jayegi.

## 8.2 Server band karna

Us black window mein **Ctrl + C** dabayein.

## 8.3 Ek-click shortcut banayein (bohot faidemand)

Rozana command likhne se bachne ke liye:

1. Project folder mein right-click → **New → Text Document**
2. Naam rakhein `START.txt` aur usme ye likhein:

```bat
@echo off
title Atelier - Server (Isse band na karein)
cd /d "%~dp0"
echo.
echo  ==========================================
echo   Atelier chal raha hai
echo   Browser mein kholein: http://localhost:8000
echo   Isse band karne se website band ho jayegi
echo  ==========================================
echo.
start http://localhost:8000
php artisan serve
pause
```

3. Save karein, phir file ka naam badal kar **`START.bat`** kar dein
   (`.txt` hata kar `.bat` likhna hai — Windows warning de to **Yes** karein)

Ab rozana sirf **`START.bat` par double-click** karein. Browser khud khul jayega. ✅

---

# Section 9 — Pehli dafa login karna

## 9.1 Login page

Browser mein: **http://localhost:8000**

Login page khulega.

## 9.2 Default accounts

| Email | Password | Role | Kya kar sakta hai |
|---|---|---|---|
| `admin@ateliercraft.com` | `password` | Admin | Sab kuch — settings, team, reports |
| `staff@ateliercraft.com` | `password` | Staff | Orders, customers, payments |
| `ahmed@ateliercraft.com` | `password` | Tailor | Sirf apne orders |

## 9.3 🔴 SABSE PEHLA KAAM — password badlein

Ye passwords sab ko maloom hain. Login ke foran baad:

1. Upar dayein taraf apne naam par click karein → **Profile**
2. **Change Password** section
3. Purana password: `password`
4. Naya password likhein (kam se kam 8 characters, kuch mushkil sa)
5. **Save**

Ye kaam **teeno accounts** ke liye karein — ya Settings → Team mein jaakar jo
accounts istemal nahi karne unhe **band (deactivate)** kar dein.

## 9.4 Do alag alag section

Login ke baad aapko do system milte hain:

| Section | Address | Kis ke liye |
|---|---|---|
| **Tailor Shop** | `http://localhost:8000/` | Silai ke orders, measurements, delivery |
| **Cloth Store** | `http://localhost:8000/cloth-store` | Kapre ki dukan, stock, POS billing |

Dono ka login ek hi hai. Sidebar se switch kar sakte hain.

---

# Section 10 — Shuruaati settings

Login karne ke baad **Settings** mein jaakar ye cheezein set karein. Isse pehle
kaam shuru na karein — receipt aur invoice inhi settings se banti hain.

## 10.1 Store ki maloomat

**Settings → General**

| Field | Kya likhein |
|---|---|
| Store Name | Aapki dukan ka asli naam |
| Address | Poora pata |
| Phone | Jo number receipt par chhapna hai |
| Email | Dukan ka email |
| Website | Agar hai to |
| Logo | Dukan ka logo upload karein (PNG, 500KB tak) |

## 10.2 Currency aur tax

| Field | Misaal |
|---|---|
| Currency | `Rs` ya `₨` ya `PKR` (default `₹` hai — badal lein) |
| Tax Rate | Aapka GST/tax percent, ya `0` agar tax nahi lagta |

## 10.3 Receipt ka footer

Receipt ke neeche jo message chhapega. Misaal:

```
Shukriya! Order lene ke liye receipt zaroor saath layein.
Delivery ke baad shikayat qabool nahi.
```

## 10.4 Products aur services ke rates

**Products & Services** page par jaayein:

1. Demo wale items delete kar dein
2. **Add New** se apne asli items daalein:
   - Shalwar Kameez silai — Rs. 1500
   - Coat pant — Rs. 4000
   - Waskat — Rs. 1200
   
   (jo bhi aapke rates hain)

## 10.5 Cloth Store ka stock

**Cloth Store → Categories** phir **Products**:

1. Demo categories aur products delete karein
2. Apni asli categories banayein (Lawn, Khaddar, Silk, wagera)
3. Har product mein daalein: naam, **cost price** (jo aapne khareeda), **selling
   price** (jo bechte hain), **stock quantity**, **unit** (suit / meter / pcs)
4. **Low stock threshold** set karein — jab stock is se kam ho jayega, alert aayega

## 10.6 Team members

**Settings → Team**

Apne staff ke accounts banayein. Har ek ko alag account dein — ek hi account sab
ko dene se ye pata nahi chalega ke kis ne kya kiya.

Roles: **admin** (sab kuch), **staff** (orders/customers), **tailor** (sirf apne orders).

---

# Section 11 — Official customer notifications

Follow [SMS setup](NOTIFICATION-SETUP.md) for Veevo/SPEXT and SendPK configuration. Save the selected provider credentials, then enable SMS.

# Section 12 — Sab kuch auto-start karna

Taake computer on karte hi sab khud chalu ho jaye aur roz manually kuch na karna pare.

## 12.1 MySQL ko Windows service banayein

1. XAMPP Control Panel ko **right-click → Run as administrator** se kholein
2. **MySQL** ke bilkul bayein taraf ek chhota **checkbox (X)** hai — us par click karein
3. Confirmation aaye to **Yes**
4. Ab MySQL Windows ke saath khud start hoga ✅

Yehi kaam **Apache** ke liye bhi kar sakte hain (agar Apache istemal kar rahe hain).

## 12.2 Laravel server ko auto-start karein

1. Keyboard par **Windows + R** dabayein
2. Likhein: `shell:startup` → Enter
3. Ek folder khulega
4. Wahan **`START.bat`** ka **shortcut** rakh dein
   (START.bat par right-click → Copy → is folder mein right-click → **Paste shortcut**)

Ab computer on hote hi website khud chalu ho jayegi.

## 12.3 Scheduled reminders

Run `php artisan schedule:run` every minute through Windows Task Scheduler or your hosting scheduler. Keep the app server and database available.

---

# Section 13 — Roz ka istemal

## 13.1 Subah dukan kholte waqt

**Agar Section 12 kar liya hai:** kuch nahi karna. Computer on karein, browser mein
`http://localhost:8000` kholein. Bas.

**Agar nahi kiya:**

1. XAMPP kholein → **MySQL** Start
2. **`START.bat`** par double-click
3. Browser khud khul jayega

## 13.2 Raat ko band karte waqt

1. `START.bat` wali black window mein **Ctrl + C**
2. XAMPP mein **MySQL** Stop
3. Computer shut down

> 💡 **Behtar:** Computer band karne se pehle Section 14 wala backup le lein — kam
> se kam hafte mein ek baar.

## 13.3 Main pages

| Page | Address | Kaam |
|---|---|---|
| Dashboard | `/` | Aaj ka kharcha, aamdani, pending orders |
| Customers | `/customers` | Customer add / edit / history |
| Orders | `/orders` | Naya order, status change, receipt |
| Measurements | `/measurements` | Naap mehfooz karna |
| Payments | `/payments-billing` | Payment lena, invoice |
| Expenses | `/expenses` | Dukan ke kharche |
| Reports | `/reports` | Sale, profit, customer reports |
| Delivery | `/delivery` | Delivery track karna |
| Settings | `/settings` | Sab settings (sirf admin) |
| **Cloth Store** | `/cloth-store` | Dukan ka dashboard |
| POS / Billing | `/cloth-store/checkout` | Counter par bill banana |
| Stock | `/cloth-store/stock` | Stock in/out, alerts |
| Products | `/cloth-store/products` | Products manage karna |

## 13.4 Mobile / tablet par chalana (same WiFi par)

Counter par tablet ya mobile se bhi chala sakte hain:

1. **Server wale computer** par Command Prompt kholein:

```cmd
ipconfig
```

`IPv4 Address` dhoondein — jaise `192.168.1.5`

2. Server ko is tarah chalayein (taake network par available ho):

```cmd
php artisan serve --host=0.0.0.0 --port=8000
```

3. Mobile ke browser mein kholein: `http://192.168.1.5:8000`
   (apna asli IP daalein)

4. Dono cheezein **ek hi WiFi** par honi chahiye

> ⚠️ Agar mobile par na khule to Windows Firewall mein port 8000 allow karna hoga:
> Windows Defender Firewall → Advanced settings → Inbound Rules → New Rule → Port
> → TCP → 8000 → Allow.

## 13.5 App ki tarah install karna (PWA)

Ye system phone/desktop par app ki tarah install ho sakta hai:

- **Chrome (desktop):** address bar mein dayein taraf **install** ka icon → click
- **Android:** menu (⋮) → **Add to Home screen**
- **iPhone:** Share → **Add to Home Screen**

Phir alag icon se khulega, browser bar ke bagair — asli app jaisa.

---

# Section 14 — Backup aur restore

> 🔴 **Ye section sabse ahem hai.** Computer kharab ho sakta hai. Backup na hone
> par saara data khatam. Hafte mein kam se kam ek baar backup lein.

## 14.1 Panel se backup (sabse aasan)

1. **Settings → Backup** par jaayein
2. **Download Backup** dabayein
3. File download hogi
4. Use **USB / external drive / Google Drive** par copy karein — sirf isi computer
   par mat rakhein

## 14.2 phpMyAdmin se backup (poora database)

1. Kholein: `http://localhost/phpmyadmin`
2. Bayein taraf **`test_fnal_telor`** par click karein
3. Upar **Export** tab
4. Method: **Quick**, Format: **SQL**
5. **Export / Go** dabayein
6. `.sql` file download hogi — mehfooz jagah rakhein

File ka naam tareekh ke saath rakhein, jaise `backup-2026-08-19.sql`.

## 14.3 Files ka backup

Ye do folder bhi copy karein:

```
D:\Xamp\htdocs\test-fnal_telor\test-fnal_telor\storage\app\public   ← logo, photos
D:\Xamp\htdocs\test-fnal_telor\test-fnal_telor\.env                 ← settings
```

## 14.4 Restore kaise karein (agar data khatam ho jaye)

**Panel se:**

Settings → Backup → **Restore** → apni backup file chunein → confirm

**phpMyAdmin se:**

1. `http://localhost/phpmyadmin` kholein
2. `test_fnal_telor` database par click karein
3. **Import** tab
4. **Choose File** → apni `.sql` file chunein
5. **Import / Go**

## 14.5 Auto backup (hafte mein ek baar yaad dilana)

Windows mein reminder laga lein, ya ek chhoti si `.bat` file bana lein:

```bat
@echo off
set D=%date:~-4%-%date:~4,2%-%date:~7,2%
D:\Xamp\mysql\bin\mysqldump.exe -u root test_fnal_telor > "D:\Backups\atelier-%D%.sql"
echo Backup ban gaya: D:\Backups\atelier-%D%.sql
pause
```

(Pehle `D:\Backups` folder bana lein.)

---

# Section 15 — Masail aur unka hal (Troubleshooting)

## 15.1 XAMPP mein Apache start nahi ho raha

**Wajah:** Port 80 par koi aur program chal raha hai (aam taur par Skype ya IIS).

**Hal:**

1. XAMPP mein Apache ke saamne **Config → Apache (httpd.conf)**
2. `Listen 80` dhoondein → `Listen 8080` kar dein
3. `ServerName localhost:80` dhoondein → `ServerName localhost:8080`
4. Save → Apache Start

Ab phpMyAdmin `http://localhost:8080/phpmyadmin` par khulega.

> 💡 Waise `php artisan serve` istemal kar rahe hain to Apache ki zaroorat hi nahi —
> sirf MySQL chahiye.

## 15.2 MySQL start nahi ho raha

**Wajah 1:** Port 3306 busy hai (koi doosra MySQL install hai).

**Hal:** XAMPP mein MySQL → **Config → my.ini** → `port=3306` ko `port=3307` kar dein →
save → Start. Phir `.env` mein bhi `DB_PORT=3307` kar dein.

**Wajah 2:** Data corrupt ho gaya.

**Hal:** XAMPP Control Panel mein MySQL → **Logs** dekhein. Aksar `D:\Xamp\mysql\data`
mein `ib_logfile0` aur `ib_logfile1` ko rename karne se theek ho jata hai (pehle
poora `data` folder ka backup lein!).

## 15.3 `composer install` fail ho raha hai

| Error | Hal |
|---|---|
| `could not find driver` | php.ini mein `extension=pdo_mysql` se `;` hatayein (Section 1.7) |
| `ext-zip missing` | php.ini mein `extension=zip` se `;` hatayein |
| `ext-mbstring missing` | php.ini mein `extension=mbstring` se `;` hatayein |
| `Your requirements could not be resolved / php ^8.2` | PHP version purana hai — naya XAMPP install karein |
| `SSL certificate problem` | php.ini mein `curl.cainfo` set karein, ya XAMPP naya version lein |
| Bohot dair lagti hai / rukh jata hai | Internet check karein, phir `composer install` dobara chalayein |

## 15.4 `php artisan migrate` par error

| Error | Wajah aur hal |
|---|---|
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL band hai → XAMPP mein Start karein |
| `Connection refused` phir bhi | `.env` ka `DB_PORT` XAMPP wale port se match nahi kar raha |
| `Access denied for user 'root'` | `.env` mein `DB_PASSWORD=` khali chhodein |
| `Unknown database 'test_fnal_telor'` | Database banaya hi nahi → Section 5 karein |
| `Base table already exists` | Tables pehle se hain → `php artisan migrate:fresh --seed` (⚠️ saara data khatam ho jayega) |

Har `.env` change ke baad ye zaroor chalayein:

```cmd
php artisan config:clear
```

## 15.5 Page khul raha hai lekin design tuta hua hai

**Sab kuch plain white, koi color nahi, icons ghayab:**

```cmd
npm run build
php artisan optimize:clear
```

Phir browser mein **Ctrl + Shift + R** (hard refresh).

**Sirf icons ghayab hain, design theek hai:** yehi command chalayein — Font Awesome
build se aata hai.

## 15.6 `500 | Server Error`

1. `.env` mein `APP_DEBUG=true` karein
2. Page refresh karein — ab asli error dikhega
3. Ya log file dekhein:

```
storage\logs\laravel.log
```

Sabse neeche wali lines dekhein.

4. Aam hal:

```cmd
php artisan optimize:clear
php artisan storage:link
```

## 15.7 `The stream or file could not be opened` / permission error

Storage folder ki permission ka masla hai.

1. `storage` folder par right-click → **Properties → Security**
2. **Users** ko **Full control** dein
3. Yehi `bootstrap\cache` folder ke liye bhi karein

## 15.8 `php` command kaam nahi kar rahi

`'php' is not recognized as an internal or external command`

**Hal:** Section 1.6 dobara karein (PATH mein PHP daalna), phir **Command Prompt
band karke naya kholein**.

## 15.9 `npm` command kaam nahi kar rahi

Node.js install nahi hua ya PATH mein nahi. Section 3 dobara karein aur computer
restart karein.

## 15.10 Port 8000 already in use

```cmd
php artisan serve --port=8080
```

Phir `http://localhost:8080` kholein.

## 15.11 Login nahi ho raha

| Masla | Hal |
|---|---|
| "These credentials do not match" | Email/password check karein. Password `password` hai (chhote letters) |
| Password bhool gaye | Command chalayein: `php artisan tinker` phir `App\Models\User::where('email','admin@ateliercraft.com')->first()->update(['password'=>bcrypt('naya-password')]);` phir `exit` |
| Login karte hi wapis login page | Session ka masla → `php artisan optimize:clear` aur browser ke cookies clear karein |
| "Your account is inactive" | Account band hai → doosre admin se Settings → Team mein activate karwayein |

## 15.12 SMS message nahi ja raha

Check Notifications → Delivery Channels → SMS, then SMS Settings. Verify the selected provider, saved credentials, approved sender and credit. See [SMS setup](NOTIFICATION-SETUP.md).

## 15.13 Sab kuch reset karke naya shuru karna

> ⚠️ **Saara data hamesha ke liye khatam ho jayega. Pehle backup lein!**

```cmd
php artisan migrate:fresh --seed
php artisan db:seed --class=ClothStoreRolesSeeder
php artisan db:seed --class=ClothStoreSeeder
php artisan db:seed --class=FabricProductsSeeder
php artisan optimize:clear
```

---

# Section 16 — Final checklist

Sab kuch install ho jane ke baad ye list check karein. Har cheez par ✅ lagna chahiye.

## Install

- [ ] XAMPP install hai, **PHP 8.2 ya us se upar**
- [ ] `php -v` command chal rahi hai
- [ ] `composer -V` command chal rahi hai
- [ ] `node -v` mein **v18 ya us se upar**
- [ ] Project `htdocs` ke andar sahi jagah par hai

## Database

- [ ] XAMPP mein MySQL green hai
- [ ] `test_fnal_telor` database bana hua hai
- [ ] `.env` mein sahi port, database ka naam, `root`, khali password
- [ ] `php artisan migrate` bina error chal gaya
- [ ] phpMyAdmin mein tables dikh rahi hain

## Project

- [ ] `composer install` ho chuka
- [ ] `php artisan key:generate` ho chuka (`.env` mein `APP_KEY=base64:...` bhara hua)
- [ ] `php artisan db:seed` ho chuka
- [ ] Teeno Cloth Store seeders chal chuke
- [ ] `npm install` ho chuka
- [ ] **`npm run build` ho chuka** (design theek dikh raha hai)
- [ ] `php artisan storage:link` ho chuka

## Chalna

- [ ] `php artisan serve` chalti hai
- [ ] `http://localhost:8000` khulta hai
- [ ] Login ho jata hai
- [ ] Dashboard mein numbers dikh rahe hain
- [ ] `/cloth-store` bhi khulta hai
- [ ] Design theek hai — colors, icons, charts sab dikh rahe hain

## Security

- [ ] 🔴 **Admin ka password badal diya**
- [ ] Staff aur tailor ke password bhi badal diye (ya accounts band kar diye)
- [ ] Team ke asli accounts bana diye

## Settings

- [ ] Store ka naam, pata, phone set kar diya
- [ ] Logo upload kar diya
- [ ] Currency sahi kar di (`₹` se `Rs` ya jo bhi)
- [ ] Tax rate set kar di
- [ ] Receipt ka footer likh diya
- [ ] Apne asli products aur rates daal diye
- [ ] Demo data delete kar diya

## Backup

- [ ] Ek baar backup lekar dekh liya ke download hota hai
- [ ] Backup ki jagah tay kar li (USB / Google Drive)
- [ ] Hafte mein ek baar backup ka reminder laga liya

## Optional

- [ ] Meta credentials verified
- [ ] Six approved event templates mapped
- [ ] Test message chala gaya
- [ ] Auto-start set kar diya (MySQL service + START.bat + scheduler)
- [ ] `START.bat` shortcut Desktop par bana diya

---

# Zaroori Commands — Ek nazar mein

Ye sab **project folder** wale Command Prompt mein chalti hain.

## Rozana

```cmd
php artisan serve
```

## Kuch bhi ajeeb ho jaye to

```cmd
php artisan optimize:clear
```

## Design badalne ke baad

```cmd
npm run build
```

## Naya code aane ke baad (developer se update mile to)

```cmd
composer install
php artisan migrate
npm install
npm run build
php artisan optimize:clear
```

## Sab kuch dobara install (emergency)

```cmd
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan db:seed --class=ClothStoreRolesSeeder
php artisan db:seed --class=ClothStoreSeeder
php artisan db:seed --class=FabricProductsSeeder
npm install
npm run build
php artisan storage:link
php artisan optimize:clear
```

---

# Ahem baatein — hamesha yaad rakhein

1. 🔴 **`npm run build` kabhi na bhoolein.** Iske bagair page bina design ke dikhega.
2. 🔴 **Admin ka password pehle din badlein.** Default password sab ko maloom hai.
3. 🔴 **Hafte mein ek baar backup.** Computer kharab hone ka koi waqt nahi hota.
4. 🔴 **Server wali black window band na karein.** Band = website band.
5. 🔴 **MySQL hamesha chalna chahiye.** Iske bagair "Connection refused" aayega.
6. **SMS:** Configure approved sender details and available credit with the selected provider.
7. ⚠️ **`.env` file kisi ko na dein.** Isme database ke passwords hain.
9. 💡 **`.env` badalne ke baad hamesha `php artisan config:clear`.**
10. 💡 **`storage\logs\laravel.log`** — har error ka asli sabab yahan milta hai.

---

# Support ke liye

Koi masla ho to ye maloomat saath bhejein:

1. Kaunsa step chala rahe the
2. Poora error message (screenshot ya text)
3. `storage\logs\laravel.log` ki aakhri 30 lines
4. `php -v`, `node -v`, `composer -V` ka output

---

**Guide khatam. Ab aap ka system chalne ke liye tayyar hai. 🎉**
