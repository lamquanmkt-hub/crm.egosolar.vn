<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Nút dùng chung, thay dần cho `class="btn btn-*"` của Bootstrap.
 *
 * ## Vì sao là component chứ không phải rải utility ở từng chỗ
 * Nút có 4 trạng thái (hover / focus-visible / active / disabled). Rải utility tay
 * thì sai sót ở trạng thái phụ sẽ không ai phát hiện: phép so trang tĩnh KHÔNG bắt
 * được hover hay focus. Bảng dưới đây được SINH TỪ SỐ ĐO, không chép tay.
 *
 * ## Giá trị lấy từ đâu
 * Dựng một trang chứa đủ 12 biến thể trên đúng chuỗi CSS của production, rồi đo từng
 * biến thể ở từng trạng thái bằng SỰ KIỆN CHUỘT/BÀN PHÍM THẬT (CDP
 * `Input.dispatchMouseEvent` / `dispatchKeyEvent`) — không dùng `CSS.forcePseudoState`
 * vì nó không ảnh hưởng computed style.
 *
 * ## Hai điều bất ngờ khi đo, đừng "sửa" nếu chưa đọc kỹ
 * 1. `primary` KHÔNG phải màu xanh Bootstrap. Nó bị `partials/system-branding-runtime`
 *    ghi đè bằng `background: linear-gradient(135deg, var(--ego-brand-primary),
 *    var(--ego-brand-secondary)) !important` — tức MÀU THƯƠNG HIỆU ADMIN ĐỔI ĐƯỢC.
 *    Đóng cứng `#0d6efd` sẽ vừa sai màu vừa làm nút ngừng theo theme.
 * 2. Cũng vì cái `!important` đó, `primary` HIỆN KHÔNG ĐỔI GÌ khi hover/active. Đây là
 *    khiếm khuyết UX có sẵn; ở đây tái hiện ĐÚNG hiện trạng, không tự ý sửa. Muốn thêm
 *    phản hồi hover cho primary thì làm thành một thay đổi riêng, có xác nhận.
 *
 * ## Biến thể riêng của dự án KHÔNG nằm ở đây
 * `btn-ego`, `btn-ego-soft`, `btn-outline-ego`, `btn-pill`, `btn-round`… là style cục bộ
 * theo trang: `.btn-ego` được định nghĩa KHÁC NHAU ở hai file (`ego-order.css` dùng
 * gradient, `ego-payment-requests-enterprise.css` dùng bản khác), còn `btn-ego-soft` và
 * `btn-outline-ego` chỉ tồn tại trong `<style>` nội tuyến của vài view. Chúng được xử lý
 * khi chuyển chính trang đó, không gộp vào component dùng chung.
 */
final class Button extends Component
{
    /**
     * Lớp nền dùng chung: hình dạng, con chữ, chuyển động, trạng thái disabled.
     *
     * Đo được: viền 1px solid, chuyển động 0.15s ease-in-out trên 4 thuộc tính,
     * disabled = opacity .65 + pointer-events:none (giống nhau ở mọi biến thể).
     */
    private const BASE = 'tw:inline-block tw:cursor-pointer tw:select-none tw:no-underline '
        .'tw:text-center tw:align-middle tw:border tw:border-solid '
        // Chuyển động khai bằng thuộc tính tuỳ ý, KHÔNG dùng tw:ease-in-out.
        // Đo được: `tw:ease-in-out` của Tailwind là cubic-bezier(0.4, 0, 0.2, 1), KHÁC
        // từ khoá `ease-in-out` của CSS mà Bootstrap dùng — cubic-bezier(0.42, 0, 0.58, 1).
        // Viết nguyên shorthand để khớp cả đường cong lẫn cách ghi 4 giá trị.
        // ⚠️ PHẢI VIẾT LIỀN MỘT DÒNG. Tailwind quét văn bản thô của file; cắt một class
        // thành hai chuỗi nối nhau thì nó thấy hai mảnh không hợp lệ và KHÔNG sinh gì —
        // build vẫn xanh, chỉ là nút mất chuyển động. Đã vấp đúng lỗi này.
        .'tw:[transition:color_.15s_ease-in-out,background-color_.15s_ease-in-out,border-color_.15s_ease-in-out,box-shadow_.15s_ease-in-out] '
        .'tw:focus-visible:outline-none '
        .'tw:disabled:opacity-65 tw:disabled:pointer-events-none';

