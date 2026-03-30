<?php

declare(strict_types=1);

/**
 * Карточки с Ozon product id, которых ещё нет в выгрузке API, но нужны для user_content/SKU_*.
 * preserve_sync=1 — не снимать с публикации в deactivateMissing при синке Ozon.
 *
 * CLI: php8.4 database/init.php && php8.4 scripts/seed-missing-infographic-products.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Uzelok\Core\Database;

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';
$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    fwrite(STDERR, "Invalid database path.\n");
    exit(1);
}

$pdo = Database::getInstance($dbPath)->getConnection();

$rows = [
    [
        'sku' => '1980935458',
        'ozon_url' => 'https://www.ozon.ru/product/meshok-dlya-hraneniya-30-sm-h-20-sm-1980935458/',
        'title' => 'Мешок для хранения 30 см × 20 см',
    ],
    [
        'sku' => '3773752744',
        'ozon_url' => 'https://www.ozon.ru/product/sumka-svarshchika-3773752744/',
        'title' => 'Сумка сварщика',
    ],
];

$ins = $pdo->prepare(
    'INSERT INTO products (brand_type, sku, offer_id, title, price_ozon, ozon_url, is_active, sort_order, preserve_sync)
     VALUES (\'batya\', :sku, \'\', :title, 0, :ozon_url, 1, 0, 1)'
);
$upd = $pdo->prepare(
    'UPDATE products SET preserve_sync = 1, ozon_url = :ozon_url, title = :title WHERE sku = :sku'
);

foreach ($rows as $r) {
    try {
        $ins->execute([':sku' => $r['sku'], ':title' => $r['title'], ':ozon_url' => $r['ozon_url']]);
        echo "inserted sku={$r['sku']}\n";
    } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE') || $e->getCode() === '23000') {
            $upd->execute([':sku' => $r['sku'], ':title' => $r['title'], ':ozon_url' => $r['ozon_url']]);
            echo "updated sku={$r['sku']}\n";
        } else {
            throw $e;
        }
    }
}
