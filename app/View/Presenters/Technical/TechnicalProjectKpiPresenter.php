<?php

declare(strict_types=1);

namespace App\View\Presenters\Technical;

use App\DTOs\Technical\ProjectKpiEngineerRow;
use App\DTOs\Technical\ProjectKpiHeader;
use App\Support\DisplayFormat;
use Illuminate\Support\Carbon;

/**
 * Chuẩn bị trang `kythuat/kpi_project` (KPI công trình).
 *
 * Thay hai khối `@php`: khối đầu ghép mã/tên dự phòng cho công trình, và khối nằm TRONG `@foreach`
 * tra bản ghi minh chứng + tín hiệu workflow cho từng kỹ sư. Gom luôn 2 lượt `Carbon::parse()`
 * viết thẳng trong Blade.
 *
 * Lớp này thuần: không Facade, không query, không `auth()`/`request()`.
 */
final class TechnicalProjectKpiPresenter
{
    /**
     * @param  iterable<object>  $users  kỹ sư liên quan công trình
     * @param  array<int, object|null>|\Illuminate\Support\Collection<int, object|null>  $evidence  id kỹ sư → bản ghi minh chứng
     * @param  array<int, mixed>  $signals  id kỹ sư → tín hiệu workflow trong kỳ
     * @return array{kpiHeader: ProjectKpiHeader, engineerRows: list<ProjectKpiEngineerRow>}
     */
    public function viewData(object $site, iterable $users, mixed $evidence, array $signals, int $engineerCount): array
    {
        $rows = [];

        foreach ($users as $user) {
            $id = (int) ($user->id ?? 0);
            $row = is_array($evidence) ? ($evidence[$id] ?? null) : $evidence->get($id);
            $signal = $signals[$id] ?? null;

            $rows[] = new ProjectKpiEngineerRow(
                userId: $id,
                name: (string) ($user->name ?? ''),
                email: (string) ($user->email ?? ''),
                hasSignal: $signal !== null,
                // Bản cũ in `$signal['deadline'] ?? '—'` — tín hiệu là mảng/đối tượng tuỳ nguồn.
                deadlineText: (string) (data_get($signal, 'deadline') ?? '—'),
                completedText: (string) (data_get($signal, 'completed') ?? '—'),
                // Bản cũ phân biệt `=== true` / `=== false` / còn lại ("Chưa đủ dữ liệu").
                onTime: match (data_get($signal, 'on_time')) {
                    true => 'ok',
                    false => 'late',
                    default => null,
                },
                timelineExcluded: (bool) ($row->timeline_excluded ?? false),
                exclusionReason: (string) ($row->timeline_exclusion_reason ?? ''),
                // Giữ NGUYÊN kiểu int|null: bản cũ so `@selected((… ) === 1)`, ép chuỗi là mất khớp.
                qualityFirstPass: isset($row->quality_first_pass) ? (int) $row->quality_first_pass : null,
                materialWastePercent: (string) ($row->material_waste_percent ?? ''),
                hsePass: isset($row->hse_pass) ? (int) $row->hse_pass : null,
                evnAppRequired: isset($row->evn_app_required) ? (int) $row->evn_app_required : null,
                evnAppCompleted: isset($row->evn_app_completed) ? (int) $row->evn_app_completed : null,
                penaltyPoints: (float) ($row->penalty_points ?? 0),
                note: (string) ($row->note ?? ''),
            );
        }

        return [
            'kpiHeader' => new ProjectKpiHeader(
                // Bản cũ: `$site->project_code ?: ('DA-SITE-'.str_pad($id, 5, '0', STR_PAD_LEFT))`.
                projectCode: ((string) ($site->project_code ?? ''))
                    ?: ('DA-SITE-'.str_pad((string) ($site->id ?? ''), 5, '0', STR_PAD_LEFT)),
                projectName: ((string) ($site->name ?? '')) ?: ('Công trình #'.((string) ($site->id ?? ''))),
                progressText: ((int) ($site->progress_percent ?? 0)).'%',
                targetCompletionText: $this->ngay($site->target_completion_at ?? null),
                completedText: $this->ngay($site->completed_at ?? null),
                engineerCountText: DisplayFormat::number($engineerCount),
            ),
            'engineerRows' => $rows,
        ];
    }

    /** `d/m/Y`, gạch DÀI `—` khi trống — trùng đúng `DisplayFormat::date()`. */
    private function ngay(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y') : '—';
    }
}
