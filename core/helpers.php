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
 * URLs for product gallery: user infographics first (user_gallery_json), then local main, Ozon CDN, primary Ozon URL.
 *
 * @param array<string, mixed> $product DB row
 * @return list<string>
 */
function productGalleryUrls(array $product): array
{
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
                        $push($u);
                    } elseif (str_starts_with($u, 'http')) {
                        $push($u);
                    }
                }
            }
        } catch (\JsonException) {
        }
    }

    $local = trim((string) ($product['image_local_path'] ?? ''));
    if ($local !== '') {
        $push(str_starts_with($local, 'http') ? $local : '/' . ltrim($local, '/'));
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
    $token = bin2hex(random_bytes(32));
    $_SESSION['_csrf_token'] = $token;

    return $token;
}

function validateCsrfToken(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $stored = $_SESSION['_csrf_token'] ?? '';

    return $stored !== '' && hash_equals($stored, $token);
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
