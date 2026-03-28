<?php

declare(strict_types=1);

/**
 * Проверка `.ozon.env`: список товаров через API (без входа в веб-ЛК).
 *
 *   php scripts/verify-ozon-env.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Uzelok\Core\Service\OzonCatalogApi;
use Uzelok\Core\Service\OzonEnvParser;

$root = dirname(__DIR__);
$envFile = $root . DIRECTORY_SEPARATOR . '.ozon.env';

$accounts = OzonEnvParser::tryLoadAccounts($envFile);
if ($accounts === []) {
    fwrite(STDERR, "Нет аккаунтов в {$envFile}. Скопируйте ozon.env.example → .ozon.env и заполните ключи (см. комментарии в примере).\n");
    exit(1);
}

$catalog = new OzonCatalogApi();

foreach ($accounts as $acc) {
    $brand = $acc['brand_type'];
    try {
        $offerIds = $catalog->listAllOfferIds($acc['client_id'], $acc['api_key']);
        $n = count($offerIds);
        echo "[OK] brand={$brand}: в каталоге API найдено offer_id: {$n}\n";
        if ($n > 0) {
            $sample = array_slice($offerIds, 0, 3);
            echo "     примеры: " . implode(', ', $sample) . ($n > 3 ? ' …' : '') . "\n";
            $one = $catalog->fetchProductInfoList($acc['client_id'], $acc['api_key'], [ $offerIds[0] ]);
            $okInfo = $one !== [] && isset($one[0]['name']);
            echo '     /v3/product/info/list по первому offer_id: ' . ($okInfo ? 'OK (есть name)' : 'FAIL') . "\n";
        }
    } catch (Throwable $e) {
        echo '[FAIL] brand=' . $brand . ': ' . $e->getMessage() . "\n";
    }
}

echo "\nСинхронизация сайта: php8.4 cron/sync.php (на Beget CLI часто нужен php8.4, не «php»).\n";
