<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mọi thư viện nạp từ CDN phải ghim phiên bản.
 *
 * ## Vì sao
 * URL không ghim (`cdn.jsdelivr.net/npm/chart.js`) đưa production đi theo bản
 * mới nhất của bên thứ ba, không ai bấm nút gì cả. Đo ngày 2026-09-05: cùng thư
 * viện tom-select, URL JS không ghim trả về 2.6.2 trong khi URL CSS không ghim
 * trả về 2.6.1 — CSS và JS lệch nhau ngay trên một trang.
 *
 * Nâng phiên bản là việc nên làm, nhưng phải là một commit có người xem, đo lại
 * giao diện rồi mới đổi — không phải chuyện tự xảy ra lúc 3 giờ sáng.
 */
final class CdnVersionPinnedTest extends TestCase
{
    /**
     * Địa chỉ không mang khái niệm phiên bản (font, preconnect...).
     *
     * @var list<string>
     */
    private const KHONG_CAN_GHIM = [
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'fonts.bunny.net',
    ];

    #[Test]
    public function moi_url_cdn_trong_view_deu_ghim_phien_ban(): void
    {
        $viPham = [];

        foreach ($this->dsBlade() as $duongDan) {
            $noiDung = (string) file_get_contents($duongDan);

            preg_match_all(
                '/<(?:script|link)[^>]*(?:src|href)="(https?:\/\/[^"]+)"/',
                $noiDung,
                $khop
            );

            foreach ($khop[1] as $url) {
                if ($this->boQua($url)) {
                    continue;
                }

                if (! $this->daGhim($url)) {
                    $viPham[] = str_replace(base_path().'/', '', $duongDan).' -> '.$url;
                }
            }
        }

        $this->assertSame([], $viPham, sprintf(
            "%d URL CDN chưa ghim phiên bản:\n%s\n\n".
            'Thêm @<phiên bản> vào đường dẫn. Kiểm bản đang được phục vụ bằng: '.
            'curl -sSI <url> | grep -i x-jsd-version',
            count($viPham),
            implode("\n", $viPham)
        ));
    }

    private function boQua(string $url): bool
    {
        foreach (self::KHONG_CAN_GHIM as $host) {
            if (str_contains($url, $host)) {
                return true;
            }
        }

        return false;
    }

    /** jsdelivr ghim bằng `@x.y.z`; các CDN khác ghim bằng số hiệu trong đường dẫn. */
    private function daGhim(string $url): bool
    {
        if (str_contains($url, 'cdn.jsdelivr.net/npm/')) {
            return (bool) preg_match('/\/npm\/(@[^\/]+\/)?[^\/@]+@\d/', $url);
        }

        return (bool) preg_match('/\/\d+\.\d+/', $url);
    }
}
