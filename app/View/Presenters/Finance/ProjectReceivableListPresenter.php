<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\ProjectReceivableKpi;
use App\DTOs\Finance\ProjectReceivableRow;
use App\Support\DisplayFormat;
use Illuminate\Support\Carbon;

/**
 * Chuẩn bị trang `finance/project-receivables/index`.
 *
 * Thay hai khối `@php`: khối đầu khai closure `$money` (9 lượt gọi) + bản đồ trạng thái, và khối
 * nằm TRONG `@foreach` tra bản đồ đó cho từng dòng.
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 */
final class ProjectReceivableListPresenter
{
    /**
     * Trạng thái → [nhãn, chuỗi lớp huy hiệu]. Quy đổi từ `$statusMap` + `.pr-badge.*` của bản cũ.
     *
     * Chuỗi lớp phải TĨNH trong mã nguồn thì Tailwind mới quét thấy.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const STATUS_MAP = [
        'unpaid' => ['Chưa thu', 'tw:bg-[#ffe4e6] tw:text-[#be123c]'],
        'partial' => ['Đang thu', 'tw:bg-[#fff7d6] tw:text-[#a16207]'],
        'overdue' => ['Quá hạn', 'tw:bg-[#ffe4e6] tw:text-[#be123c]'],
        'paid' => ['Đã thu đủ', 'tw:bg-[#dcfce7] tw:text-[#15803d]'],
        'overpaid' => ['Thu vượt', 'tw:bg-[#fff7d6] tw:text-[#a16207]'],
    ];

    /** Nhãn + tông khi trạng thái không có trong bảng — đúng `?? ['Chưa xác định','warning']` cũ. */
    private const STATUS_DEFAULT = ['Chưa xác định', 'tw:bg-[#fff7d6] tw:text-[#a16207]'];

    /** Ngưỡng của bản cũ để coi là thu vượt đáng hiển thị. */
    private const NGUONG_THU_VUOT = 1000;

    /**
     * @param  iterable<object>  $projects  trang hiện tại của paginator, đã được controller gắn
     *                                      các thuộc tính `finance_*`
     * @param  array<string, mixed>  $summary
     * @return array{receivableRows: list<ProjectReceivableRow>, kpi: ProjectReceivableKpi}
     */
    public function viewData(iterable $projects, array $summary): array
    {
        $rows = [];

        foreach ($projects as $row) {
            [$nhan, $lop] = self::STATUS_MAP[$row->finance_status ?? ''] ?? self::STATUS_DEFAULT;
            $next = $row->finance_next_term ?? null;
            $overpaid = (float) ($row->finance_overpaid_amount ?? 0);
            $overdue = (float) ($row->finance_overdue_amount ?? 0);

            $rows[] = new ProjectReceivableRow(
                id: (int) ($row->id ?? 0),
                codeText: ((string) ($row->project_code ?? '')) ?: ('CT-'.((string) ($row->id ?? ''))),
                name: (string) ($row->name ?? ''),
                addressText: ((string) ($row->address ?? '')) ?: 'Chưa có địa chỉ',
                // Bản cũ: `@if(!empty($row->company_id))` rồi in `Công ty #<id>`.
                companyText: ! empty($row->company_id) ? 'Công ty #'.$row->company_id : '',
                progressText: 'Tiến độ: '.((int) ($row->progress_percent ?? 0)).'% · '
                    .(((string) ($row->project_phase ?? '')) ?: (((string) ($row->status ?? '')) ?: '—')),
                customerName: (string) ($row->finance_customer_name ?? ''),
                phoneText: ((string) ($row->contact_phone ?? '')) ?: 'Chưa có SĐT',
                // Closure `$money` cũ là `number_format($v, 0, ',', '.').' đ'` — trùng TỪNG KÝ TỰ
                // với `DisplayFormat::money()`.
                contractText: DisplayFormat::money($row->finance_contract_amount ?? 0),
                receivedText: DisplayFormat::money($row->finance_received_amount ?? 0),
                paymentsCountText: ((string) ($row->finance_payments_count ?? 0)).' lần thu',
                overpaidText: $overpaid > self::NGUONG_THU_VUOT
                    ? 'Thu vượt '.DisplayFormat::money($overpaid)
                    : null,
                receivableText: DisplayFormat::money($row->finance_receivable_amount ?? 0),
                overdueText: $overdue > 0 ? 'Quá hạn '.DisplayFormat::money($overdue) : null,
                hasNextTerm: $next !== null,
                nextTermName: (string) ($next->name ?? ''),
                nextTermAmountText: DisplayFormat::money($next->finance_remaining_amount ?? 0),
                // Bản cũ: `'Hạn '.Carbon::parse(...)->format('d/m/Y')` hoặc `'Chưa đặt hạn'`.
                nextTermDueText: ($next->due_date ?? null)
                    ? 'Hạn '.Carbon::parse($next->due_date)->format('d/m/Y')
                    : 'Chưa đặt hạn',
                statusText: $nhan,
                statusToneClass: $lop,
            );
        }

        return [
            'receivableRows' => $rows,
            'kpi' => new ProjectReceivableKpi(
                projectCount: DisplayFormat::number($summary['project_count'] ?? 0),
                contractAmount: DisplayFormat::money($summary['contract_amount'] ?? 0),
                receivedAmount: DisplayFormat::money($summary['received_amount'] ?? 0),
                receivableAmount: DisplayFormat::money($summary['receivable_amount'] ?? 0),
                overdueAmount: DisplayFormat::money($summary['overdue_amount'] ?? 0),
                overpaidAmount: DisplayFormat::money($summary['overpaid_amount'] ?? 0),
            ),
        ];
    }
}
