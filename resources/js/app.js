/*
 * Alpine.js is bundled with Livewire 4 and started by @livewireScripts.
 * Register shared Alpine components here before Livewire boots Alpine.
 */
import './theme';
import './pwa';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.store('toasts', {
        items: [],
        push(message, type = 'success', timeout = 4000) {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type });
            setTimeout(() => this.dismiss(id), timeout);
        },
        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });

    Alpine.data('confirmSubmit', (message) => ({
        submit(event) {
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        },
    }));

    /*
     * Purchase-order line editor. Totals shown here are a preview only;
     * the server recalculates every amount when the draft is saved.
     */
    Alpine.data('purchaseOrderForm', ({ supplier, lines, costs, errors, locale, currency }) => {
        let nextKey = 0;
        const makeLine = (line = {}) => ({
            key: nextKey++,
            product_variant_id: line.product_variant_id ?? '',
            quantity: line.quantity ?? '1',
            unit_cost: line.unit_cost ?? '',
            costTouched: (line.unit_cost ?? '') !== '',
        });

        return {
            supplier,
            costs,
            errors,
            lines: lines.length ? lines.map(makeLine) : [makeLine()],

            init() {
                this.$watch('supplier', () => this.lines.forEach((line) => this.applyCost(line)));
            },
            addLine() {
                this.lines.push(makeLine());
            },
            removeLine(index) {
                this.lines.splice(index, 1);
                this.errors = {};
                if (!this.lines.length) {
                    this.addLine();
                }
            },
            applyCost(line) {
                const cost = this.costs[this.supplier]?.[line.product_variant_id];
                if (!line.costTouched && cost !== undefined) {
                    line.unit_cost = String(cost);
                }
            },
            error(index, field) {
                return this.errors[`lines.${index}.${field}`] ?? null;
            },
            lineTotal(line) {
                return (parseInt(line.quantity, 10) || 0) * (parseInt(line.unit_cost, 10) || 0);
            },
            subtotal() {
                return this.lines.reduce((sum, line) => sum + (line.product_variant_id ? this.lineTotal(line) : 0), 0);
            },
            formatMoney(amount) {
                return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(amount);
            },
        };
    });
});

window.addEventListener('toast', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    window.Alpine?.store('toasts').push(detail.message, detail.type ?? 'success');
});

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (! (form instanceof HTMLFormElement) || ! form.hasAttribute('data-wishlist-toggle')) {
        return;
    }

    event.preventDefault();

    const button = form.querySelector('button[type="submit"]');
    const scrollY = window.scrollY;

    if (button) {
        button.disabled = true;
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        });

        if (! response.ok) {
            return;
        }

        syncWishlist(form, await response.json());
        window.scrollTo(0, scrollY);
    } finally {
        if (button) {
            button.disabled = false;
        }
    }
});

function syncWishlist(form, data) {
    document.querySelectorAll(`[data-wishlist-toggle][data-product-slug="${CSS.escape(form.dataset.productSlug)}"]`).forEach((item) => {
        const control = item.querySelector('button[type="submit"]');
        const icon = item.querySelector('svg');

        item.action = data.wished ? item.dataset.destroyUrl : item.dataset.storeUrl;

        let method = item.querySelector('input[name="_method"]');

        if (data.wished) {
            if (! method) {
                method = document.createElement('input');
                method.type = 'hidden';
                method.name = '_method';
                item.append(method);
            }

            method.value = 'DELETE';
        } else if (method) {
            method.remove();
        }

        if (control) {
            control.setAttribute('aria-pressed', data.wished ? 'true' : 'false');
            control.setAttribute('aria-label', data.wished ? item.dataset.removeLabel : item.dataset.addLabel);
        }

        if (icon) {
            icon.setAttribute('fill', data.wished ? 'currentColor' : 'none');
            icon.classList.toggle('text-danger', data.wished);
        }

        if (! data.wished) {
            item.closest('[data-wishlist-item]')?.remove();
        }
    });

    const nav = document.querySelector('[data-wishlist-nav]');

    if (nav) {
        nav.setAttribute('aria-label', data.nav_label);

        const icon = nav.querySelector('svg');

        if (icon) {
            icon.setAttribute('fill', data.count > 0 ? 'currentColor' : 'none');
            icon.classList.toggle('text-danger', data.count > 0);
        }

        const badge = nav.querySelector('[data-wishlist-count]');

        if (badge) {
            badge.textContent = data.count > 99 ? '99+' : String(data.count);
            badge.dataset.wishlistCount = String(data.count);
            badge.classList.toggle('hidden', data.count < 1);
        }
    }

    const pageCount = document.querySelector('[data-wishlist-page-count]');

    if (pageCount) {
        pageCount.textContent = data.count_label;
    }

    window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message, type: 'success' } }));

    if (data.count === 0 && document.querySelector('[data-wishlist-list]') && ! document.querySelector('[data-wishlist-item]')) {
        window.location.reload();
    }
}