    /**
     * Kích cỡ — cỡ chữ khai KÈM line-height.
     *
     * `tw:text-sm` tự đặt line-height 20px, nhưng `.btn-sm` đo được 21px (1.5 × 14px),
     * nên phải dùng cú pháp `text-[cỡ]/[dòng]` chứ không dùng thang mặc định.
     * Bo góc bám biến của Bootstrap để hôm nay khớp tuyệt đối, có giá trị dự phòng cho
     * ngày gỡ Bootstrap.
     *
     * @var array<string, string>
     */
    private const SIZES = [
        '' => 'tw:px-3 tw:py-[6px] tw:text-[16px]/[24px] tw:font-normal tw:rounded-[var(--bs-border-radius,.375rem)]',
        'sm' => 'tw:px-2 tw:py-1 tw:text-[14px]/[21px] tw:font-normal tw:rounded-[var(--bs-border-radius-sm,.25rem)]',
        'lg' => 'tw:px-4 tw:py-2 tw:text-[20px]/[30px] tw:font-normal tw:rounded-[var(--bs-border-radius-lg,.5rem)]',
        // 'none': component KHÔNG khai kích thước, chỗ dùng tự lo. Dùng khi CSS riêng của
        // trang đang chồng lên kích thước nút (vd `.tm4-filter .btn{font-size:12px}`): nếu để
        // component vẫn khai cỡ rồi chỗ dùng khai đè, hai utility cùng thuộc tính sẽ tranh
        // nhau theo THỨ TỰ TRONG FILE CSS chứ không theo thứ tự viết trong class -> không
        // đoán trước được ai thắng. Bỏ hẳn cỡ ở component thì không còn tranh chấp.
        'none' => '',
    ];

