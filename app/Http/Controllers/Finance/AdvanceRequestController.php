<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payments\AdvanceRequest;
use App\Models\User;
use App\View\Presenters\Finance\AdvanceRequestListPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvanceRequestController extends Controller
{
    private function canViewAll($user): bool
    {
        if (! $user) {
            return false;
        }
        $roles = ['admin', 'accounting', 'ketoan', 'ke_toan', 'management'];
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
            return true;
        }

        return in_array(strtolower((string) ($user->role ?? '')), $roles, true) || (int) ($user->is_admin ?? 0) === 1;
    }

    private function canApproveManagement($user): bool
    {
        if (! $user) {
            return false;
        }
        if ((int) ($user->is_admin ?? 0) === 1) {
            return true;
        }
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'management'])) {
            return true;
        }

        return in_array(strtolower((string) ($user->role ?? '')), ['admin', 'management'], true);
    }

    private function canApproveAccounting($user): bool
    {
        if (! $user) {
            return false;
        }
        if ((int) ($user->is_admin ?? 0) === 1) {
            return true;
        }
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'accounting', 'ketoan', 'ke_toan'])) {
            return true;
        }

        return in_array(strtolower((string) ($user->role ?? '')), ['admin', 'accounting', 'ketoan', 'ke_toan'], true);
    }

    private function currentCompanyName(): string
    {
        $companyId = session('ego_company_id')
            ?? session('selected_company_id')
            ?? session('company_id')
            ?? session('current_company_id')
            ?? session('active_company_id')
            ?? optional(auth()->user())->company_id;

        if ($companyId) {
            try {
                $name = DB::table('companies')->where('id', $companyId)->value('name');
                if ($name) {
                    return (string) $name;
                }
            } catch (\Throwable $e) {
                // Fallback below if the company table/context is unavailable.
            }
        }

        return (string) (session('company_name') ?: 'CÔNG TY TNHH EGO VIỆT NAM');
    }

    private function labels(): array
    {
        return [
            'draft' => 'Nháp',
            'pending' => 'Chờ QL tài chính duyệt',
            'submitted' => 'Chờ QL tài chính duyệt',
            'admin_approved' => 'Chờ kế toán chi',
            'admin_rejected' => 'QL tài chính từ chối',
            'accounting_approved' => 'Đã chi • Cần hoàn ứng',
            'accounting_rejected' => 'Kế toán từ chối',
        ];
    }

    public function index(Request $request, AdvanceRequestListPresenter $presenter)
    {
        $user = auth()->user();
        $canViewAll = $this->canViewAll($user);
        $q = AdvanceRequest::query()->with(['creator', 'settlementRequest']);
        if (! $canViewAll) {
            $q->where('created_by', $user->id);
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $q->where(function ($x) use ($term) {
                $x->where('code', 'like', "%{$term}%")
                    ->orWhere('recipient_name', 'like', "%{$term}%")
                    ->orWhere('reason', 'like', "%{$term}%");
            });
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('created_by') && $canViewAll) {
            $q->where('created_by', (int) $request->created_by);
        }
        if ($request->filled('date_from')) {
            $q->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->date_to);
        }

        $base = clone $q;
        $items = $q->latest('id')->paginate(20)->withQueryString();
        $stats = [
            'total' => (clone $base)->count(),
            // Các hồ sơ đang chờ QL tài chính / kế toán ở cả giai đoạn tạm ứng và hoàn ứng.
            'pending' => (clone $base)->where(function ($x) {
                $x->whereIn('status', ['pending', 'submitted', 'admin_approved'])
                    ->orWhereHas('settlementRequest', fn ($s) => $s->whereIn('status', ['submitted', 'admin_approved']));
            })->count(),
            // Đã chi tạm ứng nhưng nhân sự chưa lập hồ sơ hoàn ứng.
            'need_settlement' => (clone $base)
                ->where('status', 'accounting_approved')
                ->whereDoesntHave('settlementRequest')
                ->count(),
            'overdue_settlement' => (clone $base)
                ->where('status', 'accounting_approved')
                ->whereDoesntHave('settlementRequest')
                ->whereNotNull('settlement_due_date')
                ->whereDate('settlement_due_date', '<', now()->toDateString())
                ->count(),
            'done' => (clone $base)
                ->whereHas('settlementRequest', fn ($x) => $x->where('status', 'accounting_approved'))
                ->count(),
            'amount' => (clone $base)->sum('amount'),
        ];
        $creators = $canViewAll ? User::query()->orderBy('name')->get(['id', 'name']) : collect([$user]);

        $canApproveManagement = $this->canApproveManagement($user);
        $canApproveAccounting = $this->canApproveAccounting($user);

        return view('advance_requests.index', array_merge([
            'items' => $items, 'stats' => $stats, 'labels' => $this->labels(), 'creators' => $creators,
            'canViewAll' => $canViewAll, 'canApproveManagement' => $canApproveManagement,
            'canApproveAccounting' => $canApproveAccounting,
        ], $presenter->viewData(
            items: $items,
            labels: $this->labels(),
            currentUserId: $user?->id,
            canApproveManagement: $canApproveManagement,
            canApproveAccounting: $canApproveAccounting,
            today: now()->startOfDay(),
            stats: $stats,
            pageCount: $items->count(),
            totalCount: $items->total(),
            rawFilters: $request->only(['q', 'status', 'created_by', 'date_from', 'date_to']),
            oldInput: (array) $request->old(),
            currentUserName: (string) ($user->name ?? ''),
        )));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'recipient_name' => 'required|string|max:255', 'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:5000', 'needed_date' => 'nullable|date',
            'settlement_due_date' => 'nullable|date|after_or_equal:needed_date',
            'bank_name' => 'nullable|string|max:255', 'bank_account' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:255', 'note' => 'nullable|string|max:5000',
        ]);
        $data['created_by'] = auth()->id();
        $data['company'] = $this->currentCompanyName();
        $data['status'] = $request->boolean('submit_now') ? 'submitted' : 'draft';
        if ($data['status'] === 'submitted') {
            $data['submitted_at'] = now();
        }

        DB::transaction(function () use (&$data) {
            $item = AdvanceRequest::create(array_merge($data, ['code' => 'TMP-'.uniqid()]));
            $item->code = 'TU-'.now()->format('Ym').'-'.str_pad((string) $item->id, 5, '0', STR_PAD_LEFT);
            $item->save();
        });

        return back()->with('success', 'Đã tạo đề nghị tạm ứng.');
    }

    public function show(AdvanceRequest $advanceRequest)
    {
        $user = auth()->user();
        $canViewAll = $this->canViewAll($user);

        abort_unless(
            $canViewAll || (int) $advanceRequest->created_by === (int) $user->id,
            403
        );

        $advanceRequest->load(['creator', 'settlementRequest.creator']);

        $approverIds = collect([
            $advanceRequest->admin_approved_by,
            $advanceRequest->accounting_approved_by,
            optional($advanceRequest->settlementRequest)->admin_approved_by,
            optional($advanceRequest->settlementRequest)->accounting_approved_by,
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $approvers = $approverIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $approverIds)->pluck('name', 'id');

        return view('advance_requests.show', [
            'item' => $advanceRequest,
            'labels' => $this->labels(),
            'canViewAll' => $canViewAll,
            'canApproveManagement' => $this->canApproveManagement($user),
            'canApproveAccounting' => $this->canApproveAccounting($user),
            'approvers' => $approvers,
        ]);
    }

    public function update(Request $request, AdvanceRequest $advanceRequest)
    {
        abort_unless((int) $advanceRequest->created_by === (int) auth()->id() && in_array($advanceRequest->status, ['draft', 'admin_rejected', 'accounting_rejected'], true), 403);
        $data = $request->validate([
            'recipient_name' => 'required|string|max:255', 'amount' => 'required|numeric|min:1', 'reason' => 'required|string|max:5000',
            'needed_date' => 'nullable|date', 'settlement_due_date' => 'nullable|date|after_or_equal:needed_date',
            'bank_name' => 'nullable|string|max:255', 'bank_account' => 'nullable|string|max:100', 'bank_account_name' => 'nullable|string|max:255', 'note' => 'nullable|string|max:5000',
        ]);
        $advanceRequest->update($data);

        return back()->with('success', 'Đã cập nhật đề nghị tạm ứng.');
    }

    public function destroy(AdvanceRequest $advanceRequest)
    {
        $user = auth()->user();
        $isOwner = (int) $advanceRequest->created_by === (int) $user->id;
        $deletable = in_array((string) $advanceRequest->status, ['draft', 'admin_rejected', 'accounting_rejected'], true);
        abort_unless($deletable && ($isOwner || $this->canViewAll($user)), 403);
        abort_if($advanceRequest->settlementRequest()->exists(), 422, 'Phiếu đã phát sinh hoàn ứng nên không thể xóa.');
        $advanceRequest->delete();

        return back()->with('success', 'Đã xóa đề nghị tạm ứng.');
    }

    public function submit(AdvanceRequest $advanceRequest)
    {
        abort_unless((int) $advanceRequest->created_by === (int) auth()->id() && in_array($advanceRequest->status, ['draft', 'admin_rejected', 'accounting_rejected'], true), 403);
        $advanceRequest->update([
            'status' => 'submitted', 'submitted_at' => now(),
            'admin_approved_by' => null, 'admin_approved_at' => null,
            'accounting_approved_by' => null, 'accounting_approved_at' => null,
        ]);

        return back()->with('success', 'Đã gửi đề nghị tạm ứng để duyệt.');
    }

    public function managementApprove(AdvanceRequest $advanceRequest)
    {
        abort_unless($this->canApproveManagement(auth()->user()), 403);
        abort_unless(in_array((string) $advanceRequest->status, ['pending', 'submitted'], true), 422);
        $advanceRequest->update(['status' => 'admin_approved', 'admin_approved_by' => auth()->id(), 'admin_approved_at' => now()]);

        return back()->with('success', 'Đã duyệt đề nghị tạm ứng.');
    }

    public function managementReject(AdvanceRequest $advanceRequest)
    {
        abort_unless($this->canApproveManagement(auth()->user()), 403);
        abort_unless(in_array((string) $advanceRequest->status, ['pending', 'submitted'], true), 422);
        $advanceRequest->update(['status' => 'admin_rejected']);

        return back()->with('success', 'Đã từ chối đề nghị tạm ứng.');
    }

    public function accountingApprove(AdvanceRequest $advanceRequest)
    {
        abort_unless($this->canApproveAccounting(auth()->user()), 403);
        abort_unless($advanceRequest->status === 'admin_approved', 422);
        $advanceRequest->update(['status' => 'accounting_approved', 'accounting_approved_by' => auth()->id(), 'accounting_approved_at' => now()]);

        return back()->with('success', 'Đã xác nhận chi tạm ứng.');
    }

    public function accountingReject(AdvanceRequest $advanceRequest)
    {
        abort_unless($this->canApproveAccounting(auth()->user()), 403);
        abort_unless($advanceRequest->status === 'admin_approved', 422);
        $advanceRequest->update(['status' => 'accounting_rejected']);

        return back()->with('success', 'Kế toán đã từ chối chi tạm ứng.');
    }
}
