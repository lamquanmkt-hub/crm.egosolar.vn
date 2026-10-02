/** Thông báo nổi. Chỉ dùng ở layout + product-form-serial.js. */

import { afterTransition, dataConfig, registry, reflow, targetOf, trigger } from './util.js';

const store = registry();

const DEFAULTS = { animation: true, autohide: true, delay: 5000 };

export class Toast {
    constructor(element, config = {}) {
        this._element = element;
        this._config = { ...DEFAULTS, ...dataConfig(element), ...config };
        this._timeout = null;
        store.set(element, this);
    }

    static getInstance(element) {
        return store.get(element);
    }

    static getOrCreateInstance(element, config = {}) {
        return store.get(element) || new Toast(element, config);
    }

    get isShown() {
        return this._element.classList.contains('show');
    }

    show() {
        if (trigger(this._element, 'show.bs.toast').defaultPrevented) return;

        this._element.classList.remove('hide');

        if (this._config.animation) {
            this._element.classList.add('fade');
            reflow(this._element);
        }

        this._element.classList.add('showing');

        afterTransition(
            () => {
                this._element.classList.add('show');
                this._element.classList.remove('showing');
                trigger(this._element, 'shown.bs.toast');
                this._scheduleHide();
            },
            this._element,
            this._config.animation
        );
    }

    hide() {
        if (!this.isShown) return;

        if (trigger(this._element, 'hide.bs.toast').defaultPrevented) return;

        this._element.classList.add('showing');

        afterTransition(
            () => {
                this._element.classList.add('hide');
                this._element.classList.remove('showing', 'show');
                trigger(this._element, 'hidden.bs.toast');
            },
            this._element,
            this._config.animation
        );
    }

    dispose() {
        clearTimeout(this._timeout);
        store.remove(this._element);
    }

    _scheduleHide() {
        if (!this._config.autohide) return;

        clearTimeout(this._timeout);
        this._timeout = setTimeout(() => this.hide(), this._config.delay);
    }
}

export function wireToast() {
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-bs-dismiss="toast"]');

        if (!btn || btn.disabled) return;

        const host = targetOf(btn) || btn.closest('.toast');

        if (!host) return;

        Toast.getOrCreateInstance(host).hide();
    });
}
