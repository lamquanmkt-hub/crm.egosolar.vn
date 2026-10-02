/**
 * Giam bàn phím trong modal/offcanvas đang mở.
 *
 * Không có phần này thì Tab vẫn chạy ra các nút phía sau lớp phủ — người dùng
 * bàn phím và trình đọc màn hình lạc ra ngoài hộp thoại mà không biết.
 */

export class FocusTrap {
    constructor(element) {
        this._element = element;
        this._isActive = false;
        this._lastTabNavDirection = null;
        this._onFocusin = this._handleFocusin.bind(this);
        this._onKeydown = this._handleKeydown.bind(this);
    }

    activate() {
        if (this._isActive) return;

        this._element.focus();
        document.addEventListener('focusin', this._onFocusin);
        document.addEventListener('keydown', this._onKeydown);
        this._isActive = true;
    }

    deactivate() {
        if (!this._isActive) return;

        this._isActive = false;
        document.removeEventListener('focusin', this._onFocusin);
        document.removeEventListener('keydown', this._onKeydown);
    }

    _handleFocusin(event) {
        const el = this._element;

        if (event.target === document || event.target === el || el.contains(event.target)) return;

        const items = el.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex^="-"])'
        );

        if (items.length === 0) {
            el.focus();
        } else if (this._lastTabNavDirection === 'backward') {
            items[items.length - 1].focus();
        } else {
            items[0].focus();
        }
    }

    _handleKeydown(event) {
        if (event.key !== 'Tab') return;

        this._lastTabNavDirection = event.shiftKey ? 'backward' : 'forward';
    }
}
