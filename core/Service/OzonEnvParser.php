<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

/**
 * Reads multi-cabinet Ozon API credentials from project root `.ozon.env`.
 *
 * Expected lines (example):
 *   БУЙ 527927  <api-key-uuid>
 *   ВОЛНА 2250971  <api-key-uuid>
 *   БАТЯ 2103460  <api-key-uuid>
 */
final class OzonEnvParser
{
    /**
     * @return list<array{client_id: string, api_key: string, brand_type: string}>
     */
    public static function tryLoadAccounts(string $envFilePath): array
    {
        if (!is_readable($envFilePath)) {
            return [];
        }

        $content = file_get_contents($envFilePath);
        if ($content === false) {
            return [];
        }

        $out = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, 'Client-ID') && str_contains($line, 'API-Key')) {
                continue;
            }
            if (!preg_match(
                '/^(.+?)\s+(\d{4,})\s+([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\s*$/iu',
                $line,
                $m
            )) {
                continue;
            }

            $label = trim($m[1]);
            $brand = self::labelToBrandType($label);
            if ($brand === null) {
                continue;
            }

            $out[] = [
                'client_id' => $m[2],
                'api_key' => $m[3],
                'brand_type' => $brand,
            ];
        }

        return $out;
    }

    private static function labelToBrandType(string $label): ?string
    {
        $n = mb_strtolower(trim($label), 'UTF-8');

        return match (true) {
            $n === 'буй' || $n === 'buy' => 'buy',
            $n === 'батя' || $n === 'batya' => 'batya',
            $n === 'волна' || $n === 'volna' => 'volna',
            default => null,
        };
    }
}
