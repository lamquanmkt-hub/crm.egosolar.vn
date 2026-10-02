/**
 * Hộp thoại — phần được dùng nhiều nhất (26 nút mở, 79 nút đóng, 26 lệnh JS).
 *
 * Trình tự thao tác DOM bám đúng bản đo từ Bootstrap 5.3.3:
 *   mở:  body.modal-open + overflow:hidden -> tạo .modal-backdrop.fade -> .show
 *        -> modal display:block, bỏ aria-hidden, thêm aria-modal/role -> .show
 *   đóng: bỏ .show -> display:none, aria-hidden=true, bỏ aria-modal/role
 *        -> gỡ backdrop -> bỏ body.modal-open -> trả cuộn trang
 */

import { Backdrop } from './backdrop.js';
import { FocusTrap } from './focustrap.js';
import { hideScrollbar, resetScrollbar, scrollbarWidth } from './scrollbar.js';
import { afterTransition, dataConfig, isVisible, reflow, registry, targetOf, trigger } from './util.js';

const store = registry();

const DEFAULTS = { backdrop: true, keyboard: true, focus: true };

export class Modal {
    constructor(element, config = {}) {
        this._element = element;
        this._config = { ...DEFAULTS, ...dataConfig(element), ...config };
        this._dialog = element.querySelector('.modal-dialog');
        this._backdrop = new Backdrop({
            className: 'modal-backdrop',
            isVisible: Boolean(this._config.backdrop),
            isAnimated: this._isAnimated(),
            clickCallback: () => this._onBackdropClick(),
        });
        this._focustrap = new FocusTrap(element);
        this._isShown = false;
        this._isTransitioning = false;
        this._scrollbarPatched = false;

        this._addEventListeners();
        store.set(element, this);
    }

    static getInstance(element) {
        return store.get(element);
    }

    static getOrCreateInstance(element, config = {}) {
        return store.get(element) || new Modal(element, config);
    }

    toggle(relatedTarget) {
        return this._isShown ? this.hide() : this.show(relatedTarget);
    }

    show(relatedTarget) {
        if (this._isShown || this._isTransitioning) return;

        if (trigger(this._element, 'show.bs.modal', { relatedTarget }).defaultPrevented) return;

        this._isShown = true;
        this._isTransitioning = true;

        hideScrollbar();
        document.body.classList.add('modal-open');
        this._adjustDialog();
        this._backdrop.show(() => this._showElement(relatedTarget));
    }

    hide() {
        if (!this._isShown || this._isTransitioning) return;

        if (trigger(this._element, 'hide.bs.modal').defaultPrevented) return;

        this._isShown = false;
        this._isTransitioning = true;
        this._focustrap.deactivate();
        this._element.classList.remove('show');

        afterTransition(() => this._hideModal(), this._element, this._isAnimated());
    }

    dispose() {
        this._backdrop.dispose();
        this._focustrap.deactivate();
        store.remove(this._element);
    }

    _isAnimated() {
        return this._element.classList.contains('fade');
    }

    _showElement(relatedTarget) {
        if (!document.body.contains(this._element)) document.body.append(this._element);

        this._element.style.display = 'block';
        this._element.removeAttribute('aria-hidden');
        this._element.setAttribute('aria-modal', 'true');
        this._element.setAttribute('role', 'dialog');
        this._element.scrollTop = 0;

        const body = this._dialog?.querySelector('.modal-body');

        if (body) body.scrollTop = 0;

        reflow(this._element);
        this._element.classList.add('show');

        afterTransition(
            () => {
                if (this._config.focus) this._focustrap.activate();
                this._isTransitioning = false;
                trigger(this._element, 'shown.bs.modal', { relatedTarget });
            },
            this._dialog,
            this._isAnimated()
        );
    }

