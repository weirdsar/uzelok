<?php

declare(strict_types=1);

/**
 * Ручная цена на сайте (рубли целые), если Seller API не отдаёт карточку (чужой id / не в кабинете).
 *
 * CLI: php8.4 scripts/set-product-price-ozon.php 1980935458 590
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

$sku = trim((string) ($argv[1] ?? ''));
$price = isset($argv[2]) ? (int) $argv[2] : -1;
if ($sku === '' || $price < 0) {
    fwrite(STDERR, "Usage: php8.4 scripts/set-product-price-ozon.php <sku> <price_rub>\n");
    exit(1);
}

$pdo = Database::getInstance($dbPath)->getConnection();
$stmt = $pdo->prepare('UPDATE products SET price_ozon = :p, updated_at = datetime(\'now\') WHERE sku = :s');
$stmt->execute([':p' => $price, ':s' => $sku]);
if ($stmt->rowCount() === 0) {
    fwrite(STDERR, "No row with sku={$sku}\n");
    exit(1);
}

echo "OK price_ozon={$price} for sku={$sku}\n";
