<?php
/** @var array<string, mixed> $product */
use Uzelok\Core\Brand;

$brand = Brand::tryFrom((string) ($product['brand_type'] ?? ''));
$brandColor = $brand !== null ? $brand->color() : '#6b6b80';
$brandLabel = $brand !== null ? $brand->label() : '?';

$priceOzon = (int) ($product['price_ozon'] ?? 0);
$priceDirect = isset($product['price_direct']) && $product['price_direct'] !== null && $product['price_direct'] !== ''
    ? (int) $product['price_direct']
    : null;

$primaryImg = \Uzelok\Core\productCardPrimaryImage($product);
$imgSrc = $primaryImg['src'];
$imgExtraAttrs = $primaryImg['ozonRemote'] ? ' referrerpolicy="no-referrer"' : '';

$title = (string) ($product['title'] ?? '');
$ozonUrl = (string) ($product['ozon_url'] ?? '#');
$pid = (int) ($product['id'] ?? 0);
$brandType = htmlspecialchars((string) ($product['brand_type'] ?? ''), ENT_QUOTES, 'UTF-8');
$productPageUrl = '/?page=product&id=' . $pid;
$ariaProduct = 'Подробнее: ' . $title;
?>
<article
    class="product-card group flex flex-col overflow-hidden rounded-xl border border-[#2a2a3e] bg-[#1e1e2e] transition-all duration-300 hover:border-[#f97316]/50"
    data-brand="<?= $brandType ?>"
    data-product-id="<?= $pid ?>"
>
    <a
        href="<?= htmlspecialchars($productPageUrl, ENT_QUOTES, 'UTF-8') ?>"
        class="flex flex-1 flex-col focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f97316] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0a0f]"
        aria-label="<?= htmlspecialchars($ariaProduct, ENT_QUOTES, 'UTF-8') ?>"
    >
        <div class="relative aspect-[4/3] overflow-hidden bg-[#12121a]">
            <div class="card-image h-full w-full overflow-hidden">
                <?php if ($imgSrc !== '') : ?>
                    <img
                        src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>"
                        alt=""
                        class="h-full w-full object-cover object-center transition duration-300 group-hover:scale-[1.02]"
                        loading="lazy"
                        decoding="async"<?= $imgExtraAttrs ?>
                    >
                <?php else : ?>
                    <div class="flex h-full w-full items-center justify-center bg-[#1a1a2e] text-[#6b6b80]">
                        <svg class="h-16 w-16 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                <?php endif; ?>
            </div>
            <span class="pointer-events-none absolute left-2 top-2 rounded-md px-2 py-1 text-xs font-semibold text-white shadow" style="background-color: <?= htmlspecialchars($brandColor, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($brandLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="flex flex-1 flex-col p-4">
            <h3 class="line-clamp-2 text-base font-semibold leading-snug text-white group-hover:text-[#f97316]"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
            <?php
            $descShow = trim((string) ($product['description'] ?? ''));
            if ($descShow !== '') :
                ?>
                <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-[#a0a0b8]"><?= htmlspecialchars($descShow, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <div class="mt-3 font-mono text-xl font-bold text-[#f97316]"><?= htmlspecialchars(\Uzelok\Core\formatPrice($priceOzon), ENT_QUOTES, 'UTF-8') ?></div>
            <p class="text-xs text-[#6b6b80]">цена на Ozon · нажмите для подробностей</p>
            <?php if ($priceDirect !== null && $priceDirect > 0 && $priceDirect < $priceOzon) : ?>
                <p class="mt-1 text-sm font-semibold text-emerald-500">Наша цена: <?= htmlspecialchars(\Uzelok\Core\formatPrice($priceDirect), ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
    </a>
    <div class="flex flex-col gap-2 border-t border-[#2a2a3e] p-4 pt-3 sm:flex-row">
        <a href="<?= htmlspecialchars($ozonUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn-ozon inline-flex flex-1 items-center justify-center rounded-lg border border-[#2a2a3e] px-3 py-2 text-center text-sm font-medium text-[#e8e8f0] transition hover:border-[#f97316]/50">Купить на Ozon</a>
        <button type="button" class="btn-order inline-flex flex-1 items-center justify-center rounded-lg bg-[#f97316] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#ea580c]" data-product-id="<?= $pid ?>" data-product-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">Заказать дешевле</button>
    </div>
</article>
