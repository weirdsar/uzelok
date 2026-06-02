<?php

declare(strict_types=1);

/**
 * CLI: вывод строк для таблицы ozon.md по активным товарам в SQLite.
 * Запуск на сервере: php8.4 scripts/dump-products-for-ozon-md.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';
$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    fwrite(STDERR, "Invalid database path.\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$rows = $pdo->query(
    'SELECT id, sku, ozon_url, offer_id FROM products WHERE is_active = 1 ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);

/** @var array<string, string> $infographicByDbSku target sku в БД → имя файла */
$infographicByDbSku = [];
$mapFile = dirname(__DIR__) . '/user_content/infographics.map.json';
if (is_readable($mapFile)) {
    $decoded = json_decode((string) file_get_contents($mapFile), true);
    if (is_array($decoded)) {
        foreach ($decoded as $fname => $targetSku) {
            if ($fname === '_readme' || !is_string($targetSku)) {
                continue;
            }
            $infographicByDbSku[$targetSku] = $fname;
        }
    }
}

$n = 1;
foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    $sku = trim((string) ($row['sku'] ?? ''));
    $ozonUrl = trim((string) ($row['ozon_url'] ?? ''));
    $siteUrl = 'https://uzelok64.ru/?page=product&id=' . $id;
    $fromSite = $sku !== '' && ctype_digit($sku) && strlen($sku) >= 8
        ? 'https://www.ozon.ru/product/' . $sku . '/'
        : '—';
    $ozonCell = $ozonUrl !== '' ? $ozonUrl : 'https://www.ozon.ru/product/' . $sku . '/';
    $info = $infographicByDbSku[$sku] ?? ('— (шаблон: SKU_' . $sku . '_infografika.*)');
    echo '| ' . $n . ' | ' . $ozonCell . ' | ' . $sku . ' | ' . $siteUrl . ' | ' . $sku . ' | ' . $fromSite . ' | ' . $info . " |\n";
    ++$n;
}

echo "\n# count: " . count($rows) . "\n";
