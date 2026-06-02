<?php
/**
 * Product Management Admin
 * Access: /admin/index.php (protected by HTTP Basic Auth)
 *
 * - Table with thumbnails, quick toggles (active / preserve_sync), prices, direct links
 * - Advanced filters + search
 * - Full edit form (incl. brand, seo_article, manual price_direct)
 * - Recent orders with status management
 * - Sync page is separate: /admin/sync.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Uzelok\Core\Brand;
use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;

use function Uzelok\Core\generateCsrfToken;
use function Uzelok\Core\productCardPrimaryImage;
use function Uzelok\Core\productOzonPurchaseUrl;
use function Uzelok\Core\validateCsrfToken;

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__, 2) . '/config/config.php';

$adminUser = (string) ($config['admin']['username'] ?? 'admin');
$adminPass = (string) ($config['admin']['password'] ?? '');

$authUser = $_SERVER['PHP_AUTH_USER'] ?? '';
$authPass = $_SERVER['PHP_AUTH_PW'] ?? '';

if ($authUser !== $adminUser || !hash_equals($adminPass, $authPass)) {
    header('WWW-Authenticate: Basic realm="Admin"');
    http_response_code(401);
    echo 'Authorization required';
    exit;
}

$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    http_response_code(500);
    echo 'Configuration error';
    exit;
}

$db = Database::getInstance($dbPath);
$productModel = new Product($db);

$logsPath = (string) ($config['paths']['logs'] ?? dirname(__DIR__, 2) . '/logs');

// === CSRF ===
session_start();
$csrfToken = generateCsrfToken();

// === Handle POST (save product) ===
$saveResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    if (!validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $saveResult = ['success' => false, 'message' => 'CSRF validation failed'];
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $fields = [
                'title'        => trim((string) ($_POST['title'] ?? '')),
                'description'  => trim((string) ($_POST['description'] ?? '')),
                'price_direct' => $_POST['price_direct'] !== '' ? (int) $_POST['price_direct'] : null,
                'is_active'    => isset($_POST['is_active']) ? 1 : 0,
                'sort_order'   => (int) ($_POST['sort_order'] ?? 0),
                'preserve_sync'=> isset($_POST['preserve_sync']) ? 1 : 0,
                'seo_article'  => trim((string) ($_POST['seo_article'] ?? '')),
                'brand_type'   => trim((string) ($_POST['brand_type'] ?? '')),
            ];

            try {
                if ($productModel->update($id, $fields)) {
                    // Preserve current filters on redirect + signal success
                    $qs = $_SERVER['QUERY_STRING'] ?? '';
                    $redirect = '/admin/' . ($qs ? '?' . $qs . '&' : '?') . 'saved=1';
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $saveResult = ['success' => false, 'message' => 'Ошибка сохранения (update вернул false)'];
                }
            } catch (\Throwable $e) {
                $saveResult = ['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()];
            }
        }
    }
}

// Flash success from redirect after save
if (isset($_GET['saved']) && $_GET['saved'] === '1' && $saveResult === null) {
    $saveResult = ['success' => true, 'message' => 'Товар сохранён'];
}

// === Handle order status updates ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    if (validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $oid = (int) ($_POST['order_id'] ?? 0);
        $newStatus = (string) ($_POST['status'] ?? 'new');
        if ($oid > 0 && in_array($newStatus, ['new', 'processed', 'closed'], true)) {
            try {
                $db->query(
                    "UPDATE orders SET status = :s WHERE id = :id",
                    [':s' => $newStatus, ':id' => $oid]
                );
            } catch (\Throwable) {}
        }
    }
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: /admin/' . ($qs ? '?' . $qs : ''));
    exit;
}

// (legacy toggle_field handler removed — quick_toggle forms below handle is_active/preserve_sync)

// === Handle quick toggle from list table (Variant 1 admin improvements) ===
// Uses the simplest pure-PHP pattern: per-row <form method="post"> + redirect.
// Supports is_active and preserve_sync. Includes existing CSRF. Preserves all filters via QUERY_STRING.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_toggle'])) {
    if (!validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: /admin/' . ($qs ? '?' . $qs : ''));
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $field = (string) ($_POST['field'] ?? '');
    $value = (int) ($_POST['value'] ?? 0);

    if ($id > 0 && in_array($field, ['is_active', 'preserve_sync'], true)) {
        try {
            $productModel->update($id, [$field => $value ? 1 : 0]);
        } catch (\Throwable $e) {
            // Silent fail acceptable for quick toggles (full edit form shows detailed errors)
        }
    }

    // Clean redirect back to the exact same filtered view (preserves ?q, ?brand, ?inactive perfectly)
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: /admin/' . ($qs ? '?' . $qs : ''));
    exit;
}

// === Filters ===
$brandFilter = isset($_GET['brand']) ? Brand::tryFrom((string) $_GET['brand']) : null;
$search = trim((string) ($_GET['q'] ?? ''));
$showInactive = isset($_GET['inactive']);
$onlyManualPrice = isset($_GET['manual_price']);
$onlyPreserved = isset($_GET['preserved']);

// Load products
if ($brandFilter !== null) {
    $products = $productModel->findByBrand($brandFilter, !$showInactive);
} else {
    $products = $productModel->findAll(!$showInactive);
}

// Apply search filter in PHP (simple)
if ($search !== '') {
    $products = array_filter($products, function ($p) use ($search) {
        return stripos((string) ($p['title'] ?? ''), $search) !== false
            || stripos((string) ($p['sku'] ?? ''), $search) !== false
            || stripos((string) ($p['offer_id'] ?? ''), $search) !== false;
    });
}

// Extra client-side filters (manual price / preserved)
if ($onlyManualPrice) {
    $products = array_filter($products, function ($p) {
        return !empty($p['price_direct']) && (int)$p['price_direct'] > 0;
    });
}
if ($onlyPreserved) {
    $products = array_filter($products, function ($p) {
        return (int) ($p['preserve_sync'] ?? 0) === 1;
    });
}

// Global stats for header (independent of current filters)
$allProducts = $productModel->findAll(false);
$total = count($allProducts);
$activeCount = 0;
$manualPriceCount = 0;
$preservedCount = 0;
$byBrand = ['batya' => 0, 'buy' => 0, 'volna' => 0];
foreach ($allProducts as $pp) {
    if ((int) ($pp['is_active'] ?? 0) === 1) $activeCount++;
    if (!empty($pp['price_direct']) && (int)$pp['price_direct'] > 0) $manualPriceCount++;
    if ((int) ($pp['preserve_sync'] ?? 0) === 1) $preservedCount++;
    $bt = (string) ($pp['brand_type'] ?? '');
    if (isset($byBrand[$bt])) $byBrand[$bt]++;
}
$recentOrdersCount = 0;
try {
    $cntStmt = $db->query("SELECT COUNT(*) c FROM orders", []);
    $row = $cntStmt->fetch();
    $recentOrdersCount = (int) ($row['c'] ?? 0);
} catch (\Throwable) {}

// Current edit product (if any)
$editingProduct = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $editingProduct = $productModel->findById($editId);
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление товарами — УЗЕЛОК64</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #0a0a0f; color: #e8e8f0; }
        .uz-table { border-collapse: collapse; }
        .uz-table th, .uz-table td { padding: 8px 12px; border-bottom: 1px solid #2a2a3e; }
        .uz-table th { background: #12121a; text-align: left; font-weight: 600; }
        .form-input { background: #1e1e2e; border: 1px solid #2a2a3e; color: #e8e8f0; }
        .form-input:focus { outline: none; border-color: #f97316; }
    </style>
</head>
<body class="p-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-orange-500">Управление карточками товаров</h1>
            <p class="text-sm text-[#a0a0b8] mt-1">Ручное редактирование (синхронизация с Ozon не затирает ручные изменения)</p>
        </div>
        <div class="flex gap-3">
            <a href="/admin/sync.php" 
               class="px-4 py-2 rounded-lg bg-[#1e1e2e] border border-[#2a2a3e] hover:bg-[#25253a] text-sm">
                → Синхронизация Ozon
            </a>
            <a href="/" class="px-4 py-2 rounded-lg border border-[#2a2a3e] hover:bg-[#1e1e2e] text-sm">На сайт</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="mb-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-[#12121a] border border-[#2a2a3e] rounded-xl px-4 py-3">
            <div class="text-xs text-[#6b6b80]">Всего товаров</div>
            <div class="text-2xl font-semibold tabular-nums"><?= $total ?></div>
        </div>
        <div class="bg-[#12121a] border border-[#2a2a3e] rounded-xl px-4 py-3">
            <div class="text-xs text-[#6b6b80]">Активных</div>
            <div class="text-2xl font-semibold tabular-nums text-emerald-400"><?= $activeCount ?></div>
        </div>
        <div class="bg-[#12121a] border border-[#2a2a3e] rounded-xl px-4 py-3">
            <div class="text-xs text-[#6b6b80]">Ручная цена</div>
            <div class="text-2xl font-semibold tabular-nums text-orange-400"><?= $manualPriceCount ?></div>
        </div>
        <div class="bg-[#12121a] border border-[#2a2a3e] rounded-xl px-4 py-3">
            <div class="text-xs text-[#6b6b80]">Защищено от синка</div>
            <div class="text-2xl font-semibold tabular-nums"><?= $preservedCount ?></div>
        </div>
        <div class="bg-[#12121a] border border-[#2a2a3e] rounded-xl px-4 py-3">
            <div class="text-xs text-[#6b6b80]">Заявок всего</div>
            <div class="text-2xl font-semibold tabular-nums"><?= $recentOrdersCount ?></div>
        </div>
        <div class="bg-[#12121a] border border-[#2a2a3e] rounded-xl px-4 py-3 text-xs flex flex-col justify-center">
            <div class="flex gap-2 text-[#a0a0b8]">
                <span>БАТЯ: <span class="font-mono text-white"><?= $byBrand['batya'] ?></span></span>
                <span>БУЙ: <span class="font-mono text-white"><?= $byBrand['buy'] ?></span></span>
                <span>ВОЛНА: <span class="font-mono text-white"><?= $byBrand['volna'] ?></span></span>
            </div>
            <div class="mt-1 text-[10px] text-[#6b6b80]">По брендам (все)</div>
        </div>
    </div>

    <?php if ($saveResult): ?>
        <div class="mb-4 p-3 rounded-lg <?= $saveResult['success'] ? 'bg-emerald-900/40 text-emerald-400' : 'bg-red-900/40 text-red-400' ?>">
            <?= htmlspecialchars($saveResult['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="get" class="mb-4 flex flex-wrap gap-3 items-end bg-[#12121a] p-4 rounded-xl border border-[#2a2a3e]">
        <div>
            <label class="block text-xs text-[#a0a0b8] mb-1">Поиск</label>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                   class="form-input px-3 py-2 rounded w-64" placeholder="Название или SKU">
        </div>

        <div>
            <label class="block text-xs text-[#a0a0b8] mb-1">Бренд</label>
            <select name="brand" class="form-input px-3 py-2 rounded">
                <option value="">Все</option>
                <?php foreach (Brand::cases() as $b): ?>
                    <option value="<?= $b->value ?>" <?= $brandFilter === $b ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="inactive" value="1" <?= $showInactive ? 'checked' : '' ?> class="accent-orange-500">
            Показывать неактивные
        </label>

        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="manual_price" value="1" <?= $onlyManualPrice ? 'checked' : '' ?> class="accent-orange-500">
            Только с ручной ценой
        </label>

        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="preserved" value="1" <?= $onlyPreserved ? 'checked' : '' ?> class="accent-orange-500">
            Только защищённые
        </label>

        <button type="submit" class="px-4 py-2 bg-orange-600 hover:bg-orange-500 rounded text-sm font-medium">Применить</button>
        <a href="/admin/" class="px-4 py-2 text-sm text-[#a0a0b8] hover:text-white">Сбросить</a>
    </form>

    <!-- Products Table -->
    <div class="bg-[#12121a] rounded-xl border border-[#2a2a3e] overflow-hidden">
        <table class="uz-table w-full text-sm">
            <thead>
                <tr>
                    <th class="w-12">ID</th>
                    <th class="w-10"></th>
                    <th>Бренд</th>
                    <th>SKU</th>
                    <th>Название</th>
                    <th class="text-right">Ozon</th>
                    <th class="text-right">Наша цена</th>
                    <th class="w-10 text-center" title="Активен (показывается на сайте)">Акт.</th>
                    <th class="w-10 text-center" title="Защищён от перезаписи при синке Ozon (preserve_sync)">Sync</th>
                    <th class="w-12 text-center">Порядок</th>
                    <th class="w-24 text-right">Обновлён</th>
                    <th class="w-40"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($products) === 0): ?>
                    <tr><td colspan="10" class="p-8 text-center text-[#6b6b80]">Товары не найдены</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): 
                        $brand = Brand::tryFrom((string) ($p['brand_type'] ?? ''));
                        $brandColor = $brand ? $brand->color() : '#6b6b80';
                        $isActive = (int) ($p['is_active'] ?? 0);
                        $preserveSync = (int) ($p['preserve_sync'] ?? 0);
                    ?>
                    <tr class="<?= $isActive === 0 ? 'opacity-60' : '' ?>">
                        <td class="font-mono text-xs text-[#6b6b80]"><?= (int) $p['id'] ?></td>
                        <td>
                            <?php $img = productCardPrimaryImage($p); if ($img['src']): ?>
                                <img src="<?= htmlspecialchars($img['src']) ?>" 
                                     class="w-9 h-9 object-cover rounded border border-[#2a2a3e] bg-[#0a0a0f]"
                                     <?= $img['ozonRemote'] ? 'referrerpolicy="no-referrer"' : '' ?>
                                     alt="">
                            <?php else: ?>
                                <div class="w-9 h-9 bg-[#1e1e2e] rounded border border-[#2a2a3e]"></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium" 
                                  style="background: <?= htmlspecialchars($brandColor) ?>; color: white;">
                                <?= $brand ? htmlspecialchars($brand->label()) : '?' ?>
                            </span>
                        </td>
                        <td class="font-mono text-xs"><?= htmlspecialchars((string) ($p['sku'] ?? '')) ?></td>
                        <td class="max-w-[380px]">
                            <div class="line-clamp-2"><?= htmlspecialchars((string) ($p['title'] ?? '')) ?></div>
                            <?php if (!empty($p['offer_id'])): ?>
                                <div class="text-[10px] text-[#6b6b80] font-mono"><?= htmlspecialchars((string)$p['offer_id']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-right font-mono text-orange-400"><?= number_format((int) ($p['price_ozon'] ?? 0), 0, ',', ' ') ?> ₽</td>
                        <td class="text-right font-mono <?= !empty($p['price_direct']) ? 'text-emerald-400 font-semibold' : 'text-[#6b6b80]' ?>">
                            <?= !empty($p['price_direct']) ? number_format((int) $p['price_direct'], 0, ',', ' ') . ' ₽' : '—' ?>
                        </td>

                        <!-- QUICK TOGGLE: is_active -->
                        <td class="text-center p-1">
                            <form method="post" class="quick-toggle-form" style="display:inline-block; margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="field" value="is_active">
                                <input type="hidden" name="value" value="<?= $isActive ? '0' : '1' ?>">
                                <input type="hidden" name="quick_toggle" value="1">
                                <button type="submit"
                                        class="quick-toggle-btn px-2 py-0.5 text-base leading-none hover:bg-[#25253a] rounded transition-colors"
                                        title="<?= $isActive ? 'Скрыть товар (сделать неактивным)' : 'Показать товар (сделать активным)' ?>">
                                    <?= $isActive ? '<span class="text-emerald-400">●</span>' : '<span class="text-red-400">○</span>' ?>
                                </button>
                            </form>
                        </td>

                        <!-- QUICK TOGGLE: preserve_sync -->
                        <td class="text-center p-1">
                            <form method="post" class="quick-toggle-form" style="display:inline-block; margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="field" value="preserve_sync">
                                <input type="hidden" name="value" value="<?= $preserveSync ? '0' : '1' ?>">
                                <input type="hidden" name="quick_toggle" value="1">
                                <button type="submit"
                                        class="quick-toggle-btn px-2 py-0.5 text-base leading-none hover:bg-[#25253a] rounded transition-colors"
                                        title="<?= $preserveSync ? 'Снять защиту (синк сможет перезаписывать)' : 'Защитить от перезаписи при синке Ozon' ?>">
                                    <?= $preserveSync ? '🔒' : '—' ?>
                                </button>
                            </form>
                        </td>

                        <td class="text-center font-mono text-xs"><?= (int) ($p['sort_order'] ?? 0) ?></td>
                        <td class="text-right pr-2 text-[10px] text-[#6b6b80] tabular-nums">
                            <?php
                                $upd = (string) ($p['updated_at'] ?? '');
                                echo $upd ? htmlspecialchars(substr($upd, 5, 11)) : '—';
                            ?>
                        </td>
                        <td class="text-right pr-3 text-xs space-x-2">
                            <?php
                                $baseQs = [];
                                if ($search) $baseQs[] = 'q=' . urlencode($search);
                                if ($brandFilter) $baseQs[] = 'brand=' . urlencode($brandFilter->value);
                                if ($showInactive) $baseQs[] = 'inactive=1';
                                if ($onlyManualPrice) $baseQs[] = 'manual_price=1';
                                if ($onlyPreserved) $baseQs[] = 'preserved=1';
                                $qsStr = $baseQs ? '&' . implode('&', $baseQs) : '';
                                $editHref = '?edit=' . (int) $p['id'] . $qsStr;
                                $siteHref = '/?page=product&id=' . (int) $p['id'];
                                $ozonHref = productOzonPurchaseUrl($p);
                            ?>
                            <a href="<?= htmlspecialchars($editHref) ?>" class="text-orange-400 hover:underline">Редакт.</a>
                            <a href="<?= htmlspecialchars($siteHref) ?>" target="_blank" rel="noopener" class="text-[#a0a0b8] hover:text-white">Сайт</a>
                            <a href="<?= htmlspecialchars($ozonHref) ?>" target="_blank" rel="noopener" class="text-[#a0a0b8] hover:text-white">Ozon</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="mt-2 text-xs text-[#6b6b80]">
        Всего показано: <?= count($products) ?>. 
        Для массовых изменений и синхронизации используйте страницу синхронизации.
    </p>

    <!-- Recent Orders -->
    <?php
    $recentOrders = [];
    try {
        $ordStmt = $db->query(
            "SELECT o.*, p.title as product_title 
             FROM orders o 
             LEFT JOIN products p ON p.id = o.product_id 
             ORDER BY o.created_at DESC 
             LIMIT 12",
            []
        );
        $recentOrders = $ordStmt->fetchAll();
    } catch (\Throwable) {}
    ?>
    <div class="mt-10">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold">Последние заявки <span class="text-xs text-[#6b6b80]">(<?= count($recentOrders) ?> из <?= $recentOrdersCount ?>)</span></h2>
            <a href="/admin/sync.php" class="text-xs text-[#a0a0b8] hover:text-white">Синхронизация →</a>
        </div>
        <div class="bg-[#12121a] border border-[#2a2a3e] rounded-xl overflow-hidden">
            <table class="uz-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Имя / Телефон</th>
                        <th>Товар</th>
                        <th>Источник</th>
                        <th>Комментарий</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentOrders): ?>
                        <tr><td colspan="7" class="p-6 text-center text-[#6b6b80]">Заявок пока нет</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $o): 
                            $st = (string) ($o['status'] ?? 'new');
                            $src = (string) ($o['source'] ?? 'website');
                        ?>
                        <tr>
                            <td class="font-mono text-xs text-[#a0a0b8]"><?= htmlspecialchars(substr((string)($o['created_at'] ?? ''), 0, 16)) ?></td>
                            <td>
                                <div><?= htmlspecialchars((string) ($o['customer_name'] ?? '')) ?></div>
                                <div class="font-mono text-xs text-[#6b6b80]"><?= htmlspecialchars((string) ($o['customer_phone'] ?? '')) ?></div>
                            </td>
                            <td class="max-w-[260px] text-xs">
                                <?php if (!empty($o['product_title'])): ?>
                                    <?= htmlspecialchars($o['product_title']) ?>
                                <?php else: ?>
                                    <span class="text-[#6b6b80]">— общая заявка —</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="inline-block px-1.5 py-px rounded text-[10px] <?= $src === 'workshop' ? 'bg-amber-900/50 text-amber-300' : 'bg-[#2a2a3e] text-[#a0a0b8]' ?>">
                                    <?= $src === 'workshop' ? '🏭 Мастерская' : 'Сайт' ?>
                                </span>
                            </td>
                            <td class="max-w-[240px] text-xs text-[#a0a0b8] line-clamp-2"><?= htmlspecialchars(trim((string) ($o['message'] ?? '')) ?: '—') ?></td>
                            <td>
                                <span class="text-xs px-2 py-0.5 rounded <?= $st === 'closed' ? 'bg-zinc-700' : ($st === 'processed' ? 'bg-emerald-900/60 text-emerald-300' : 'bg-orange-900/50 text-orange-300') ?>">
                                    <?= htmlspecialchars($st) ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                    <input type="hidden" name="update_order_status" value="1">
                                    <select name="status" class="form-input text-xs py-0.5 px-1 rounded" onchange="this.form.submit()">
                                        <option value="new" <?= $st === 'new' ? 'selected' : '' ?>>new</option>
                                        <option value="processed" <?= $st === 'processed' ? 'selected' : '' ?>>processed</option>
                                        <option value="closed" <?= $st === 'closed' ? 'selected' : '' ?>>closed</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-1 text-[10px] text-[#6b6b80]">Статус меняется автоматически при выборе в выпадашке. Заявки сохраняются в БД независимо от Telegram/MAX/email.</p>
    </div>

    <!-- Edit Form -->
    <?php if ($editingProduct): ?>
    <?php
        $editBrand = Brand::tryFrom((string) ($editingProduct['brand_type'] ?? ''));
        $editOzonUrl = productOzonPurchaseUrl($editingProduct);
        $editImg = productCardPrimaryImage($editingProduct);
    ?>
    <div class="mt-8 bg-[#12121a] border border-orange-500/40 rounded-2xl p-6" id="edit-form">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold flex items-center gap-2">
                    Редактирование #<?= (int) $editingProduct['id'] ?>
                    <span class="text-sm text-[#6b6b80] font-normal">— <?= htmlspecialchars((string) ($editingProduct['sku'] ?? '')) ?></span>
                </h2>
                <div class="mt-1 text-xs">
                    <a href="<?= htmlspecialchars($editOzonUrl) ?>" target="_blank" rel="noopener" class="text-orange-400 hover:underline">Ozon →</a>
                    <a href="/?page=product&id=<?= (int) $editingProduct['id'] ?>" target="_blank" class="ml-3 text-[#a0a0b8] hover:text-white">Открыть на сайте →</a>
                </div>
            </div>
            <?php if ($editImg['src']): ?>
                <img src="<?= htmlspecialchars($editImg['src']) ?>" class="w-16 h-16 object-cover rounded-lg border border-[#2a2a3e]" <?= $editImg['ozonRemote'] ? 'referrerpolicy="no-referrer"' : '' ?> alt="">
            <?php endif; ?>
        </div>

        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int) $editingProduct['id'] ?>">
            <input type="hidden" name="save_product" value="1">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm mb-1 text-[#a0a0b8]">Бренд</label>
                    <select name="brand_type" class="form-input w-full px-3 py-2 rounded">
                        <?php foreach (Brand::cases() as $b): ?>
                            <option value="<?= $b->value ?>" <?= $editBrand === $b ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b->label()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm mb-1 text-[#a0a0b8]">Название</label>
                    <input type="text" name="title" value="<?= htmlspecialchars((string) ($editingProduct['title'] ?? '')) ?>" 
                           class="form-input w-full px-3 py-2 rounded" required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1 text-[#a0a0b8]">Наша цена (price_direct) — переопределяет Ozon</label>
                    <input type="number" name="price_direct" value="<?= htmlspecialchars((string) ($editingProduct['price_direct'] ?? '')) ?>" 
                           class="form-input w-full px-3 py-2 rounded" placeholder="Оставьте пустым для цены с Ozon">
                </div>
                <div>
                    <label class="block text-sm mb-1 text-[#a0a0b8]">SKU / offer_id (из Ozon)</label>
                    <div class="font-mono text-sm px-3 py-2 bg-[#1a1a24] rounded border border-[#2a2a3e] text-[#a0a0b8]">
                        <?= htmlspecialchars((string) ($editingProduct['sku'] ?? '')) ?>
                        <?php if (!empty($editingProduct['offer_id'])): ?> / <?= htmlspecialchars((string) $editingProduct['offer_id']) ?><?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm mb-1 text-[#a0a0b8]">Описание</label>
                <textarea name="description" rows="4" class="form-input w-full px-3 py-2 rounded font-mono text-sm"><?= htmlspecialchars((string) ($editingProduct['description'] ?? '')) ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm mb-1 text-[#a0a0b8]">Порядок сортировки</label>
                    <input type="number" name="sort_order" value="<?= (int) ($editingProduct['sort_order'] ?? 0) ?>" 
                           class="form-input w-full px-3 py-2 rounded">
                </div>
                <div class="flex items-center gap-6 pt-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" <?= (int) ($editingProduct['is_active'] ?? 0) ? 'checked' : '' ?> class="accent-orange-500 w-4 h-4">
                        <span>Активен</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="preserve_sync" value="1" <?= (int) ($editingProduct['preserve_sync'] ?? 0) ? 'checked' : '' ?> class="accent-orange-500 w-4 h-4">
                        <span>Защищён от перезаписи синком</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm mb-1 text-[#a0a0b8]">SEO-статья (отображается на странице товара)</label>
                <textarea name="seo_article" rows="8" class="form-input w-full px-3 py-2 rounded font-mono text-sm"><?= htmlspecialchars((string) ($editingProduct['seo_article'] ?? '')) ?></textarea>
            </div>

            <div class="flex gap-3 items-center">
                <button type="submit" class="px-6 py-2.5 bg-orange-600 hover:bg-orange-500 rounded-xl font-semibold">
                    Сохранить изменения
                </button>
                <?php
                    $cancelQs = [];
                    if ($search) $cancelQs[] = 'q=' . urlencode($search);
                    if ($brandFilter) $cancelQs[] = 'brand=' . urlencode($brandFilter->value);
                    if ($showInactive) $cancelQs[] = 'inactive=1';
                    if ($onlyManualPrice) $cancelQs[] = 'manual_price=1';
                    if ($onlyPreserved) $cancelQs[] = 'preserved=1';
                    $cancelHref = '/admin/' . ($cancelQs ? '?' . implode('&', $cancelQs) : '');
                ?>
                <a href="<?= htmlspecialchars($cancelHref) ?>" class="px-6 py-2.5 border border-[#2a2a3e] rounded-xl hover:bg-[#1e1e2e]">Отмена</a>
                <span class="ml-auto text-xs text-[#6b6b80]">
                    Поля «preserve_sync» и ручная цена защищают от полной перезаписи при синке Ozon.
                </span>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <script>
        // Scroll to edit form if present
        if (window.location.search.includes('edit=')) {
            const el = document.getElementById('edit-form');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Optional tiny vanilla JS (progressive enhancement only)
        // Variant 1 admin improvements — quick toggles for is_active + preserve_sync.
        // Disables the toggle button immediately on submit to prevent double-clicks.
        // Completely safe to delete this block — the <form method="post"> toggles work 100% without JS.
        // If the UI Agent later wants fetch + row refresh instead of full reload, they can replace this
        // with a fetch POST using the exact same hidden-field contract (csrf_token, id, field, value, quick_toggle).
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form.quick-toggle-form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    const btn = form.querySelector('button.quick-toggle-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.6';
                    }
                });
            });
        });
    </script>
</body>
</html>
