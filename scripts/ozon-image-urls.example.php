<?php

declare(strict_types=1);

/**
 * Copy to `ozon-image-urls.local.php` (gitignored) and paste real Ozon CDN URLs.
 *
 * How to get URLs (Browser MCP or Chrome DevTools on each PDP):
 * - View page source → search for `og:image` or `https://ir.ozon.ru` / `https://cdn1.ozon.ru`
 * - Or Network tab → filter "jpg" / "webp" → first gallery image (prefer /wc1000/ or replace /wc50/ with /wc1000/)
 *
 * Leave empty strings for SKUs you skip; merge overrides defaults in download-images.php.
 */
return [
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
