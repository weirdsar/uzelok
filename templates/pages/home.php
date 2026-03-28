<?php
/** @var array<int, array<string, mixed>> $products */
/** @var array<string, mixed> $config */
/** @var \Uzelok\Core\Brand|null $brandFilter */
/** @var array<int, \Uzelok\Core\Brand> $brands */
/** @var array<string, int> $catalogTabCounts */
use Uzelok\Core\Brand;

$tabCountAll = (int) ($catalogTabCounts['all'] ?? 0);
?>
<section class="relative flex min-h-[85vh] flex-col justify-center overflow-hidden px-4 pb-16 pt-8 sm:px-6 lg:px-8">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-[#1a1a2e]/40 to-[#0a0a0f]"></div>
    <div class="relative z-10 mx-auto max-w-5xl text-center fade-in-up">
        <h1 class="text-4xl font-bold tracking-tight text-white sm:text-5xl md:text-6xl">Качественные текстильные изделия</h1>
        <p id="hero-tagline" class="hero-tagline mx-auto mt-4 max-w-2xl text-lg text-[#a0a0b8] sm:text-xl">Хозяйственные сумки • Автоаксессуары • Снаряжение для туризма</p>
        <p class="mt-6 inline-flex rounded-full border border-[#f97316]/40 bg-[#f97316]/10 px-4 py-2 text-sm font-medium text-[#f97316]">Дешевле, чем на Ozon — закажите напрямую</p>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
            <a href="#catalog" class="rounded-xl bg-[#f97316] px-8 py-3.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 transition hover:bg-[#ea580c]">Смотреть каталог</a>
            <button type="button" class="hero-open-modal rounded-xl border border-[#2a2a3e] bg-[#1e1e2e] px-8 py-3.5 text-sm font-semibold text-white transition hover:border-[#f97316]/50">Оставить заявку</button>
        </div>
        <div class="mx-auto mt-16 grid max-w-3xl grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-[#2a2a3e] bg-[#1e1e2e]/80 p-4 text-sm text-[#a0a0b8]">💰 Прямые цены</div>
            <div class="rounded-xl border border-[#2a2a3e] bg-[#1e1e2e]/80 p-4 text-sm text-[#a0a0b8]">🚚 Доставка по РФ</div>
            <div class="rounded-xl border border-[#2a2a3e] bg-[#1e1e2e]/80 p-4 text-sm text-[#a0a0b8]">📦 Оптовые заказы</div>
        </div>
    </div>
</section>

<section id="brands" class="scroll-mt-28 px-4 py-16 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <h2 class="text-center text-3xl font-bold text-white">Наши бренды</h2>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            <?php foreach ($brands as $b) :
                $ozonKey = $b->value;
                $storeUrl = is_array($config['ozon_stores'] ?? null) ? ($config['ozon_stores'][$ozonKey] ?? '#') : '#';
                $hrefCard = '/?page=home&brand=' . urlencode($b->value) . '#catalog';
                $ariaProducts = 'Показать товары ' . $b->label();
                ?>
                <div class="group relative overflow-hidden rounded-2xl border border-[#2a2a3e] bg-gradient-to-br from-[#1e1e2e] to-[#12121a] transition hover:border-[#f97316]/40 hover:shadow-lg hover:shadow-orange-500/10">
                    <a href="<?= htmlspecialchars($hrefCard, ENT_QUOTES, 'UTF-8') ?>" class="block p-8" aria-label="<?= htmlspecialchars($ariaProducts, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="text-4xl"><?= htmlspecialchars($b->icon(), ENT_QUOTES, 'UTF-8') ?></div>
                        <h3 class="mt-4 text-xl font-bold" style="color: <?= htmlspecialchars($b->color(), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($b->label(), ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="mt-2 text-sm text-[#a0a0b8]"><?= htmlspecialchars($b->subtitle(), ENT_QUOTES, 'UTF-8') ?></p>
                        <span class="mt-4 inline-block text-sm text-[#f97316] group-hover:underline">Смотреть товары →</span>
                    </a>
                    <div class="border-t border-[#2a2a3e] px-8 py-3">
                        <a href="<?= htmlspecialchars((string) $storeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="text-xs text-[#6b6b80] hover:text-[#a0a0b8]">Магазин на Ozon ↗</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="catalog" class="scroll-mt-28 border-t border-[#2a2a3e] bg-[#12121a]/50 px-4 py-16 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-3xl font-bold text-white">Каталог товаров</h2>
                <?php if ($brandFilter instanceof Brand) : ?>
                    <p class="mt-2 text-[#a0a0b8]">Бренд: <span class="font-semibold text-white"><?= htmlspecialchars($brandFilter->label(), ENT_QUOTES, 'UTF-8') ?></span>
                        <a href="/?page=home#catalog" class="ml-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#2a2a3e] text-white hover:bg-[#f97316]" title="Сбросить">×</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-8 flex flex-wrap gap-2 border-b border-[#2a2a3e] pb-4">
            <?php
            $allActive = $brandFilter === null;
            $allClasses = $allActive ? 'border-b-2 border-[#f97316] text-white' : 'text-[#a0a0b8] hover:text-white';
            ?>
            <a href="/?page=home#catalog" data-brand-tab="all" data-tab-count="<?= $tabCountAll ?>" class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium <?= $allClasses ?>">
                Все
                <span class="tabular-nums text-xs opacity-70" data-tab-badge="all"><?= $tabCountAll ?></span>
            </a>
            <?php foreach ($brands as $b) :
                $active = $brandFilter !== null && $brandFilter === $b;
                $cnt = (int) ($catalogTabCounts[$b->value] ?? 0);
                $tabClasses = $active ? 'border-b-2 text-white' : 'text-[#a0a0b8] hover:text-white';
                ?>
                <a href="/?page=home&brand=<?= urlencode($b->value) ?>#catalog" data-brand-tab="<?= htmlspecialchars($b->value, ENT_QUOTES, 'UTF-8') ?>" data-tab-count="<?= $cnt ?>" class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium <?= $tabClasses ?>" style="<?= $active ? 'border-color: ' . htmlspecialchars($b->color(), ENT_QUOTES, 'UTF-8') : '' ?>">
                    <?= htmlspecialchars($b->label(), ENT_QUOTES, 'UTF-8') ?>
                    <span class="tabular-nums text-xs opacity-70" data-tab-badge="<?= htmlspecialchars($b->value, ENT_QUOTES, 'UTF-8') ?>"><?= $cnt ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (count($products) === 0) : ?>
            <div class="mt-12 flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#2a2a3e] py-16 text-center text-[#6b6b80]">
                <span class="text-4xl" aria-hidden="true">📦</span>
                <p class="mt-4 text-lg">Товары скоро появятся</p>
            </div>
        <?php else : ?>
            <p id="catalog-count" class="mb-4 mt-6 text-sm text-[#6b6b80]"></p>
            <div id="catalog-grid" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <?php foreach ($products as $product) :
                    require __DIR__ . '/../components/product-card.php';
                endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="border-t border-[#2a2a3e] bg-gradient-to-r from-[#1a1a2e]/80 to-[#0a0a0f] px-4 py-16 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-4xl text-center">
        <h2 class="text-2xl font-bold text-white sm:text-3xl">Хотите дешевле или оптом?</h2>
        <p class="mt-3 text-[#a0a0b8]">Оставьте заявку — мы предложим лучшую цену</p>
        <button type="button" class="cta-open-modal mt-8 rounded-xl bg-[#f97316] px-8 py-3.5 text-sm font-semibold text-white shadow-lg hover:bg-[#ea580c]">Оставить заявку</button>
    </div>
</section>
