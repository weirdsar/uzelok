<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

/**
 * Normalizes Ozon Seller API product fields for the site DB and UI.
 */
final class OzonProductAttributes
{
    /**
     * All distinct image URLs from Ozon product info (order: primary_image, images, color_image, images360).
     * Skips URLs that look like direct video files / embeds.
     *
     * @return list<string>
     */
    public static function extractGalleryUrls(array $item): array
    {
        $seen = [];
        $out = [];
        foreach (['primary_image', 'images', 'color_image', 'images360'] as $key) {
            self::collectImageUrlsFromList($item[$key] ?? null, $seen, $out);
        }

        return $out;
    }

    /**
     * Video URLs from product info, rich JSON, and optional extra trees (e.g. /v3/product/info/attributes row).
     *
     * @param list<array<string, mixed>> $extraVideoContexts
     * @return list<string>
     */
    public static function extractVideoUrls(array $item, array $extraVideoContexts = []): array
    {
        $seen = [];
        $out = [];
        $push = static function (string $u) use (&$seen, &$out): void {
            if (!str_starts_with($u, 'http')) {
                return;
            }
            if (!self::looksLikeVideoUrl($u)) {
                return;
            }
            if (isset($seen[$u])) {
                return;
            }
            $seen[$u] = true;
            $out[] = $u;
        };

        foreach (['video', 'video_url', 'template_video', 'product_video'] as $key) {
            $v = $item[$key] ?? null;
            if (is_string($v)) {
                $push($v);
            } elseif (is_array($v)) {
                foreach ($v as $x) {
                    if (is_string($x)) {
                        $push($x);
                    } elseif (is_array($x)) {
                        foreach (['url', 'link', 'src', 'value'] as $ik) {
                            if (isset($x[$ik]) && is_string($x[$ik])) {
                                $push($x[$ik]);
                            }
                        }
                    }
                }
            }
        }

        $videos = $item['videos'] ?? null;
        if (is_array($videos)) {
            foreach ($videos as $x) {
                if (is_string($x)) {
                    $push($x);
                } elseif (is_array($x)) {
                    foreach (['url', 'link', 'src'] as $ik) {
                        if (isset($x[$ik]) && is_string($x[$ik])) {
                            $push($x[$ik]);
                        }
                    }
                }
            }
        }

        foreach (['rich_json', 'rich_content_json'] as $rjKey) {
            $raw = $item[$rjKey] ?? null;
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            try {
                $decoded = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
                self::walkForVideoUrls($decoded, $push, 0, 16);
            } catch (\JsonException) {
            }
        }

        self::walkForVideoUrls($item, $push, 0, 8);

        foreach ($extraVideoContexts as $ctx) {
            if (is_array($ctx) && $ctx !== []) {
                self::walkForVideoUrls($ctx, $push, 0, 14);
            }
        }

        return $out;
    }

    public static function extractPrimaryImageUrl(array $item): string
    {
        $urls = self::extractGalleryUrls($item);

        return $urls[0] ?? '';
    }

    /**
     * Plain text for SQLite / карточки (без HTML).
     */
    public static function normalizeDescription(string $raw): string
    {
        $t = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/\s+/u', ' ', $t) ?? '';
        $t = trim($t);
        if (mb_strlen($t) > 60000) {
            $t = mb_substr($t, 0, 60000);
        }

        return $t;
    }

