<?php
/** @var string $csrfToken */
?>
<section class="px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-4xl font-bold text-white">Контакты</h1>
        <div class="mt-8 space-y-4 text-[#a0a0b8]">
            <p><strong class="text-white">Адрес:</strong> Саратовская обл., пос. Солнечный</p>
            <p><strong class="text-white">Телефон:</strong> <a href="tel:+79000000000" class="text-[#f97316] hover:underline">+7 (900) 000-00-00</a></p>
            <p><strong class="text-white">Email:</strong> <a href="mailto:info@uzelok64.ru" class="text-[#f97316] hover:underline">info@uzelok64.ru</a></p>
        </div>

        <div class="mt-10 rounded-2xl border border-[#2a2a3e] bg-[#1e1e2e] p-6">
            <h2 class="text-lg font-semibold text-white">Оплата и доставка</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-[#a0a0b8]">
                <li>Выставление счёта на организацию</li>
                <li>Перевод на карту</li>
                <li>Наличные при встрече</li>
                <li>Доставка по всей РФ транспортными компаниями и Почтой России</li>
            </ul>
        </div>

        <h2 class="mt-12 text-xl font-bold text-white">Обратная связь</h2>
        <form id="contactsForm" class="mt-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="source" value="website">
            <input type="hidden" name="product_id" value="">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="contacts-name">Имя <span class="text-red-400">*</span></label>
                <input id="contacts-name" type="text" name="name" required minlength="2" autocomplete="name" class="form-input mt-0">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="contacts-phone">Телефон <span class="text-red-400">*</span></label>
                <input id="contacts-phone" type="tel" name="phone" required pattern="[+\d\s\-()]{5,20}" inputmode="tel" autocomplete="tel" class="form-input mt-0">
                <p class="mt-1 text-xs text-[#6b6b80]">Мы позвоним, чтобы уточнить детали заказа</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="contacts-email">Email</label>
                <input id="contacts-email" type="email" name="email" autocomplete="email" class="form-input mt-0">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="contacts-message">Сообщение</label>
                <textarea id="contacts-message" name="message" maxlength="1000" rows="4" class="form-input mt-0"></textarea>
            </div>
            <p id="contactsFormError" class="hidden text-sm text-red-400"></p>
            <p id="contactsFormSuccess" class="hidden text-sm text-emerald-400"></p>
            <button type="submit" class="rounded-xl bg-[#f97316] px-6 py-3 font-semibold text-white transition hover:bg-[#ea580c]">Отправить</button>
        </form>
    </div>
</section>
