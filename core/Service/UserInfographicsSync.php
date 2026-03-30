<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

use PDO;
use PDOStatement;

/**
 * Копирует user_content/* в public_html/assets/images/user-content/ и пишет products.user_gallery_json.
 * Поле user_gallery_json не перезаписывается Ozon-синком (см. Product::upsertFromOzon).
 */
final class UserInfographicsSync
{
    /**
     * @return array{copied: int, db_updated: int, messages: list<string>}
     */
    public static function syncFromUserContent(
        PDO $pdo,
        string $srcDir,
        string $destDir,
    ): array {
        $messages = [];

        if (!is_dir($srcDir)) {
            return ['copied' => 0, 'db_updated' => 0, 'messages' => ['user_content/: missing']];
        }

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return ['copied' => 0, 'db_updated' => 0, 'messages' => ["cannot create: {$destDir}"]];
        }

        $infographicMap = self::loadMap($srcDir . DIRECTORY_SEPARATOR . 'infographics.map.json');
        if ($infographicMap !== []) {
            $messages[] = 'infographics.map.json: ' . count($infographicMap) . ' override(s)';
        }

        $resolveSku = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');
        $resolveSkuOrOffer = $pdo->prepare('SELECT id FROM products WHERE sku = :k OR offer_id = :k2 LIMIT 1');

        /** @var array<int, list<string>> $productIdToBasenames */
        $productIdToBasenames = [];
        $files = scandir($srcDir);
        if ($files === false) {
            return ['copied' => 0, 'db_updated' => 0, 'messages' => ['cannot read user_content']];
        }

        $copied = 0;
        foreach ($files as $base) {
            if ($base === '.' || $base === '..') {
                continue;
            }
            $full = $srcDir . DIRECTORY_SEPARATOR . $base;
            if (!is_file($full)) {
                continue;
            }
            if ($base === 'infographics.map.json' || str_ends_with(strtolower($base), '.json')) {
                continue;
            }

            $targetProductId = self::resolveTargetProductId(
                $base,
                $infographicMap,
                $resolveSku,
                $resolveSkuOrOffer,
                $messages
            );
            if ($targetProductId === null) {
                continue;
            }

            $dest = $destDir . DIRECTORY_SEPARATOR . $base;
            if (!copy($full, $dest)) {
                $messages[] = "copy failed: {$base}";
                continue;
            }
            ++$copied;

            if (!isset($productIdToBasenames[$targetProductId])) {
                $productIdToBasenames[$targetProductId] = [];
            }
            $productIdToBasenames[$targetProductId][] = $base;
        }

        if ($productIdToBasenames === []) {
            $messages[] = 'no matching infographics';

            return ['copied' => $copied, 'db_updated' => 0, 'messages' => $messages];
        }

        ksort($productIdToBasenames, SORT_NUMERIC);

        $check = $pdo->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
        $upd = $pdo->prepare('UPDATE products SET user_gallery_json = :j, updated_at = datetime(\'now\') WHERE id = :id');

        $dbUpdated = 0;
        foreach ($productIdToBasenames as $pid => $basenames) {
            $basenames = array_values(array_unique($basenames));
            sort($basenames, SORT_STRING);
            $check->execute([':id' => $pid]);
            if ($check->fetchColumn() === false) {
                $messages[] = "warning: no product id={$pid}";
                continue;
            }
            $urls = [];
            foreach ($basenames as $bn) {
                $urls[] = '/assets/images/user-content/' . $bn;
            }
            try {
                $json = json_encode($urls, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (\JsonException $e) {
                $messages[] = 'json id=' . (string) $pid . ': ' . $e->getMessage();
                continue;
            }
            $upd->execute([':j' => $json, ':id' => $pid]);
            ++$dbUpdated;
        }

        return ['copied' => $copied, 'db_updated' => $dbUpdated, 'messages' => $messages];
    }

    /**
     * @return array<string, string>
     */
    private static function loadMap(string $mapPath): array
    {
        if (!is_file($mapPath)) {
            return [];
        }
        $raw = file_get_contents($mapPath);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $fname => $key) {
            if (is_string($fname) && is_string($key) && $fname !== '' && trim($key) !== '') {
                $out[$fname] = trim($key);
            }
        }

        return $out;
    }

    /**
     * @param array<string, string> $infographicMap
     * @param PDOStatement $resolveSku
     * @param PDOStatement $resolveSkuOrOffer
     * @param list<string> $messages
     */
    private static function resolveTargetProductId(
        string $base,
        array $infographicMap,
        PDOStatement $resolveSku,
        PDOStatement $resolveSkuOrOffer,
        array &$messages,
    ): ?int {
        if (isset($infographicMap[$base])) {
            $k = $infographicMap[$base];
            $resolveSkuOrOffer->execute([':k' => $k, ':k2' => $k]);
            $row = $resolveSkuOrOffer->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $messages[] = "skip map (no product sku/offer_id={$k}): {$base}";

                return null;
            }

            return (int) $row['id'];
        }
        if (preg_match('/^id\.(\d+)_infografika(?:_[^.]+)?\.[a-z0-9]+$/i', $base, $m) === 1) {
            return (int) $m[1];
        }
        if (preg_match('/^ozon\.(\d+)_infografika(?:_[^.]+)?\.[a-z0-9]+$/i', $base, $m) === 1) {
            $sku = (string) $m[1];
            $resolveSku->execute([':sku' => $sku]);
            $row = $resolveSku->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $messages[] = "skip (no product sku={$sku}): {$base}";

                return null;
            }

            return (int) $row['id'];
        }
        if (preg_match('/^SKU_(\d+)_infografika(?:_[^.]+)?\.[a-z0-9]+$/i', $base, $m) === 1) {
            $sku = (string) $m[1];
            $resolveSku->execute([':sku' => $sku]);
            $row = $resolveSku->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $messages[] = "skip (no product sku={$sku}): {$base}";

                return null;
            }

            return (int) $row['id'];
        }
        $messages[] = "skip (pattern): {$base}";

        return null;
    }
}
