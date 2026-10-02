<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\SettlementAttachmentRow;
use App\DTOs\Finance\SettlementDetail;
use App\View\Presenters\Finance\SettlementDetailPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard trang `settlement_requests/show` sau đợt 2026-09-30.
 */
final class SettlementDetailPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/settlement_requests/show.blade.php';

    private const CONTROLLER_KEYS = [
        'item', 'labels', 'canViewAll', 'canApproveManagement', 'canApproveAccounting', 'approvers',
    ];

    private const LOOP_AND_BLADE_VARIABLES = [
        'file', 'loop', 'errors', 'slot', 'attributes', 'component',
    ];

    private const ALPINE_MAGICS = ['el', 'dispatch', 'event', 'nextTick', 'refs', 'data', 'store'];

    public function test_view_chi_in_khong_tu_tinh(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $view, 'view còn khối @php');
        $this->assertStringNotContainsString('number_format', $view, 'view còn tự định dạng số');
        $this->assertStringNotContainsString('auth()', $view, 'view còn tự hỏi người đăng nhập');
        $this->assertStringNotContainsString('basename(', $view, 'view còn tự xử lý đường dẫn tệp');
        $this->assertStringNotContainsString('pathinfo(', $view, 'view còn tự lấy phần mở rộng');

        $this->assertStringNotContainsString('<style', $view, 'view dùng lại khối style nội tuyến');
        $this->assertStringNotContainsString('ego-hu-', $view, 'view dùng lại hệ class ego-hu-* cũ');
        $this->assertSame(1, substr_count($view, '<script'), 'chỉ được còn thẻ nạp JS dùng chung');
        $this->assertKhongLamDungImportant($view, 'settlement_requests/show');

        // 🚨 Không được viết directive Blade DÍNH LIỀN sau một chữ: `\B@` của compileStatements đòi
        // ký tự trước không phải chữ. Bản cũ có `…kế toán@else …` và nó lọt nguyên văn ra HTML.
        $this->assertDoesNotMatchRegularExpression('/\w@(else|endif|elseif|endforeach)\b/', $view,
            'directive Blade dính liền sau chữ sẽ KHÔNG được biên dịch');

        $data = (new SettlementDetailPresenter)->viewData(
            item: (object) ['attachments' => [], 'creator' => null, 'advanceRequest' => null],
            labels: [], approvers: [], currentUserId: null,
            canViewAll: false, canApproveManagement: false, canApproveAccounting: false,
        );

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $view, $m);
        $provided = array_merge(
            self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, self::ALPINE_MAGICS, array_keys($data)
        );

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');
    }

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        foreach (['detail' => SettlementDetail::class, 'file' => SettlementAttachmentRow::class] as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z0-9]+)/', $view, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien ({$dto})");
        }
    }

    public function test_trang_that_in_dung_va_khong_lot_directive(): void
    {
        Carbon::setTestNow('2026-09-30 09:15:00');

        $ql = $this->userWithRole('management', ['id' => 820002, 'name' => 'Trần QL'], ['page.finance']);
        $nv = $this->userWithRole('technical', ['id' => 820001, 'name' => 'NV Guard'], ['page.finance']);

        DB::table('settlement_requests')->insert([[
            'id' => 820010, 'code' => 'ST-GUARD', 'advance_request_id' => null, 'created_by' => 820001,
            'company' => 'EGO', 'recipient_name' => 'Nguyễn Nhận', 'advance_amount' => 12500000,
            'actual_amount' => 11000000, 'refund_amount' => 1500000, 'difference_amount' => -1500000,
            'settlement_type' => 'refund', 'reason' => "Dòng 1\nDòng 2", 'note' => 'Ghi chú',
            'status' => 'draft', 'attachments' => json_encode(['settlements/hoa-don.pdf', 'settlements/anh.JPG']),
            'created_at' => '2026-09-20 08:00:00', 'updated_at' => '2026-09-20 08:00:00',
        ]]);

        $html = (string) $this->actingAs($nv)->get('/settlement-requests/820010')->assertOk()->getContent();

        $this->assertStringContainsString('ST-GUARD', $html);
        $this->assertStringContainsString('1.500.000 đ', $html);
        $this->assertStringContainsString('Nhân sự hoàn lại công ty', $html);
        $this->assertStringContainsString('PDF', $html, 'nhãn loại tệp');
        $this->assertStringContainsString('ẢNH', $html, 'đuôi .JPG viết HOA vẫn nhận ra là ảnh');
        $this->assertStringContainsString('Dòng 1<br />', $html, 'nl2br giữ xuống dòng của lý do');

        // 🚨 Bản cũ để lọt `@else` ra HTML VÀ làm ô bước 3 trống. Hai khẳng định này chặn cả hai.
        $this->assertStringNotContainsString('@else', $html, 'directive Blade lọt ra HTML');
        $this->assertStringContainsString('Chưa đến bước kế toán', $html, 'ô bước 3 không được để trống');
        $this->assertStringContainsString('Chưa đến bước duyệt', $html);
    }
}
