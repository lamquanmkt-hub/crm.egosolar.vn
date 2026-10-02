<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\Services\Finance\FinanceFullAccess;
use App\Support\DisplayFormat;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị giá trị cho view `finance.supplier-debts.index`.
 *
 * Trước 2026-09-07 view tự tính trong 6 khối `@php`: quyền sửa hồ sơ đã hoàn tất (so email viết
 * cứng — nay qua {@see FinanceFullAccess}), 2 closure định dạng, bảng nhãn trạng thái đợt, cờ
 * theo công nợ (đã gắn ĐNTT / đồng bộ từ phiếu nhập), cờ theo đợt (nhãn, màu, khoá, đã chi, …),
 * % của đợt, đợt kế tiếp cho tách hàng loạt. Cờ được gắn thẳng vào object dòng (cùng cách
 * controller đã làm với paid_amount, files…), tên khoá khác giữ tên biến cũ của view.
 */
final class SupplierDebtPagePresenter
{
    private const ROUND_STATUS_TEXT = [
        'planned' => 'Dự kiến',
        'requested' => 'Đã lập ĐNTT',
        'submitted' => 'Đã gửi',
        'pending' => 'Đang chờ',
        'admin_approved' => 'Admin duyệt',
        'admin_rejected' => 'Admin từ chối',
        'accounting_approved' => 'Đã thanh toán',
        'accounting_rejected' => 'Kế toán từ chối',
        'paid' => 'Đã thanh toán',
        'cancelled' => 'Đã hủy',
        'draft' => 'Nháp',
    ];

    private const PAID_STATUSES = ['paid', 'accounting_approved'];

    private const WAITING_STATUSES = ['planned', 'draft', 'requested', 'submitted', 'pending', 'admin_approved'];

    public function __construct(private readonly FinanceFullAccess $fullAccess) {}

    /**
     * Gắn cờ hiển thị lên từng công nợ/đợt (đổi tại chỗ) và trả các giá trị dẫn xuất; bộ lọc thiếu
     * trên request tới đây là null, form so sánh `=== ''` nên ép như view cũ.
     *
     * @param  Collection<int, object>  $debts  dòng công nợ controller đã gom (payment_rounds, paid_amount…)
     * @return array<string, mixed> chỉ các giá trị dẫn xuất; controller gộp với dữ liệu gốc
     */
    public function viewData(Collection $debts, ?string $status, ?string $keyword, ?string $period, ?string $month, ?Authenticatable $user): array
    {
        $debts->each(fn (object $debt) => $this->decorateDebt($debt));

        return [
            'fmt' => $this,
            'financeCompletedEditor' => $this->fullAccess->allows($user),
            'financeFullAccessEmail' => $this->fullAccess->primaryEmail(),
            'status' => $status ?? '',
            'keyword' => $keyword ?? '',
            'period' => $period ?? 'all',
            'month' => $month ?? now()->format('Y-m'),
        ];
    }

    /** Tiền hiển thị: `1.234.567 đ`. */
    public function money(mixed $value): string
    {
        return DisplayFormat::money($value);
    }

    /** Tiền cho ô nhập: số thuần, bỏ số 0 thừa sau dấu chấm (`1234567.5`, `2000000`). */
    public function moneyInput(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) ($value ?? 0), 2, '.', ''), '0'), '.');
    }

    private function decorateDebt(object $debt): void
    {
        $rounds = collect($debt->payment_rounds ?? []);
        $total = (float) ($debt->total_amount ?? 0);

        $debt->has_linked_payment_request = $rounds->contains(fn (object $round) => ! empty($round->payment_request_id));
        $debt->synced_from_receipt = ($debt->source_type ?? null) === 'product_goods_receipt';
        $debt->next_bulk_round = ((int) ($rounds->max('payment_round') ?? 0)) + 1;
        $debt->existing_round_amount = (float) $rounds->sum('amount');

        $rounds->each(fn (object $round) => $this->decorateRound($round, $total));
    }

    private function decorateRound(object $round, float $debtTotal): void
    {
        $status = (string) ($round->status ?? 'planned');
        $isPaid = in_array($status, self::PAID_STATUSES, true);

        $round->status_key = $status;
        $round->status_text = self::ROUND_STATUS_TEXT[$status] ?? ($status ?: 'Dự kiến');
        $round->badge_class = $isPaid ? 'tw:bg-[#dcfce7] tw:text-[#047857]' : (in_array($status, self::WAITING_STATUSES, true) ? 'tw:bg-[#fef3c7] tw:text-[#b45309]' : 'tw:bg-[#ffe4e6] tw:text-[#be123c]');
        $round->is_locked = (bool) ($round->payment_request_is_locked ?? false);
        $round->is_paid = $isPaid;
        $round->has_payment_request = ! empty($round->payment_request_id) && empty($round->payment_request_missing);
        $round->is_partial_paid = (bool) ($round->is_partial_paid ?? false);
        $round->paid_amount_by_request = (float) ($round->paid_amount_by_request ?? 0);
        $round->remaining_amount_by_request = (float) ($round->remaining_amount_by_request ?? 0);
        $round->remaining_round_exists = (bool) ($round->remaining_round_exists ?? false);

        $percent = $debtTotal > 0 ? round(((float) ($round->amount ?? 0) * 100) / $debtTotal, 4) : null;
        $round->percent_text = $percent === null ? '' : rtrim(rtrim(number_format($percent, 4, '.', ''), '0'), '.');
    }
}
