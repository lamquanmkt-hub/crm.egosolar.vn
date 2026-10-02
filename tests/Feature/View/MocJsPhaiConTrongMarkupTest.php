<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mọi LỚP mà `<script>` của một view tự tìm bằng selector đều phải còn trong markup của view đó.
 *
 * ## Vì sao cần
 * Đợt chuyển CSS sang Tailwind xoá tên lớp đi. Lớp nào chỉ dùng để trang trí thì không sao,
 * nhưng lớp nào JS đang `querySelector`/`closest`/`matches` vào thì **tính năng chết câm**:
 * không lỗi, không test đỏ, computed style vẫn khớp 100%.
 *
 * Đã xảy ra THẬT hai lần:
 *  - `finance-toggle-btn` (debt-customers): nút mở chi tiết bấm không ra gì.
 *  - `sd-bulk-trigger` (supplier-debts): nút "+ Thêm dòng theo %" không mở được thẻ chia đợt —
 *    lọt lên production. `sd-edit-grid` thì mất focus sau khi mở form sửa.
 *
 * Cách chữa đúng: móc JS dùng `data-*`, đừng dùng tên lớp CSS.
 */
final class MocJsPhaiConTrongMarkupTest extends TestCase
{
    /**
     * Chỉ soi các view ĐÃ qua đợt chuyển sang Tailwind — đó là nơi tên lớp đang bị xoá đi.
     * View chưa chuyển còn nhiều lớp do JS tự dựng bằng template literal; soi chúng bây giờ
     * chỉ tạo báo động giả. Thêm view vào đây mỗi khi chuyển xong một trang.
     */
    private const VIEW_DA_CHUYEN = [
        'finance/index',
        'finance/debt-customers',
        'finance/supplier-debts/index',
        'finance/supplier-debts/_debt_files_manager',
        'projects/sites/index',
        'sites/index',
        'orders/my-orders',
        'marketing/budget',
    ];

    /** Lớp do thư viện bên thứ ba sinh ra lúc chạy, không bao giờ có trong markup của ta. */
    private const BEN_THU_BA = ['goog-te-combo', 'skiptranslate'];

    /** @var array<string, string>|null */
    private static ?array $moiView = null;

    #[Test]
    public function moi_lop_js_tim_deu_con_trong_markup(): void
    {
        $viPham = [];

        foreach ($this->viewCoScript() as $duongDan => $noiDung) {
            $viTri = strpos($noiDung, '<script');
            $markup = substr($noiDung, 0, (int) $viTri);
            $js = substr($noiDung, (int) $viTri);

            preg_match_all(
                '/(?:querySelector(?:All)?|closest|matches)\(\s*[\'"]([^\'"]+)[\'"]/',
                $js,
                $khop
            );

            $lop = [];
            foreach ($khop[1] as $selector) {
                preg_match_all('/\.([a-zA-Z][\w-]*)/', $selector, $c);
                foreach ($c[1] as $x) {
                    $lop[$x] = true;
                }
            }

            foreach (array_keys($lop) as $x) {
                // Bỏ qua lớp do CHÍNH JS đó tạo/gỡ lúc chạy (classList.add/toggle/remove)
                // và lớp của bên thứ ba (Google Translate) — chúng không có trong markup là đúng.
                if (preg_match('/classList\.(add|toggle|remove)\([\'"]'.preg_quote($x, '/').'[\'"]/', $js) === 1) {
                    continue;
                }
                if (in_array($x, self::BEN_THU_BA, true)) {
                    continue;
                }
                // Lớp có thể nằm ở partial được @include -> tìm trong TOÀN BỘ resources/views.
                if ($this->coTrongBatKyView($x)) {
                    continue;
                }
                $viPham[] = str_replace(base_path().'/', '', $duongDan).' :: .'.$x;
            }
        }

        $this->assertSame([], $viPham, implode("\n", array_merge(
            ['JS tìm lớp không còn trong markup -> tính năng chết câm. Dùng móc data-* thay vì tên lớp:'],
            $viPham
        )));
    }

    private function coTrongBatKyView(string $lop): bool
    {
        if (self::$moiView === null) {
            self::$moiView = [];
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $tep) {
                if ($tep->isFile() && str_ends_with($tep->getFilename(), '.blade.php')) {
                    $noiDung = (string) file_get_contents($tep->getPathname());
                    $viTri = strpos($noiDung, '<script');
                    self::$moiView[$tep->getPathname()] = $viTri === false ? $noiDung : substr($noiDung, 0, $viTri);
                }
            }
        }

        foreach (self::$moiView as $markup) {
            if (preg_match('/(?<![\\w-])'.preg_quote($lop, '/').'(?![\\w-])/', $markup) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private function viewCoScript(): array
    {
        $ra = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $tep) {
            if (! $tep->isFile() || ! str_ends_with($tep->getFilename(), '.blade.php')) {
                continue;
            }
            $ten = str_replace([resource_path('views').'/', '.blade.php'], '', $tep->getPathname());
            if (! in_array($ten, self::VIEW_DA_CHUYEN, true)) {
                continue;
            }

            $noiDung = (string) file_get_contents($tep->getPathname());
            if (str_contains($noiDung, '<script')) {
                $ra[$tep->getPathname()] = $noiDung;
            }
        }

        ksort($ra);

        return $ra;
    }
}
