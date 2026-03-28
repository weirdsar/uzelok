<?php

declare(strict_types=1);

/**
 * Копирует файлы из user_content/ в public_html/assets/images/user-content/
 * и записывает пути в products.user_gallery_json (не затирается синком Ozon).
 *
 * Имена файлов (один товар — несколько файлов, порядок = сортировка по имени):
 *   id.{internal_db_id}_infografika.ext — по products.id
 *   id.{id}_infografika_01.ext … — порядок по имени файла
 *   ozon.{ozon_product_id}_infografika.ext — по products.sku (= Ozon product_id)
 *   ozon.{id}_infografika_01.ext … — то же + порядок слайдов
 *
 * CLI: php8.4 scripts/sync-user-infographics.php
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

$root = dirname(__DIR__);
$srcDir = $root . DIRECTORY_SEPARATOR . 'user_content';
$destDir = $root . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'user-content';

if (!is_dir($srcDir)) {
    echo "user_content/: directory missing, nothing to do.\n";
    exit(0);
}

if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
    fwrite(STDERR, "Cannot create: {$destDir}\n");
    exit(1);
}

/** @var array<int, list<string>> $productIdToBasenames */
$productIdToBasenames = [];

$files = scandir($srcDir);
if ($files === false) {
    fwrite(STDERR, "Cannot read user_content.\n");
    exit(1);
}

$pdo = Database::getInstance($dbPath)->getConnection();
$resolveSku = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');

foreach ($files as $base) {
    if ($base === '.' || $base === '..') {
        continue;
    }
    $full = $srcDir . DIRECTORY_SEPARATOR . $base;
    if (!is_file($full)) {
        continue;
    }

    $targetProductId = null;
    if (preg_match('/^id\.(\d+)_infografika(?:_[^.]+)?\.[a-z0-9]+$/i', $base, $m) === 1) {
        $targetProductId = (int) $m[1];
    } elseif (preg_match('/^ozon\.(\d+)_infografika(?:_[^.]+)?\.[a-z0-9]+$/i', $base, $m) === 1) {
        $sku = (string) $m[1];
        $resolveSku->execute([':sku' => $sku]);
        $row = $resolveSku->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            echo "skip (no product sku={$sku}): {$base}\n";
            continue;
        }
        $targetProductId = (int) $row['id'];
    } else {
        echo "skip (pattern): {$base}\n";
        continue;
    }

    $dest = $destDir . DIRECTORY_SEPARATOR . $base;
    if (!copy($full, $dest)) {
        fwrite(STDERR, "copy failed: {$base}\n");
        continue;
    }
    echo "copied {$base} -> user-content/\n";

    if (!isset($productIdToBasenames[$targetProductId])) {
        $productIdToBasenames[$targetProductId] = [];
    }
    $productIdToBasenames[$targetProductId][] = $base;
}

if ($productIdToBasenames === []) {
    echo "No matching id.* / ozon.* infographics in user_content/.\n";
    exit(0);
}

ksort($productIdToBasenames, SORT_NUMERIC);

$check = $pdo->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
$upd = $pdo->prepare('UPDATE products SET user_gallery_json = :j, updated_at = datetime(\'now\') WHERE id = :id');

$updated = 0;
foreach ($productIdToBasenames as $pid => $basenames) {
    $basenames = array_values(array_unique($basenames));
    sort($basenames, SORT_STRING);
    $check->execute([':id' => $pid]);
    if ($check->fetchColumn() === false) {
        echo "warning: no product id={$pid}, skipped DB update\n";
        continue;
    }
    $urls = [];
    foreach ($basenames as $bn) {
        $urls[] = '/assets/images/user-content/' . $bn;
    }
    try {
        $json = json_encode($urls, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (\JsonException $e) {
        fwrite(STDERR, "json id={$pid}: " . $e->getMessage() . "\n");
        continue;
    }
    $upd->execute([':j' => $json, ':id' => $pid]);
    ++$updated;
}

echo "Database updated: {$updated} product(s).\n";
