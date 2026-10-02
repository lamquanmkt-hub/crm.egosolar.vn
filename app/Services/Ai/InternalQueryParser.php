<?php

namespace App\Services\Ai;

use Carbon\Carbon;
use Illuminate\Support\Str;

class InternalQueryParser
{
    public function parse(string $message): array
    {
        $original = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
        $normalized = $this->normalize($original);
        $module = $this->detectModule($original, $normalized);

        return match ($module) {
            'tasks' => $this->parseTasks($original, $normalized),
            'payment_requests' => $this->parsePaymentRequests($original, $normalized),
            default => [
                'module' => 'unsupported',
                'intent' => 'help',
                'tool' => null,
                'arguments' => [],
                'filters' => [],
            ],
        };
    }

    private function detectModule(string $original, string $normalized): string
    {
        if ($this->extractPaymentCode($original) !== null || $this->containsAny($normalized, [
            'de nghi thanh toan', 'phieu thanh toan', 'thanh toan', 'ung luong',
            'chi tien', 'ke toan da chi', 'nguoi nhan tien',
        ])) {
            return 'payment_requests';
        }

        if ($this->containsAny($normalized, [
            'cong viec', 'task', 'viec duoc giao', 'viec cua toi', 'viec chua lam',
            'viec can lam', 'viec qua han', 'viec dang lam', 'viec da nop',
        ])) {
            return 'tasks';
        }

        return 'unsupported';
    }

    private function parsePaymentRequests(string $original, string $normalized): array
    {
        $code = $this->extractPaymentCode($original);
        $intent = $code !== null
            ? 'detail'
            : ($this->isSummaryIntent($normalized) ? 'summary' : 'search');

        [$dateFrom, $dateTo, $dateLabel] = $this->extractDateRange($normalized);
        $status = $this->extractPaymentStatus($normalized);
        $overdueOnly = str_contains($normalized, 'qua han');
        $companyScope = $this->containsAny($normalized, [
            'tat ca cong ty', 'toan bo cong ty', 'ca hai cong ty', 'ca 2 cong ty',
            'khong gioi han cong ty', 'tat ca don vi',
        ]) ? 'all' : 'current';

        $keyword = $code === null
            ? $this->extractPaymentKeyword($original, $intent)
            : null;

        return [
            'module' => 'payment_requests',
            'intent' => $intent,
            'tool' => match ($intent) {
                'detail' => 'get_payment_request',
                'summary' => 'summarize_payment_requests',
                default => 'search_payment_requests',
            },
            'arguments' => [
                'id' => null,
                'code' => $code,
                'keyword' => $keyword,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'creator_name' => null,
                'overdue_only' => $overdueOnly,
                'company_scope' => $companyScope,
                'limit' => $this->resultLimit(),
            ],
            'filters' => [
                'date_label' => $dateLabel,
                'status_label' => $this->paymentStatusLabel($status),
                'overdue_only' => $overdueOnly,
                'company_scope' => $companyScope,
                'keyword' => $keyword,
                'code' => $code,
            ],
        ];
    }

    private function parseTasks(string $original, string $normalized): array
    {
        $intent = $this->isSummaryIntent($normalized) ? 'summary' : 'search';
        [$dateFrom, $dateTo, $dateLabel] = $this->extractDateRange($normalized);

        $status = $this->extractTaskStatus($normalized);
        $statusGroup = $this->extractTaskStatusGroup($normalized, $status);
        $overdueOnly = str_contains($normalized, 'qua han');
        $assigneeScope = $this->containsAny($normalized, [
            'tat ca nhan vien', 'toan bo nhan vien', 'tat ca cong viec', 'toan bo cong viec',
            'cua moi nguoi', 'tat ca phong ban',
        ]) ? 'all' : 'me';

        $keyword = $this->extractTaskKeyword($original, $intent);

        return [
            'module' => 'tasks',
            'intent' => $intent,
            'tool' => $intent === 'summary' ? 'summarize_tasks' : 'search_tasks',
            'arguments' => [
                'keyword' => $keyword,
                'status' => $status,
                'status_group' => $statusGroup,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'overdue_only' => $overdueOnly,
                'assignee_scope' => $assigneeScope,
                'limit' => $this->resultLimit(),
            ],
            'filters' => [
                'date_label' => $dateLabel,
                'status_label' => $this->taskFilterLabel($status, $statusGroup),
                'overdue_only' => $overdueOnly,
                'assignee_scope' => $assigneeScope,
                'keyword' => $keyword,
            ],
        ];
    }

