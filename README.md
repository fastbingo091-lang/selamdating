# Selam Market

Selam Market lets users browse products published by an administrator, purchase available stock from their ETB wallet, and see purchases in My Products. Each purchase records its own price, image, daily-profit amount, and profit duration. A user can claim each owned product’s profit once every 24 hours during its term. Claims and three-level team commissions are credited to the in-app wallet and recorded for admin reporting.

Wallets use ETB. Each member has a numeric Selam user ID; internal transfers are sent to that ID and recorded as paired ledger entries. Agents credit deposits only after confirming receipt. Withdrawal requests reserve funds until an agent records the external bank/Telebirr payout or rejects the request. Product-profit claims and team commissions credit the Selam wallet only; they do not represent an external bank or Telebirr transfer. Referral commissions are 15%, 5%, and 3% of the claimed daily profit for the first, second, and third referral levels.

## Requirements

- XAMPP with Apache, MySQL/MariaDB, and PHP 8.2 or newer
- A browser such as Chrome

## Run locally with Docker

Docker Desktop with Docker Compose is an alternative to the XAMPP setup below. From the repository folder, start the app and its MariaDB database:

```powershell
docker compose up --build -d
```

The app will be available at `http://localhost:8080`. The first run creates an empty database; import the schema and the application migrations before registering or using the app:

```powershell
function Import-DockerSql($path) {
    Get-Content -Raw -Encoding UTF8 $path |
        docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" goferbet'
}

Import-DockerSql database/schema.sql
@(
    "database/migrations/20260925_phone_telegram.sql",
    "database/migrations/20261025_social_wallet_agents.sql",
    "database/migrations/20261029_product_marketplace.sql",
    "database/migrations/20261030_marketplace_referrals_and_duration.sql",
    "database/migrations/20261031_notifications.sql",
    "database/migrations/20261032_marketplace_rewards.sql",
    "database/migrations/20261033_marketplace_partners.sql"
) | ForEach-Object { Import-DockerSql $_ }
```

Then visit `http://localhost:8080/healthz.php`; a successful database connection returns `ok`. The default database passwords in Compose are for local development only. Set `DB_PASS` and `DB_ROOT_PASSWORD` in your shell before starting the containers if you want different local credentials. Database and uploaded images persist in Docker volumes. To stop the containers without deleting data, run `docker compose down`; `docker compose down -v` also deletes the database and uploaded images.

## Phase 1 setup

1. Install XAMPP, then open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. The open workspace is `C:\xampp\htdocs\ጎፈርቤት`. The separately requested `C:\xampp\htdocs\goferbet` folder does not currently exist on this machine; copy the project there if that exact URL is required.
3. Open `http://localhost/phpmyadmin/` in Chrome. Choose **New**, enter `goferbet` as the database name, select `utf8mb4_unicode_ci` if offered, and create it.
4. For a fresh database, select `goferbet`, open **Import**, choose `database/schema.sql`, and run it. Then open **SQL** and run the migrations not included in the base schema: `database/migrations/20260925_phone_telegram.sql`, `database/migrations/20261025_social_wallet_agents.sql`, `database/migrations/20261029_product_marketplace.sql`, `database/migrations/20261030_marketplace_referrals_and_duration.sql`, `database/migrations/20261031_notifications.sql`, `database/migrations/20261032_marketplace_rewards.sql`, and `database/migrations/20261033_marketplace_partners.sql`. For an existing installation, back up the database first and run only migrations not already applied, in order. Old account data is retained, but dating pages are disabled.
5. Open `config/database.php` if your MySQL settings differ from the local XAMPP defaults. The defaults are host `127.0.0.1`, database `goferbet`, user `root`, and an empty password. For a password-protected or non-local database, set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in Apache's environment instead of committing credentials to source control.
6. Visit `http://localhost/ጎፈርቤት/` in Chrome to open Selam.

## Database requirement

Create/import the `goferbet` database before registering or using the application. The homepage runs a local PDO check and shows **Service ready** when the database connection works.

## Deploy to Render

The repository includes a Render Blueprint (`render.yaml`) and a Docker image for PHP 8.2 with Apache, `pdo_mysql`, `mysqli`, `mbstring`, and cURL. Render does not provide a managed MySQL database for this app; provision a separate MySQL/MariaDB-compatible database first. Do not select Render Postgres: the application schema and queries require MySQL/MariaDB.

