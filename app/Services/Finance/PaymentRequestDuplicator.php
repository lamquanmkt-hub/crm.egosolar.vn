<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Repositories\PaymentRequestRepositoryInterface;

/**
 * Sao chép một ĐNTT thành phiếu nháp mới.
 *
 * Quy tắc (giữ nguyên từ closure cũ trong `routes/finance.php`):
 * - Mã phiếu mới theo dạng `PR-<năm>-<5 số>`, lấy số kế tiếp của năm hiện tại.
 * - Bản sao quay về `draft` để người dùng sửa lại trước khi gửi duyệt.
 * - Người tạo là người đang đăng nhập, không phải người tạo phiếu gốc.
 * - Xoá sạch mọi dấu vết duyệt/từ chối/chi tiền của phiếu gốc.
 * - Tệp đính kèm được nhân bản sang phiếu mới.
 */
final class PaymentRequestDuplicator
{
    /**
     * Các cột phải xoá trắng trên bản sao — dấu vết xử lý của phiếu gốc.
     *
     * @var list<string>
     */
    private const APPROVAL_TRAIL_COLUMNS = [
        'approved_by',
        'approved_at',
        'admin_approved_by',
        'admin_approved_at',
        'accounting_approved_by',
        'accounting_approved_at',
        'rejected_by',
        'rejected_at',
        'admin_note',
        'accounting_note',
        'reject_reason',
        'paid_at',
        'paid_by',
        'deleted_at',
    ];

    public function __construct(
        private readonly PaymentRequestRepositoryInterface $paymentRequests,
    ) {}

    /**
     * Tạo bản sao, trả về id phiếu mới.
     *
     * @param  int  $sourceId  Phiếu gốc (phải tồn tại — người gọi tự kiểm tra)
     * @param  int|null  $creatorId  Người đang đăng nhập
     */
    public function duplicate(object $source, ?int $creatorId): int
    {
        $columns = $this->paymentRequests->columns();
        $data = (array) $source;

        unset($data['id']);

        $data = $this->withNewCode($data, $columns);
        $data = $this->withFilledReason($data, $columns);

        if (in_array('created_by', $columns, true)) {
            $data['created_by'] = $creatorId;
        }

        if (in_array('status', $columns, true)) {
            $data['status'] = 'draft';
        }

        foreach (self::APPROVAL_TRAIL_COLUMNS as $column) {
            if (in_array($column, $columns, true)) {
                $data[$column] = null;
            }
        }

        foreach (['created_at', 'updated_at'] as $column) {
            if (in_array($column, $columns, true)) {
                $data[$column] = now();
            }
        }

        $newId = $this->paymentRequests->insert($data);

        $this->paymentRequests->copyAttachments((int) $source->id, $newId);

        return $newId;
    }

    /**
     * Cấp mã phiếu kế tiếp của năm hiện tại.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function withNewCode(array $data, array $columns): array
    {
        if (! in_array('code', $columns, true)) {
            return $data;
        }

        $prefix = 'PR-'.now()->format('Y').'-';
        $next = $this->paymentRequests->nextCodeSequence($prefix);

        $data['code'] = $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);

        return $data;
    }

    /**
     * Bảo đảm phiếu mới luôn có nội dung/lý do.
     *
     * Hai cột `reason` và `payment_content` cùng mô tả một việc nhưng dữ liệu cũ
     * lúc điền cột này lúc điền cột kia; bản sao lấp chỗ trống từ cột còn lại để
     * phiếu mới không hiện trắng.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function withFilledReason(array $data, array $columns): array
    {
        $fallback = 'Thanh toán theo đề nghị';

        if (in_array('reason', $columns, true) && empty($data['reason'])) {
            $data['reason'] = $data['payment_content'] ?? $data['receiver_name'] ?? $fallback;
        }

        if (in_array('payment_content', $columns, true) && empty($data['payment_content'])) {
            $data['payment_content'] = $data['reason'] ?? $fallback;
        }

        return $data;
    }
}
