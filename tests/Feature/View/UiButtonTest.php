<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\View\Components\Ui\Button;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Canh giữ <x-ui.button>.
 *
 * ## Vì sao cần test này
 * Nút có 4 trạng thái (hover / focus-visible / active / disabled). Nếu ai sửa chuỗi
 * class và làm rơi mất một biến thể trạng thái, TRANG VẪN TRÔNG BÌNH THƯỜNG lúc tĩnh —
 * chỉ hỏng khi người dùng rê chuột hoặc tab tới. Không phép so ảnh nào bắt được.
 *
 * Giá trị dưới đây đo trên production 2026-09-02: đọc biến `--bs-btn-*` áp lên
 * `.btn.btn-outline-secondary` qua CDP rồi đối chiếu với `.btn:hover`,
 * `.btn:focus-visible`, `.btn:first-child:active`, `.btn:disabled` trong chính
 * bootstrap.min.css đang chạy. Đã kiểm bằng sự kiện chuột/bàn phím thật: cả 4 trạng
 * thái khớp Bootstrap từng thuộc tính.
 */
final class UiButtonTest extends TestCase
{
    /** Có href thì ra thẻ <a>. */
    public function test_co_href_thi_ra_the_a(): void
    {
        $html = Blade::render('<x-ui.button href="/quay-lai">Quay lại</x-ui.button>');

        $this->assertStringContainsString('<a href="/quay-lai"', $html);
        $this->assertStringContainsString('Quay lại', $html);
    }

    /** Không có href thì ra <button>, mặc định type=button để không submit form ngoài ý muốn. */
    public function test_khong_co_href_thi_ra_the_button(): void
    {
        $html = Blade::render('<x-ui.button>Lưu</x-ui.button>');

        $this->assertStringContainsString('<button type="button"', $html);
        $this->assertStringNotContainsString('type="button" type=', $html);
    }

    /** type truyền vào phải được tôn trọng, và không bị nhân đôi. */
    public function test_type_truyen_vao_duoc_ton_trong(): void
    {
        $html = Blade::render('<x-ui.button type="submit">Lưu</x-ui.button>');

        $this->assertStringContainsString('<button type="submit"', $html);
        $this->assertSame(1, substr_count($html, 'type='));
    }

    /**
     * Đủ cả 4 trạng thái — đây là phần dễ rơi mất nhất khi sửa tay.
     */
    public function test_giu_du_bon_trang_thai(): void
    {
        $html = Blade::render('<x-ui.button href="#">x</x-ui.button>');

        foreach ([
            'tw:hover:bg-[#6c757d]',
            'tw:hover:text-[#ffffff]',
            'tw:hover:border-[#6c757d]',
            'tw:focus-visible:bg-[#6c757d]',
            'tw:focus-visible:shadow-[0_0_0_4px_rgba(108,117,125,0.5)]',
            'tw:focus-visible:outline-none',
            'tw:active:bg-[#6c757d]',
            'tw:active:text-[#ffffff]',
            'tw:disabled:opacity-65',
            'tw:disabled:pointer-events-none',
        ] as $class) {
            $this->assertStringContainsString($class, $html, "Rơi mất class trạng thái: {$class}");
        }
    }

    /**
     * Kích thước/typography khớp .btn: padding 6px/12px, chữ 16px, line-height 24px.
     *
     * Cỡ chữ khai kèm line-height (`text-[16px]/[24px]`) chứ không dùng `tw:text-base`
     * + `tw:leading-6`: thang mặc định của Tailwind đặt line-height riêng cho từng cỡ và
     * lệch với 1.5 của Bootstrap ở cỡ sm (20px so với 21px).
     */
    public function test_kich_thuoc_khop_bootstrap(): void
    {
        $html = Blade::render('<x-ui.button href="#">x</x-ui.button>');

        foreach (['tw:px-3', 'tw:py-[6px]', 'tw:text-[16px]/[24px]', 'tw:font-normal'] as $class) {
            $this->assertStringContainsString($class, $html);
        }
    }

    /** Bo góc bám biến của Bootstrap, có giá trị dự phòng cho ngày gỡ Bootstrap. */
    public function test_bo_goc_bam_bien_bootstrap(): void
    {
        $html = Blade::render('<x-ui.button href="#">x</x-ui.button>');

        $this->assertStringContainsString('tw:rounded-[var(--bs-border-radius,.375rem)]', $html);
    }

