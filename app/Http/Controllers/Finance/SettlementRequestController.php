<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payments\AdvanceRequest;
use App\Models\Payments\SettlementRequest;
use App\Models\User;
use App\View\Presenters\Finance\SettlementDetailPresenter;
use App\View\Presenters\Finance\SettlementRequestListPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettlementRequestController extends Controller
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

    private function labels(): array
    {
        return [
            'draft' => 'Nháp',
            'pending' => 'Chờ QL tài chính duyệt hoàn ứng',
            'submitted' => 'Chờ QL tài chính duyệt hoàn ứng',
            'admin_approved' => 'Chờ kế toán đối soát',
            'admin_rejected' => 'QL tài chính từ chối hoàn ứng',
            'accounting_approved' => 'Đã quyết toán',
            'accounting_rejected' => 'Kế toán từ chối đối soát',
        ];
    }

    private function settlementAmounts(float $advanceAmount, float $actualAmount): array
    {
        $advanceAmount = round(max(0, $advanceAmount), 2);
        $actualAmount = round(max(0, $actualAmount), 2);
        $difference = round($actualAmount - $advanceAmount, 2);

        if ($difference > 0) {
            return [0.0, $difference, 'pay_more'];
        }

        if ($difference < 0) {
            return [abs($difference), $difference, 'refund'];
        }

        return [0.0, 0.0, 'balanced'];
    }

    public function index(Request $request, SettlementRequestListPresenter $presenter)
    {
        $user = auth()->user();
        $canViewAll = $this->canViewAll($user);
        $q = SettlementRequest::query()->with(['creator', 'advanceRequest']);

        if (! $canViewAll) {
            $q->where('created_by', $user->id);
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $q->where(function ($x) use ($term) {
                $x->where('code', 'like', "%{$term}%")
                    ->orWhere('recipient_name', 'like', "%{$term}%")
                    ->orWhere('reason', 'like', "%{$term}%")
                    ->orWhereHas('advanceRequest', function ($advance) use ($term) {
                        $advance->where('code', 'like', "%{$term}%");
                    });
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
            'pending' => (clone $base)->whereIn('status', ['pending', 'submitted', 'admin_approved'])->count(),
            'done' => (clone $base)->where('status', 'accounting_approved')->count(),
            'spent' => (clone $base)->sum('actual_amount'),
            'refund' => (clone $base)->sum('refund_amount'),
        ];

        // Chỉ những phiếu tạm ứng đã được kế toán xác nhận chi và chưa có phiếu hoàn ứng.
        $advanceQuery = DB::table('advance_requests as a')
            ->select(
                'a.id', 'a.code', 'a.created_by', 'a.company', 'a.recipient_name', 'a.amount',
                'a.reason', 'a.needed_date', 'a.settlement_due_date', 'a.accounting_approved_at'
            )
            ->where('a.status', 'accounting_approved')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('settlement_requests as s')
                    ->whereColumn('s.advance_request_id', 'a.id');
            });

        if (! $canViewAll) {
            $advanceQuery->where('a.created_by', $user->id);
        }

        $advances = $advanceQuery->orderByDesc('a.accounting_approved_at')->orderByDesc('a.id')->limit(300)->get();

        $creators = $canViewAll
            ? User::query()->orderBy('name')->get(['id', 'name'])
            : collect([$user]);

        $canApproveManagement = $this->canApproveManagement($user);
        $canApproveAccounting = $this->canApproveAccounting($user);

        return view('settlement_requests.index', array_merge(compact(
            'items', 'stats', 'advances', 'creators', 'canViewAll'
        ), [
            'labels' => $this->labels(),
            'canApproveManagement' => $canApproveManagement,
            'canApproveAccounting' => $canApproveAccounting,
        ], $presenter->viewData(
            items: $items,
            advances: $advances,
            labels: $this->labels(),
            currentUserId: $user?->id,
            canViewAll: $canViewAll,
            canApproveManagement: $canApproveManagement,
            canApproveAccounting: $canApproveAccounting,
            stats: $stats,
            pageCount: $items->count(),
            totalCount: $items->total(),
            rawFilters: $request->only(['q', 'status', 'created_by', 'date_from', 'date_to']),
            oldInput: (array) $request->old(),
        )));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'advance_request_id' => 'required|integer|exists:advance_requests,id',
            'actual_amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:5000',
            'note' => 'nullable|string|max:5000',
            'attachments' => 'required|array|min:1|max:10',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx',
        ], [
            'advance_request_id.required' => 'Vui lòng chọn phiếu tạm ứng cần hoàn.',
            'attachments.required' => 'Vui lòng đính kèm ít nhất 1 chứng từ.',
            'attachments.*.max' => 'Mỗi chứng từ tối đa 10MB.',
        ]);

        $paths = [];
        foreach ($request->file('attachments', []) as $file) {
            $paths[] = $file->store('settlement-requests/'.now()->format('Y/m'), 'public');
        }

        try {
            $item = DB::transaction(function () use ($data, $request, $paths) {
                $advance = DB::table('advance_requests')
                    ->where('id', (int) $data['advance_request_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $advance || $advance->status !== 'accounting_approved') {
                    throw ValidationException::withMessages([
                        'advance_request_id' => 'Phiếu tạm ứng này chưa được kế toán xác nhận đã chi.',
                    ]);
                }

                if (! $this->canViewAll(auth()->user()) && (int) $advance->created_by !== (int) auth()->id()) {
                    abort(403);
                }

                $alreadyExists = SettlementRequest::query()
                    ->where('advance_request_id', $advance->id)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyExists) {
                    throw ValidationException::withMessages([
                        'advance_request_id' => 'Phiếu tạm ứng này đã có đề nghị hoàn ứng. Hãy mở phiếu hoàn ứng hiện có để tiếp tục xử lý.',
                    ]);
                }

                [$refundAmount, $differenceAmount, $settlementType] = $this->settlementAmounts(
                    (float) $advance->amount,
                    (float) $data['actual_amount']
                );

                $item = SettlementRequest::create([
                    'code' => 'TMP-'.uniqid(),
                    'advance_request_id' => $advance->id,
                    'created_by' => auth()->id(),
                    'company' => $advance->company ?: (session('company_name') ?: 'CÔNG TY TNHH EGO VIỆT NAM'),
                    'recipient_name' => $advance->recipient_name ?: (auth()->user()->name ?? 'Nhân sự'),
                    'advance_amount' => $advance->amount,
                    'actual_amount' => $data['actual_amount'],
                    'refund_amount' => $refundAmount,
                    'difference_amount' => $differenceAmount,
                    'settlement_type' => $settlementType,
                    'attachments' => $paths,
                    'reason' => $data['reason'],
                    'note' => $data['note'] ?? null,
                    'status' => $request->boolean('submit_now') ? 'submitted' : 'draft',
                    'submitted_at' => $request->boolean('submit_now') ? now() : null,
                ]);

                $item->code = 'HU-'.now()->format('Ym').'-'.str_pad((string) $item->id, 5, '0', STR_PAD_LEFT);
                $item->save();

                return $item;
            });
        } catch (\Throwable $e) {
            // Nếu lưu DB thất bại, dọn các file vừa upload để không tạo file rác.
            foreach ($paths as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return redirect()->route('settlement_requests.show', $item)->with('success', 'Đã tạo đề nghị hoàn ứng và liên kết phiếu tạm ứng thành công.');
    }

    public function storeForAdvance(Request $request, AdvanceRequest $advanceRequest)
    {
        $user = auth()->user();
        abort_unless((int) $advanceRequest->created_by === (int) $user->id || $this->canViewAll($user), 403);

        if ((string) $advanceRequest->status !== 'accounting_approved') {
            throw ValidationException::withMessages([
                'actual_amount' => 'Chỉ được hoàn ứng sau khi kế toán đã xác nhận chi tạm ứng.',
            ]);
        }

        $data = $request->validate([
            'actual_amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:5000',
            'note' => 'nullable|string|max:5000',
            'attachments' => 'required|array|min:1|max:10',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx',
        ], [
            'attachments.required' => 'Vui lòng đính kèm ít nhất 1 chứng từ.',
            'attachments.*.max' => 'Mỗi chứng từ tối đa 10MB.',
        ]);

        $paths = [];
        foreach ($request->file('attachments', []) as $file) {
            $paths[] = $file->store('settlement-requests/'.now()->format('Y/m'), 'public');
        }

        try {
            $item = DB::transaction(function () use ($advanceRequest, $data, $request, $paths) {
                $advance = DB::table('advance_requests')
                    ->where('id', $advanceRequest->id)
                    ->lockForUpdate()
                    ->first();

                if (! $advance || $advance->status !== 'accounting_approved') {
                    throw ValidationException::withMessages([
                        'actual_amount' => 'Phiếu tạm ứng chưa ở trạng thái kế toán đã chi.',
                    ]);
                }

                $alreadyExists = SettlementRequest::query()
                    ->where('advance_request_id', $advance->id)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyExists) {
                    throw ValidationException::withMessages([
                        'actual_amount' => 'Phiếu tạm ứng này đã có hồ sơ hoàn ứng.',
                    ]);
                }

                [$refundAmount, $differenceAmount, $settlementType] = $this->settlementAmounts(
                    (float) $advance->amount,
                    (float) $data['actual_amount']
                );

                $item = SettlementRequest::create([
                    'code' => 'TMP-'.uniqid(),
                    'advance_request_id' => $advance->id,
                    'created_by' => auth()->id(),
                    'company' => $advance->company ?: (session('company_name') ?: 'CÔNG TY TNHH EGO VIỆT NAM'),
                    'recipient_name' => $advance->recipient_name ?: (auth()->user()->name ?? 'Nhân sự'),
                    'advance_amount' => $advance->amount,
                    'actual_amount' => $data['actual_amount'],
                    'refund_amount' => $refundAmount,
                    'difference_amount' => $differenceAmount,
                    'settlement_type' => $settlementType,
                    'attachments' => $paths,
                    'reason' => $data['reason'],
                    'note' => $data['note'] ?? null,
                    'status' => $request->boolean('submit_now') ? 'submitted' : 'draft',
                    'submitted_at' => $request->boolean('submit_now') ? now() : null,
                ]);

                $item->code = 'HU-'.now()->format('Ym').'-'.str_pad((string) $item->id, 5, '0', STR_PAD_LEFT);
                $item->save();

                return $item;
            });
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return redirect()->route('advance_requests.show', $advanceRequest)
            ->with('success', $request->boolean('submit_now')
                ? 'Đã tạo và gửi hoàn ứng cho Quản lý tài chính duyệt.'
                : 'Đã lưu nháp hoàn ứng.');
    }

    public function show(SettlementRequest $settlementRequest, SettlementDetailPresenter $presenter)
    {
        $user = auth()->user();
        $canViewAll = $this->canViewAll($user);

        abort_unless(
            $canViewAll || (int) $settlementRequest->created_by === (int) $user->id,
            403
        );

        $settlementRequest->load(['creator', 'advanceRequest']);

        $approverIds = collect([
            $settlementRequest->admin_approved_by,
            $settlementRequest->accounting_approved_by,
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $approvers = $approverIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $approverIds)->pluck('name', 'id');

        return view('settlement_requests.show', array_merge([
            'item' => $settlementRequest,
            'labels' => $this->labels(),
            'canViewAll' => $canViewAll,
            'canApproveManagement' => $this->canApproveManagement($user),
            'canApproveAccounting' => $this->canApproveAccounting($user),
            'approvers' => $approvers,
        ], $presenter->viewData(
            item: $settlementRequest,
            labels: $this->labels(),
            approvers: $approvers->all(),
            currentUserId: $user?->id,
            canViewAll: $canViewAll,
            canApproveManagement: $this->canApproveManagement($user),
            canApproveAccounting: $this->canApproveAccounting($user),
        )));
    }

    public function destroy(SettlementRequest $settlementRequest)
    {
        $user = auth()->user();
        $isOwner = (int) $settlementRequest->created_by === (int) $user->id;
        $deletable = in_array((string) $settlementRequest->status, ['draft', 'admin_rejected', 'accounting_rejected'], true);
        abort_unless($deletable && ($isOwner || $this->canViewAll($user)), 403);

        foreach ((array) ($settlementRequest->attachments ?? []) as $path) {
            Storage::disk('public')->delete($path);
        }
        $advanceId = $settlementRequest->advance_request_id;
        $settlementRequest->delete();

        return $advanceId
            ? redirect()->route('advance_requests.show', $advanceId)->with('success', 'Đã xóa bản nháp hoàn ứng.')
            : back()->with('success', 'Đã xóa đề nghị hoàn ứng.');
    }

    public function submit(SettlementRequest $settlementRequest)
    {
        abort_unless(
            (int) $settlementRequest->created_by === (int) auth()->id()
            && in_array($settlementRequest->status, ['draft', 'admin_rejected', 'accounting_rejected'], true),
            403
        );
        $settlementRequest->update([
            'status' => 'submitted', 'submitted_at' => now(),
            'admin_approved_by' => null, 'admin_approved_at' => null,
            'accounting_approved_by' => null, 'accounting_approved_at' => null,
        ]);

        return redirect()->route('advance_requests.show', $settlementRequest->advance_request_id)->with('success', 'Đã gửi hoàn ứng để Quản lý tài chính duyệt.');
    }

    public function managementApprove(SettlementRequest $settlementRequest)
    {
        abort_unless($this->canApproveManagement(auth()->user()), 403);
        abort_unless(in_array((string) $settlementRequest->status, ['pending', 'submitted'], true), 422);
        $settlementRequest->update([
            'status' => 'admin_approved',
            'admin_approved_by' => auth()->id(),
            'admin_approved_at' => now(),
        ]);

        return back()->with('success', 'Quản lý tài chính đã duyệt hoàn ứng. Hồ sơ đã chuyển sang kế toán đối soát.');
    }

    public function managementReject(SettlementRequest $settlementRequest)
    {
        abort_unless($this->canApproveManagement(auth()->user()), 403);
        abort_unless(in_array((string) $settlementRequest->status, ['pending', 'submitted'], true), 422);
        $settlementRequest->update(['status' => 'admin_rejected']);

        return back()->with('success', 'Đã từ chối đề nghị hoàn ứng.');
    }

    public function accountingApprove(SettlementRequest $settlementRequest)
    {
        abort_unless($this->canApproveAccounting(auth()->user()), 403);
        abort_unless($settlementRequest->status === 'admin_approved', 422);
        $settlementRequest->update([
            'status' => 'accounting_approved',
            'accounting_approved_by' => auth()->id(),
            'accounting_approved_at' => now(),
        ]);

        return redirect()->route('advance_requests.show', $settlementRequest->advance_request_id)->with('success', 'Kế toán đã đối soát hoàn ứng. Hồ sơ tạm ứng đã quyết toán hoàn tất.');
    }

    public function accountingReject(SettlementRequest $settlementRequest)
    {
        abort_unless($this->canApproveAccounting(auth()->user()), 403);
        abort_unless($settlementRequest->status === 'admin_approved', 422);
        $settlementRequest->update(['status' => 'accounting_rejected']);

        return redirect()->route('advance_requests.show', $settlementRequest->advance_request_id)->with('success', 'Kế toán đã từ chối đối soát hoàn ứng.');
    }
}
