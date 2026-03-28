<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';
$dbPath = $config['paths']['database'] ?? '';
if (!is_string($dbPath) || $dbPath === '' || !is_readable($dbPath)) {
    fwrite(STDERR, "DB missing: {$dbPath}\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rows = $pdo->query(
    'SELECT id, title, gallery_json, videos_json FROM products WHERE is_active = 1 ORDER BY id LIMIT 15'
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $g = json_decode((string) ($row['gallery_json'] ?? ''), true);
    $v = json_decode((string) ($row['videos_json'] ?? ''), true);
    $gc = is_array($g) ? count($g) : 0;
    $vc = is_array($v) ? count($v) : 0;
    $title = mb_substr((string) ($row['title'] ?? ''), 0, 50);
    echo sprintf("id=%d  images=%d  videos=%d  %s\n", (int) $row['id'], $gc, $vc, $title);
}
