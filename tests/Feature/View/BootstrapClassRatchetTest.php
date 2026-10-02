<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bánh cóc cho việc chuyển Bootstrap -> Tailwind: chỉ được đi tới, không lùi.
 *
 * ## Vì sao cần
 * `resources/css/app.css` ghi rõ: Bootstrap CDN chỉ được gỡ khi KHÔNG còn view
 * nào phụ thuộc lớp của nó. Việc chuyển kéo dài nhiều đợt, nên phải có thứ chặn
 * hai hướng trôi: thêm lớp Bootstrap vào view mới, và làm bẩn lại view đã sạch.
 *
 * ## Cách kiểm chứng khi chuyển (đừng bỏ bước này)
 * Quy đổi theo GIÁ TRỊ chứ không theo tên — `mb-3` của Bootstrap là 16px còn
 * `tw:mb-3` chỉ 12px. Sau khi sửa phải đo lại computed style của MỌI phần tử ở
 * nhiều khổ màn và so với bản trước.
 *
 * Ba nguồn nhiễu đã gặp khi đo, phải khử trước khi tin kết quả:
 *   1. Đồng hồ sidebar được JS cập nhật LÚC CHẠY, ghi đè cả giá trị đã chuẩn hoá
 *      trong HTML -> phải ghim lại ngay trước khi chụp. Đây là nguồn nhiễu ~1px.
 *   2. Bề rộng phần tử co theo chữ lệch ~0,2px giữa các lần chạy do thời điểm
 *      font web sẵn sàng -> chờ `document.fonts.ready` và cho ngưỡng 0,5px.
 *   3. Dữ liệu ngẫu nhiên của factory làm số phần tử khác nhau -> chỉ so phần
 *      giao của hai bên.
 * Đo nhiễu nền TRƯỚC (chụp cùng một trang hai lần) rồi mới đo thật; lần đầu làm
 * việc này, 21 "khác biệt" hoá ra đều nằm dưới mức nhiễu 48 của chính bộ đo.
 */