    private function extractPaymentCode(string $message): ?string
    {
        if (preg_match('/\bPR[-\s]?\d{4}[-\s]?\d{3,}\b/i', $message, $matches) !== 1) {
            return null;
        }

        $code = strtoupper(preg_replace('/\s+/', '-', $matches[0]) ?? $matches[0]);
        $parts = array_values(array_filter(explode('-', $code), fn ($part) => $part !== ''));

        return count($parts) >= 3 ? implode('-', $parts) : $code;
    }

    private function extractPaymentStatus(string $normalized): ?string
    {
        $rules = [
            'accounting_rejected' => ['ke toan tu choi'],
            'admin_rejected' => ['giam doc tu choi', 'quan ly tai chinh tu choi', 'quan ly tu choi'],
            'rejected' => ['bi tu choi', 'da tu choi', 'tu choi'],
            'accounting_approved' => ['ke toan da chi', 'da chi tien', 'da chi', 'da thanh toan', 'hoan tat thanh toan'],
            'admin_approved' => ['cho ke toan', 'doi ke toan', 'giam doc da duyet', 'quan ly tai chinh da duyet', 'quan ly da duyet'],
            'submitted' => ['da gui duyet', 'cho giam doc', 'doi giam doc'],
            'waiting' => ['cho duyet', 'dang cho duyet', 'dang duyet', 'cho xu ly', 'dang cho xu ly', 'dang xu ly'],
            'draft' => ['ban nhap', 'phieu nhap', 'dang nhap'],
            'unpaid' => ['chua chi', 'chua thanh toan'],
        ];

        foreach ($rules as $status => $phrases) {
            if ($this->containsAny($normalized, $phrases)) {
                return $status;
            }
        }

        return null;
    }

    private function extractTaskStatus(string $normalized): ?string
    {
        $rules = [
            'revision' => ['can sua', 'can bo sung', 'tra lai sua', 'yeu cau sua'],
            'rejected' => ['bi tu choi', 'da tu choi', 'task tu choi'],
            'approved' => ['da duyet', 'da hoan thanh', 'hoan thanh roi', 'da xong'],
            'submitted' => ['da nop', 'cho duyet ket qua', 'dang cho duyet ket qua'],
            'in_progress' => ['dang lam', 'dang thuc hien', 'dang xu ly'],
            'new' => ['moi giao', 'chua bat dau'],
        ];

        foreach ($rules as $status => $phrases) {
            if ($this->containsAny($normalized, $phrases)) {
                return $status;
            }
        }

        return null;
    }

    private function extractTaskStatusGroup(string $normalized, ?string $status): ?string
    {
        if ($status !== null) {
            return null;
        }

        if ($this->containsAny($normalized, [
            'chua lam', 'viec can lam', 'can xu ly', 'toi chua lam', 'chua thuc hien',
        ])) {
            return 'action_required';
        }

        if ($this->containsAny($normalized, [
            'chua hoan thanh', 'chua xong', 'con viec', 'viec con lai', 'dang mo',
        ])) {
            return 'unfinished';
        }

        return null;
    }

