<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Uzelok\Core\Service\OzonCatalogApi;
use Uzelok\Core\Service\OzonEnvParser;

$config = require dirname(__DIR__) . '/config/config.php';
$ozonCfg = is_array($config['ozon'] ?? null) ? $config['ozon'] : [];
$baseUrl = isset($ozonCfg['base_url']) ? (string) $ozonCfg['base_url'] : null;
$accounts = OzonEnvParser::tryLoadAccounts(dirname(__DIR__) . '/.ozon.env');
if ($accounts === []) {
    fwrite(STDERR, "no .ozon.env\n");
    exit(1);
}

$a = $accounts[0];
$catalog = new OzonCatalogApi($baseUrl);
$ids = $catalog->listAllOfferIds((string) $a['client_id'], (string) $a['api_key']);
if ($ids === []) {
    fwrite(STDERR, "no offers\n");
    exit(1);
}

$items = $catalog->fetchProductInfoList((string) $a['client_id'], (string) $a['api_key'], [reset($ids)]);
$item = $items[0] ?? [];
echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
