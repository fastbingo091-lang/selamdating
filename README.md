# Selam Dating App

Selam includes account registration and login, private dating profiles, reciprocal-preference discovery, profile likes and comments, and one-to-one chats. Profiles stay private until a member opts in to Discover; dating media and interactions are available only for reciprocal matches. Members can send one free message per conversation, while an active Selam membership unlocks further messages and other members’ Telegram usernames. The user interface uses a TikTok-inspired dark theme and vertical discovery feed.

Wallets use ETB. Each member has a numeric Selam user ID shown on their profile; internal transfers are sent to that ID and recorded as paired ledger entries. Agents credit deposits only after confirming receipt. Withdrawal requests reserve funds until an agent records the external bank/Telebirr payout or rejects the request. The application does not send bank or Telebirr payments automatically; all off-platform payments require agent verification.

## Requirements

- XAMPP with Apache, MySQL/MariaDB, and PHP 8.2 or newer
- A browser such as Chrome

## Phase 1 setup

1. Install XAMPP, then open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. The open workspace is `C:\xampp\htdocs\ጎፈርቤት`. The separately requested `C:\xampp\htdocs\goferbet` folder does not currently exist on this machine; copy the project there if that exact URL is required.
3. Open `http://localhost/phpmyadmin/` in Chrome. Choose **New**, enter `goferbet` as the database name, select `utf8mb4_unicode_ci` if offered, and create it.
4. For a fresh database, select `goferbet`, open **Import**, choose `database/schema.sql`, and run it. The SQL also creates/selects `goferbet` if it does not already exist. If you already imported an earlier schema, back up your database, select `goferbet`, open **SQL**, and run each migration not previously applied, in order: `database/migrations/20260925_phone_telegram.sql`, `database/migrations/20261023_dating_profiles.sql`, `database/migrations/20261024_dating_discovery_chat.sql`, and `database/migrations/20261025_social_wallet_agents.sql`.
5. Open `config/database.php` if your MySQL settings differ from the local XAMPP defaults. The defaults are host `127.0.0.1`, database `goferbet`, user `root`, and an empty password. For a password-protected or non-local database, set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in Apache's environment instead of committing credentials to source control.
6. Visit `http://localhost/ጎፈርቤት/` in Chrome to open Selam.

## Database requirement

Create/import the `goferbet` database before registering or using the application. The homepage runs a local PDO check and shows **Service ready** when the database connection works.

## Test registration and login

1. Confirm the homepage shows **Service ready**. If not, check the database setup below before testing accounts.
2. Click **Register** and enter a username (3-50 letters, numbers, or underscores), an international phone number such as `+251912345678`, and a password exactly six characters long using only A-Z, a-z, and 0-9. The form says: **Password must be exactly 6 letters or numbers.** Confirm the password and submit. SMS verification is not enabled.
3. You should be sent to Login with **Account created. You can now log in.** Registration saves a password hash and creates a zero-balance ETB wallet in one database transaction. The numeric ID assigned to your account appears on your profile.
4. Sign in using the username or phone number and the six-character password you registered. On first login, complete the private dating profile questionnaire (including your date of birth, gender, and who you want to meet) before reaching the dashboard.
5. Click **Logout** in the footer. You should return to Login and see **You have been logged out.**

After completing the questionnaire, opt in to Discover on your Profile to be shown to reciprocal matches. Discovery respects both members’ chosen preferences. Set an optional Telegram username on Profile; it is only shown inside a chat to members with an active membership. Every member can send one message per conversation without membership; active members can continue sending messages. Membership status uses an active row in `vip_purchases` whose package is active. Play has been removed from the Selam navigation. The Profile page lets you upload up to six private JPEG/PNG/WebP photos (10 MB each) and one private MP4/WebM video (40 MB). Media is only delivered through an authenticated owner-checking route, while raw files are blocked by the included Apache `.htaccess`. In `php.ini`, set `upload_max_filesize = 40M` and `post_max_size = 48M` (or higher) for the full video limit, then restart Apache. The bundled XAMPP configuration currently has `post_max_size = 40M`, so videos near the maximum may be rejected until that setting is increased. Logged-out visitors can discover Selam, sign in, or create an account. Wallet, betting, and admin features remain separate from dating discovery. Five failed login attempts in one browser session temporarily pause further attempts for 15 minutes; this is a basic local-development limit, not a substitute for production rate limiting.