final class BootstrapClassRatchetTest extends TestCase
{
    /**
     * Tổng số lượt dùng lớp Bootstrap THẬT, đo ngày 2026-09-06 sau đợt `btn`. CHỈ ĐƯỢC GIẢM.
     *
     * 🚨 1.636 -> 1.828 (2026-10-01): KHÔNG phải trôi ngược — mẫu THIẾU hẳn hai họ Bootstrap
     * và nay đếm thêm **192 lượt** vốn vô hình. `m[trblxy]?-[0-5]` không có `s`/`e`, nên các
     * utility LOGICAL của Bootstrap 5 chưa bao giờ được tính: `me-1` (153), `ps-3` (15),
     * `pe-4` (8), `ms-3` (6), `ps-4` (6), `pe-3` (4) — 47 view dính, nhiều nhất là
     * `marketing/dashboard` (22) và `orders/show-legacy` (13, view chết).
     * Kiểm là Bootstrap thật, không phải lớp riêng của trang, bằng hai phép độc lập:
     * grep `.me-1{`/`.ms-3{`… trong `resources/views`, `resources/css`, `public/css` -> KHÔNG
     * tệp nào tự khai; và chính `bootstrap@5.3.3/dist/css/bootstrap.min.css` khai
     * `.me-1{margin-right:.25rem!important}`, `.ms-3{margin-left:1rem!important}`,
     * `.pe-3{padding-right:1rem!important}`, `.ps-3{padding-left:1rem!important}`.
     * Bằng chứng nó là ĐIỂM MÙ chứ không phải việc nhỏ: `app_context.md` ghi `kythuat/luong_settings`
     * còn "4 token Bootstrap (`me-1` ×4) cố ý chưa chuyển", mà bộ đếm vẫn xếp trang đó là SẠCH.
     * VIEW_SACH 191 -> 187: bốn view mất danh hiệu sạch oan từ trước — `kythuat/luong`,
     * `kythuat/luong_settings`, `finance/audit`, `orders/index`.
     * Đây là lần thứ TƯ cùng một loại lỗi "mẫu không đếm đúng thứ nó nói là đang đếm" (ba lần
     * trước đều là đếm THỪA: `btn-*`, `table-*`, và sáu họ khác); lần này là đếm THIẾU.
     * Bước này KHÔNG sửa view nào.
     *
     * 2.951 -> 2.626: 160 lượt do siết mẫu (lớp riêng `btn-ego`, `btn-pill`… không còn bị
     * đếm là Bootstrap) và 165 lượt do 135 nút `btn` ở 29 view sang <x-ui.button>.
     * 2.626 -> 2.590: 30 nút đóng `btn-close`(-white) sang <x-ui.close-button>.
     * 2.590 -> 1.807: quét utility `text-*` (783 lượt / 117 view) theo giá trị, đo 42 trang
     * × 6 khổ màn; 114 chỗ giữ `!` vì CSS của trang tranh chấp (xem TestCase).
     * 1.643 -> 1.637 (2026-09-29, finance/debt-customers nửa CSS): chỉ 6 lượt vì trang dùng hệ
     * BEM `finance-*` của riêng nó chứ không phải Bootstrap — khối <style> 561 dòng / 82 rule đã
     * sang Tailwind hết. View 926 -> 332 dòng; 0 `@php`, 0 `<style>`, 0 `!`. VIEW_SACH 179 -> 180.
     * Đo 627 phần tử × 43 thuộc tính = 27.500 ô, nhiễu nền = 0 -> 33 ô đổi, cả 33 giải thích được
     * (28 box-shadow 5 lớp của Tailwind v4 với lớp hiệu lực y nguyên, 5 là cách IN RA gradient sau
     * minify: `0%` bị rút thành `0px` và stop đầu/cuối ngầm định bị bỏ — cùng một gradient).
     *
     * ⚠️ Ba lỗi HỎNG IM LẶNG bắt được nhờ đo, đều là XUNG ĐỘT CÙNG THUỘC TÍNH giữa hai lớp tiện
     * ích (độ đặc hiệu bằng nhau -> thứ tự trong tệp CSS quyết định, mà thứ tự đó Tailwind sinh
     * chứ không phải thứ tự viết trong `class=`): viền huy hiệu và viền pill bị lớp NỀN nuốt màu
     * của biến thể; và `th` bảng lồng mất chữ hoa/đậm vì bản cũ dùng bộ chọn HẬU DUỆ
     * `.finance-table thead th` với tới được bảng trong, còn <x-ui.table> chỉ dùng con trực tiếp.
     * DebtCustomersLookBrowserTest chốt cả ba (đã thử hoàn nguyên từng cái -> test đỏ đúng chỗ).
     *
     * 1.674 -> 1.643 (2026-09-28, orders/my-orders): 31 lượt của trang đó, làm CẢ HAI NỬA trong
     * một lượt vì trang nhỏ (255 dòng). Trang nay 0 `@php`, 0 lượt Bootstrap, 0 `data-bs-*`.
     * Đo 618 phần tử × 44 thuộc tính = 27.192 ô: **7 ô đổi**, cả 7 là box-shadow 5 lớp của
     * Tailwind v4. VIEW_SACH 178 -> 179.
     *
     * 1.705 -> 1.674 (2026-09-28, sites/index nửa CSS): 31 lượt của trang đó (lưới, `fs-5`,
     * `bg-white`, họ `table`, `badge`, `py-5`…). Trang nay còn **0** lượt Bootstrap, **0** `data-bs-*`
     * và **0** khối `<style>`. VIEW_SACH 174 -> 178 (trang này + 3 tệp view component mới).
     * Đo 1.079 phần tử × 55 thuộc tính = 57.134 ô: 15 ô đổi, đều giải thích được.
     *
     * 1.740 -> 1.705 (2026-09-28, rà mẫu): 35 lượt ở SÁU họ nữa vốn không phải Bootstrap —
     * `card-head`, `card-body-custom`, `card-glass`, `form-label-pro`, `form-body`, `fw-black`,
     * `text-panel`, `text-limit`, `col-stt`, `nav-icon`. Không có view nào bị sửa ở bước này;
     * chỉ là con số nay đếm đúng thứ nó nói là đang đếm. VIEW_SACH 173 -> 174.
     *
     * 1.773 -> 1.740 (2026-09-28, phần bảng): 17 lượt ở `marketing/budget` chuyển sang
     * <x-ui.table-wrap|table|table-head> (trang này nay còn **0** lượt Bootstrap), cộng 16 lượt
     * do SIẾT MẪU họ `table` (xem ghi chú ở hằng MAU). VIEW_SACH 172 -> 173.
     * Đo 946 phần tử × 43 thuộc tính = 40.678 ô: **0 ô đổi**.
     *
     * 1.781 -> 1.773 (2026-09-28, phần Alpine): 8 lượt `nav`/`nav-tabs`/`nav-item`/`nav-link` biến mất
     * khi dải tab của `marketing/budget` chuyển sang `<x-ui.tabs>`. `collapse`, `modal-*`, `tab-pane`,
     * `tab-content`, `fade` cũng bỏ được nhưng KHÔNG nằm trong mẫu đếm nên không đổi con số.
     * VIEW_SACH 170 -> 172 vì thêm hai tệp view của component (`components/ui/tabs`, `modal`) —
     * chúng không có lớp Bootstrap nào nên được kể là view sạch.
     *
     * 1.807 -> 1.781 (2026-09-28): 24 lượt ở `marketing/budget` (thẻ/đầu thẻ/cỡ chữ/bố cục),
     * đo 949 phần tử × 43 thuộc tính = 42.705 ô, 11 ô đổi và cả 11 đều là box-shadow bị
     * Tailwind v4 tách thành 5 lớp mà 4 lớp thêm vào trong suốt + kích thước 0 (nhìn y hệt).
     *
     * ⚠️ Mốc 1.807 đã CŨ SẴN trước đợt này: đo lại trên đúng bản HEAD ra 1.805, và VIEW_SACH
     * thật là 170 chứ không phải 168. Bánh cóc chỉ so `<=`/`>=` nên nó xanh trong khi trôi dần.
     * Hai hằng dưới đây nay đặt bằng số đo thật (xem khối "Đo lại" trong app_context.md).
     *
     * "Thật" ở đây loại trừ view ĐỘC LẬP — view tự dựng `<html>` và không
     * `@extends` layout, nên không nạp Bootstrap. Bảy view như vậy (PDF của
     * DomPDF, bản xuất Excel, trang welcome) tự định nghĩa `.badge`, `.row`,
     * `.text-right`… trong `<style>` của chính nó; tên chỉ TRÙNG Bootstrap.
     * Đếm cả chúng thì con số phồng lên 75 lượt và chuyển sang `tw:*` còn làm
     * hỏng PDF, vì bản dựng PDF không có CSS của Tailwind.
     *
     * `layouts/app` KHÔNG được loại trừ: chính nó nạp Bootstrap CDN.
     */
    private const TONG_LUOT = 2773;