    private function extractDateRange(string $normalized): array
    {
        $today = Carbon::today();

        if (preg_match('/(?:tu\s+ngay\s+)?(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{4}))?\s+(?:den|toi)\s+(?:ngay\s+)?(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{4}))?/', $normalized, $m) === 1) {
            $fromYear = (int) (($m[3] ?? '') ?: $today->year);
            $toYear = (int) (($m[6] ?? '') ?: $fromYear);
            $from = $this->safeDate($fromYear, (int) $m[2], (int) $m[1]);
            $to = $this->safeDate($toYear, (int) $m[5], (int) $m[4]);
            if ($from && $to) {
                return [$from, $to, sprintf('từ %s đến %s', Carbon::parse($from)->format('d/m/Y'), Carbon::parse($to)->format('d/m/Y'))];
            }
        }

        if ($this->containsAny($normalized, ['hom nay', 'trong ngay nay'])) {
            $date = $today->toDateString();

            return [$date, $date, 'hôm nay'];
        }
        if (str_contains($normalized, 'hom qua')) {
            $date = $today->copy()->subDay()->toDateString();

            return [$date, $date, 'hôm qua'];
        }
        if (str_contains($normalized, 'tuan truoc')) {
            $from = $today->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
            $to = $today->copy()->subWeek()->endOfWeek(Carbon::SUNDAY);

            return [$from->toDateString(), $to->toDateString(), 'tuần trước'];
        }
        if (str_contains($normalized, 'tuan nay')) {
            return [$today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(), $today->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(), 'tuần này'];
        }
        if (str_contains($normalized, 'thang truoc')) {
            $date = $today->copy()->subMonthNoOverflow();

            return [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString(), 'tháng trước'];
        }
        if (str_contains($normalized, 'thang nay')) {
            return [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString(), 'tháng này'];
        }
        if (str_contains($normalized, 'quy truoc')) {
            $date = $today->copy()->subQuarter();

            return [$date->copy()->startOfQuarter()->toDateString(), $date->copy()->endOfQuarter()->toDateString(), 'quý trước'];
        }
        if (str_contains($normalized, 'quy nay')) {
            return [$today->copy()->startOfQuarter()->toDateString(), $today->copy()->endOfQuarter()->toDateString(), 'quý này'];
        }
        if (preg_match('/quy\s*([1-4])(?:\s*(?:\/|nam)\s*(\d{4}))?/', $normalized, $m) === 1) {
            $quarter = (int) $m[1];
            $year = (int) (($m[2] ?? '') ?: $today->year);
            $from = Carbon::create($year, (($quarter - 1) * 3) + 1, 1)->startOfMonth();
            $to = $from->copy()->addMonths(2)->endOfMonth();

            return [$from->toDateString(), $to->toDateString(), "quý {$quarter}/{$year}"];
        }
        if (preg_match('/thang\s*(\d{1,2})(?:\s*(?:\/|nam)\s*(\d{4}))?/', $normalized, $m) === 1) {
            $month = (int) $m[1];
            $year = (int) (($m[2] ?? '') ?: $today->year);
            if ($month >= 1 && $month <= 12) {
                $date = Carbon::create($year, $month, 1);

                return [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString(), "tháng {$month}/{$year}"];
            }
        }
        if (str_contains($normalized, 'nam nay')) {
            return [$today->copy()->startOfYear()->toDateString(), $today->copy()->endOfYear()->toDateString(), 'năm nay'];
        }
        if (preg_match('/nam\s*(20\d{2})/', $normalized, $m) === 1) {
            $year = (int) $m[1];

            return ["{$year}-01-01", "{$year}-12-31", "năm {$year}"];
        }
        if (preg_match('/(\d{1,3})\s*ngay\s*(?:qua|gan day|tro lai day)/', $normalized, $m) === 1) {
            $days = max(1, min((int) $m[1], 365));

            return [$today->copy()->subDays($days - 1)->toDateString(), $today->toDateString(), "{$days} ngày gần đây"];
        }
        if (preg_match('/(?:ngay\s*)?(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $normalized, $m) === 1) {
            $date = $this->safeDate((int) $m[3], (int) $m[2], (int) $m[1]);
            if ($date) {
                return [$date, $date, Carbon::parse($date)->format('d/m/Y')];
            }
        }

        return [null, null, null];
    }

    private function extractPaymentKeyword(string $original, string $intent): ?string
    {
        if (preg_match('/[“"\']([^”"\']{2,100})[”"\']/u', $original, $m) === 1) {
            return trim($m[1]);
        }

        $patterns = [
            '/(?:người nhận|nguoi nhan|nhận tiền|nhan tien)\s+(?:là\s+)?(.+?)(?=\s+(?:trong|tháng|thang|tuần|tuan|năm|nam|từ|tu|đến|den|quá hạn|qua han|đang|dang|đã|da|chưa|chua)\b|$)/iu',
            '/(?:lý do|ly do|nội dung|noi dung)\s+(.+?)(?=\s+(?:trong|tháng|thang|tuần|tuan|năm|nam|từ|tu|đến|den|quá hạn|qua han|đang|dang|đã|da|chưa|chua)\b|$)/iu',
            '/(?:của|cua)\s+([\p{L}\p{N}.&()\-\s]{2,80}?)(?=\s+(?:trong|tháng|thang|tuần|tuan|năm|nam|từ|tu|đến|den|quá hạn|qua han|đang|dang|đã|da|chưa|chua)\b|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $original, $m) === 1) {
                $candidate = $this->cleanKeyword($m[1]);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        if ($intent === 'summary') {
            return null;
        }

        $cleaned = preg_replace([
            '/\b(tất cả công ty|toàn bộ công ty|cả hai công ty|cả 2 công ty|cho tôi|giúp tôi|vui lòng|hãy|tìm kiếm|tìm|tra cứu|mở|xem|danh sách|liệt kê|các|những|phiếu|mã phiếu|đề nghị thanh toán|thanh toán|công ty|người nhận|người tạo)\b/iu',
            '/\b(hôm nay|hôm qua|tuần này|tuần trước|tháng này|tháng trước|quý này|quý trước|năm nay)\b/iu',
            '/\b(tháng|quý|năm)\s*\d{1,4}(?:\s*(?:\/|năm)\s*\d{4})?/iu',
            '/\b(đang chờ duyệt|chờ duyệt|đã gửi duyệt|chờ kế toán|đợi kế toán|đã duyệt|đã chi|đã thanh toán|chưa chi|chưa thanh toán|quá hạn|nháp|bị từ chối|từ chối|chờ xử lý|đang xử lý)\b/iu',
            '/\b(trong|ở|vào|theo|đang|đã|chưa|có|nào|không|cần|muốn|của|tất cả|toàn bộ)\b/iu',
            '/\s+/u',
        ], [' ', ' ', ' ', ' ', ' ', ' '], $original);

        return $this->cleanKeyword((string) $cleaned);
    }

    private function extractTaskKeyword(string $original, string $intent): ?string
    {
        if (preg_match('/[“"\']([^”"\']{2,100})[”"\']/u', $original, $m) === 1) {
            return trim($m[1]);
        }

        if ($intent === 'summary') {
            return null;
        }

        $cleaned = preg_replace([
            '/\b(cho tôi|giúp tôi|vui lòng|hãy|tìm kiếm|tìm|tra cứu|mở|xem|danh sách|liệt kê|các|những|công việc|task|việc được giao|việc của tôi|của tôi|tôi)\b/iu',
            '/\b(hôm nay|hôm qua|tuần này|tuần trước|tháng này|tháng trước|quý này|quý trước|năm nay)\b/iu',
            '/\b(tháng|quý|năm)\s*\d{1,4}(?:\s*(?:\/|năm)\s*\d{4})?/iu',
            '/\b(chưa làm|chưa hoàn thành|chưa xong|cần làm|còn lại|đang làm|đã nộp|đã duyệt|đã hoàn thành|quá hạn|cần sửa|bị từ chối|mới giao|chưa bắt đầu)\b/iu',
            '/\b(trong|ở|vào|theo|đang|đã|chưa|có|nào|không|cần|muốn|của|tất cả|toàn bộ)\b/iu',
            '/\s+/u',
        ], [' ', ' ', ' ', ' ', ' ', ' '], $original);

        return $this->cleanKeyword((string) $cleaned);
    }

    private function cleanKeyword(string $value): ?string
    {
        $value = trim($value, " \t\n\r\0\x0B,.;:?!-");
        if ($value === '' || mb_strlen($value) < 2) {
            return null;
        }

        return Str::limit($value, 100, '');
    }

    private function isSummaryIntent(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'tong hop', 'thong ke', 'bao cao', 'bao nhieu', 'tong tien',
            'tong gia tri', 'so luong', 'tinh hinh',
        ]);
    }

