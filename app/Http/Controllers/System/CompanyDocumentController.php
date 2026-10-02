<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\CompanyDocumentActivity;
use App\Models\CompanyDocumentFile;
use App\Models\CompanyDocumentFolder;
use App\Models\CompanyDocumentVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * EGO Document Workspace.
 *
 * Giữ nguyên bảng, ID và đường dẫn file cũ; bổ sung metadata, phiên bản,
 * phê duyệt, lịch sử và thùng rác mềm.
 */
class CompanyDocumentController extends Controller
{
    private array $departments = [
        'sales' => ['label' => 'Phòng Sales', 'short' => 'Sales', 'icon' => 'bi-briefcase', 'roles' => ['sales', 'sales_manager']],
        'marketing' => ['label' => 'Marketing', 'short' => 'Marketing', 'icon' => 'bi-megaphone', 'roles' => ['marketing', 'marketing_manager']],
        'technical' => ['label' => 'Kỹ thuật', 'short' => 'Kỹ thuật', 'icon' => 'bi-tools', 'roles' => ['technical', 'technical_manager', 'truong_phong_ky_thuat']],
        'accounting' => ['label' => 'Kế toán', 'short' => 'Kế toán', 'icon' => 'bi-calculator', 'roles' => ['accounting']],
        'assistant' => ['label' => 'Trợ lý', 'short' => 'Trợ lý', 'icon' => 'bi-person-workspace', 'roles' => ['assistant', 'tro_ly', 'management']],
        'warehouse' => ['label' => 'Kho', 'short' => 'Kho', 'icon' => 'bi-box-seam', 'roles' => ['warehouse', 'kho']],
        'media' => ['label' => 'Tư liệu hình ảnh', 'short' => 'Tư liệu', 'icon' => 'bi-images', 'roles' => ['*']],
    ];

    private array $approvalLabels = [
        'not_required' => 'Không cần duyệt',
        'draft' => 'Bản nháp',
        'pending' => 'Chờ duyệt',
        'revision' => 'Cần chỉnh sửa',
        'approved' => 'Đã duyệt',
        'archived' => 'Đã lưu trữ',
    ];