    _hideModal() {
        this._element.style.display = 'none';
        this._element.setAttribute('aria-hidden', 'true');
        this._element.removeAttribute('aria-modal');
        this._element.removeAttribute('role');
        this._isTransitioning = false;

        this._backdrop.hide(() => {
            document.body.classList.remove('modal-open');
            this._resetAdjustments();
            resetScrollbar();
            trigger(this._element, 'hidden.bs.modal');
        });
    }

    _onBackdropClick() {
        if (this._config.backdrop === 'static') {
            this._staticBounce();

            return;
        }

        if (this._config.backdrop) this.hide();
    }

    /** backdrop:'static' — nhấp ra ngoài chỉ nảy nhẹ hộp thoại chứ không đóng. */
    _staticBounce() {
        if (trigger(this._element, 'hidePrevented.bs.modal').defaultPrevented) return;

        const isOverflowing = this._element.scrollHeight > document.documentElement.clientHeight;
        const initialOverflowY = this._element.style.overflowY;

        if (initialOverflowY === 'hidden' || this._element.classList.contains('modal-static')) return;

        if (!isOverflowing) this._element.style.overflowY = 'hidden';

        this._element.classList.add('modal-static');

        afterTransition(
            () => {
                this._element.classList.remove('modal-static');
                afterTransition(
                    () => {
                        this._element.style.overflowY = initialOverflowY;
                    },
                    this._element
                );
            },
            this._dialog
        );

        this._element.focus();
    }

    _adjustDialog() {
        const modalOverflowing = this._element.scrollHeight > document.documentElement.clientHeight;
        const width = scrollbarWidth();
        const bodyOverflowing = width > 0;

        if (bodyOverflowing && !modalOverflowing) {
            this._element.style.paddingRight = `${width}px`;
            this._scrollbarPatched = true;
        }

        if (!bodyOverflowing && modalOverflowing) {
            this._element.style.paddingLeft = `${width}px`;
            this._scrollbarPatched = true;
        }
    }

    _resetAdjustments() {
        if (!this._scrollbarPatched) return;

        this._element.style.paddingLeft = '';
        this._element.style.paddingRight = '';
        this._scrollbarPatched = false;
    }

    _addEventListeners() {
        this._element.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;

            if (this._config.keyboard) {
                this.hide();

                return;
            }

            this._staticBounce();
        });

        // Chỉ đóng khi cả mousedown lẫn click đều rơi trúng nền, để kéo chọn chữ
        // trong hộp thoại rồi nhả chuột ra ngoài không làm mất nội dung đang nhập.
        this._element.addEventListener('mousedown', (event) => {
            if (event.target !== this._element) return;

            this._element.addEventListener(
                'click',
                (event2) => {
                    if (event2.target !== this._element) return;

                    this._onBackdropClick();
                },
                { once: true }
            );
        });

        window.addEventListener('resize', () => {
            if (this._isShown && !this._isTransitioning) this._adjustDialog();
        });
    }
}

export function wireModal() {
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-bs-toggle="modal"]');

        if (btn) {
            const target = targetOf(btn);

            if (!target) return;

            if (['A', 'AREA'].includes(btn.tagName)) event.preventDefault();

            // Trả focus về nút đã mở, nhưng chỉ khi nút đó còn trên trang.
            target.addEventListener(
                'show.bs.modal',
                (showEvent) => {
                    if (showEvent.defaultPrevented) return;

                    target.addEventListener('hidden.bs.modal', () => {
                        if (isVisible(btn)) btn.focus();
                    }, { once: true });
                },
                { once: true }
            );

            const opened = document.querySelector('.modal.show');

            if (opened && opened !== target) Modal.getInstance(opened)?.hide();

            Modal.getOrCreateInstance(target).toggle(btn);

            return;
        }

        const dismiss = event.target.closest('[data-bs-dismiss="modal"]');

        if (!dismiss || dismiss.disabled) return;

        const host = targetOf(dismiss) || dismiss.closest('.modal');

        if (!host) return;

        if (['A', 'AREA'].includes(dismiss.tagName)) event.preventDefault();

        Modal.getOrCreateInstance(host).hide();
    });
}
