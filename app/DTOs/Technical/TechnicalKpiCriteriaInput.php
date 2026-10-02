<?php

declare(strict_types=1);

namespace App\DTOs\Technical;

/**
 * Một tiêu chí KPI trong form nhập của trang lương kỹ thuật.
 *
 * Tồn tại vì view trước đây có một khối `@php` NGAY TRONG `@foreach` chỉ để suy ra ba cờ
 * (`isMaterial`, `isErrorBased`) và hai giá trị mặc định — chạy lại cho từng tiêu chí.
 */
final readonly class TechnicalKpiCriteriaInput
{
    /**
     * @param  mixed  $weight  trọng số THÔ (0.4) — view in nguyên vào `value=` và `data-weight=`
     * @param  string  $weightPercentText  trọng số dạng phần trăm để hiển thị: `40`
     * @param  bool  $isMaterial  kiểu `material_waste`: nhập % hao hụt, không có ô KH
     * @param  bool  $isErrorBased  kiểu `minus_quality`/`minus_safety`: nhập số lỗi
     * @param  mixed  $defaultPlan  giữ kiểu gốc: cột decimal ra chuỗi, mặc định là số nguyên
     */
    public function __construct(
        public string $definitionId,
        public string $name,
        public string $unit,
        public mixed $weight,
        public string $weightPercentText,
        public string $type,
        public string $subject,
        public string $rule,
        public string $source,
        public string $icon,
        public bool $isMaterial,
        public bool $isErrorBased,
        public mixed $defaultPlan,
        public mixed $defaultActual,
    ) {}
}
