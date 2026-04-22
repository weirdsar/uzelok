<?php
/** @var array<string, mixed> $config */
$year = (int) date('Y');
$stores = $config['ozon_stores'] ?? [];
if (!is_array($stores)) {
    $stores = [];
}
?>
<footer class="border-t border-[#2a2a3e] bg-[#12121a]">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-lg font-bold text-[#f97316]">УЗЕЛОК64</p>
                <p class="mt-2 text-sm text-[#a0a0b8]">Качественные текстильные изделия: хозтовары, автоаксессуары, туризм и рыбалка.</p>
                <p class="mt-4 text-xs text-[#6b6b80]">© <?= $year ?> УЗЕЛОК64</p>
            </div>
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-[#e8e8f0]">Каталог</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="/?page=home&brand=batya#catalog" class="text-[#a0a0b8] hover:text-[#f97316]">БАТЯ — хозтовары</a></li>
                    <li><a href="/?page=home&brand=buy#catalog" class="text-[#a0a0b8] hover:text-[#f97316]">БУЙ — авто / мото</a></li>
                    <li><a href="/?page=home&brand=volna#catalog" class="text-[#a0a0b8] hover:text-[#f97316]">ВОЛНА — туризм</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-[#e8e8f0]">Наши магазины на Ozon</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <?php if (isset($stores['batya'])) : ?>
                        <li><a href="<?= htmlspecialchars((string) $stores['batya'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[#a0a0b8] hover:text-[#f97316]">БАТЯ <span aria-hidden="true">↗</span></a></li>
                    <?php endif; ?>
                    <?php if (isset($stores['buy'])) : ?>
                        <li><a href="<?= htmlspecialchars((string) $stores['buy'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[#a0a0b8] hover:text-[#f97316]">БУЙ <span aria-hidden="true">↗</span></a></li>
                    <?php endif; ?>
                    <?php if (isset($stores['volna'])) : ?>
                        <li><a href="<?= htmlspecialchars((string) $stores['volna'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[#a0a0b8] hover:text-[#f97316]">ВОЛНА <span aria-hidden="true">↗</span></a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-[#e8e8f0]">Контакты</h3>
                <ul class="mt-4 space-y-2 text-sm text-[#a0a0b8]">
                    <li>г. Саратов, ул. Киселёва, д. 18Б</li>
                    <li><a href="tel:+79297700084" class="hover:text-[#f97316]">+7 (929) 770-00-84</a></li>
                    <li><a href="mailto:ananev-dm@mail.ru" class="hover:text-[#f97316]">ananev-dm@mail.ru</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-10 border-t border-[#2a2a3e] pt-6 text-center text-xs text-[#6b6b80]">
            Сделано с 🧡 для uzelok64.ru — <a href="https://sii5.ru" class="text-[#a0a0b8] underline decoration-[#2a2a3e] underline-offset-2 transition hover:text-[#f97316] hover:decoration-[#f97316]/50" target="_blank" rel="noopener noreferrer">sii5.ru</a>
        </div>
    </div>
</footer>
