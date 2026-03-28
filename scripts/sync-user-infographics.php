<?php

declare(strict_types=1);

/**
 * Копирует файлы из user_content/ в public_html/assets/images/user-content/
 * и записывает пути в products.user_gallery_json по шаблону имени: id.{product_id}_infografika.ext
 *
 * CLI: php scripts/sync-user-infographics.php
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

/** @var array<int, list<string>> $idToDestBasenames */
$idToDestBasenames = [];
$files = scandir($srcDir);
if ($files === false) {
    fwrite(STDERR, "Cannot read user_content.\n");
    exit(1);
}

foreach ($files as $base) {
    if ($base === '.' || $base === '..') {
        continue;
    }
    $full = $srcDir . DIRECTORY_SEPARATOR . $base;
    if (!is_file($full)) {
        continue;
    }
    if (preg_match('/^id\.(\d+)_infografika\.[a-z0-9]+$/i', $base, $m) !== 1) {
        echo "skip (pattern): {$base}\n";
        continue;
    }
    $pid = (int) $m[1];
    $dest = $destDir . DIRECTORY_SEPARATOR . $base;
    if (!copy($full, $dest)) {
        fwrite(STDERR, "copy failed: {$base}\n");
        continue;
    }
    $idToDestBasenames[$pid][] = $base;
    echo "copied {$base} -> user-content/\n";
}

if ($idToDestBasenames === []) {
    echo "No matching id.NNN_infografika.* files in user_content/.\n";
    exit(0);
}

ksort($idToDestBasenames, SORT_NUMERIC);

$pdo = Database::getInstance($dbPath)->getConnection();
$check = $pdo->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
$upd = $pdo->prepare('UPDATE products SET user_gallery_json = :j, updated_at = datetime(\'now\') WHERE id = :id');

$updated = 0;
foreach ($idToDestBasenames as $pid => $basenames) {
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
