<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

final class OzonService
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly string $clientId,
        private readonly string $apiKey,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = $baseUrl ?? 'https://api-seller.ozon.ru';
    }

    /**
     * @param list<string> $skus
     * @return list<array<string, mixed>>
     */
    public function fetchProductList(array $skus): array
    {
        if ($this->usesPlaceholderCredentials()) {
            return $this->getMockData();
        }

        $decoded = $this->sendRequest('/v3/product/info/list', ['offer_id' => array_values($skus)]);
        $items = $decoded['result']['items'] ?? $decoded['items'] ?? null;
        if (!is_array($items)) {
            return [];
        }

        /** @var list<array<string, mixed>> $items */
        return $items;
    }

    /**
     * Product attributes (POST /v3/product/info/attributes) — may carry video URLs in values / rich JSON.
     *
     * @param list<int> $productIds
     * @return list<array<string, mixed>>
     */
    public function fetchProductInfoAttributes(array $productIds): array
    {
        if ($this->usesPlaceholderCredentials()) {
            return [];
        }
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
            $decoded = $this->sendRequest('/v3/product/info/attributes', [
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
     * Full description text (POST /v1/product/info/description). Empty when mock/invalid id.
     */
    public function fetchProductDescription(int $productId): string
    {
        if ($this->usesPlaceholderCredentials() || $productId < 1) {
            return '';
        }

        try {
            $decoded = $this->sendRequest('/v1/product/info/description', ['product_id' => (string) $productId], 45);
            $r = $decoded['result'] ?? null;

            return is_array($r) ? (string) ($r['description'] ?? '') : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * After real API keys are configured, load gallery URLs from Ozon Seller API, e.g.:
     * POST /v2/product/info (or /v3/product/info/list) — fields `images` / `primary_image` per product.
     * See https://docs.ozon.ru/api/seller/ — replace stub with JSON decode + return list of HTTPS URLs.
     *
     * @return list<string> Absolute image URLs (CDN)
     */
    public function fetchProductImages(int|string $productId): array
    {
        if ($this->usesPlaceholderCredentials()) {
            return [];
        }

        unset($productId);

        return [];
    }

    /**
     * Fallback URL when API/mock does not provide `ozon_url`.
     * With slug: https://www.ozon.ru/product/{slug}-{id}/
     * Without: https://www.ozon.ru/product/{id}/
     */
    public function buildOzonUrl(int|string $productId, string $slug = ''): string
    {
        $id = is_int($productId) ? (string) $productId : preg_replace('/[^\d]/', '', (string) $productId);
        if ($id === '') {
            return 'https://www.ozon.ru/';
        }
        if ($slug !== '') {
            return 'https://www.ozon.ru/product/' . rawurlencode($slug) . '-' . $id . '/';
        }

        return 'https://www.ozon.ru/product/' . $id . '/';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getMockData(): array
    {
        // TODO: prices and descriptions are placeholders — real data comes from Ozon API sync.

        return [
            [
                'id' => 3526089555,
                'offer_id' => 'BUY-DOMKRAT',
                'name' => 'Сумка для домкрата автомобильного',
                'price' => '890',
                'marketing_price' => '990',
                'description' => 'Прочная сумка-органайзер для автомобильного домкрата. Защищает багажник от грязи, компактное хранение.',
                'ozon_url' => 'https://www.ozon.ru/product/sumka-dlya-domkrata-avtomobilnogo-3526089555/',
                'sort_order' => 100,
            ],
            [
                'id' => 2384564507,
                'offer_id' => 'BUY-COMPRESSOR',
                'name' => 'Сумка для автокомпрессора',
                'price' => '790',
                'marketing_price' => '890',
                'description' => 'Сумка для хранения и транспортировки автомобильного компрессора. Прочная ткань, удобная ручка.',
                'ozon_url' => 'https://www.ozon.ru/product/sumka-dlya-avtokompressora-2384564507/',
                'sort_order' => 110,
            ],
            [
                'id' => 3353068030,
                'offer_id' => 'BUY-PROVODA',
                'name' => 'Сумка для проводов прикуривания',
                'price' => '690',
                'marketing_price' => '790',
                'description' => 'Органайзер для проводов прикуривания. Компактная сумка с застёжкой.',
                'ozon_url' => 'https://www.ozon.ru/product/sumka-dlya-provodov-prikurivaniya-3353068030/',
                'sort_order' => 120,
            ],
            [
                'id' => 3352955195,
                'offer_id' => 'BUY-STROPA',
                'name' => 'Сумка для буксировочной стропы',
                'price' => '690',
                'marketing_price' => '790',
                'description' => 'Сумка для хранения буксировочного троса или стропы. Прочный материал, компактный размер.',
                'ozon_url' => 'https://www.ozon.ru/product/sumka-dlya-buksirovochnoy-stropy-3352955195/',
                'sort_order' => 130,
            ],
            [
                'id' => 3352968330,
                'offer_id' => 'BUY-ELECTRIKA',
                'name' => 'Сумка для хранения автоэлектрики',
                'price' => '590',
                'marketing_price' => '690',
                'description' => 'Органайзер для хранения автомобильной электрики: предохранители, клеммы, провода.',
                'ozon_url' => 'https://www.ozon.ru/product/sumka-dlya-hraneniya-avtoelektriki-3352968330/',
                'sort_order' => 140,
            ],
            [
                'id' => 3352903699,
                'offer_id' => 'BUY-APTECHKA',
                'name' => 'Сумка авто-аптечка',
                'price' => '490',
                'marketing_price' => '590',
                'description' => 'Текстильная сумка для автомобильной аптечки. Яркая маркировка, удобный доступ.',
                'ozon_url' => 'https://www.ozon.ru/product/sumka-avto-aptechka-3352903699/',
                'sort_order' => 150,
            ],
            [
                'id' => 3347697391,
                'offer_id' => 'BUY-INSTRUMENT',
                'name' => 'Сумка для инструмента',
                'price' => '990',
                'marketing_price' => '1190',
                'description' => 'Универсальная сумка для набора инструментов. Множество карманов, прочная конструкция.',
                'ozon_url' => 'https://www.ozon.ru/product/sumka-dlya-instrumenta-3347697391/',
                'sort_order' => 160,
            ],
            [
                'id' => 1893584234,
                'offer_id' => 'BATYA-REZTSY',
                'name' => 'Сумка-скрутка для резцов по дереву',
                'price' => '1290',
                'marketing_price' => '1490',
                'description' => 'Профессиональная сумка-скрутка для хранения и транспортировки резцов по дереву.',
                'ozon_url' => 'https://www.ozon.ru/product/sumka-skrutka-dlya-reztsov-po-derevu-1893584234/',
                'sort_order' => 200,
            ],
            [
                'id' => 1893558422,
                'offer_id' => 'BATYA-SKRUTKA',
                'name' => 'Сумка-скрутка для инструмента',
                'price' => '1190',
                'marketing_price' => '1390',
                'description' => 'Универсальная сумка-скрутка для ручного инструмента. Индивидуальные ячейки для каждого инструмента.',
                'ozon_url' => 'https://www.ozon.ru/product/sumka-skrutka-dlya-instrumenta-1893558422/',
                'sort_order' => 210,
            ],
            [
                'id' => 1884174749,
                'offer_id' => 'BATYA-AVTO',
                'name' => 'Сумка-скрутка для авто-инструмента',
                'price' => '1390',
                'marketing_price' => '1590',
                'description' => 'Сумка-скрутка для автомобильного набора инструментов: ключи, отвёртки, головки.',
                'ozon_url' => 'https://www.ozon.ru/product/sumka-skrutka-dlya-avto-instrumenta-1884174749/',
                'sort_order' => 220,
            ],
            [
                'id' => 1666958285,
                'offer_id' => 'VOLNA-YAKOR',
                'name' => 'Якорь для лодки',
                'price' => '1890',
                'marketing_price' => '2190',
                'description' => 'Складной якорь для надувной лодки ПВХ. Компактный, лёгкий, надёжная фиксация.',
                'ozon_url' => 'https://www.ozon.ru/product/yakor-dlya-lodki-1666958285/',
                'sort_order' => 300,
            ],
        ];
    }

    private function usesPlaceholderCredentials(): bool
    {
        return $this->clientId === 'YOUR_OZON_CLIENT_ID' || $this->apiKey === 'YOUR_OZON_API_KEY'
            || $this->clientId === '' || $this->apiKey === '';
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function sendRequest(string $endpoint, array $body, int $timeoutSeconds = 30): array
    {
        $url = rtrim($this->baseUrl, '/') . $endpoint;
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
                'Client-Id: ' . $this->clientId,
                'Api-Key: ' . $this->apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new \RuntimeException('Ozon curl error: ' . $errno);
        }

        if (!is_string($response)) {
            throw new \RuntimeException('Ozon empty response');
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('Ozon HTTP ' . $httpCode . ': ' . $response);
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