    /**
     * Số view hoàn toàn không dùng lớp Bootstrap. CHỈ ĐƯỢC TĂNG.
     *
     * Đo lại 2026-09-30 (đợt advance_requests): số thật là 182 trong khi hằng còn 180 — bánh cóc
     * so `>=` nên nó xanh trong khi trôi. Siết lại để hai component mới của đợt trước được chốt.
     * Siết tiếp lên 191 sau đợt settlement_requests (thêm component theo trang, và trang đó nay
     * không còn lớp Bootstrap nào).
     */
    private const VIEW_SACH = 188;

    /**
     * Lớp Bootstrap nhận diện được — chỉ utility và component hay dùng.
     *
     * Họ `btn` liệt kê TƯỜNG MINH thay vì `btn-[a-z0-9-]+`: mẫu rộng đếm cả lớp riêng
     * của trang tình cờ mang tiền tố `btn-` (`btn-ego`, `btn-pill`, `btn-round`,
     * `btn-soft`… 160 lượt, 2026-09-06) — chúng không phải Bootstrap và không mất đi
     * khi gỡ Bootstrap. Đếm nhầm làm mốc phồng lên và tiến độ hiện sai.
     *
     * Họ `table` mắc ĐÚNG lỗi đó và được siết ngày 2026-09-28: `table-[a-z-]+` đang đếm cả
     * `table-wrap` (8), `table-pro` (3), `table-modern` (2), `table-card` (1) — 14 lượt lớp
     * riêng của trang, mỗi lớp đều có CSS của chính view khai ra, không liên quan Bootstrap.
     *
     * Rà nốt CÁC HỌ CÒN LẠI cùng ngày (liệt kê mọi token mà mẫu đang bắt, rồi soi từng cái):
     * thêm 35 lượt đếm nhầm ở sáu họ — `card-head` (12), `card-body-custom` (5), `card-glass` (1),
     * `form-label-pro` (5), `form-body` (2), `fw-black` (4), `text-panel` (2), `text-limit` (1),
     * `col-stt` (2), `nav-icon` (1). Chín trong mười lớp này có CSS do chính view khai (đã grep
     * `.<tên>` trong `resources/views`, `resources/css`, `public/css`); `nav-icon` thì không có
     * CSS nào cả — Bootstrap 5 cũng không có lớp đó.
     *
     * Ba họ `bg-*`, `d-*`, `alert-*` giữ mẫu rộng: rà xong KHÔNG có token lạ nào. Chúng vẫn là
     * bẫy tiềm tàng, nhưng siết trước khi bị cắn là YAGNI — cách rà nằm ngay ở đoạn trên.
     *
     * 2.794 -> 2.773 (2026-10-02, `marketing/metrics_edit`): 21 lượt — `small` ×18 (nhãn biểu mẫu),
     * `container-fluid`, `border-0`, `shadow-sm`. View 146 -> 131 dòng (bỏ khối `@php` 15 dòng).
     * Đo 902 phần tử × 33 thuộc tính × 4 khổ = 115.456 ô: **0 ô đổi ngay lượt đầu**. VIEW_SACH 187 -> 188.
     *
     * 2.816 -> 2.794 (2026-10-02, `product-categories/show`): 22 lượt — `shadow-sm` ×4, `bg-*` ×6,
     * `list-group*` ×6, `badge` ×2, `h3` ×2, `container-fluid`, `rounded-pill`. VIEW_SACH 186 -> 187.
     * Đo 898 phần tử × 36 thuộc tính × 4 khổ = 129.312 ô: 0 ô đổi sau hai vòng sửa.
     *
     * 2.845 -> 2.816 (2026-10-01, `payment_methods/index`): 29 trong 30 lượt. **Giữ CỐ Ý 1 lượt
     * `input-group`** — đó là MÓC của chính CSS repo (`app.css` khai
     * `.input-group > input:not(.form-control){position:relative;flex:1 1 auto;width:1%;min-width:0}`
     * cho ô `<x-ui.input>`), cùng nhóm "cấm đụng" với `.table-responsive`. Thay bằng utility đã đo:
     * ô nở 363 -> 405px, nút rớt xuống dòng, 116 ô lệch. VIEW_SACH không đổi (186).
     *
     * 2.899 -> 2.845 (2026-10-01, `finance/accounts/index`): 54 lượt — `rounded-pill` ×9,
     * `border-0` ×7, `rounded-4` ×7, `shadow-sm` ×6, `small` ×5, `fs-4` ×4, `badge` ×2, và 11 lớp
     * còn lại 1 lượt (`container-fluid`, `shadow-lg`, `overflow-hidden`, `opacity-75`, `d-grid`,
     * `d-inline-block`, `py-5`, `bg-light`, `bg-*-subtle`, họ `table`). VIEW_SACH 184 -> 186.
     * Đo 909 phần tử × 37 thuộc tính × 4 khổ = 130.896 ô: **0 ô đổi** sau hai vòng sửa.
     *
     * 2.932 -> 2.899 (2026-10-01, `hr/employees/create`): 33 lượt, cùng hình dạng trang sửa
     * (`is-invalid` ×11, `invalid-feedback` ×11, `me-1` ×3, `border` ×2, `container`, `flex-wrap`,
     * `border-0`, `shadow-sm`, `rounded-4`, `bg-light`). VIEW_SACH 183 -> 184.
     *
     * 2.963 -> 2.932 (2026-10-01, `hr/employees/edit`): 31 lượt — `is-invalid` ×10 +
     * `invalid-feedback` ×10 (hai lớp này đi cùng lỗi validation), `me-1` ×3, `border` ×2,
     * `container`, `flex-wrap`, `border-0`, `shadow-sm`, `rounded-4`, `bg-light`.
     * VIEW_SACH 181 -> 183 (trang + `<x-ui.field-error>`).
     *
     * 3.018 -> 2.963 (2026-10-01, `hr/overtime/index`): 55 lượt của trang đó
     * (`rounded-4` ×12 · `border-0` ×7 · `shadow-sm` ×7 · `small` ×8 · `fs-3` ×5 · `rounded-3` ×4 ·
     * họ `table` ×4 · `container-fluid`/`rounded-pill`/`me-1`/`py-5`/`border-top`/`badge`/
     * `align-middle`/`flex-wrap` mỗi lớp 1). VIEW_SACH 179 -> 181 (trang + component huy hiệu mới).
     *
     * ✅ SIẾT LƯỢT 2 cùng ngày — 2.252 -> 3.018 (+766 lượt), VIEW_SACH 184 -> 179. Rà lại bằng chính
     * lệnh hai chiều thì lộ thêm một nhóm trang trí nữa, đã đối chiếu từng tên với bootstrap@5.3.3:
     * `small` (398), `flex-wrap` (169), `is-invalid` (76), `invalid-feedback` (59), `opacity-75` (15),
     * `flex-column` (14), `font-monospace` (9), `h3`/`h4` (6), `flex-grow-1` (5), `flex-md-row` (4),
     * `object-fit-cover` (3), `flex-lg-row` (3), `flex-xl-row` (2), `flex-fill`, `mx-auto`, `img-fluid`.
     * Năm view nữa mất danh hiệu sạch, mỗi view vì ĐÚNG MỘT lớp: `auth/login` (`is-invalid`×2),
     * `hr/attendance/my` (`small`), `tasks/edit` (`small`), `sites/index` (`flex-column`),
     * `sales/work_reports/partials_modal_form_fields` (`flex-wrap`).
     * ⚠️ Nghĩa là tuyên bố cũ "`sites/index` 0 lượt Bootstrap, xong hoàn toàn" SAI đúng một lớp —
     * bộ đếm khi đó không thấy `flex-column`. Đã sửa lại ghi chép trong `app_context.md`.
     *
     * 🚫 **CỐ Ý KHÔNG ĐẾM, đã kiểm nên đừng "siết" thêm:**
     *   - `bi` / `bi-*` (2.777 lượt): đó là **Bootstrap ICONS** — gói font RIÊNG
     *     (`bootstrap-icons@1.11.3`), repo cố ý giữ. `bootstrap.min.css` có chuỗi `.bi` nhưng chỉ
     *     trong luật `.icon-link>.bi`, nên phép rà "tên có trong bootstrap.min.css" cho DƯƠNG TÍNH GIẢ.
     *   - Móc hành vi: `modal*`, `collapse`, `dropdown*`, `offcanvas*`, `toast*`, `tab-*`, `fade`,
     *     `show`, `active`, `nav*` — `bs-compat`/Alpine bám vào chính tên lớp.
     *
     * ✅ SIẾT LƯỢT 1 (2026-10-01, chủ dự án chốt) — 1.828 -> 2.252 (+424 lượt / 33 lớp), VIEW_SACH 187 -> 184.
     * Chỉ ba view mất danh hiệu sạch, và đều mất ĐÚNG: `layouts/app` (`border-0`, `position-fixed`,
     * `top-0`, `end-0` — chính nó kéo Bootstrap CDN về), `tasks/my` (`progress`, `progress-bar`),
     * `meeting-room-bookings/partials/booking-modal` (`spinner-border`, `spinner-border-sm`).
     * Bước này KHÔNG sửa view nào; đã thử thêm `class="border-0"` vào một view sạch -> cả hai
     * assertion đỏ. Lịch sử phát hiện ghi dưới đây.
     * rà chiều "đếm THIẾU" (lệnh ở `app_context.md` mục Đo lại) rồi đối chiếu từng token với
     * `bootstrap@5.3.3/dist/css/bootstrap.min.css` cho ra **52 lớp Bootstrap thật / 752 lượt** mà
     * mẫu không bắt. Chia hai nhóm:
     *   - **331 lượt / 20 lớp là MÓC HÀNH VI** (`modal*`, `collapse`, `dropdown*`, `toast*`, `nav*`,
     *     `tab*`, `fade`, `show`): CỐ Ý không đếm — `bs-compat`/Alpine bám vào chính tên lớp, bỏ tên
     *     là hỏng hành vi, nên chúng không đo được tiến độ "gỡ Bootstrap trang trí".
     *   - **421 lượt / 32 lớp là tiện ích THUẦN TRANG TRÍ → bỏ sót THẬT**: `border-0` (98),
     *     `align-middle` (69), `rounded-pill` (64), `rounded-4` (50), `input-group*` (50),
     *     `border-bottom|top` (18), `progress*` (16), `spinner-border*` (13), `position-relative` (9),
     *     `rounded-3` (7), `list-group*` (6), `border-<màu>` (5), `shadow-lg` (3), `sticky-top` (3),
     *     `justify-content-xl-end` (3), `visually-hidden` (2), `align-items-lg-center`, `top-0`,
     *     `end-0`, `position-fixed`, `sticky-lg-top` (1 mỗi lớp).
     * Nhóm sau đã được thêm vào mẫu, liệt kê TƯỜNG MINH từng tên (không dùng tiền tố rộng — đó là
     * nguyên nhân của ba đợt đếm THỪA trước). Số thật khi siết là **+424 lượt / 33 lớp**, nhiều hơn
     * 421 vì có thêm `overflow-hidden` (3 lượt, bản Bootstrap không tiền tố `tw:`).
     *
     * ⚠️ Khác biệt quan trọng so với ba đợt đếm THỪA: ở đây repo CÓ khai một số tên này trong
     * `<style>` của trang (`.ego-inputgroup .input-group-text`, `.progress-bar`, `.sticky-top`,
     * `.smx-loading .spinner-border`…) nhưng đều là **đè lên lớp của Bootstrap** (bộ chọn hậu duệ,
     * hoặc ghi lại một vài thuộc tính), KHÔNG phải tự định nghĩa một lớp độc lập như `btn-ego` /
     * `table-wrap`. Nghĩa là trang vẫn PHỤ THUỘC Bootstrap -> đếm là đúng. Đã kiểm từng lớp bằng
     * `grep -rn "\.<lop>\s*[,{:]" resources/views resources/css public/css`.
     */
    private const MAU = '/^(btn|btn-(?:primary|secondary|success|danger|warning|info|light|dark|link)'
        .'|btn-outline-(?:primary|secondary|success|danger|warning|info|light|dark)|btn-sm|btn-lg'
        .'|btn-close|btn-close-white|btn-group|btn-group-sm|btn-group-lg|btn-group-vertical|btn-toolbar|btn-check'
        .'|card|card-(?:body|header|footer|title|subtitle|text|link|group)'
        .'|card-img(?:-top|-bottom|-overlay)?|card-header-(?:tabs|pills)'
        .'|row|col|col-(?:auto|[1-9]|1[0-2])|col-(?:sm|md|lg|xl|xxl)-(?:auto|[1-9]|1[0-2])|d-[a-z-]+'
        .'|m[trblxy]?-[0-5]|p[trblxy]?-[0-5]|m[se]-[0-5]|p[se]-[0-5]|bg-[a-z-]+'
        .'|text-(?:start|center|end|justify)|text-(?:sm|md|lg|xl|xxl)-(?:start|center|end)'
        .'|text-(?:primary|secondary|success|danger|warning|info|light|dark|body|black|white|muted|reset)'
        .'|text-(?:body-emphasis|body-secondary|body-tertiary|black-50|white-50)'
        .'|text-(?:primary|secondary|success|danger|warning|info|light|dark)-emphasis'
        .'|text-(?:lowercase|uppercase|capitalize|wrap|nowrap|break|truncate)'
        .'|text-decoration-(?:none|underline|line-through)|text-opacity-(?:25|50|75|100)'
        .'|form-control|form-control-(?:sm|lg|plaintext|color)|form-label|form-text'
        .'|form-select|form-select-(?:sm|lg)|form-check|form-check-(?:input|label|inline)'
        .'|form-switch|form-range|form-floating'
        .'|table|table-sm|table-hover|table-bordered|table-borderless|table-striped|table-striped-columns'
        .'|table-active|table-group-divider|table-responsive(?:-(?:sm|md|lg|xl|xxl))?'
        .'|table-(?:primary|secondary|success|danger|warning|info|light|dark)'
        .'|badge|alert|alert-[a-z-]+|nav|nav-(?:link|item|tabs|pills|fill|justified|underline)'
        .'|justify-content-[a-z]+|align-items-[a-z]+'
        // Biến thể theo breakpoint của hai họ trên — mẫu cũ chỉ bắt bản không có breakpoint.
        .'|justify-content-(?:sm|md|lg|xl|xxl)-(?:start|end|center|between|around|evenly)'
        .'|align-items-(?:sm|md|lg|xl|xxl)-(?:start|end|center|baseline|stretch)'
        .'|align-self-(?:auto|start|end|center|baseline|stretch)'
        // Tiện ích THUẦN TRANG TRÍ bị bỏ sót tới 2026-10-01 (liệt kê TƯỜNG MINH, không dùng tiền tố rộng).
        .'|align-(?:baseline|top|middle|bottom|text-top|text-bottom)'
        .'|border-0|border-(?:top|bottom|start|end)|border-(?:top|bottom|start|end)-0'
        .'|border-(?:primary|secondary|success|danger|warning|info|light|dark|white)'
        .'|rounded-(?:0|1|2|3|4|5|circle|pill)|rounded-(?:top|bottom|start|end)'
        .'|position-(?:static|relative|absolute|fixed|sticky)'
        .'|top-(?:0|50|100)|bottom-(?:0|50|100)|start-(?:0|50|100)|end-(?:0|50|100)'
        .'|translate-middle(?:-x|-y)?'
        .'|input-group|input-group-(?:text|sm|lg)'
        .'|progress|progress-bar|progress-stacked|progress-bar-(?:striped|animated)'
        .'|spinner-border|spinner-border-sm|spinner-grow|spinner-grow-sm'
        .'|list-group|list-group-item|list-group-flush|list-group-numbered'
        .'|list-group-item-action|list-group-horizontal'
        .'|visually-hidden|visually-hidden-focusable'
        // Nhóm trang trí thứ hai (siết 2026-10-01 lượt 2) — xem ghi chú ở trên về `bi`.
        .'|small|lead|h[1-6]|display-[1-6]|font-monospace|blockquote'
        .'|flex-(?:row|row-reverse|column|column-reverse|wrap|wrap-reverse|nowrap|fill)'
        .'|flex-(?:sm|md|lg|xl|xxl)-(?:row|row-reverse|column|column-reverse|wrap|nowrap|fill)'
        .'|flex-(?:grow|shrink)-[01]|order-(?:first|last|[0-5])'
        .'|m[trblxy]?-auto|opacity-(?:0|25|50|75|100)'
        .'|object-fit-(?:contain|cover|fill|scale|none)|img-fluid|img-thumbnail|ratio'
        .'|is-invalid|is-valid|invalid-feedback|valid-feedback|invalid-tooltip|valid-tooltip'
        .'|input-group-prepend|input-group-append|col-form-label|form-text'
        .'|sticky-top|sticky-bottom|sticky-(?:sm|md|lg|xl|xxl)-top|sticky-(?:sm|md|lg|xl|xxl)-bottom'
        .'|shadow-lg|shadow-none|overflow-(?:auto|hidden|visible|scroll)'
        .'|w-100|h-100|border|rounded|shadow|shadow-sm|g-[0-5]|gap-[0-5]'
        .'|fw-(?:bold|bolder|semibold|medium|normal|light)|fs-[1-6]'
        .'|float-[a-z]+|container|container-fluid)$/';

