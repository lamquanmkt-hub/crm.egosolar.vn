<?php

namespace App\Services\Ai;

use App\Models\Ai\AiConversation;
use App\Models\Ai\AiToolLog;
use App\Models\User;
use App\Services\Ai\Tools\PaymentRequestTool;
use App\Services\Ai\Tools\TaskTool;
use Illuminate\Support\Arr;
use Throwable;

class AiAssistantService
{
    public function __construct(
        private readonly InternalQueryParser $parser,
        private readonly PaymentRequestTool $paymentRequestTool,
        private readonly TaskTool $taskTool,
    ) {}

    public function respond(AiConversation $conversation, User $user): array
    {
        if (! config('ai_assistant.enabled', true)) {
            return $this->result('Tìm kiếm thông minh đang bị tắt trong cấu hình hệ thống.');
        }

        $message = (string) $conversation->messages()
            ->where('role', 'user')
            ->latest('id')
            ->value('content');

        $parsed = $this->parser->parse($message);
        $module = (string) ($parsed['module'] ?? 'unsupported');

        if ($module === 'unsupported') {
            return $this->result(
                "Tôi chưa xác định được anh đang muốn tìm module nào.\n"
                ."Hiện hỗ trợ:\n"
                ."• Đề nghị thanh toán: mã phiếu, trạng thái, thời gian, người nhận, tổng tiền.\n"
                ."• Công việc: việc của tôi, việc chưa làm, đang làm, chờ duyệt hoặc quá hạn.\n\n"
                .'Ví dụ: “Có công việc nào tôi chưa làm không?” hoặc “Phiếu thanh toán đang chờ duyệt tháng này”.'
            );
        }

        $toolName = (string) $parsed['tool'];
        $arguments = (array) $parsed['arguments'];
        $startedAt = hrtime(true);
        $status = 'success';
        $errorMessage = null;

        try {
            $toolResult = match ($module) {
                'tasks' => $this->taskTool->execute($toolName, $arguments, $user),
                default => $this->paymentRequestTool->execute($toolName, $arguments, $user),
            };

            if (! ($toolResult['ok'] ?? false)) {
                $status = 'error';
                $errorMessage = (string) ($toolResult['message'] ?? $toolResult['error'] ?? 'Không thể truy vấn dữ liệu.');
            }
        } catch (Throwable $e) {
            $status = 'error';
            $errorMessage = $e->getMessage();
            $toolResult = [
                'ok' => false,
                'message' => $module === 'tasks'
                    ? 'Không thể truy vấn dữ liệu Công việc lúc này.'
                    : 'Không thể truy vấn dữ liệu Đề nghị thanh toán lúc này.',
                'result_count' => 0,
            ];
            report($e);
        }

        AiToolLog::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'company_id' => session('active_company_id') ?: null,
            'tool_name' => 'internal_'.$toolName,
            'arguments' => Arr::except($arguments, ['id']),
            'result_count' => (int) ($toolResult['result_count'] ?? 0),
            'duration_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        if (! ($toolResult['ok'] ?? false)) {
            return $this->result((string) ($toolResult['message'] ?? 'Không tìm thấy dữ liệu phù hợp.'));
        }

