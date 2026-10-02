/**
 * Đọc/ghi tiền theo cách người dùng gõ ở trang công nợ nhà cung cấp.
 *
 * Chép NGUYÊN hành vi của bản JS thuần trong view (trước 2026-09-29): người dùng gõ lẫn lộn
 * `1.234.567`, `1,234,567`, `1234567.5`, nên phải đoán đâu là dấu ngăn nghìn, đâu là dấu thập
 * phân. Đổi một nhánh ở đây là đổi SỐ TIỀN đề nghị thanh toán — xem
 * tests/Browser/SupplierDebtSplitBehaviourTest.php.
 */

/** Đưa chuỗi người dùng gõ về dạng số thuần mà `parseFloat` đọc được. */
export function chuanHoaTien(value) {
    value = String(value || '').trim();

    if (!value) {
        return value;
    }

    value = value.replace(/\s+/g, '').replace(/[^0-9,.-]/g, '');

    const lastComma = value.lastIndexOf(',');
    const lastDot = value.lastIndexOf('.');

    if (lastComma !== -1 && lastDot !== -1) {
        // Có cả hai dấu: dấu nào đứng SAU là dấu thập phân.
        const decimalSep = lastComma > lastDot ? ',' : '.';
        const thousandSep = decimalSep === ',' ? '.' : ',';
        value = value.split(thousandSep).join('').replace(decimalSep, '.');
    } else if (lastComma !== -1) {
        // Chỉ có phẩy: quá 2 chữ số sau nó (hoặc nhiều phẩy) thì nó là dấu ngăn nghìn.
        const after = value.length - lastComma - 1;
        value = (after > 2 || (value.match(/,/g) || []).length > 1)
            ? value.split(',').join('')
            : value.replace(',', '.');
    } else if (lastDot !== -1) {
        // Chỉ có chấm: đúng 3 chữ số sau nó (hoặc nhiều chấm) thì nó là dấu ngăn nghìn.
        const after = value.length - lastDot - 1;
        if (after === 3 || (value.match(/\./g) || []).length > 1) {
            value = value.split('.').join('');
        }
    }

    return value;
}

/** Chuỗi người dùng gõ -> số. Không đọc được thì 0, KHÔNG phải NaN. */
export function docTien(value) {
    const number = parseFloat(chuanHoaTien(value));

    return isNaN(number) ? 0 : number;
}

/** Số -> `1.234.567 đ`; chỉ hiện phần lẻ khi thực sự có (ngưỡng 0,001). */
export function inTien(value) {
    const number = Number(value || 0);
    const maximumFractionDigits = Math.abs(number - Math.round(number)) > 0.001 ? 2 : 0;

    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits }).format(number) + ' đ';
}
