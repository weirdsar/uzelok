<?php

declare(strict_types=1);

namespace Uzelok\Core\Model;

use Uzelok\Core\Brand;
use Uzelok\Core\Database;

final class Product
{
    public function __construct(
        private readonly Database $db,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM products';
        $params = [];
        if ($activeOnly) {
            $sql .= ' WHERE is_active = :active';
            $params[':active'] = 1;
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByBrand(Brand $brand, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM products WHERE brand_type = :brand';
        $params = [':brand' => $brand->value];
        if ($activeOnly) {
            $sql .= ' AND is_active = :active';
            $params[':active'] = 1;
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySku(string $sku): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM products WHERE sku = :sku LIMIT 1',
            [':sku' => $sku]
        );
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        /** @var array<string, mixed> $row */
        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM products WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        /** @var array<string, mixed> $row */
        return $row;
    }

    /**
     * @param array<string, mixed> $data keys: sku, offer_id, brand_type, title, description, price_ozon, ozon_url, image_local_path, image_ozon_url, gallery_json, videos_json, seo_article, sort_order
     * user_gallery_json is not written here — preserved on conflict; new rows get '' (локальные инфографики из user_content).
     */
    public function upsertFromOzon(array $data): bool
    {
        $sku = $data['sku'] ?? null;
        if (!is_string($sku) || $sku === '') {
            return false;
        }

        $sql = <<<'SQL'
INSERT INTO products (
    sku, offer_id, brand_type, title, description, price_ozon, ozon_url, image_local_path, image_ozon_url, gallery_json, videos_json, seo_article, user_gallery_json,
    sort_order, is_active, updated_at
) VALUES (
    :sku, :offer_id, :brand_type, :title, :description, :price_ozon, :ozon_url, :image_local_path, :image_ozon_url, :gallery_json, :videos_json, :seo_article, '',
    :sort_order, 1, datetime('now')
)
ON CONFLICT(sku) DO UPDATE SET
    offer_id = excluded.offer_id,
    brand_type = excluded.brand_type,
    title = excluded.title,
    description = excluded.description,
    price_ozon = excluded.price_ozon,
    ozon_url = excluded.ozon_url,
    image_local_path = CASE WHEN excluded.image_local_path != '' THEN excluded.image_local_path ELSE products.image_local_path END,
    image_ozon_url = excluded.image_ozon_url,
    gallery_json = excluded.gallery_json,
    videos_json = excluded.videos_json,
    seo_article = CASE WHEN excluded.seo_article != '' THEN excluded.seo_article ELSE products.seo_article END,
    sort_order = CASE WHEN excluded.sort_order = 0 THEN products.sort_order ELSE excluded.sort_order END,
    is_active = 1,
    updated_at = datetime('now')
SQL;

        $params = [
            ':sku' => $sku,
            ':offer_id' => (string) ($data['offer_id'] ?? ''),
            ':brand_type' => (string) ($data['brand_type'] ?? 'batya'),
            ':title' => (string) ($data['title'] ?? ''),
            ':description' => (string) ($data['description'] ?? ''),
            ':price_ozon' => (int) ($data['price_ozon'] ?? 0),
            ':ozon_url' => (string) ($data['ozon_url'] ?? ''),
            ':image_local_path' => (string) ($data['image_local_path'] ?? ''),
            ':image_ozon_url' => (string) ($data['image_ozon_url'] ?? ''),
            ':gallery_json' => (string) ($data['gallery_json'] ?? ''),
            ':videos_json' => (string) ($data['videos_json'] ?? ''),
            ':seo_article' => (string) ($data['seo_article'] ?? ''),
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        try {
            $this->db->query($sql, $params);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @param list<string> $activeSKUs
     */
    public function deactivateMissing(array $activeSKUs): int
    {
        if ($activeSKUs === []) {
            $stmt = $this->db->query(
                "UPDATE products SET is_active = 0, updated_at = datetime('now') WHERE is_active = 1 AND sku IS NOT NULL",
                []
            );

            return $stmt->rowCount();
        }

        $placeholders = [];
        $params = [];
        foreach ($activeSKUs as $i => $sku) {
            $key = ':p' . $i;
            $placeholders[] = $key;
            $params[$key] = $sku;
        }

        $inList = implode(', ', $placeholders);
        $sql = "UPDATE products SET is_active = 0, updated_at = datetime('now')
                WHERE is_active = 1 AND sku IS NOT NULL AND sku NOT IN ({$inList})";

        $stmt = $this->db->query($sql, $params);

        return $stmt->rowCount();
    }

    public function count(bool $activeOnly = true): int
    {
        $sql = 'SELECT COUNT(*) FROM products';
        $params = [];
        if ($activeOnly) {
            $sql .= ' WHERE is_active = :active';
            $params[':active'] = 1;
        }
        $stmt = $this->db->query($sql, $params);
        $count = $stmt->fetchColumn();
        if ($count === false) {
            return 0;
        }

        return (int) $count;
    }

    public function countByBrand(Brand $brand, bool $activeOnly = true): int
    {
        $sql = 'SELECT COUNT(*) FROM products WHERE brand_type = :brand';
        $params = [':brand' => $brand->value];
        if ($activeOnly) {
            $sql .= ' AND is_active = :active';
            $params[':active'] = 1;
        }
        $stmt = $this->db->query($sql, $params);
        $count = $stmt->fetchColumn();
        if ($count === false) {
            return 0;
        }

        return (int) $count;
    }
}
