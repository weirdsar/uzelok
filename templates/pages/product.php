<?php
/** @var array<string, mixed> $productDetail */
/** @var array<string, mixed> $config */
use Uzelok\Core\Brand;

$brand = Brand::tryFrom((string) ($productDetail['brand_type'] ?? ''));
$brandColor = $brand !== null ? $brand->color() : '#6b6b80';
$brandLabel = $brand !== null ? $brand->label() : '—';
$title = (string) ($productDetail['title'] ?? '');
$pid = (int) ($productDetail['id'] ?? 0);
$priceOzon = (int) ($productDetail['price_ozon'] ?? 0);
$priceDirect = isset($productDetail['price_direct']) && $productDetail['price_direct'] !== null && $productDetail['price_direct'] !== ''
    ? (int) $productDetail['price_direct']
    : null;
$ozonUrl = (string) ($productDetail['ozon_url'] ?? '#');
$offerId = trim((string) ($productDetail['offer_id'] ?? ''));
$skuRow = trim((string) ($productDetail['sku'] ?? ''));
$category = trim((string) ($productDetail['category'] ?? ''));
$desc = trim((string) ($productDetail['description'] ?? ''));

/** @var list<string> $galleryUrls */
$galleryUrls = \Uzelok\Core\productGalleryUrls($productDetail);
/** @var list<string> $videoUrls */
$videoUrls = \Uzelok\Core\productVideoUrls($productDetail);
/** @var list<array{type: string, url: string}> $mediaItems */
$mediaItems = \Uzelok\Core\productMediaItems($productDetail);

$imgNoReferrer = static function (string $u): bool {
    return str_contains($u, 'ozone.ru') || str_contains($u, 'ozon.ru');
};

