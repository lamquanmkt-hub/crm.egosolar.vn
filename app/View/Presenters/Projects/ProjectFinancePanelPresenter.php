<?php

declare(strict_types=1);

namespace App\View\Presenters\Projects;

/**
 * Chuẩn bị giá trị cho partial `projects-unified.partials.admin-finance` (bảng tài chính công trình,
 * chỉ Admin). Trước 2026-09-08 partial tự tính trong 2 khối `@php`: biên lợi nhuận, lãi/lỗ, hai bảng
 * nhãn; và nguồn của từng khoản thanh toán (phiếu thu cũ hay ghi nhận mới).
 */
final class ProjectFinancePanelPresenter
{
    public const PAYMENT_METHOD_LABELS = ['bank_transfer' => 'Chuyển khoản', 'cash' => 'Tiền mặt', 'card' => 'Thẻ', 'other' => 'Khác'];

    public const EXPENSE_CATEGORY_LABELS = ['supplier' => 'Nhà cung cấp', 'service' => 'Dịch vụ', 'travel' => 'Đi lại', 'rental' => 'Thuê ngoài', 'other' => 'Khác'];

    /**
     * @param  array<string, mixed>|null  $finance  singleFinanceSummary() của controller; null khi không thấy tài chính
     * @return array<string, mixed>
     */
    public function viewData(?array $finance): array
    {
        $financeData = $finance ?? [];

        return [
            'financeData' => $financeData,
            'grossMargin' => (float) ($financeData['profit_margin'] ?? 0),
            'isProfitable' => (float) ($financeData['profit'] ?? 0) >= 0,
            'paymentMethodLabels' => self::PAYMENT_METHOD_LABELS,
            'expenseCategoryLabels' => self::EXPENSE_CATEGORY_LABELS,
        ];
    }

    /** Khoản thanh toán đến từ phiếu thu cũ (`receipt`) hay ghi nhận mới (`record`) — quyết định route sửa/xoá. */
    public static function paymentSource(object $payment): string
    {
        return ($payment->finance_source ?? '') === 'legacy_receipt' ? 'receipt' : 'record';
    }
}
