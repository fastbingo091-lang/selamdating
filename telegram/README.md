# GoferBet Telegram bot

The bot can answer `/start`, `/help`, `/balance`, and `/profile`. Users can also send a normal message to the bot; it forwards that message to the configured support operator. An operator can reply from Telegram with `/reply <chat_id> <message>`.

## Local XAMPP mode (long polling)

For local XAMPP, use long polling; Telegram connects through the bot's outbound API requests, so a public HTTPS URL is not required. Because the BotFather token was pasted into a chat, revoke it with BotFather and create a replacement. Do not paste the replacement token into chat or save it in source code.

From the project directory, run:

```powershell
powershell -ExecutionPolicy Bypass -File .\telegram\run_bot.ps1
```

The script requests the replacement token using masked input, then asks for the operator's numeric Telegram user ID. It provides those values only to the running PHP process and starts the polling loop. Keep that terminal open while you want the bot online.

Users can send `/start`, `/help`, `/balance`, or `/profile`. Balance/profile commands require the Telegram ID to be linked by the verified Mini App registration flow. Normal user messages are forwarded to the `TELEGRAM_ADMIN_IDS` operator. Reply from the operator account using `/reply <chat_id> <message>`. A Telegram user must start or message the bot before the bot can reply to them.

Polling and webhooks cannot run simultaneously. The polling script checks for an existing webhook and exits if one is configured.

## Hosted webhook setup

1. Create a bot with Telegram's official BotFather. Keep its token private and rotate it if it has been exposed.
2. Deploy GoferBet to a public HTTPS host. Telegram cannot deliver webhooks to `localhost`, a private LAN address, or plain HTTP.
3. Set these environment variables for Apache/PHP before starting Apache:
   - `TELEGRAM_BOT_TOKEN`: the BotFather token.
   - `TELEGRAM_WEBHOOK_SECRET`: a random 32-256 character value containing only letters, numbers, `_`, or `-`.
   - `TELEGRAM_WEBHOOK_URL`: the public HTTPS URL ending in `/telegram/webhook.php`.
   - `TELEGRAM_ADMIN_IDS`: comma-separated numeric Telegram user IDs allowed to reply to users.
4. Restart Apache so it reads the environment, then register the webhook from the project directory:

```powershell
C:\xampp\php\php.exe telegram\set_webhook.php
```

The setup script only accepts HTTPS webhook URLs. It reads secrets from the process environment; do not place tokens in PHP files, source control, screenshots, or chat messages.

## User linking and messaging

Telegram account commands use the Telegram numeric user ID only when it was saved through the verified Mini App registration flow. Users whose accounts are not linked can still send a message to support, but `/balance` and `/profile` require a linked account. A Telegram user must start or message the bot before the bot can message them.

Each incoming support message is sent to IDs in `TELEGRAM_ADMIN_IDS`, with the sender's chat ID. An allowlisted operator replies in their Telegram conversation with `/reply <chat_id> <message>`. Only allowlisted Telegram IDs can invoke `/reply`.

The webhook validates `X-Telegram-Bot-Api-Secret-Token`, limits request size, and never logs message bodies or bot tokens. All wallet values it reports are DEMO USDT and have no real-money value.