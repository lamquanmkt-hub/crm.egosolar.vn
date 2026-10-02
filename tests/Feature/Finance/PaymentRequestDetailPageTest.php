<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\PaymentAttachmentCard;
use App\DTOs\Finance\PaymentRequestDetail;
use App\View\Presenters\Finance\PaymentRequestDetailPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard trang `payment_requests/show` sau đợt 2026-09-30.
 */
final class PaymentRequestDetailPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/payment_requests/show.blade.php';

    private const CONTROLLER_KEYS = ['item', 'canEditByPolicy'];

    private const LOOP_AND_BLADE_VARIABLES = ['att', 'loop', 'errors', 'slot', 'attributes', 'component'];

    private const ALPINE_MAGICS = ['el', 'dispatch', 'event', 'nextTick', 'refs', 'data', 'store'];

    public function test_view_chi_in_khong_tu_tinh(): void
    {
        // ⚠️ BỎ CHÚ THÍCH BLADE trước khi kiểm. Chú thích không bao giờ ra HTML, nhưng nó hay nhắc
        // lại đúng tên thứ đang bị cấm (để giải thích vì sao bỏ) và làm guard đỏ oan — đã vấp 5 lần
        // trong các đợt trước với `<style>`, `<script>`, `ego-hu-`, `ego-pr-table-card`.
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        $this->assertStringNotContainsString('@php', $view, 'view còn khối @php');
        $this->assertStringNotContainsString('number_format', $view, 'view còn tự định dạng số');
        $this->assertStringNotContainsString('auth()', $view, 'view còn tự hỏi người đăng nhập');
        $this->assertStringNotContainsString('pathinfo(', $view, 'view còn tự lấy phần mở rộng');
        $this->assertStringNotContainsString('Carbon', $view, 'view còn tự parse ngày');
        $this->assertStringNotContainsString('<style', $view, 'view dùng lại khối style nội tuyến');
        $this->assertStringNotContainsString('hasRole', $view, 'view còn tự suy vai');

        // Chỉ được còn thẻ nạp JS dùng chung; bộ xem trước đã sang Alpine.
        $this->assertSame(1, substr_count($view, '<script'), 'view còn JS nội tuyến');
        $this->assertStringNotContainsString('addEventListener', $view);
        $this->assertStringNotContainsString('ego-pr-preview-ready', $view, 'lớp chết không có CSS lẫn JS');

        // 🚨 Directive Blade dính liền sau chữ ASCII sẽ KHÔNG được biên dịch (regex `\B@`).
        $this->assertDoesNotMatchRegularExpression('/\w@(else|endif|elseif|endforeach)\b/', $view);

        // 🚨 `@includeIf` phải nằm TRONG section: trước đây nó đứng sau `@endsection` nên nội dung
        // partial in ra TRƯỚC `<!DOCTYPE html>` và trang rơi vào quirks mode.
        $viTriInclude = strpos($view, "@includeIf('payment-requests._buibichthao_actions')");
        $viTriEnd = strrpos($view, '@endsection');
        $this->assertNotFalse($viTriInclude, 'mất include partial');
        $this->assertLessThan($viTriEnd, $viTriInclude, '@includeIf phải đứng TRƯỚC @endsection');

        $data = (new PaymentRequestDetailPresenter)->viewData(
            item: (object) ['creator' => null, 'director' => null, 'accountant' => null],
            attachments: [], currentUserId: null, isAdmin: false, isAccounting: false,
            canEditByPolicy: null,
            actionUrls: ['approveAdmin' => '', 'rejectAdmin' => '', 'approveAcc' => '', 'rejectAcc' => ''],
            fileUrls: fn (int $id): array => ['download' => '', 'preview' => ''],
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

        foreach (['detail' => PaymentRequestDetail::class, 'att' => PaymentAttachmentCard::class] as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z0-9]+)/', $view, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien ({$dto})");
        }
    }

    public function test_trang_that_in_ten_nguoi_duyet_chu_khong_phai_id(): void
    {
        Carbon::setTestNow('2026-09-30 09:15:00');

        $ql = $this->userWithRole('admin', ['id' => 810001, 'name' => 'Trần Quản Lý']);
        $nv = $this->userWithRole('technical', ['id' => 810002, 'name' => 'NV Guard']);

        DB::table('payment_requests')->insert([[
            'id' => 810010, 'code' => 'PR-GUARD', 'doc_type' => 'payment_voucher', 'created_by' => 810002,
            'receiver_name' => 'Nguyễn Nhận', 'department' => 'Kỹ thuật', 'company' => 'EGO',
            'reason' => "Dòng 1\nDòng 2", 'amount' => 12500000, 'payment_due_date' => '2026-10-15',
            'payment_content' => 'Nội dung', 'status' => 'admin_approved',
            'admin_approved_by' => 810001, 'admin_approved_at' => '2026-09-21 10:00:00',
            'created_at' => '2026-09-20 08:00:00', 'updated_at' => '2026-09-20 08:00:00',
        ]]);

        DB::table('payment_attachments')->insert([[
            'id' => 810020, 'payment_request_id' => 810010, 'original_name' => 'hoa-don.pdf',
            'path' => 'pa/a.pdf', 'mime_type' => 'application/pdf', 'size' => 204800,
            'created_at' => now(), 'updated_at' => now(),
        ]]);

        $html = (string) $this->actingAs($nv)->get('/payment-requests/810010')->assertOk()->getContent();

        $this->assertStringContainsString('PR-GUARD', $html);
        // 🚨 Bản cũ gọi sai tên quan hệ nên LUÔN in `#810001`; nay phải ra tên thật.
        $this->assertStringContainsString('Trần Quản Lý', $html, 'tên người duyệt phải hiện');
        $this->assertStringNotContainsString('#810001', $html, 'không được in id thay cho tên');
        // Tiền theo dấu tiếng Việt (bản cũ dùng number_format trơn → `12,500,000`).
        $this->assertStringContainsString('12.500.000 đ', $html);
        $this->assertStringNotContainsString('12,500,000', $html);
        $this->assertStringContainsString('200.0 KB', $html, 'kích thước tệp giữ dấu chấm kiểu Anh');
        $this->assertStringContainsString('Dòng 1<br />', $html, 'nl2br giữ xuống dòng');
    }

    public function test_doctype_luon_o_dau_tai_lieu(): void
    {
        $thao = $this->userWithRole('accounting', ['id' => 810003, 'name' => 'Bùi Bích Thảo', 'email' => 'buibichthao@egosolar.vn']);

        DB::table('payment_requests')->insert([[
            'id' => 810011, 'code' => 'PR-DOCTYPE', 'doc_type' => 'payment_voucher', 'created_by' => 810003,
            'receiver_name' => 'Nguyễn Nhận', 'department' => 'KT', 'company' => 'EGO', 'reason' => 'x',
            'amount' => 1000000, 'payment_due_date' => '2026-10-15', 'payment_content' => 'y',
            'status' => 'draft', 'created_at' => '2026-09-20 08:00:00', 'updated_at' => '2026-09-20 08:00:00',
        ]]);

        $html = (string) $this->actingAs($thao)->get('/payment-requests/810011')->assertOk()->getContent();

        // 🚨 Partial `_buibichthao_actions` chỉ hiện với đúng email này. Khi `@includeIf` còn nằm
        // sau `@endsection`, nội dung partial in TRƯỚC doctype → `document.compatMode = BackCompat`
        // (quirks mode), đo được bằng trình duyệt thật.
        $this->assertStringStartsWith('<!DOCTYPE html>', ltrim($html), 'doctype phải là thứ đầu tiên');
        $this->assertStringContainsString('ego-pay-admin-actions', $html, 'partial vẫn phải render');
    }
}
