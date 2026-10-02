<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phần trăm phải đi qua `DisplayFormat::percent()` — không `number_format(...)` rồi dán `%`.
 *
 * ## Vì sao cần guard này
 * Trước 2026-09-29 có **27 chỗ** tự định dạng phần trăm. 20 chỗ truyền `',', '.'` đúng, 7 chỗ
 * dùng dấu MẶC ĐỊNH của PHP nên ra `0.0%` kiểu Anh trong khi tiền cùng trang ra `1.000.000 đ`
 * kiểu Việt. Không test nào bắt được: cả 1.069 test vẫn xanh khi đổi hết 27 chỗ sang dấu Việt.
 * Nghĩa là quy ước hiển thị **không có chốt chặn nào** — đây là cái chốt đó.
 *
 * Bắt cả hai dạng: `{{ number_format(...) }}%` trong Blade và `number_format(...).'%'` trong PHP.
 */
final class PhanTramDungDauVietTest extends TestCase
{
    /** Nơi được phép chứa `number_format` cạnh `%`: chính hàm percent và test của nó. */
    private const MIEN_TRU = [
        'app/Support/DisplayFormat.php',
        'tests/Unit/Support/DisplayFormatTest.php',
        'tests/Feature/View/PhanTramDungDauVietTest.php',
    ];

    #[Test]
    public function khong_con_cho_nao_tu_dinh_dang_phan_tram(): void
    {
        $viPham = [];

        foreach ($this->tepCanRa() as $duongDan) {
            $noiDung = (string) file_get_contents($duongDan);
            $tuongDoi = str_replace(base_path().'/', '', $duongDan);

            if (in_array($tuongDoi, self::MIEN_TRU, true)) {
                continue;
            }

            // `number_format(...)` rồi tới `%` trong vòng 12 ký tự (đủ cho `}}%` và `).'%`).
            if (preg_match_all('/number_format\([^)]*\)[^%]{0,12}%/', $noiDung, $khop)) {
                foreach ($khop[0] as $doan) {
                    $viPham[] = $tuongDoi.' :: '.trim($doan);
                }
            }
        }

        $this->assertSame([], $viPham, implode("\n", array_merge(
            ['Phần trăm phải dùng DisplayFormat::percent($v, $soLe) — dấu Việt ở mọi trang:'],
            $viPham
        )));
    }

    /** @return list<string> */
    private function tepCanRa(): array
    {
        $ra = [];

        foreach ([resource_path('views'), app_path()] as $goc) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($goc, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $tep) {
                if ($tep->isFile() && str_ends_with($tep->getFilename(), '.php')) {
                    $ra[] = $tep->getPathname();
                }
            }
        }

        sort($ra);

        return $ra;
    }
}
