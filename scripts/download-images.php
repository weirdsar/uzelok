<?php

declare(strict_types=1);

/**
 * Download primary product images and set products.image_local_path.
 *
 * Usage:
 *   php scripts/download-images.php
 *   php scripts/download-images.php --demo   # Picsum placeholders (no Ozon URLs needed)
 *
 * Real Ozon URLs: copy scripts/ozon-image-urls.example.php → scripts/ozon-image-urls.local.php
 * and paste CDN URLs from og:image or gallery (see example file comments).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';

use Uzelok\Core\Database;

$dbPath = $config['paths']['database'];
$publicPath = $config['paths']['public'] ?? dirname(__DIR__) . '/public_html';
if (!is_string($dbPath)) {
    fwrite(STDERR, "Invalid database path.\n");
    exit(1);
}

$db = Database::getInstance($dbPath);
$imagesDir = rtrim((string) $publicPath, '/\\') . '/assets/images/products';

if (!is_dir($imagesDir) && !mkdir($imagesDir, 0755, true) && !is_dir($imagesDir)) {
    fwrite(STDERR, "Cannot create: {$imagesDir}\n");
    exit(1);
}

$demo = in_array('--demo', $argv, true);

/** @var array<string, string> $imageUrls */
$imageUrls = [
    'BUY-DOMKRAT' => '',
    'BUY-COMPRESSOR' => '',
    'BUY-PROVODA' => '',
    'BUY-STROPA' => '',
    'BUY-ELECTRIKA' => '',
    'BUY-APTECHKA' => '',
    'BUY-INSTRUMENT' => '',
    'BATYA-REZTSY' => '',
    'BATYA-SKRUTKA' => '',
    'BATYA-AVTO' => '',
    'VOLNA-YAKOR' => '',
];

$localFile = dirname(__DIR__) . '/scripts/ozon-image-urls.local.php';
if (is_file($localFile)) {
    /** @var array<string, string> $loaded */
    $loaded = require $localFile;
    foreach ($loaded as $sku => $url) {
        if (is_string($sku) && is_string($url) && $url !== '') {
            $imageUrls[$sku] = $url;
        }
    }
}

if ($demo) {
    foreach (array_keys($imageUrls) as $sku) {
        $seed = strtolower(str_replace(['-', '_'], '', $sku));
        $imageUrls[$sku] = 'https://picsum.photos/seed/' . rawurlencode($seed) . '/800/600';
    }
    echo "[INFO] --demo: using picsum.photos placeholders (replace with Ozon URLs via ozon-image-urls.local.php)\n\n";
}

$downloaded = 0;
$errors = 0;

foreach ($imageUrls as $sku => $url) {
    if ($url === '') {
        echo "[SKIP] {$sku}: no URL (fill scripts/ozon-image-urls.local.php or use --demo)\n";
        continue;
    }

    $filename = strtolower($sku) . '.jpg';
    $filepath = $imagesDir . DIRECTORY_SEPARATOR . $filename;
    $relativePath = '/assets/images/products/' . $filename;

    echo "[DOWNLOAD] {$sku}\n  {$url}\n";

    $ch = curl_init($url);
    if ($ch === false) {
        echo "[ERROR] {$sku}: curl_init failed\n";
        ++$errors;
        continue;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: image/webp,image/apng,image/*,*/*;q=0.8'],
    ]);

    $imageData = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($imageData) || strlen($imageData) < 500) {
        echo "[ERROR] {$sku}: HTTP {$httpCode} {$curlErr}\n";
        ++$errors;
        continue;
    }

    if (file_put_contents($filepath, $imageData) === false) {
        echo "[ERROR] {$sku}: write failed\n";
        ++$errors;
        continue;
    }

    $kb = (int) round(strlen($imageData) / 1024);
    echo "[SAVED] {$filepath} ({$kb} KB)\n";

    if (extension_loaded('gd')) {
        resizeImageIfNeeded($filepath, 800, 85);
    }

    $db->query(
        'UPDATE products SET image_local_path = :path, updated_at = datetime(\'now\') WHERE sku = :sku',
        [':path' => $relativePath, ':sku' => $sku]
    );
    echo "[DB] {$sku} → {$relativePath}\n\n";
    ++$downloaded;
}

echo "Done: {$downloaded} downloaded, {$errors} errors.\n";
exit($errors > 0 && $downloaded === 0 ? 1 : 0);

/**
 * Downscale JPEG if wider than $maxWidth (keeps aspect ratio).
 */
function resizeImageIfNeeded(string $path, int $maxWidth = 800, int $quality = 85): void
{
    $info = @getimagesize($path);
    if ($info === false || ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
        return;
    }

    if ($info[0] <= $maxWidth) {
        return;
    }

    $src = @imagecreatefromjpeg($path);
    if ($src === false) {
        return;
    }

    $ratio = $maxWidth / $info[0];
    $newHeight = (int) round($info[1] * $ratio);
    $dst = imagecreatetruecolor($maxWidth, $newHeight);
    if ($dst === false) {
        imagedestroy($src);

        return;
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, $newHeight, $info[0], $info[1]);
    imagejpeg($dst, $path, $quality);
    imagedestroy($src);
    imagedestroy($dst);

    echo '[RESIZE] ' . $path . ": {$info[0]}x{$info[1]} → {$maxWidth}x{$newHeight}\n";
}
