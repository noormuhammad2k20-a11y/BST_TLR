# Client ke fresh laptop par setup — XAMPP install hone ke baad

Ye guide is project ke 14 September 2026 wale code ke mutabiq hai. Steps upar se neeche follow karein. Ye **naye client ka khali business setup** hai; purana data transfer karna ho to section 12 pehle parhein. Purani `CLIENT-SETUP-GUIDE.md` ke demo seeders/default passwords ke bajaye ye guide use karein.

## 1. Folder aur commands ka tareeqa

Is guide mein XAMPP `C:\xampp` aur project `C:\xampp\htdocs\test-fnal_telor` maana gaya hai. Agar client ne doosri jagah install kiya hai to har path us ke mutabiq badlein. Aapke current development laptop ka project `D:\Xamp\htdocs\test-fnal_telor` hai.

Commands **Command Prompt (cmd)** mein ek ek karke chalani hain, siwaye jahan PowerShell likha hai. Error aaye to usay solve karke agli command chalayein.

## 2. XAMPP aur PHP check karein

1. XAMPP Control Panel kholein; Apache aur MySQL Start karein. Dono green hon.
2. Browser mein [phpMyAdmin](http://localhost/phpmyadmin) kholein.
3. Windows + R dabayein, `cmd` likhein, Enter karein:

```cmd
C:\xampp\php\php.exe -v
C:\xampp\php\php.exe --ini
C:\xampp\php\php.exe -m
```

PHP kam se kam 8.2 hona chahiye. `--ini` se loaded php.ini ka path milega. Us file mein available extension lines ke aage ka `;` hata kar required extensions enable karein:

```ini
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
extension=intl
extension=gd
extension=sodium
```

Project ko BCMath, DOM, XML aur PDO bhi chahiye; ye Windows PHP mein built-in ho sakte hain. `php -m` mein check karein. Har extension ke liye andazay se DLL line add na karein. Missing module ho to compatible PHP installation theek karein. Save karke Apache Stop/Start karein.

Start menu mein **Edit environment variables for your account → Path → Edit → New** se `C:\xampp\php` add karein. Naya CMD khol kar:

```cmd
where php
php -v
```

Pehla PHP path XAMPP wala hona chahiye.

## 3. Composer aur Node install karein

1. [Composer ki official download page](https://getcomposer.org/download/) se Windows installer lein. PHP select karte waqt `C:\xampp\php\php.exe` dein. Composer 2 use karein.
2. [Node.js ki official download page](https://nodejs.org/en/download) se Windows installer lein. Is lockfile ke liye Node **24.0.0 ya us se naya 24.x** use karein. Purani guide ka Node 18 / 20.11 example use na karein.
3. Naya CMD khol kar check karein:

```cmd
composer -V
node -v
npm -v
```

Internet dependencies download karne ke waqt chahiye. Agar developer compatible `vendor` aur ready `public/build` package bana kar deta hai to client par Node/npm build ki zaroorat nahi; source se install ke liye neeche complete steps hain.

## 4. Project client laptop par copy karein

Project ki deployment copy USB/ZIP se yahan extract karein:

```text
C:\xampp\htdocs\test-fnal_telor
```

Isi folder ke seedha andar `artisan`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `.env.example`, `app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`, `scripts`, `storage` hon. Double nested folder zaroori nahi.

Fresh client package banate waqt apne original project se kuch delete na karein. Sirf deployment copy se ye cheezein exclude karein:

- Development `.env`, SQL dumps, `.git`, `node_modules`, tests/dev tools aur temporary QA files.
- Development logs, sessions, compiled views aur generated PHP cache files in `bootstrap/cache` (directory aur `.gitignore` rehne dein).
- `public/hot` aur purana `public/storage` link; client par naya link banega.
- Apne development uploads/business backups aur `storage/app/license/license.dat`.
- Poora owner license generator/private-key folder. Client ko private key kabhi na dein.

`resources/license/public.key` zaroor copy karein. `storage/app/public`, `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/logs`, `bootstrap/cache` directories mojood aur writable hon. `vendor` missing ho to Composer neeche bana dega.

CMD mein:

```cmd
cd /d C:\xampp\htdocs\test-fnal_telor
copy .env.example .env
notepad .env
```

Copy command sirf fresh installation ke liye hai; existing client ki `.env` overwrite na karein. File ka naam `.env.txt` nahi hona chahiye; Explorer mein file extensions show karein.

## 5. Khali database banayein

phpMyAdmin → **New** → naam `tailor_store` → collation `utf8mb4_unicode_ci` → **Create**.

Fresh installation mein SQL file import karna zaroori nahi; migrations tables banayengi. MySQL ka actual port XAMPP mein note karein; aam tor par 3306 hota hai.

## 6. .env set karein

`.env.example` se bani file mein neeche wali keys replace karein; baqi lines rehne dein. Ek key ko do baar add na karein:

```dotenv
APP_NAME="Atelier"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://atelier.localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tailor_store
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_LEVEL=warning
MAIL_MAILER=log
```

`root` aur blank password sirf unchanged local XAMPP defaults hain. Agar MySQL password set hai to `DB_PASSWORD="actual-password"` likhein; port bhi actual use karein. Ye setup isi laptop ke local use ke liye hai. `QUEUE_CONNECTION=sync` se separate queue window nahi chahiye; current SMS bhi synchronous hain. Local HTTP ke liye secure cookie false hai; HTTPS deployment mein true karein.

## 7. Install aur admin account banayein

Project wale CMD mein har command complete hone dein:

```cmd
composer install --no-dev --optimize-autoloader
composer check-platform-reqs --no-dev
php artisan config:clear
php artisan key:generate --force
php artisan migrate --force
php artisan production:setup
```

`production:setup` aapse admin ka email, naam aur kam se kam **12 characters** ka password poochega. Password type karte waqt screen par dikhai nahi dega. Yehi login use hoga; koi default `password` account use nahi karna. Existing active admin ho to command usay retain karti hai.

Ab design build aur uploads ka link:

```cmd
npm ci
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan optimize
php artisan migrate:status
```

`public/build/manifest.json` ban jana chahiye aur migrations Ran hon. Ready built package mila hai to sirf dono npm commands skip kar sakte hain.

Client par `php artisan db:seed`, demo seeders, `migrate:fresh`, `db:wipe` aur reset commands mat chalayein. `production:setup` zaroori permissions/settings khud install karta hai. Fresh key sirf ek dafa banayein; baad mein key badalne se encrypted settings ka access kharab ho sakta hai.

## 8. Apache se browser mein chalayein

Rozana terminal khula rakhne ki zaroorat se bachne ke liye Apache ka document root project ke **public** folder par set karein.

1. `C:\xampp\apache\conf\httpd.conf` ka backup lein. Confirm karein ke `LoadModule rewrite_module modules/mod_rewrite.so` aur `Include conf/extra/httpd-vhosts.conf` comment (`#`) na hon.
2. `C:\xampp\apache\conf\extra\httpd-vhosts.conf` ka backup lein. Existing valid entries retain karte hue neeche blocks add karein. `localhost` ka block pehle se hai to duplicate na banayein:

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs"
    <Directory "C:/xampp/htdocs">
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName atelier.localhost
    DocumentRoot "C:/xampp/htdocs/test-fnal_telor/public"
    <Directory "C:/xampp/htdocs/test-fnal_telor/public">
        Options FollowSymLinks
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

3. Notepad **Run as administrator** karke `C:\Windows\System32\drivers\etc\hosts` kholein (file picker mein All files select karein). Ye line add karke save karein:

```text
127.0.0.1 atelier.localhost
```

4. CMD mein config check karein:

```cmd
C:\xampp\apache\bin\httpd.exe -t
```

5. `Syntax OK` ke baad Apache restart karein. Browser mein [Atelier](http://atelier.localhost) kholein. `APP_URL` bhi isi address ka hona chahiye.

Project ke root folder ko public document root na banayein. Ye config local access rakhti hai; Wi-Fi ke doosre devices ke liye ye guide nahi hai.

## 9. Client ke laptop ka license activate karein

1. [Activation page](http://atelier.localhost/license) kholein aur **Machine ID** copy karein.
2. **Apne owner laptop par**, client laptop par nahi, PowerShell kholein. Project ki license documentation ke mutabiq owner generator:

```powershell
& "D:\TailorLicenseOwner\Generate-License.ps1"
```

3. Client Name, client ka Machine ID, Trial/Lifetime aur Trial ho to expiry enter karein. Agar owner folder doosri jagah rakha hai to uska actual path use karein; tafseel `docs/offline-licensing.md` mein hai.
4. Generator jo `license.dat` de, sirf woh client ko USB se dein.
5. Client ki activation page par file upload karein. Success ke baad section 7 ke admin email/password se login karein.

Apne laptop ka existing license doosre laptop par nahi chalega. `resources/license/public.key` rehni chahiye. Windows date/time sahi rakhein. Windows reinstall hone par naya Machine ID/license chahiye ho sakta hai. Activation ke liye hosting/domain khareedna zaroori nahi.

## 10. Background delivery check lagayein

Admin PowerShell client par kholein aur ye chalayein:

```powershell
Set-Location 'C:\xampp\htdocs\test-fnal_telor'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\install-delivery-task.ps1 -PhpWin 'C:\xampp\php\php-win.exe'
```

Ye existing project script ek minute ka windowless Windows scheduled task banata hai. Global execution policy change nahi hoti. Default S4U unattended mode hai. Agar Windows account ki wajah se S4U registration fail ho, signed-in use ke liye:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\install-delivery-task.ps1 -PhpWin 'C:\xampp\php\php-win.exe' -Interactive
```

Interactive mode mein Windows account signed in rehna chahiye. Task Scheduler mein `Atelier-Delivery-Attention-test-fnal_telor` dekhein. MySQL running rakh kar 1–2 minute baad `storage/framework/delivery-background-status.json` ka `completed_at` update verify karein. Failure ki detail `storage/logs/delivery-background-errors.log` mein hogi.

Is worker ke saath delivery checks ke liye alag `schedule:run` task bhi na lagayein. Current Windows worker internal delivery attention check karta hai; customer ko automatic SMS bhejne ki guarantee nahi. Laptop off/sleep mein checks nahi chalte. `QUEUE_CONNECTION=sync` ki wajah se queue worker bhi zaroori nahi.

## 11. Client ki settings aur roz ka use

1. Settings mein shop name, logo, phone, currency aur timezone (Pakistan ke liye Asia/Karachi) set karein.
2. Staff/users aur unki permissions banayein; actual services, prices aur cloth stock enter karein.
3. SMS chahiye to Settings mein provider, API key aur sender configuration save karein. Tafseel `NOTIFICATION-SETUP.md` mein hai; scheduling ke liye upar current Windows instructions follow karein. SMS ke liye internet/provider credit chahiye. Client ki ijazat se apne number par test SMS bhejein; charge lag sakta hai.
4. Printer Windows mein install karein aur receipt/PDF print preview check karein.
5. Roz XAMPP khol kar Apache aur MySQL Start karein, phir `http://atelier.localhost` kholein. Browser bookmark ya desktop shortcut bana dein. `npm run dev` / `php artisan serve` roz chalane ki zaroorat nahi.
6. Auto-start chahiye to XAMPP Control Panel administrator ke tor par kholein, Apache/MySQL ki **Svc** entries se services install karein (agar pehle nahi hain); Windows Services mein unki Startup type Automatic karein. Existing duplicate Apache/MySQL service na banayein. Restart karke dono aur website check karein. Sirf XAMPP panel auto-open hona services chalne ka proof nahi.

Core local kaam internet ke baghair test karein. SMS online service hai. Computer shut down se pehle current entry save karein.

## 12. Backup, purana data transfer aur update

### Rozana backup

phpMyAdmin mein `tailor_store` select karein → Export → SQL → Go. Is SQL ke saath `storage/app/public`, `.env` (APP_KEY samait), client `storage/app/license/license.dat` aur project/version ki copy backup mein rakhein. Backup `public`/htdocs mein na rakhein; protected external drive/folder mein rakhein. `.env` aur backups mein private information hoti hai. Kabhi spare database par restore karke backup verify bhi karein.

### Agar purane client ka data bhi lana hai

Ye fresh blank setup se alag raasta hai: source app mein entries temporarily rok kar database SQL, uploads aur matching `.env`/APP_KEY ki backup lein. Client par khali database mein phpMyAdmin → Import se SQL import karein, uploads restore karein aur matching purana APP_KEY retain karein. `.env` mein sirf destination DB credentials/URL waghera adjust karein. **Key generate dobara nahi karni.** Phir dependencies/assets setup karke `config:clear`, `migrate --force`, `production:setup`, `storage:link`, `optimize:clear`, `optimize` chalayein. Imported users/passwords retain honge. Doosre laptop ke liye naya machine-bound license phir bhi chahiye. Developer ka demo database fresh client ko import na karein.

### Baad mein application update

Pehle verified backup lein aur entries band karein. Client ki `.env`, uploads aur license retain karein; code update ke baad Composer install, required asset build, `php artisan migrate --force` aur caches rebuild karein. Existing database par fresh/reset commands kabhi nahi. Database imports ko populated live database mein seedha repeat na karein.

## 13. Common errors

| Error / masla | Kya check karein |
|---|---|
| `php`/`composer`/`npm` not recognized | PATH aur installation; naya CMD kholein. |
| Composer PHP/extension error | `where php`, `php --ini`, `php -m`; required PHP/modules theek karein. `--ignore-platform-reqs` se bypass na karein. |
| npm engine error | Node version section 3 ke mutabiq karein. |
| `Could not open input file: artisan` | CMD us folder mein lein jahan artisan file hai. |
| Connection refused / SQLSTATE 2002 | MySQL Start, actual DB_PORT, DB_HOST check karein. |
| Access denied / Unknown database | DB user/password aur database name; `.env` change ke baad `php artisan config:clear`. |
| No application encryption key | Fresh install mein section 7; transferred data mein original APP_KEY restore karein. |
| Vite manifest missing / design nahi | `npm ci`, `npm run build`; public/build transfer check karein. Deployment copy ka stale public/hot remove karein. |
| Logo/photos nahi | storage/app/public files aur public/storage link target check karein. Symlink permission fail ho to trusted project CMD administrator ke tor par khol kar storage:link retry karein. |
| 419 Page Expired | Ek hi URL use karein; SESSION_DOMAIN=null, local HTTP par secure cookie false, config clear; browser ki site cookies clear karein. |
| 403 / routes par 404 | VirtualHost ka public path, rewrite module, AllowOverride aur public/.htaccess check karein. |
| Apache start nahi hota | Apache error log aur port 80 conflict check karein. Agar 8080 use karein to Listen, dono VirtualHost ports aur APP_URL/browser URL sab mein 8080 match karein. |
| License invalid / wrong machine | Client Machine ID ka signed license aur matching public.key; date/time check karein. |
| Machine ID unavailable | Apache PHP ko reg.exe/process execution aur MachineGuid read access chahiye. Worker ke restricted flags main PHP config mein copy na karein. |
| 500 error | storage/logs/laravel.log dekhein; storage/bootstrap/cache write access aur dependencies check karein. Client par debug permanently on na karein. |
| Background task fail | Actual PhpWin path, MySQL running, scheduled-task result aur delivery-background-errors.log check karein. |

## 14. Handover checklist

- [ ] PHP/platform check pass, Apache aur MySQL running.
- [ ] Database migrations Ran; fresh client mein demo data nahi.
- [ ] Admin account se login aur license valid.
- [ ] CSS, icons, logo, Tailor aur Cloth Store screens load hoti hain.
- [ ] Controlled sample customer/order/measurement/payment/receipt workflow verify kiya; test entries ko proper application workflow se handle kiya.
- [ ] Stock/POS use hona hai to actual opening stock aur sale workflow verify kiya.
- [ ] Background status timestamp update hota hai.
- [ ] Printer aur zaroorat ho to authorized SMS test verify kiya.
- [ ] Backup safe jagah hai aur restore process samajh aa gaya.
- [ ] Laptop restart ke baad website dobara khol kar verify ki.

Ye file setup instructions hai; client laptop par installation aur checks wahin perform karne hain.
