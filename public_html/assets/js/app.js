(function () {
    'use strict';

    function pluralize(n, one, few, many) {
        var mod10 = n % 10;
        var mod100 = n % 100;
        if (mod10 === 1 && mod100 !== 11) {
            return one;
        }
        if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
            return few;
        }
        return many;
    }

    function showToast(message, type, duration) {
        var container = document.getElementById('toast-container');
        if (!container) {
            return;
        }
        type = type || 'success';
        duration = duration === undefined ? 4000 : duration;
        var el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.setAttribute('role', 'status');
        el.textContent = message;
        container.appendChild(el);
        requestAnimationFrame(function () {
            el.classList.add('toast-visible');
        });
        var removeMs = duration > 0 ? duration : 4000;
        setTimeout(function () {
            el.classList.remove('toast-visible');
            setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 280);
        }, removeMs);
    }

    function initToasts() {
        // #toast-container is in layout; hook reserved for future
    }

    function ymReachGoal(goalName) {
        var id = typeof window.__UZEL_YM_ID__ !== 'undefined' ? window.__UZEL_YM_ID__ : 0;
        if (!id || !goalName) {
            return;
        }
        try {
            if (typeof window.ym === 'function') {
                window.ym(id, 'reachGoal', goalName);
            }
        } catch (err) {
            /* ignore */
        }
    }

    function initScrollProgress() {
        var fill = document.getElementById('scroll-progress-fill');
        if (!fill) {
            return;
        }
        function update() {
            var doc = document.documentElement;
            var scrollTop = window.scrollY || doc.scrollTop;
            var max = doc.scrollHeight - window.innerHeight;
            var p = max <= 0 ? 0 : (scrollTop / max) * 100;
            fill.style.width = Math.min(100, Math.max(0, p)) + '%';
        }
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        update();
    }

    function initScrollAnimations() {
        var nodes = document.querySelectorAll('.product-card');
        if (!nodes.length) {
            return;
        }
        if (!('IntersectionObserver' in window)) {
            nodes.forEach(function (n) {
                n.classList.add('is-visible');
            });
            return;
        }
        var io = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) {
                        e.target.classList.add('is-visible');
                        io.unobserve(e.target);
                    }
                });
            },
            { root: null, rootMargin: '0px 0px -40px 0px', threshold: 0.08 }
        );
        nodes.forEach(function (n) {
            n.classList.add('animate-on-scroll');
            io.observe(n);
        });
    }

    function initHeroTagline() {
        var el = document.getElementById('hero-tagline');
        if (!el) {
            return;
        }
        var lines = [
            'Хозяйственные сумки • Автоаксессуары • Снаряжение для туризма',
            'Качество мастерской — по ценам без Ozon',
            'Доставка по всей России • Оптовые заказы',
        ];
        var idx = 0;
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        setInterval(function () {
            el.style.opacity = '0';
            el.style.transform = 'translateY(8px)';
            setTimeout(function () {
                idx = (idx + 1) % lines.length;
                el.textContent = lines[idx];
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, 400);
        }, 3500);
    }

    /**
     * Ozon CDN: крупный пресет, если в пути есть /wcNNN/ (иначе оставляем как есть).
     */
    function preferLargeProductImageUrl(src) {
        if (typeof src !== 'string' || !/^https?:/i.test(src)) {
            return src;
        }
        if (!/ozon\.ru|ozone\.ru/i.test(src)) {
            return src;
        }
        if (/\/wc\d{2,4}\//i.test(src)) {
            return src.replace(/\/wc\d{2,4}\//i, '/wc1200/');
        }
        return src;
    }

    function ensureLightboxStyles() {
        if (document.getElementById('uz-lightbox-styles')) {
            return;
        }
        var s = document.createElement('style');
        s.id = 'uz-lightbox-styles';
        s.textContent =
            '.uz-lightbox{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.92);opacity:0;transition:opacity .2s ease;padding:12px;box-sizing:border-box}' +
            '.uz-lightbox.uz-lightbox-open{opacity:1}' +
            '.uz-lightbox__backdrop{position:absolute;inset:0;cursor:zoom-out}' +
            '.uz-lightbox__img{position:relative;z-index:1;box-sizing:border-box;max-width:none;max-height:none;object-fit:contain;pointer-events:none;user-select:none;image-rendering:auto}' +
            '.uz-lightbox__close{position:absolute;top:12px;right:12px;z-index:2;width:44px;height:44px;border:none;border-radius:10px;background:rgba(30,30,46,.95);color:#e8e8f0;font-size:26px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(0,0,0,.4)}' +
            '.uz-lightbox__close:hover{background:#f97316;color:#fff}' +
            '.uz-lightbox__nav{position:absolute;top:50%;z-index:2;transform:translateY(-50%);width:48px;height:48px;border:none;border-radius:10px;background:rgba(30,30,46,.95);color:#e8e8f0;font-size:22px;cursor:pointer}' +
            '.uz-lightbox__nav:hover{background:#f97316;color:#fff}' +
            '.uz-lightbox__nav--prev{left:8px}' +
            '.uz-lightbox__nav--next{right:8px}' +
            '.uz-lightbox__hint{position:absolute;bottom:16px;left:50%;transform:translateX(-50%);z-index:2;font-size:12px;color:#a0a0b8;pointer-events:none;text-align:center;max-width:90%}';
        document.head.appendChild(s);
    }

    function openImageLightbox(sources, startIndex) {
        if (!sources || !sources.length) {
            return;
        }
        ensureLightboxStyles();
        var idx = Math.min(Math.max(0, startIndex), sources.length - 1);
        var overlay = document.createElement('div');
        overlay.className = 'uz-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Просмотр фото');

        var backdrop = document.createElement('button');
        backdrop.type = 'button';
        backdrop.className = 'uz-lightbox__backdrop';
        backdrop.setAttribute('aria-label', 'Закрыть');

        var img = document.createElement('img');
        img.className = 'uz-lightbox__img';
        img.decoding = 'async';
        img.draggable = false;

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'uz-lightbox__close';
        closeBtn.setAttribute('aria-label', 'Закрыть');
        closeBtn.innerHTML = '&times;';

        var prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'uz-lightbox__nav uz-lightbox__nav--prev';
        prevBtn.setAttribute('aria-label', 'Предыдущее фото');
        prevBtn.textContent = '\u2039';

        var nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'uz-lightbox__nav uz-lightbox__nav--next';
        nextBtn.setAttribute('aria-label', 'Следующее фото');
        nextBtn.textContent = '\u203a';

        var hint = document.createElement('div');
        hint.className = 'uz-lightbox__hint';
        hint.textContent =
            sources.length > 1
                ? 'Стрелки \u2190 \u2192 или свайп — другие фото, Esc — закрыть'
                : 'Esc или клик по фону — закрыть';

        var onWinResize = null;
        var curLoadToken = 0;
        function fitLightboxImage() {
            var nw = img.naturalWidth;
            var nh = img.naturalHeight;
            if (!nw || !nh) {
                return;
            }
            var maxW = Math.max(0, window.innerWidth * 0.96 - 32);
            var maxH = Math.max(0, window.innerHeight * 0.92 - 32);
            var s = Math.min(maxW / nw, maxH / nh);
            if (!isFinite(s) || s <= 0) {
                s = 1;
            }
            var w = Math.max(1, Math.floor(nw * s));
            var h = Math.max(1, Math.floor(nh * s));
            img.style.width = w + 'px';
            img.style.height = h + 'px';
        }

        function render() {
            var cur = sources[idx];
            var trySrc = preferLargeProductImageUrl(cur.src);
            var origSrc = cur.src;
            img.removeAttribute('width');
            img.removeAttribute('height');
            img.style.width = '';
            img.style.height = '';
            img.alt = cur.alt || '';
            var multi = sources.length > 1;
            prevBtn.style.display = multi ? '' : 'none';
            nextBtn.style.display = multi ? '' : 'none';
            hint.style.display = multi ? '' : 'none';
            var token = ++curLoadToken;
            if (trySrc !== origSrc) {
                img.onerror = function () {
                    if (img.src === origSrc) {
                        return;
                    }
                    img.onerror = null;
                    img.src = origSrc;
                    img.addEventListener(
                        'load',
                        function onFallback() {
                            if (token !== curLoadToken) {
                                return;
                            }
                            fitLightboxImage();
                        },
                        { once: true }
                    );
                    if (img.complete && img.naturalWidth) {
                        if (token === curLoadToken) {
                            fitLightboxImage();
                        }
                    }
                };
            } else {
                img.onerror = null;
            }
            img.src = trySrc;
            function afterLoad() {
                if (token !== curLoadToken) {
                    return;
                }
                fitLightboxImage();
            }
            if (img.complete && img.naturalWidth) {
                afterLoad();
            } else {
                img.addEventListener('load', afterLoad, { once: true });
            }
        }

        function closeLb() {
            if (onWinResize) {
                window.removeEventListener('resize', onWinResize);
                onWinResize = null;
            }
            curLoadToken += 1;
            img.onerror = null;
            overlay.classList.remove('uz-lightbox-open');
            setTimeout(function () {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
                document.body.style.overflow = '';
                document.removeEventListener('keydown', onKey);
            }, 220);
        }

        function step(delta) {
            idx = (idx + delta + sources.length) % sources.length;
            render();
        }

        function onKey(e) {
            if (e.key === 'Escape') {
                closeLb();
            } else if (e.key === 'ArrowRight') {
                step(1);
            } else if (e.key === 'ArrowLeft') {
                step(-1);
            }
        }

        backdrop.addEventListener('click', closeLb);
        closeBtn.addEventListener('click', closeLb);
        prevBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            step(-1);
        });
        nextBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            step(1);
        });

        var touchStartX = null;
        overlay.addEventListener(
            'touchstart',
            function (e) {
                if (e.touches.length === 1) {
                    touchStartX = e.touches[0].clientX;
                }
            },
            { passive: true }
        );
        overlay.addEventListener(
            'touchend',
            function (e) {
                if (touchStartX === null || !e.changedTouches.length) {
                    return;
                }
                var dx = e.changedTouches[0].clientX - touchStartX;
                touchStartX = null;
                if (Math.abs(dx) < 48) {
                    return;
                }
                if (dx < 0) {
                    step(1);
                } else {
                    step(-1);
                }
            },
            { passive: true }
        );

        overlay.appendChild(backdrop);
        overlay.appendChild(img);
        overlay.appendChild(closeBtn);
        overlay.appendChild(prevBtn);
        overlay.appendChild(nextBtn);
        overlay.appendChild(hint);
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        onWinResize = function () {
            fitLightboxImage();
        };
        window.addEventListener('resize', onWinResize, { passive: true });
        render();
        document.addEventListener('keydown', onKey);
        requestAnimationFrame(function () {
            overlay.classList.add('uz-lightbox-open');
        });
    }

    function initProductGallery() {
        var strip = document.getElementById('product-gallery-strip');
        if (!strip) {
            return;
        }
        var slides = strip.querySelectorAll('.product-gallery-slide');
        if (!slides.length) {
            return;
        }
        var thumbs = document.querySelectorAll('#product-gallery-thumbs .product-gallery-thumb');
        function scrollToSlide(idx) {
            var slide = slides[idx];
            if (!slide) {
                return;
            }
            var left = slide.offsetLeft;
            if (typeof strip.scrollTo === 'function') {
                strip.scrollTo({ left: left, behavior: 'smooth' });
            } else {
                strip.scrollLeft = left;
            }
        }
        if (thumbs.length > 0) {
            thumbs.forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var idx = parseInt(btn.getAttribute('data-gallery-index'), 10);
                    if (isNaN(idx) || idx < 0 || idx >= slides.length) {
                        return;
                    }
                    scrollToSlide(idx);
                    thumbs.forEach(function (t) {
                        t.classList.remove('border-[#f97316]');
                        t.classList.add('border-transparent');
                    });
                    btn.classList.remove('border-transparent');
                    btn.classList.add('border-[#f97316]');
                });
            });
            var scrollThumbRaf = null;
            strip.addEventListener(
                'scroll',
                function () {
                    if (!slides.length) {
                        return;
                    }
                    if (scrollThumbRaf !== null) {
                        return;
                    }
                    scrollThumbRaf = requestAnimationFrame(function () {
                        scrollThumbRaf = null;
                        var w = strip.clientWidth;
                        if (w <= 0) {
                            return;
                        }
                        var mid = strip.scrollLeft + w * 0.5;
                        var best = 0;
                        var bestDist = Infinity;
                        for (var i = 0; i < slides.length; i++) {
                            var el = slides[i];
                            var c = el.offsetLeft + el.offsetWidth * 0.5;
                            var d = Math.abs(c - mid);
                            if (d < bestDist) {
                                bestDist = d;
                                best = i;
                            }
                        }
                        thumbs.forEach(function (t, j) {
                            var on = j === best;
                            t.classList.toggle('border-[#f97316]', on);
                            t.classList.toggle('border-transparent', !on);
                        });
                    });
                },
                { passive: true }
            );
        }

        slides.forEach(function (sl) {
            var im0 = sl.querySelector('img');
            if (im0 && im0.src) {
                sl.style.cursor = 'zoom-in';
            }
        });
        strip.addEventListener('click', function (e) {
            var slide = e.target && e.target.closest ? e.target.closest('.product-gallery-slide') : null;
            if (!slide || !strip.contains(slide)) {
                return;
            }
            var photoImg = slide.querySelector(':scope > img');
            if (!photoImg || !photoImg.src) {
                return;
            }
            e.preventDefault();
            var list = [];
            slides.forEach(function (fig) {
                var im = fig.querySelector(':scope > img');
                if (im && im.src) {
                    list.push({ src: im.src, alt: im.getAttribute('alt') || '' });
                }
            });
            if (!list.length) {
                return;
            }
            var photoSlides = [];
            slides.forEach(function (fig) {
                var im = fig.querySelector(':scope > img');
                if (im && im.src) {
                    photoSlides.push(fig);
                }
            });
            var idxInPhotos = photoSlides.indexOf(slide);
            openImageLightbox(list, idxInPhotos >= 0 ? idxInPhotos : 0);
        });
    }

    var catalogCardZoomInit = false;
    function initCatalogCardImageZoom() {
        if (catalogCardZoomInit) {
            return;
        }
        catalogCardZoomInit = true;
        /* capture: срабатывает раньше других обработчиков */
        document.addEventListener(
            'click',
            function (e) {
                var t = e.target;
                if (!t || !t.closest) {
                    return;
                }
                var im = t.closest('.product-card .card-image img');
                if (!im || !im.src) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                openImageLightbox([{ src: im.src, alt: im.getAttribute('alt') || '' }], 0);
            },
            true
        );
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            var t = e.target;
            if (!t || !t.matches) {
                return;
            }
            if (!t.matches('.product-card .card-image img') || !t.src) {
                return;
            }
            e.preventDefault();
            openImageLightbox([{ src: t.src, alt: t.getAttribute('alt') || '' }], 0);
        });
    }

    function initMobileMenu() {
        var btn = document.getElementById('menu-btn');
        var menu = document.getElementById('mobile-menu');
        var iconOpen = document.getElementById('menu-icon-open');
        var iconClose = document.getElementById('menu-icon-close');
        if (!btn || !menu) {
            return;
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = menu.classList.contains('open');
            menu.classList.toggle('open', !isOpen);
            if (iconOpen) {
                iconOpen.classList.toggle('hidden', !isOpen);
            }
            if (iconClose) {
                iconClose.classList.toggle('hidden', isOpen);
            }
            btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        });

        document.addEventListener('click', function (e) {
            if (!menu.classList.contains('open')) {
                return;
            }
            var t = e.target;
            if (btn.contains(t) || menu.contains(t)) {
                return;
            }
            menu.classList.remove('open');
            if (iconOpen) {
                iconOpen.classList.remove('hidden');
            }
            if (iconClose) {
                iconClose.classList.add('hidden');
            }
            btn.setAttribute('aria-expanded', 'false');
        });

        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                menu.classList.remove('open');
                if (iconOpen) {
                    iconOpen.classList.remove('hidden');
                }
                if (iconClose) {
                    iconClose.classList.add('hidden');
                }
                btn.setAttribute('aria-expanded', 'false');
            });
        });
    }

    function initOzonButtonHints() {
        document.querySelectorAll('.btn-ozon').forEach(function (btn) {
            var originalText = btn.textContent.trim();
            btn.addEventListener('mouseenter', function () {
                btn.textContent = 'Перейти на Ozon →';
            });
            btn.addEventListener('mouseleave', function () {
                btn.textContent = originalText;
            });
            btn.addEventListener('click', function () {
                ymReachGoal('ozon_outbound_click');
            });
        });
    }

    function updateTabCounts() {
        var cards = document.querySelectorAll('.product-card');
        var countEl = document.getElementById('catalog-count');
        if (!countEl) {
            return;
        }
        var n = cards.length;
        var word = pluralize(n, 'товар', 'товара', 'товаров');
        countEl.textContent = n + ' ' + word;
    }

    function postForm(form, errorEl) {
        var action = '/submit-form.php';
        if (errorEl) {
            errorEl.classList.add('hidden');
            errorEl.textContent = '';
        }
        var fd = new FormData(form);
        return fetch(action, {
            method: 'POST',
            body: fd,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, status: r.status, data: data };
                });
            })
            .catch(function () {
                return { ok: false, data: { success: false, errors: ['Сеть недоступна'] } };
            });
    }

    function setModalLoading(loading) {
        var submitBtn = document.getElementById('modal-submit-btn');
        var textEl = document.getElementById('modal-btn-text');
        var spinEl = document.getElementById('modal-btn-spinner');
        if (submitBtn) {
            submitBtn.disabled = !!loading;
        }
        if (textEl) {
            textEl.textContent = loading ? 'Отправка...' : 'Отправить заявку';
        }
        if (spinEl) {
            spinEl.classList.toggle('hidden', !loading);
        }
    }

    function initOrderModal() {
        var modal = document.getElementById('orderModal');
        var modalForm = document.getElementById('orderModalForm');
        var modalSubtitle = document.getElementById('orderModalSubtitle');
        var modalProductId = document.getElementById('modalProductId');
        var orderFormError = document.getElementById('orderFormError');

        function resetModalFormState() {
            if (orderFormError) {
                orderFormError.classList.add('hidden');
                orderFormError.textContent = '';
            }
            setModalLoading(false);
        }

        function resetModalForm() {
            if (!modalForm) {
                return;
            }
            ['name', 'phone', 'email', 'message'].forEach(function (name) {
                var el = modalForm.querySelector('[name="' + name + '"]');
                if (el) {
                    el.value = '';
                }
            });
            if (modalProductId) {
                modalProductId.value = '';
            }
            resetModalFormState();
        }

        function openOrderModal(productId, productTitle) {
            if (!modal) {
                return;
            }
            if (modalProductId) {
                modalProductId.value = productId ? String(productId) : '';
            }
            if (modalSubtitle) {
                modalSubtitle.textContent = productTitle
                    ? 'Товар: ' + productTitle
                    : 'Мы перезвоним вам в ближайшее время';
            }
            resetModalFormState();
            modal.showModal();
        }

        function closeOrderModal() {
            if (!modal) {
                return;
            }
            modal.close();
            resetModalForm();
        }

        function bindOpenModal(selector) {
            document.querySelectorAll(selector).forEach(function (el) {
                el.addEventListener('click', function () {
                    openOrderModal(null, '');
                });
            });
        }

        bindOpenModal('#openOrderModalEmpty');
        bindOpenModal('.hero-open-modal');
        bindOpenModal('.cta-open-modal');
        bindOpenModal('.mobile-open-modal');

        document.querySelectorAll('.btn-order').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-product-id') || '';
                var title = btn.getAttribute('data-product-title') || '';
                openOrderModal(id, title);
            });
        });

        var closeBtn = document.getElementById('orderModalClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeOrderModal);
        }

        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.close();
                }
            });
            modal.addEventListener('close', function () {
                resetModalForm();
            });
        }

        if (modalForm) {
            modalForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (orderFormError) {
                    orderFormError.classList.add('hidden');
                    orderFormError.textContent = '';
                }
                setModalLoading(true);
                postForm(modalForm, orderFormError).then(function (res) {
                    var d = res.data;
                    if (d.success) {
                        ymReachGoal('order_sent');
                        closeOrderModal();
                        showToast(
                            'Заявка отправлена! Мы свяжемся с вами в ближайшее время.',
                            'success',
                            5000
                        );
                    } else {
                        var msg =
                            d.errors && d.errors.join
                                ? d.errors.join(' ')
                                : 'Ошибка отправки. Попробуйте ещё раз.';
                        showToast(msg, 'error');
                        setModalLoading(false);
                    }
                });
            });
        }
    }

    function initForms() {
        function wirePageForm(formId, errId) {
            var form = document.getElementById(formId);
            if (!form) {
                return;
            }
            var errEl = errId ? document.getElementById(errId) : null;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (errEl) {
                    errEl.classList.add('hidden');
                    errEl.textContent = '';
                }
                var btn = form.querySelector('[type="submit"]');
                var prevText = '';
                if (btn) {
                    btn.disabled = true;
                    prevText = btn.textContent;
                    btn.textContent = 'Отправка...';
                }
                postForm(form, errEl).then(function (res) {
                    var d = res.data;
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = prevText || 'Отправить';
                    }
                    if (d.success) {
                        ymReachGoal('order_sent');
                        showToast(
                            'Заявка отправлена! Мы свяжемся с вами в ближайшее время.',
                            'success',
                            5000
                        );
                        form.reset();
                    } else {
                        var msg =
                            d.errors && d.errors.join
                                ? d.errors.join(' ')
                                : 'Ошибка отправки. Попробуйте ещё раз.';
                        showToast(msg, 'error');
                        if (errEl) {
                            errEl.textContent = msg;
                            errEl.classList.remove('hidden');
                        }
                    }
                });
            });
        }
        wirePageForm('workshopForm', 'workshopFormError');
        wirePageForm('contactsForm', 'contactsFormError');
    }

    function initScrollHeader() {
        var siteHeader = document.getElementById('siteHeader');
        if (!siteHeader) {
            return;
        }
        window.addEventListener('scroll', function () {
            if (window.scrollY > 24) {
                siteHeader.classList.add('shadow-lg');
            } else {
                siteHeader.classList.remove('shadow-lg');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initToasts();
        initScrollProgress();
        initScrollAnimations();
        initHeroTagline();
        initMobileMenu();
        initProductGallery();
        initCatalogCardImageZoom();
        initOzonButtonHints();
        initOrderModal();
        initForms();
        initScrollHeader();
        updateTabCounts();
    });
})();
