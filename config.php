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
    'webhook_secret' => 'CHANGE_ME',

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
   * Extensible flows: each flow maps "waiting field = yes" → save text → update deal.
   * Add new scenarios (IIN, address, etc.) by copying the city block.
   */
  'flows' => [

    'city' => [
      'enabled' => true,
      'description' => 'Save client city and move deal to next stage',

      'waiting_field' => 'UF_CRM_WAITING_CITY',
      'target_field' => 'UF_CRM_CITY',
      'after_stage' => 'NEW',

      'waiting_yes' => '1',
      'waiting_no' => '0',

      // Optional per-flow funnel filter (overrides bitrix.category_id when set).
      'category_id' => null,

      'processor' => 'text',
      'processor_options' => [
        'save_raw_text' => true,
        'normalize' => false,
        'aliases' => [
          'алмата' => 'Алматы',
          'алма-ата' => 'Алматы',
          'астана' => 'Астана',
          'нур-султан' => 'Астана',
          'нурсултан' => 'Астана',
        ],
        // Empty = allow any city text.
        'allowed_cities' => [],
        // When allowed_cities is not empty: 'reject' | 'save_raw'
        'unknown_city_behavior' => 'save_raw',
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
