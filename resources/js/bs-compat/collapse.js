/**
 * Gập/mở khối nội dung (28 chỗ dùng).
 *
 * Chiều cao phải đi qua lớp .collapsing với height tính bằng px thì mới có
 * chuyển động; đặt thẳng height:auto là nhảy phựt một cái.
 */

import { afterTransition, dataConfig, registry, reflow, targetOf, trigger } from './util.js';

const store = registry();

export class Collapse {
    constructor(element, config = {}) {
        this._element = element;
        this._config = { toggle: true, parent: null, ...dataConfig(element), ...config };
        this._isTransitioning = false;
        this._triggers = [...document.querySelectorAll('[data-bs-toggle="collapse"]')]
            .filter((t) => targetOf(t) === element);

        store.set(element, this);
    }

    static getInstance(element) {
        return store.get(element);
    }

    static getOrCreateInstance(element, config = {}) {
        return store.get(element) || new Collapse(element, { toggle: false, ...config });
    }

    get _isShown() {
        return this._element.classList.contains('show');
    }

    toggle() {
        return this._isShown ? this.hide() : this.show();
    }

    show() {
        if (this._isTransitioning || this._isShown) return;

        if (trigger(this._element, 'show.bs.collapse').defaultPrevented) return;

        this._element.classList.remove('collapse');
        this._element.classList.add('collapsing');
        this._element.style.height = '0px';
        this._setTriggers(true);
        this._isTransitioning = true;

        afterTransition(
            () => {
                this._isTransitioning = false;
                this._element.classList.remove('collapsing');
                this._element.classList.add('collapse', 'show');
                this._element.style.height = '';
                trigger(this._element, 'shown.bs.collapse');
            },
            this._element,
            true
        );

        reflow(this._element);
        this._element.style.height = `${this._element.scrollHeight}px`;
    }

    hide() {
        if (this._isTransitioning || !this._isShown) return;

        if (trigger(this._element, 'hide.bs.collapse').defaultPrevented) return;

        this._element.style.height = `${this._element.getBoundingClientRect().height}px`;
        reflow(this._element);

        this._element.classList.add('collapsing');
        this._element.classList.remove('collapse', 'show');
        this._setTriggers(false);
        this._isTransitioning = true;

        afterTransition(
            () => {
                this._isTransitioning = false;
                this._element.classList.remove('collapsing');
                this._element.classList.add('collapse');
                trigger(this._element, 'hidden.bs.collapse');
            },
            this._element,
            true
        );

        this._element.style.height = '';
    }

    /** Nút bấm mang lớp .collapsed khi khối đang đóng — nhiều CSS xoay mũi tên theo lớp này. */
    _setTriggers(isOpen) {
        for (const t of this._triggers) {
            t.classList.toggle('collapsed', !isOpen);
            t.setAttribute('aria-expanded', String(isOpen));
        }
    }
}

export function wireCollapse() {
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-bs-toggle="collapse"]');

        if (!btn) return;

        if (['A', 'AREA'].includes(btn.tagName)) event.preventDefault();

        const target = targetOf(btn);

        if (!target) return;

        Collapse.getOrCreateInstance(target).toggle();
    });
}
