# Telegram Post Bot

Telegram Post Bot is a WordPress plugin that lets configured Telegram users submit WordPress posts through a guided chat flow. Submissions are created as drafts so editorial publishing remains inside WordPress.

- [Project website and documentation](https://omidakhavans.github.io/telegram-post-bot/)
- [Download the latest plugin ZIP](https://github.com/omidakhavans/telegram-post-bot/releases/latest/download/telegram-post-bot.zip)
- [GitHub releases](https://github.com/omidakhavans/telegram-post-bot/releases)
- [Companion implementation article](https://omidakhavan.blog/front-end-wordpress-submission-telegram-bot/)

## Features

- Guided Telegram session for title, tags, category, and content.
- Access limited to configured Telegram user IDs.
- WordPress posts created with `draft` status.
- Categories created when the requested category does not exist.
- `/start`, `/post`, and `/endsession` commands.
- Temporary per-user state stored in WordPress transients with a one-hour expiration.

## Requirements

- PHP 8.0.2 or newer
- WordPress 5.8 or newer
- Composer for source development
- A Telegram bot token created with BotFather

The plugin uses:

- [`irazasyed/telegram-bot-sdk`](https://github.com/irazasyed/telegram-bot-sdk)
- [`vlucas/phpdotenv`](https://github.com/vlucas/phpdotenv)

## Installation

### From a release

1. Download [`telegram-post-bot.zip`](https://github.com/omidakhavans/telegram-post-bot/releases/latest/download/telegram-post-bot.zip).
2. Install it through WordPress or extract it into `wp-content/plugins/`.
3. Activate the plugin in WordPress.

The release ZIP includes production Composer dependencies.

### From source

1. Clone the repository into `wp-content/plugins/`.
2. Install dependencies:

   ```bash
   composer install
   ```

3. Activate the plugin in WordPress.

## Configuration

Create a `.env` file in the plugin directory:

```dotenv
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
TELEGRAM_AUTHORIZED_USERS=12345678,87654321
```

Do not commit `.env` or expose the bot token.

Configure Telegram to send webhook updates to:

```text
https://your-site.example/wp-json/telegram/v1/webhook/
```

For example, using the Telegram Bot API:

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -d "url=https://your-site.example/wp-json/telegram/v1/webhook/"
```

## Workflow

1. An authorized user sends `/start`.
2. The bot presents `/post` and `/endsession`.
3. `/post` collects the title, tags, category, and content step by step.
4. The user sends `publish` to submit the content.
5. WordPress creates a draft post and the bot sends back its permalink.

## Security notes

The webhook route is publicly reachable so Telegram can call it. The current implementation authorizes users inside the handler using `TELEGRAM_AUTHORIZED_USERS`, but it does not yet verify a Telegram secret token or cryptographic request signature. Use HTTPS, keep the bot token outside source control, and treat this project as a demonstration until request validation and rate limiting are strengthened.

The [companion article](https://omidakhavan.blog/front-end-wordpress-submission-telegram-bot/) explains the original implementation and identifies similar hardening work.

## Development and releases

The public site is a static Next.js export with Fumadocs MDX documentation. Run it locally with:

```bash
npm install
npm run dev
```

Run the static checks with:

```bash
npm run typecheck
npm run build
```

The `Build and release plugin` GitHub Actions workflow installs production Composer dependencies, builds `telegram-post-bot.zip`, and creates a GitHub Release for tags matching `v*`. Each release uses the stable asset name `telegram-post-bot.zip`.
