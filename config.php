<?php

declare(strict_types=1);

/**
 * Central configuration for Wazzup → Bitrix24 integration.
 * Edit this file only — no .env required.
 */
return [

  'app' => [
    'debug' => false,
    'timezone' => 'Asia/Almaty',
  ],

  'wazzup' => [
    // Used when registering webhook via PATCH /v3/webhooks (Authorization: Bearer ...).
    'api_key' => '392140e389874e4ebe688ea3d99b3f3c',

    // Query-string secret for public endpoint: webhook.php?key=...
    'webhook_secret' => 'bC7kM9pQ2xR5vN8wL4jT6hF',

    // Full public URL (for Wazzup registration).
    'public_webhook_url' => 'https://bitrix-city.designportal.kz/public/webhook.php',

    // null = accept all channels. Example: ['b96a353b-9999-4cac-8413-ba99999f981']
    'allowed_channel_ids' => null,
  ],

  'bitrix' => [
  // Incoming webhook URL (must end with /)
    'webhook' => 'https://b24-9hskx2.bitrix24.kz/rest/1/4xio3vkll2vzixt5/',

    // Deal entity type for crm.item.* (deals = 2).
    'deal_entity_type_id' => 2,

    // null = search in all funnels. Example: 0 (default funnel) or 9.
    'category_id' => null,

    // How to find contact by phone.
    'contact_lookup' => [
      'method' => 'crm.duplicate.findbycomm',
      'type' => 'PHONE',
      'entity_type' => 'CONTACT',
    ],

    // Active deal = stage semantics "process" (in progress).
    // Set false to skip semantics check (not recommended).
    'active_deal_semantics' => ['process'],

    'http' => [
      'connect_timeout' => 5.0,
      'timeout' => 20.0,
      'retry_attempts' => 3,
      'retry_delay_ms' => 500,
    ],
  ],

  /**
   * Extensible flows.
   *
   * waiting_mode:
   *   'stage' — сделка в колонке воронки (стадии), робот не нужен
   *   'field' — пользовательское поле «Ожидаем город = Да»
   */
  'flows' => [

    'city' => [
      'enabled' => true,
      'description' => 'Save client city and move deal to next stage',

      // Колонка воронки «Ожидаем город» = стадия сделки (STAGE_ID).
      'waiting_mode' => 'stage',
      'waiting_stage' => 'NEW',

      'target_field' => 'UF_CRM_1786528780569',
      'after_stage' => 'PREPARATION',

      // Только для waiting_mode = 'field':
      // 'waiting_field' => 'UF_CRM_WAITING_CITY',
      // 'waiting_yes' => '1',
      // 'waiting_no' => '0',

      // Optional per-flow funnel filter (overrides bitrix.category_id when set).
      'category_id' => null,

      'processor' => 'text',
      'processor_options' => [
        'save_raw_text' => true,
        'normalize' => false,

        // Номера и синонимы → каноническое название (сверяется с allowed_cities).
        // Подгоните цифры под текст вашего сообщения в Wazzup.
        'aliases' => [
          '1' => 'Астана',
          '2' => 'Алматы',
          '3' => 'Шымкент',
          '4' => 'Караганда',
          '5' => 'Актобе',
          '6' => 'Тараз',
          '7' => 'Павлодар',
          '8' => 'Усть-Каменогорск',
          '9' => 'Семей',
          '10' => 'Атырау',
          '11' => 'Костанай',
          '12' => 'Кызылорда',
          '13' => 'Уральск',
          '14' => 'Петропавловск',
          '15' => 'Актау',
          '16' => 'Темиртау',
          '17' => 'Туркестан',

          'алмата' => 'Алматы',
          'алма-ата' => 'Алматы',
          'алма ата' => 'Алматы',
          'астана' => 'Астана',
          'нур-султан' => 'Астана',
          'нурсултан' => 'Астана',
          'нур султан' => 'Астана',
          'шымкент' => 'Шымкент',
          'чимкент' => 'Шымкент',
          'караганда' => 'Караганда',
          'актобе' => 'Актобе',
          'актау' => 'Актау',
          'атырау' => 'Атырау',
          'костанай' => 'Костанай',
          'кызылорда' => 'Кызылорда',
          'павлодар' => 'Павлодар',
          'семей' => 'Семей',
          'семипалатинск' => 'Семей',
          'усть-каменогорск' => 'Усть-Каменогорск',
          'оскемен' => 'Усть-Каменогорск',
          'уральск' => 'Уральск',
          'петропавловск' => 'Петропавловск',
          'тараз' => 'Тараз',
          'туркестан' => 'Туркестан',
        ],

        // Вариант B: только города из списка. Остальное — отклоняется, сделка остаётся в «Ожидаем город».
        'allowed_cities' => [
          'Астана',
          'Алматы',
          'Шымкент',
          'Караганда',
          'Актобе',
          'Тараз',
          'Павлодар',
          'Усть-Каменогорск',
          'Семей',
          'Атырау',
          'Костанай',
          'Кызылорда',
          'Уральск',
          'Петропавловск',
          'Актау',
          'Темиртау',
          'Туркестан',
        ],

        'unknown_city_behavior' => 'reject',
      ],
    ],

    // Example for future use (disabled):
    // 'iin' => [
    //   'enabled' => false,
    //   'waiting_field' => 'UF_CRM_WAITING_IIN',
    //   'target_field' => 'UF_CRM_IIN',
    //   'after_stage' => 'STAGE_AFTER_IIN',
    //   'waiting_yes' => '1',
    //   'waiting_no' => '0',
    //   'processor' => 'text',
    //   'processor_options' => [
    //     'save_raw_text' => true,
    //     'normalize' => false,
    //     'aliases' => [],
    //     'allowed_cities' => [],
    //   ],
    // ],

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
