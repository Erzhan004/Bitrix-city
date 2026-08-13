# Wazzup → Bitrix24 (Leads + Branch Routing)

## Logic

```
Лид STATUS = Не обработан
  → любое сообщение не из списка городов → IGNORE (ждём дальше)
  → Астана / Алматы / Павлодар → город в лид + сделка в воронке + Обработан

Лид STATUS = Обработан
  → сообщения игнорируются
```

No SQLite. No field «Ожидаем город».

## Install

```bash
composer install
chmod -R 775 storage/logs
```

## config.php

Set:

- `lead.city_field`
- `lead.statuses.unprocessed` / `processed`
- `branches.*.category_id` / `stage_id` / `cities`

## Example logs

```
UNPROCESSED_LEAD_FOUND
WAITING_FOR_CITY_IGNORED   # «Здравствуйте»
...
BRANCH_RESOLVED_ASTANA
CITY_SAVED_TO_LEAD
DEAL_CREATED
LEAD_MARKED_PROCESSED
```
