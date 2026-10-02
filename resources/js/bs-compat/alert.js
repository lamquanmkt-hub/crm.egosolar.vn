/** Đóng hộp cảnh báo (19 chỗ dùng) — Bootstrap gỡ hẳn phần tử khỏi DOM. */

import { afterTransition, registry, targetOf, transitionDuration, trigger } from './util.js';

const store = registry();

export class Alert {
    constructor(element) {
        this._element = element;
        store.set(element, this);
    }

    static getInstance(element) {
        return store.get(element);
    }

    static getOrCreateInstance(element) {
        return store.get(element) || new Alert(element);
    }

    close() {
        if (trigger(this._element, 'close.bs.alert').defaultPrevented) return;

        // Markup Bootstrap cũ mờ dần nhờ `.fade.show`; markup <x-ui.alert> không có
        // hai lớp đó nên đặt thẳng opacity. Làm cả hai thì đường nào cũng chạy.
        this._element.classList.remove('show');
        this._element.style.opacity = '0';

        // Chờ hay không là do CÓ transition thật hay không, chứ không do lớp `.fade`
        // — có vậy mới thôi phụ thuộc tên lớp của Bootstrap.
        afterTransition(
            () => {
                trigger(this._element, 'closed.bs.alert');
                store.remove(this._element);
                this._element.remove();
            },
            this._element,
            transitionDuration(this._element) > 0
        );
    }
}

export function wireAlert() {
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-bs-dismiss="alert"]');

        if (!btn || btn.disabled) return;

        // `[data-ego-alert]` là móc của <x-ui.alert>; `.alert` giữ cho markup
        // Bootstrap chưa chuyển. Bỏ `.alert` mà không có móc mới thì nút đóng chết.
        const host = targetOf(btn) || btn.closest('[data-ego-alert], .alert');

        if (!host) return;

        if (['A', 'AREA'].includes(btn.tagName)) event.preventDefault();

        Alert.getOrCreateInstance(host).close();
    });
}
