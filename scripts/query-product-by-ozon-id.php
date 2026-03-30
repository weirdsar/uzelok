<?php

declare(strict_types=1);

/**
 * CLI: php8.4 scripts/query-product-by-ozon-id.php 1980935458 3773752744
 */

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';
$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    fwrite(STDERR, "Invalid database path.\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

foreach (array_slice($argv, 1) as $id) {
    $id = trim($id);
    if ($id === '') {
        continue;
    }
    $q = $pdo->prepare(
        'SELECT id, sku, offer_id, ozon_url FROM products WHERE sku = ? OR offer_id = ? OR (trim(ozon_url) != \'\' AND ozon_url LIKE ?)'
    );
    $q->execute([$id, $id, '%' . $id . '%']);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    echo $id . ' => ' . json_encode($rows, JSON_UNESCAPED_UNICODE) . "\n";
}
