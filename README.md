# Wazzup → Bitrix24 Integration

PHP 8.3 webhook service: incoming Wazzup message → find Bitrix24 deal waiting for city → save city → change stage.

## Architecture / Архитектура

```
public/webhook.php              HTTP entrypoint (security, JSON, fast 200)
src/WazzupWebhookHandler.php    Orchestration, error handling
src/Wazzup/WazzupWebhookParser.php  Wazzup v3 payload parsing
src/Flow/FlowOrchestrator.php   Extensible flows (city, IIN, address, ...)
src/Bitrix/DealService.php      Contact lookup + deal selection + update
src/Bitrix/BitrixClient.php     Guzzle REST client with retries
config.php                      All settings (no .env)
```

**Why this structure**

- `config.php` + `flows` — add new scenarios without touching core logic
- `FlowOrchestrator` — one pipeline for all «waiting field → save answer → stage» flows
- `crm.item.*` — current Bitrix24 universal API
- **No database, no cron** — repeat protection via Bitrix field «Ожидаем город = Нет»
- Monolog — structured logs without secrets

## Requirements

- PHP >= 8.3
- Extensions: `curl`, `json`, `mbstring`
- Composer
- Writable `storage/logs`

## Install

```bash
composer install
chmod -R 775 storage
```

Point web server document root to `public/` **or** expose:

```text
https://domain.kz/wazzup/public/webhook.php?key=SECRET
```

## Repeat webhook protection / Защита от повторов

No SQLite. Protection is in Bitrix24:

1. First message: `Ожидаем город = Да` → city saved → `Ожидаем город = Нет` + stage changed
2. Wazzup resends same webhook → deal no longer matches `waiting_yes` → **ignored**

Log: `Ignored because waiting field is not yes`

## config.php — what to change

| Key | Description |
|-----|-------------|
| `wazzup.api_key` | Bearer token for `PATCH /v3/webhooks` |
| `wazzup.webhook_secret` | Query `?key=` for your endpoint |
| `bitrix.webhook` | Incoming webhook URL ending with `/` |
| `flows.city.waiting_field` | UF code «Ожидаем город» |
| `flows.city.target_field` | UF code «Город» |
| `flows.city.after_stage` | `STAGE_ID` after city received |

## Postman test

`POST https://domain.kz/.../webhook.php?key=YOUR_SECRET`

```json
{
  "messages": [{
    "messageId": "11111111-2222-3333-4444-555555555555",
    "chatType": "whatsapp",
    "chatId": "77071234567",
    "type": "text",
    "status": "inbound",
    "text": "Костанай",
    "isEcho": false
  }]
}
```

## Logs / Диагностика

| Log message | Meaning |
|-------------|---------|
| `Webhook received` | Request accepted |
| `Contact found` | Phone matched in Bitrix |
| `Deal updated` | City saved, stage changed |
| `Ignored because waiting field is not yes` | Repeat or not waiting for city |
| `MULTIPLE_WAITING_DEALS` | Ambiguous deals — no changes |
| `Bitrix API error` | REST failure |

## Behaviour checklist

| Case | Behaviour |
|------|-----------|
| Repeat webhook after success | Ignored (`waiting_city = Нет`) |
| Message after city saved | Ignored |
| Multiple waiting deals | `MULTIPLE_WAITING_DEALS`, no update |
| Outbound / `isEcho=true` | Ignored |
| Wazzup `test:true` | 200 OK, no Bitrix calls |
