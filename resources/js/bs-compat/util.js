/**
 * Tiện ích dùng chung cho lớp thay thế Bootstrap JS.
 *
 * Mọi hành vi ở thư mục này được đối chiếu với Bootstrap 5.3.3 thật bằng bộ đo
 * CDP (xem tests/Feature/Architecture/BootstrapJsRemovedTest.php để biết vì sao
 * lớp này tồn tại). Sửa gì ở đây thì phải đo lại, đừng sửa theo cảm tính.
 */

/** Ép trình duyệt tính lại layout — Bootstrap cần bước này trước khi thêm .show
 *  thì transition mới chạy, không thì trạng thái đầu và cuối gộp làm một khung. */
export const reflow = (el) => el.offsetHeight;

/** Đọc thời lượng transition thật của phần tử (ms). Giống getTransitionDurationFromElement. */
export function transitionDuration(el) {
    if (!el) return 0;

    let { transitionDuration: dur, transitionDelay: delay } = window.getComputedStyle(el);
    const fDur = Number.parseFloat(dur);
    const fDelay = Number.parseFloat(delay);

    if (!fDur && !fDelay) return 0;

    dur = dur.split(',')[0];
    delay = delay.split(',')[0];

    return (Number.parseFloat(dur) + Number.parseFloat(delay)) * 1000;
}

/**
 * Gọi callback sau khi transition kết thúc, kèm hẹn giờ dự phòng.
 * Không có dự phòng thì tab ẩn / prefers-reduced-motion sẽ treo modal vĩnh viễn.
 */
export function afterTransition(callback, el, waitForTransition = true) {
    if (!waitForTransition) {
        callback();

        return;
    }

    const emulated = transitionDuration(el) + 5;
    let called = false;

    const handler = ({ target }) => {
        if (target !== el) return;
        called = true;
        el.removeEventListener('transitionend', handler);
        callback();
    };

    el.addEventListener('transitionend', handler);
    setTimeout(() => {
        if (!called) el.dispatchEvent(new Event('transitionend'));
    }, emulated);
}

/**
 * Phát sự kiện kiểu Bootstrap ('show.bs.modal'...). Các thuộc tính phụ được gắn
 * thẳng lên đối tượng sự kiện chứ không nhét vào detail, vì mã hiện có đọc
 * `event.relatedTarget` — đúng như Bootstrap làm.
 */
export function trigger(el, type, props = {}) {
    const evt = new Event(type, { bubbles: true, cancelable: true });

    for (const [k, v] of Object.entries(props)) {
        Object.defineProperty(evt, k, { get: () => v });
    }

    el.dispatchEvent(evt);

    return evt;
}

/** Lấy phần tử đích của một nút bấm: ưu tiên data-bs-target, sau đó href. */
export function targetOf(trigger_) {
    let sel = trigger_.getAttribute('data-bs-target');

    if (!sel || sel === '#') {
        const href = trigger_.getAttribute('href');
        if (!href || (!href.startsWith('#') && !href.startsWith('.'))) return null;
        sel = href.trim();
    }

    try {
        return document.querySelector(sel);
    } catch {
        return null;
    }
}

/** Phần tử có đang hiển thị không — dùng để quyết định có trả focus về nút bấm. */
export function isVisible(el) {
    if (!el || el.getClientRects().length === 0) return false;

    return getComputedStyle(el).visibility === 'visible';
}

/** Đọc tuỳ chọn từ data-bs-* trên phần tử, ép kiểu như Bootstrap (true/false/số/JSON). */
export function dataConfig(el) {
    const out = {};

    for (const name of el.getAttributeNames()) {
        if (!name.startsWith('data-bs-')) continue;

        const key = name
            .slice(8)
            .replace(/-(.)/g, (_, c) => c.toUpperCase());

        if (['toggle', 'target', 'dismiss'].includes(key)) continue;

        out[key] = normalize(el.getAttribute(name));
    }

    return out;
}

function normalize(value) {
    if (value === 'true') return true;
    if (value === 'false') return false;
    if (value === Number(value).toString()) return Number(value);
    if (value === '' || value === 'null') return null;
    if (typeof value !== 'string') return value;

    try {
        return JSON.parse(decodeURIComponent(value));
    } catch {
        return value;
    }
}

/** Sổ đăng ký thể hiện theo phần tử — thay cho Data.set/get của Bootstrap. */
export function registry() {
    const map = new WeakMap();

    return {
        get: (el) => map.get(el) || null,
        set: (el, inst) => map.set(el, inst),
        remove: (el) => map.delete(el),
    };
}
