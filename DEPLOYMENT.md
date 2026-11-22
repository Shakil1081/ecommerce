# Deploying Pick & Pearl

## cPanel: shop.webspreed.com

The host has SSH but no Node.js. JS/CSS are built on your PC and **committed to git** in `public/build`, so the server gets them with `git pull`. SSR is off (`INERTIA_SSR_ENABLED=false`).

### One-time setup in cPanel

1. **MultiPHP Manager**: set `shop.webspreed.com` to **PHP 8.2 or 8.3**. In **Select PHP Version › Extensions**, enable `bcmath`, `intl`, `gd`, `mbstring`, `pdo_mysql`, `zip` and `fileinfo`.
2. **MySQL Databases**: create a database and a user. Add the user to the database with **All Privileges**, and note the full names (they start with your cPanel username).
3. **SSL/TLS Status**: run AutoSSL so `https://shop.webspreed.com` has a certificate.
4. **SSH / Terminal**: clone the code *outside* `public_html`:
   ```bash
   cd ~
   git clone https://github.com/Shakil1081/ecommerce.git shop.webspreed.com
   ```
   If the repository is private, use a GitHub deploy key or a personal access token.
5. **Domains** (or Subdomains): set the **Document Root** of `shop.webspreed.com` to `shop.webspreed.com/public`. Only `public/` may be reachable from the web; `.env` and the code must not be.
6. **Create the `.env` file** on the server:
   ```bash
   cd ~/shop.webspreed.com
   cp deploy/.env.cpanel.example .env
   nano .env        # fill in DB_DATABASE, DB_USERNAME, DB_PASSWORD
   ```
7. **Cron Jobs**: add one entry that runs every minute. Use the PHP path the deploy script prints:
   ```
   * * * * * cd ~/shop.webspreed.com && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
   ```

### Every deploy

1. **On your PC (Git Bash)**, build and push the assets. This is only needed when front-end code (`resources/js`, `resources/scss`) changed:
   ```bash
   bash deploy/build-assets.sh
   git add public/build && git commit -m "build: production assets" && git push origin main
   ```
2. **On the server (SSH)**:
   ```bash
   cd ~/shop.webspreed.com
   git pull origin main
   bash deploy/cpanel-deploy.sh --first   # first deploy only: app key, seed data, storage link
   bash deploy/cpanel-deploy.sh           # later deploys
   ```

The script does the following:

- puts the site into maintenance mode
- installs the PHP packages
- unpacks the assets
- migrates the database
- caches the config and routes
- brings the site back up

Then work through **section 5** (admin panel setup) and **section 6** (smoke test) below.

---

## Generic server

Step-by-step checklist for putting the store on a production server. Work through it top to bottom.

## 1. Server requirements

- PHP 8.2+ with `bcmath`, `intl`, `gd` (or `imagick`), `pdo_mysql`, `mbstring`, `openssl`, `curl`, `zip`
- MySQL 8 (or MariaDB 10.6+)
- Redis (cache)
- Node.js 20+ (build step and the SSR server)
- Composer 2
- HTTPS certificate. The site must be served over HTTPS only.

## 2. Production `.env`

Copy `.env.example` to `.env` on the server. Then change these values:

```dotenv
APP_NAME="Pick & Pearl"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
LOG_LEVEL=warning

DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

# Behind Cloudflare / nginx proxy (needed for real customer IPs in fraud checks)
TRUSTED_PROXIES=*

# Real Google reCAPTCHA / Cloudflare Turnstile keys (or leave empty and set them in the admin panel)
RECAPTCHA_SITE_KEY=...
RECAPTCHA_SECRET_KEY=...
```

Then generate the app key, once only:

```bash
php artisan key:generate
```

Keep this key safe. Payment, courier and SMS credentials are encrypted with it. If the key changes, those saved credentials can no longer be read.

## 3. Install and build

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build && npm run build:ssr
rm -f public/hot            # a leftover hot file makes every page blank

php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**First deploy only:** run the full seeder:

```bash
php artisan db:seed --force
```

This creates:

- the default admin (login details in `ADMIN_LOGIN.txt`; **change that password immediately after logging in**)
- the demo products and categories
- the Bangladesh districts
- the delivery zones and the default gateway records

The demo audit-log history is skipped in production.

**Later deploys:** don't run the full `db:seed` again. `LadiesBagsSeeder` resets the demo products' prices and stock levels to the seed values, which would overwrite real stock counts. Only re-run `RolesAndPermissionsSeeder` (shown above).

## 4. Background processes

**Cron.** Add one entry. It releases stock held by unpaid orders and sends abandoned-cart reminders:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

**SSR server.** Keep it running with Supervisor or pm2. It renders pages on the server so Google sees the full content:

```ini
[program:pickpearl-ssr]
command=php /path/to/app/artisan inertia:start-ssr
autostart=true
autorestart=true
user=www-data
```

After each deploy, run `php artisan inertia:stop-ssr`. Supervisor then restarts it with the new build.

## 5. Set up in the admin panel (`/login`)

- [ ] **Change the default admin password.**
- [ ] **Settings › Store Pages**: replace every `[placeholder]`: phone, email, address, trade licence and VAT BIN. bKash, Nagad and SSLCommerz check these pages during merchant approval.
- [ ] **Settings › Payment Methods**: add live bKash / Nagad / SSLCommerz credentials, and press "Test connection".
- [ ] **Delivery › Courier Settings**: add Steadfast / Pathao credentials **and a webhook secret**. Webhooks are rejected without a secret.
- [ ] **Delivery › Delivery Charges**: check the zone rates.
- [ ] **Marketing & Messages › SMS Settings**: set up an SMS gateway. Order OTP codes need it.
- [ ] **Marketing & Messages › Email Settings**: set up SMTP. Password-reset emails need it.
- [ ] **Staff & Security › Spam Protection**: use real reCAPTCHA / Turnstile keys, not test keys.
- [ ] **Settings › Image Storage**: choose local or cloud storage.
- [ ] **Staff & Security › Staff**: create staff accounts, each with only the role it needs.

If the server database was ever seeded with the development setup, check **Spam Protection**. It may still hold Google's public test keys, which let every request pass.

## 6. Smoke test after deploy

- [ ] Home page, Shop, a product page, and `/privacy`, `/terms`, `/returns` all load.
- [ ] View the page source of a product page: it has a single `<title>` and a `<script type="application/ld+json">`. If not, the SSR server isn't running.
- [ ] Place a test Cash on Delivery order, then confirm it appears in **Orders › Order List**.
- [ ] Register a customer account, log out, and use "Forgot password" (the email arrives).
- [ ] Check `https://your-domain.com/sitemap.xml`, then submit it in Google Search Console.
- [ ] Watch the browser console for CSP warnings for a few days. If there are none, set `CSP_ENFORCE=true` and run `php artisan config:cache` again.
