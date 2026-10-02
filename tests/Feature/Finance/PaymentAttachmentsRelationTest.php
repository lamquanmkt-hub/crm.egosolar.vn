<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Payments\PaymentRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chứng từ ĐNTT lấy qua quan hệ đã eager load, không truy vấn lại trong view.
 *
 * Trước đây `payment_requests/show` và `payment_requests/edit` cùng chạy lại
 * `DB::table('payment_attachments')` dù controller đã
 * `PaymentRequest::with([..., 'attachments'])`. Đo trên trang show: **3 câu** truy
 * vấn cùng bảng cho một lần dựng trang, sau khi sửa còn **1**.
 *
 * Thứ tự được ghim vào chính quan hệ: truy vấn cũ có `orderBy('id')`, bỏ nó đi mà
 * quan hệ không sắp xếp thì danh sách chứng từ đổi thứ tự tuỳ CSDL trả về.
 */
final class PaymentAttachmentsRelationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function quan_he_tra_ve_theo_thu_tu_id_tang_dan(): void
    {
        $prId = $this->taoPhieu();

        // Chèn ngược thứ tự tên để chắc chắn thứ tự đến từ id chứ không phải may.
        $ids = [];
        foreach (['zzz.pdf', 'aaa.pdf', 'mmm.pdf'] as $ten) {
            $ids[] = DB::table('payment_attachments')->insertGetId([
                'payment_request_id' => $prId, 'original_name' => $ten,
                'path' => 'p/'.$ten, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $pr = PaymentRequest::with('attachments')->findOrFail($prId);

        $this->assertSame($ids, $pr->attachments->pluck('id')->all());
        $this->assertSame(
            ['zzz.pdf', 'aaa.pdf', 'mmm.pdf'],
            $pr->attachments->pluck('original_name')->all()
        );
    }

    #[Test]
    public function trang_chi_tiet_chi_truy_van_bang_chung_tu_mot_lan(): void
    {
        $admin = $this->userWithRole('admin');
        $prId = $this->taoPhieu($admin->id);

        DB::table('payment_attachments')->insert([
            'payment_request_id' => $prId, 'original_name' => 'hop-dong.pdf',
            'path' => 'p/hop-dong.pdf', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $dem = 0;
        DB::listen(function ($q) use (&$dem): void {
            if (str_contains($q->sql, 'payment_attachments')) {
                $dem++;
            }
        });

        $this->actingAs($admin)->get('/payment-requests/'.$prId)->assertOk();

        $this->assertSame(1, $dem, sprintf(
            'Trang chi tiết chạy %d câu trên payment_attachments. Controller đã eager '.
            'load quan hệ attachments — view chỉ việc dùng $item->attachments.',
            $dem
        ));
    }

    #[Test]
    public function view_khong_con_truy_van_bang_chung_tu(): void
    {
        foreach (['show', 'edit'] as $ten) {
            $nguon = (string) preg_replace(
                '/\{\{--.*?--\}\}/s', '',
                (string) file_get_contents(resource_path('views/payment_requests/'.$ten.'.blade.php'))
            );

            $this->assertStringNotContainsString(
                "DB::table('payment_attachments')",
                $nguon,
                "payment_requests/$ten lại truy vấn thẳng bảng chứng từ."
            );
        }
    }

    private function taoPhieu(?int $nguoiTao = null): int
    {
        $nguoiTao ??= $this->userWithRole('accounting')->id;

        return (int) DB::table('payment_requests')->insertGetId([
            'code' => 'DN-'.random_int(10000, 99999),
            'created_by' => $nguoiTao,
            'receiver_name' => 'NCC ABC',
            'amount' => 45000000,
            'status' => 'submitted',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