1. Back up the local database before moving any existing users, wallets, purchases, coupons, or admin accounts. Provision a production MySQL/MariaDB database that permits connections from Render and supports InnoDB, `utf8mb4`, and the schema's foreign keys and checks. Do not use Render Postgres.
2. Create the database using the provider's console. Import `database/schema.sql`, omitting its initial `CREATE DATABASE` and `USE goferbet` statements if the provider created the database under a different name. Then apply these migrations once, in order: `database/migrations/20260925_phone_telegram.sql`, `database/migrations/20261025_social_wallet_agents.sql`, `database/migrations/20261029_product_marketplace.sql`, `database/migrations/20261030_marketplace_referrals_and_duration.sql`, `database/migrations/20261031_notifications.sql`, `database/migrations/20261032_marketplace_rewards.sql`, and `database/migrations/20261033_marketplace_partners.sql`. Do not reapply migrations to a database that already contains them.
3. In Render, create a **Blueprint** from this Git repository and review `render.yaml`. During Blueprint setup, provide `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` from the database provider; keep credentials out of Git. `DB_PORT` defaults to `3306`, and `APP_ENV=production` is already configured. If the provider requires a CA certificate, add it as a Render Secret File and set `DB_SSL_CA` to that file's path. The web service uses Render's paid `0.5c-512mb` plan because product image uploads require a persistent disk.
4. Keep one web instance: the image disk is attached to one instance and is not shared across scaled replicas. Wait for the deployment and `/healthz.php` check to succeed.
5. For a fresh database, open the Render Shell and create the first administrator with `php docker/create_admin.php <username>`. Enter a strong password at the hidden prompt. Existing imported admin accounts can sign in at `/admin/login.php`.
6. Test registration, login, product image upload, purchase, and admin access before directing users to the site.

The Render disk stores files under `assets/uploads`, including product images, across deploys and restarts. It does not import images from the local machine; re-upload those images through Admin → Products after deployment, or copy them to the mounted disk before launch. The database is separate and must be backed up with the database provider. Render's service filesystem outside that disk is ephemeral.

The Docker image uses `DB_PORT` (default `3306`) and optional `DB_SSL_CA`; it does not run schema migrations automatically. Configure production support contact variables in Render as needed. Telegram bot webhook variables are optional unless the bot is being used; see [telegram/README.md](telegram/README.md).

## Test registration and login

1. Confirm the homepage shows **Service ready**. If not, check the database setup below before testing accounts.
2. Click **Register** and enter a username (3-50 letters, numbers, or underscores), an international phone number such as `+251912345678`, and a password exactly six characters long using only A-Z, a-z, and 0-9. The form says: **Password must be exactly 6 letters or numbers.** Confirm the password and submit. SMS verification is not enabled.
3. You should be sent to Login with **Account created. You can now log in.** Registration saves a password hash, referral code, and zero-balance ETB wallet in one database transaction. The numeric ID assigned to your account is available in your account header.
4. Sign in using the username or phone number and the six-character password you registered. You will be sent to the product catalog.
5. Click **Logout** in the footer. You should return to Login and see **You have been logged out.**

Five failed login attempts in one browser session temporarily pause further attempts for 15 minutes; this is a basic local-development limit, not a substitute for production rate limiting.

## Password test cases

Accepted: `123456`, `ABC123`, `a1b2c3`, `Selam1`.

Rejected: `12345`, `1234567`, `abc!12`, `Gofer@1`, `12 3456`.

The server applies `^[A-Za-z0-9]{6}$` on signup and login. Passwords remain stored with PHP `password_hash()` and checked using `password_verify()`.

Run the phone and Telegram identity checks without connecting to MySQL:

```powershell
C:\xampp\php\php.exe tests\auth_identity_test.php
C:\xampp\php\php.exe tests\marketplace_partner_smoke.php
```

The identity test uses a local fake token, not your Telegram bot token. The marketplace smoke test requires the marketplace, rewards, and partner migrations to be applied; it creates temporary users and products, exercises coupon/partner flows, and removes its records afterward.

## Telegram username

When registration is opened inside a Telegram Mini App, the Telegram username field is filled from Telegram's Mini App data. The server verifies Telegram's signed `initData` before accepting the Telegram username or ID. Bot chat setup and the local XAMPP runner are documented in [telegram/README.md](telegram/README.md). Do not put the bot token in PHP source code or commit it to Git. In a normal browser, enter a username manually. A phone number is not supplied by Telegram Mini Apps, so the user enters it; no SMS code is sent.

