import './bootstrap';

import Alpine from 'alpinejs';

import chiaDotCongNo from './finance/supplier-debt-split.js';
import { chuanHoaTien, docTien } from './finance/money.js';

/**
 * Alpine dùng cho các view chuyển mới. Hành vi Bootstrap cũ (modal, offcanvas,
 * collapse, tab, alert, dropdown, toast) nằm ở bản dựng riêng public/js/bootstrap-compat.js
 * — xem resources/js/bs-compat/standalone.js để biết vì sao phải tách.
 */
window.Alpine = Alpine;

// Component/magic theo trang: đăng ký TRƯỚC Alpine.start() thì x-data mới thấy.
Alpine.data('chiaDotCongNo', chiaDotCongNo);

/**
 * Bật/tắt hàng chi tiết trong bảng công nợ.
 *
 * Nút bấm và hàng chi tiết là hai `<tr>` ANH EM nên không có thẻ cha chung nào nhỏ hơn
 * `<tbody>` — đặt phạm vi ở đó, khoá theo id, KHÔNG phải thêm thẻ bọc nào.
 */
Alpine.data('hangChiTiet', () => ({
    mo: {},

    bat(id) {
        this.mo[id] = ! this.mo[id];
    },

    /** Mở (không phải bật/tắt) rồi cuộn tới và đưa tiêu điểm vào ô đầu của form sửa. */
    moVaSua(id) {
        this.mo[id] = true;

        this.$nextTick(() => {
            const hang = document.getElementById('sd-detail-' + id);

            if (! hang) {
                return;
            }

            hang.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Bản cũ chờ 200ms cho cuộn êm xong mới focus — giữ nguyên, focus sớm quá thì
            // trình duyệt tự cuộn giật một nhịp nữa.
            window.setTimeout(() => {
                const o = hang.querySelector(
                    '[data-edit-grid] input:not([type="hidden"]), [data-edit-grid] select, [data-edit-grid] textarea'
                );

                if (o) {
                    o.focus({ preventScroll: true });
                }
            }, 200);
        });
    },
}));

/** `$tien.doc('12,5')` / `$tien.chuan(...)` — dùng trong biểu thức x-on cho gọn. */
Alpine.magic('tien', () => ({ doc: docTien, chuan: chuanHoaTien }));

/**
 * Một đợt thanh toán lẻ: gõ % thì nhảy tiền và ngược lại.
 * Giữ nguyên hai phép làm tròn của bản JS thuần (xem supplier-debt-split.js).
 */
Alpine.data('dotThanhToan', (tong) => ({
    tong: Number(tong) || 0,
    phanTram: '',
    soTien: '',

    theoPhanTram() {
        if (this.phanTram === '') {
            return;
        }
        this.soTien = Math.round((this.tong * docTien(this.phanTram) / 100) * 100) / 100;
    },

    theoSoTien() {
        if (this.tong <= 0) {
            return;
        }
        const p = Math.round((docTien(this.soTien) * 100 / this.tong) * 10000) / 10000;
        this.phanTram = String(p).replace(/\.0+$/, '');
    },

    chuanHoaTruocKhiGui(form) {
        form.querySelectorAll('[data-money-input]').forEach((o) => {
            o.value = chuanHoaTien(o.value);
        });
    },
}));

Alpine.start();
