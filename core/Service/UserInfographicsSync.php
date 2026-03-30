<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

use PDO;

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
                $pdo,
                $base,
                $infographicMap,
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
            if (!is_string($fname) || !is_string($key) || $fname === '' || trim($key) === '') {
                continue;
            }
            if (str_starts_with($fname, '_')) {
                continue;
            }
            $out[$fname] = trim($key);
        }

        return $out;
    }

    /**
     * Ищет карточку по sku, offer_id и (для числового Ozon product id из URL) по вхождению в ozon_url.
     *
     * @param array<string, string> $infographicMap
     * @param list<string> $messages
     */
    private static function resolveTargetProductId(
        PDO $pdo,
        string $base,
        array $infographicMap,
        array &$messages,
    ): ?int {
        if (isset($infographicMap[$base])) {
            $k = $infographicMap[$base];
            $id = self::findProductIdByKey($pdo, $k);
            if ($id === null) {
                $messages[] = "skip map (no product for key={$k}): {$base}";

                return null;
            }

            return $id;
        }
        if (preg_match('/^id\.(\d+)_infografika(?:_[^.]+)?\.[a-z0-9]+$/i', $base, $m) === 1) {
            return (int) $m[1];
        }
        if (preg_match('/^ozon\.(\d+)_infografika(?:_[^.]+)?\.[a-z0-9]+$/i', $base, $m) === 1) {
            $ozonId = (string) $m[1];
            $id = self::findProductIdByKey($pdo, $ozonId);
            if ($id === null) {
                $messages[] = "skip (no product for Ozon id={$ozonId}): {$base}";

                return null;
            }

            return $id;
        }
        if (preg_match('/^SKU_(\d+)_infografika(?:_[^.]+)?\.[a-z0-9]+$/i', $base, $m) === 1) {
            $ozonId = (string) $m[1];
            $id = self::findProductIdByKey($pdo, $ozonId);
            if ($id === null) {
                $messages[] = "skip (no product for Ozon id={$ozonId}): {$base}";

                return null;
            }

            return $id;
        }
        $messages[] = "skip (pattern): {$base}";

        return null;
    }

    /**
     * Ключ из имени файла / карты: sku, offer_id или числовой id из URL ozon.ru/product/...-1234567890/.
     */
    private static function findProductIdByKey(PDO $pdo, string $key): ?int
    {
        $sql = 'SELECT id FROM products WHERE sku = :k OR offer_id = :k2';
        $params = [':k' => $key, ':k2' => $key];
        if (preg_match('/^\d{8,}$/', $key) === 1) {
            $sql .= ' OR (trim(ozon_url) != \'\' AND ozon_url LIKE :likepat)';
            $params[':likepat'] = '%' . $key . '%';
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : (int) $row['id'];
    }
}