## Telegram bot chat

The bot's local long-polling and hosted webhook options are documented in [telegram/README.md](telegram/README.md). Local XAMPP can use polling; a deployed webhook requires a public HTTPS URL.

## Selam branding

The shared brand include displays the Selam market mark and wordmark across the header, authentication pages, footer, and admin shell.

## Test the database connection

The homepage runs a small `SELECT 1` using the PDO connection. A **Service ready** status means PHP reached the configured database. If it reports that the service needs setup:

- Confirm MySQL is running in the XAMPP Control Panel.
- Confirm the database name and credentials in `config/database.php` match your MySQL setup.
- Confirm the schema import completed and PHP has the `pdo_mysql` extension enabled. XAMPP normally includes this extension.
- For the detailed local error, check the PHP/Apache error log in `C:\xampp\apache\logs\error.log`. The page intentionally does not show database error details to visitors.

Login PDO exceptions are logged server-side with the PDO error code and message; they are not sent to the browser. A database outage displays a generic retry message. Invalid credentials display a generic authentication error, and a correctly authenticated disabled account displays **Your account is inactive.**

## Admin screens

The `admin/` panel supports product creation, editing, stock and publishing controls, product-profit claim reporting, account search and enable/disable actions, receipt-based deposit review, agent-entered deposits, agent-processed withdrawals, activity logs, and online-agent presence. The dashboard summarizes catalog availability, recorded product sales, and user-claimed product profit. Disabled or deleted user accounts are signed out on their next request. Agents are active admin accounts; presence is refreshed while the operations panel remains open. The agent must confirm an external payment before crediting a deposit or marking a withdrawal paid. The application records wallet ledger entries but does not initiate external payouts.

Admins can also send in-app announcements to everyone or a message to one active user by Selam user ID. Users can review messages in Notifications. New accounts receive a persistent welcome message in their notification inbox. Global notifications appear centered on the page; loading spinners are centered and appear after two seconds. The Support page always displays Telegram customer support at `@accessme2018`; configure optional email and WhatsApp links with `SELAM_SUPPORT_EMAIL` and `SELAM_SUPPORT_WHATSAPP` (international phone number without spaces).

Admins manage fixed-ETB coupons and daily streak reward amounts from **Admin → Rewards**. Coupons can have an optional overall redemption limit and expiry; each user can redeem a coupon once during a product purchase. The daily streak reward credits the ETB wallet once per calendar day; consecutive days increase the displayed streak, and a missed day restarts the count.

Admins can also configure a one-time VIP Club upgrade price from **Admin → Rewards**. A successful upgrade permanently grants Partner status. Partners earn the existing 15% / 5% / 3% referral commissions plus an extra 9% on direct invitees' product-profit claims, and receive an extra 3% of their own product daily profit. Coupons are shared with all users and can be limited by an admin-set total number of redemptions.


The admin claim ledger reports in-app wallet credits and three-level commissions. Product daily profit stops after the number of days configured for the purchased product. External withdrawal requests continue to be reviewed separately in the Withdrawal Requests screen.

## Development notes

- The connection function is `database()` in `config/database.php`.
- Shared styling lives in `assets/css/style.css`; marketplace and admin themes are in `assets/css/tiktok-app.css` and `assets/css/tiktok-admin.css`. `assets/js/app.js` adds submission/navigation spinners and global success/error toasts across user and admin pages.
- Financial amounts use `DECIMAL` columns. New registrations use a unique phone number; email is optional.
- Wallet transfers write matching debit/credit transaction records in one database transaction. Withdrawal requests reserve funds in `locked_balance`; a completed payout debits the wallet and records a transaction, while a rejected request releases the reserved amount.
- To see PHP errors during local development, enable `display_errors=On` and `error_reporting=E_ALL` in XAMPP's `php.ini`, then restart Apache. Do not enable detailed error display in a production environment.
- Bootstrap 5 and the Amharic-capable font are loaded from public CDNs on the homepage, so those visual assets need an internet connection. The page structure and language text are otherwise served locally.

## Production readiness

Before using real-value payments or daily-profit claims in production, add formal authorization, identity/receipt checks, fraud monitoring, reconciliation, retention controls, and applicable financial/compliance review.