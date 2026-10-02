<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\DTOs\System\SystemBrandingData;
use App\Services\System\SystemBrandingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `partials/system-branding-runtime` sau khi dời khối `@php` 62 dòng sang
 * `SystemBrandingService` + view composer (2026-09-25).
 *
 * Partial này nằm trong CẢ HAI layout nên khối cũ chạy ở mọi request: tự `Cache::remember`, tự
 * truy vấn `ego_system_settings`, tự làm sạch màu, tự dựng JSON.
 *
 * ⚠️ Thẻ `<style>` trong partial này KHÔNG bị cấm: nó là CSS sinh từ dữ liệu DB (biến màu, bán
 * kính do admin đặt), không phải stylesheet tĩnh. Đây là ngoại lệ có chủ đích của luật
 * "không `<style>` trong view" ở `rules/blade-views.md`.
 */
final class SystemBrandingTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/partials/system-branding-runtime.blade.php';

    public function test_partial_khong_con_php_khong_tu_cache_va_khong_tu_truy_van(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $source);
        $this->assertStringNotContainsString('DB::', $source);
        $this->assertStringNotContainsString('Cache::', $source);
        $this->assertStringNotContainsString('SchemaCache', $source);

        // Toàn bộ biến phải là $branding do composer bơm vào.
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $this->assertSame(['branding'], array_values(array_unique($m[1])));

        // Và chỉ đọc thuộc tính thật của DTO.
        preg_match_all('/\$branding->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(
            fn (\ReflectionProperty $p) => $p->getName(),
            (new \ReflectionClass(SystemBrandingData::class))->getProperties()
        );
        $this->assertNotSame([], $m[1]);
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)));
    }

    public function test_khong_co_dong_cai_dat_thi_dung_mac_dinh(): void
    {
        $data = (new SystemBrandingService)->fromSettings([]);

        $this->assertSame('#12ABC6', $data->primaryColor);
        $this->assertSame('#0D988C', $data->secondaryColor);
        $this->assertSame('#06182A', $data->sidebarColor);
        $this->assertSame('#FFFFFF', $data->topbarColor);
        $this->assertSame('#F4F8FB', $data->pageBackground);
        $this->assertSame(16, $data->cardRadius);
        $this->assertSame('', $data->faviconUrl, 'chưa đặt favicon thì view phải bỏ thẻ link');
        $this->assertStringContainsString('"brandName":"EGO Solar CRM"', $data->runtimeJson);
        $this->assertStringContainsString('"density":"comfortable"', $data->runtimeJson);
    }

    public function test_mau_sai_dang_thi_ve_mac_dinh_con_mau_chu_thuong_thi_viet_hoa(): void
    {
        $data = (new SystemBrandingService)->fromSettings([
            'primary_color' => 'xanh',        // không phải hex
            'secondary_color' => '#12ABC',    // thiếu 1 ký tự
            'sidebar_color' => '',            // rỗng
            'topbar_color' => '#GGGGGG',      // ký tự ngoài [0-9A-F]
            'page_background' => '  #f4f8fb ', // hợp lệ nhưng chữ thường + khoảng trắng
        ]);

        $this->assertSame('#12ABC6', $data->primaryColor);
        $this->assertSame('#0D988C', $data->secondaryColor);
        $this->assertSame('#06182A', $data->sidebarColor);
        $this->assertSame('#FFFFFF', $data->topbarColor);
        // trim + strtoupper trước khi khớp, nên giá trị này ĐƯỢC nhận
        $this->assertSame('#F4F8FB', $data->pageBackground);
    }

    public function test_ban_kinh_the_bi_kep_trong_khoang_8_den_30(): void
    {
        $svc = new SystemBrandingService;

        $this->assertSame(30, $svc->fromSettings(['card_radius' => '999'])->cardRadius);
        $this->assertSame(8, $svc->fromSettings(['card_radius' => '0'])->cardRadius);
        $this->assertSame(8, $svc->fromSettings(['card_radius' => '-5'])->cardRadius);
        $this->assertSame(20, $svc->fromSettings(['card_radius' => '20'])->cardRadius);
        $this->assertSame(8, $svc->fromSettings(['card_radius' => 'to'])->cardRadius, 'chuỗi lạ -> (int) = 0 -> kẹp thành 8');
    }

    public function test_do_dac_ui_la_thi_ve_comfortable(): void
    {
        $svc = new SystemBrandingService;

        $this->assertStringContainsString('"density":"compact"', $svc->fromSettings(['ui_density' => 'compact'])->runtimeJson);
        $this->assertStringContainsString('"density":"comfortable"', $svc->fromSettings(['ui_density' => 'thoáng'])->runtimeJson);
    }

    public function test_json_giu_nguyen_bo_co_cua_ban_cu(): void
    {
        $data = (new SystemBrandingService)->fromSettings(['brand_name' => "Công ty <X> & 'Y'"]);

        // JSON_UNESCAPED_UNICODE: tiếng Việt không thành \uXXXX
        $this->assertStringContainsString('Công ty', $data->runtimeJson);
        // JSON_HEX_TAG / JSON_HEX_AMP / JSON_HEX_APOS: nhúng được vào <script> mà không cần thoát thêm
        $this->assertStringNotContainsString('<', $data->runtimeJson);
        $this->assertStringNotContainsString('&', $data->runtimeJson);
        $this->assertStringContainsString('\u003C', $data->runtimeJson);
        $this->assertStringContainsString('\u0026', $data->runtimeJson);
        $this->assertStringContainsString('\u0027', $data->runtimeJson);
        // JSON_UNESCAPED_SLASHES: URL không bị thành \/
        $this->assertStringNotContainsString('\\/', $data->runtimeJson);
    }

    public function test_doc_that_tu_db_va_dung_cache(): void
    {
        Cache::forget(SystemBrandingService::CACHE_KEY);
        DB::table('ego_system_settings')->delete();
        $now = '2026-01-01 00:00:00';
        DB::table('ego_system_settings')->insert([
            ['key' => 'primary_color', 'value' => '#ABCDEF', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'card_radius', 'value' => '22', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $first = (new SystemBrandingService)->theme();
        $this->assertSame('#ABCDEF', $first->primaryColor);
        $this->assertSame(22, $first->cardRadius);

        // Đổi DB nhưng chưa xoá cache -> vẫn ra giá trị cũ, chứng minh cache thật sự có tác dụng.
        DB::table('ego_system_settings')->where('key', 'primary_color')->update(['value' => '#000000']);
        $this->assertSame('#ABCDEF', (new SystemBrandingService)->theme()->primaryColor);

        Cache::forget(SystemBrandingService::CACHE_KEY);
        $this->assertSame('#000000', (new SystemBrandingService)->theme()->primaryColor);
    }
}
