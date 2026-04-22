<?php

declare(strict_types=1);

/**
 * Copy to config.php and fill in real credentials (config.php is gitignored on production).
 */
return [
    'app_name' => 'УЗЕЛОК64',
    'app_url' => 'https://uzelok64.ru',
    /** ID счётчика Яндекс Метрики (metrika.yandex.ru). 0 — не подключать. */
    'yandex_metrika_id' => 108717789,
    'debug' => false,
    'paths' => [
        'root' => dirname(__DIR__),
        'public' => dirname(__DIR__) . '/public_html',
        'database' => dirname(__DIR__) . '/database/uzelok.db',
        'logs' => dirname(__DIR__) . '/logs',
        'templates' => dirname(__DIR__) . '/templates',
        'images' => dirname(__DIR__) . '/public_html/assets/images',
    ],
    'ozon' => [
        // Продакшен (три кабинета): скопируйте `ozon.env.example` в корень сайта как `.ozon.env`, заполните ключи.
        // `client_id` / `api_key` здесь оставьте пустыми. Затем по SSH: `php cron/sync.php`.
        // Один кабинет: заполните `client_id`, `api_key` и список `skus`; `.ozon.env` не нужен.
        'client_id' => '',
        'api_key' => '',
        'base_url' => 'https://api-seller.ozon.ru',

        // Мульти-кабинет: `.ozon.env` в корне (см. `OzonEnvParser`, пример — `ozon.env.example`).
        // Иначе — `skus` + один `client_id` / `api_key` выше.

        // Реальные Ozon Product ID для отслеживания
        'product_ids' => [
            3526089555, 2384564507, 3353068030, 3352955195,
            3352968330, 3352903699, 3347697391,
            1893584234, 1893558422, 1884174749,
            1666958285,
        ],

        // SKU placeholder → бренд (реальные offer_id подставятся после API)
        'sku_brand_map' => [
            'BUY-DOMKRAT' => 'buy',
            'BUY-COMPRESSOR' => 'buy',
            'BUY-PROVODA' => 'buy',
            'BUY-STROPA' => 'buy',
            'BUY-ELECTRIKA' => 'buy',
            'BUY-APTECHKA' => 'buy',
            'BUY-INSTRUMENT' => 'buy',
            'BATYA-REZTSY' => 'batya',
            'BATYA-SKRUTKA' => 'batya',
            'BATYA-AVTO' => 'batya',
            'VOLNA-YAKOR' => 'volna',
        ],

        // Ozon Product ID → SKU
        'product_id_to_sku' => [
            3526089555 => 'BUY-DOMKRAT',
            2384564507 => 'BUY-COMPRESSOR',
            3353068030 => 'BUY-PROVODA',
            3352955195 => 'BUY-STROPA',
            3352968330 => 'BUY-ELECTRIKA',
            3352903699 => 'BUY-APTECHKA',
            3347697391 => 'BUY-INSTRUMENT',
            1893584234 => 'BATYA-REZTSY',
            1893558422 => 'BATYA-SKRUTKA',
            1884174749 => 'BATYA-AVTO',
            1666958285 => 'VOLNA-YAKOR',
        ],

        // Список offer_id для запросов к API / мока (порядок = sort_order в моке)
        'skus' => [
            'BUY-DOMKRAT',
            'BUY-COMPRESSOR',
            'BUY-PROVODA',
            'BUY-STROPA',
            'BUY-ELECTRIKA',
            'BUY-APTECHKA',
            'BUY-INSTRUMENT',
            'BATYA-REZTSY',
            'BATYA-SKRUTKA',
            'BATYA-AVTO',
            'VOLNA-YAKOR',
        ],
    ],
    'telegram' => [
        'bot_token' => 'YOUR_TELEGRAM_BOT_TOKEN',
        'chat_id' => 'YOUR_TELEGRAM_CHAT_ID',
        'base_url' => 'https://api.telegram.org',
    ],
    'email' => [
        'to' => 'ananev-dm@mail.ru',
        'from' => 'noreply@uzelok64.ru',
        'from_name' => 'УЗЕЛОК64 — Заявка с сайта',
    ],
    'admin' => [
        'username' => 'admin',
        'password' => 'CHANGE_ME_SECURE_PASSWORD',
    ],
    'ozon_stores' => [
        'batya' => 'https://www.ozon.ru/seller/batya-2103460/',
        'buy' => 'https://www.ozon.ru/seller/buy/',
        'volna' => 'https://www.ozon.ru/seller/volna-2250971/',
    ],
];
