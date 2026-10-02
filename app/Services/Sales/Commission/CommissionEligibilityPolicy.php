<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

/**
 * Đơn này có được tính hoa hồng không — theo ĐÚNG chính sách của kỳ.
 *
 * ## Vì sao lớp này ra đời
 * Trước đây có hai định nghĩa "đủ điều kiện" chạy song song trên cùng dữ liệu:
 *
 * - Bản xuất Excel đọc bốn công tắc trong `crm_commission_policies`
 *   (`only_paid`, `hold_if_debt`, `only_shipped`, `only_completed`).
 * - Màn hình báo cáo BỎ QUA cả bốn, tự dùng quy tắc cứng "thu đủ và chưa huỷ".
 *
 * Đo trên dữ liệu kiểm thử: để chính sách mặc định thì hai bên ra cùng số; bật
 * `only_completed` thì Excel về 0 còn màn hình giữ nguyên 2.821.296; tắt
 * `only_paid` và `hold_if_debt` thì Excel cao hơn màn hình 1.000.000. Tức là
 * công tắc trong màn hình cài đặt không có tác dụng lên chính con số người dùng
 * nhìn thấy.
 *
 * Nay chỉ còn một định nghĩa, và nó là bản đọc chính sách.
 */
final class CommissionEligibilityPolicy
{
    /** Chuỗi trạng thái coi như đơn đã hoàn tất. */
    private const COMPLETED_STATUSES = ['completed', 'hoan_tat', 'done', 'da_hoan_thanh'];

    /** Trạng thái chỉ cần CHỨA một trong các mẩu này cũng coi là hoàn tất. */
    private const COMPLETED_FRAGMENTS = ['completed', 'hoan'];

    /** Sai số cho phép khi so tiền thu với tổng đơn (đồng). */
    private const PAID_TOLERANCE = 0.01;

    /** Công nợ dưới mức này coi như đã tất toán, tránh chặn vì lẻ tiền. */
    private const DEBT_TOLERANCE = 1;

    public function __construct(
        private readonly OrderColumnMap $cols,
        private readonly object $policy,
    ) {}

    public function isEligible(object $order, float $afterVat, float $paid): bool
    {
        /*
         * Đơn không có tiền thì không có gì để tính hoa hồng. Bản Excel cũ coi
         * `tổng <= 0` là "đã thu đủ" nên gắn nhãn "Đủ điều kiện" cho cả đơn 0
         * đồng — số tiền vẫn là 0 nhưng nhãn đó làm người đọc hiểu sai. Đây là
         * chỗ DUY NHẤT hai bản cũ được hoà giải theo hướng chặt hơn.
         */
        if ($afterVat <= 0) {
            return false;
        }

        $debt = max(0, $afterVat - $paid);

        if ($this->flag('only_paid', 1) && ! $this->isPaid($afterVat, $paid)) {
            return false;
        }

        if ($this->flag('hold_if_debt', 1) && $debt > self::DEBT_TOLERANCE) {
            return false;
        }

        if ($this->flag('only_shipped', 0) && ! $this->isShipped($order)) {
            return false;
        }

        return ! $this->flag('only_completed', 0) || $this->isCompleted($order);
    }

    private function flag(string $name, int $default): bool
    {
        return (int) ($this->policy->{$name} ?? $default) === 1;
    }

    /** Đơn tổng bằng 0 coi như không còn gì phải thu. */
    private function isPaid(float $afterVat, float $paid): bool
    {
        return $afterVat <= 0 || $paid + self::PAID_TOLERANCE >= $afterVat;
    }

    /** Đơn không ghi nhận xuất kho thì coi như đã xuất — cột này chỉ có ở bản mới. */
    private function isShipped(object $order): bool
    {
        return isset($order->inventory_issued) ? ((int) $order->inventory_issued === 1) : true;
    }

    private function isCompleted(object $order): bool
    {
        $statusCol = $this->cols->completionStatusCol;
        $status = $statusCol ? strtolower((string) ($order->{$statusCol} ?? '')) : '';

        if (in_array($status, self::COMPLETED_STATUSES, true)) {
            return true;
        }

        foreach (self::COMPLETED_FRAGMENTS as $fragment) {
            if (str_contains($status, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
