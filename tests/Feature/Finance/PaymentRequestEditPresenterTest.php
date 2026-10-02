<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\PaymentRequestAttachmentRow;
use App\Models\Payments\PaymentAttachment;
use App\Models\Payments\PaymentRequest;
use App\View\Presenters\Finance\PaymentRequestEditPresenter;
use Tests\TestCase;

/** Guard dữ liệu form và các nhánh null/0 mà schema thật không luôn cho phép seed. */
final class PaymentRequestEditPresenterTest extends TestCase
{
    public function test_nhan_du_phong_va_ngay_nhap_lai(): void
    {
        $presenter = new PaymentRequestEditPresenter;
        $item = (new PaymentRequest)->forceFill([
            'id' => 992101, 'status' => 'unknown', 'payment_due_date' => '2026-09-15',
            'created_at' => '2026-09-10 08:30:00',
        ]);
        $data = $presenter->viewData($item, [], []);
        $this->assertSame('unknown', $data['statusLabel']);
        $this->assertSame('neutral', $data['statusTone']);
        $this->assertSame('2026-09-15', $data['dueValue']);
        $this->assertSame('10/09/2026 08:30', $data['createdAt']);
        $this->assertSame(992101, $data['egoPrId']);
        foreach (['2026-10-01', '', '0'] as $old) {
            $this->assertSame($old, $presenter->viewData($item, [], [], $old)['dueValue']);
        }
        $item->forceFill(['status' => null, 'payment_due_date' => null, 'created_at' => null, 'id' => null]);
        $this->assertSame([
            'statusLabel' => '-', 'statusTone' => 'neutral', 'dueValue' => null,
            'createdAt' => '-', 'egoPrId' => 0, 'egoAttachments' => [],
        ], $presenter->viewData($item, [], []));
        $item->status = 'admin_approved';
        $this->assertSame('Nhãn từ controller', $presenter->viewData($item, [], ['admin_approved' => 'Nhãn từ controller'])['statusLabel']);
        $this->assertFalse($item->relationLoaded('attachments'), 'Presenter không lazy load chứng từ.');
    }

    public function test_chung_tu_giu_ten_metadata_thu_tu_va_duong_dan_cu(): void
    {
        $item = (new PaymentRequest)->forceFill(['id' => 992101]);
        $attachments = [];
        foreach ([
            ['original_name' => 'Báo giá <A>.pdf', 'path' => 'docs/unused.pdf', 'mime_type' => 'application/pdf', 'size' => 1048576],
            ['original_name' => '', 'path' => 'docs/fallback.pdf', 'mime_type' => null, 'size' => 0],
            ['original_name' => '0', 'path' => 'docs/zero.pdf', 'mime_type' => '', 'size' => null],
            ['original_name' => null, 'path' => null, 'mime_type' => null, 'size' => 1],
        ] as $index => $attributes) {
            $attachments[] = (new PaymentAttachment)->forceFill(['id' => 993110 - $index] + $attributes);
        }
        $rows = (new PaymentRequestEditPresenter)->viewData($item, $attachments, [])['egoAttachments'];
        $this->assertContainsOnlyInstancesOf(PaymentRequestAttachmentRow::class, $rows);
        $this->assertSame(['Báo giá <A>.pdf', 'fallback.pdf', 'zero.pdf', ''], array_column($rows, 'fileName'));
        $this->assertSame(['application/pdf  - 1,024.0 KB', 'Tệp đính kèm ', ' ', 'Tệp đính kèm  - 0.0 KB'], array_column($rows, 'fileMeta'));
        foreach ($rows as $index => $row) {
            $this->assertSame($attachments[$index], $row->attachment, 'Giữ thứ tự đầu vào đã được controller tải.');
            $this->assertSame('/payment-requests/992101/attachments-thao/'.$attachments[$index]->id.'/download', $row->downloadPath);
        }
    }

    public function test_view_khong_tu_tinh_va_moi_bien_duoc_cap(): void
    {
        $source = (string) file_get_contents(resource_path('views/payment_requests/edit.blade.php'));
        foreach (['@php', '<?php', 'Carbon::', 'DB::', 'number_format(', 'basename('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $matches);
        $provided = array_merge(
            ['item', 'companyOptions', 'company', 'row', 'errors'],
            array_keys((new PaymentRequestEditPresenter)->viewData(new PaymentRequest, [], [])),
        );
        $this->assertSame([], array_values(array_diff(array_unique($matches[1]), $provided)));

        preg_match_all('/\$row->([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $matches);
        $properties = array_map(fn (\ReflectionProperty $property): string => $property->getName(), (new \ReflectionClass(PaymentRequestAttachmentRow::class))->getProperties());
        $this->assertSame([], array_values(array_diff(array_unique($matches[1]), $properties)));
    }
}
