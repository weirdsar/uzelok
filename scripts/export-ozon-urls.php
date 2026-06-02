<?php

declare(strict_types=1);

/**
 * Выгрузка всех карточек Ozon из трёх кабинетов (.ozon.env): sku витрины, offer_id, ссылка на витрину.
 * CLI: php8.4 scripts/export-ozon-urls.php
 *        php8.4 scripts/export-ozon-urls.php --markdown   (таблица для ozon.md)
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Uzelok\Core\Service\OzonCatalogApi;
use Uzelok\Core\Service\OzonEnvParser;
use Uzelok\Core\Service\OzonProductAttributes;
use Uzelok\Core\Service\OzonService;

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';
$root = dirname(__DIR__);
$envPath = $root . DIRECTORY_SEPARATOR . '.ozon.env';
$accounts = OzonEnvParser::tryLoadAccounts($envPath);
if ($accounts === []) {
    fwrite(STDERR, "Нет читаемого .ozon.env с тремя кабинетами.\n");
    exit(1);
}

$ozonCfg = is_array($config['ozon'] ?? null) ? $config['ozon'] : [];
$baseUrl = isset($ozonCfg['base_url']) ? (string) $ozonCfg['base_url'] : null;
$catalog = new OzonCatalogApi($baseUrl);
$ozon = new OzonService(
    (string) ($ozonCfg['client_id'] ?? ''),
    (string) ($ozonCfg['api_key'] ?? ''),
    $baseUrl,
);

$markdown = in_array('--markdown', $argv, true);

/** @var array<int, array{brand: string, offer_id: string, title: string, url: string}> */
$byPid = [];

foreach ($accounts as $acc) {
    $clientId = (string) ($acc['client_id'] ?? '');
    $apiKey = (string) ($acc['api_key'] ?? '');
    $brand = (string) ($acc['brand_type'] ?? '');
    if ($clientId === '' || $apiKey === '') {
        continue;
    }

    $offerIds = $catalog->listAllOfferIds($clientId, $apiKey);
    $chunkSize = 100;
    for ($i = 0, $n = count($offerIds); $i < $n; $i += $chunkSize) {
        $chunk = array_slice($offerIds, $i, $chunkSize);
        $items = $catalog->fetchProductInfoList($clientId, $apiKey, $chunk);
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $storefront = OzonProductAttributes::storefrontSkuForCatalog($item);
            if ($storefront < 1) {
                continue;
            }
            if (isset($byPid[$storefront])) {
                continue;
            }
            $offerId = trim((string) ($item['offer_id'] ?? ''));
            $title = (string) ($item['name'] ?? '');
            $url = resolveStorefrontUrl($item, $ozon, $storefront);
            $byPid[$storefront] = [
                'brand' => $brand,
                'offer_id' => $offerId,
                'title' => $title,
                'url' => $url,
            ];
        }
    }
}

ksort($byPid, SORT_NUMERIC);

if ($markdown) {
    $n = 1;
    echo "\n";
    echo "<!-- Сгенерировано: scripts/export-ozon-urls.php --markdown -->\n";
    echo "<table style=\"border-collapse:collapse;width:100%;font-size:13px;\">\n";
    echo "<thead><tr><th style=\"text-align:left;padding:8px;border-bottom:2px solid #ccc;\">№</th>";
    echo "<th style=\"text-align:left;padding:8px;border-bottom:2px solid #ccc;\">Кабинет</th>";
    echo "<th style=\"text-align:left;padding:8px;border-bottom:2px solid #ccc;\">sku витрины</th>";
    echo "<th style=\"text-align:left;padding:8px;border-bottom:2px solid #ccc;\">offer_id</th>";
    echo "<th style=\"text-align:left;padding:8px;border-bottom:2px solid #ccc;\">Название</th>";
    echo "<th style=\"text-align:left;padding:8px;border-bottom:2px solid #ccc;\">Ссылка на карточку Ozon</th></tr></thead>\n<tbody>\n";
    foreach ($byPid as $pid => $row) {
        $brand = htmlspecialchars($row['brand'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $oid = htmlspecialchars($row['offer_id'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = htmlspecialchars($row['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = htmlspecialchars($row['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $href = htmlspecialchars($row['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        echo '<tr style="border-bottom:1px solid #eee;"><td style="padding:6px;vertical-align:top;">' . $n . '</td>';
        echo '<td style="padding:6px;vertical-align:top;">' . $brand . '</td>';
        echo '<td style="padding:6px;vertical-align:top;">' . (string) $pid . '</td>';
        echo '<td style="padding:6px;vertical-align:top;word-break:break-all;">' . $oid . '</td>';
        echo '<td style="padding:6px;vertical-align:top;">' . $title . '</td>';
        echo '<td style="padding:6px;vertical-align:top;word-break:break-all;"><a href="' . $href . '">' . $url . "</a></td></tr>\n";
        ++$n;
    }
    echo "</tbody></table>\n\n";
    echo "<!-- Всего карточек: " . count($byPid) . " -->\n";
} else {
    foreach ($byPid as $pid => $row) {
        echo $pid . "\t" . $row['brand'] . "\t" . $row['offer_id'] . "\t" . $row['url'] . "\t" . $row['title'] . "\n";
    }
    echo '# count: ' . count($byPid) . "\n";
}

/**
 * @param array<string, mixed> $item
 */
function resolveStorefrontUrl(array $item, OzonService $ozon, int $productId): string
{
    foreach (['url', 'ozon_url', 'link'] as $key) {
        $u = $item[$key] ?? null;
        if (is_string($u) && $u !== '' && str_contains($u, 'ozon.ru')) {
            $clean = preg_replace('/[?#].*$/', '', $u);

            return is_string($clean) && $clean !== '' ? $clean : $u;
        }
    }
    $sources = $item['sources'] ?? null;
    if (is_array($sources)) {
        foreach ($sources as $s) {
            if (!is_array($s)) {
                continue;
            }
            $u = $s['url'] ?? $s['link'] ?? null;
            if (is_string($u) && str_contains($u, 'ozon.ru')) {
                $clean = preg_replace('/[?#].*$/', '', $u);

                return is_string($clean) && $clean !== '' ? $clean : $u;
            }
        }
    }

    return $ozon->buildOzonUrl($productId);
}
