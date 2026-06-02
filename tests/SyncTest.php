<?php
/**
 * Minimal tests for product synchronization logic.
 *
 * Focuses on the model layer (upsert + deactivate) which is the core of SyncController.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;

final class SyncTest extends TestCase
{
    private ?Product $productModel = null;
    private ?string $tempDbPath = null;

    protected function setUp(): void
    {
        $this->tempDbPath = sys_get_temp_dir() . '/uzelok_sync_test_' . uniqid() . '.db';
        $db = Database::getInstance($this->tempDbPath);

        // Create full schema matching migrations (required for upsertFromOzon INSERT + ON CONFLICT)
        $db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                brand_type TEXT NOT NULL,
                sku TEXT UNIQUE,
                offer_id TEXT,
                title TEXT NOT NULL,
                price_ozon INTEGER NOT NULL DEFAULT 0,
                price_direct INTEGER DEFAULT NULL,
                ozon_url TEXT NOT NULL DEFAULT '',
                image_local_path TEXT DEFAULT '',
                image_ozon_url TEXT DEFAULT '',
                description TEXT DEFAULT '',
                category TEXT DEFAULT '',
                gallery_json TEXT DEFAULT '',
                videos_json TEXT DEFAULT '',
                seo_article TEXT DEFAULT '',
                user_gallery_json TEXT DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                preserve_sync INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        $this->productModel = new Product($db);
    }

    protected function tearDown(): void
    {
        if ($this->tempDbPath && file_exists($this->tempDbPath)) {
            unlink($this->tempDbPath);
        }
    }

    public function testUpsertFromOzonCreatesAndUpdates(): void
    {
        // First sync creates the product
        $this->productModel->upsertFromOzon([
            'sku' => 'SYNC-001',
            'brand_type' => 'buy',
            'title' => 'Initial Title',
            'price_ozon' => 1000,
            'offer_id' => 'SYNC-001',
            'sort_order' => 10,
        ]);

        $product = $this->productModel->findBySku('SYNC-001');
        $this->assertNotNull($product);
        $this->assertEquals('Initial Title', $product['title']);
        $this->assertEquals(1000, $product['price_ozon']);

        // Second sync updates it (price changed, title changed)
        $this->productModel->upsertFromOzon([
            'sku' => 'SYNC-001',
            'brand_type' => 'buy',
            'title' => 'Updated Title',
            'price_ozon' => 1200,
            'offer_id' => 'SYNC-001',
            'sort_order' => 10,
        ]);

        $updated = $this->productModel->findBySku('SYNC-001');
        $this->assertEquals('Updated Title', $updated['title']);
        $this->assertEquals(1200, $updated['price_ozon']);
        $this->assertEquals(1, $updated['is_active']); // should stay active
    }

    public function testDeactivateMissing(): void
    {
        // Create two products
        $this->productModel->upsertFromOzon(['sku' => 'KEEP-001', 'brand_type' => 'batya', 'title' => 'Keep', 'price_ozon' => 500, 'offer_id' => 'KEEP-001']);
        $this->productModel->upsertFromOzon(['sku' => 'DEACT-001', 'brand_type' => 'batya', 'title' => 'Deactivate', 'price_ozon' => 600, 'offer_id' => 'DEACT-001']);

        // Simulate sync that only returns KEEP-001
        $activeSkus = ['KEEP-001'];
        $deactivated = $this->productModel->deactivateMissing($activeSkus);

        $this->assertGreaterThanOrEqual(1, $deactivated);

        $keep = $this->productModel->findBySku('KEEP-001');
        $deact = $this->productModel->findBySku('DEACT-001');

        $this->assertEquals(1, $keep['is_active']);
        $this->assertEquals(0, $deact['is_active']);
    }
}