<?php
/** @var string $csrfToken */
?>
<dialog id="orderModal" class="w-full max-w-md rounded-2xl border border-[#2a2a3e] bg-[#1e1e2e] p-0 text-[#e8e8f0] shadow-2xl" aria-labelledby="modal-title" aria-modal="true">
    <div class="p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="modal-title" class="text-xl font-bold text-white">Оставить заявку</h2>
                <p id="orderModalSubtitle" class="mt-1 text-sm text-[#a0a0b8]">Мы перезвоним вам в ближайшее время</p>
            </div>
            <button type="button" id="orderModalClose" class="rounded-lg p-1 text-2xl leading-none text-[#6b6b80] hover:bg-white/10 hover:text-white" aria-label="Закрыть">×</button>
        </div>

        <form id="orderModalForm" class="mt-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="product_id" id="modalProductId" value="">
            <input type="hidden" name="source" value="website">

            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="modal-name">Ваше имя <span class="text-red-400">*</span></label>
                <input id="modal-name" type="text" name="name" required minlength="2" maxlength="100" autocomplete="name" class="form-input">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="modal-phone">Телефон <span class="text-red-400">*</span></label>
                <input id="modal-phone" type="tel" name="phone" required pattern="[+\d\s\-()]{5,20}" inputmode="tel" autocomplete="tel" class="form-input">
                <p class="mt-1 text-xs text-[#6b6b80]">Мы позвоним, чтобы уточнить детали заказа</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="modal-email">Email</label>
                <input id="modal-email" type="email" name="email" autocomplete="email" class="form-input">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-[#a0a0b8]" for="modal-message">Комментарий</label>
                <textarea id="modal-message" name="message" maxlength="1000" rows="3" class="form-input"></textarea>
            </div>

            <p id="orderFormError" class="hidden text-sm text-red-400"></p>

            <button type="submit" id="modal-submit-btn" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#f97316] py-3 px-6 font-semibold text-white transition-all duration-200 hover:bg-[#ea580c]">
                <span id="modal-btn-text">Отправить заявку</span>
                <svg id="modal-btn-spinner" class="hidden h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
            </button>
        </form>
    </div>
</dialog>
