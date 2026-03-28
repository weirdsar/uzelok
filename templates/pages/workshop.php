<?php
/** @var string $csrfToken */
?>
<section class="px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-4xl text-center">
        <h1 class="text-4xl font-bold text-white sm:text-5xl">Инженерно-швейная мастерская</h1>
        <p class="mt-4 text-xl text-[#a0a0b8]">От идеи до эталона — проектирование и пошив текстильных изделий</p>
        <p class="mt-4 text-[#6b6b80]">📍 Саратов, Солнечный</p>
    </div>
</section>

<section class="px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl">
        <h2 class="text-center text-2xl font-bold text-white">Услуги</h2>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php
            $services = [
                ['📐', 'Инженерное проектирование', 'Разработка конструкции изделия с учётом материалов, нагрузок и технологии производства'],
                ['✂️', 'Создание лекал', 'Точные лекала для серийного производства с припусками и метками'],
                ['🧵', 'Пошив эталонных образцов', 'Изготовление первого образца для утверждения перед запуском серии'],
                ['🔬', 'ВТО и тестирование', 'Влажно-тепловая обработка, проверка на прочность и износостойкость'],
                ['📦', 'Мелкосерийное производство', 'Выпуск партий от 10 до 500 единиц'],
                ['🤝', 'Консультация', 'Обсудим ваш проект, подберём материалы и технологию'],
            ];
            foreach ($services as $svc) :
                ?>
                <div class="rounded-2xl border border-[#2a2a3e] bg-[#1e1e2e] p-6 transition hover:border-[#f97316]/40">
                    <div class="text-3xl"><?= htmlspecialchars($svc[0], ENT_QUOTES, 'UTF-8') ?></div>
                    <h3 class="mt-3 text-lg font-semibold text-white"><?= htmlspecialchars($svc[1], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-2 text-sm text-[#a0a0b8]"><?= htmlspecialchars($svc[2], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="border-t border-[#2a2a3e] px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
        <h2 class="text-center text-2xl font-bold text-white">Процесс работы</h2>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-4 text-sm text-[#a0a0b8] md:gap-8">
            <div class="rounded-xl border border-[#2a2a3e] bg-[#1e1e2e] px-4 py-3 text-center"><span class="font-mono text-[#f97316]">01</span><br>Бриф и ТЗ</div>
            <span class="hidden text-[#6b6b80] md:inline">→</span>
            <div class="rounded-xl border border-[#2a2a3e] bg-[#1e1e2e] px-4 py-3 text-center"><span class="font-mono text-[#f97316]">02</span><br>Проектирование и лекала</div>
            <span class="hidden text-[#6b6b80] md:inline">→</span>
            <div class="rounded-xl border border-[#2a2a3e] bg-[#1e1e2e] px-4 py-3 text-center"><span class="font-mono text-[#f97316]">03</span><br>Пошив образца</div>
            <span class="hidden text-[#6b6b80] md:inline">→</span>
            <div class="rounded-xl border border-[#2a2a3e] bg-[#1e1e2e] px-4 py-3 text-center"><span class="font-mono text-[#f97316]">04</span><br>Утверждение и серия</div>
        </div>
    </div>
</section>

<section class="border-t border-[#2a2a3e] bg-[#12121a]/50 px-4 py-16 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-xl">
        <h2 class="text-center text-2xl font-bold text-white">Есть идея? Обсудим проект</h2>
        <form id="workshopForm" class="mt-8 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="source" value="workshop">
            <input type="hidden" name="product_id" value="">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="workshop-name">Имя <span class="text-red-400">*</span></label>
                <input id="workshop-name" type="text" name="name" required minlength="2" autocomplete="name" class="form-input mt-0">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="workshop-phone">Телефон <span class="text-red-400">*</span></label>
                <input id="workshop-phone" type="tel" name="phone" required pattern="[+\d\s\-()]{5,20}" inputmode="tel" autocomplete="tel" class="form-input mt-0">
                <p class="mt-1 text-xs text-[#6b6b80]">Мы позвоним, чтобы уточнить детали</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="workshop-email">Email <span class="text-red-400">*</span></label>
                <input id="workshop-email" type="email" name="email" required autocomplete="email" class="form-input mt-0">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="workshop-message">Описание проекта</label>
                <textarea id="workshop-message" name="message" maxlength="1000" rows="4" class="form-input mt-0"></textarea>
            </div>
            <p id="workshopFormError" class="hidden text-sm text-red-400"></p>
            <p id="workshopFormSuccess" class="hidden text-sm text-emerald-400"></p>
            <button type="submit" class="w-full rounded-xl bg-[#f97316] py-3 font-semibold text-white transition hover:bg-[#ea580c]">Отправить</button>
        </form>
    </div>
</section>
