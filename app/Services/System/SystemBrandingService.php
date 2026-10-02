<?php

declare(strict_types=1);

namespace App\Services\System;

use App\DTOs\System\SystemBrandingData;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Nguồn duy nhất cho giá trị thương hiệu/chủ đề chạy động.
 *
 * Thay khối `@php` 62 dòng của `partials/system-branding-runtime` — partial đó nằm trong CẢ HAI
 * layout nên khối cũ chạy ở **mọi request**, tự `Cache::remember`, tự truy vấn
 * `ego_system_settings`, tự làm sạch màu và tự dựng JSON.
 *
 * Tách làm hai phần có chủ đích: `theme()` lo I/O (cache + truy vấn, có guard bảng và try/catch),
 * còn `fromSettings()` là hàm THUẦN — test gọi trực tiếp nó để phủ mọi nhánh làm sạch mà không
 * cần cache hay DB.
 */
final class SystemBrandingService
{
    /** Khoá cache giữ đúng bản cũ để không mất hiệu lực cache khi deploy. */
    public const CACHE_KEY = 'ego.system.branding.v2';

    private const TTL_SECONDS = 600;

    /** Giá trị mặc định, cũng là danh sách khoá được đọc từ `ego_system_settings`. */
    private const DEFAULTS = [
        'brand_name' => 'EGO Solar CRM',
        'brand_short_name' => 'EGO Solar',
        'logo_light' => 'images/ego-logo.png',
        'logo_sidebar' => 'logo/ego-solar-white.png',
        'favicon' => '',
        'primary_color' => '#12ABC6',
        'secondary_color' => '#0D988C',
        'sidebar_color' => '#06182A',
        'topbar_color' => '#FFFFFF',
        'page_background' => '#F4F8FB',
        'card_radius' => '16',
        'ui_density' => 'comfortable',
    ];

    /**
     * Giá trị chủ đề cho request hiện tại.
     *
     * Bọc try/catch như bản cũ: trang KHÔNG được trắng chỉ vì bảng cài đặt lỗi — cùng lắm là
     * hiển thị theo mặc định.
     */
    public function theme(): SystemBrandingData
    {
        try {
            $stored = Cache::remember(
                self::CACHE_KEY,
                self::TTL_SECONDS,
                static function (): array {
                    if (! SchemaCache::hasTable('ego_system_settings')) {
                        return [];
                    }

                    return DB::table('ego_system_settings')
                        ->whereIn('key', array_keys(self::DEFAULTS))
                        ->pluck('value', 'key')
                        ->map(fn ($value): string => (string) $value)
                        ->all();
                }
            );
        } catch (\Throwable) {
            $stored = [];
        }

        return $this->fromSettings(is_array($stored) ? $stored : []);
    }

    /**
     * Làm sạch và suy ra giá trị hiển thị — THUẦN, không I/O.
     *
     * @param  array<string, string>  $stored  các cặp key/value đọc từ DB (có thể thiếu khoá)
     */
    public function fromSettings(array $stored): SystemBrandingData
    {
        $theme = array_merge(self::DEFAULTS, $stored);

        $favicon = (string) $theme['favicon'];

        return new SystemBrandingData(
            // Rỗng thì để rỗng chứ không gọi asset(''): bản cũ bọc trong `@if(! empty(...))`.
            faviconUrl: $favicon !== '' ? asset($favicon) : '',
            primaryColor: $this->safeHex($theme['primary_color'], self::DEFAULTS['primary_color']),
            secondaryColor: $this->safeHex($theme['secondary_color'], self::DEFAULTS['secondary_color']),
            sidebarColor: $this->safeHex($theme['sidebar_color'], self::DEFAULTS['sidebar_color']),
            topbarColor: $this->safeHex($theme['topbar_color'], self::DEFAULTS['topbar_color']),
            pageBackground: $this->safeHex($theme['page_background'], self::DEFAULTS['page_background']),
            cardRadius: max(8, min(30, (int) $theme['card_radius'])),
            runtimeJson: $this->runtimeJson($theme),
        );
    }

    /** Hex 6 ký tự, viết hoa; không đúng dạng thì về mặc định. */
    private function safeHex(mixed $value, string $fallback): string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^#[0-9A-F]{6}$/', $value) === 1 ? $value : $fallback;
    }

    /**
     * JSON bơm cho JS.
     *
     * Bộ cờ giữ y bản cũ: `JSON_HEX_*` để chuỗi nhúng được vào `<script>` mà không cần thoát thêm,
     * `JSON_UNESCAPED_UNICODE` để tên thương hiệu tiếng Việt không thành `\uXXXX`.
     *
     * @param  array<string, string>  $theme
     */
    private function runtimeJson(array $theme): string
    {
        $density = in_array($theme['ui_density'], ['comfortable', 'compact'], true)
            ? $theme['ui_density']
            : 'comfortable';

        return (string) json_encode([
            'brandName' => $theme['brand_name'],
            'brandShortName' => $theme['brand_short_name'],
            'logoLight' => asset($theme['logo_light'] ?: self::DEFAULTS['logo_light']),
            'logoSidebar' => asset($theme['logo_sidebar'] ?: self::DEFAULTS['logo_sidebar']),
            'density' => $density,
            'sidebarColor' => $this->safeHex($theme['sidebar_color'], self::DEFAULTS['sidebar_color']),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
