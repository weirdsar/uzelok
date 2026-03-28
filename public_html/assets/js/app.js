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

    function initProductGallery() {
        var strip = document.getElementById('product-gallery-strip');
        var thumbs = document.querySelectorAll('#product-gallery-thumbs .product-gallery-thumb');
        if (!strip || thumbs.length === 0) {
            return;
        }
        var slides = strip.querySelectorAll('.product-gallery-slide');
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
        strip.addEventListener('scroll', function () {
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
        }, { passive: true });
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
        initOzonButtonHints();
        initOrderModal();
        initForms();
        initScrollHeader();
        updateTabCounts();
    });
})();
