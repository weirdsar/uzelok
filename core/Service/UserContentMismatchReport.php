<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

use PDO;

/**
 * Сравнение user_content с products после синка (несостыковки для отчёта).
 */
final class UserContentMismatchReport
{
    /**
     * @return array{
     *   files_no_product: list<string>,
     *   active_skus_no_file: list<string>,
     *   pattern_skipped: list<string>,
     *   active_count: int,
     *   files_scanned: int
     * }
     */
    public static function analyze(PDO $pdo, string $srcDir, bool $strictSkuOnly): array
    {
        $filesNoProduct = [];
        $patternSkipped = [];
        $activeSkusNoFile = [];
        $filesScanned = 0;

        if (!is_dir($srcDir)) {
            return [
                'files_no_product' => [],
                'active_skus_no_file' => [],
                'pattern_skipped' => [],
                'active_count' => 0,
                'files_scanned' => 0,
            ];
        }

        $files = scandir($srcDir);
        if ($files === false) {
            return [
                'files_no_product' => [],
                'active_skus_no_file' => [],
                'pattern_skipped' => [],
                'active_count' => 0,
                'files_scanned' => 0,
            ];
        }

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
            ++$filesScanned;

            $skuFromFile = self::extractSkuFromFilename($base);
            if ($skuFromFile === null) {
                $patternSkipped[] = $base;
                continue;
            }

            if ($strictSkuOnly) {
                $stmt = $pdo->prepare('SELECT 1 FROM products WHERE sku = :s AND is_active = 1 LIMIT 1');
                $stmt->execute([':s' => $skuFromFile]);
                if ($stmt->fetchColumn() === false) {
                    $filesNoProduct[] = $base . ' (sku=' . $skuFromFile . ')';
                }
            } else {
                $stmt = $pdo->prepare(
                    'SELECT 1 FROM products WHERE (sku = :s OR offer_id = :s2) AND is_active = 1 LIMIT 1'
                );
                $stmt->execute([':s' => $skuFromFile, ':s2' => $skuFromFile]);
                if ($stmt->fetchColumn() === false) {
                    $stmt2 = $pdo->prepare(
                        'SELECT 1 FROM products WHERE is_active = 1 AND trim(ozon_url) != \'\' AND ozon_url LIKE :like LIMIT 1'
                    );
                    $stmt2->execute([':like' => '%' . $skuFromFile . '%']);
                    if ($stmt2->fetchColumn() === false) {
                        $filesNoProduct[] = $base . ' (key=' . $skuFromFile . ')';
                    }
                }
            }
        }

        $activeRows = $pdo->query(
            "SELECT sku FROM products WHERE is_active = 1 AND sku IS NOT NULL AND sku != ''"
        );
        if ($activeRows !== false) {
            foreach ($activeRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($sku === '' || !ctype_digit($sku)) {
                    continue;
                }
                $globSku = $srcDir . DIRECTORY_SEPARATOR . 'SKU_' . $sku . '_infografika.*';
                $matches = glob($globSku);
                if ($matches === false || $matches === []) {
                    $globOzon = $srcDir . DIRECTORY_SEPARATOR . 'ozon.' . $sku . '_infografika.*';
                    $matches = glob($globOzon);
                }
                if ($matches === false || $matches === []) {
                    $activeSkusNoFile[] = $sku;
                }
            }
        }

        $cnt = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1');
        $activeCount = $cnt !== false ? (int) $cnt->fetchColumn() : 0;

        return [
            'files_no_product' => $filesNoProduct,
            'active_skus_no_file' => $activeSkusNoFile,
            'pattern_skipped' => $patternSkipped,
            'active_count' => $activeCount,
            'files_scanned' => $filesScanned,
        ];
    }

    public static function formatText(array $report): string
    {
        $lines = [];
        $lines[] = '--- Отчёт user_content vs каталог ---';
        $lines[] = 'Активных карточек в БД: ' . (string) ($report['active_count'] ?? 0);
        $lines[] = 'Файлов в user_content (кроме .json): ' . (string) ($report['files_scanned'] ?? 0);
        $lines[] = '';
        $lines[] = 'Файлы без активной карточки (по правилам отчёта):';
        foreach ($report['files_no_product'] ?? [] as $x) {
            $lines[] = '  - ' . $x;
        }
        if (($report['files_no_product'] ?? []) === []) {
            $lines[] = '  (нет)';
        }
        $lines[] = '';
        $lines[] = 'Активные числовые sku без локального SKU_* / ozon.* файла:';
        foreach ($report['active_skus_no_file'] ?? [] as $x) {
            $lines[] = '  - ' . $x;
        }
        if (($report['active_skus_no_file'] ?? []) === []) {
            $lines[] = '  (нет)';
        }
        $lines[] = '';
        $lines[] = 'Файлы с нераспознанным шаблоном имени:';
        foreach ($report['pattern_skipped'] ?? [] as $x) {
            $lines[] = '  - ' . $x;
        }
        if (($report['pattern_skipped'] ?? []) === []) {
            $lines[] = '  (нет)';
        }

        return implode("\n", $lines) . "\n";
    }

    private static function extractSkuFromFilename(string $base): ?string
    {
        if (preg_match('/^SKU_(\d+)_infografika(?:_[^.]+)?\.[a-z0-9]+$/i', $base, $m) === 1) {
            return (string) $m[1];
        }
        if (preg_match('/^ozon\.(\d+)_infografika(?:_[^.]+)?\.[a-z0-9]+$/i', $base, $m) === 1) {
            return (string) $m[1];
        }

        return null;
    }
}
