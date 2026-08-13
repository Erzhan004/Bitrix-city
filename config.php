<?php

declare(strict_types=1);

/**
 * Central configuration for Wazzup → Bitrix24 lead routing.
 * Edit this file only — no .env required.
 */
return [

  'app' => [
    'debug' => false,
    'timezone' => 'Asia/Almaty',
  ],

  'wazzup' => [
    'api_key' => '392140e389874e4ebe688ea3d99b3f3c',
    'webhook_secret' => 'bC7kM9pQ2xR5vN8wL4jT6hF',
    'public_webhook_url' => 'https://bitrix-city.designportal.kz/public/webhook.php',
    'allowed_channel_ids' => null,
  ],

  'bitrix' => [
    'webhook' => 'https://b24-9hskx2.bitrix24.kz/rest/1/4xio3vkll2vzixt5/',

    'entity_type' => [
      'lead' => 1,
      'deal' => 2,
      'contact' => 3,
    ],

    'http' => [
      'connect_timeout' => 5.0,
      'timeout' => 20.0,
      'retry_attempts' => 3,
      'retry_delay_ms' => 500,
    ],
  ],

  /**
   * Lead statuses (crm.item stageId for leads = classic STATUS_ID).
   * Replace with real STATUS_ID from crm.status.list (ENTITY_ID=STATUS).
   */
  'lead' => [
    'city_field' => 'UF_CRM_1786596216514',

    'statuses' => [
      'unprocessed' => 'NEW',        // Не обработан
      'processed' => 'CONVERTED',    // Качественный лид
    ],
  ],

  /**
   * Branch routing by city text → deal funnel.
   * category_id / stage_id MUST belong to the same funnel.
   *
   * While lead is "Не обработан":
   * - message NOT in cities → ignore (keep waiting)
   * - message matches a branch → save city + create deal + mark processed
   */
  'branches' => [

    'astana' => [
      'name' => 'Астана',
      'cities' => [
        'астана',
        'astana',
        'нур-султан',
        'нурсултан',
        'нур султан',
        '1',
      ],
      'category_id' => 0,       // воронка «Астана» (основная)
      'stage_id' => 'NEW',      // стадия «новый»
      'assigned_by_id' => null,
    ],

    'almaty' => [
      'name' => 'Алматы',
      'cities' => [
        'алматы',
        'алмата',
        'алма-ата',
        'алма ата',
        'almaty',
        '2',
      ],
      'category_id' => 2,       // воронка «Алматы»
      'stage_id' => 'C2:NEW',   // стадия «Новая»
      'assigned_by_id' => null,
    ],

    'pavlodar' => [
      'name' => 'Павлодар',
      'cities' => [
        'павлодар',
        'pavlodar',
        '3',
      ],
      'category_id' => 4,       // воронка «Павлодар»
      'stage_id' => 'C4:NEW',   // стадия «Новая»
      'assigned_by_id' => null,
    ],

  ],

  'deal' => [
    // Placeholders: {branch}, {phone}, {city}, {lead_id}
    'title_template' => 'Заявка WhatsApp — {branch} — +{phone}',
  ],

  'security' => [
    'max_body_bytes' => 1048576,
    'allowed_methods' => ['POST'],
    'content_types' => [
      'application/json',
      'application/json; charset=utf-8',
      'application/json; charset-utf-8',
    ],
  ],

  'logging' => [
    'enabled' => true,
    'level' => 'info',
    'file' => __DIR__ . '/storage/logs/wazzup.log',
    'max_message_length' => 255,
  ],

];
