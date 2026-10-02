<?php

declare(strict_types=1);

namespace App\DTOs\System;

/**
 * Bộ giá trị thương hiệu/chủ đề đã làm sạch, dùng cho partial `system-branding-runtime`.
 *
 * Partial đó nằm trong CẢ HAI layout (`app` và `guest`) nên chạy ở mọi request; trước đây nó tự
 * cache + truy vấn + làm sạch ngay trong một khối `@php` 62 dòng.
 */
final readonly class SystemBrandingData
{
    /**
     * @param  string  $faviconUrl  URL favicon; chuỗi RỖNG khi chưa đặt (view dựa vào đó để bỏ thẻ link)
     * @param  int  $cardRadius  bán kính thẻ, đã kẹp trong khoảng 8–30
     * @param  string  $runtimeJson  JSON bơm cho JS, giữ đúng bộ cờ json_encode của bản cũ
     */
    public function __construct(
        public string $faviconUrl,
        public string $primaryColor,
        public string $secondaryColor,
        public string $sidebarColor,
        public string $topbarColor,
        public string $pageBackground,
        public int $cardRadius,
        public string $runtimeJson,
    ) {}
}
