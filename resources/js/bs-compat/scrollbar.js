/**
 * Khoá cuộn trang khi mở modal/offcanvas.
 *
 * Phải bù đúng bề rộng thanh cuộn vào padding-right, không thì lúc mở modal cả
 * trang nhích ngang một nhịp. Giá trị gốc được cất vào data-bs-* y như Bootstrap
 * để nếu còn sót mã cũ đọc thuộc tính đó thì vẫn khớp.
 */

const SELECTOR_FIXED = '.fixed-top, .fixed-bottom, .is-fixed, .sticky-top';
const SELECTOR_STICKY = '.sticky-top';

function attrName(styleProp) {
    return 'data-bs-' + styleProp.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase());
}

function saveAndSet(el, styleProp, callback) {
    const width = scrollbarWidth();
    const attr = attrName(styleProp);
    const inline = el.style[styleProp];

    if (inline) el.setAttribute(attr, inline);

    const computed = window.getComputedStyle(el).getPropertyValue(
        styleProp.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase())
    );

    el.style[styleProp] = `${callback(Number.parseFloat(computed) || 0, width)}px`;
}

function restore(el, styleProp) {
    const attr = attrName(styleProp);
    const saved = el.getAttribute(attr);

    if (saved === null) {
        el.style.removeProperty(styleProp.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase()));

        return;
    }

    el.removeAttribute(attr);
    el.style[styleProp] = saved;
}

function each(selector, fn) {
    for (const el of document.querySelectorAll(selector)) fn(el);
}

export function scrollbarWidth() {
    return Math.abs(window.innerWidth - document.documentElement.clientWidth);
}

export function hideScrollbar() {
    const body = document.body;
    const width = scrollbarWidth();

    if (body.style.overflow) body.setAttribute('data-bs-overflow', body.style.overflow);
    body.style.overflow = 'hidden';

    saveAndSet(body, 'paddingRight', (calc) => calc + width);
    each(SELECTOR_FIXED, (el) => saveAndSet(el, 'paddingRight', (calc) => calc + width));
    each(SELECTOR_STICKY, (el) => saveAndSet(el, 'marginRight', (calc) => calc - width));
}

export function resetScrollbar() {
    const body = document.body;

    restore(body, 'overflow');
    restore(body, 'paddingRight');
    each(SELECTOR_FIXED, (el) => restore(el, 'paddingRight'));
    each(SELECTOR_STICKY, (el) => restore(el, 'marginRight'));
}
