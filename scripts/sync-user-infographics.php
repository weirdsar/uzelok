<?php

declare(strict_types=1);

/**
 * Копирует файлы из user_content/ в public_html/assets/images/user-content/
 * и записывает пути в products.user_gallery_json (не затирается синком Ozon).
 *
 * Имена файлов (один товар — несколько файлов, порядок = сортировка по имени):
 *   id.{internal_db_id}_infografika.ext — по products.id (ломается после пересборки БД / смене id)
 *   id.{id}_infografika_01.ext … — порядок по имени файла
 *   ozon.{ozon_product_id}_infografika.ext — по products.sku (= Ozon product_id), предпочтительно
 *   ozon.{id}_infografika_01.ext … — то же + порядок слайдов
 *   SKU_{ozon_product_id}_infografika.ext — число из ссылки Ozon: .../product/...-{id}/ (тот же id в имени файла).
 *
 * Сопоставление с БД: sku, offer_id или вхождение числового id в ozon_url (если в sku хранится не product_id).
 *
 * Переопределение (если id.* «уехали» на другие карточки): user_content/infographics.map.json
 *   { "id.25_infografika.png": "1549149172" }  — значение = products.sku или products.offer_id
 *   Запись в карте имеет приоритет над разбором id.N из имени файла.
 *
 * CLI: php8.4 scripts/sync-user-infographics.php
 *        php8.4 scripts/sync-user-infographics.php --strict   (только SKU_* / ozon.* = products.sku)
 * После деплоя также вызывается из cron/sync.php и admin sync (после Ozon).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Uzelok\Core\Database;
use Uzelok\Core\Service\UserInfographicsSync;

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';

$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    fwrite(STDERR, "Invalid database path.\n");
    exit(1);
}

$root = dirname(__DIR__);
$srcDir = $root . DIRECTORY_SEPARATOR . 'user_content';
$public = isset($config['paths']['public']) && is_string($config['paths']['public'])
    ? $config['paths']['public']
    : $root . DIRECTORY_SEPARATOR . 'public_html';
$destDir = $public . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'user-content';

$pdo = Database::getInstance($dbPath)->getConnection();
$strict = in_array('--strict', $argv, true);
$result = UserInfographicsSync::syncFromUserContent($pdo, $srcDir, $destDir, $strict);

foreach ($result['messages'] as $m) {
    echo $m . "\n";
}
echo sprintf("copied=%d, database products updated=%d\n", $result['copied'], $result['db_updated']);

exit(0);
