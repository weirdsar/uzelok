<?php

declare(strict_types=1);

namespace Uzelok\Core;

use Uzelok\Core\Service\ProductSeoArticleGenerator;

function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * @param int $priceRub Whole rubles (not kopecks)
 */
function formatPrice(int $priceRub): string
{
    return number_format($priceRub, 0, ',', ' ') . ' ₽';
}

/**
 * Ссылка «Купить на Ozon»: числовой sku в БД = публичный SKU витрины из поля `sku` в ответе Seller API (см. OzonProductAttributes::storefrontSkuForCatalog).
 * Fallback — ozon_url без query (utm/at), чтобы не тащить чужие карточки из устаревших данных.
 *
 * @param array<string, mixed> $product DB row
 */
function productOzonPurchaseUrl(array $product): string
{
    $sku = trim((string) ($product['sku'] ?? ''));
    if ($sku !== '' && ctype_digit($sku) && strlen($sku) >= 8) {
        return 'https://www.ozon.ru/product/' . $sku . '/';
    }

    $legacy = trim((string) ($product['ozon_url'] ?? ''));
    if ($legacy !== '' && str_starts_with($legacy, 'http')) {
        if (str_contains($legacy, 'ozon.ru')) {
            $clean = preg_replace('/[?#].*$/', '', $legacy);
            if (is_string($clean) && $clean !== '') {
                return $clean;
            }
        }

        return $legacy;
    }

    return 'https://www.ozon.ru/';
}

/**
 * URLs for product gallery: user infographics first, then Ozon API (gallery + primary), then local file.
 * Local comes after Ozon so stale demo/Picsum files under image_local_path do not hide real CDN URLs after sync.
 *
 * @param array<string, mixed> $product DB row
 * @return list<string>
 */
function productGalleryUrls(array $product): array
{
    /** Bump when replacing user_content files so browsers bypass long-lived image cache (.htaccess Expires). */
    $userContentVer = '7';

    $withUserContentBust = static function (string $u) use ($userContentVer): string {
        if (!str_starts_with($u, '/assets/images/user-content/')) {
            return $u;
        }
        $sep = str_contains($u, '?') ? '&' : '?';

        return $u . $sep . 'v=' . $userContentVer;
    };

    $seen = [];
    $out = [];
    $push = static function (string $u) use (&$seen, &$out): void {
        if ($u === '' || isset($seen[$u])) {
            return;
        }
        $seen[$u] = true;
        $out[] = $u;
    };

    $ug = trim((string) ($product['user_gallery_json'] ?? ''));
    if ($ug !== '') {
        try {
            $arr = json_decode($ug, true, 64, JSON_THROW_ON_ERROR);
            if (is_array($arr)) {
                foreach ($arr as $u) {
                    if (!is_string($u) || $u === '') {
                        continue;
                    }
                    if (str_starts_with($u, '/')) {
                        $push($withUserContentBust($u));
                    } elseif (str_starts_with($u, 'http')) {
                        $push($u);
                    }
                }
            }
        } catch (\JsonException) {
        }
    }

    $gj = trim((string) ($product['gallery_json'] ?? ''));
    if ($gj !== '') {
        try {
            $arr = json_decode($gj, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($arr)) {
                foreach ($arr as $u) {
                    if (is_string($u) && str_starts_with($u, 'http')) {
                        $push($u);
                    }
                }
            }
        } catch (\JsonException) {
        }
    }

    $oz = trim((string) ($product['image_ozon_url'] ?? ''));
    if ($oz !== '' && str_starts_with($oz, 'http')) {
        $push($oz);
    }

    $local = trim((string) ($product['image_local_path'] ?? ''));
    if ($local !== '') {
        $push(str_starts_with($local, 'http') ? $local : '/' . ltrim($local, '/'));
    }

    return $out;
}

/**
 * Главное изображение для превью в каталоге: первый кадр общей галереи (инфографика, если есть).
 *
 * @param array<string, mixed> $product DB row
 * @return array{src: string, ozonRemote: bool}
 */
function productCardPrimaryImage(array $product): array
{
    $urls = productGalleryUrls($product);
    if ($urls !== []) {
        $primary = $urls[0];
        $src = str_starts_with($primary, 'http') ? $primary : '/' . ltrim($primary, '/');
        $ozonRemote = str_starts_with($primary, 'http')
            && (str_contains($primary, 'ozone.ru') || str_contains($primary, 'ozon.ru'));

        return ['src' => $src, 'ozonRemote' => $ozonRemote];
    }

    $imgLocal = trim((string) ($product['image_local_path'] ?? ''));
    if ($imgLocal !== '') {
        $src = str_starts_with($imgLocal, 'http') ? $imgLocal : '/' . ltrim($imgLocal, '/');
        $ozonRemote = str_starts_with($imgLocal, 'http')
            && (str_contains($imgLocal, 'ozone.ru') || str_contains($imgLocal, 'ozon.ru'));

        return ['src' => $src, 'ozonRemote' => $ozonRemote];
    }

    $imgRemote = trim((string) ($product['image_ozon_url'] ?? ''));
    if ($imgRemote !== '' && str_starts_with($imgRemote, 'http')) {
        return ['src' => $imgRemote, 'ozonRemote' => true];
    }

    return ['src' => '', 'ozonRemote' => false];
}

