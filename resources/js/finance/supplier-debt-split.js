import { chuanHoaTien, docTien, inTien } from './money.js';

/**
 * Thẻ "chia đợt thanh toán theo %" ở `finance/supplier-debts`.
 *
 * Thay bản JS thuần tự dựng HTML bằng template literal — bản đó CHÉP nguyên chuỗi lớp Tailwind
 * vào trong JS (~600 ký tự), nên mỗi lần đổi giao diện phải sửa hai nơi. Nay markup do `x-for`
 * dựng từ Blade, chỉ còn một nguồn sự thật.
 *
 * ⚠️ Ba phép tính dưới đây là TIỀN, giữ nguyên từng phép làm tròn của bản cũ:
 *   - % -> tiền: Math.round((tong * phanTram / 100) * 100) / 100
 *   - chia đều n dòng: (n-1) dòng đầu Math.floor((100/n)*10000)/10000, dòng CUỐI ăn phần dư
 *   - tiền -> %: Math.round((soTien * 100 / tong) * 10000) / 10000
 * Khoá bằng tests/Browser/SupplierDebtSplitBehaviourTest.php.
 */
export default function chiaDotCongNo(cauHinh = {}) {
    return {
        mo: false,
        dong: [],
        tong: Number(cauHinh.tong) || 0,
        daCo: Number(cauHinh.daCo) || 0,
        dotKeTiep: Number(cauHinh.dotKeTiep) || 1,

        /** Bản cũ thêm MỘT dòng mỗi lần bấm nút mở — giữ nguyên, đừng "sửa" thành chỉ thêm khi rỗng. */
        moThe() {
            this.mo = true;
            this.themDong();
            this.$nextTick(() => this.$el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
        },

        themDong() {
            this.dong.push({
                dot: this.dotKeTiep + this.dong.length,
                phanTram: '',
                soTien: '',
                ngay: '',
                trangThai: 'planned',
                ghiChu: '',
            });
        },

        xoaDong(i) {
            this.dong.splice(i, 1);
        },

        /** Gõ % thì nhảy tiền; để trống % thì KHÔNG đụng vào tiền (giống bản cũ). */
        theoPhanTram(d) {
            if (d.phanTram === '') {
                return;
            }
            d.soTien = Math.round((this.tong * docTien(d.phanTram) / 100) * 100) / 100;
        },

        chiaDeu() {
            if (this.dong.length === 0) {
                this.themDong();
            }

            let dungPhanTram = 0;
            let dungTien = 0;
            const n = this.dong.length;

            this.dong.forEach((d, i) => {
                if (i === n - 1) {
                    d.phanTram = Math.round((100 - dungPhanTram) * 10000) / 10000;
                    d.soTien = Math.round((this.tong - dungTien) * 100) / 100;

                    return;
                }

                const phanTram = Math.floor((100 / n) * 10000) / 10000;
                const soTien = Math.round((this.tong * phanTram / 100) * 100) / 100;

                d.phanTram = phanTram;
                d.soTien = soTien;
                dungPhanTram += phanTram;
                dungTien += soTien;
            });
        },

        get tongPhanTram() {
            return this.dong.reduce((s, d) => s + docTien(d.phanTram), 0);
        },

        get tongTien() {
            return this.dong.reduce((s, d) => s + docTien(d.soTien), 0);
        },

        get sauKhiLuu() {
            return this.daCo + this.tongTien;
        },

        get vuotTran() {
            return this.sauKhiLuu > this.tong;
        },

        /** Bản cũ: `toFixed(2)` rồi bỏ đuôi `.00`. Giữ nguyên để chuỗi không đổi một ký tự. */
        get tongPhanTramChu() {
            return this.tongPhanTram.toFixed(2).replace(/\.00$/, '');
        },

        get tongTienChu() {
            return inTien(this.tongTien);
        },

        get daCoChu() {
            return inTien(this.daCo);
        },

        get conLaiChu() {
            return inTien(this.tong - this.sauKhiLuu);
        },

        /** Trước khi gửi, đưa mọi ô tiền về dạng số thuần để server đọc được. */
        chuanHoaTruocKhiGui(form) {
            form.querySelectorAll('[data-money-input]').forEach((o) => {
                o.value = chuanHoaTien(o.value);
            });
        },
    };
}
