<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Controller quản lý đề xuất nội bộ: tạo, duyệt, từ chối, xóa.
 */
class ProposalController extends Controller
{
    /**
     * Kiểm tra bảng có tồn tại trong database.
     */
    private function tableExists(string $table): bool
    {
        return SchemaCache::hasTable($table);
    }

    /**
     * Lọc dữ liệu chỉ giữ các cột có trong bảng.
     */
    private function filterColumns(string $table, array $data): array
    {
        if (! SchemaCache::hasTable($table)) {
            return $data;
        }

        return collect($data)
            ->only(SchemaCache::columns($table))
            ->toArray();
    }

    /**
     * Kiểm tra người dùng có quyền duyệt đề xuất.
     */
    private function isApprover(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole([
                'admin',
                'manager',
                'accounting',
                'sales_manager',
                'marketing_manager',
            ]);
        }

        return false;
    }

    /**
     * Danh sách loại đề xuất.
     */
    private function types(): array
    {
        return [
            'mua_sam' => 'Mua sắm',
            'tam_ung' => 'Tạm ứng',
            'sua_chua' => 'Sửa chữa',
            'nhan_su' => 'Nhân sự',
            'cong_viec' => 'Công việc',
            'quy_trinh' => 'Quy trình',
            'khac' => 'Khác',
        ];
    }

    /**
     * Danh sách mức độ ưu tiên.
     */
    private function priorities(): array
    {
        return [
            'low' => 'Thấp',
            'normal' => 'Bình thường',
            'high' => 'Cao',
            'urgent' => 'Gấp',
        ];
    }

    /**
     * Lấy tên phòng ban của người dùng hiện tại.
     */
    private function currentUserDepartment(): string
    {
        $user = auth()->user();

        if (! $user) {
            return '';
        }

        if (isset($user->department) && is_string($user->department)) {
            return $user->department;
        }

        if (SchemaCache::hasTable('departments') && SchemaCache::hasColumn('users', 'department_id')) {
            $department = DB::table('users')
                ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
                ->where('users.id', $user->id)
                ->select('departments.name')
                ->first();

            return $department->name ?? '';
        }

        return '';
    }

    /**
     * Hiển thị danh sách đề xuất kèm bộ lọc và thống kê.
     */
    public function index(Request $request)
    {
        if (! $this->tableExists('proposals')) {
            return back()->with('error', 'Chưa có bảng proposals trong database.');
        }

        $types = $this->types();
        $priorities = $this->priorities();
        $canApprove = $this->isApprover();

        $query = DB::table('proposals')
            ->leftJoin('users', 'users.id', '=', 'proposals.user_id')
            ->select(
                'proposals.*',
                DB::raw('COALESCE(users.name, "Không rõ") as employee_name')
            )
            ->orderByDesc('proposals.id');

        if (! $canApprove) {
            $query->where('proposals.user_id', auth()->id());
        }

        if ($request->filled('status')) {
            $query->where('proposals.status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('proposals.proposal_type', $request->type);
        }

        if ($request->filled('priority')) {
            $query->where('proposals.priority', $request->priority);
        }

        if ($request->filled('q')) {
            $q = trim($request->q);

            $query->where(function ($sub) use ($q) {
                $sub->where('proposals.title', 'like', "%{$q}%")
                    ->orWhere('proposals.content', 'like', "%{$q}%")
                    ->orWhere('proposals.reason', 'like', "%{$q}%")
                    ->orWhere('users.name', 'like', "%{$q}%");
            });
        }

        $proposals = $query->limit(300)->get();

        $summaryQuery = DB::table('proposals');

        if (! $canApprove) {
            $summaryQuery->where('user_id', auth()->id());
        }

        $summaryData = $summaryQuery->get();

        $summary = [
            'total' => $summaryData->count(),
            'pending' => $summaryData->where('status', 'pending')->count(),
            'approved' => $summaryData->where('status', 'approved')->count(),
            'rejected' => $summaryData->where('status', 'rejected')->count(),
            'amount_pending' => $summaryData->where('status', 'pending')->sum('amount'),
        ];

        return view('proposals.index', compact(
            'proposals',
            'summary',
            'types',
            'priorities',
            'canApprove'
        ));
    }

    /**
     * Hiển thị form tạo đề xuất.
     */
    public function create()
    {
        return view('proposals.create', [
            'types' => $this->types(),
            'priorities' => $this->priorities(),
            'departmentName' => $this->currentUserDepartment(),
        ]);
    }

    /**
     * Lưu đề xuất mới kèm file đính kèm.
     */
    public function store(Request $request)
    {
        if (! $this->tableExists('proposals')) {
            return back()->with('error', 'Chưa có bảng proposals trong database.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'proposal_type' => 'nullable|string|max:100',
            'priority' => 'nullable|string|max:30',
            'department_name' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'needed_date' => 'nullable|date',
            'content' => 'nullable|string',
            'reason' => 'nullable|string',
            'expected_result' => 'nullable|string',
            'payment_receiver' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:255',
            'attachments.*' => 'nullable|file|max:20480',
        ]);

        $data = [
            'user_id' => auth()->id(),
            'title' => $request->title,
            'proposal_type' => $request->proposal_type ?: 'khac',
            'priority' => $request->priority ?: 'normal',
            'department_name' => $request->department_name ?: $this->currentUserDepartment(),
            'amount' => (float) $request->input('amount', 0),
            'needed_date' => $request->needed_date,
            'content' => $request->content,
            'reason' => $request->reason,
            'expected_result' => $request->expected_result,
            'payment_receiver' => $request->payment_receiver,
            'bank_name' => $request->bank_name,
            'bank_account' => $request->bank_account,
            'bank_account_name' => $request->bank_account_name,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $proposalId = DB::table('proposals')
            ->insertGetId($this->filterColumns('proposals', $data));

        if ($request->hasFile('attachments') && $this->tableExists('proposal_attachments')) {
            foreach ($request->file('attachments') as $file) {
                if (! $file) {
                    continue;
                }

                $path = $file->store('proposal_attachments/'.$proposalId, 'public');

                DB::table('proposal_attachments')->insert([
                    'proposal_id' => $proposalId,
                    'uploaded_by' => auth()->id(),
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_mime' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()
            ->route('de-xuat.show', $proposalId)
            ->with('success', 'Đã gửi đề xuất cho sếp duyệt.');
    }

    /**
     * Hiển thị chi tiết đề xuất và file đính kèm.
     */
    public function show($id)
    {
        if (! $this->tableExists('proposals')) {
            abort(404);
        }

        $proposal = DB::table('proposals')
            ->leftJoin('users', 'users.id', '=', 'proposals.user_id')
            ->leftJoin('users as approver', 'approver.id', '=', 'proposals.approved_by')
            ->where('proposals.id', $id)
            ->select(
                'proposals.*',
                DB::raw('COALESCE(users.name, "Không rõ") as employee_name'),
                DB::raw('COALESCE(approver.name, "") as approved_name')
            )
            ->first();

        abort_if(! $proposal, 404);

        $canApprove = $this->isApprover();

        if (! $canApprove && (int) $proposal->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $attachments = $this->tableExists('proposal_attachments')
            ? DB::table('proposal_attachments')
                ->where('proposal_id', $id)
                ->orderByDesc('id')
                ->get()
            : collect();

        $paymentRequest = null;
        if ($this->tableExists('payment_requests') && SchemaCache::hasColumn('payment_requests', 'proposal_id')) {
            $paymentRequest = DB::table('payment_requests')
                ->where('proposal_id', $id)
                ->first();
        }

        $proposalTask = $this->findProposalTask((int) $id);
        $assignableUsers = collect();
        if ($canApprove && $proposal->status === 'approved' && ! $proposalTask) {
            $assignableUsers = $this->activeAssignees();
        }

        return view('proposals.show', [
            'proposal' => $proposal,
            'attachments' => $attachments,
            'types' => $this->types(),
            'priorities' => $this->priorities(),
            'canApprove' => $canApprove,
            'paymentRequest' => $paymentRequest,
            'proposalTask' => $proposalTask,
            'assignableUsers' => $assignableUsers,
            'suggestedTaskDueAt' => $this->suggestedTaskDueAt($proposal),
        ]);
    }

    /**
     * Form chỉnh sửa đề xuất. Người tạo vẫn được sửa khi đang chờ duyệt.
     */
    public function edit($id)
    {
        $proposal = DB::table('proposals')->where('id', $id)->first();
        abort_if(! $proposal, 404);
        abort_unless((int) $proposal->user_id === (int) auth()->id(), 403);

        if ($proposal->status !== 'pending') {
            return redirect()->route('de-xuat.show', $proposal->id)
                ->with('error', 'Chỉ đề xuất đang gửi duyệt mới được chỉnh sửa.');
        }

        return view('proposals.edit', [
            'proposal' => $proposal,
            'types' => $this->types(),
            'priorities' => $this->priorities(),
            'departmentName' => $this->currentUserDepartment(),
        ]);
    }

    /**
     * Cập nhật đề xuất và đồng bộ sang DNTT nếu DNTT chưa qua bước Giám đốc duyệt.
     */
    public function update(Request $request, $id)
    {
        $proposal = DB::table('proposals')->where('id', $id)->first();
        abort_if(! $proposal, 404);
        abort_unless((int) $proposal->user_id === (int) auth()->id(), 403);

        if ($proposal->status !== 'pending') {
            return redirect()->route('de-xuat.show', $proposal->id)
                ->with('error', 'Đề xuất không còn ở trạng thái cho phép chỉnh sửa.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'proposal_type' => 'nullable|string|max:100',
            'priority' => 'nullable|string|max:30',
            'department_name' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'needed_date' => 'nullable|date',
            'content' => 'nullable|string',
            'reason' => 'nullable|string',
            'expected_result' => 'nullable|string',
            'payment_receiver' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:255',
            'attachments.*' => 'nullable|file|max:20480',
        ]);

        $data = [
            'title' => $request->title,
            'proposal_type' => $request->proposal_type ?: 'khac',
            'priority' => $request->priority ?: 'normal',
            'department_name' => $request->department_name ?: $this->currentUserDepartment(),
            'amount' => (float) $request->input('amount', 0),
            'needed_date' => $request->needed_date,
            'content' => $request->content,
            'reason' => $request->reason,
            'expected_result' => $request->expected_result,
            'payment_receiver' => $request->payment_receiver,
            'bank_name' => $request->bank_name,
            'bank_account' => $request->bank_account,
            'bank_account_name' => $request->bank_account_name,
            'updated_at' => now(),
        ];

        DB::transaction(function () use ($id, $data, $request) {
            DB::table('proposals')->where('id', $id)->update($this->filterColumns('proposals', $data));

            if ($request->hasFile('attachments') && $this->tableExists('proposal_attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (! $file || ! $file->isValid()) {
                        continue;
                    }
                    $path = $file->store('proposal_attachments/'.$id, 'public');
                    DB::table('proposal_attachments')->insert($this->filterColumns('proposal_attachments', [
                        'proposal_id' => $id,
                        'uploaded_by' => auth()->id(),
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_mime' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }

            if ($this->tableExists('payment_requests') && SchemaCache::hasColumn('payment_requests', 'proposal_id')) {
                $payment = DB::table('payment_requests')->where('proposal_id', $id)->first();
                if ($payment && in_array($payment->status, ['draft', 'submitted', 'admin_rejected', 'accounting_rejected'], true)) {
                    $fresh = DB::table('proposals')
                        ->leftJoin('users', 'users.id', '=', 'proposals.user_id')
                        ->where('proposals.id', $id)
                        ->select('proposals.*', DB::raw('COALESCE(users.name, "Không rõ") as employee_name'))
                        ->first();

                    DB::table('payment_requests')->where('id', $payment->id)->update(
                        $this->filterColumns('payment_requests', $this->paymentDataFromProposal($fresh, false))
                    );
                }
            }
        });

        return redirect()->route('de-xuat.show', $id)
            ->with('success', 'Đã cập nhật đề xuất. Dữ liệu thanh toán liên kết cũng đã được đồng bộ nếu còn đang chờ duyệt.');
    }

    /**
     * Danh sách nhân sự đang hoạt động để Ban giám đốc chọn người đảm nhận.
     */
    private function activeAssignees()
    {
        if (! $this->tableExists('users')) {
            return collect();
        }

        $query = DB::table('users')
            ->where(function ($q) {
                $q->whereNull('users.is_active')->orWhere('users.is_active', 1);
            });

        if ($this->tableExists('departments')) {
            $query->leftJoin('departments', 'departments.id', '=', 'users.department_id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.department_id',
                    DB::raw('COALESCE(departments.name, "Chưa có phòng ban") as department_name')
                )
                ->orderByRaw('COALESCE(departments.name, "ZZZ")');
        } else {
            $query->select('users.id', 'users.name', 'users.email', 'users.department_id')
                ->addSelect(DB::raw('"Chưa có phòng ban" as department_name'));
        }

        return $query->orderBy('users.name')->get();
    }

    /**
     * Gợi ý hạn công việc từ ngày cần xử lý của đề xuất.
     * Nếu nội dung có dạng 19h00 / 19:00 thì ưu tiên đúng giờ đó.
     */
    private function suggestedTaskDueAt(object $proposal): ?string
    {
        if (empty($proposal->needed_date)) {
            return null;
        }

        $time = '17:00';
        $text = trim(implode(' ', array_filter([
            $proposal->expected_result ?? null,
            $proposal->content ?? null,
            $proposal->reason ?? null,
        ])));

        if ($text !== '' && preg_match('/\b([01]?\d|2[0-3])\s*(?:h|:)\s*([0-5]?\d)?\b/ui', $text, $match)) {
            $hour = str_pad((string) ((int) $match[1]), 2, '0', STR_PAD_LEFT);
            $minute = isset($match[2]) && $match[2] !== ''
                ? str_pad((string) ((int) $match[2]), 2, '0', STR_PAD_LEFT)
                : '00';
            $time = $hour.':'.$minute;
        }

        try {
            return Carbon::parse($proposal->needed_date.' '.$time)->format('Y-m-d\TH:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Tìm công việc đã sinh từ đề xuất. Ưu tiên cột proposal_id,
     * fallback qua link_url để vẫn an toàn nếu migration chưa chạy.
     */
    private function findProposalTask(int $proposalId): ?object
    {
        if (! $this->tableExists('tasks')) {
            return null;
        }

        $query = DB::table('tasks')
            ->leftJoin('users as task_assignee', 'task_assignee.id', '=', 'tasks.assignee_id')
            ->leftJoin('users as task_requester', 'task_requester.id', '=', 'tasks.requester_id')
            ->select(
                'tasks.*',
                DB::raw('COALESCE(task_assignee.name, "-") as assignee_name'),
                DB::raw('COALESCE(task_requester.name, "-") as requester_name')
            );

        if (SchemaCache::hasColumn('tasks', 'proposal_id')) {
            $query->where('tasks.proposal_id', $proposalId);
        } else {
            $query->where('tasks.link_url', route('de-xuat.show', $proposalId));
        }

        return $query->orderBy('tasks.id')->first();
    }

    /**
     * Copy file của đề xuất sang hồ sơ công việc để người nhận có đủ tài liệu.
     */
    private function copyProposalAttachmentsToTask(int $proposalId, int $taskId): void
    {
        if (! $this->tableExists('proposal_attachments') || ! $this->tableExists('task_attachments')) {
            return;
        }

        $files = DB::table('proposal_attachments')->where('proposal_id', $proposalId)->get();
        foreach ($files as $file) {
            $sourcePath = (string) ($file->file_path ?? '');
            if ($sourcePath === '' || ! Storage::disk('public')->exists($sourcePath)) {
                continue;
            }

            $fileName = basename($sourcePath);
            $targetPath = 'task_attachments/'.$taskId.'/proposal-'.$proposalId.'-'.$fileName;
            if (! Storage::disk('public')->copy($sourcePath, $targetPath)) {
                continue;
            }

            DB::table('task_attachments')->insert(
                $this->filterColumns('task_attachments', [
                    'task_id' => $taskId,
                    'uploaded_by' => auth()->id(),
                    'type' => 'task',
                    'file_name' => (string) ($file->file_name ?? $fileName),
                    'file_path' => $targetPath,
                    'file_mime' => $file->file_mime ?? null,
                    'file_size' => $file->file_size ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    /**
     * Tạo thông báo ngay cho người được giao việc.
     */
    private function notifyProposalTaskAssigned(int $taskId, int $assigneeId, string $taskTitle, $dueAt): void
    {
        try {
            $assigner = optional(auth()->user())->name ?: 'Hệ thống';
            $dueText = '';
            if (! empty($dueAt)) {
                try {
                    $dueText = ' - Hạn: '.Carbon::parse($dueAt)->format('d/m/Y H:i');
                } catch (\Throwable $e) {
                    $dueText = ' - Hạn: '.(string) $dueAt;
                }
            }

            DB::table('task_notifications')->insert(
                $this->filterColumns('task_notifications', [
                    'task_id' => $taskId,
                    'user_id' => $assigneeId,
                    'created_by' => auth()->id(),
                    'type' => 'assigned',
                    'title' => '📌 Công việc mới từ đề xuất',
                    'message' => $assigner.' đã giao cho bạn công việc: '.$taskTitle.$dueText,
                    'link' => route('tasks.show', $taskId),
                    'is_read' => 0,
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        } catch (\Throwable $e) {
            // Không để lỗi thông báo làm hỏng giao dịch chính ĐNTT + giao việc.
            report($e);
        }
    }

    /**
     * Chuẩn hóa dữ liệu thanh toán lấy từ đề xuất.
     */
    private function paymentDataFromProposal(object $proposal, bool $forCreate = true): array
    {
        $company = (string) session('active_company_name', '');
        if ($company === '') {
            $company = 'Công ty TNHH Ego Việt Nam';
        }

        $title = trim((string) ($proposal->title ?? ''));
        $content = trim((string) ($proposal->content ?? ''));
        $reason = trim((string) ($proposal->reason ?? ''));
        $paymentContent = trim($title.($content !== '' ? ' - '.$content : ''));

        $bank = [];
        if (trim((string) ($proposal->bank_name ?? '')) !== '') {
            $bank[] = 'Ngân hàng: '.trim((string) $proposal->bank_name);
        }
        if (trim((string) ($proposal->bank_account ?? '')) !== '') {
            $bank[] = 'Số tài khoản: '.trim((string) $proposal->bank_account);
        }
        if (trim((string) ($proposal->bank_account_name ?? '')) !== '') {
            $bank[] = 'Chủ tài khoản: '.trim((string) $proposal->bank_account_name);
        }

        $data = [
            'company' => $company,
            'receiver_name' => trim((string) ($proposal->payment_receiver ?? '')) ?: (string) ($proposal->employee_name ?? 'Chưa cập nhật'),
            'department' => (string) ($proposal->department_name ?? ''),
            'payment_content' => $paymentContent !== '' ? $paymentContent : 'Thanh toán theo đề xuất #'.$proposal->id,
            'reason' => $reason !== '' ? $reason : 'Thanh toán theo đề xuất #'.$proposal->id,
            'amount' => max(0, (int) round((float) ($proposal->amount ?? 0))),
            'payment_due_date' => $proposal->needed_date ?? null,
            'bank_name' => trim((string) ($proposal->bank_name ?? '')),
            'bank_account' => trim((string) ($proposal->bank_account ?? '')),
            'bank_account_name' => trim((string) ($proposal->bank_account_name ?? '')),
            'bank_info' => implode(' | ', $bank),
            'doc_type' => (($proposal->proposal_type ?? '') === 'tam_ung') ? 'advance' : 'payment_request',
            'updated_at' => now(),
        ];

        if ($forCreate) {
            $data['proposal_id'] = (int) $proposal->id;
            $data['created_by'] = (int) $proposal->user_id;
            $data['status'] = 'submitted';
            $data['code'] = 'TMP-'.uniqid('', true);
            $data['created_at'] = now();
        }

        return $data;
    }

    /**
     * Tạo DNTT từ đề xuất. DNTT được gửi duyệt ngay, không dừng ở Nháp.
     */
    public function createPaymentRequest(Request $request, $id)
    {
        if (! $this->isApprover()) {
            abort(403);
        }

        if (! $this->tableExists('payment_requests') || ! SchemaCache::hasColumn('payment_requests', 'proposal_id')) {
            return back()->with('error', 'Chưa hoàn tất cấu hình liên kết Đề xuất → Đề nghị thanh toán.');
        }

        if (! $this->tableExists('tasks')) {
            return back()->with('error', 'Chưa có bảng công việc để giao cho người đảm nhận.');
        }

        $proposal = DB::table('proposals')
            ->leftJoin('users', 'users.id', '=', 'proposals.user_id')
            ->where('proposals.id', $id)
            ->select('proposals.*', DB::raw('COALESCE(users.name, "Không rõ") as employee_name'))
            ->first();

        abort_if(! $proposal, 404);

        if ($proposal->status !== 'approved') {
            return back()->with('error', 'Phải duyệt đề xuất trước, sau đó mới tạo ĐNTT và giao việc.');
        }

        $existingPaymentId = DB::table('payment_requests')->where('proposal_id', $proposal->id)->value('id');
        $existingTask = $this->findProposalTask((int) $proposal->id);

        if ($existingPaymentId && $existingTask) {
            return redirect()->route('de-xuat.show', $proposal->id)
                ->with('success', 'Đề xuất này đã có ĐNTT và đã giao việc cho '.$existingTask->assignee_name.'.');
        }

        $needsTask = ! $existingTask;
        $rules = [
            'task_due_at' => $needsTask ? 'required|date' : 'nullable|date',
            'task_title' => 'nullable|string|max:255',
            'task_description' => 'nullable|string|max:5000',
        ];
        $rules['assignee_id'] = $needsTask
            ? 'required|integer|exists:users,id'
            : 'nullable|integer|exists:users,id';

        $validated = $request->validate($rules, [
            'assignee_id.required' => 'Vui lòng chọn người đảm nhận công việc.',
            'assignee_id.exists' => 'Người đảm nhận không còn tồn tại trong hệ thống.',
            'task_due_at.required' => 'Vui lòng chọn hạn hoàn thành công việc.',
            'task_due_at.date' => 'Hạn hoàn thành không hợp lệ.',
        ]);

        if ($needsTask) {
            $assigneeActive = DB::table('users')
                ->where('id', (int) $validated['assignee_id'])
                ->where(function ($q) {
                    $q->whereNull('is_active')->orWhere('is_active', 1);
                })
                ->exists();

            if (! $assigneeActive) {
                return back()->withInput()->with('error', 'Người được chọn hiện không còn hoạt động.');
            }

        }

        try {
            $result = DB::transaction(function () use ($proposal, $validated, $existingTask) {
                $paymentColumns = SchemaCache::columns('payment_requests');
                $paymentId = DB::table('payment_requests')
                    ->where('proposal_id', $proposal->id)
                    ->lockForUpdate()
                    ->value('id');

                $createdPayment = false;
                if (! $paymentId) {
                    $paymentData = $this->paymentDataFromProposal($proposal, true);

                    if (in_array('company_id', $paymentColumns, true)) {
                        $companyId = (int) session('active_company_id', 0);
                        if ($companyId > 0) {
                            $paymentData['company_id'] = $companyId;
                        }
                    }

                    $paymentId = DB::table('payment_requests')->insertGetId(
                        array_intersect_key($paymentData, array_flip($paymentColumns))
                    );

                    $paymentUpdate = ['code' => 'PR-'.now()->format('Y').'-'.str_pad((string) $paymentId, 5, '0', STR_PAD_LEFT)];
                    if (in_array('updated_at', $paymentColumns, true)) {
                        $paymentUpdate['updated_at'] = now();
                    }
                    DB::table('payment_requests')->where('id', $paymentId)->update($paymentUpdate);
                    $createdPayment = true;

                    if ($this->tableExists('payment_request_approvals')) {
                        DB::table('payment_request_approvals')->insert(
                            $this->filterColumns('payment_request_approvals', [
                                'payment_request_id' => $paymentId,
                                'actor_id' => (int) $proposal->user_id,
                                'step' => 'submit',
                                'action' => 'submitted',
                                'note' => 'Tự động gửi duyệt sau khi đề xuất #'.$proposal->id.' được duyệt',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ])
                        );
                    }

                    if ($this->tableExists('proposal_attachments') && $this->tableExists('payment_attachments')) {
                        $files = DB::table('proposal_attachments')->where('proposal_id', $proposal->id)->get();
                        foreach ($files as $file) {
                            $sourcePath = (string) ($file->file_path ?? '');
                            if ($sourcePath === '' || ! Storage::disk('public')->exists($sourcePath)) {
                                continue;
                            }
                            $fileName = basename($sourcePath);
                            $targetPath = 'payment_requests/'.$paymentId.'/proposal-'.$proposal->id.'-'.$fileName;
                            if (! Storage::disk('public')->copy($sourcePath, $targetPath)) {
                                continue;
                            }
                            DB::table('payment_attachments')->insert(
                                $this->filterColumns('payment_attachments', [
                                    'payment_request_id' => $paymentId,
                                    'original_name' => (string) ($file->file_name ?? $fileName),
                                    'path' => $targetPath,
                                    'mime_type' => $file->file_mime ?? null,
                                    'size' => $file->file_size ?? null,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ])
                            );
                        }
                    }
                }

                $taskId = $existingTask->id ?? null;
                $createdTask = false;
                if (! $taskId) {
                    $taskColumns = SchemaCache::columns('tasks');
                    $taskTitle = trim((string) ($validated['task_title'] ?? ''));
                    if ($taskTitle === '') {
                        $taskTitle = 'Thực hiện đề xuất #'.$proposal->id.' - '.$proposal->title;
                    }

                    $taskDescription = trim((string) ($validated['task_description'] ?? ''));
                    if ($taskDescription === '') {
                        $parts = array_filter([
                            'Nội dung đề xuất: '.trim((string) ($proposal->content ?? '')),
                            trim((string) ($proposal->reason ?? '')) !== '' ? 'Lý do: '.trim((string) $proposal->reason) : null,
                            trim((string) ($proposal->expected_result ?? '')) !== '' ? 'Kết quả kỳ vọng: '.trim((string) $proposal->expected_result) : null,
                            'ĐNTT liên kết: PR-'.now()->format('Y').'-'.str_pad((string) $paymentId, 5, '0', STR_PAD_LEFT),
                        ]);
                        $taskDescription = implode("\n\n", $parts);
                    }

                    $priority = match ((string) ($proposal->priority ?? 'normal')) {
                        'low' => 'low',
                        'high', 'urgent' => 'high',
                        default => 'medium',
                    };

                    $taskData = [
                        'title' => $taskTitle,
                        'description' => $taskDescription,
                        'requester_id' => auth()->id(),
                        'assignee_id' => (int) $validated['assignee_id'],
                        'priority' => $priority,
                        'status' => 'new',
                        'progress_percent' => 0,
                        'due_at' => $validated['task_due_at'] ?? null,
                        'link_url' => route('de-xuat.show', $proposal->id),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (in_array('proposal_id', $taskColumns, true)) {
                        $taskData['proposal_id'] = (int) $proposal->id;
                    }
                    if (in_array('company_id', $taskColumns, true)) {
                        $companyId = (int) session('active_company_id', 0);
                        if ($companyId > 0) {
                            $taskData['company_id'] = $companyId;
                        }
                    }

                    $taskId = DB::table('tasks')->insertGetId(
                        array_intersect_key($taskData, array_flip($taskColumns))
                    );
                    $createdTask = true;

                    $this->copyProposalAttachmentsToTask((int) $proposal->id, (int) $taskId);
                    $this->notifyProposalTaskAssigned(
                        (int) $taskId,
                        (int) $validated['assignee_id'],
                        $taskTitle,
                        $validated['task_due_at'] ?? null
                    );
                }

                return [
                    'payment_id' => (int) $paymentId,
                    'task_id' => (int) $taskId,
                    'created_payment' => $createdPayment,
                    'created_task' => $createdTask,
                ];
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Không hoàn tất được luồng ĐNTT & giao việc: '.$e->getMessage());
        }

        $message = 'Đã hoàn tất: ';
        $steps = [];
        $steps[] = $result['created_payment'] ? 'tạo ĐNTT và gửi duyệt' : 'ĐNTT đã tồn tại';
        $steps[] = $result['created_task'] ? 'giao việc cho người đảm nhận' : 'công việc đã tồn tại';
        $message .= implode(' + ', $steps).'.';

        return redirect()->route('de-xuat.show', $proposal->id)->with('success', $message);
    }

    /**
     * Duyệt đề xuất kèm ghi chú.
     */
    public function approve(Request $request, $id)
    {
        if (! $this->isApprover()) {
            abort(403);
        }

        $request->validate([
            'approved_note' => 'nullable|string|max:2000',
        ]);

        $data = [
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approved_note' => $request->approved_note,
            'reject_reason' => null,
            'updated_at' => now(),
        ];

        DB::table('proposals')
            ->where('id', $id)
            ->update($this->filterColumns('proposals', $data));

        return redirect()->route('de-xuat.show', $id)
            ->with('success', 'Đã duyệt đề xuất. Hãy chọn người đảm nhận để tạo ĐNTT và giao việc.');
    }

    /**
     * Từ chối đề xuất kèm lý do.
     */
    public function reject(Request $request, $id)
    {
        if (! $this->isApprover()) {
            abort(403);
        }

        $request->validate([
            'reject_reason' => 'nullable|string|max:2000',
        ]);

        $data = [
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'reject_reason' => $request->reject_reason,
            'updated_at' => now(),
        ];

        DB::table('proposals')
            ->where('id', $id)
            ->update($this->filterColumns('proposals', $data));

        return back()->with('success', 'Đã từ chối đề xuất.');
    }

    /**
     * Xóa đề xuất và toàn bộ file đính kèm.
     */
    public function destroy($id)
    {
        if (! $this->tableExists('proposals')) {
            abort(404);
        }

        $proposal = DB::table('proposals')
            ->where('id', $id)
            ->first();

        abort_if(! $proposal, 404);

        $canDelete = $this->isApprover()
            || ((int) $proposal->user_id === (int) auth()->id() && $proposal->status === 'pending');

        if (! $canDelete) {
            abort(403);
        }

        if ($this->tableExists('proposal_attachments')) {
            $attachments = DB::table('proposal_attachments')
                ->where('proposal_id', $id)
                ->get();

            foreach ($attachments as $file) {
                Storage::disk('public')->delete($file->file_path);
            }

            DB::table('proposal_attachments')
                ->where('proposal_id', $id)
                ->delete();
        }

        DB::table('proposals')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('de-xuat.index')
            ->with('success', 'Đã xóa đề xuất.');
    }
}