        return $this->result(
            $this->formatAnswer(
                $module,
                (string) $parsed['intent'],
                $toolResult,
                (array) $parsed['filters']
            ),
            (array) ($toolResult['actions'] ?? [])
        );
    }

    private function formatAnswer(string $module, string $intent, array $data, array $filters): string
    {
        if ($module === 'tasks') {
            return $intent === 'summary'
                ? $this->formatTaskSummary($data, $filters)
                : $this->formatTaskSearch($data, $filters);
        }

        return match ($intent) {
            'detail' => $this->formatPaymentDetail($data),
            'summary' => $this->formatPaymentSummary($data, $filters),
            default => $this->formatPaymentSearch($data, $filters),
        };
    }

    private function formatPaymentDetail(array $data): string
    {
        $item = (array) ($data['payment_request'] ?? []);
        if ($item === []) {
            return 'Không tìm thấy phiếu trong phạm vi anh/chị được phép xem.';
        }

        $lines = [
            ($item['code'] ?? 'Phiếu thanh toán').' — '.($item['status_label'] ?? $item['status'] ?? 'Chưa rõ trạng thái'),
            'Người nhận: '.($item['receiver_name'] ?: 'Chưa cập nhật'),
            'Số tiền: '.($item['amount_formatted'] ?? '0 đ'),
            'Công ty: '.($item['company'] ?: 'Chưa cập nhật'),
            'Người tạo: '.($item['creator_name'] ?: 'Chưa cập nhật'),
            'Hạn thanh toán: '.$this->displayDate($item['payment_due_date'] ?? null),
        ];

        if (! empty($item['reason'])) {
            $lines[] = 'Lý do: '.$item['reason'];
        }
        if (! empty($item['payment_content'])) {
            $lines[] = 'Nội dung: '.$item['payment_content'];
        }

        return implode("\n", $lines);
    }

    private function formatPaymentSummary(array $data, array $filters): string
    {
        $summary = (array) ($data['summary'] ?? []);
        $scope = $this->paymentScopeDescription($filters);

        $lines = [
            'Tổng hợp Đề nghị thanh toán'.($scope !== '' ? ' — '.$scope : '').':',
            '• Tổng số: '.number_format((int) ($summary['total_count'] ?? 0), 0, ',', '.').' phiếu',
            '• Tổng giá trị: '.($summary['total_amount_formatted'] ?? '0 đ'),
            '• Đã chi: '.number_format((int) ($summary['paid_count'] ?? 0), 0, ',', '.').' phiếu — '.($summary['paid_amount_formatted'] ?? '0 đ'),
            '• Đang chờ: '.number_format((int) ($summary['waiting_count'] ?? 0), 0, ',', '.').' phiếu — '.($summary['waiting_amount_formatted'] ?? '0 đ'),
            '• Quá hạn chưa xử lý: '.number_format((int) ($summary['overdue_count'] ?? 0), 0, ',', '.').' phiếu — '.($summary['overdue_amount_formatted'] ?? '0 đ'),
        ];

        $breakdown = array_values(array_filter((array) ($summary['status_breakdown'] ?? []), fn ($row) => (int) ($row['count'] ?? 0) > 0));
        if ($breakdown !== []) {
            $lines[] = '';
            $lines[] = 'Theo trạng thái:';
            foreach ($breakdown as $row) {
                $lines[] = '• '.($row['label'] ?? $row['status'] ?? 'Khác').': '.number_format((int) ($row['count'] ?? 0), 0, ',', '.').' phiếu — '.($row['amount_formatted'] ?? '0 đ');
            }
        }

        return implode("\n", $lines);
    }

    private function formatPaymentSearch(array $data, array $filters): string
    {
        $items = (array) ($data['results'] ?? []);
        $count = (int) ($data['result_count'] ?? count($items));
        $scope = $this->paymentScopeDescription($filters);

        if ($count === 0) {
            return 'Không có Đề nghị thanh toán phù hợp'.($scope !== '' ? ' với bộ lọc '.$scope : '').'.\n'
                .'Lưu ý: tìm kiếm đang áp dụng đúng công ty hiện được chọn. Có thể thử thêm “tất cả công ty” nếu tài khoản có quyền.';
        }

        $maxItems = max(1, (int) config('ai_assistant.max_list_items_in_answer', 8));
        $shown = array_slice($items, 0, $maxItems);
        $lines = [
            'Tìm thấy '.number_format($count, 0, ',', '.').' phiếu'.($scope !== '' ? ' — '.$scope : '').':',
        ];

        foreach ($shown as $index => $item) {
            $lines[] = ($index + 1).'. '.($item['code'] ?? 'Không có mã')
                .' — '.($item['receiver_name'] ?: 'Chưa có người nhận')
                .' — '.($item['amount_formatted'] ?? '0 đ');
            $lines[] = '   '.($item['status_label'] ?? $item['status'] ?? 'Chưa rõ trạng thái')
                .' | Hạn: '.$this->displayDate($item['payment_due_date'] ?? null)
                .' | Tạo: '.$this->displayDateTime($item['created_at'] ?? null);
            if (! empty($item['reason'])) {
                $lines[] = '   Lý do: '.$item['reason'];
            }
        }

        if ($count > count($shown)) {
            $lines[] = 'Đang hiển thị '.count($shown).'/'.$count.' kết quả gần nhất.';
        }

        return implode("\n", $lines);
    }

    private function formatTaskSummary(array $data, array $filters): string
    {
        $summary = (array) ($data['summary'] ?? []);
        $scope = $this->taskScopeDescription($filters);

        $lines = [
            'Tổng hợp Công việc'.($scope !== '' ? ' — '.$scope : '').':',
            '• Tổng số: '.number_format((int) ($summary['total_count'] ?? 0), 0, ',', '.').' việc',
            '• Cần thực hiện: '.number_format((int) ($summary['action_required_count'] ?? 0), 0, ',', '.').' việc',
            '• Đã nộp, chờ duyệt: '.number_format((int) ($summary['submitted_count'] ?? 0), 0, ',', '.').' việc',
            '• Đã duyệt: '.number_format((int) ($summary['approved_count'] ?? 0), 0, ',', '.').' việc',
            '• Quá hạn: '.number_format((int) ($summary['overdue_count'] ?? 0), 0, ',', '.').' việc',
        ];

        return implode("\n", $lines);
    }

    private function formatTaskSearch(array $data, array $filters): string
    {
        $items = (array) ($data['results'] ?? []);
        $count = (int) ($data['result_count'] ?? count($items));
        $scope = $this->taskScopeDescription($filters);

        if ($count === 0) {
            return 'Không có công việc phù hợp'.($scope !== '' ? ' với bộ lọc '.$scope : '').'.';
        }

        $maxItems = max(1, (int) config('ai_assistant.max_list_items_in_answer', 8));
        $shown = array_slice($items, 0, $maxItems);
        $lines = [
            'Có '.number_format($count, 0, ',', '.').' công việc'.($scope !== '' ? ' — '.$scope : '').':',
        ];

        foreach ($shown as $index => $item) {
            $overdue = ! empty($item['is_overdue']) ? ' • QUÁ HẠN' : '';
            $lines[] = ($index + 1).'. #'.($item['id'] ?? '-').' — '.($item['title'] ?: 'Chưa có tiêu đề');
            $lines[] = '   '.($item['status_label'] ?? $item['status'] ?? 'Chưa rõ')
                .' | Ưu tiên: '.($item['priority_label'] ?? $item['priority'] ?? 'Chưa rõ')
                .' | Hạn: '.$this->displayDateTime($item['due_at'] ?? null).$overdue;
            if (! empty($item['requester_name'])) {
                $lines[] = '   Người giao: '.$item['requester_name'];
            }
        }

        if ($count > count($shown)) {
            $lines[] = 'Đang hiển thị '.count($shown).'/'.$count.' kết quả.';
        }

        return implode("\n", $lines);
    }

    private function paymentScopeDescription(array $filters): string
    {
        $parts = [];
        if (! empty($filters['keyword'])) {
            $parts[] = 'từ khóa “'.$filters['keyword'].'”';
        }
        if (! empty($filters['status_label'])) {
            $parts[] = 'trạng thái '.$filters['status_label'];
        }
        if (! empty($filters['date_label'])) {
            $parts[] = $filters['date_label'];
        }
        if (! empty($filters['overdue_only'])) {
            $parts[] = 'chỉ phiếu quá hạn';
        }
        $parts[] = ($filters['company_scope'] ?? 'current') === 'all'
            ? 'tất cả công ty được phép xem'
            : 'công ty đang chọn';

        return implode(', ', $parts);
    }

    private function taskScopeDescription(array $filters): string
    {
        $parts = [];
        if (! empty($filters['keyword'])) {
            $parts[] = 'từ khóa “'.$filters['keyword'].'”';
        }
        if (! empty($filters['status_label'])) {
            $parts[] = $filters['status_label'];
        }
        if (! empty($filters['date_label'])) {
            $parts[] = 'hạn '.$filters['date_label'];
        }
        if (! empty($filters['overdue_only'])) {
            $parts[] = 'quá hạn';
        }
        $parts[] = ($filters['assignee_scope'] ?? 'me') === 'all'
            ? 'tất cả nhân sự được phép xem'
            : 'công việc được giao cho tôi';

        return implode(', ', $parts);
    }

    private function displayDate(mixed $value): string
    {
        if (! $value) {
            return 'Chưa đặt';
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('d/m/Y');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function displayDateTime(mixed $value): string
    {
        if (! $value) {
            return 'Chưa đặt';
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('d/m/Y H:i');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function result(string $answer, array $actions = []): array
    {
        return [
            'answer' => $answer,
            'actions' => array_slice($actions, 0, 8),
            'model' => (string) config('ai_assistant.engine', 'internal-smart-search-v2'),
            'usage' => [
                'input_tokens' => 0,
                'output_tokens' => 0,
            ],
        ];
    }
}
