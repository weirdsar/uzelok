<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

/**
 * Low-level Ozon Seller HTTP calls for catalog discovery (per-cabinet credentials).
 */
final class OzonCatalogApi
{
    private readonly string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? 'https://api-seller.ozon.ru', '/');
    }

    /**
     * @return list<string> offer_id values (seller article), non-empty only
     */
    public function listAllOfferIds(string $clientId, string $apiKey): array
    {
        $seen = [];
        $lastId = '';
        $page = 0;
        $maxPages = 500;

        do {
            ++$page;
            if ($page > $maxPages) {
                break;
            }

            $body = [
                'filter' => new \stdClass(),
                'last_id' => $lastId,
                'limit' => 1000,
            ];

            $decoded = $this->post($clientId, $apiKey, '/v3/product/list', $body);
            $items = $decoded['result']['items'] ?? [];
            if (!is_array($items) || $items === []) {
                break;
            }

            $nextLast = (string) ($decoded['result']['last_id'] ?? '');
            foreach ($items as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $oid = isset($row['offer_id']) ? trim((string) $row['offer_id']) : '';
                if ($oid !== '') {
                    $seen[$oid] = true;
                }
            }

            if ($nextLast === '' || $nextLast === $lastId) {
                break;
            }
            $lastId = $nextLast;
        } while (true);

        return array_keys($seen);
    }

    /**
     * @param list<string> $offerIds
     * @return list<array<string, mixed>>
     */
    public function fetchProductInfoList(string $clientId, string $apiKey, array $offerIds): array
    {
        if ($offerIds === []) {
            return [];
        }

        $decoded = $this->post($clientId, $apiKey, '/v3/product/info/list', [
            'offer_id' => array_values($offerIds),
        ]);

        $items = $decoded['result']['items'] ?? $decoded['items'] ?? null;

        return is_array($items) ? $items : [];
    }

    /**
     * Rich attributes per product (may include video URLs not present in /v3/product/info/list).
     *
     * @param list<int> $productIds Ozon product_id values
     * @return list<array<string, mixed>>
     */
    public function fetchProductInfoAttributes(string $clientId, string $apiKey, array $productIds): array
    {
        $seen = [];
        foreach ($productIds as $pid) {
            $p = (int) $pid;
            if ($p > 0) {
                $seen[$p] = true;
            }
        }
        $idList = array_map(static fn (int $id): string => (string) $id, array_keys($seen));
        if ($idList === []) {
            return [];
        }

        try {
            $decoded = $this->post($clientId, $apiKey, '/v3/product/info/attributes', [
                'filter' => [
                    'product_id' => $idList,
                    'visibility' => 'ALL',
                ],
                'limit' => min(1000, max(count($idList), 1)),
                'sort_dir' => 'ASC',
            ], 90);
        } catch (\Throwable) {
            return [];
        }

        $result = $decoded['result'] ?? null;
        if (is_array($result) && isset($result['items']) && is_array($result['items'])) {
            /** @var list<array<string, mixed>> */
            return $result['items'];
        }
        if (is_array($result) && array_is_list($result)) {
            /** @var list<array<string, mixed>> */
            return $result;
        }

        return [];
    }

    /**
     * Full card description (not included in /v3/product/info/list).
     */
    public function fetchProductDescription(string $clientId, string $apiKey, int $productId): string
    {
        if ($productId < 1) {
            return '';
        }

        try {
            $decoded = $this->post($clientId, $apiKey, '/v1/product/info/description', [
                'product_id' => (string) $productId,
            ], 45);
            $r = $decoded['result'] ?? null;

            return is_array($r) ? (string) ($r['description'] ?? '') : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function post(string $clientId, string $apiKey, string $endpoint, array $body, int $timeoutSeconds = 60): array
    {
        $url = $this->baseUrl . $endpoint;
        $payload = json_encode($body, JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Client-Id: ' . $clientId,
                'Api-Key: ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new \RuntimeException('Ozon curl error: ' . (string) $errno);
        }

        if (!is_string($response)) {
            throw new \RuntimeException('Ozon empty response');
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('Ozon HTTP ' . $httpCode . ': ' . substr($response, 0, 500));
        }

        try {
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Ozon invalid JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Ozon response is not an object');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
