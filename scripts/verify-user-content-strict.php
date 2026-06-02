<?php

declare(strict_types=1);

/**
 * Проверка: user_content vs активные products.sku (strict), плюс user_gallery_json.
 * CLI: php8.4 scripts/verify-user-content-strict.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Uzelok\Core\Database;
use Uzelok\Core\Service\UserContentMismatchReport;

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';

$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    fwrite(STDERR, "Invalid database path.\n");
    exit(1);
}

$root = dirname(__DIR__);
$srcDir = $root . DIRECTORY_SEPARATOR . 'user_content';

$pdo = Database::getInstance($dbPath)->getConnection();

$report = UserContentMismatchReport::analyze($pdo, $srcDir, true);
echo UserContentMismatchReport::formatText($report);

$lines = [];
$lines[] = '';
$lines[] = '--- user_gallery_json (ожидается один путь на SKU_* для активного sku) ---';

$stmt = $pdo->query(
    'SELECT id, sku, user_gallery_json FROM products WHERE is_active = 1 ORDER BY id'
);
$rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
$galleryIssues = 0;

foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    $sku = trim((string) ($row['sku'] ?? ''));
    $j = trim((string) ($row['user_gallery_json'] ?? ''));
    if ($sku === '' || !ctype_digit($sku)) {
        $lines[] = "id={$id}: sku пустой/не число — пропуск";
        ++$galleryIssues;

        continue;
    }

    $glob = $srcDir . DIRECTORY_SEPARATOR . 'SKU_' . $sku . '_infografika.*';
    $files = glob($glob);
    if ($files === false || $files === []) {
        $lines[] = "id={$id} sku={$sku}: нет файла SKU_{$sku}_infografika.* в user_content";
        ++$galleryIssues;

        continue;
    }
    sort($files, SORT_STRING);
    $expectedBases = array_map(static fn (string $p): string => basename($p), $files);
    $expectedPaths = array_map(
        static fn (string $b): string => '/assets/images/user-content/' . $b,
        $expectedBases
    );

    if ($j === '') {
        $lines[] = 'id=' . $id . ' sku=' . $sku . ': user_gallery_json пусто (ожидалось ' . implode(', ', $expectedPaths) . ')';
        ++$galleryIssues;

        continue;
    }

    try {
        $decoded = json_decode($j, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        $lines[] = "id={$id} sku={$sku}: JSON ошибка: " . $e->getMessage();
        ++$galleryIssues;

        continue;
    }

    if (!is_array($decoded) || $decoded === []) {
        $lines[] = "id={$id} sku={$sku}: user_gallery_json не массив URL";
        ++$galleryIssues;

        continue;
    }

    $urls = array_values(array_filter($decoded, static fn ($u) => is_string($u) && $u !== ''));
    $missing = [];
    foreach ($expectedPaths as $expectedPath) {
        $base = basename($expectedPath);
        $found = false;
        foreach ($urls as $u) {
            if ($u === $expectedPath || str_ends_with($u, '/' . $base)) {
                $found = true;

                break;
            }
        }
        if (!$found) {
            $missing[] = $expectedPath;
        }
    }
    if ($missing !== []) {
        $lines[] = 'id=' . $id . ' sku=' . $sku . ': в галерее нет ' . implode(', ', $missing) . ', есть: ' . json_encode($urls, JSON_UNESCAPED_UNICODE);
        ++$galleryIssues;

        continue;
    }

    $lines[] = 'id=' . $id . ' sku=' . $sku . ': OK (' . implode(', ', $expectedBases) . ')';
}

echo implode("\n", $lines) . "\n";
echo "\nИтого проблем в галерее: {$galleryIssues}\n";

exit($galleryIssues > 0 ? 1 : 0);
