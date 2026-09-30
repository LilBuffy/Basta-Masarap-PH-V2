(() => {
    'use strict';

    const body = document.body;
    const BASE = body.dataset.base || '';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let I18N = {};
    try {
        I18N = JSON.parse(document.getElementById('bm-i18n')?.textContent || '{}');
    } catch (e) {
        I18N = {};
    }

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const fmt = (text, ...values) => values.reduce((out, value) => out.replace('%d', value), text || '');
    const norm = (value) => (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    function toast(message, options = {}) {
        const region = document.getElementById('toasts');
        if (!region || !message) return;
        const isError = options.type === 'error';
        const el = document.createElement('div');
        el.className = 'toast' + (isError ? ' is-error' : '');
        if (isError) el.setAttribute('role', 'alert');
        const text = document.createElement('p');
        text.textContent = message;
        el.appendChild(text);
        if (options.action) {
            const link = document.createElement('a');
            link.href = options.action.href;
            link.textContent = options.action.label;
            el.appendChild(link);
        }
        region.appendChild(el);
        while (region.children.length > 3) region.firstElementChild.remove();
        const dismiss = () => {
            el.classList.add('is-leaving');
            setTimeout(() => el.remove(), 220);
        };
        setTimeout(dismiss, isError || options.action ? 5500 : 3200);
    }

    async function api(path, payload = {}) {
        try {
            const response = await fetch(BASE + path, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ ...payload, csrf_token: CSRF }),
                credentials: 'same-origin',
            });
            let data;
            try {
                data = await response.json();
            } catch (e) {
                data = { success: false, message: I18N.server };
            }
            data.status = response.status;
            if (!data.success && !data.message) data.message = I18N.server;
            return data;
        } catch (e) {
            return { success: false, message: I18N.network, status: 0 };
        }
    }

    function failure(data) {
        if (data.login_url) {
            const next = encodeURIComponent(location.pathname + location.search);
            toast(data.message, { type: 'error', action: { href: `${data.login_url}?next=${next}`, label: I18N.logIn } });
            return;
        }
        toast(data.message, { type: 'error' });
    }

    async function busy(button, task) {
        if (!button || button.getAttribute('aria-busy') === 'true') return null;
        button.setAttribute('aria-busy', 'true');
        try {
            return await task();
        } finally {
            button.removeAttribute('aria-busy');
        }
    }

    function itemsText(count) {
        return count === 1 ? I18N.itemOne : fmt(I18N.itemMany, count);
    }

    function setCartCount(count) {
        $$('[data-cart-count]').forEach((badge) => {
            badge.textContent = count;
            badge.hidden = count < 1;
            badge.classList.remove('is-bumped');
            void badge.offsetWidth;
            if (count > 0) badge.classList.add('is-bumped');
        });
        $$('[data-cart-link]').forEach((link) => link.setAttribute('aria-label', `${I18N.cart}: ${itemsText(count)}`));
        $$('[data-cart-bar]').forEach((bar) => {
            bar.hidden = count < 1;
            const label = $('[data-cart-bar-count]', bar);
            if (label) label.textContent = itemsText(count);
        });
    }

    function confirmDialog(message, label, tone = 'danger') {
        if (typeof HTMLDialogElement === 'undefined') return Promise.resolve(window.confirm(message));
        return new Promise((resolve) => {
            const dialog = document.createElement('dialog');
            dialog.className = 'dialog';
            dialog.setAttribute('aria-labelledby', 'confirm-title');
            const title = document.createElement('h2');
            title.id = 'confirm-title';
            title.textContent = message;
            const actions = document.createElement('div');
            actions.className = 'dialog-actions';
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'btn btn-secondary';
            cancel.textContent = I18N.cancel;
            const ok = document.createElement('button');
            ok.type = 'button';
            ok.className = 'btn ' + (tone === 'danger' ? 'btn-danger' : 'btn-primary');
            ok.textContent = label || I18N.confirm;
            actions.append(cancel, ok);
            dialog.append(title, actions);
            document.body.appendChild(dialog);
            let result = false;
            cancel.addEventListener('click', () => dialog.close());
            ok.addEventListener('click', () => {
                result = true;
                dialog.close();
            });
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
            dialog.addEventListener('close', () => {
                dialog.remove();
                resolve(result);
            });
            dialog.showModal();
            cancel.focus();
        });
    }

    function initNav() {
        const toggle = $('[data-nav-toggle]');
        const nav = document.getElementById('site-nav');
        const setOpen = (open) => {
            if (!toggle || !nav) return;
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
        };
        toggle?.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
        document.addEventListener('click', (event) => {
            if (!nav?.classList.contains('is-open')) return;
            if (!nav.contains(event.target) && !toggle.contains(event.target)) setOpen(false);
        });
        window.matchMedia('(min-width: 861px)').addEventListener('change', (event) => {
            if (event.matches) setOpen(false);
        });

        $$('[data-menu]').forEach((menu) => {
            const button = $('[data-menu-toggle]', menu);
            const panel = $('[data-menu-panel]', menu);
            const close = (returnFocus) => {
                panel.hidden = true;
                button.setAttribute('aria-expanded', 'false');
                if (returnFocus) button.focus();
            };
            button.addEventListener('click', () => {
                const open = panel.hidden;
                panel.hidden = !open;
                button.setAttribute('aria-expanded', String(open));
                if (open) $('a, button', panel)?.focus();
            });
            document.addEventListener('click', (event) => {
                if (!panel.hidden && !menu.contains(event.target)) close(false);
            });
            menu.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !panel.hidden) close(true);
            });
            menu.addEventListener('focusout', (event) => {
                if (!panel.hidden && !menu.contains(event.relatedTarget)) close(false);
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && nav?.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });
    }

    async function addToCart(button) {
        const data = await busy(button, () => api('/ajax/cart.php', { action: 'add', dish_id: Number(button.dataset.addCart) }));
        if (!data) return;
        if (!data.success) return failure(data);
        setCartCount(data.cart_count);
        button.classList.add('is-added');
        setTimeout(() => button.classList.remove('is-added'), 1400);
    }

    function syncToggle(button, active) {
        const on = button.dataset.labelOn;
        const off = button.dataset.labelOff;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
        const label = $('[data-toggle-label]', button);
        if (label) label.textContent = active ? on : off;
        else button.setAttribute('aria-label', active ? on : off);
        button.classList.remove('is-popping');
        void button.offsetWidth;
        if (active) button.classList.add('is-popping');
    }

    async function toggleSave(button) {
        const type = button.dataset.toggle;
        const data = await busy(button, () => api(`/ajax/${type}.php`, { dish_id: Number(button.dataset.dishId) }));
        if (!data) return;
        if (!data.success) return failure(data);
        $$(`[data-toggle="${type}"][data-dish-id="${button.dataset.dishId}"]`).forEach((peer) => syncToggle(peer, data.active));
        toast(data.message);
        if (!data.active && button.hasAttribute('data-remove-on-off')) {
            const item = button.closest('[data-dish-item]');
            const grid = item?.parentElement;
            item?.classList.add('is-leaving');
            await wait(220);
            item?.remove();
            if (grid && !$('[data-dish-item]', grid)) {
                grid.hidden = true;
                $('[data-empty-state]')?.removeAttribute('hidden');
            }
        }
    }

    async function react(button) {
        const data = await busy(button, () => api('/ajax/react.php', { dish_id: Number(button.dataset.dishId), type: button.dataset.react }));
        if (!data) return;
        if (!data.success) return failure(data);
        $$('[data-react]').forEach((peer) => peer.setAttribute('aria-pressed', String(data.reaction === peer.dataset.react)));
        const likes = $('[data-count="like"]');
        const dislikes = $('[data-count="dislike"]');
        if (likes) likes.textContent = data.likes;
        if (dislikes) dislikes.textContent = data.dislikes;
    }

    async function updateCartLine(button) {
        const line = button.closest('.cart-line');
        if (!line) return;
        const isRemove = button.hasAttribute('data-remove-line');
        const payload = isRemove
            ? { action: 'remove', dish_id: Number(line.dataset.dishId) }
            : { action: 'update', dish_id: Number(line.dataset.dishId), delta: Number(button.dataset.delta) };
        line.classList.add('is-updating');
        const data = await api('/ajax/cart.php', payload);
        line.classList.remove('is-updating');
        if (!data.success) return failure(data);

        setCartCount(data.cart_count);
        if (data.removed) {
            line.classList.add('is-leaving');
            await wait(220);
            line.remove();
        } else {
            $('[data-line-qty]', line).textContent = data.qty;
            $('[data-line-total]', line).textContent = data.line_total;
            $('[data-qty-step][data-delta="-1"]', line).disabled = data.qty <= 1;
            $('[data-qty-step][data-delta="1"]', line).disabled = data.qty >= data.max_qty;
        }
        if (data.empty) {
            $('[data-cart-layout]')?.setAttribute('hidden', '');
            $('[data-empty-state]')?.removeAttribute('hidden');
            return;
        }
        $('[data-summary="subtotal"]').textContent = data.subtotal;
        $('[data-summary="fee"]').textContent = data.delivery_fee;
        $('[data-summary="total"]').textContent = data.total;
        const notice = $('[data-unavailable-notice]');
        const checkout = $('[data-checkout]');
        if (notice) notice.hidden = data.unavailable < 1;
        if (checkout) {
            if (data.unavailable > 0) {
                checkout.setAttribute('aria-disabled', 'true');
                checkout.setAttribute('tabindex', '-1');
            } else {
                checkout.removeAttribute('aria-disabled');
                checkout.removeAttribute('tabindex');
            }
        }
    }

    function initMenuFilter() {
        const grid = document.getElementById('dish-grid');
        if (!grid) return;
        const search = document.getElementById('menu-search');
        const sort = document.getElementById('menu-sort');
        const chips = $$('[data-category-chip]');
        const results = document.getElementById('results-line');
        const empty = document.getElementById('menu-empty');
        const items = $$('[data-dish-item]', grid);
        const state = {
            q: search.value,
            category: chips.find((chip) => chip.classList.contains('is-active'))?.dataset.categoryChip || '',
            sort: sort.value,
        };

        const sorters = {
            popular: (a, b) => a.dataset.rank - b.dataset.rank,
            price_low: (a, b) => a.dataset.price - b.dataset.price || a.dataset.rank - b.dataset.rank,
            price_high: (a, b) => b.dataset.price - a.dataset.price || a.dataset.rank - b.dataset.rank,
            rating: (a, b) => b.dataset.rating - a.dataset.rating || b.dataset.reviews - a.dataset.reviews || a.dataset.rank - b.dataset.rank,
            newest: (a, b) => b.dataset.created - a.dataset.created || a.dataset.rank - b.dataset.rank,
        };

        function apply(animate) {
            const query = norm(state.q);
            const ordered = [...items].sort(sorters[state.sort] || sorters.popular);
            let shown = 0;
            ordered.forEach((item) => {
                const matches = (!state.category || item.dataset.category === state.category) && (!query || norm(item.dataset.search).includes(query));
                const wasHidden = item.hidden;
                item.hidden = !matches;
                if (matches) {
                    shown++;
                    if (animate && wasHidden) {
                        item.classList.remove('is-entering');
                        void item.offsetWidth;
                        item.classList.add('is-entering');
                    }
                }
                grid.appendChild(item);
            });
            chips.forEach((chip) => {
                const active = chip.dataset.categoryChip === state.category;
                chip.classList.toggle('is-active', active);
                if (active) chip.setAttribute('aria-current', 'true');
                else chip.removeAttribute('aria-current');
            });
            grid.hidden = shown === 0;
            empty.hidden = shown !== 0;
            results.textContent = shown === 0 ? '' : shown === 1 ? results.dataset.one : fmt(results.dataset.many, shown);
            const params = new URLSearchParams();
            if (state.q.trim()) params.set('search', state.q.trim());
            if (state.category) params.set('category', state.category);
            if (state.sort !== 'popular') params.set('sort', state.sort);
            const qs = params.toString();
            history.replaceState(null, '', location.pathname + (qs ? `?${qs}` : ''));
        }

        search.addEventListener('input', () => {
            state.q = search.value;
            apply(true);
        });
        search.form.addEventListener('submit', (event) => event.preventDefault());
        sort.addEventListener('change', () => {
            state.sort = sort.value;
            apply(false);
        });
        chips.forEach((chip) => chip.addEventListener('click', (event) => {
            event.preventDefault();
            state.category = chip.dataset.categoryChip;
            apply(true);
        }));
        document.getElementById('menu-reset')?.addEventListener('click', () => {
            state.q = '';
            state.category = '';
            search.value = '';
            apply(true);
            search.focus();
        });
        apply(false);
    }

    function fieldMessage(input) {
        const v = input.validity;
        if (v.valueMissing) return I18N.required;
        if (v.typeMismatch && input.type === 'email') return I18N.email;
        if (v.tooShort) return fmt(I18N.tooShort, input.minLength);
        if (v.patternMismatch) return input.dataset.patternMessage || I18N.required;
        if (v.rangeUnderflow || v.rangeOverflow || v.stepMismatch) return input.dataset.rangeMessage || I18N.required;
        if (input.dataset.match) {
            const other = document.querySelector(input.dataset.match);
            if (other && other.value !== input.value) return I18N.mismatch;
        }
        return '';
    }

    function showFieldError(input, message) {
        const wrap = input.closest('.field');
        if (!wrap) return;
        let error = $('.field-error', wrap);
        if (!message) {
            error?.remove();
            wrap.classList.remove('has-error');
            input.removeAttribute('aria-invalid');
            return;
        }
        if (!error) {
            error = document.createElement('p');
            error.className = 'field-error';
            error.id = `${input.id}-error`;
            wrap.appendChild(error);
        }
        error.textContent = message;
        wrap.classList.add('has-error');
        input.setAttribute('aria-invalid', 'true');
        const described = new Set((input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
        described.add(error.id);
        input.setAttribute('aria-describedby', [...described].join(' '));
    }

    function validateForm(form) {
        let firstInvalid = null;
        $$('input, select, textarea', form).forEach((input) => {
            if (input.type === 'hidden' || input.disabled || input.type === 'radio') return;
            const message = fieldMessage(input);
            showFieldError(input, message);
            if (message && !firstInvalid) firstInvalid = input;
        });
        firstInvalid?.focus();
        return !firstInvalid;
    }

    function initForms() {
        document.addEventListener('submit', async (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            if (form.hasAttribute('data-confirm') && form.dataset.confirmed !== '1') {
                event.preventDefault();
                const ok = await confirmDialog(form.dataset.confirm, form.dataset.confirmLabel);
                if (ok) {
                    form.dataset.confirmed = '1';
                    form.requestSubmit(event.submitter || undefined);
                }
                return;
            }
            delete form.dataset.confirmed;

            if (form.hasAttribute('data-validate') && !validateForm(form)) {
                event.preventDefault();
                return;
            }

            if (form.hasAttribute('data-async')) {
                event.preventDefault();
                const control = $('[data-async-control]', form);
                await busy(control, async () => {
                    try {
                        const response = await fetch(form.getAttribute('action') || location.href, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
                            credentials: 'same-origin',
                        });
                        const data = await response.json();
                        if (!data.success) return toast(data.message || I18N.server, { type: 'error' });
                        control.setAttribute('aria-checked', String(data.value));
                        if (data.message) toast(data.message);
                    } catch (e) {
                        toast(I18N.network, { type: 'error' });
                    }
                });
                return;
            }

            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }
            if (form.hasAttribute('data-validate') || form.hasAttribute('data-once')) {
                form.dataset.submitting = '1';
                const submit = event.submitter || $('button[type="submit"]', form);
                submit?.setAttribute('aria-busy', 'true');
            }
        });

        document.addEventListener('input', (event) => {
            const input = event.target;
            if (input instanceof HTMLElement && input.closest('.has-error') && input.closest('form[data-validate]')) {
                if (!fieldMessage(input)) showFieldError(input, '');
            }
        });

        window.addEventListener('pageshow', () => {
            $$('form[data-submitting]').forEach((form) => {
                delete form.dataset.submitting;
                $$('[aria-busy]', form).forEach((el) => el.removeAttribute('aria-busy'));
            });
        });
    }

    async function submitReview(form) {
        const type = form.dataset.reviewForm;
        const rating = form.querySelector('input[name="rating"]:checked');
        const comment = form.elements.comment.value.trim();
        const ratingError = $('[data-rating-error]', form);
        const commentInput = form.elements.comment;
        let valid = true;
        if (ratingError) ratingError.hidden = !!rating;
        if (!rating) valid = false;
        showFieldError(commentInput, comment ? '' : I18N.required);
        if (!comment) valid = false;
        if (!valid) {
            (rating ? commentInput : form.querySelector('input[name="rating"]'))?.focus();
            return;
        }
        const submit = $('button[type="submit"]', form);
        const payload = { action: type, rating: Number(rating.value), comment };
        if (type === 'create') payload.dish_id = Number(form.dataset.dishId);
        else payload.review_id = Number(form.dataset.reviewId);
        const data = await busy(submit, () => api('/ajax/review.php', payload));
        if (!data) return;
        if (!data.success) return failure(data);
        location.reload();
    }

    function initReviews() {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (form instanceof HTMLFormElement && form.hasAttribute('data-review-form')) {
                event.preventDefault();
                submitReview(form);
            }
        });
        document.addEventListener('click', async (event) => {
            const editToggle = event.target.closest('[data-review-edit-toggle]');
            if (editToggle) {
                const panel = document.getElementById('review-edit');
                const view = document.getElementById('review-view');
                const open = panel.hidden;
                panel.hidden = !open;
                view.hidden = open;
                if (open) $('textarea', panel)?.focus();
                return;
            }
            const remove = event.target.closest('[data-review-delete]');
            if (remove) {
                const ok = await confirmDialog(remove.dataset.confirm, remove.dataset.confirmLabel);
                if (!ok) return;
                const data = await busy(remove, () => api('/ajax/review.php', { action: 'delete', review_id: Number(remove.dataset.reviewDelete) }));
                if (!data) return;
                if (!data.success) return failure(data);
                location.reload();
                return;
            }
            const report = event.target.closest('[data-report-review]');
            if (report) {
                const data = await busy(report, () => api('/ajax/report_review.php', { review_id: Number(report.dataset.reportReview) }));
                if (!data) return;
                if (!data.success) return failure(data);
                toast(data.message);
                report.disabled = true;
                report.querySelector('[data-report-label]').textContent = report.dataset.doneLabel;
            }
        });
    }

    function initActions() {
        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const add = target.closest('[data-add-cart]');
            if (add) return void addToCart(add);
            const save = target.closest('[data-toggle]');
            if (save) return void toggleSave(save);
            const reaction = target.closest('[data-react]');
            if (reaction) return void react(reaction);
            const cartButton = target.closest('[data-qty-step], [data-remove-line]');
            if (cartButton && !cartButton.disabled) return void updateCartLine(cartButton);
            const disabledLink = target.closest('a[aria-disabled="true"]');
            if (disabledLink) event.preventDefault();
        });
    }

    initNav();
    initActions();
    initForms();
    initReviews();
    initMenuFilter();
})();