    /**
     * Màu của từng biến thể — SINH TỪ SỐ ĐO trên production 2026-09-02, không chép tay.
     *
     * Biến thể đặc (solid) có `active` KHÁC `hover` (Bootstrap làm đậm thêm một nấc),
     * còn biến thể `outline-*` thì `active` trùng `hover` — đã đo, không suy diễn.
     *
     * @var array<string, string>
     */
    private const VARIANTS = [
        // Nút mà TRANG tự khai nền/màu/viền (ví dụ .attendance-filter-btn). Chuỗi rỗng
        // để component chỉ đóng góp phần BASE — con trỏ, transition, viền focus, trạng
        // thái disabled — và không tranh chấp gì với CSS của trang. Đối xứng với size 'none'.
        'none' => '',

        'primary' => 'tw:text-white tw:border-transparent tw:bg-transparent '
            // tw:bg-transparent là BẮT BUỘC: biến thể này tô bằng background-image, nếu
            // không đặt background-color thì <button disabled> ăn nền mặc định của trình
            // duyệt rgba(239,239,239,.3). Bootstrap không dính vì .btn luôn đặt
            // background-color: var(--bs-btn-bg).
            // nền là gradient thương hiệu, admin đổi được -> bám biến, KHÔNG đóng cứng màu
            .'tw:bg-[linear-gradient(135deg,var(--ego-brand-primary),var(--ego-brand-secondary))] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(49,132,253,0.5)]',
        'secondary' => 'tw:text-[#ffffff] tw:bg-[#6c757d] tw:border-[#6c757d] '
            .'tw:hover:text-[#ffffff] tw:hover:bg-[#5c636a] tw:hover:border-[#565e64] '
            .'tw:active:text-[#ffffff] tw:active:bg-[#565e64] tw:active:border-[#51585e] '
            .'tw:focus-visible:text-[#ffffff] tw:focus-visible:bg-[#5c636a] tw:focus-visible:border-[#565e64] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(130,138,145,0.5)]',
        'success' => 'tw:text-[#ffffff] tw:bg-[#198754] tw:border-[#198754] '
            .'tw:hover:text-[#ffffff] tw:hover:bg-[#157347] tw:hover:border-[#146c43] '
            .'tw:active:text-[#ffffff] tw:active:bg-[#146c43] tw:active:border-[#13653f] '
            .'tw:focus-visible:text-[#ffffff] tw:focus-visible:bg-[#157347] tw:focus-visible:border-[#146c43] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(60,153,110,0.5)]',
        'danger' => 'tw:text-[#ffffff] tw:bg-[#dc3545] tw:border-[#dc3545] '
            .'tw:hover:text-[#ffffff] tw:hover:bg-[#bb2d3b] tw:hover:border-[#b02a37] '
            .'tw:active:text-[#ffffff] tw:active:bg-[#b02a37] tw:active:border-[#a52834] '
            .'tw:focus-visible:text-[#ffffff] tw:focus-visible:bg-[#bb2d3b] tw:focus-visible:border-[#b02a37] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(225,83,97,0.5)]',
        'warning' => 'tw:text-[#000000] tw:bg-[#ffc107] tw:border-[#ffc107] '
            .'tw:hover:text-[#000000] tw:hover:bg-[#ffca2c] tw:hover:border-[#ffc720] '
            .'tw:active:text-[#000000] tw:active:bg-[#ffcd39] tw:active:border-[#ffc720] '
            .'tw:focus-visible:text-[#000000] tw:focus-visible:bg-[#ffca2c] tw:focus-visible:border-[#ffc720] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(217,164,6,0.5)]',
        'light' => 'tw:text-[#000000] tw:bg-[#f8f9fa] tw:border-[#f8f9fa] '
            .'tw:hover:text-[#000000] tw:hover:bg-[#d3d4d5] tw:hover:border-[#c6c7c8] '
            .'tw:active:text-[#000000] tw:active:bg-[#c6c7c8] tw:active:border-[#babbbc] '
            .'tw:focus-visible:text-[#000000] tw:focus-visible:bg-[#d3d4d5] tw:focus-visible:border-[#c6c7c8] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(211,212,213,0.5)]',
        'dark' => 'tw:text-[#ffffff] tw:bg-[#212529] tw:border-[#212529] '
            .'tw:hover:text-[#ffffff] tw:hover:bg-[#424649] tw:hover:border-[#373b3e] '
            .'tw:active:text-[#ffffff] tw:active:bg-[#4d5154] tw:active:border-[#373b3e] '
            .'tw:focus-visible:text-[#ffffff] tw:focus-visible:bg-[#424649] tw:focus-visible:border-[#373b3e] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(66,70,73,0.5)]',
        'outline-primary' => 'tw:text-[#0d6efd] tw:bg-[transparent] tw:border-[#0d6efd] '
            .'tw:hover:text-[#ffffff] tw:hover:bg-[#0d6efd] tw:hover:border-[#0d6efd] '
            .'tw:active:text-[#ffffff] tw:active:bg-[#0d6efd] tw:active:border-[#0d6efd] '
            .'tw:focus-visible:text-[#ffffff] tw:focus-visible:bg-[#0d6efd] tw:focus-visible:border-[#0d6efd] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(13,110,253,0.5)]',
        'outline-secondary' => 'tw:text-[#6c757d] tw:bg-[transparent] tw:border-[#6c757d] '
            .'tw:hover:text-[#ffffff] tw:hover:bg-[#6c757d] tw:hover:border-[#6c757d] '
            .'tw:active:text-[#ffffff] tw:active:bg-[#6c757d] tw:active:border-[#6c757d] '
            .'tw:focus-visible:text-[#ffffff] tw:focus-visible:bg-[#6c757d] tw:focus-visible:border-[#6c757d] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(108,117,125,0.5)]',
        'outline-danger' => 'tw:text-[#dc3545] tw:bg-[transparent] tw:border-[#dc3545] '
            .'tw:hover:text-[#ffffff] tw:hover:bg-[#dc3545] tw:hover:border-[#dc3545] '
            .'tw:active:text-[#ffffff] tw:active:bg-[#dc3545] tw:active:border-[#dc3545] '
            .'tw:focus-visible:text-[#ffffff] tw:focus-visible:bg-[#dc3545] tw:focus-visible:border-[#dc3545] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(220,53,69,0.5)]',
        'outline-warning' => 'tw:text-[#ffc107] tw:bg-[transparent] tw:border-[#ffc107] '
            .'tw:hover:text-[#000000] tw:hover:bg-[#ffc107] tw:hover:border-[#ffc107] '
            .'tw:active:text-[#000000] tw:active:bg-[#ffc107] tw:active:border-[#ffc107] '
            .'tw:focus-visible:text-[#000000] tw:focus-visible:bg-[#ffc107] tw:focus-visible:border-[#ffc107] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(255,193,7,0.5)]',
        'outline-light' => 'tw:text-[#f8f9fa] tw:bg-[transparent] tw:border-[#f8f9fa] '
            .'tw:hover:text-[#000000] tw:hover:bg-[#f8f9fa] tw:hover:border-[#f8f9fa] '
            .'tw:active:text-[#000000] tw:active:bg-[#f8f9fa] tw:active:border-[#f8f9fa] '
            .'tw:focus-visible:text-[#000000] tw:focus-visible:bg-[#f8f9fa] tw:focus-visible:border-[#f8f9fa] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(248,249,250,0.5)]',
        // Ba biến thể bổ sung 2026-09-03, cũng đo trên production như 12 cái đầu.
        // `outline-success` từng bị thiếu và làm một nút xanh lá hoá xám — phép so trang
        // bắt được, nhưng chỉ vì trang đó tình cờ có nó.
        'outline-success' => 'tw:text-[#198754] tw:bg-[transparent] tw:border-[#198754] '
            .'tw:hover:text-[#ffffff] tw:hover:bg-[#198754] tw:hover:border-[#198754] '
            .'tw:active:text-[#ffffff] tw:active:bg-[#198754] tw:active:border-[#198754] '
            .'tw:focus-visible:text-[#ffffff] tw:focus-visible:bg-[#198754] tw:focus-visible:border-[#198754] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(25,135,84,0.5)]',
        'info' => 'tw:text-[#000000] tw:bg-[#0dcaf0] tw:border-[#0dcaf0] '
            .'tw:hover:text-[#000000] tw:hover:bg-[#31d2f2] tw:hover:border-[#25cff2] '
            .'tw:active:text-[#000000] tw:active:bg-[#3dd5f3] tw:active:border-[#25cff2] '
            .'tw:focus-visible:text-[#000000] tw:focus-visible:bg-[#31d2f2] tw:focus-visible:border-[#25cff2] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(11,172,204,0.5)]',
        // `link`: không nền không viền; focus-visible GIỮ NGUYÊN màu chữ mặc định
        // (khác hover) — đã đo, đừng "sửa" cho giống các biến thể khác.
        // Bootstrap gạch chân `.btn-link` ở MỌI trạng thái; BASE lại đặt `tw:no-underline`
        // nên biến thể này phải ghi đè. Chỉ lộ ra khi có trang thật dùng tới nó.
        'link' => 'tw:underline tw:text-[#0d6efd] tw:bg-[transparent] tw:border-[transparent] '
            .'tw:hover:text-[#0a58ca] tw:active:text-[#0a58ca] '
            .'tw:focus-visible:text-[#0d6efd] '
            .'tw:focus-visible:shadow-[0_0_0_4px_rgba(49,132,253,0.5)]',
    ];