/**
 * @param array<string, mixed> $product DB row
 * @return list<string>
 */
function productVideoUrls(array $product): array
{
    $vj = trim((string) ($product['videos_json'] ?? ''));
    if ($vj === '') {
        return [];
    }
    try {
        $arr = json_decode($vj, true, 256, JSON_THROW_ON_ERROR);
        if (!is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $u) {
            if (is_string($u) && str_starts_with($u, 'http')) {
                $out[] = $u;
            }
        }

        return $out;
    } catch (\JsonException) {
        return [];
    }
}

/**
 * Ordered gallery: all images (as on Ozon card), then videos.
 *
 * @param array<string, mixed> $product DB row
 * @return list<array{type: string, url: string}>
 */
function productMediaItems(array $product): array
{
    $items = [];
    foreach (productGalleryUrls($product) as $u) {
        $items[] = ['type' => 'image', 'url' => $u];
    }
    foreach (productVideoUrls($product) as $u) {
        $items[] = ['type' => 'video', 'url' => $u];
    }

    return $items;
}

function productVideoYoutubeEmbedSrc(string $url): ?string
{
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([a-zA-Z0-9_-]{6,})#', $url, $m) === 1) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
    }
    if (preg_match('#youtube\.com/embed/([a-zA-Z0-9_-]{6,})#', $url, $m) === 1) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
    }

    return null;
}

function productVideoRutubeEmbedSrc(string $url): ?string
{
    if (preg_match('#rutube\.ru/(?:video|play/embed)/(?:private/)?([a-f0-9]{32})#i', $url, $m) === 1) {
        return 'https://rutube.ru/play/embed/' . $m[1];
    }
    if (preg_match('#rutube\.ru/video/(?:private/)?(\d{6,})#', $url, $m) === 1) {
        return 'https://rutube.ru/play/embed/' . $m[1];
    }

    return null;
}

function productVideoIsDirectStreamUrl(string $url): bool
{
    return preg_match('/\.(mp4|webm|m3u8)(\?|#|$)/i', $url) === 1;
}

/**
 * SEO-статья для карточки товара: из БД или сгенерированная по названию и описанию.
 *
 * @param array<string, mixed> $product DB row
 */
function productSeoArticlePlain(array $product): string
{
    $stored = trim((string) ($product['seo_article'] ?? ''));
    if ($stored !== '') {
        return $stored;
    }

    return ProductSeoArticleGenerator::generate($product);
}

function generateCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Only generate a new token if one doesn't already exist in the session.
    // Regenerating on every request breaks form submissions.
    if (empty($_SESSION['_csrf_token'])) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
    }

    return $_SESSION['_csrf_token'];
}

function validateCsrfToken(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $stored = $_SESSION['_csrf_token'] ?? '';

    return $stored !== '' && hash_equals($stored, $token);
}

/**
 * Returns the stored admin password (either a modern hash from config/admin.hash
 * or the legacy plaintext value from config.php).
 */
function getAdminPassword(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $baseDir = dirname(__DIR__);
    $hashFile = $baseDir . '/config/admin.hash';

    if (is_readable($hashFile)) {
        $hash = trim((string) @file_get_contents($hashFile));
        if ($hash !== '') {
            $cached = $hash;
            return $cached;
        }
    }

    // Fallback to config.php (backward compatibility)
    $configPath = $baseDir . '/config/config.php';
    if (is_readable($configPath)) {
        $config = @include $configPath;
        if (is_array($config)) {
            $cached = (string) ($config['admin']['password'] ?? '');
            return $cached;
        }
    }

    $cached = '';
    return $cached;
}

/**
 * Verifies the password entered via HTTP Basic Auth.
 * Supports password_hash() (preferred) and legacy plaintext.
 */
function verifyAdminPassword(string $input): bool
{
    $stored = getAdminPassword();
    if ($stored === '') {
        return false;
    }

    // Modern hash (bcrypt, argon2 etc.)
    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2')) {
        return password_verify($input, $stored);
    }

    // Legacy plaintext (timing-safe comparison)
    return hash_equals($stored, $input);
}

/**
 * Changes the admin password.
 * Stores a secure hash in config/admin.hash (gitignored).
 * Returns true on success.
 */
function setAdminPassword(string $newPassword): bool
{
    if (mb_strlen(trim($newPassword)) < 8) {
        return false;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($hash === false) {
        return false;
    }

    $baseDir = dirname(__DIR__);
    $hashFile = $baseDir . '/config/admin.hash';
    $configDir = dirname($hashFile);

    if (!is_dir($configDir)) {
        @mkdir($configDir, 0755, true);
    }

    $fp = @fopen($hashFile, 'c+');
    if ($fp === false) {
        return false;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }

    ftruncate($fp, 0);
    fwrite($fp, $hash . "\n");
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    @chmod($hashFile, 0600);

    return true;
}

function logLine(string $level, string $message, string $logPath): void
{
    $dir = dirname($logPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $line = sprintf(
        "[%s] [%s] %s\n",
        (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        $level,
        $message
    );
    file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}

function logError(string $message, string $logPath): void
{
    logLine('ERROR', $message, $logPath);
}