## Password test cases

Accepted: `123456`, `ABC123`, `a1b2c3`, `Selam1`.

Rejected: `12345`, `1234567`, `abc!12`, `Gofer@1`, `12 3456`.

The server applies `^[A-Za-z0-9]{6}$` on signup and login. Passwords remain stored with PHP `password_hash()` and checked using `password_verify()`.

Run the phone and Telegram identity checks without connecting to MySQL:

```powershell
C:\xampp\php\php.exe tests\auth_identity_test.php
```

The test uses a local fake token, not your Telegram bot token.

## Telegram username

When registration is opened inside a Telegram Mini App, the Telegram username field is filled from Telegram's Mini App data. The server verifies Telegram's signed `initData` before accepting the Telegram username or ID. Bot chat setup and the local XAMPP runner are documented in [telegram/README.md](telegram/README.md). Do not put the bot token in PHP source code or commit it to Git. In a normal browser, enter a username manually. A phone number is not supplied by Telegram Mini Apps, so the user enters it; no SMS code is sent.

## Telegram bot chat

The bot's local long-polling and hosted webhook options are documented in [telegram/README.md](telegram/README.md). Local XAMPP can use polling; a deployed webhook requires a public HTTPS URL.

## Selam branding

The shared brand include displays the Selam heart mark and wordmark across the header, authentication pages, footer, and admin shell.

## Test the database connection

The homepage runs a small `SELECT 1` using the PDO connection. A **Service ready** status means PHP reached the configured database. If it reports that the service needs setup:

- Confirm MySQL is running in the XAMPP Control Panel.
- Confirm the database name and credentials in `config/database.php` match your MySQL setup.
- Confirm the schema import completed and PHP has the `pdo_mysql` extension enabled. XAMPP normally includes this extension.
- For the detailed local error, check the PHP/Apache error log in `C:\xampp\apache\logs\error.log`. The page intentionally does not show database error details to visitors.

Login PDO exceptions are logged server-side with the PDO error code and message; they are not sent to the browser. A database outage displays a generic retry message. Invalid credentials display a generic authentication error, and a correctly authenticated disabled account displays **Your account is inactive.**

## Admin screens

The `admin/` panel supports account search and enable/disable actions, receipt-based deposit review, agent-entered deposits, agent-processed withdrawals, activity logs, and online-agent presence. Disabled or deleted user accounts are signed out on their next request. Agents are active admin accounts; presence is refreshed while the operations panel remains open. The agent must confirm an external payment before crediting a deposit or marking a withdrawal paid. The application records wallet ledger entries but does not initiate external payouts.

## Development notes

- The connection function is `database()` in `config/database.php`.
- Shared base styling lives in `assets/css/style.css`; TikTok-inspired themes are in `assets/css/tiktok-app.css`, `assets/css/tiktok-discover.css`, and `assets/css/tiktok-admin.css`.
- Financial amounts use `DECIMAL` columns. New registrations use a unique phone number; email is optional.
- Wallet transfers write matching debit/credit transaction records in one database transaction. Withdrawal requests reserve funds in `locked_balance`; a completed payout debits the wallet and records a transaction, while a rejected request releases the reserved amount.
- To see PHP errors during local development, enable `display_errors=On` and `error_reporting=E_ALL` in XAMPP's `php.ini`, then restart Apache. Do not enable detailed error display in a production environment.
- Bootstrap 5 and the Amharic-capable font are loaded from public CDNs on the homepage, so those visual assets need an internet connection. The page structure and language text are otherwise served locally.

## Planned dating-app work

Safety, moderation, and reporting tools; accessibility and deployment review. Before using agent-assisted real-value payments in production, add formal agent authorization, identity/receipt checks, fraud monitoring, reconciliation, retention controls, and applicable financial/compliance review.