    public function index(Request $request)
    {
        $allowed = $this->allowedDepartments();
        abort_if(empty($allowed), 403);

        $department = (string) $request->query('department', $allowed[0]);
        abort_unless(in_array($department, $allowed, true), 403);

        $scope = (string) $request->query('scope', 'all');
        if (! in_array($scope, ['all', 'recent', 'approval', 'trash'], true)) {
            $scope = 'all';
        }

        $folderId = $request->integer('folder');
        $currentFolder = null;
        if ($folderId && $scope === 'all') {
            $currentFolder = CompanyDocumentFolder::where('department', $department)->findOrFail($folderId);
        }

        $folders = collect();
        if ($scope === 'all') {
            $folders = CompanyDocumentFolder::query()
                ->with(['creator:id,name'])
                ->withCount(['files', 'children'])
                ->where('department', $department)
                ->where('parent_id', $currentFolder?->id)
                ->orderBy('name')
                ->get();
        } elseif ($scope === 'trash') {
            $folders = CompanyDocumentFolder::onlyTrashed()
                ->with(['creator:id,name'])
                ->where('department', $department)
                ->latest('deleted_at')
                ->limit(100)
                ->get();
        }

        $filesQuery = CompanyDocumentFile::query()
            ->with([
                'uploader:id,name',
                'updater:id,name',
                'approver:id,name',
                'folder:id,name',
            ])
            ->withCount('versions')
            ->where('department', $department);

        if ($scope === 'all') {
            $filesQuery->where('folder_id', $currentFolder?->id);
        } elseif ($scope === 'recent') {
            $filesQuery->where('updated_at', '>=', now()->subDays(30));
        } elseif ($scope === 'approval') {
            $filesQuery->whereIn('approval_status', ['pending', 'revision']);
        } elseif ($scope === 'trash') {
            $filesQuery = CompanyDocumentFile::onlyTrashed()
                ->with(['uploader:id,name', 'folder:id,name'])
                ->where('department', $department)
                ->latest('deleted_at');
        }

        $this->applyFileFilters($filesQuery, $request);

        $files = $filesQuery
            ->orderByDesc($scope === 'trash' ? 'deleted_at' : 'updated_at')
            ->paginate(35)
            ->withQueryString();

        $breadcrumbs = $this->breadcrumbs($currentFolder);

        $baseFileQuery = CompanyDocumentFile::query()->where('department', $department);
        $baseFolderQuery = CompanyDocumentFolder::query()->where('department', $department);

        $stats = [
            'files' => (clone $baseFileQuery)->count(),
            'folders' => (clone $baseFolderQuery)->count(),
            'pending' => (clone $baseFileQuery)->where('approval_status', 'pending')->count(),
            'review_due' => (clone $baseFileQuery)
                ->whereNotNull('review_due_at')
                ->whereDate('review_due_at', '<=', now()->addDays(30)->toDateString())
                ->count(),
            'storage' => (int) (clone $baseFileQuery)->sum('size'),
            'trash' => CompanyDocumentFile::onlyTrashed()->where('department', $department)->count()
                + CompanyDocumentFolder::onlyTrashed()->where('department', $department)->count(),
        ];

        $scopeCounts = [
            'all' => $stats['files'] + $stats['folders'],
            'recent' => (clone $baseFileQuery)->where('updated_at', '>=', now()->subDays(30))->count(),
            'approval' => (clone $baseFileQuery)->whereIn('approval_status', ['pending', 'revision'])->count(),
            'trash' => $stats['trash'],
        ];

        $companyDocClipboard = session('company_doc_clipboard');

        return view('company-documents.index', [
            'departments' => $this->departments,
            'allowed' => $allowed,
            'department' => $department,
            'scope' => $scope,
            'currentFolder' => $currentFolder,
            'breadcrumbs' => $breadcrumbs,
            'folders' => $folders,
            'files' => $files,
            'stats' => $stats,
            'scopeCounts' => $scopeCounts,
            'companyDocClipboard' => $companyDocClipboard,
            'approvalLabels' => $this->approvalLabels,
            'canApproveDocuments' => $this->canApproveDocuments(),
            'canManageDepartment' => $this->canManageDepartment($department),
        ]);
    }