    #[Test]
    public function so_luot_dung_lop_bootstrap_khong_tang(): void
    {
        [$tong, $sach] = $this->dem();

        $this->assertLessThanOrEqual(self::TONG_LUOT, $tong, sprintf(
            "Số lượt dùng lớp Bootstrap TĂNG: %d -> %d.\n".
            'View mới phải viết bằng utility `tw:*`. Nếu bạn vừa chuyển bớt được thì '.
            'hạ TONG_LUOT xuống %d.',
            self::TONG_LUOT, $tong, $tong
        ));

        // Giảm được thì phải cập nhật mốc, không thì bánh cóc hở dần.
        $this->assertGreaterThanOrEqual(self::TONG_LUOT - 200, $tong, sprintf(
            'Số lượt đã giảm mạnh (%d -> %d). Hạ TONG_LUOT xuống %d để chốt tiến độ.',
            self::TONG_LUOT, $tong, $tong
        ));

        unset($sach);
    }

    #[Test]
    public function so_view_sach_bootstrap_khong_giam(): void
    {
        [, $sach] = $this->dem();

        $this->assertGreaterThanOrEqual(self::VIEW_SACH, count($sach), sprintf(
            "Số view sạch Bootstrap GIẢM: %d -> %d.\n".
            'Có view đã chuyển xong bị thêm lại lớp Bootstrap.',
            self::VIEW_SACH, count($sach)
        ));
    }

