<?php

declare(strict_types=1);

namespace Uzelok\Core\Controller;

use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;
use Uzelok\Core\Service\OzonCatalogApi;
use Uzelok\Core\Service\OzonImageDownloader;
use Uzelok\Core\Service\OzonProductAttributes;
use Uzelok\Core\Service\OzonService;

use function Uzelok\Core\logError;
use function Uzelok\Core\logLine;

final class SyncController
{
    /**
     * @param list<string> $skus
     * @param array<string, string> $skuBrandMap offer_id / SKU → brand_type
     * @param list<array{client_id: string, api_key: string, brand_type: string}>|null $ozonAccounts multi-cabinet (from `.ozon.env`); when set, catalog is loaded via API discovery
     */
    public function __construct(
        private readonly OzonService $ozon,
        private readonly Product $product,
        private readonly Database $db,
        private readonly string $logPath,
        private readonly array $skus,
        private readonly array $skuBrandMap,
        private readonly string $triggerType = 'cron',
        private readonly ?array $ozonAccounts = null,
        private readonly ?string $ozonApiBaseUrl = null,
        private readonly ?string $productImagesDirectory = null,
    ) {
    }

    /**
     * @return array{status: string, updated: int, deactivated: int, errors: string}
     */
    public function sync(): array
    {
        $pdo = $this->db->getConnection();
        $syncLogId = null;
        $updated = 0;

        try {
            $this->db->query(
                'INSERT INTO sync_log (started_at, status, trigger_type) VALUES (datetime(\'now\'), :status, :trigger)',
                [
                    ':status' => 'running',
                    ':trigger' => $this->triggerType,
                ]
            );
            $syncLogId = (int) $pdo->lastInsertId();

            logLine('INFO', 'Ozon sync started (' . $this->triggerType . ')', $this->logPath);

            /** @var list<string> $activeSkus */
            $activeSkus = [];
            if ($this->ozonAccounts !== null && $this->ozonAccounts !== []) {
                [$updated, $activeSkus, $accountErrors] = $this->syncFromMultiCabinetApi();
                if ($accountErrors !== '') {
                    logLine('WARNING', 'Ozon multi-cabinet partial errors: ' . $accountErrors, $this->logPath);
                }
            } else {
                $items = $this->ozon->fetchProductList($this->skus);
                $attrsByPid = $this->buildAttributesByProductIdLegacy($items);

                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $sku = (string) ($item['offer_id'] ?? $item['sku'] ?? '');
                    if ($sku === '') {
                        continue;
                    }
                    $activeSkus[] = $sku;
                    $pid = (int) ($item['id'] ?? $item['product_id'] ?? 0);
                    $longDesc = $this->ozon->fetchProductDescription($pid);
                    $imgUrl = OzonProductAttributes::extractPrimaryImageUrl($item);
                    $videoCtx = $this->videoContextsForProductId($attrsByPid, $pid);
                    $upsert = $this->mapItemToUpsert($item, $sku, null, $longDesc, $imgUrl, $videoCtx);
                    $upsert = $this->maybeDownloadProductImage($upsert, $imgUrl);
                    if ($this->product->upsertFromOzon($upsert)) {
                        ++$updated;
                    }
                }
            }

            $skipDeactivateAll = $this->ozonAccounts !== null
                && $this->ozonAccounts !== []
                && $activeSkus === []
                && $updated === 0;
            if ($skipDeactivateAll) {
                logLine(
                    'WARNING',
                    'Ozon multi-cabinet: empty result, skipped deactivate-all safeguard',
                    $this->logPath
                );
            }
            $deactivated = $skipDeactivateAll ? 0 : $this->product->deactivateMissing($activeSkus);

            if ($syncLogId > 0) {
                $this->db->query(
                    'UPDATE sync_log SET finished_at = datetime(\'now\'), status = :status, products_updated = :u, products_deactivated = :d, error_message = :err WHERE id = :id',
                    [
                        ':status' => 'success',
                        ':u' => $updated,
                        ':d' => $deactivated,
                        ':err' => '',
                        ':id' => $syncLogId,
                    ]
                );
            }

            $msg = sprintf('Ozon sync finished: updated=%d, deactivated=%d', $updated, $deactivated);
            logLine('INFO', $msg, $this->logPath);

            return [
                'status' => 'success',
                'updated' => $updated,
                'deactivated' => $deactivated,
                'errors' => '',
            ];
        } catch (\Throwable $e) {
            $err = $e->getMessage();
            logError('Ozon sync failed: ' . $err, $this->logPath);

            if ($syncLogId !== null && $syncLogId > 0) {
                $this->db->query(
                    'UPDATE sync_log SET finished_at = datetime(\'now\'), status = :status, error_message = :err WHERE id = :id',
                    [
                        ':status' => 'error',
                        ':err' => $err,
                        ':id' => $syncLogId,
                    ]
                );
            }

            return [
                'status' => 'error',
                'updated' => $updated,
                'deactivated' => 0,
                'errors' => $err,
            ];
        }
    }

    /**
     * @return array{0: int, 1: list<string>, 2: string} updated, active offer_ids, concatenated errors (non-fatal per cabinet)
     */
    private function syncFromMultiCabinetApi(): array
    {
        $catalog = new OzonCatalogApi($this->ozonApiBaseUrl);
        $updated = 0;
        /** @var list<string> $activeSkus */
        $activeSkus = [];
        $errors = [];

        foreach ($this->ozonAccounts ?? [] as $acc) {
            $clientId = (string) ($acc['client_id'] ?? '');
            $apiKey = (string) ($acc['api_key'] ?? '');
            $brand = (string) ($acc['brand_type'] ?? 'batya');
            if ($clientId === '' || $apiKey === '') {
                continue;
            }

            try {
                $offerIds = $catalog->listAllOfferIds($clientId, $apiKey);
                logLine(
                    'INFO',
                    sprintf('Ozon cabinet brand=%s: discovered %d offer_id', $brand, count($offerIds)),
                    $this->logPath
                );

                $chunkSize = 100;
                for ($i = 0, $n = count($offerIds); $i < $n; $i += $chunkSize) {
                    $chunk = array_slice($offerIds, $i, $chunkSize);
                    $items = $catalog->fetchProductInfoList($clientId, $apiKey, $chunk);
                    $pidsChunk = [];
                    foreach ($items as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $op = (int) ($row['id'] ?? $row['product_id'] ?? 0);
                        if ($op > 0) {
                            $pidsChunk[] = $op;
                        }
                    }
                    $attrsByPid = [];
                    if ($pidsChunk !== []) {
                        try {
                            $attrRows = $catalog->fetchProductInfoAttributes($clientId, $apiKey, $pidsChunk);
                            $attrsByPid = $this->mapAttributeRowsByProductId($attrRows);
                        } catch (\Throwable $e) {
                            logLine(
                                'WARNING',
                                'Ozon product/info/attributes chunk skipped: ' . $e->getMessage(),
                                $this->logPath
                            );
                        }
                    }
                    foreach ($items as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $offerId = trim((string) ($item['offer_id'] ?? $item['sku'] ?? ''));
                        if ($offerId === '') {
                            continue;
                        }
                        $productId = (int) ($item['id'] ?? $item['product_id'] ?? 0);
                        $storageSku = $productId > 0
                            ? (string) $productId
                            : $brand . '|' . $offerId;
                        $activeSkus[] = $storageSku;
                        $longDesc = $productId > 0
                            ? $catalog->fetchProductDescription($clientId, $apiKey, $productId)
                            : '';
                        $imgUrl = OzonProductAttributes::extractPrimaryImageUrl($item);
                        $videoCtx = $this->videoContextsForProductId($attrsByPid, $productId);
                        $upsert = $this->mapItemToUpsert($item, $storageSku, $brand, $longDesc, $imgUrl, $videoCtx);
                        $upsert = $this->maybeDownloadProductImage($upsert, $imgUrl);
                        if ($this->product->upsertFromOzon($upsert)) {
                            ++$updated;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = $brand . ': ' . $e->getMessage();
                logError('Ozon multi-cabinet failed for brand=' . $brand . ': ' . $e->getMessage(), $this->logPath);
            }
        }

        return [$updated, $activeSkus, implode('; ', $errors)];
    }

    /**
     * @param array<string, mixed> $upsert
     * @return array<string, mixed>
     */
    private function maybeDownloadProductImage(array $upsert, string $imageUrl): array
    {
        if ($this->productImagesDirectory === null || $this->productImagesDirectory === '' || $imageUrl === '') {
            return $upsert;
        }

        $sku = (string) ($upsert['sku'] ?? '');
        if ($sku === '') {
            return $upsert;
        }

        $dir = $this->productImagesDirectory;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return $upsert;
        }

        $filename = OzonImageDownloader::safeJpegFilename($sku);
        $full = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $dl = new OzonImageDownloader();
        if ($dl->downloadToFile($imageUrl, $full)) {
            $upsert['image_local_path'] = '/assets/images/products/' . $filename;
        }

        return $upsert;
    }

    /**
     * @param array<string, mixed> $item Ozon API item or mock row
     * @param string $storageSku unique DB key (legacy: offer_id; multi-cabinet: ozon product_id or brand|offer_id)
     * @return array<string, mixed>
     */
    /**
     * @param list<array<string, mixed>> $extraVideoContexts
     */
    private function mapItemToUpsert(
        array $item,
        string $storageSku,
        ?string $fixedBrandType = null,
        string $apiDescription = '',
        ?string $primaryImageOverride = null,
        array $extraVideoContexts = [],
    ): array {
        $offerId = trim((string) ($item['offer_id'] ?? $item['sku'] ?? ''));
        $brandType = $fixedBrandType
            ?? ($this->skuBrandMap[$offerId] ?? $this->guessBrandFromSkuPrefix($offerId));

        $priceOzon = $this->extractPriceOzon($item);
        $ozonUrl = $this->resolveOzonUrl($item, $offerId);
        $sortOrder = isset($item['sort_order']) ? (int) $item['sort_order'] : 0;

        $descSource = $apiDescription !== '' ? $apiDescription : (string) ($item['description'] ?? '');
        $description = OzonProductAttributes::normalizeDescription($descSource);

        $imageOzonUrl = $primaryImageOverride ?? OzonProductAttributes::extractPrimaryImageUrl($item);

        $galleryUrls = OzonProductAttributes::extractGalleryUrls($item);
        try {
            $galleryJson = json_encode($galleryUrls, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            $galleryJson = '[]';
        }

        $videoUrls = OzonProductAttributes::extractVideoUrls($item, $extraVideoContexts);
        try {
            $videosJson = json_encode($videoUrls, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            $videosJson = '[]';
        }

        return [
            'sku' => $storageSku,
            'offer_id' => $offerId,
            'brand_type' => $brandType,
            'title' => (string) ($item['name'] ?? ''),
            'description' => $description,
            'price_ozon' => $priceOzon,
            'ozon_url' => $ozonUrl,
            'image_local_path' => '',
            'image_ozon_url' => $imageOzonUrl,
            'gallery_json' => $galleryJson,
            'videos_json' => $videosJson,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function buildAttributesByProductIdLegacy(array $items): array
    {
        $pids = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $p = (int) ($row['id'] ?? $row['product_id'] ?? 0);
            if ($p > 0) {
                $pids[] = $p;
            }
        }
        if ($pids === []) {
            return [];
        }
        $map = [];
        foreach (array_chunk($pids, 100) as $chunk) {
            try {
                $rows = $this->ozon->fetchProductInfoAttributes($chunk);
            } catch (\Throwable) {
                continue;
            }
            $map += $this->mapAttributeRowsByProductId($rows);
        }

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function mapAttributeRowsByProductId(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pid = (int) ($row['id'] ?? $row['product_id'] ?? 0);
            if ($pid < 1 && isset($row['product_info']) && is_array($row['product_info'])) {
                $pi = $row['product_info'];
                $pid = (int) ($pi['id'] ?? $pi['product_id'] ?? 0);
            }
            if ($pid > 0) {
                $map[$pid] = $row;
            }
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $attrsByPid
     * @return list<array<string, mixed>>
     */
    private function videoContextsForProductId(array $attrsByPid, int $productId): array
    {
        if ($productId < 1 || !isset($attrsByPid[$productId])) {
            return [];
        }

        return [$attrsByPid[$productId]];
    }

    private function guessBrandFromSkuPrefix(string $sku): string
    {
        $prefix = strtoupper(explode('-', $sku)[0] ?? '');

        return match ($prefix) {
            'BUY' => 'buy',
            'BATYA' => 'batya',
            'VOLNA' => 'volna',
            default => 'batya',
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveOzonUrl(array $item, string $offerIdForFallback): string
    {
        $direct = $item['ozon_url'] ?? null;
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        $id = $item['id'] ?? $item['product_id'] ?? null;
        if ($id !== null && $id !== '') {
            return $this->ozon->buildOzonUrl((int) $id);
        }

        return $this->ozon->buildOzonUrl($offerIdForFallback);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractPriceOzon(array $item): int
    {
        $raw = $item['marketing_seller_price']
            ?? $item['marketing_price']
            ?? $item['price']
            ?? $item['old_price']
            ?? 0;
        if (is_array($raw)) {
            $raw = $raw['price'] ?? $raw['value'] ?? $raw['marketing_seller_price'] ?? 0;
        }
        $s = preg_replace('/[^\d.]/', '', (string) $raw) ?? '0';

        return (int) round((float) $s);
    }
}