    /** Biến thể lạ thì lùi về mặc định thay vì render nút không có màu. */
    public function test_bien_the_la_lui_ve_mac_dinh(): void
    {
        $this->assertFalse(Button::hasVariant('khong-ton-tai'));

        $html = Blade::render('<x-ui.button href="#" variant="khong-ton-tai">x</x-ui.button>');

        $this->assertStringContainsString('tw:text-[#6c757d]', $html);
    }

    /** Class truyền thêm ở chỗ dùng phải được gộp, không đè mất class của component. */
    public function test_class_truyen_them_duoc_gop(): void
    {
        $html = Blade::render('<x-ui.button href="#" class="tw:ml-2">x</x-ui.button>');

        $this->assertStringContainsString('tw:ml-2', $html);
        $this->assertStringContainsString('tw:inline-block', $html);
    }

    /**
     * Mọi biến thể đều phải có mặt và ra class khác nhau.
     *
     * 12 biến thể đo ngày 2026-09-02, thêm outline-success/info/link ngày 2026-09-03
     * khi phát hiện view đang dùng mà component chưa có.
     */
    public function test_du_bien_the(): void
    {
        // 15 biến thể Bootstrap + 'none'. 'none' không đóng góp màu nào, dành cho nút mà
        // TRANG tự khai nền/màu/viền (ví dụ .attendance-filter-btn) — đối xứng với size 'none'.
        $this->assertCount(16, Button::variants());
        $this->assertContains('none', Button::variants());

        $seen = [];
        foreach (Button::variants() as $v) {
            $html = Blade::render('<x-ui.button href="#" :variant="$v">x</x-ui.button>', ['v' => $v]);
            $this->assertStringNotContainsString('btn btn-', $html, "Biến thể {$v} còn sót class Bootstrap");
            $seen[$v] = $html;
        }

        $this->assertSame(count($seen), count(array_unique($seen)), 'Có hai biến thể ra cùng một chuỗi class');
    }

    /**
     * Biến thể đặc phải giữ đủ hover + active RIÊNG BIỆT.
     *
     * Bootstrap làm active đậm hơn hover một nấc; nếu ai gộp hai trạng thái làm một thì
     * nút mất phản hồi lúc nhấn, mà trang tĩnh trông vẫn y hệt.
     */
    public function test_bien_the_dac_co_hover_va_active_rieng(): void
    {
        foreach (['secondary' => ['#5c636a', '#565e64'], 'success' => ['#157347', '#146c43'],
            'danger' => ['#bb2d3b', '#b02a37'], 'dark' => ['#424649', '#4d5154']] as $v => [$hover, $active]) {
            $html = Blade::render('<x-ui.button href="#" :variant="$v">x</x-ui.button>', ['v' => $v]);

            $this->assertStringContainsString("tw:hover:bg-[{$hover}]", $html, "{$v}: sai màu hover");
            $this->assertStringContainsString("tw:active:bg-[{$active}]", $html, "{$v}: sai màu active");
        }
    }

    /**
     * `primary` phải bám biến thương hiệu, KHÔNG được đóng cứng màu.
     *
     * Nút primary bị partials/system-branding-runtime tô bằng gradient từ
     * --ego-brand-primary/--ego-brand-secondary, là màu admin đổi được trong trang cài
     * đặt. Đóng cứng #0d6efd vừa sai màu vừa làm nút ngừng theo theme — mà ảnh chụp ở
     * thiết lập mặc định sẽ KHÔNG phát hiện ra.
     */
    public function test_primary_bam_bien_thuong_hieu(): void
    {
        $html = Blade::render('<x-ui.button href="#" variant="primary">x</x-ui.button>');

        $this->assertStringContainsString('var(--ego-brand-primary)', $html);
        $this->assertStringContainsString('var(--ego-brand-secondary)', $html);
        $this->assertStringNotContainsString('#0d6efd', $html);
        // background-color phải đặt tường minh, nếu không <button disabled> ăn nền mặc
        // định của trình duyệt rgba(239,239,239,.3).
        $this->assertStringContainsString('tw:bg-transparent', $html);
    }

    /** Ba cỡ phải ra padding/cỡ chữ/bo góc khác nhau, đúng số đo của .btn / .btn-sm / .btn-lg. */
    public function test_ba_co_dung_so_do(): void
    {
        $expected = [
            '' => ['tw:px-3', 'tw:py-[6px]', 'tw:text-[16px]/[24px]', 'tw:rounded-[var(--bs-border-radius,.375rem)]'],
            'sm' => ['tw:px-2', 'tw:py-1', 'tw:text-[14px]/[21px]', 'tw:rounded-[var(--bs-border-radius-sm,.25rem)]'],
            'lg' => ['tw:px-4', 'tw:py-2', 'tw:text-[20px]/[30px]', 'tw:rounded-[var(--bs-border-radius-lg,.5rem)]'],
        ];

        foreach ($expected as $size => $classes) {
            $html = Blade::render('<x-ui.button href="#" :size="$s">x</x-ui.button>', ['s' => $size]);
            foreach ($classes as $class) {
                $this->assertStringContainsString($class, $html, "Cỡ '{$size}' thiếu {$class}");
            }
        }
    }

