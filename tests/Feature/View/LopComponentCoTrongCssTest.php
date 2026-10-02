<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\View\Components\Ui\Alert;
use App\View\Components\Ui\Button;
use App\View\Components\Ui\Card;
use App\View\Components\Ui\CardBody;
use App\View\Components\Ui\CardFooter;
use App\View\Components\Ui\CardHeader;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Mọi lớp Tailwind mà component phát ra PHẢI có thật trong CSS đã build.
 *
 * Tailwind quét mã nguồn theo VĂN BẢN. Viết `"tw:bg-[$nen]"` thì nó chỉ thấy
 * `tw:bg-[` và không sinh ra lớp nào — component render ra class không tồn tại,
 * và hộp cảnh báo mất sạch màu mà không có lỗi nào báo. Đúng lỗi này đã nằm sẵn
 * trong `Ui\Alert` cho tới 2026-09-06, chỉ vì component chưa được nối vào view
 * nào nên không ai thấy.
 *
 * Test này bắt trước khi lên production. Nó đọc CSS ĐÃ BUILD trong repo, nên
 * quên chạy `npm run build` sau khi thêm biến thể là đỏ ngay.
 */
final class LopComponentCoTrongCssTest extends TestCase
{
    public function test_moi_bien_the_alert_co_lop_that(): void
    {
        $this->assertLopCoTrongCss(
            array_map(
                static fn (string $v): string => (new Alert($v))->classes(),
                ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'],
            ),
            'Ui\Alert',
        );
    }

    public function test_alert_dong_duoc_co_lop_that(): void
    {
        $this->assertLopCoTrongCss([(new Alert('info', true))->classes()], 'Ui\Alert (đóng được)');
    }

    public function test_moi_bien_the_button_co_lop_that(): void
    {
        $lop = [];

        foreach (['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'] as $v) {
            foreach (['', 'sm', 'lg'] as $co) {
                $lop[] = (new Button(variant: $v, size: $co))->classes();
            }
        }

        $this->assertLopCoTrongCss($lop, 'Ui\Button');
    }

    /**
     * Nút đóng phải tìm được hộp mà KHÔNG cần lớp `.alert` của Bootstrap.
     *
     * `wireAlert()` trước đây dò `btn.closest('.alert')`. Component không phát ra
     * lớp đó nữa, nên nếu mất móc `data-ego-alert` thì nút đóng chết lặng — không
     * lỗi, không dấu vết. Đã dựng trang đối chứng và bấm thật để xác nhận cả hai
     * markup đều đóng được; test này giữ giao kèo giữa hai bên.
     */
    public function test_giao_keo_moc_dong_alert(): void
    {
        $blade = file_get_contents(resource_path('views/components/ui/alert.blade.php'));
        $js = file_get_contents(resource_path('js/bs-compat/alert.js'));

        $this->assertStringContainsString('data-ego-alert', $blade);
        $this->assertStringContainsString("closest('[data-ego-alert], .alert')", $js);
        $this->assertStringContainsString('data-bs-dismiss="alert"', $blade);

        $this->assertStringContainsString('data-ego-alert', file_get_contents(public_path('js/bootstrap-compat.js')),
            'Bản build của bs-compat chưa có móc mới — chạy `npm run build`.');
    }

    public function test_ho_card_co_lop_that(): void
    {
        $this->assertLopCoTrongCss([
            (new Card)->classes(), (new CardBody)->classes(),
            (new CardHeader)->classes(), (new CardFooter)->classes(),
        ], 'Ui\Card*');
    }

    /**
     * `.card` là MÓC của giao diện chạy động: bán kính theo chủ đề admin đặt và
     * mật độ compact đều bắt theo tên lớp. Component không phát `.card` nữa nên
     * hai luật đó PHẢI bắt được `[data-ego-card]` / `[data-ego-card-body]`,
     * và component phải phát đúng hai móc ấy. Mất một vế là cấu hình chủ đề chết
     * lặng — không lỗi, chỉ là bo góc không đổi theo cài đặt nữa.
     */
    public function test_giao_keo_moc_chu_de_cua_card(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $runtime = file_get_contents(resource_path('views/partials/system-branding-runtime.blade.php'));

        $this->assertMatchesRegularExpression('/\.card,\s*\[data-ego-card\]\s*\{/', $layout,
            'layouts/app: luật nền/viền/bo của .card phải bắt cả [data-ego-card].');
        $this->assertStringContainsString('html body [data-ego-card]', $runtime,
            'branding-runtime: bán kính theo chủ đề phải áp cho [data-ego-card].');
        $this->assertStringContainsString('html[data-ego-density="compact"] [data-ego-card-body]', $runtime,
            'branding-runtime: mật độ compact phải áp cho [data-ego-card-body].');

        $this->assertStringContainsString('data-ego-card ', Blade::render('<x-ui.card>x</x-ui.card>'));
        $this->assertStringContainsString('data-ego-card-body ', Blade::render('<x-ui.card-body>x</x-ui.card-body>'));
    }

