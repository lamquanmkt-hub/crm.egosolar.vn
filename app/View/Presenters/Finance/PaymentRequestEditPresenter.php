<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\PaymentRequestAttachmentRow;
use App\Models\Payments\PaymentAttachment;
use App\Models\Payments\PaymentRequest;
use App\Support\DisplayFormat;

/** Chuẩn bị trạng thái, ngày và chứng từ thay ba khối PHP của form sửa đề nghị thanh toán. */
final class PaymentRequestEditPresenter
{
    /** Tông màu đang dùng trong CSS của form; nhãn lấy từ controller để không lặp bản đồ nghiệp vụ. */
    private const STATUS_TONES = [
        'draft' => 'neutral',
        'submitted' => 'info',
        'admin_approved' => 'primary',
        'admin_rejected' => 'danger',
        'accounting_approved' => 'success',
        'accounting_rejected' => 'warning',
    ];

    /**
     * @param  iterable<PaymentAttachment>  $attachments  controller truyền quan hệ đã eager load, đúng thứ tự id
     * @param  array<string, string>  $statusLabels
     * @param  mixed  $oldDueValue  input cũ từ session; chuỗi rỗng vẫn ghi đè ngày đã lưu
     * @return array{statusLabel: string, statusTone: string, dueValue: mixed, createdAt: string, egoPrId: int, egoAttachments: list<PaymentRequestAttachmentRow>}
     */
    public function viewData(PaymentRequest $item, iterable $attachments, array $statusLabels, mixed $oldDueValue = null): array
    {
        $dueValue = $oldDueValue;
        // Model đã cast payment_due_date thành date; chỉ đọc khi không có input cũ, như form trước đây.
        if ($dueValue === null && ! empty($item->payment_due_date)) {
            $dueValue = DisplayFormat::date($item->payment_due_date, 'Y-m-d');
        }

        $requestId = (int) ($item->id ?? 0);
        $rows = [];
        foreach ($attachments as $attachment) {
            // Chứng từ cũ dùng dấu chấm thập phân/dấu phẩy nghìn (1,024.0 KB), khác DisplayFormat::number.
            $size = ! empty($attachment->size) ? number_format(((float) $attachment->size) / 1024, 1).' KB' : '';
            $rows[] = new PaymentRequestAttachmentRow(
                attachment: $attachment,
                fileName: ! empty($attachment->original_name) ? $attachment->original_name : basename($attachment->path ?? ''),
                fileMeta: ($attachment->mime_type ?? 'Tệp đính kèm').' '.($size ? ' - '.$size : ''),
                downloadPath: '/payment-requests/'.$requestId.'/attachments-thao/'.$attachment->id.'/download',
            );
        }

        return [
            'statusLabel' => $statusLabels[$item->status] ?? ($item->status ?? '-'),
            'statusTone' => self::STATUS_TONES[$item->status] ?? 'neutral',
            'dueValue' => $dueValue,
            'createdAt' => ! empty($item->created_at) ? DisplayFormat::date($item->created_at, 'd/m/Y H:i') : '-',
            'egoPrId' => $requestId,
            'egoAttachments' => $rows,
        ];
    }
}
