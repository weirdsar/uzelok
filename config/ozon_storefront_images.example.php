<?php

declare(strict_types=1);

/**
 * Скопируйте в ozon_storefront_images.local.php (файл в .gitignore).
 * Нужен, если на сайте без реальных ключей Ozon Seller API (мок-каталог):
 * иначе в БД не попадают URL с CDN Ozon, и остаются только локальные фото
 * (в т.ч. после scripts/download-images.php --demo).
 *
 * Откуда брать URL: откройте карточку товара на ozon.ru → «Просмотр кода» →
 * og:image или прямая ссылка на картинку из галереи (cdn1.ozonusercontent.com, ir-*.ozone.ru).
 *
 * После заполнения на сервере: php database/seed.php или php cron/sync.php
 */
return [
    'BUY-DOMKRAT' => '',
    'BUY-COMPRESSOR' => '',
    'BUY-PROVODA' => '',
    'BUY-STROPA' => '',
    'BUY-ELECTRIKA' => '',
    'BUY-APTECHKA' => '',
    'BUY-INSTRUMENT' => '',
    'BATYA-REZTSY' => '',
    'BATYA-SKRUTKA' => '',
    'BATYA-AVTO' => '',
    'VOLNA-YAKOR' => '',
];
