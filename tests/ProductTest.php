<?php
/**
 * Minimal tests for Product model manual updates (saving products in admin).
 *
 * Run with: composer test
 * or: vendor/bin/phpunit tests/ProductTest.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;

final class ProductTest extends TestCase
{
    private ?Product $productModel = null;
    private ?string $tempDbPath = null;

    protected function setUp(): void
    {
        // Use a fresh temporary SQLite database for isolation
        $this->tempDbPath = sys_get_temp_dir() . '/uzelok_test_' . uniqid() . '.db';

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

    public function testUpdatePriceDirect(): void
    {
        // Insert a test product
        $this->productModel->upsertFromOzon([
            'sku' => 'TEST-001',
            'brand_type' => 'buy',
            'title' => 'Test Product',
            'price_ozon' => 1500,
            'offer_id' => 'TEST-001',
        ]);

        $product = $this->productModel->findBySku('TEST-001');
        $this->assertNotNull($product);
        $id = (int) $product['id'];

        // Update price_direct
        $success = $this->productModel->update($id, [
            'price_direct' => 1250,
        ]);

        $this->assertTrue($success);

        $updated = $this->productModel->findById($id);
        $this->assertEquals(1250, $updated['price_direct']);
        $this->assertEquals(1500, $updated['price_ozon']); // Ozon price untouched
    }

    public function testUpdateIsActiveAndPreserveSync(): void
    {
        $this->productModel->upsertFromOzon([
            'sku' => 'TEST-002',
            'brand_type' => 'batya',
            'title' => 'Another Test',
            'price_ozon' => 2000,
            'offer_id' => 'TEST-002',
        ]);

        $product = $this->productModel->findBySku('TEST-002');
        $id = (int) $product['id'];

        $success = $this->productModel->update($id, [
            'is_active' => 0,
            'preserve_sync' => 1,
        ]);

        $this->assertTrue($success);

        $updated = $this->productModel->findById($id);
        $this->assertEquals(0, $updated['is_active']);
        $this->assertEquals(1, $updated['preserve_sync']);
    }

    public function testUpdateClearsPriceDirectWhenNull(): void
    {
        $this->productModel->upsertFromOzon([
            'sku' => 'TEST-003',
            'brand_type' => 'volna',
            'title' => 'Clear Price Test',
            'price_ozon' => 3000,
            'offer_id' => 'TEST-003',
        ]);

        $product = $this->productModel->findBySku('TEST-003');
        $id = (int) $product['id'];

        // First set a manual price
        $this->productModel->update($id, ['price_direct' => 2500]);

        // Then clear it
        $this->productModel->update($id, ['price_direct' => null]);

        $updated = $this->productModel->findById($id);
        $this->assertNull($updated['price_direct']);
    }
}