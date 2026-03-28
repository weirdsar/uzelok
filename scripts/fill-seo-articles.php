<?php

declare(strict_types=1);

/**
 * CLI: заполняет products.seo_article для всех активных товаров (идемпотентно перезаписывает).
 * Запуск: php scripts/fill-seo-articles.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Uzelok\Core\Database;
use Uzelok\Core\Service\ProductSeoArticleGenerator;

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';

$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    fwrite(STDERR, "Invalid database path.\n");
    exit(1);
}

$pdo = Database::getInstance($dbPath)->getConnection();
$stmt = $pdo->query('SELECT * FROM products WHERE is_active = 1 ORDER BY id ASC');
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
if ($rows === false) {
    $rows = [];
}

$upd = $pdo->prepare('UPDATE products SET seo_article = :a, updated_at = datetime(\'now\') WHERE id = :id');

$n = 0;
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    /** @var array<string, mixed> $row */
    $text = ProductSeoArticleGenerator::generate($row);
    $upd->execute([':a' => $text, ':id' => (int) ($row['id'] ?? 0)]);
    ++$n;
}

echo "SEO articles written: {$n}" . PHP_EOL;
