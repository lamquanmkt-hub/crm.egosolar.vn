<?php

declare(strict_types=1);

namespace App\DTOs\Admin;

/** Một dòng (module) của ma trận quyền thao tác. */
final readonly class PermissionMatrixRow
{
    /**
     * @param  string  $searchText  chuỗi đã hạ chữ thường cho ô tìm kiếm phía client
     * @param  list<PermissionMatrixCell>  $cells  ĐÃ gióng hàng theo đúng thứ tự cột
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public string $searchText,
        public array $cells,
    ) {}
}