    /**
     * Thẻ được phép thay cho `<button>`/`<a>` qua prop `as`.
     *
     * Bootstrap cho phép gắn `.btn` lên `<label>` (nhãn của `<input type=file>` ẩn)
     * và `<summary>` (nút mở `<details>`). Hai thẻ đó không đổi thành `<button>` được
     * mà không đổi hành vi, nên component nhận `as="label"` / `as="summary"` và chỉ
     * đóng góp lớp. Danh sách đóng để không ai lỡ tay dựng `<div>` giả nút.
     */
    private const ALLOWED_TAGS = ['label', 'summary'];

    public function __construct(
        public string $variant = 'outline-secondary',
        public string $size = '',
        public ?string $href = null,
        public bool $borderBase = true,
        public string $as = '',
    ) {
        if ($as !== '' && ! in_array($as, self::ALLOWED_TAGS, true)) {
            throw new \InvalidArgumentException(
                "x-ui.button: as=\"$as\" không được phép; chỉ nhận ".implode('/', self::ALLOWED_TAGS).'.'
            );
        }
    }

    /** Chuỗi class hoàn chỉnh cho biến thể + cỡ đang chọn. */
    public function classes(): string
    {
        $variant = self::VARIANTS[$this->variant] ?? self::VARIANTS['outline-secondary'];

        if (! $this->borderBase) {
            $variant = $this->withoutBaseBorderColour($variant);
        }

        return self::BASE
            .' '.(self::SIZES[$this->size] ?? self::SIZES[''])
            .' '.$variant;
    }

    /**
     * Bỏ MÀU VIỀN Ở TRẠNG THÁI MẶC ĐỊNH, giữ nguyên màu viền của hover/active/focus.
     *
     * Dùng khi CSS riêng của trang đã quy định màu viền (vd `.cc-btn-quick{border-color:…}`)
     * và ta muốn nó thắng ở trạng thái tĩnh, nhưng vẫn giữ đổi màu khi tương tác — đúng
     * như Bootstrap vốn làm: `.btn:hover` (0,2,0) tự thắng `.cc-btn-quick` (0,1,0).
     *
     * Đây là cách thay cho việc rải `!important` ở chỗ dùng: bỏ hẳn thứ gây tranh chấp
     * thay vì dùng búa tạ để đè nó.
     */
    private function withoutBaseBorderColour(string $variant): string
    {
        $kept = array_filter(
            explode(' ', $variant),
            static fn (string $c): bool => ! str_starts_with($c, 'tw:border-['),
        );

        return implode(' ', $kept);
    }

    /** Biến thể có được khai báo hay không — dùng cho test canh. */
    public static function hasVariant(string $variant): bool
    {
        return array_key_exists($variant, self::VARIANTS);
    }

    /** Danh sách biến thể đang hỗ trợ. */
    public static function variants(): array
    {
        return array_keys(self::VARIANTS);
    }

    /** Danh sách cỡ đang hỗ trợ. */
    public static function sizes(): array
    {
        return array_keys(self::SIZES);
    }

    public function render(): View
    {
        return view('components.ui.button');
    }
}