    private function resultLimit(): int
    {
        return max(1, min((int) config('ai_assistant.max_search_results', 20), 20));
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(Str::ascii($value))) ?? '');
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function safeDate(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day)->toDateString();
    }

    private function paymentStatusLabel(?string $status): ?string
    {
        return [
            'draft' => 'Nháp',
            'submitted' => 'Đã gửi duyệt',
            'admin_approved' => 'Đã duyệt, chờ kế toán',
            'admin_rejected' => 'Quản lý từ chối',
            'accounting_approved' => 'Kế toán đã chi',
            'accounting_rejected' => 'Kế toán từ chối',
            'waiting' => 'Đang chờ xử lý',
            'rejected' => 'Đã bị từ chối',
            'unpaid' => 'Chưa chi',
        ][$status] ?? null;
    }

    private function taskFilterLabel(?string $status, ?string $statusGroup): ?string
    {
        if ($status !== null) {
            return [
                'new' => 'Mới giao',
                'in_progress' => 'Đang làm',
                'submitted' => 'Đã nộp, chờ duyệt',
                'revision' => 'Cần sửa / bổ sung',
                'rejected' => 'Bị từ chối',
                'approved' => 'Đã duyệt',
            ][$status] ?? $status;
        }

        return [
            'action_required' => 'Cần thực hiện',
            'unfinished' => 'Chưa hoàn thành',
        ][$statusGroup] ?? null;
    }
}
