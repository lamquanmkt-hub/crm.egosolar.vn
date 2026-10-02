/**
 * Menu thả xuống (2 chỗ dùng, cả hai đều .dropdown-menu-end trong ô bảng).
 *
 * Bootstrap dùng Popper để đặt vị trí. Ở đây chỉ cần đúng bốn thế cần thiết
 * (bottom/top × start/end) nên tự tính, khỏi kéo thêm một thư viện 20KB.
 *
 * Cách tính tránh phải suy luận về offsetParent: đặt menu ở gốc toạ độ với
 * transform 0, đo xem nó rơi vào đâu trên màn hình, rồi dịch đúng phần chênh.
 */

import { dataConfig, registry, trigger } from './util.js';

const store = registry();

const OFFSET = 2; // khoảng hở giữa nút và menu, bằng $dropdown-spacer của Bootstrap

export class Dropdown {
    constructor(element, config = {}) {
        this._element = element;
        this._config = { autoClose: true, ...dataConfig(element), ...config };
        this._menu = this._findMenu();

        store.set(element, this);
    }

    static getInstance(element) {
        return store.get(element);
    }

    static getOrCreateInstance(element, config = {}) {
        return store.get(element) || new Dropdown(element, config);
    }

    get _isShown() {
        return this._element.classList.contains('show');
    }

    toggle() {
        return this._isShown ? this.hide() : this.show();
    }

    show() {
        if (this._isShown || this._element.disabled || this._element.classList.contains('disabled')) return;
        if (!this._menu) return;

        if (trigger(this._element, 'show.bs.dropdown', { relatedTarget: this._element }).defaultPrevented) return;

        this._element.focus(); // Bootstrap chuyển focus sang nút khi mở, để mũi tên/Esc còn nhận
        this._element.classList.add('show');
        this._element.setAttribute('aria-expanded', 'true');
        this._menu.classList.add('show');
        this._position();

        trigger(this._element, 'shown.bs.dropdown', { relatedTarget: this._element });
    }

    hide() {
        if (!this._isShown) return;

        if (trigger(this._element, 'hide.bs.dropdown', { relatedTarget: this._element }).defaultPrevented) return;

        this._element.classList.remove('show');
        this._element.setAttribute('aria-expanded', 'false');
        this._menu.classList.remove('show');
        this._menu.removeAttribute('style');

        trigger(this._element, 'hidden.bs.dropdown', { relatedTarget: this._element });
    }

    dispose() {
        store.remove(this._element);
    }

    _findMenu() {
        const parent = this._element.parentElement;

        return parent?.querySelector(':scope > .dropdown-menu') || null;
    }

    _position() {
        const menu = this._menu;

        menu.style.position = 'absolute';
        menu.style.inset = '0px auto auto 0px';
        menu.style.margin = '0px';
        menu.style.transform = 'translate(0px, 0px)';

        const base = menu.getBoundingClientRect();
        const ref = this._element.getBoundingClientRect();
        const vw = document.documentElement.clientWidth;
        const vh = document.documentElement.clientHeight;

        const alignEnd = menu.classList.contains('dropdown-menu-end');
        let x = alignEnd ? ref.right - base.width : ref.left;
        let y = ref.bottom + OFFSET;

        // Lật lên trên khi phía dưới không đủ chỗ mà phía trên thì đủ.
        if (y + base.height > vh && ref.top - base.height - OFFSET >= 0) {
            y = ref.top - base.height - OFFSET;
        }

        // Kéo vào trong khi menu tràn mép trái/phải cửa sổ.
        if (x + base.width > vw) x = vw - base.width;
        if (x < 0) x = 0;

        menu.style.transform = `translate(${Math.round(x - base.x)}px, ${Math.round(y - base.y)}px)`;
    }
}

export function wireDropdown() {
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-bs-toggle="dropdown"]');

        if (btn) {
            event.preventDefault();

            const inst = Dropdown.getOrCreateInstance(btn);

            closeOthers(btn);
            inst.toggle();

            return;
        }

        // Nhấp ra ngoài thì đóng; nhấp bên trong menu chỉ đóng khi autoClose cho phép.
        for (const opened of document.querySelectorAll('[data-bs-toggle="dropdown"].show')) {
            const inst = Dropdown.getInstance(opened);

            if (!inst) continue;

            const insideMenu = inst._menu?.contains(event.target);
            const autoClose = inst._config.autoClose;

            if (autoClose === false) continue;
            if (insideMenu && (autoClose === 'outside')) continue;
            if (!insideMenu && (autoClose === 'inside')) continue;

            inst.hide();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        for (const opened of document.querySelectorAll('[data-bs-toggle="dropdown"].show')) {
            Dropdown.getInstance(opened)?.hide();
        }
    });
}

function closeOthers(current) {
    for (const opened of document.querySelectorAll('[data-bs-toggle="dropdown"].show')) {
        if (opened !== current) Dropdown.getInstance(opened)?.hide();
    }
}
