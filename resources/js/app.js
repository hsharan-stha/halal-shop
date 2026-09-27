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
