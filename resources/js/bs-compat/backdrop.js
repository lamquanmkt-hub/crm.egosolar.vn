/** Lớp phủ mờ phía sau modal/offcanvas. Bootstrap tạo và huỷ thẻ div này mỗi lần mở. */

import { afterTransition, reflow } from './util.js';

export class Backdrop {
    constructor({ className = 'modal-backdrop', isVisible = true, isAnimated = true, clickCallback = null } = {}) {
        this._className = className;
        this._isVisible = Boolean(isVisible);
        this._isAnimated = isAnimated;
        this._clickCallback = clickCallback;
        this._element = null;
        this._isAppended = false;
    }

    show(callback = () => {}) {
        if (!this._isVisible) {
            callback();

            return;
        }

        this._append();

        const el = this._element;

        if (this._isAnimated) reflow(el);

        el.classList.add('show');
        this._emulate(callback);
    }

    hide(callback = () => {}) {
        if (!this._isVisible) {
            callback();

            return;
        }

        this._element.classList.remove('show');
        this._emulate(() => {
            this.dispose();
            callback();
        });
    }

    dispose() {
        if (!this._isAppended) return;

        this._element.remove();
        this._isAppended = false;
        this._element = null;
    }

    _element_() {
        if (this._element) return this._element;

        const el = document.createElement('div');

        el.className = this._className;
        if (this._isAnimated) el.classList.add('fade');
        this._element = el;

        return el;
    }

    _append() {
        if (this._isAppended) return;

        const el = this._element_();

        document.body.append(el);
        el.addEventListener('mousedown', () => this._clickCallback?.());
        this._isAppended = true;
    }

    _emulate(callback) {
        afterTransition(callback, this._element_(), this._isAnimated);
    }
}
