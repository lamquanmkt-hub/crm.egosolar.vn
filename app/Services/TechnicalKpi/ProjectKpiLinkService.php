<?php

namespace App\Services\TechnicalKpi;

use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectKpiLinkService
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_TIMELINE = 'project_timeline';

    public const SOURCE_QUALITY = 'project_quality';

    public const SOURCE_MATERIAL = 'project_material_waste';

    public const SOURCE_HSE = 'project_hse';

    public const SOURCE_EVN_APP = 'project_evn_app';

    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_MANUAL => 'Nhập tay',
            self::SOURCE_TIMELINE => 'Công trình · Tiến độ / deadline',
            self::SOURCE_QUALITY => 'Công trình · Nghiệm thu / chất lượng',
            self::SOURCE_MATERIAL => 'Công trình · Hao hụt vật tư',
            self::SOURCE_HSE => 'Công trình · HSE / vệ sinh',
            self::SOURCE_EVN_APP => 'Công trình · EVN / App',
        ];
    }

    /**
     * Bộ nhớ đệm trong MỘT request cho `evidenceRow()`: khoá "<site>:<user>:<tháng>".
     *
     * Vì sao cần: `evidenceRow()` là hàm thuần theo ba khoá đó, nhưng nó được gọi một lần cho
     * MỖI cặp (công trình, kỹ sư) trong lúc tóm tắt — đo được 1 truy vấn mỗi cặp. Nạp cả bảng
     * theo công trình + kỳ (1 truy vấn) rồi tra O(1) ở đây.
     *
     * @var array<string, object|null>
     */
    private array $evidenceMemo = [];

    /** Công trình+kỳ đã nạp hàng loạt rồi, để không nạp lại. @var array<string, true> */
    private array $evidencePreloaded = [];

    /** Kết quả nhóm theo kỹ sư của `preloadEvidenceForUsers()`. @var array<string, array<int, Collection<int, object>>> */
    private array $evidenceByUsers = [];

    private function table(string $table): bool
    {
        return SchemaCache::hasTable($table);
    }

    private function column(string $table, string $column): bool
    {
        return $this->table($table) && SchemaCache::hasColumn($table, $column);
    }

    private function monthBounds(string $month): array
    {
        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
        }

        return [$start, $start->copy()->endOfMonth()];
    }

    /**
     * Danh sách công trình liên quan đến kỹ sư trong kỳ KPI.
     * Ưu tiên assignment workflow; fallback lead_engineer_id để dữ liệu cũ vẫn được tính.
     */
    public function projectsForUserMonth(int $userId, string $month): Collection
    {
        if ($userId <= 0 || ! $this->table('sites')) {
            return collect();
        }

        [$start, $end] = $this->monthBounds($month);
        $projects = collect();

        if ($this->table('project_workflow_assignments') && $this->table('project_workflow_steps')) {
            $rows = DB::table('project_workflow_assignments as a')
                ->join('project_workflow_steps as w', 'w.id', '=', 'a.workflow_step_id')
                ->join('sites as s', 's.id', '=', 'w.site_id')
                ->where('a.user_id', $userId)
                ->when($this->column('project_workflow_assignments', 'is_active'), fn ($q) => $q->where('a.is_active', 1))
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('w.updated_at', [$start, $end]);
                    if ($this->column('project_workflow_steps', 'approved_at')) {
                        $q->orWhereBetween('w.approved_at', [$start, $end]);
                    }
                    if ($this->column('project_workflow_steps', 'submitted_at')) {
                        $q->orWhereBetween('w.submitted_at', [$start, $end]);
                    }
                    if ($this->column('project_workflow_assignments', 'submitted_at')) {
                        $q->orWhereBetween('a.submitted_at', [$start, $end]);
                    }
                    if ($this->column('sites', 'completed_at')) {
                        $q->orWhereBetween('s.completed_at', [$start->toDateString(), $end->toDateString()]);
                    }
                    if ($this->column('sites', 'handover_at')) {
                        $q->orWhereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]);
                    }
                })
                ->select([
                    's.id as site_id', 's.project_code', 's.name', 's.status as site_status',
                    's.project_phase', 's.progress_percent', 's.target_completion_at', 's.completed_at', 's.handover_at',
                    'w.id as workflow_step_id', 'w.step_code', 'w.status as step_status', 'w.due_at',
                    'w.recommitted_due_at', 'w.approved_at', 'w.submitted_at', 'w.returned_reason',
                    'a.assignment_role', 'a.status as assignment_status',
                ])
                ->orderBy('s.id')
                ->orderBy('w.sequence')
                ->get();

            foreach ($rows->groupBy('site_id') as $siteId => $siteRows) {
                $projects->put((int) $siteId, $this->summarizeProjectRows($siteRows, $userId, $month));
            }
        }

        // Dữ liệu cũ chỉ có kỹ sư phụ trách chính.
        if ($this->column('sites', 'lead_engineer_id')) {
            $leadRows = DB::table('sites as s')
                ->where('s.lead_engineer_id', $userId)
                ->where(function ($q) use ($start, $end) {
                    $hasAny = false;
                    if ($this->column('sites', 'completed_at')) {
                        $q->whereBetween('s.completed_at', [$start->toDateString(), $end->toDateString()]);
                        $hasAny = true;
                    }
                    if ($this->column('sites', 'handover_at')) {
                        $hasAny ? $q->orWhereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]) : $q->whereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]);
                        $hasAny = true;
                    }
                    if (! $hasAny) {
                        $q->whereBetween('s.updated_at', [$start, $end]);
                    }
                })
                ->select('s.*')
                ->get();

            foreach ($leadRows as $site) {
                if ($projects->has((int) $site->id)) {
                    continue;
                }
                $projects->put((int) $site->id, $this->summarizeLegacySite($site, $userId, $month));
            }
        }

        return $projects->values()->sortByDesc(fn ($row) => $row['activity_at'] ?? '')->values();
    }

    /**
     * Tín hiệu workflow của ĐÚNG MỘT công trình cho nhiều kỹ sư, bằng số truy vấn KHÔNG ĐỔI.
     *
     * Vì sao tồn tại: trang KPI công trình trước đây gọi `projectsForUserMonth()` trong vòng lặp
     * kỹ sư rồi `firstWhere('site_id', …)` để lấy đúng một phần tử. Đo được trên trang thật
     * (`tests/Feature/Technical/TechnicalProjectKpiQueryBudgetTest`): số truy vấn khớp chính xác
     * công thức `13 + 3·E + E·K` (E = số kỹ sư, K = số công trình KHÁC mỗi kỹ sư cũng tham gia
     * trong kỳ) — tức Θ(E·K), và phần lớn kết quả lấy về bị bỏ đi ngay.
     * Hàm này lọc theo `site_id` NGAY TRONG SQL nên chỉ còn 3 truy vấn bất kể E và K.
     *
     * Trả về đúng thứ `projectsForUserMonth($uid, $month)->firstWhere('site_id', $siteId)` trả
     * về cho từng kỹ sư (có test đối chứng từng khoá: `ProjectKpiSignalsEquivalenceTest`).
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, mixed>|null> id kỹ sư → tín hiệu, hoặc null nếu kỳ này không có
     */
    public function signalsForProjectUsers(int $siteId, array $userIds, string $month): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $ketQua = array_fill_keys($userIds, null);

        if ($siteId <= 0 || $userIds === [] || ! $this->table('sites')) {
            return $ketQua;
        }

        [$start, $end] = $this->monthBounds($month);

        // 1 truy vấn cho cả bảng minh chứng của công trình+kỳ (thay 1 truy vấn mỗi kỹ sư).
        $this->preloadEvidence($siteId, $userIds, $month);

        if ($this->table('project_workflow_assignments') && $this->table('project_workflow_steps')) {
            $rows = DB::table('project_workflow_assignments as a')
                ->join('project_workflow_steps as w', 'w.id', '=', 'a.workflow_step_id')
                ->join('sites as s', 's.id', '=', 'w.site_id')
                ->where('w.site_id', $siteId)
                ->whereIn('a.user_id', $userIds)
                ->when($this->column('project_workflow_assignments', 'is_active'), fn ($q) => $q->where('a.is_active', 1))
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('w.updated_at', [$start, $end]);
                    if ($this->column('project_workflow_steps', 'approved_at')) {
                        $q->orWhereBetween('w.approved_at', [$start, $end]);
                    }
                    if ($this->column('project_workflow_steps', 'submitted_at')) {
                        $q->orWhereBetween('w.submitted_at', [$start, $end]);
                    }
                    if ($this->column('project_workflow_assignments', 'submitted_at')) {
                        $q->orWhereBetween('a.submitted_at', [$start, $end]);
                    }
                    if ($this->column('sites', 'completed_at')) {
                        $q->orWhereBetween('s.completed_at', [$start->toDateString(), $end->toDateString()]);
                    }
                    if ($this->column('sites', 'handover_at')) {
                        $q->orWhereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]);
                    }
                })
                ->select([
                    's.id as site_id', 's.project_code', 's.name', 's.status as site_status',
                    's.project_phase', 's.progress_percent', 's.target_completion_at', 's.completed_at', 's.handover_at',
                    'w.id as workflow_step_id', 'w.step_code', 'w.status as step_status', 'w.due_at',
                    'w.recommitted_due_at', 'w.approved_at', 'w.submitted_at', 'w.returned_reason',
                    'a.assignment_role', 'a.status as assignment_status', 'a.user_id as ego_user_id',
                ])
                // Bản cũ sắp `s.id, w.sequence` rồi `groupBy('site_id')`; ở đây chỉ một công trình
                // nên thứ tự trong nhóm của từng kỹ sư vẫn là `w.sequence` — giữ y hệt.
                ->orderBy('w.sequence')
                ->get();

            foreach ($rows->groupBy('ego_user_id') as $uid => $userRows) {
                $ketQua[(int) $uid] = $this->summarizeProjectRows($userRows, (int) $uid, $month);
            }
        }

        // Nhánh dữ liệu cũ: chỉ kỹ sư phụ trách chính, và chỉ khi nhánh workflow chưa cho ra gì.
        if ($this->column('sites', 'lead_engineer_id')) {
            $site = DB::table('sites as s')
                ->where('s.id', $siteId)
                ->whereIn('s.lead_engineer_id', $userIds)
                ->where(function ($q) use ($start, $end) {
                    $hasAny = false;
                    if ($this->column('sites', 'completed_at')) {
                        $q->whereBetween('s.completed_at', [$start->toDateString(), $end->toDateString()]);
                        $hasAny = true;
                    }
                    if ($this->column('sites', 'handover_at')) {
                        $hasAny ? $q->orWhereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]) : $q->whereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]);
                        $hasAny = true;
                    }
                    if (! $hasAny) {
                        $q->whereBetween('s.updated_at', [$start, $end]);
                    }
                })
                ->select('s.*')
                ->first();

            if ($site !== null) {
                $lead = (int) ($site->lead_engineer_id ?? 0);
                if ($ketQua[$lead] === null && in_array($lead, $userIds, true)) {
                    $ketQua[$lead] = $this->summarizeLegacySite($site, $lead, $month);
                }
            }
        }

        return $ketQua;
    }

    public function projectUsers(int $siteId): Collection
    {
        $ids = collect();

        if ($this->table('project_workflow_assignments') && $this->table('project_workflow_steps')) {
            $ids = DB::table('project_workflow_assignments as a')
                ->join('project_workflow_steps as w', 'w.id', '=', 'a.workflow_step_id')
                ->where('w.site_id', $siteId)
                ->when($this->column('project_workflow_assignments', 'is_active'), fn ($q) => $q->where('a.is_active', 1))
                ->pluck('a.user_id');
        }

        if ($this->table('sites') && $this->column('sites', 'lead_engineer_id')) {
            $lead = (int) (DB::table('sites')->where('id', $siteId)->value('lead_engineer_id') ?? 0);
            if ($lead > 0) {
                $ids->push($lead);
            }
        }

        $ids = $ids->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty() || ! $this->table('users')) {
            return collect();
        }

        return DB::table('users')->whereIn('id', $ids->all())->select('id', 'name', 'email')->orderBy('name')->get();
    }

    public function evidenceForProject(int $siteId, string $month): Collection
    {
        if (! $this->table('technical_kpi_project_evidence')) {
            return collect();
        }

        return DB::table('technical_kpi_project_evidence')
            ->where('site_id', $siteId)
            ->where('payroll_month', $month)
            ->get()
            ->keyBy('user_id');
    }

    public function metricsForUserMonth(int $userId, string $month): array
    {
        $projects = $this->projectsForUserMonth($userId, $month);
        $projectIds = $projects->pluck('site_id')->map(fn ($id) => (int) $id)->filter()->values();
        $evidence = collect();

        if ($projectIds->isNotEmpty() && $this->table('technical_kpi_project_evidence')) {
            $evidence = DB::table('technical_kpi_project_evidence')
                ->where('user_id', $userId)
                ->where('payroll_month', $month)
                ->whereIn('site_id', $projectIds->all())
                ->get();
        }

        return $this->metricsFromProjects($month, $projects, $evidence);
    }

    /**
     * Phần TÍNH của `metricsForUserMonth()`, tách ra để bản hàng loạt dùng chung.
     *
     * Không I/O: nhận sẵn danh sách công trình đã tóm tắt và các dòng minh chứng của kỳ. Nhờ vậy
     * `dashboardForPayrolls()` nạp dữ liệu MỘT LƯỢT cho mọi kỹ sư rồi gọi hàm này, thay vì mỗi
     * kỹ sư một bộ truy vấn.
     *
     * @param  Collection<int, array<string, mixed>>  $projects
     * @param  Collection<int, object>  $evidence
     * @return array<string, mixed>
     */
    private function metricsFromProjects(string $month, Collection $projects, Collection $evidence): array
    {
        [$monthStart, $monthEnd] = $this->monthBounds($month);
        $timelineEligible = $projects->filter(function ($p) use ($monthStart, $monthEnd) {
            if (($p['timeline_excluded'] ?? false) || ! ($p['has_deadline'] ?? false) || empty($p['deadline_iso'])) {
                return false;
            }
            $deadline = Carbon::parse($p['deadline_iso']);

            return $deadline->betweenIncluded($monthStart, $monthEnd);
        });
        $timeline = [
            'available' => $timelineEligible->isNotEmpty(),
            'plan' => (float) $timelineEligible->count(),
            'actual' => (float) $timelineEligible->where('on_time', true)->count(),
            'label' => 'Tự động từ deadline / ngày hoàn thành công trình',
        ];

        $qualityRows = $evidence->filter(fn ($r) => $r->quality_first_pass !== null);
        $quality = [
            'available' => $qualityRows->isNotEmpty(),
            'plan' => (float) $qualityRows->count(),
            'actual' => (float) $qualityRows->filter(fn ($r) => (bool) $r->quality_first_pass)->count(),
            'label' => 'Từ KPI công trình · nghiệm thu lần đầu',
        ];

        $materialRows = $evidence->filter(fn ($r) => $r->material_waste_percent !== null);
        $material = [
            'available' => $materialRows->isNotEmpty(),
            'plan' => 0.0,
            'actual' => $materialRows->isNotEmpty() ? (float) $materialRows->avg('material_waste_percent') : 0.0,
            'label' => 'Từ KPI công trình · % hao hụt vật tư bình quân',
        ];

        $hseRows = $evidence->filter(fn ($r) => $r->hse_pass !== null);
        $hse = [
            'available' => $hseRows->isNotEmpty(),
            'plan' => (float) $hseRows->count(),
            'actual' => (float) $hseRows->filter(fn ($r) => (bool) $r->hse_pass)->count(),
            'label' => 'Từ KPI công trình · checklist HSE',
        ];

        $evnRows = $evidence->filter(fn ($r) => $r->evn_app_required !== null);
        $evnRequired = $evnRows->filter(fn ($r) => (bool) $r->evn_app_required);
        if ($evnRows->isNotEmpty() && $evnRequired->isEmpty()) {
            // Tất cả công trình trong kỳ đều N/A: không trừ KPI.
            $evn = ['available' => true, 'plan' => 1.0, 'actual' => 1.0, 'label' => 'Không có công trình yêu cầu EVN/App trong kỳ (N/A)'];
        } else {
            $evn = [
                'available' => $evnRequired->isNotEmpty(),
                'plan' => (float) $evnRequired->count(),
                'actual' => (float) $evnRequired->filter(fn ($r) => (bool) $r->evn_app_completed)->count(),
                'label' => 'Từ KPI công trình · EVN/App',
            ];
        }

        $penalty = (float) $evidence->sum(fn ($r) => (float) ($r->penalty_points ?? 0));

        return [
            self::SOURCE_TIMELINE => $timeline,
            self::SOURCE_QUALITY => $quality,
            self::SOURCE_MATERIAL => $material,
            self::SOURCE_HSE => $hse,
            self::SOURCE_EVN_APP => $evn,
            'project_penalty_points' => $penalty,
            'project_count' => $projects->count(),
            'issue_count' => $projects->filter(fn ($p) => ! empty($p['issues'] ?? []))->count(),
            'projects' => $projects,
        ];
    }

    public function dashboardForPayrolls(Collection $payrolls, string $month): array
    {
        $userIds = $payrolls
            ->map(fn ($row) => (int) ($row->user_id ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($userIds === []) {
            return [];
        }

        // Trước đây: `metricsForUserMonth()` cho MỖI bảng lương — đo được `32 + 3·U + U·S` truy
        // vấn (U = bảng lương trong kỳ, S = công trình mỗi kỹ sư), và controller lấy `limit(200)`
        // bảng lương. Nay nạp một lượt cho mọi kỹ sư.
        $projectsByUser = $this->projectsForUsersMonth($userIds, $month);
        // Lượt này KHÔNG thêm truy vấn: `projectsForUsersMonth()` ở trên đã nạp và memo.
        $evidenceByUser = $this->preloadEvidenceForUsers($userIds, $month);

        $map = [];
        foreach ($userIds as $userId) {
            $projects = $projectsByUser[$userId] ?? collect();
            $siteIds = $projects->pluck('site_id')->map(fn ($id) => (int) $id)->filter()->values()->all();

            // Bản cũ lọc `whereIn('site_id', …)` ngay trong SQL — lọc lại ở đây cho bằng.
            $evidence = ($evidenceByUser[$userId] ?? collect())
                ->filter(fn ($r) => in_array((int) $r->site_id, $siteIds, true))
                ->values();

            $map[$userId] = $this->metricsFromProjects($month, $projects, $evidence);
        }

        return $map;
    }

    /**
     * `projectsForUserMonth()` cho NHIỀU kỹ sư bằng số truy vấn không đổi (3).
     *
     * Trả về đúng thứ vòng lặp cũ trả về cho từng kỹ sư — có test đối chứng từng khoá
     * (`ProjectKpiDashboardEquivalenceTest`). Thứ tự phần tử cũng phải giống: `arsort()` của PHP 8
     * là SẮP ỔN ĐỊNH nên thứ tự trước khi sắp quyết định các phần tử bằng điểm, vì vậy ở đây giữ
     * nguyên trình tự "workflow theo `s.id` trước, rồi tới nhánh dữ liệu cũ".
     *
     * @param  list<int>  $userIds
     * @return array<int, Collection<int, array<string, mixed>>>
     */
    public function projectsForUsersMonth(array $userIds, string $month): array
    {
        $userIds = array_values(array_filter(array_unique(array_map('intval', $userIds)), fn (int $id) => $id > 0));
        $ketQua = [];
        foreach ($userIds as $uid) {
            $ketQua[$uid] = collect();
        }

        if ($userIds === [] || ! $this->table('sites')) {
            return $ketQua;
        }

        [$start, $end] = $this->monthBounds($month);

        // Nạp minh chứng của cả kỳ cho các kỹ sư này vào memo -> `evidenceRow()` tra O(1).
        $this->preloadEvidenceForUsers($userIds, $month);

        $choTomTat = [];
        $choDuLieuCu = [];

        if ($this->table('project_workflow_assignments') && $this->table('project_workflow_steps')) {
            $rows = DB::table('project_workflow_assignments as a')
                ->join('project_workflow_steps as w', 'w.id', '=', 'a.workflow_step_id')
                ->join('sites as s', 's.id', '=', 'w.site_id')
                ->whereIn('a.user_id', $userIds)
                ->when($this->column('project_workflow_assignments', 'is_active'), fn ($q) => $q->where('a.is_active', 1))
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('w.updated_at', [$start, $end]);
                    if ($this->column('project_workflow_steps', 'approved_at')) {
                        $q->orWhereBetween('w.approved_at', [$start, $end]);
                    }
                    if ($this->column('project_workflow_steps', 'submitted_at')) {
                        $q->orWhereBetween('w.submitted_at', [$start, $end]);
                    }
                    if ($this->column('project_workflow_assignments', 'submitted_at')) {
                        $q->orWhereBetween('a.submitted_at', [$start, $end]);
                    }
                    if ($this->column('sites', 'completed_at')) {
                        $q->orWhereBetween('s.completed_at', [$start->toDateString(), $end->toDateString()]);
                    }
                    if ($this->column('sites', 'handover_at')) {
                        $q->orWhereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]);
                    }
                })
                ->select([
                    's.id as site_id', 's.project_code', 's.name', 's.status as site_status',
                    's.project_phase', 's.progress_percent', 's.target_completion_at', 's.completed_at', 's.handover_at',
                    'w.id as workflow_step_id', 'w.step_code', 'w.status as step_status', 'w.due_at',
                    'w.recommitted_due_at', 'w.approved_at', 'w.submitted_at', 'w.returned_reason',
                    'a.assignment_role', 'a.status as assignment_status', 'a.user_id as ego_user_id',
                ])
                ->orderBy('s.id')
                ->orderBy('w.sequence')
                ->get();

            foreach ($rows->groupBy('ego_user_id') as $uid => $userRows) {
                $uid = (int) $uid;
                foreach ($userRows->groupBy('site_id') as $siteId => $siteRows) {
                    // Chưa tóm tắt ngay: `summarizeProjectRows()` sẽ hỏi `evidenceRow()`, mà memo
                    // chỉ có cặp CÓ dòng. Gom cặp trước, ghi memo âm, rồi mới tóm tắt.
                    $choTomTat[] = [$uid, (int) $siteId, $siteRows];
                }
            }
        }

        if ($this->column('sites', 'lead_engineer_id')) {
            $leadRows = DB::table('sites as s')
                ->whereIn('s.lead_engineer_id', $userIds)
                ->where(function ($q) use ($start, $end) {
                    $hasAny = false;
                    if ($this->column('sites', 'completed_at')) {
                        $q->whereBetween('s.completed_at', [$start->toDateString(), $end->toDateString()]);
                        $hasAny = true;
                    }
                    if ($this->column('sites', 'handover_at')) {
                        $hasAny ? $q->orWhereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]) : $q->whereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]);
                        $hasAny = true;
                    }
                    if (! $hasAny) {
                        $q->whereBetween('s.updated_at', [$start, $end]);
                    }
                })
                ->select('s.*')
                ->get();

            $daCoWorkflow = [];
            foreach ($choTomTat as [$uid, $siteId]) {
                $daCoWorkflow[$uid.':'.$siteId] = true;
            }

            foreach ($leadRows as $site) {
                $uid = (int) ($site->lead_engineer_id ?? 0);
                if (! isset($ketQua[$uid]) || isset($daCoWorkflow[$uid.':'.((int) $site->id)])) {
                    continue;
                }
                $choDuLieuCu[] = [$uid, $site];
            }
        }

        // Ghi memo ÂM cho mọi cặp sắp bị hỏi: thiếu bước này thì mỗi cặp KHÔNG có minh chứng lại
        // thành một truy vấn riêng — đúng N+1 vừa gỡ (đo được 6 và 7 truy vấn thay vì 3).
        foreach ($choTomTat as [$uid, $siteId]) {
            $this->evidenceMemo[$siteId.':'.$uid.':'.$month] ??= null;
        }
        foreach ($choDuLieuCu as [$uid, $site]) {
            $this->evidenceMemo[((int) $site->id).':'.$uid.':'.$month] ??= null;
        }

        foreach ($choTomTat as [$uid, $siteId, $siteRows]) {
            $ketQua[$uid] = $ketQua[$uid]->put($siteId, $this->summarizeProjectRows($siteRows, $uid, $month));
        }
        foreach ($choDuLieuCu as [$uid, $site]) {
            $ketQua[$uid] = $ketQua[$uid]->put((int) $site->id, $this->summarizeLegacySite($site, $uid, $month));
        }

        foreach ($ketQua as $uid => $projects) {
            $ketQua[$uid] = $projects->values()->sortByDesc(fn ($row) => $row['activity_at'] ?? '')->values();
        }

        return $ketQua;
    }

    /**
     * Nạp MỘT LƯỢT minh chứng của cả kỳ cho nhiều kỹ sư: vừa ghi vào memo của `evidenceRow()`,
     * vừa trả về bản nhóm theo kỹ sư để phần tính metric dùng luôn.
     *
     * Gọi lại với cùng (kỳ, tập kỹ sư) thì KHÔNG thêm truy vấn — nhờ `$evidencePreloaded`. Nhờ đó
     * `dashboardForPayrolls()` dùng được cùng dữ liệu mà không truy vấn hai lần.
     *
     * Khác `preloadEvidence()` (theo công trình): ở đây chưa biết tập công trình nên chỉ ghi được
     * những cặp CÓ dòng; cặp không có vẫn phải xuống DB một lần. `summarizeProjectRows()` chỉ hỏi
     * đúng các cặp (công trình, kỹ sư) có workflow trong kỳ, và guard đếm truy vấn canh phần đó.
     *
     * @param  list<int>  $userIds
     * @return array<int, Collection<int, object>>
     */
    private function preloadEvidenceForUsers(array $userIds, string $month): array
    {
        if ($userIds === [] || ! $this->table('technical_kpi_project_evidence')) {
            return [];
        }

        sort($userIds);
        $khoa = 'users:'.$month.':'.implode(',', $userIds);

        if (isset($this->evidenceByUsers[$khoa])) {
            return $this->evidenceByUsers[$khoa];
        }

        $rows = DB::table('technical_kpi_project_evidence')
            ->whereIn('user_id', $userIds)
            ->where('payroll_month', $month)
            ->get();

        foreach ($rows as $row) {
            $this->evidenceMemo[((int) $row->site_id).':'.((int) $row->user_id).':'.$month] = $row;
        }

        return $this->evidenceByUsers[$khoa] = $rows->groupBy(fn ($row) => (int) $row->user_id)->all();
    }

    private function summarizeProjectRows(Collection $rows, int $userId, string $month): array
    {
        $first = $rows->first();
        $construction = $rows->firstWhere('step_code', 'construction');
        $acceptance = $rows->firstWhere('step_code', 'acceptance');
        $reference = $acceptance ?: $construction ?: $rows->last();
        $deadline = $reference->recommitted_due_at ?? $reference->due_at ?? $first->target_completion_at ?? null;
        $completed = $acceptance->approved_at ?? $acceptance->submitted_at ?? $first->completed_at ?? $first->handover_at ?? $reference->approved_at ?? $reference->submitted_at ?? null;
        $onTime = null;
        if ($deadline && $completed) {
            $onTime = Carbon::parse($completed)->lte(Carbon::parse($deadline));
        }

        $issues = [];
        if ($deadline && $completed && ! $onTime) {
            $issues[] = 'Trễ tiến độ';
        }
        if ($deadline && ! $completed && Carbon::parse($deadline)->isPast()) {
            $issues[] = 'Đang quá hạn';
        }
        if ($acceptance && in_array((string) $acceptance->step_status, ['revision', 'returned'], true)) {
            $issues[] = 'Nghiệm thu cần bổ sung';
        }
        if ($acceptance && trim((string) ($acceptance->returned_reason ?? '')) !== '') {
            $issues[] = 'Có lý do trả nghiệm thu';
        }

        $evidence = $this->evidenceRow((int) $first->site_id, $userId, $month);
        if ($evidence) {
            if ($evidence->quality_first_pass === 0) {
                $issues[] = 'Không đạt nghiệm thu lần đầu';
            }
            if ($evidence->hse_pass === 0) {
                $issues[] = 'HSE chưa đạt';
            }
            if ((float) ($evidence->material_waste_percent ?? 0) > 4) {
                $issues[] = 'Hao hụt vật tư cao';
            }
            if ((float) ($evidence->penalty_points ?? 0) > 0) {
                $issues[] = 'Có điểm phạt';
            }
        }

        return [
            'site_id' => (int) $first->site_id,
            'code' => (string) ($first->project_code ?: ('DA-SITE-'.$first->site_id)),
            'name' => (string) ($first->name ?: ('Công trình #'.$first->site_id)),
            'role' => (string) ($rows->first()->assignment_role ?? 'collaborator'),
            'steps' => $rows->pluck('step_code')->filter()->unique()->values()->all(),
            'deadline' => $deadline ? Carbon::parse($deadline)->format('d/m/Y') : null,
            'deadline_iso' => $deadline ? Carbon::parse($deadline)->toDateTimeString() : null,
            'completed' => $completed ? Carbon::parse($completed)->format('d/m/Y') : null,
            'completed_iso' => $completed ? Carbon::parse($completed)->toDateTimeString() : null,
            'has_deadline' => (bool) $deadline,
            'on_time' => $onTime,
            'timeline_excluded' => (bool) ($evidence->timeline_excluded ?? false),
            'issues' => array_values(array_unique($issues)),
            'activity_at' => (string) ($completed ?? $reference->approved_at ?? $reference->submitted_at ?? $first->completed_at ?? ''),
            'evidence_status' => $evidence ? 'configured' : 'missing',
        ];
    }

    private function summarizeLegacySite(object $site, int $userId, string $month): array
    {
        $deadline = $site->target_completion_at ?? null;
        $completed = $site->completed_at ?? $site->handover_at ?? null;
        $onTime = $deadline && $completed ? Carbon::parse($completed)->lte(Carbon::parse($deadline)) : null;
        $evidence = $this->evidenceRow((int) $site->id, $userId, $month);
        $issues = [];
        if ($deadline && $completed && ! $onTime) {
            $issues[] = 'Trễ tiến độ';
        }
        if ($evidence && $evidence->hse_pass === 0) {
            $issues[] = 'HSE chưa đạt';
        }
        if ($evidence && (float) ($evidence->penalty_points ?? 0) > 0) {
            $issues[] = 'Có điểm phạt';
        }

        return [
            'site_id' => (int) $site->id,
            'code' => (string) ($site->project_code ?? ('DA-SITE-'.$site->id)),
            'name' => (string) ($site->name ?? ('Công trình #'.$site->id)),
            'role' => 'lead',
            'steps' => [],
            'deadline' => $deadline ? Carbon::parse($deadline)->format('d/m/Y') : null,
            'deadline_iso' => $deadline ? Carbon::parse($deadline)->toDateTimeString() : null,
            'completed' => $completed ? Carbon::parse($completed)->format('d/m/Y') : null,
            'completed_iso' => $completed ? Carbon::parse($completed)->toDateTimeString() : null,
            'has_deadline' => (bool) $deadline,
            'on_time' => $onTime,
            'timeline_excluded' => (bool) ($evidence->timeline_excluded ?? false),
            'issues' => $issues,
            'activity_at' => (string) ($completed ?? $site->updated_at ?? ''),
            'evidence_status' => $evidence ? 'configured' : 'missing',
        ];
    }

    private function evidenceRow(int $siteId, int $userId, string $month): ?object
    {
        if (! $this->table('technical_kpi_project_evidence')) {
            return null;
        }

        $khoa = $siteId.':'.$userId.':'.$month;

        if (array_key_exists($khoa, $this->evidenceMemo)) {
            return $this->evidenceMemo[$khoa];
        }

        return $this->evidenceMemo[$khoa] = DB::table('technical_kpi_project_evidence')
            ->where('site_id', $siteId)
            ->where('user_id', $userId)
            ->where('payroll_month', $month)
            ->first();
    }

    /**
     * Nạp TRƯỚC mọi dòng minh chứng của một công trình trong kỳ vào memo — 1 truy vấn thay cho
     * một truy vấn mỗi kỹ sư. Dòng không có cũng ghi `null` để lần tra sau không đi xuống DB.
     *
     * @param  list<int>  $userIds  danh sách kỹ sư cần có mặt trong memo
     */
    private function preloadEvidence(int $siteId, array $userIds, string $month): void
    {
        $khoaBang = $siteId.':'.$month;

        if (isset($this->evidencePreloaded[$khoaBang]) || ! $this->table('technical_kpi_project_evidence')) {
            return;
        }

        $this->evidencePreloaded[$khoaBang] = true;

        $rows = DB::table('technical_kpi_project_evidence')
            ->where('site_id', $siteId)
            ->where('payroll_month', $month)
            ->get();

        foreach ($rows as $row) {
            $this->evidenceMemo[$siteId.':'.((int) $row->user_id).':'.$month] = $row;
        }

        foreach ($userIds as $uid) {
            $khoa = $siteId.':'.((int) $uid).':'.$month;
            $this->evidenceMemo[$khoa] ??= null;
        }
    }
}