    public function storeFolder(Request $request)
    {
        $data = $request->validate([
            'department' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        abort_unless(in_array($data['department'], $this->allowedDepartments(), true), 403);
        abort_unless($this->canManageDepartment($data['department']), 403);

        $parentId = $data['parent_id'] ?? null;
        if ($parentId) {
            CompanyDocumentFolder::where('department', $data['department'])->findOrFail($parentId);
        }

        $name = trim($data['name']);
        $exists = CompanyDocumentFolder::where('department', $data['department'])
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Tên thư mục đã tồn tại trong vị trí này.'])->withInput();
        }

        $folder = CompanyDocumentFolder::create([
            'department' => $data['department'],
            'parent_id' => $parentId,
            'name' => $name,
            'description' => $data['description'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->activity(null, $folder, 'folder_created', 'Đã tạo thư mục “'.$folder->name.'”.');

        return back()->with('success', 'Đã tạo thư mục mới.');
    }

    public function updateFolder(Request $request, CompanyDocumentFolder $folder)
    {
        $this->authorizeDepartment($folder->department);
        abort_unless($this->canEditFolder($folder), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        $oldName = $folder->name;
        $folder->update([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        $this->activity(null, $folder, 'folder_updated', 'Đã cập nhật thư mục “'.$oldName.'”.');

        return back()->with('success', 'Đã cập nhật thư mục.');
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'department' => ['required', 'string'],
            'folder_id' => ['nullable', 'integer'],
            'files' => ['required'],
            'files.*' => ['file', 'max:51200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'review_due_at' => ['nullable', 'date'],
            'approval_mode' => ['nullable', 'in:not_required,draft,pending'],
        ]);

        $this->authorizeDepartment($data['department']);
        abort_unless($this->canManageDepartment($data['department']), 403);

        $folderId = $data['folder_id'] ?? null;
        if ($folderId) {
            CompanyDocumentFolder::where('department', $data['department'])->findOrFail($folderId);
        }

        $created = 0;
        foreach ($request->file('files', []) as $uploadedFile) {
            $originalName = $uploadedFile->getClientOriginalName();
            $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'tai-lieu';
            $ext = $uploadedFile->getClientOriginalExtension();
            $storedName = now()->format('YmdHis').'_'.Str::random(8).'_'.$safeName.($ext ? '.'.$ext : '');
            $path = $uploadedFile->storeAs('company-documents/'.$data['department'], $storedName, 'public');

            $file = CompanyDocumentFile::create([
                'department' => $data['department'],
                'folder_id' => $folderId,
                'name' => pathinfo($originalName, PATHINFO_FILENAME),
                'description' => $data['description'] ?? null,
                'tags' => $this->parseTags($data['tags'] ?? ''),
                'approval_status' => $data['approval_mode'] ?? 'not_required',
                'version_no' => 1,
                'review_due_at' => $data['review_due_at'] ?? null,
                'original_name' => $originalName,
                'path' => $path,
                'mime' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'uploaded_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->activity($file, null, 'file_uploaded', 'Đã tải lên tài liệu “'.$file->display_name.'”.');
            $created++;
        }

        return back()->with('success', 'Đã tải lên '.$created.' tài liệu.');
    }

    public function showFile(CompanyDocumentFile $file)
    {
        $this->authorizeDepartment($file->department);

        $file->load([
            'uploader:id,name',
            'updater:id,name',
            'approver:id,name',
            'folder:id,name',
            'versions.uploader:id,name',
            'activities.user:id,name',
        ]);

        return response()->json([
            'id' => $file->id,
            'name' => $file->display_name,
            'original_name' => $file->original_name,
            'description' => $file->description,
            'tags' => $file->tags ?: [],
            'department' => $file->department,
            'department_label' => $this->departments[$file->department]['label'] ?? $file->department,
            'folder' => $file->folder?->name,
            'mime' => $file->mime,
            'extension' => $file->extension,
            'size' => $this->formatBytes((int) $file->size),
            'version_no' => (int) ($file->version_no ?: 1),
            'approval_status' => $file->approval_status ?: 'not_required',
            'approval_label' => $this->approvalLabels[$file->approval_status] ?? $file->approval_status,
            'approval_note' => $file->approval_note,
            'review_due_at' => optional($file->review_due_at)->format('d/m/Y'),
            'uploader' => $file->uploader?->name ?: 'Không xác định',
            'updater' => $file->updater?->name ?: $file->uploader?->name ?: 'Không xác định',
            'approver' => $file->approver?->name,
            'approved_at' => optional($file->approved_at)->format('d/m/Y H:i'),
            'created_at' => optional($file->created_at)->format('d/m/Y H:i'),
            'updated_at' => optional($file->updated_at)->format('d/m/Y H:i'),
            'preview_url' => route('company-documents.files.preview', $file),
            'download_url' => route('company-documents.files.download', $file),
            'update_url' => route('company-documents.files.update', $file),
            'version_upload_url' => route('company-documents.files.versions.store', $file),
            'submit_url' => route('company-documents.files.submit', $file),
            'approve_url' => route('company-documents.files.approve', $file),
            'revision_url' => route('company-documents.files.revision', $file),
            'can_edit' => $this->canEditFile($file),
            'can_approve' => $this->canApproveDocuments(),
            'versions' => $file->versions->map(fn (CompanyDocumentVersion $version) => [
                'id' => $version->id,
                'version_no' => $version->version_no,
                'name' => $version->original_name,
                'size' => $this->formatBytes((int) $version->size),
                'change_note' => $version->change_note,
                'uploader' => $version->uploader?->name ?: 'Không xác định',
                'created_at' => optional($version->created_at)->format('d/m/Y H:i'),
                'download_url' => route('company-documents.versions.download', $version),
            ])->values(),
            'activities' => $file->activities->take(25)->map(fn (CompanyDocumentActivity $activity) => [
                'action' => $activity->action,
                'description' => $activity->description,
                'user' => $activity->user?->name ?: 'Hệ thống',
                'created_at' => optional($activity->created_at)->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }

    public function updateFile(Request $request, CompanyDocumentFile $file)
    {
        $this->authorizeDepartment($file->department);
        abort_unless($this->canEditFile($file), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'review_due_at' => ['nullable', 'date'],
        ]);

        $file->update([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'tags' => $this->parseTags($data['tags'] ?? ''),
            'review_due_at' => $data['review_due_at'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        $this->activity($file, null, 'file_updated', 'Đã cập nhật thông tin tài liệu “'.$file->display_name.'”.');

        return back()->with('success', 'Đã cập nhật thông tin tài liệu.');
    }

    public function uploadVersion(Request $request, CompanyDocumentFile $file)
    {
        $this->authorizeDepartment($file->department);
        abort_unless($this->canEditFile($file), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'change_note' => ['nullable', 'string', 'max:3000'],
            'submit_after_upload' => ['nullable', 'boolean'],
        ]);

        $currentVersion = max(1, (int) $file->version_no);

        CompanyDocumentVersion::firstOrCreate(
            ['file_id' => $file->id, 'version_no' => $currentVersion],
            [
                'original_name' => $file->original_name,
                'path' => $file->path,
                'mime' => $file->mime,
                'size' => $file->size,
                'change_note' => 'Phiên bản được lưu tự động trước khi cập nhật.',
                'uploaded_by' => $file->updated_by ?: $file->uploaded_by,
            ]
        );

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'tai-lieu';
        $ext = $uploadedFile->getClientOriginalExtension();
        $storedName = now()->format('YmdHis').'_'.Str::random(8).'_'.$safeName.($ext ? '.'.$ext : '');
        $path = $uploadedFile->storeAs('company-documents/'.$file->department, $storedName, 'public');

        $newVersion = $currentVersion + 1;
        $file->update([
            'original_name' => $originalName,
            'path' => $path,
            'mime' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
            'version_no' => $newVersion,
            'approval_status' => $request->boolean('submit_after_upload') ? 'pending' : 'draft',
            'approval_note' => null,
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => auth()->id(),
        ]);

        $this->activity($file, null, 'version_uploaded', 'Đã tải phiên bản V'.$newVersion.' của “'.$file->display_name.'”.', [
            'change_note' => $data['change_note'] ?? null,
        ]);

        return back()->with('success', 'Đã cập nhật phiên bản V'.$newVersion.'.');
    }

    public function submitApproval(CompanyDocumentFile $file)
    {
        $this->authorizeDepartment($file->department);
        abort_unless($this->canEditFile($file), 403);

        $file->update([
            'approval_status' => 'pending',
            'approval_note' => null,
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => auth()->id(),
        ]);

        $this->activity($file, null, 'approval_submitted', 'Đã gửi tài liệu “'.$file->display_name.'” để phê duyệt.');

        return back()->with('success', 'Đã gửi tài liệu để phê duyệt.');
    }

    public function approveFile(Request $request, CompanyDocumentFile $file)
    {
        $this->authorizeDepartment($file->department);
        abort_unless($this->canApproveDocuments(), 403);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:3000'],
        ]);

        $file->update([
            'approval_status' => 'approved',
            'approval_note' => $data['note'] ?? null,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        $this->activity($file, null, 'file_approved', 'Đã phê duyệt tài liệu “'.$file->display_name.'”.');

        return back()->with('success', 'Đã phê duyệt tài liệu.');
    }

    public function requestRevision(Request $request, CompanyDocumentFile $file)
    {
        $this->authorizeDepartment($file->department);
        abort_unless($this->canApproveDocuments(), 403);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:3000'],
        ]);

        $file->update([
            'approval_status' => 'revision',
            'approval_note' => $data['note'],
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => auth()->id(),
        ]);

        $this->activity($file, null, 'revision_requested', 'Đã yêu cầu chỉnh sửa tài liệu “'.$file->display_name.'”.', [
            'note' => $data['note'],
        ]);

        return back()->with('success', 'Đã gửi yêu cầu chỉnh sửa.');
    }

    public function preview(Request $request, CompanyDocumentFile $file)
    {
        $this->authorizeDepartment($file->department);
        $request->query->set('name', $file->original_name ?: basename($file->path));

        return app(CompanyDocumentPreviewController::class)($request, $file->id);
    }

    public function download(CompanyDocumentFile $file)
    {
        $this->authorizeDepartment($file->department);
        abort_unless(Storage::disk('public')->exists($file->path), 404);

        $this->activity($file, null, 'file_downloaded', 'Đã tải xuống tài liệu “'.$file->display_name.'”.');

        return Storage::disk('public')->download($file->path, $file->original_name ?: basename($file->path));
    }

    public function downloadVersion(CompanyDocumentVersion $version)
    {
        $version->load('file');
        abort_unless($version->file, 404);
        $this->authorizeDepartment($version->file->department);
        abort_unless(Storage::disk('public')->exists($version->path), 404);

        return Storage::disk('public')->download($version->path, $version->original_name ?: basename($version->path));
    }

    /**
     * Chuyển file vào thùng rác. Không xóa file vật lý.
     */
    public function destroyFile(CompanyDocumentFile $file)
    {
        $this->authorizeDepartment($file->department);
        abort_unless($this->canEditFile($file), 403);

        $this->activity($file, null, 'file_trashed', 'Đã đưa tài liệu “'.$file->display_name.'” vào thùng rác.');
        $file->delete();

        return back()->with('success', 'Đã đưa tài liệu vào thùng rác. File vật lý vẫn được giữ nguyên.');
    }

    public function restoreFile(int $file)
    {
        $record = CompanyDocumentFile::onlyTrashed()->findOrFail($file);
        $this->authorizeDepartment($record->department);
        abort_unless($this->canManageDepartment($record->department), 403);

        $record->restore();
        $record->updated_by = auth()->id();
        $record->save();
        $this->activity($record, null, 'file_restored', 'Đã khôi phục tài liệu “'.$record->display_name.'”.');

        return back()->with('success', 'Đã khôi phục tài liệu.');
    }

    /**
     * Chuyển toàn bộ cây thư mục vào thùng rác mềm. Không xóa file vật lý.
     */
    public function destroyFolder(CompanyDocumentFolder $folder)
    {
        $this->authorizeDepartment($folder->department);
        abort_unless($this->canEditFolder($folder), 403);

        $name = $folder->name;
        $this->trashFolderTree($folder);
        $this->activity(null, $folder, 'folder_trashed', 'Đã đưa thư mục “'.$name.'” vào thùng rác.');

        return redirect()->route('company-documents.index', ['department' => $folder->department])
            ->with('success', 'Đã đưa thư mục và nội dung bên trong vào thùng rác. Không xóa file vật lý.');
    }

    public function restoreFolder(int $folder)
    {
        $record = CompanyDocumentFolder::onlyTrashed()->findOrFail($folder);
        $this->authorizeDepartment($record->department);
        abort_unless($this->canManageDepartment($record->department), 403);

        $this->restoreFolderTree($record);
        $this->activity(null, $record, 'folder_restored', 'Đã khôi phục thư mục “'.$record->name.'”.');

        return back()->with('success', 'Đã khôi phục thư mục và các nội dung bên trong.');
    }

    public function setClipboard(Request $request)
    {
        $data = $request->validate([
            'object_type' => ['required', 'in:file,folder'],
            'object_id' => ['required', 'integer'],
            'action' => ['required', 'in:copy,move'],
        ]);

        if ($data['object_type'] === 'folder') {
            $item = CompanyDocumentFolder::findOrFail((int) $data['object_id']);
            $this->authorizeDepartment($item->department);
            $name = $item->name;
        } else {
            $item = CompanyDocumentFile::findOrFail((int) $data['object_id']);
            $this->authorizeDepartment($item->department);
            $name = $item->display_name;
        }

        session()->put('company_doc_clipboard', [
            'object_type' => $data['object_type'],
            'object_id' => (int) $data['object_id'],
            'action' => $data['action'],
            'name' => $name,
            'department' => $item->department,
        ]);

        return back()->with('success', 'Đã chọn '.($data['action'] === 'copy' ? 'sao chép' : 'di chuyển').': '.$name);
    }

    public function clearClipboard()
    {
        session()->forget('company_doc_clipboard');

        return back()->with('success', 'Đã hủy thao tác sao chép/di chuyển.');
    }

    public function paste(Request $request)
    {
        $data = $request->validate([
            'department' => ['required', 'string'],
            'folder_id' => ['nullable', 'integer'],
        ]);

        $this->authorizeDepartment($data['department']);
        abort_unless($this->canManageDepartment($data['department']), 403);

        $clip = session('company_doc_clipboard');
        if (! $clip) {
            return back()->withErrors(['clipboard' => 'Chưa chọn file hoặc thư mục để dán.']);
        }

        $targetDepartment = $data['department'];
        $targetFolderId = $data['folder_id'] ?: null;
        if ($targetFolderId) {
            CompanyDocumentFolder::where('department', $targetDepartment)->findOrFail($targetFolderId);
        }

        if ($clip['object_type'] === 'file') {
            $file = CompanyDocumentFile::findOrFail((int) $clip['object_id']);
            $this->authorizeDepartment($file->department);

            if ($clip['action'] === 'move') {
                $file->department = $targetDepartment;
                $file->folder_id = $targetFolderId;
                $file->updated_by = auth()->id();
                $file->save();
                session()->forget('company_doc_clipboard');
                $this->activity($file, null, 'file_moved', 'Đã di chuyển tài liệu “'.$file->display_name.'”.');

                return redirect()->route('company-documents.index', ['department' => $targetDepartment, 'folder' => $targetFolderId])
                    ->with('success', 'Đã di chuyển tài liệu.');
            }

            $copy = $this->copyFile($file, $targetDepartment, $targetFolderId);
            $this->activity($copy, null, 'file_copied', 'Đã sao chép tài liệu từ “'.$file->display_name.'”.');

            return redirect()->route('company-documents.index', ['department' => $targetDepartment, 'folder' => $targetFolderId])
                ->with('success', 'Đã sao chép tài liệu.');
        }

        $folder = CompanyDocumentFolder::findOrFail((int) $clip['object_id']);
        $this->authorizeDepartment($folder->department);

        if ($clip['action'] === 'move') {
            if ($this->isSameOrChild($folder->id, $targetFolderId)) {
                return back()->withErrors(['clipboard' => 'Không thể di chuyển thư mục vào chính nó hoặc thư mục con.']);
            }

            $folder->department = $targetDepartment;
            $folder->parent_id = $targetFolderId;
            $folder->updated_by = auth()->id();
            $folder->save();
            $this->syncFolderDepartment($folder, $targetDepartment);
            session()->forget('company_doc_clipboard');
            $this->activity(null, $folder, 'folder_moved', 'Đã di chuyển thư mục “'.$folder->name.'”.');

            return redirect()->route('company-documents.index', ['department' => $targetDepartment, 'folder' => $targetFolderId])
                ->with('success', 'Đã di chuyển thư mục.');
        }

        $newFolder = $this->copyFolder($folder, $targetDepartment, $targetFolderId);
        $this->activity(null, $newFolder, 'folder_copied', 'Đã sao chép thư mục từ “'.$folder->name.'”.');

        return redirect()->route('company-documents.index', ['department' => $targetDepartment, 'folder' => $targetFolderId])
            ->with('success', 'Đã sao chép thư mục.');
    }

    private function applyFileFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string) $request->query('q'));
        if ($keyword !== '') {
            $query->where(function (Builder $sub) use ($keyword): void {
                $like = '%'.$keyword.'%';
                $sub->where('original_name', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('tags', 'like', $like);
            });
        }

        $type = (string) $request->query('type');
        if ($type !== '') {
            $query->where(function (Builder $sub) use ($type): void {
                match ($type) {
                    'pdf' => $sub->where('original_name', 'like', '%.pdf'),
                    'office' => $sub->where(function (Builder $q): void {
                        $q->where('original_name', 'like', '%.doc%')
                            ->orWhere('original_name', 'like', '%.xls%')
                            ->orWhere('original_name', 'like', '%.ppt%');
                    }),
                    'image' => $sub->where('mime', 'like', 'image/%'),
                    'archive' => $sub->where(function (Builder $q): void {
                        $q->where('original_name', 'like', '%.zip')
                            ->orWhere('original_name', 'like', '%.rar')
                            ->orWhere('original_name', 'like', '%.7z');
                    }),
                    default => null,
                };
            });
        }

        $status = (string) $request->query('status');
        if ($status !== '' && array_key_exists($status, $this->approvalLabels)) {
            $query->where('approval_status', $status);
        }
    }

    private function breadcrumbs(?CompanyDocumentFolder $folder): array
    {
        $items = [];
        $current = $folder;
        $guard = 0;

        while ($current && $guard < 30) {
            array_unshift($items, $current);
            $current = $current->parent_id ? CompanyDocumentFolder::find($current->parent_id) : null;
            $guard++;
        }

        return $items;
    }

    private function trashFolderTree(CompanyDocumentFolder $folder): void
    {
        foreach ($folder->files()->get() as $file) {
            $file->delete();
        }
        foreach ($folder->children()->get() as $child) {
            $this->trashFolderTree($child);
        }
        $folder->delete();
    }

    private function restoreFolderTree(CompanyDocumentFolder $folder): void
    {
        $folder->restore();
        CompanyDocumentFile::onlyTrashed()->where('folder_id', $folder->id)->restore();

        $children = CompanyDocumentFolder::onlyTrashed()->where('parent_id', $folder->id)->get();
        foreach ($children as $child) {
            $this->restoreFolderTree($child);
        }
    }

    private function copyFile(CompanyDocumentFile $file, string $department, ?int $folderId): CompanyDocumentFile
    {
        abort_unless(Storage::disk('public')->exists($file->path), 404);

        $ext = pathinfo($file->original_name ?: $file->path, PATHINFO_EXTENSION);
        $safe = Str::slug(pathinfo($file->original_name ?: $file->path, PATHINFO_FILENAME)) ?: 'file';
        $newPath = 'company-documents/'.$department.'/'.now()->format('YmdHis').'_'.Str::random(8).'_'.$safe.($ext ? '.'.$ext : '');
        Storage::disk('public')->copy($file->path, $newPath);

        return CompanyDocumentFile::create([
            'department' => $department,
            'folder_id' => $folderId,
            'name' => $file->name,
            'description' => $file->description,
            'tags' => $file->tags,
            'approval_status' => 'draft',
            'version_no' => 1,
            'review_due_at' => $file->review_due_at,
            'original_name' => $file->original_name,
            'path' => $newPath,
            'mime' => $file->mime,
            'size' => $file->size,
            'uploaded_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    private function copyFolder(CompanyDocumentFolder $folder, string $department, ?int $parentId): CompanyDocumentFolder
    {
        $newFolder = CompanyDocumentFolder::create([
            'department' => $department,
            'parent_id' => $parentId,
            'name' => $this->uniqueFolderName($department, $parentId, $folder->name),
            'description' => $folder->description,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        foreach ($folder->files()->get() as $file) {
            $this->copyFile($file, $department, $newFolder->id);
        }
        foreach ($folder->children()->get() as $child) {
            $this->copyFolder($child, $department, $newFolder->id);
        }

        return $newFolder;
    }

    private function syncFolderDepartment(CompanyDocumentFolder $folder, string $department): void
    {
        CompanyDocumentFile::where('folder_id', $folder->id)->update([
            'department' => $department,
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ]);

        foreach ($folder->children()->get() as $child) {
            $child->department = $department;
            $child->updated_by = auth()->id();
            $child->save();
            $this->syncFolderDepartment($child, $department);
        }
    }

    private function isSameOrChild(int $sourceFolderId, ?int $targetFolderId): bool
    {
        if (! $targetFolderId) {
            return false;
        }

        $current = CompanyDocumentFolder::find($targetFolderId);
        while ($current) {
            if ((int) $current->id === $sourceFolderId) {
                return true;
            }
            $current = $current->parent_id ? CompanyDocumentFolder::find($current->parent_id) : null;
        }

        return false;
    }

    private function uniqueFolderName(string $department, ?int $parentId, string $name): string
    {
        $base = trim($name) ?: 'Thư mục';
        $candidate = $base;
        $i = 2;

        while (CompanyDocumentFolder::where('department', $department)
            ->where('parent_id', $parentId)
            ->where('name', $candidate)
            ->exists()) {
            $candidate = $base.' ('.$i.')';
            $i++;
        }

        return $candidate;
    }

    private function parseTags(string $tags): array
    {
        return collect(preg_split('/[,;\n]+/u', $tags) ?: [])
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    private function activity(
        ?CompanyDocumentFile $file,
        ?CompanyDocumentFolder $folder,
        string $action,
        string $description,
        array $meta = []
    ): void {
        CompanyDocumentActivity::create([
            'file_id' => $file?->id,
            'folder_id' => $folder?->id,
            'action' => $action,
            'description' => $description,
            'meta' => $meta ?: null,
            'user_id' => auth()->id(),
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $index);

        return number_format($value, $index === 0 ? 0 : 1, ',', '.').' '.$units[$index];
    }

    private function authorizeDepartment(string $department): void
    {
        abort_unless(in_array($department, $this->allowedDepartments(), true), 403);
    }

    private function canEditFile(CompanyDocumentFile $file): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $this->hasAnyRole($user, ['admin', 'management'])
            || (int) $file->uploaded_by === (int) $user->id
            || $this->isDepartmentManager($file->department);
    }

    private function canEditFolder(CompanyDocumentFolder $folder): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $this->hasAnyRole($user, ['admin', 'management'])
            || (int) $folder->created_by === (int) $user->id
            || $this->isDepartmentManager($folder->department);
    }

    private function canManageDepartment(string $department): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($this->hasAnyRole($user, ['admin', 'management'])) {
            return true;
        }

        return in_array($department, $this->allowedDepartments(), true);
    }

    private function canApproveDocuments(): bool
    {
        $user = auth()->user();

        return $user && $this->hasAnyRole($user, [
            'admin',
            'management',
            'sales_manager',
            'marketing_manager',
            'technical_manager',
            'truong_phong_ky_thuat',
            'accounting',
        ]);
    }

    private function isDepartmentManager(string $department): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $roles = match ($department) {
            'sales' => ['sales_manager'],
            'marketing' => ['marketing_manager'],
            'technical' => ['technical_manager', 'truong_phong_ky_thuat'],
            'accounting' => ['accounting'],
            'assistant' => ['management', 'assistant', 'tro_ly'],
            'warehouse' => ['warehouse', 'kho'],
            default => [],
        };

        return $this->hasAnyRole($user, $roles);
    }

    private function allowedDepartments(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        if ($this->hasAnyRole($user, ['admin', 'management', 'warehouse', 'kho'])) {
            return array_keys($this->departments);
        }

        $allowed = [];
        foreach ($this->departments as $key => $meta) {
            $roles = $meta['roles'] ?? [];
            if (in_array('*', $roles, true) || $this->hasAnyRole($user, $roles)) {
                $allowed[] = $key;
            }
        }

        return array_values(array_unique($allowed));
    }

    private function hasAnyRole($user, array $roles): bool
    {
        if (empty($roles)) {
            return false;
        }
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }
        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return isset($user->role) && in_array((string) $user->role, $roles, true);
    }
}