    /**
     * Bốn view chuyển trong đợt 2026-09-05, đã đo 564.930 giá trị computed style
     * trên 4 khổ màn -> không thuộc tính nào đổi.
     */
    #[Test]
    public function bon_view_da_chuyen_van_sach(): void
    {
        $daChuyen = [
            'hr/gifts/receipts/index',
            'hr/gifts/requests/index',
            'hr/gifts/requests/create',
            'meeting-room-bookings/index',
        ];

        [, $sach] = $this->dem();

        foreach ($daChuyen as $view) {
            $this->assertContains($view, $sach, "$view lại có lớp Bootstrap.");
        }
    }

    /**
     * View tự dựng trang (có `<html>`, không `@extends`) thì không nạp Bootstrap.
     * `layouts/app` là ngoại lệ: chính nó kéo Bootstrap CDN về.
     */
    private function laViewDocLap(string $ma, string $duongDan): bool
    {
        if (str_starts_with($duongDan, 'layouts/')) {
            return false;
        }

        return str_contains(strtolower($ma), '<html') && ! str_contains($ma, '@extends');
    }

    /** @return array{0: int, 1: list<string>} */
    private function dem(): array
    {
        $tong = 0;
        $sach = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $tep) {
            if (! $tep->isFile() || ! str_ends_with($tep->getFilename(), '.blade.php')) {
                continue;
            }

            $ma = (string) preg_replace(
                '/\{\{--.*?--\}\}/s', '', (string) file_get_contents($tep->getPathname())
            );

            $duongDan = str_replace(
                [resource_path('views').'/', '.blade.php'], '', $tep->getPathname()
            );

            // View độc lập không nạp Bootstrap -> lớp cùng tên là của chính nó,
            // nên không tính lượt VÀ vẫn kể là sạch (nó có phụ thuộc gì đâu).
            if ($this->laViewDocLap($ma, $duongDan)) {
                $sach[] = $duongDan;

                continue;
            }

            $n = 0;
            // `(?<![-:\w])` để KHÔNG vơ luôn `x-bind:class="…"` / `:class="…"` của Alpine —
            // trong đó là biểu thức JS, không phải danh sách lớp.
            preg_match_all('/(?<![-:\\w])class="([^"]*)"/', $ma, $khop);

            foreach ($khop[1] as $danhSach) {
                foreach (preg_split('/\s+/', trim($danhSach)) ?: [] as $lop) {
                    if ($lop !== '' && preg_match(self::MAU, $lop) === 1) {
                        $n++;
                    }
                }
            }

            $tong += $n;

            if ($n === 0) {
                $sach[] = $duongDan;
            }
        }

        sort($sach);

        return [$tong, $sach];
    }
}
