<?php

namespace App\Services\Ai\Tools;

use App\Contracts\Services\PageAccessServiceInterface;
use App\Models\Payments\PaymentRequest;
use App\Models\User;
use App\Support\DisplayFormat;
use App\Support\EgoCompanyScope;
use App\Support\SchemaCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PaymentRequestTool
{
    private const STATUS_LABELS = [
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'admin_approved' => 'Giám đốc đã duyệt',
        'admin_rejected' => 'Giám đốc từ chối',
        'accounting_approved' => 'Kế toán đã chi',
        'accounting_rejected' => 'Kế toán từ chối',
    ];

    public function __construct(private readonly PageAccessServiceInterface $pageAccess) {}

    public function definitions(): array
    {
        return [
            [
                'type' => 'function',
                'name' => 'search_payment_requests',
                'description' => 'Tìm danh sách đề nghị thanh toán trong CRM. Chỉ đọc dữ liệu mà người dùng hiện tại được phép xem.',
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => ['string', 'null'], 'description' => 'Mã phiếu, người nhận, lý do hoặc nội dung thanh toán.'],
                        'status' => [
                            'type' => ['string', 'null'],
                            'enum' => [null, 'draft', 'submitted', 'admin_approved', 'admin_rejected', 'accounting_approved', 'accounting_rejected'],
                        ],
                        'date_from' => ['type' => ['string', 'null'], 'description' => 'Ngày tạo từ YYYY-MM-DD.'],
                        'date_to' => ['type' => ['string', 'null'], 'description' => 'Ngày tạo đến YYYY-MM-DD.'],
                        'creator_name' => ['type' => ['string', 'null'], 'description' => 'Tên người tạo phiếu.'],
                        'overdue_only' => ['type' => 'boolean'],
                        'company_scope' => ['type' => 'string', 'enum' => ['current', 'all']],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['keyword', 'status', 'date_from', 'date_to', 'creator_name', 'overdue_only', 'company_scope', 'limit'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => 'summarize_payment_requests',
                'description' => 'Tổng hợp số lượng, tổng tiền, đã chi, đang chờ và quá hạn của đề nghị thanh toán.',
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => ['string', 'null']],
                        'status' => [
                            'type' => ['string', 'null'],
                            'enum' => [null, 'draft', 'submitted', 'admin_approved', 'admin_rejected', 'accounting_approved', 'accounting_rejected'],
                        ],
                        'date_from' => ['type' => ['string', 'null']],
                        'date_to' => ['type' => ['string', 'null']],
                        'creator_name' => ['type' => ['string', 'null']],
                        'overdue_only' => ['type' => 'boolean'],
                        'company_scope' => ['type' => 'string', 'enum' => ['current', 'all']],
                    ],
                    'required' => ['keyword', 'status', 'date_from', 'date_to', 'creator_name', 'overdue_only', 'company_scope'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => 'get_payment_request',
                'description' => 'Lấy chi tiết an toàn của một đề nghị thanh toán theo ID hoặc mã phiếu. Không trả thông tin tài khoản ngân hàng.',
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => ['integer', 'null']],
                        'code' => ['type' => ['string', 'null']],
                        'company_scope' => ['type' => 'string', 'enum' => ['current', 'all']],
                    ],
                    'required' => ['id', 'code', 'company_scope'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function supports(string $name): bool
    {
        return in_array($name, ['search_payment_requests', 'summarize_payment_requests', 'get_payment_request'], true);
    }

    public function execute(string $name, array $arguments, User $user): array
    {
        if (! $this->pageAccess->canAccess($user, 'page.payment_requests')) {
            return [
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'Tài khoản không có quyền truy cập module Đề nghị thanh toán.',
                'result_count' => 0,
            ];
        }

        return match ($name) {
            'search_payment_requests' => $this->search($arguments, $user),
            'summarize_payment_requests' => $this->summarize($arguments, $user),
            'get_payment_request' => $this->detail($arguments, $user),
            default => ['ok' => false, 'error' => 'unknown_tool', 'result_count' => 0],
        };
    }

    private function search(array $arguments, User $user): array
    {
        $limit = max(1, min(
            (int) ($arguments['limit'] ?? 10),
            (int) config('ai_assistant.max_search_results', 20)
        ));

        $query = $this->baseQuery($arguments, $user)->with('creator:id,name');

        $items = $query
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $results = $items->map(function (PaymentRequest $item) {
            return [
                'id' => (int) $item->id,
                'code' => (string) $item->code,
                'receiver_name' => (string) $item->receiver_name,
                'creator_name' => (string) optional($item->creator)->name,
                'company' => (string) $item->company,
                'amount' => (int) $item->amount,
                'amount_formatted' => DisplayFormat::money($item->amount),
                'status' => (string) $item->status,
                'status_label' => self::STATUS_LABELS[$item->status] ?? (string) $item->status,
                'payment_due_date' => optional($item->payment_due_date)->format('Y-m-d'),
                'created_at' => optional($item->created_at)->format('Y-m-d H:i'),
                'reason' => Str::limit(trim(strip_tags((string) $item->reason)), 180),
                'url' => route('payment_requests.show', $item->id),
            ];
        })->values()->all();

        return [
            'ok' => true,
            'result_count' => count($results),
            'results' => $results,
            'actions' => collect($results)->map(fn (array $item) => [
                'label' => 'Mở '.$item['code'],
                'url' => $item['url'],
            ])->all(),
        ];
    }

    private function summarize(array $arguments, User $user): array
    {
        $query = $this->baseQuery($arguments, $user);

        $statusRows = (clone $query)
            ->selectRaw('status, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('status')
            ->get();

        $statusBreakdown = $statusRows->map(fn ($row) => [
            'status' => (string) $row->status,
            'label' => self::STATUS_LABELS[$row->status] ?? (string) $row->status,
            'count' => (int) $row->total_count,
            'amount' => (int) $row->total_amount,
            'amount_formatted' => DisplayFormat::money($row->total_amount),
        ])->values()->all();

        $totalCount = array_sum(array_column($statusBreakdown, 'count'));
        $totalAmount = array_sum(array_column($statusBreakdown, 'amount'));

        $paid = collect($statusBreakdown)->firstWhere('status', 'accounting_approved') ?? ['count' => 0, 'amount' => 0];

        $waitingRows = collect($statusBreakdown)->whereIn('status', ['submitted', 'admin_approved']);
        $waitingCount = (int) $waitingRows->sum('count');
        $waitingAmount = (int) $waitingRows->sum('amount');

        $overdueQuery = $this->baseQuery(array_merge($arguments, ['overdue_only' => true]), $user);
        $overdueCount = (clone $overdueQuery)->count();
        $overdueAmount = (int) (clone $overdueQuery)->sum('amount');

        return [
            'ok' => true,
            'result_count' => $totalCount,
            'summary' => [
                'total_count' => $totalCount,
                'total_amount' => $totalAmount,
                'total_amount_formatted' => DisplayFormat::money($totalAmount),
                'paid_count' => (int) $paid['count'],
                'paid_amount' => (int) $paid['amount'],
                'paid_amount_formatted' => DisplayFormat::money($paid['amount']),
                'waiting_count' => $waitingCount,
                'waiting_amount' => $waitingAmount,
                'waiting_amount_formatted' => DisplayFormat::money($waitingAmount),
                'overdue_count' => $overdueCount,
                'overdue_amount' => $overdueAmount,
                'overdue_amount_formatted' => DisplayFormat::money($overdueAmount),
                'status_breakdown' => $statusBreakdown,
            ],
        ];
    }

    private function detail(array $arguments, User $user): array
    {
        $query = $this->baseQuery([
            'keyword' => null,
            'status' => null,
            'date_from' => null,
            'date_to' => null,
            'creator_name' => null,
            'overdue_only' => false,
            'company_scope' => $arguments['company_scope'] ?? 'current',
        ], $user)->with('creator:id,name');

        $id = isset($arguments['id']) ? (int) $arguments['id'] : 0;
        $code = trim((string) ($arguments['code'] ?? ''));

        if ($id > 0) {
            $query->whereKey($id);
        } elseif ($code !== '') {
            $query->where('code', $code);
        } else {
            return [
                'ok' => false,
                'error' => 'validation_error',
                'message' => 'Cần cung cấp ID hoặc mã phiếu.',
                'result_count' => 0,
            ];
        }

        /** @var PaymentRequest|null $item */
        $item = $query->first();

        if (! $item) {
            return [
                'ok' => false,
                'error' => 'not_found',
                'message' => 'Không tìm thấy phiếu trong phạm vi được phép xem.',
                'result_count' => 0,
            ];
        }

        return [
            'ok' => true,
            'result_count' => 1,
            'payment_request' => [
                'id' => (int) $item->id,
                'code' => (string) $item->code,
                'doc_type' => (string) $item->doc_type,
                'receiver_name' => (string) $item->receiver_name,
                'creator_name' => (string) optional($item->creator)->name,
                'department' => (string) $item->department,
                'company' => (string) $item->company,
                'reason' => trim(strip_tags((string) $item->reason)),
                'payment_content' => trim(strip_tags((string) $item->payment_content)),
                'amount' => (int) $item->amount,
                'amount_formatted' => DisplayFormat::money($item->amount),
                'status' => (string) $item->status,
                'status_label' => self::STATUS_LABELS[$item->status] ?? (string) $item->status,
                'payment_due_date' => optional($item->payment_due_date)->format('Y-m-d'),
                'created_at' => optional($item->created_at)->format('Y-m-d H:i'),
                'url' => route('payment_requests.show', $item->id),
            ],
            'actions' => [[
                'label' => 'Mở '.$item->code,
                'url' => route('payment_requests.show', $item->id),
            ]],
        ];
    }

    private function baseQuery(array $arguments, User $user): Builder
    {
        $query = PaymentRequest::query();
        $canViewAll = $this->canViewAll($user);

        if (! $canViewAll) {
            $query->where('created_by', $user->id);
        }

        $requestedScope = (string) ($arguments['company_scope'] ?? 'current');
        $scope = ($requestedScope === 'all' && $canViewAll) ? 'all' : 'current';

        if ($scope === 'current') {
            $this->applyCurrentCompany($query);
        }

        $keyword = trim((string) ($arguments['keyword'] ?? ''));
        if ($keyword !== '') {
            $escaped = addcslashes($keyword, '%_\\');
            $query->where(function (Builder $sub) use ($escaped) {
                $like = '%'.$escaped.'%';
                $sub->where('code', 'like', $like)
                    ->orWhere('receiver_name', 'like', $like)
                    ->orWhere('reason', 'like', $like)
                    ->orWhere('payment_content', 'like', $like)
                    ->orWhere('company', 'like', $like)
                    ->orWhereHas('creator', fn (Builder $creator) => $creator->where('name', 'like', $like));
            });
        }

        $status = trim((string) ($arguments['status'] ?? ''));
        if (array_key_exists($status, self::STATUS_LABELS)) {
            $query->where('status', $status);
        } elseif ($status === 'waiting') {
            $query->whereIn('status', ['submitted', 'admin_approved']);
        } elseif ($status === 'rejected') {
            $query->whereIn('status', ['admin_rejected', 'accounting_rejected']);
        } elseif ($status === 'unpaid') {
            $query->whereNotIn('status', ['accounting_approved', 'admin_rejected', 'accounting_rejected']);
        }

        $dateFrom = $this->validDate($arguments['date_from'] ?? null);
        $dateTo = $this->validDate($arguments['date_to'] ?? null);

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $creatorName = trim((string) ($arguments['creator_name'] ?? ''));
        if ($creatorName !== '') {
            $escapedCreator = addcslashes($creatorName, '%_\\');
            $query->whereHas('creator', fn (Builder $creator) => $creator->where('name', 'like', '%'.$escapedCreator.'%'));
        }

        if ((bool) ($arguments['overdue_only'] ?? false)) {
            $query->whereNotNull('payment_due_date')
                ->whereDate('payment_due_date', '<', now()->toDateString())
                ->whereNotIn('status', ['accounting_approved', 'admin_rejected', 'accounting_rejected']);
        }

        return $query;
    }

    private function applyCurrentCompany(Builder $query): void
    {
        $companyId = EgoCompanyScope::currentId();
        if ($companyId <= 0) {
            return;
        }

        $companyNames = EgoCompanyScope::companyNames($companyId);

        if (SchemaCache::hasColumn('payment_requests', 'company_id')) {
            $query->where(function (Builder $companyQuery) use ($companyId, $companyNames) {
                $companyQuery->where('company_id', $companyId);

                if ($companyNames !== []) {
                    $companyQuery->orWhere(function (Builder $legacyQuery) use ($companyNames) {
                        $legacyQuery->whereNull('company_id')->whereIn('company', $companyNames);
                    });
                }
            });

            return;
        }

        if ($companyNames !== []) {
            $query->whereIn('company', $companyNames);
        }
    }

    private function canViewAll(User $user): bool
    {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'accounting', 'ketoan', 'ke_toan'])) {
            return true;
        }

        return str_contains(mb_strtolower((string) $user->email), 'ketoan');
    }

    private function validDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
