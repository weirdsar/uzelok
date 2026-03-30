<?php

declare(strict_types=1);

/**
 * Полная пересборка каталога:
 * 1) очистка products (заявки: product_id обнуляется)
 * 2) синхронизация всех кабинетов из .ozon.env + картинки Ozon
 * 3) инфографика из user_content в strict-режиме (номер в имени = products.sku)
 * 4) отчёт несостыковок в stdout и logs/rebuild-catalog-report.txt
 *
 * CLI: php8.4 scripts/rebuild-catalog-from-ozon.php --yes
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;
use Uzelok\Core\Service\UserContentMismatchReport;
use Uzelok\Core\Service\UserInfographicsSync;
use Uzelok\Core\SyncBootstrap;

if (!in_array('--yes', $argv, true)) {
    fwrite(STDERR, "Подтвердите полную очистку каталога: добавьте аргумент --yes\n");
    exit(1);
}

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';
$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    fwrite(STDERR, "Invalid database path.\n");
    exit(1);
}

$root = dirname(__DIR__);
$public = isset($config['paths']['public']) && is_string($config['paths']['public'])
    ? $config['paths']['public']
    : $root . DIRECTORY_SEPARATOR . 'public_html';
$srcDir = $root . DIRECTORY_SEPARATOR . 'user_content';
$destDir = $public . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'user-content';
$logsPath = (string) ($config['paths']['logs'] ?? $root . '/logs');
$logFile = $logsPath . '/sync.log';
$reportFile = $logsPath . '/rebuild-catalog-report.txt';

$db = Database::getInstance($dbPath);
$pdo = $db->getConnection();
$product = new Product($db);

echo "1) Очистка products...\n";
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('UPDATE orders SET product_id = NULL');
$pdo->exec('DELETE FROM products');
try {
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'products'");
} catch (\Throwable) {
}

echo "2) Синхронизация Ozon (3 кабинета + локальные фото)...\n";
try {
    $controller = SyncBootstrap::createSyncController($config, $product, $db, $logFile, 'rebuild');
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$syncResult = $controller->sync();
echo sprintf(
    "   status=%s updated=%d deactivated=%d active_products=%s\n",
    (string) ($syncResult['status'] ?? ''),
    (int) ($syncResult['updated'] ?? 0),
    (int) ($syncResult['deactivated'] ?? 0),
    (string) ($syncResult['active_products'] ?? '?')
);

if (($syncResult['status'] ?? '') !== 'success') {
    fwrite(STDERR, 'Sync failed: ' . (string) ($syncResult['errors'] ?? '') . "\n");
    exit(1);
}

echo "3) Инфографика user_content (strict: номер в SKU_* / ozon.* = products.sku)...\n";
$inf = UserInfographicsSync::syncFromUserContent($pdo, $srcDir, $destDir, true);
foreach ($inf['messages'] as $m) {
    echo '   ' . $m . "\n";
}
echo sprintf("   copied=%d db_updated=%d\n", $inf['copied'], $inf['db_updated']);

echo "4) Отчёт несостыковок...\n";
$report = UserContentMismatchReport::analyze($pdo, $srcDir, true);
$text = UserContentMismatchReport::formatText($report);
echo $text;

if (!is_dir($logsPath) && !mkdir($logsPath, 0755, true) && !is_dir($logsPath)) {
    fwrite(STDERR, "Cannot create logs dir.\n");
} else {
    file_put_contents($reportFile, $text);
    echo "Отчёт записан: {$reportFile}\n";
}

$ozonCount = (int) ($syncResult['active_products'] ?? 0);
$dbCount = (int) ($report['active_count'] ?? 0);
if ($ozonCount === $dbCount) {
    echo "OK: активных карточек на сайте: {$dbCount} (совпадает с итогом синка).\n";
} else {
    fwrite(STDERR, "Внимание: расхождение active_products синка ({$ozonCount}) и отчёта ({$dbCount}).\n");
}

exit(0);
