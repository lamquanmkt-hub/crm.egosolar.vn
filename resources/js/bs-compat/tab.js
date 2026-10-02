/** Tab và pill (11 chỗ dùng + 3 lệnh JS). */

import { afterTransition, registry, reflow, targetOf, trigger } from './util.js';

const store = registry();

const SELECTOR_TOGGLE = '[data-bs-toggle="tab"], [data-bs-toggle="pill"], [data-bs-toggle="list"]';

function setIfMissing(el, attr, value) {
    if (!el.hasAttribute(attr)) el.setAttribute(attr, value);
}

export class Tab {
    constructor(element) {
        this._element = element;
        this._parent = element.closest('.list-group, .nav, [role="tablist"]');

        store.set(element, this);

        if (!this._parent) return;

        this._setInitialAttributes();
        element.addEventListener('keydown', (event) => this._keydown(event));
    }

    static getInstance(element) {
        return store.get(element);
    }

    static getOrCreateInstance(element) {
        return store.get(element) || new Tab(element);
    }

    show() {
        const el = this._element;

        if (el.classList.contains('active') || el.classList.contains('disabled') || el.disabled) return;

        const parent = el.closest('.nav, .list-group');
        const active = parent
            ? [...parent.querySelectorAll('.active')].find((n) => n !== el && this._isNavItem(n, parent))
            : null;

        if (active && trigger(active, 'hide.bs.tab', { relatedTarget: el }).defaultPrevented) return;
        if (trigger(el, 'show.bs.tab', { relatedTarget: active }).defaultPrevented) return;

        if (active) this._deactivate(active, el);

        this._activate(el, active);
    }

    /**
     * Gắn sẵn vai trò và trạng thái ARIA cho cả cụm tab ngay khi tải trang.
     * Thiếu bước này thì trình đọc màn hình không biết tab nào đang chọn, và
     * Tab trên bàn phím sẽ dừng ở từng nút thay vì nhảy qua cả cụm.
     */
    _setInitialAttributes() {
        setIfMissing(this._parent, 'role', 'tablist');

        for (const child of this._children()) {
            const isActive = child.classList.contains('active');
            const outer = child.closest('.nav-item, .list-group-item') || child;

            child.setAttribute('aria-selected', String(isActive));

            if (outer !== child) setIfMissing(outer, 'role', 'presentation');
            if (!isActive) child.setAttribute('tabindex', '-1');

            setIfMissing(child, 'role', 'tab');

            const pane = targetOf(child);

            if (!pane) continue;

            setIfMissing(pane, 'role', 'tabpanel');
            if (child.id) setIfMissing(pane, 'aria-labelledby', child.id);
        }
    }

    _children() {
        return [...this._parent.querySelectorAll(SELECTOR_TOGGLE)]
            .filter((n) => !n.classList.contains('dropdown-toggle'));
    }

    _keydown(event) {
        if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;

        event.stopPropagation();
        event.preventDefault();

        const items = this._children().filter((n) => !n.disabled && !n.classList.contains('disabled'));
        let next;

        if (event.key === 'Home') {
            next = items[0];
        } else if (event.key === 'End') {
            next = items[items.length - 1];
        } else {
            const forward = ['ArrowRight', 'ArrowDown'].includes(event.key);
            const i = items.indexOf(event.target);
            const size = items.length;

            next = items[(i + (forward ? 1 : -1) + size) % size]; // vòng lại đầu/cuối
        }

        if (!next) return;

        next.focus({ preventScroll: true });
        Tab.getOrCreateInstance(next).show();
    }

    _isNavItem(node, parent) {
        return node.parentElement?.closest('.nav, .list-group') === parent || node.parentElement === parent
            || node.closest('.nav-item')?.parentElement === parent;
    }

    _activate(el, related) {
        el.classList.add('active');
        el.setAttribute('aria-selected', 'true');
        el.removeAttribute('tabindex');

        const pane = targetOf(el);

        this._toggleShow(pane, true, () => trigger(el, 'shown.bs.tab', { relatedTarget: related }));
    }

    _deactivate(el, related) {
        el.classList.remove('active');
        el.setAttribute('aria-selected', 'false');
        el.setAttribute('tabindex', '-1');

        const pane = targetOf(el);

        this._toggleShow(pane, false, () => trigger(el, 'hidden.bs.tab', { relatedTarget: related }));
    }

    _toggleShow(pane, isOpen, done) {
        if (!pane) {
            done();

            return;
        }

        const animated = pane.classList.contains('fade');

        pane.classList.toggle('active', isOpen);

        if (!animated) {
            pane.classList.toggle('show', isOpen);
            done();

            return;
        }

        if (isOpen) {
            reflow(pane);
            pane.classList.add('show');
        } else {
            pane.classList.remove('show');
        }

        afterTransition(done, pane, true);
    }
}

export function wireTab() {
    // Bootstrap dựng thể hiện cho các tab đang active lúc tải trang, chính lúc đó
    // mới gắn ARIA cho cả cụm. Giữ đúng thời điểm để trạng thái đầu trang khớp.
    const initAll = () => {
        for (const el of document.querySelectorAll(SELECTOR_TOGGLE)) {
            if (el.classList.contains('active')) Tab.getOrCreateInstance(el);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll, { once: true });
    } else {
        initAll();
    }

    document.addEventListener('click', (event) => {
        const btn = event.target.closest(SELECTOR_TOGGLE);

        if (!btn) return;

        event.preventDefault();

        if (btn.classList.contains('disabled') || btn.disabled) return;

        Tab.getOrCreateInstance(btn).show();
    });
}
