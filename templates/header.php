<?php
/** @var string $page */
/** @var array<string, mixed> $config */
/** @var \Uzelok\Core\Brand|null $brandFilter */
$brandGet = isset($_GET['brand']) ? (string) $_GET['brand'] : '';

$navActive = static fn (bool $active): string => $active
    ? 'nav-link active rounded-lg px-3 py-2 text-sm font-medium text-white bg-white/10'
    : 'nav-link rounded-lg px-3 py-2 text-sm font-medium text-[#a0a0b8] hover:text-white transition';
?>
<header id="siteHeader" class="fixed top-0 left-0 right-0 z-50 border-b border-[#2a2a3e] bg-[#0a0a0f]/90 backdrop-blur-md transition-shadow duration-300">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <a href="/?page=home" class="group flex flex-col">
            <span class="text-xl font-bold tracking-tight text-[#f97316] sm:text-2xl">УЗЕЛОК<span class="text-white">64</span></span>
            <span class="text-xs text-[#6b6b80]">Хозтовары • Авто • Туризм</span>
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Основная навигация">
            <a href="/?page=home#catalog" class="<?= htmlspecialchars($navActive($page === 'home' && $brandGet === ''), ENT_QUOTES, 'UTF-8') ?>">Каталог</a>
            <a href="/?page=home&brand=batya#catalog" class="<?= htmlspecialchars($page === 'home' && $brandGet === 'batya' ? 'nav-link active rounded-lg px-3 py-2 text-sm font-medium bg-brand-batya/20 text-[#d97706]' : 'nav-link rounded-lg px-3 py-2 text-sm font-medium text-[#a0a0b8] hover:text-white transition', ENT_QUOTES, 'UTF-8') ?>">БАТЯ</a>
            <a href="/?page=home&brand=buy#catalog" class="<?= htmlspecialchars($page === 'home' && $brandGet === 'buy' ? 'nav-link active rounded-lg px-3 py-2 text-sm font-medium bg-brand-buy/20 text-[#2563eb]' : 'nav-link rounded-lg px-3 py-2 text-sm font-medium text-[#a0a0b8] hover:text-white transition', ENT_QUOTES, 'UTF-8') ?>">БУЙ</a>
            <a href="/?page=home&brand=volna#catalog" class="<?= htmlspecialchars($page === 'home' && $brandGet === 'volna' ? 'nav-link active rounded-lg px-3 py-2 text-sm font-medium bg-brand-volna/20 text-[#059669]' : 'nav-link rounded-lg px-3 py-2 text-sm font-medium text-[#a0a0b8] hover:text-white transition', ENT_QUOTES, 'UTF-8') ?>">ВОЛНА</a>
            <a href="/?page=workshop" class="<?= htmlspecialchars($navActive($page === 'workshop'), ENT_QUOTES, 'UTF-8') ?>">Мастерская</a>
            <a href="/?page=contacts" class="<?= htmlspecialchars($navActive($page === 'contacts'), ENT_QUOTES, 'UTF-8') ?>">Контакты</a>
        </nav>

        <div class="flex items-center gap-3">
            <button type="button" id="openOrderModalEmpty" class="rounded-lg bg-[#f97316] px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/20 transition hover:bg-[#ea580c]">
                Оставить заявку
            </button>
            <button type="button" id="menu-btn" class="relative md:hidden p-2 text-[#a0a0b8] transition-colors hover:text-[#f97316]" aria-expanded="false" aria-controls="mobile-menu" aria-label="Меню">
                <span class="relative block h-6 w-6" aria-hidden="true">
                    <svg id="menu-icon-open" class="absolute inset-0 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg id="menu-icon-close" class="absolute inset-0 hidden h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </span>
            </button>
        </div>
    </div>

    <div id="mobile-menu" class="border-t border-[#2a2a3e] bg-[#0a0a0f]/95 px-4 py-4 lg:hidden">
        <nav class="flex flex-col gap-2" aria-label="Мобильная навигация">
            <a href="/?page=home#catalog" class="nav-link rounded-lg px-3 py-2 <?= $page === 'home' && $brandGet === '' ? 'active bg-white/10 text-white' : 'text-[#e8e8f0]' ?>">Каталог</a>
            <a href="/?page=home&brand=batya#catalog" class="nav-link rounded-lg px-3 py-2 <?= $page === 'home' && $brandGet === 'batya' ? 'active bg-brand-batya/15 text-[#d97706]' : 'text-[#e8e8f0]' ?>">БАТЯ</a>
            <a href="/?page=home&brand=buy#catalog" class="nav-link rounded-lg px-3 py-2 <?= $page === 'home' && $brandGet === 'buy' ? 'active bg-brand-buy/15 text-[#2563eb]' : 'text-[#e8e8f0]' ?>">БУЙ</a>
            <a href="/?page=home&brand=volna#catalog" class="nav-link rounded-lg px-3 py-2 <?= $page === 'home' && $brandGet === 'volna' ? 'active bg-brand-volna/15 text-[#059669]' : 'text-[#e8e8f0]' ?>">ВОЛНА</a>
            <a href="/?page=workshop" class="nav-link rounded-lg px-3 py-2 <?= $page === 'workshop' ? 'active bg-white/10 text-white' : 'text-[#e8e8f0]' ?>">Мастерская</a>
            <a href="/?page=contacts" class="nav-link rounded-lg px-3 py-2 <?= $page === 'contacts' ? 'active bg-white/10 text-white' : 'text-[#e8e8f0]' ?>">Контакты</a>
            <button type="button" class="mobile-open-modal mt-2 rounded-lg bg-[#f97316] px-4 py-3 text-center font-semibold text-white">Оставить заявку</button>
        </nav>
    </div>
</header>