    /**
     * Class transition phải nguyên vẹn MỘT token.
     *
     * Tailwind quét văn bản thô của file PHP. Nếu ai xuống dòng giữa chuỗi class thì nó
     * thấy hai mảnh không hợp lệ và không sinh CSS — build vẫn xanh, nút chỉ lặng lẽ mất
     * chuyển động. Đã vấp đúng lỗi này khi viết component.
     */
    public function test_class_transition_nguyen_ven(): void
    {
        $html = Blade::render('<x-ui.button href="#">x</x-ui.button>');

        $this->assertStringContainsString(
            'tw:[transition:color_.15s_ease-in-out,background-color_.15s_ease-in-out,'
            .'border-color_.15s_ease-in-out,box-shadow_.15s_ease-in-out]',
            $html
        );
    }

    /**
     * Không dùng tw:ease-in-out của Tailwind.
     *
     * Đo được: `tw:ease-in-out` sinh ra cubic-bezier(0.4, 0, 0.2, 1), KHÁC từ khoá
     * `ease-in-out` của CSS mà Bootstrap dùng — cubic-bezier(0.42, 0, 0.58, 1).
     */
    public function test_khong_dung_ease_in_out_cua_tailwind(): void
    {
        $html = Blade::render('<x-ui.button href="#">x</x-ui.button>');

        $this->assertStringNotContainsString('tw:ease-in-out', $html);
    }

    /**
     * Biến thể `link` phải gạch chân — BASE đặt `tw:no-underline` nên nó phải ghi đè.
     *
     * Bootstrap gạch chân `.btn-link` ở MỌI trạng thái. Thiếu chỗ này thì nút trông
     * như chữ thường; lỗi nằm im cho tới khi có trang thật dùng biến thể link
     * (trang orders/my-orders, 2026-09-03).
     */
    public function test_bien_the_link_gach_chan(): void
    {
        $html = Blade::render('<x-ui.button variant="link">x</x-ui.button>');

        $this->assertStringContainsString('tw:underline', $html);
        $this->assertStringContainsString('tw:no-underline', $html, 'BASE vẫn phải có, link chỉ ghi đè');
    }

    /**
     * `as="label"` / `as="summary"`: Bootstrap gắn `.btn` lên hai thẻ này (nhãn của
     * input file ẩn, nút mở <details>); đổi thành <button> là đổi hành vi, nên
     * component chỉ đóng góp lớp và giữ nguyên thẻ.
     */
    public function test_as_label_va_summary_giu_nguyen_the(): void
    {
        $label = Blade::render('<x-ui.button as="label" for="f1" variant="outline-warning" size="none" class="wrk-mini-btn">Thay</x-ui.button>');
        $this->assertStringStartsWith('<label ', trim($label));
        $this->assertStringContainsString(' for="f1"', $label);
        $this->assertStringEndsWith('</label>', trim($label));
        $this->assertStringContainsString('wrk-mini-btn', $label);
        $this->assertStringContainsString('tw:hover:bg-[#ffc107]', $label, 'vẫn đủ trạng thái của biến thể');
        $this->assertStringNotContainsString('type=', $label);

        $summary = Blade::render('<x-ui.button as="summary" variant="light" size="none" aria-label="Tùy chọn">…</x-ui.button>');
        $this->assertStringStartsWith('<summary ', trim($summary));
        $this->assertStringContainsString(' aria-label="Tùy chọn"', $summary);
        $this->assertStringEndsWith('</summary>', trim($summary));
    }

    /** Thẻ ngoài danh sách bị từ chối ngay, không âm thầm dựng <div> giả nút. */
    public function test_as_the_khac_bi_tu_choi(): void
    {
        // Blade bọc lỗi của component vào ViewException; kiểm thông điệp gốc là đủ.
        $this->expectException(\Illuminate\View\ViewException::class);
        $this->expectExceptionMessage('as="div" không được phép');
        Blade::render('<x-ui.button as="div">x</x-ui.button>');
    }
}