    /**
     * Component card CỐ Ý không phát nền/viền/bo góc — ba thứ đó do luật
     * `[data-ego-card]` trong layout và chủ đề chạy động quyết định. Phát ra là
     * Tailwind (nạp SAU <style> của layout) đè mất `background: var(--card)`.
     */
    public function test_card_khong_tu_phat_nen_vien_bo(): void
    {
        $lop = (new Card)->classes();

        $this->assertDoesNotMatchRegularExpression('/tw:(?:bg|border|rounded)/', $lop);
    }

    /**
     * Thứ tự trong tệp CSS mà cơ chế "phủ một phần" của LopTienIch dựa vào.
     *
     * Component giữ `tw:p-4` khi nơi gọi chỉ đặt `tw:py-2`, vì bỏ đi là mất đệm
     * trái/phải. Phần chồng nhau để thứ tự CSS xử: `.tw\:py-2` PHẢI đứng sau
     * `.tw\:p-4`. Đó là thứ tự sắp xếp chuẩn của Tailwind, nhưng nó là giả định
     * ngầm của cả cơ chế nên phải canh — đổi bản Tailwind mà thứ tự đảo là
     * đệm sai mà không test nào khác thấy.
     */
    public function test_thu_tu_css_dung_nhu_co_che_nhuong_lop_gia_dinh(): void
    {
        $css = $this->cssDaBuild();

        foreach ([['tw:p-4', 'tw:px-4'], ['tw:p-4', 'tw:py-2'], ['tw:px-4', 'tw:py-2'], ['tw:p-4', 'tw:pr-12']] as [$truoc, $sau]) {
            $viTriTruoc = strpos($css, '.'.$truoc.'{');
            $viTriSau = strpos($css, '.'.$sau.'{');

            $this->assertIsInt($viTriTruoc, "Không thấy .{$truoc} trong CSS đã build.");
            $this->assertIsInt($viTriSau, "Không thấy .{$sau} trong CSS đã build.");
            $this->assertLessThan($viTriSau, $viTriTruoc,
                ".{$sau} phải đứng SAU .{$truoc} thì lớp hẹp hơn của nơi gọi mới thắng.");
        }
    }

    /** Hộp đóng được phải có transition, vì alert.js chờ theo thời lượng ĐO ĐƯỢC. */
    public function test_alert_dong_duoc_co_transition(): void
    {
        $lop = (new Alert('info', true))->classes();

        $this->assertStringContainsString('tw:transition-opacity', $lop);
        $this->assertStringContainsString('tw:duration-150', $lop);
        $this->assertStringContainsString('transitionDuration(this._element) > 0',
            file_get_contents(resource_path('js/bs-compat/alert.js')));
    }

    /**
     * @param  list<string>  $chuoiLop
     */
    private function assertLopCoTrongCss(array $chuoiLop, string $noi): void
    {
        $css = $this->cssDaBuild();
        $thieu = [];

        foreach ($chuoiLop as $chuoi) {
            foreach (preg_split('/\s+/', trim($chuoi)) ?: [] as $lop) {
                // Bỏ qua lớp rỗng và lớp không phải Tailwind.
                if ($lop === '' || ! str_starts_with($lop, 'tw:')) {
                    continue;
                }

                if (! str_contains($css, '.'.$lop)) {
                    $thieu[] = $lop;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($thieu)), "{$noi}: lớp trên KHÔNG có "
            .'trong CSS đã build. Nguyên nhân thường gặp: chuỗi lớp ghép từ biến nên Tailwind '
            .'không quét thấy — viết thành chuỗi tĩnh. Hoặc chỉ là quên chạy `npm run build`.');
    }

    /** CSS đã build, bỏ hết dấu thoát để so bằng chính tên lớp. */
    private function cssDaBuild(): string
    {
        $tep = glob(public_path('build/assets/app-*.css'));

        $this->assertNotEmpty($tep, 'Không thấy public/build/assets/app-*.css — chạy `npm run build`.');

        return str_replace('\\', '', file_get_contents($tep[0]));
    }
}
