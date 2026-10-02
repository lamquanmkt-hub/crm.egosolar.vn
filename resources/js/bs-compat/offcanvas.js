/** Ngăn kéo trượt cạnh màn hình. Cùng cơ chế modal, khác lớp CSS và mặc định scroll. */

import { Backdrop } from './backdrop.js';
import { FocusTrap } from './focustrap.js';
import { hideScrollbar, resetScrollbar } from './scrollbar.js';
import { afterTransition, dataConfig, isVisible, registry, targetOf, trigger } from './util.js';

const store = registry();

const DEFAULTS = { backdrop: true, keyboard: true, scroll: false };

export class Offcanvas {
    constructor(element, config = {}) {
        this._element = element;
        this._config = { ...DEFAULTS, ...dataConfig(element), ...config };
        this._isShown = false;
        this._backdrop = new Backdrop({
            className: 'offcanvas-backdrop',
            isVisible: this._config.backdrop === true || this._config.backdrop === 'static',
            isAnimated: true,
            clickCallback: () => {
                if (this._config.backdrop === 'static') {
                    trigger(this._element, 'hidePrevented.bs.offcanvas');

                    return;
                }

                this.hide();
            },
        });
        this._focustrap = new FocusTrap(element);

        this._addEventListeners();
        store.set(element, this);
    }

    static getInstance(element) {
        return store.get(element);
    }

    static getOrCreateInstance(element, config = {}) {
        return store.get(element) || new Offcanvas(element, config);
    }

    toggle(relatedTarget) {
        return this._isShown ? this.hide() : this.show(relatedTarget);
    }

    show(relatedTarget) {
        if (this._isShown) return;

        if (trigger(this._element, 'show.bs.offcanvas', { relatedTarget }).defaultPrevented) return;

        this._isShown = true;
        this._backdrop.show();

        if (!this._config.scroll) hideScrollbar();

        this._element.setAttribute('aria-modal', 'true');
        this._element.setAttribute('role', 'dialog');
        this._element.classList.add('showing');

        afterTransition(
            () => {
                if (this._config.scroll !== true) this._focustrap.activate();

                this._element.classList.add('show');
                this._element.classList.remove('showing');
                trigger(this._element, 'shown.bs.offcanvas', { relatedTarget });
            },
            this._element,
            true
        );
    }

    hide() {
        if (!this._isShown) return;

        if (trigger(this._element, 'hide.bs.offcanvas').defaultPrevented) return;

        this._focustrap.deactivate();
        this._element.blur();
        this._isShown = false;
        this._element.classList.add('hiding');
        this._backdrop.hide();

        afterTransition(
            () => {
                this._element.classList.remove('show', 'hiding');
                this._element.removeAttribute('aria-modal');
                this._element.removeAttribute('role');

                if (!this._config.scroll) resetScrollbar();

                trigger(this._element, 'hidden.bs.offcanvas');
            },
            this._element,
            true
        );
    }

    dispose() {
        this._backdrop.dispose();
        this._focustrap.deactivate();
        store.remove(this._element);
    }

    _addEventListeners() {
        this._element.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;

            if (this._config.keyboard) {
                this.hide();

                return;
            }

            trigger(this._element, 'hidePrevented.bs.offcanvas');
        });
    }
}

export function wireOffcanvas() {
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-bs-toggle="offcanvas"]');

        if (btn) {
            const target = targetOf(btn);

            if (!target) return;

            if (['A', 'AREA'].includes(btn.tagName)) event.preventDefault();
            if (btn.disabled || btn.classList.contains('disabled')) return;

            target.addEventListener('hidden.bs.offcanvas', () => {
                if (isVisible(btn)) btn.focus();
            }, { once: true });

            const opened = document.querySelector('.offcanvas.show');

            if (opened && opened !== target) Offcanvas.getInstance(opened)?.hide();

            Offcanvas.getOrCreateInstance(target).toggle(btn);

            return;
        }

        const dismiss = event.target.closest('[data-bs-dismiss="offcanvas"]');

        if (!dismiss || dismiss.disabled) return;

        const host = targetOf(dismiss) || dismiss.closest('.offcanvas');

        if (!host) return;

        if (['A', 'AREA'].includes(dismiss.tagName)) event.preventDefault();

        Offcanvas.getOrCreateInstance(host).hide();
    });
}
