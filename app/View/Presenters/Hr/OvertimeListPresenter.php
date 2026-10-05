<?php

declare(strict_types=1);

namespace App\View\Presenters\Hr;

use App\DTOs\Hr\OvertimeRow;
use App\DTOs\Hr\OvertimeSummaryCards;
use App\Support\DisplayFormat;

/**
 * Chuẩn bị trang `hr/overtime/index` (đăng ký tăng ca).
 *
 * Thay hai khối `@php`: khối đầu khai closure `$fmtHour` (6 chỗ gọi), khối thứ hai nằm TRONG
 * `@forelse` và gọi `auth()->user()` lại cho TỪNG dòng để tính quyền duyệt.
 *
 * Lớp này thuần: không Facade, không query, không `auth()`/`request()` — id người đang đăng nhập
 * đi vào bằng tham số.
 */
final class OvertimeListPresenter
{
    /**
     * @param  iterable<object>  $requests  dòng đơn tăng ca của trang hiện tại
     * @param  array<string, mixed>  $summary  khoá total/pending/approved/rejected/hours
     * @param  int  $currentUserId  id người đang đăng nhập (0 nếu không có)
     * @return array{summaryCards: OvertimeSummaryCards, overtimeRows: list<OvertimeRow>}
     */
    public function viewData(iterable $requests, array $summary, int $currentUserId, bool $canManage, array $approvableIds = []): array
    {
        $rows = [];

        foreach ($requests as $item) {
            // Bản cũ: `$canManage || (int)$item->approver_id === (int)auth()->user()->id`.
            $canApprove = (string) ($item->status ?? '') === 'pending'
                && ($canManage || (int) ($item->approver_id ?? 0) === $currentUserId || in_array((int) ($item->id ?? 0), $approvableIds, true));

            $rows[] = new OvertimeRow(
                id: (int) ($item->id ?? 0),
                // Bản cũ in `$item->user->name ?? '-'` — gạch NGẮN, không phải gạch dài của DisplayFormat.
                userName: (string) ($item->user->name ?? '-'),
                departmentName: (string) ($item->user->department->name ?? '-'),
                dateText: $item->overtime_date ? $item->overtime_date->format('d/m/Y') : '',
                timeText: ($item->start_at ? $item->start_at->format('H:i') : '')
                    .' - '.($item->end_at ? $item->end_at->format('H:i') : ''),
                hoursText: $this->gio($item->hours ?? 0),
                // Bản cũ: `$item->approver->name ?? 'HR / Admin'`.
                approverName: (string) ($item->approver->name ?? 'HR / Admin'),
                // `?:` chứ không `??`: lý do là chuỗi RỖNG cũng phải ra `-` (bản cũ dùng `?:`).
                reasonText: ((string) ($item->reason ?? '')) ?: '-',
                statusLabel: (string) ($item->status_label ?? ''),
                statusTone: (string) ($item->status_badge_class ?? 'secondary'),
                approvalNote: (string) ($item->approval_note ?? ''),
                canApprove: $canApprove,
            );
        }

        return [
            'summaryCards' => new OvertimeSummaryCards(
                totalText: DisplayFormat::number($summary['total'] ?? 0),
                pendingText: DisplayFormat::number($summary['pending'] ?? 0),
                approvedText: DisplayFormat::number($summary['approved'] ?? 0),
                rejectedText: DisplayFormat::number($summary['rejected'] ?? 0),
                hoursText: $this->gio($summary['hours'] ?? 0),
            ),
            'overtimeRows' => $rows,
        ];
    }

    /**
     * Số giờ: dấu phần thập phân TIẾNG VIỆT, cắt số 0 vô nghĩa (`3` · `2,5` · `1.234,5`).
     *
     * Bản cũ dùng `number_format($n, 2, '.', '')` rồi `rtrim` — tức dấu chấm thập phân kiểu Anh và
     * KHÔNG phân cách nghìn, lệch hẳn với tiền/số cùng trang. Đổi sang dấu Việt theo quyết định
     * 2026-09-29 (xem `DisplayFormat::percent`); phần "cắt số 0" giữ y bản cũ nên `3,00` vẫn ra `3`.
     */
    private function gio(mixed $value): string
    {
        $text = DisplayFormat::number($value, 2);

        if (! str_contains($text, ',')) {
            return $text;
        }

        return rtrim(rtrim($text, '0'), ',');
    }
}
