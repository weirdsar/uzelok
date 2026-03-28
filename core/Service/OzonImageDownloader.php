<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

/**
 * Downloads a primary product image from Ozon CDN into public assets.
 */
final class OzonImageDownloader
{
    private const MIN_BYTES = 500;

    public function downloadToFile(string $imageUrl, string $destinationPath): bool
    {
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        $ch = curl_init($imageUrl);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: image/webp,image/apng,image/*,*/*;q=0.8'],
        ]);

        $data = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !is_string($data) || strlen($data) < self::MIN_BYTES) {
            return false;
        }

        if (@file_put_contents($destinationPath, $data) === false) {
            return false;
        }

        return true;
    }

    public static function safeJpegFilename(string $storageSku): string
    {
        if (preg_match('/^[1-9]\d*$/', $storageSku) === 1) {
            return $storageSku . '.jpg';
        }

        $base = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $storageSku);
        $base = trim((string) $base, '._-');
        if ($base === '') {
            $base = 'p_' . md5($storageSku);
        }
        if (strlen($base) > 120) {
            $base = substr($base, 0, 120);
        }

        return $base . '.jpg';
    }
}
