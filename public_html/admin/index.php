<?php
/**
 * Minimal Product Management Admin
 * Access: /admin/index.php (protected by HTTP Basic Auth)
 *
 * Allows viewing and manually editing product cards.
 * Sync functionality remains in /admin/sync.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Uzelok\Core\Brand;
use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;

use function Uzelok\Core\generateCsrfToken;
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
            ];

            try {
                if ($productModel->update($id, $fields)) {
                    $saveResult = ['success' => true, 'message' => 'Товар сохранён'];
                } else {
                    $saveResult = ['success' => false, 'message' => 'Ошибка сохранения (update вернул false)'];
                }
            } catch (\Throwable $e) {
                $saveResult = ['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()];
            }
        }
    }
}

// === Quick table toggles (Variant 1) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_field'])) {
    if (validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $id = (int) ($_POST['id'] ?? 0);
        $field = (string) ($_POST['toggle_field'] ?? '');
        if ($id > 0 && in_array($field, ['is_active', 'preserve_sync'], true)) {
            $value = (int) ($_POST['value'] ?? 0);
            try {
                $productModel->update($id, [$field => $value ? 1 : 0]);
                $saveResult = ['success' => true, 'message' => 'Статус обновлён'];
            } catch (\Throwable) {}
        }
    }
    $qs = http_build_query($_GET);
    header('Location: /admin/' . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

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
            || stripos((string) ($p['sku'] ?? ''), $search) !== false;
    });
}

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

        <button type="submit" class="px-4 py-2 bg-orange-600 hover:bg-orange-500 rounded text-sm font-medium">Применить</button>
        <a href="/admin/" class="px-4 py-2 text-sm text-[#a0a0b8] hover:text-white">Сбросить</a>
    </form>

    <!-- Products Table -->
    <div class="bg-[#12121a] rounded-xl border border-[#2a2a3e] overflow-hidden">
        <table class="uz-table w-full text-sm">
            <thead>
                <tr>
                    <th class="w-12">ID</th>
                    <th>Бренд</th>
                    <th>SKU</th>
                    <th>Название</th>
                    <th class="text-right">Ozon</th>
                    <th class="text-right">Наша цена</th>
                    <th class="w-12 text-center" title="Активен (показывается на сайте)">Акт.</th>
                    <th class="w-12 text-center" title="Защищён от перезаписи при синке Ozon (preserve_sync)">Sync</th>
                    <th class="w-16 text-center">Порядок</th>
                    <th class="w-20"></th>
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
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium" 
                                  style="background: <?= htmlspecialchars($brandColor) ?>; color: white;">
                                <?= $brand ? htmlspecialchars($brand->label()) : '?' ?>
                            </span>
                        </td>
                        <td class="font-mono text-xs"><?= htmlspecialchars((string) ($p['sku'] ?? '')) ?></td>
                        <td class="max-w-[420px]">
                            <div class="line-clamp-2"><?= htmlspecialchars((string) ($p['title'] ?? '')) ?></div>
                        </td>
                        <td class="text-right font-mono text-orange-400"><?= number_format((int) ($p['price_ozon'] ?? 0), 0, ',', ' ') ?> ₽</td>
                        <td class="text-right font-mono <?= !empty($p['price_direct']) ? 'text-emerald-400 font-semibold' : 'text-[#6b6b80]' ?>">
                            <?= !empty($p['price_direct']) ? number_format((int) $p['price_direct'], 0, ',', ' ') . ' ₽' : '—' ?>
                        </td>

                        <!-- QUICK TOGGLE: is_active (Variant 1 admin improvements) -->
                        <!-- UI Agent: Replace the inner <button> content + classes with your styled toggle (pill/switch/icon).
                             Keep the <form method="post"> structure, hidden fields, and quick_toggle marker exactly as-is.
                             The form posts to the same URL and redirects preserving all current filters. -->
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

                        <!-- QUICK TOGGLE: preserve_sync (Variant 1 admin improvements) -->
                        <!-- UI Agent: Same form contract as above. Use any visual you want inside the button (🔒 / shield / check / text).
                             field=preserve_sync, value= the TARGET state (0 or 1). -->
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
                        <td class="text-right pr-4">
                            <?php
                                $editQs = [];
                                if ($search) $editQs[] = 'q=' . urlencode($search);
                                if ($brandFilter) $editQs[] = 'brand=' . urlencode($brandFilter->value);
                                if ($showInactive) $editQs[] = 'inactive=1';
                                $editHref = '?edit=' . (int) $p['id'] . ($editQs ? '&' . implode('&', $editQs) : '');
                            ?>
                            <a href="<?= htmlspecialchars($editHref) ?>" 
                               class="text-orange-400 hover:underline text-sm">Редактировать</a>
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

    <!-- Edit Form -->
    <?php if ($editingProduct): ?>
    <div class="mt-8 bg-[#12121a] border border-orange-500/40 rounded-2xl p-6" id="edit-form">
        <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
            Редактирование товара #<?= (int) $editingProduct['id'] ?>
            <span class="text-sm text-[#6b6b80] font-normal">— <?= htmlspecialchars((string) ($editingProduct['sku'] ?? '')) ?></span>
        </h2>

        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int) $editingProduct['id'] ?>">
            <input type="hidden" name="save_product" value="1">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1 text-[#a0a0b8]">Название</label>
                    <input type="text" name="title" value="<?= htmlspecialchars((string) ($editingProduct['title'] ?? '')) ?>" 
                           class="form-input w-full px-3 py-2 rounded" required>
                </div>
                <div>
                    <label class="block text-sm mb-1 text-[#a0a0b8]">Наша цена (price_direct)</label>
                    <input type="number" name="price_direct" value="<?= htmlspecialchars((string) ($editingProduct['price_direct'] ?? '')) ?>" 
                           class="form-input w-full px-3 py-2 rounded" placeholder="Оставьте пустым, чтобы не переопределять Ozon">
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

            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-orange-600 hover:bg-orange-500 rounded-xl font-semibold">
                    Сохранить изменения
                </button>
                <a href="/admin/" class="px-6 py-2.5 border border-[#2a2a3e] rounded-xl hover:bg-[#1e1e2e]">Отмена</a>
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
