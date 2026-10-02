<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Canh giữ luồng tải chứng từ cho phiếu đề nghị thanh toán.
 *
 * ## Lỗi thật đã xảy ra trên production
 * Ngày 2026-09-03 tìm thấy 5 bản ghi `payment_attachments` trỏ tới file KHÔNG còn
 * tồn tại ở bất kỳ đâu trong storage. Nguyên nhân nằm ở chính khối catch:
 *
 *   - rollback CÓ điều kiện `transactionLevel() > 0`
 *   - nhưng xoá file thì VÔ điều kiện
 *
 * Nên khi lỗi xảy ra SAU `DB::commit()` — mà thứ hay ném lỗi ngay sau commit chính
 * là `Log::info` ghi vào `storage/logs`, đúng chỗ hỏng khi hết quota đĩa — bản ghi
 * ở lại còn file bị xoá sạch. Log production có `Disk quota exceeded` ngày
 * 2026-08-27, khớp với dạng hỏng này.
 *
 * Hai test dưới canh đúng hai nửa của lỗi đó.
 */
final class PaymentAttachmentUploadTest extends TestCase
{
    /** @var list<int> id đã tạo, dọn tay vì không dùng DatabaseTransactions */
    private array $madePaymentRequests = [];

    private ?int $madeUserId = null;

    /**
     * KHÔNG dùng DatabaseTransactions.
     *
     * Trait đó bọc mỗi test trong một transaction, nên sau `DB::commit()` của
     * controller `transactionLevel()` vẫn > 0 và nhánh rollback trong catch vẫn
     * chạy — đúng thứ production KHÔNG làm (ở đó mức về 0). Giữ trait thì test
     * xanh/đỏ vì lý do sai. Đổi lại phải tự dọn dữ liệu.
     */
    protected function tearDown(): void
    {
        foreach ($this->madePaymentRequests as $id) {
            DB::table('payment_attachments')->where('payment_request_id', $id)->delete();
            DB::table('payment_requests')->where('id', $id)->delete();
        }

        if ($this->madeUserId !== null) {
            DB::table('model_has_roles')->where('model_id', $this->madeUserId)->delete();
            DB::table('users')->where('id', $this->madeUserId)->delete();
        }

        parent::tearDown();
    }

    private function seedPaymentRequest(int $id, int $userId): void
    {
        DB::table('payment_requests')->insert([
            'id' => $id,
            'code' => 'PC-TEST-'.$id,
            'created_by' => $userId,
            'receiver_name' => 'Nguoi nhan',
            'amount' => 1000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->madePaymentRequests[] = $id;
    }

    /**
     * Lỗi SAU commit không được xoá file và không được mất bản ghi.
     *
     * Đây là bản canh trực tiếp cho 5 bản ghi hỏng trên production.
     */
    public function test_loi_sau_commit_khong_lam_mat_file(): void
    {
        Storage::fake('public');
        $user = $this->userWithRole(Role::Admin->value);
        $this->madeUserId = (int) $user->id;
        $this->seedPaymentRequest(880001, (int) $user->id);

        // Mô phỏng đúng thứ đã hỏng: ghi log thất bại (hết quota đĩa).
        Log::shouldReceive('info')->andThrow(new \RuntimeException('Disk quota exceeded'));
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();

        $res = $this->actingAs($user)->post('/payment-requests/880001/attachments', [
            'attachments' => [UploadedFile::fake()->create('chung-tu.pdf', 40, 'application/pdf')],
        ]);

        $res->assertStatus(201);

        $rows = DB::table('payment_attachments')->where('payment_request_id', 880001)->get();
        $this->assertCount(1, $rows, 'Bản ghi phải còn sau khi đã commit');

        Storage::disk('public')->assertExists($rows[0]->path);
    }

    /** Đường bình thường: có bản ghi và có file, khớp nhau. */
    public function test_tai_len_thanh_cong_thi_ban_ghi_va_file_khop_nhau(): void
    {
        Storage::fake('public');
        $user = $this->userWithRole(Role::Admin->value);
        $this->madeUserId = (int) $user->id;
        $this->seedPaymentRequest(880002, (int) $user->id);

        $res = $this->actingAs($user)->post('/payment-requests/880002/attachments', [
            'attachments' => [UploadedFile::fake()->create('hoa-don.pdf', 20, 'application/pdf')],
        ]);

        $res->assertStatus(201);

        $rows = DB::table('payment_attachments')->where('payment_request_id', 880002)->get();
        $this->assertCount(1, $rows);
        Storage::disk('public')->assertExists($rows[0]->path);
        $this->assertSame('hoa-don.pdf', $rows[0]->original_name);
    }

    /**
     * Khối catch chỉ được xoá file khi CHƯA commit — canh ở mức mã nguồn.
     *
     * Điều kiện này không mô phỏng được bằng request thật (phải ép ghi file hỏng
     * giữa chừng), nên canh bằng cách đọc mã: việc xoá phải nằm trong `if (! $committed)`.
     */
    public function test_khoi_catch_chi_xoa_file_khi_chua_commit(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Finance/PaymentAttachmentController.php')
        );

        $this->assertMatchesRegularExpression(
            '/if \(! \$committed\) \{\s*foreach \(\$storedPaths as \$path\) \{/',
            $src,
            'Việc xoá file phải nằm trong nhánh chưa commit',
        );

        $this->assertStringContainsString('$committed = true;', $src);
    }

    /**
     * Đường ghi thứ hai (PaymentRequestController) phải kiểm `store()`.
     *
     * `store()` trả về false khi ghi hỏng; không kiểm thì bản ghi vẫn được chèn
     * với đường dẫn rỗng.
     */
    public function test_duong_ghi_con_lai_kiem_ket_qua_store(): void
    {
        $src = (string) file_get_contents(
            app_path('Http/Controllers/Finance/PaymentRequestController.php')
        );

        $this->assertMatchesRegularExpression(
            '/\$path = \$file->store\("payment_requests\/\{\$id\}", \'public\'\);.*?if \(! \$path \|\| ! Storage::disk\(\'public\'\)->exists\(\$path\)\)/s',
            $src,
            'Phải kiểm kết quả store() trước khi chèn bản ghi',
        );
    }
}