    /**
     * @param list<string> $out
     */
    private static function collectImageUrlsFromList(mixed $v, array &$seen, array &$out): void
    {
        if ($v === null) {
            return;
        }
        if (is_string($v)) {
            self::appendHttpsImageUrl($v, $seen, $out);

            return;
        }
        if (!is_array($v)) {
            return;
        }
        foreach ($v as $x) {
            if (is_string($x)) {
                self::appendHttpsImageUrl($x, $seen, $out);
            } elseif (is_array($x)) {
                foreach (['url', 'file_name', 'link', 'primary_url', 'image'] as $ik) {
                    if (isset($x[$ik]) && is_string($x[$ik])) {
                        self::appendHttpsImageUrl($x[$ik], $seen, $out);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, bool> $seen
     * @param list<string> $out
     */
    /**
     * Приводит URL картинки к https-строке (Ozon иногда отдаёт //cdn… без схемы).
     */
    public static function normalizeImageUrl(string $u): string
    {
        $u = trim($u);
        if ($u === '') {
            return '';
        }
        if (str_starts_with($u, '//')) {
            return 'https:' . $u;
        }
        if (str_starts_with($u, 'http://') || str_starts_with($u, 'https://')) {
            return $u;
        }

        return '';
    }

    /**
     * Объединяет списки URL без дублей; порядок: сначала $primary, затем $secondary.
     *
     * @param list<string> $primary
     * @param list<string> $secondary
     * @return list<string>
     */
    public static function mergeGalleryUrlLists(array $primary, array $secondary): array
    {
        $seen = [];
        $out = [];
        $push = static function (string $raw) use (&$seen, &$out): void {
            $u = self::normalizeImageUrl($raw);
            if ($u === '' || self::looksLikeVideoUrl($u)) {
                return;
            }
            if (isset($seen[$u])) {
                return;
            }
            $seen[$u] = true;
            $out[] = $u;
        };

        foreach ($primary as $x) {
            if (is_string($x)) {
                $push($x);
            }
        }
        foreach ($secondary as $x) {
            if (is_string($x)) {
                $push($x);
            }
        }

        return $out;
    }

    private static function appendHttpsImageUrl(string $u, array &$seen, array &$out): void
    {
        $u = self::normalizeImageUrl($u);
        if ($u === '') {
            return;
        }
        if (self::looksLikeVideoUrl($u)) {
            return;
        }
        if (isset($seen[$u])) {
            return;
        }
        $seen[$u] = true;
        $out[] = $u;
    }

    private static function looksLikeVideoUrl(string $u): bool
    {
        $lower = strtolower($u);
        if (preg_match('/\.(mp4|webm|m3u8)(\?|#|$)/i', $u) === 1) {
            return true;
        }
        if (str_contains($lower, 'youtube.com/') || str_contains($lower, 'youtu.be/')) {
            return true;
        }
        if (str_contains($lower, 'rutube.ru/')) {
            return true;
        }
        if (str_contains($lower, 'vk.com/video') || str_contains($lower, 'vkvideo.ru')) {
            return true;
        }
        if (str_contains($lower, 'ozonusercontent.com') && str_contains($lower, 'video')) {
            return true;
        }
        if (preg_match('~ozon\.ru/[^?\s#]*video~i', $u) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param callable(string): void $push
     */
    private static function walkForVideoUrls(mixed $node, callable $push, int $depth, int $maxDepth): void
    {
        if ($depth > $maxDepth) {
            return;
        }
        if (is_string($node)) {
            $push($node);

            return;
        }
        if (!is_array($node)) {
            return;
        }
        foreach ($node as $v) {
            self::walkForVideoUrls($v, $push, $depth + 1, $maxDepth);
        }
    }

    /**
     * Публичный ID карточки на Ozon (фрагмент URL /product/…). В ответах Seller API он в `product_id`;
     * поле `id` иногда — другой внутренний идентификатор, его нельзя подставлять в ссылку на карточку.
     */
    public static function marketplaceProductIdFromItem(array $item): int
    {
        $byPid = (int) ($item['product_id'] ?? 0);
        if ($byPid > 0) {
            return $byPid;
        }

        return (int) ($item['id'] ?? 0);
    }

    /**
     * Для строк attributes и вложенного `product_info`.
     */
    public static function marketplaceProductIdFromRow(array $row): int
    {
        $byPid = (int) ($row['product_id'] ?? 0);
        if ($byPid > 0) {
            return $byPid;
        }
        $byId = (int) ($row['id'] ?? 0);
        if ($byId > 0) {
            return $byId;
        }
        if (isset($row['product_info']) && is_array($row['product_info'])) {
            $pi = $row['product_info'];
            $nested = (int) ($pi['product_id'] ?? 0);
            if ($nested > 0) {
                return $nested;
            }

            return (int) ($pi['id'] ?? 0);
        }

        return 0;
    }
}