$seoArticlePlain = \Uzelok\Core\productSeoArticlePlain($productDetail);
$seoArticleHeadline = 'Как применять «' . $title . '» и зачем он вам нужен';
$seoParagraphs = preg_split('/\n\s*\n/', $seoArticlePlain, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$appUrl = rtrim((string) ($config['app_url'] ?? 'https://uzelok64.ru'), '/');
$productCanonical = $appUrl . '/?page=product&id=' . (string) $pid;
?>
<section class="px-4 pb-16 pt-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-4xl">
        <nav class="mb-8 text-sm text-[#6b6b80]" aria-label="Навигация">
            <a href="/?page=home#catalog" class="text-[#a0a0b8] transition hover:text-[#f97316]">← К каталогу</a>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#2a2a3e] pb-6">
            <div>
                <span class="inline-block rounded-md px-2 py-1 text-xs font-semibold text-white" style="background-color: <?= htmlspecialchars($brandColor, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($brandLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <h1 class="mt-3 text-2xl font-bold text-white sm:text-3xl"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div class="text-right">
                <p class="font-mono text-2xl font-bold text-[#f97316]"><?= htmlspecialchars(\Uzelok\Core\formatPrice($priceOzon), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-[#6b6b80]">цена на Ozon</p>
                <?php if ($priceDirect !== null && $priceDirect > 0 && $priceDirect < $priceOzon) : ?>
                    <p class="mt-1 text-sm font-semibold text-emerald-500">Наша цена: <?= htmlspecialchars(\Uzelok\Core\formatPrice($priceDirect), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($mediaItems !== []) : ?>
            <div class="mt-8 space-y-4" id="product-gallery">
                <p class="text-xs text-[#6b6b80] md:hidden">Листайте фото и видео горизонтально →</p>
                <div class="product-gallery-strip scroll-smooth pb-2" id="product-gallery-strip">
                    <?php foreach ($mediaItems as $i => $media) : ?>
                        <?php
                        $u = $media['url'];
                        $isVideo = ($media['type'] === 'video');
                        $yt = $isVideo ? \Uzelok\Core\productVideoYoutubeEmbedSrc($u) : null;
                        $rt = ($isVideo && $yt === null) ? \Uzelok\Core\productVideoRutubeEmbedSrc($u) : null;
                        $directVideo = $isVideo && $yt === null && $rt === null && \Uzelok\Core\productVideoIsDirectStreamUrl($u);
                        ?>
                        <figure class="product-gallery-slide rounded-xl border border-[#2a2a3e] bg-[#12121a] p-2">
                            <?php if (!$isVideo) : ?>
                                <img
                                    src="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($title . ' — фото ' . (string) ($i + 1), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mx-auto max-h-[min(70vh,28rem)] w-full object-contain"
                                    loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"
                                    decoding="async"
                                    <?= $imgNoReferrer($u) ? 'referrerpolicy="no-referrer"' : '' ?>
                                >
                            <?php elseif ($yt !== null) : ?>
                                <div class="mx-auto aspect-video w-full max-h-[min(70vh,28rem)] overflow-hidden rounded-lg bg-black">
                                    <iframe
                                        class="h-full w-full"
                                        src="<?= htmlspecialchars($yt, ENT_QUOTES, 'UTF-8') ?>"
                                        title="<?= htmlspecialchars($title . ' — видео', ENT_QUOTES, 'UTF-8') ?>"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowfullscreen
                                        loading="lazy"
                                    ></iframe>
                                </div>
                            <?php elseif ($rt !== null) : ?>
                                <div class="mx-auto aspect-video w-full max-h-[min(70vh,28rem)] overflow-hidden rounded-lg bg-black">
                                    <iframe
                                        class="h-full w-full"
                                        src="<?= htmlspecialchars($rt, ENT_QUOTES, 'UTF-8') ?>"
                                        title="<?= htmlspecialchars($title . ' — видео', ENT_QUOTES, 'UTF-8') ?>"
                                        allow="clipboard-write; autoplay"
                                        allowfullscreen
                                        loading="lazy"
                                    ></iframe>
                                </div>
                            <?php elseif ($directVideo) : ?>
                                <video
                                    class="mx-auto max-h-[min(70vh,28rem)] w-full rounded-lg"
                                    controls
                                    playsinline
                                    preload="metadata"
                                    <?= $imgNoReferrer($u) ? 'referrerpolicy="no-referrer"' : '' ?>
                                >
                                    <source src="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>">
                                </video>
                            <?php else : ?>
                                <div class="flex min-h-[12rem] flex-col items-center justify-center gap-3 px-4 py-8 text-center">
                                    <span class="text-4xl" aria-hidden="true">▶</span>
                                    <p class="text-sm text-[#a0a0b8]">Видео откроется на Ozon или в сервисе площадки</p>
                                    <a href="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-[#f97316] px-4 py-2 text-sm font-semibold text-white hover:bg-[#ea580c]">Открыть видео</a>
                                </div>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
                <?php if (count($mediaItems) > 1) : ?>
                    <div class="flex flex-wrap gap-2" id="product-gallery-thumbs" role="tablist" aria-label="Миниатюры">
                        <?php foreach ($mediaItems as $i => $media) : ?>
                            <?php
                            $u = $media['url'];
                            $isVideo = ($media['type'] === 'video');
                            ?>
                            <button
                                type="button"
                                class="product-gallery-thumb h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 ring-offset-2 ring-offset-[#0a0a0f] transition hover:border-[#f97316]/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f97316] <?= $i === 0 ? 'border-[#f97316]' : 'border-transparent' ?>"
                                data-gallery-index="<?= $i ?>"
                                aria-label="<?= $isVideo ? 'Показать видео' : 'Показать фото ' . (string) ($i + 1) ?>"
                            >
                                <?php if (!$isVideo) : ?>
                                    <img
                                        src="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>"
                                        alt=""
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                        <?= $imgNoReferrer($u) ? 'referrerpolicy="no-referrer"' : '' ?>
                                    >
                                <?php else : ?>
                                    <span class="flex h-full w-full items-center justify-center bg-[#1a1a2e] text-xl text-[#f97316]" aria-hidden="true">▶</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="mt-8 flex aspect-[4/3] items-center justify-center rounded-xl border border-[#2a2a3e] bg-[#12121a] text-[#6b6b80]">
                <span class="text-6xl opacity-30" aria-hidden="true">📷</span>
            </div>
        <?php endif; ?>

        <div class="mt-10 grid gap-10 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <h2 class="text-lg font-semibold text-white">Описание</h2>
                <?php if ($desc !== '') : ?>
                    <div class="mt-3 whitespace-pre-line text-sm leading-relaxed text-[#a0a0b8]"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></div>
                <?php else : ?>
                    <p class="mt-3 text-sm text-[#6b6b80]">Описание появится после синхронизации с Ozon.</p>
                <?php endif; ?>
            </div>
            <aside class="lg:col-span-2">
                <h2 class="text-lg font-semibold text-white">Характеристики</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-4 border-b border-[#2a2a3e]/80 py-2">
                        <dt class="text-[#6b6b80]">Бренд</dt>
                        <dd class="text-right text-[#e8e8f0]"><?= htmlspecialchars($brandLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <?php if ($offerId !== '') : ?>
                        <div class="flex justify-between gap-4 border-b border-[#2a2a3e]/80 py-2">
                            <dt class="text-[#6b6b80]">Артикул продавца</dt>
                            <dd class="text-right font-mono text-xs text-[#e8e8f0]"><?= htmlspecialchars($offerId, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($skuRow !== '') : ?>
                        <div class="flex justify-between gap-4 border-b border-[#2a2a3e]/80 py-2">
                            <dt class="text-[#6b6b80]">ID в каталоге</dt>
                            <dd class="text-right font-mono text-xs text-[#e8e8f0]"><?= htmlspecialchars($skuRow, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($category !== '') : ?>
                        <div class="flex justify-between gap-4 border-b border-[#2a2a3e]/80 py-2">
                            <dt class="text-[#6b6b80]">Категория</dt>
                            <dd class="text-right text-[#e8e8f0]"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-[#6b6b80]">Медиа (как на Ozon)</dt>
                        <dd class="text-right text-[#e8e8f0]"><?= count($galleryUrls) ?> фото<?= count($videoUrls) > 0 ? ', ' . count($videoUrls) . ' видео' : '' ?></dd>
                    </div>
                </dl>
            </aside>
        </div>

        <article
            class="mt-12 rounded-xl border border-[#2a2a3e] bg-[#12121a]/40 p-6 sm:p-8"
            itemscope
            itemtype="https://schema.org/Article"
        >
            <meta itemprop="headline" content="<?= htmlspecialchars($seoArticleHeadline, ENT_QUOTES, 'UTF-8') ?>">
            <meta itemprop="inLanguage" content="ru-RU">
            <link itemprop="mainEntityOfPage" href="<?= htmlspecialchars($productCanonical, ENT_QUOTES, 'UTF-8') ?>">
            <div itemprop="publisher" itemscope itemtype="https://schema.org/Organization">
                <meta itemprop="name" content="УЗЕЛОК64">
            </div>
            <h2 class="text-xl font-bold leading-snug text-white sm:text-2xl"><?= htmlspecialchars($seoArticleHeadline, ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-2 text-xs text-[#6b6b80]">Практический гид для покупателя: сценарии использования и польза аксессуара в быту, в дороге и в мастерской.</p>
            <div class="mt-6 space-y-4 text-sm leading-relaxed text-[#a0a0b8]" itemprop="articleBody">
                <?php foreach ($seoParagraphs as $para) : ?>
                    <?php
                    $p = trim((string) $para);
                    if ($p === '') {
                        continue;
                    }
                    ?>
                    <p><?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; ?>
            </div>
        </article>
        <?php
        $ldArticle = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $seoArticleHeadline,
            'inLanguage' => 'ru-RU',
            'articleBody' => $seoArticlePlain,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $productCanonical],
            'publisher' => ['@type' => 'Organization', 'name' => 'УЗЕЛОК64'],
        ];
        ?>
        <?php
        $ldJson = json_encode($ldArticle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($ldJson !== false) :
            ?>
        <script type="application/ld+json"><?= $ldJson ?></script>
        <?php endif; ?>

        <div class="mt-12 flex flex-col gap-3 border-t border-[#2a2a3e] pt-8 sm:flex-row sm:justify-center">
            <a href="<?= htmlspecialchars($ozonUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn-ozon inline-flex flex-1 items-center justify-center rounded-xl border border-[#2a2a3e] px-6 py-3 text-center text-sm font-semibold text-[#e8e8f0] transition hover:border-[#f97316]/50 sm:max-w-xs">Купить на Ozon</a>
            <button type="button" class="btn-order inline-flex flex-1 items-center justify-center rounded-xl bg-[#f97316] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#ea580c] sm:max-w-xs" data-product-id="<?= $pid ?>" data-product-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">Заказать дешевле</button>
        </div>
    </div>
</section>
