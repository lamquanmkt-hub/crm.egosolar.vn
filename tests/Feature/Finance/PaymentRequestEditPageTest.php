<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Chốt HTML form sửa phiếu bằng fixture cố định, không chạm tệp chứng từ thật. */
final class PaymentRequestEditPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_form_giu_trang_thai_ngay_va_chung_tu(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 11)->setTime(10, 0));
        $actor = $this->userWithRole('admin', [
            'id' => 991101, 'name' => 'Người kiểm thử', 'email' => 'payment-edit@example.test',
        ]);
        config(['ego.finance_full_access_emails' => [$actor->email]]);
        $this->actingAs($actor);

        $cases = [
            ['draft', 'Nháp', 'neutral', null, [], '', false],
            ['submitted', 'Đã gửi duyệt', 'info', '2026-09-15', [], '2026-09-15', true],
            ['admin_approved', 'Quản lý tài chính đã duyệt', 'primary', '2026-09-15', [], '2026-09-15', true],
            ['admin_rejected', 'Quản lý tài chính từ chối', 'danger', '2026-09-15', [], '2026-09-15', true],
            ['accounting_approved', 'Kế toán đã chi', 'success', '2026-09-15', [], '2026-09-15', true],
            ['accounting_rejected', 'Kế toán từ chối', 'warning', '2026-09-15', [], '2026-09-15', true],
            ['draft', 'Nháp', 'neutral', '2026-09-15', ['payment_due_date' => '2026-10-01'], '2026-10-01', true],
            ['draft', 'Nháp', 'neutral', '2026-09-15', ['payment_due_date' => ''], '', true],
            ['draft', 'Nháp', 'neutral', '2026-09-15', ['payment_due_date' => null], '2026-09-15', true],
        ];

        foreach ($cases as $index => [$status, $label, $tone, $due, $oldInput, $expectedDue, $hasAttachments]) {
            $id = 992101 + $index;
            DB::table('payment_requests')->insert([
                'id' => $id, 'code' => 'PR-EDIT-TEST-'.$index, 'created_by' => $actor->id,
                'receiver_name' => 'Nhà cung cấp', 'amount' => 1234000, 'status' => $status,
                'payment_due_date' => $due, 'created_at' => $index === 0 ? null : '2026-09-10 08:30:00',
                'updated_at' => '2026-09-10 08:30:00',
            ]);
            if ($hasAttachments) {
                // Chèn ngược id để bắt hồi quy thứ tự eager load.
                foreach ([[2, '0', 'docs/zero.pdf', '', null], [1, '', 'docs/fallback.pdf', null, 0], [0, 'Hóa đơn <A>.pdf', 'docs/invoice.pdf', 'application/pdf', 1048576]] as [$offset, $name, $path, $mime, $size]) {
                    DB::table('payment_attachments')->insert([
                        'id' => 993101 + $index * 3 + $offset, 'payment_request_id' => $id,
                        'original_name' => $name, 'path' => $path, 'mime_type' => $mime, 'size' => $size,
                    ]);
                }
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $html = $this->withSession(['_token' => str_repeat('a', 40), '_old_input' => $oldInput])
                ->get('/payment-requests/'.$id.'/edit')->assertOk()->getContent();
            $attachmentQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'payment_attachments'));
            DB::disableQueryLog();
            $this->assertCount(1, $attachmentQueries, 'Chứng từ chỉ được eager load một lần.');
            $this->assertStringContainsString('pay-edit-chip '.$tone.'">'.$label, $html);
            $this->assertMatchesRegularExpression('/name="payment_due_date"\s+class="pay-edit-control"\s+value="'.preg_quote($expectedDue, '/').'"/', $html);
            $this->assertStringContainsString('Ngày tạo: '.($index === 0 ? '-' : '10/09/2026 08:30'), $html);
            $this->assertStringContainsString("var paymentRequestId = '".$id."';", $html);
            if ($hasAttachments) {
                $this->assertStringContainsString('Hóa đơn &lt;A&gt;.pdf', $html);
                $this->assertStringContainsString('application/pdf  - 1,024.0 KB', $html);
                $this->assertStringContainsString('Tệp đính kèm </div>', $html);
                $this->assertStringContainsString('zero.pdf', $html);
                $this->assertStringContainsString(url('/payment-requests/'.$id.'/attachments-thao/'.(993101 + $index * 3).'/download'), $html);
                $this->assertLessThan(strpos($html, 'fallback.pdf'), strpos($html, 'Hóa đơn &lt;A&gt;.pdf'));
            } else {
                $this->assertStringContainsString('Phiếu này chưa có chứng từ đính kèm.', $html);
            }

        }
    }

    public function test_quyen_sua_khong_thay_doi_khi_tach_presenter(): void
    {
        config(['ego.finance_full_access_emails' => []]);
        $owner = $this->userWithRole('sales', ['id' => 991111, 'email' => 'edit-owner@example.test'], ['page.payment_requests']);
        $other = $this->userWithRole('sales', ['id' => 991112, 'email' => 'edit-other@example.test'], ['page.payment_requests']);
        $accountant = $this->userWithRole('accounting', ['id' => 991113, 'email' => 'edit-accountant@example.test'], ['page.payment_requests']);
        $hr = $this->userWithRole('hr', ['id' => 991114, 'email' => 'edit-hr@example.test'], ['page.payment_requests']);
        $id = 992121;
        DB::table('payment_requests')->insert([
            'id' => $id, 'code' => 'PR-EDIT-ACCESS', 'created_by' => $owner->id,
            'receiver_name' => 'NCC kiểm thử', 'amount' => 0, 'status' => 'draft',
        ]);
        $uri = '/payment-requests/'.$id.'/edit';
        $show = route('payment_requests.show', $id);

        foreach (['draft', 'admin_rejected', 'accounting_rejected'] as $status) {
            DB::table('payment_requests')->where('id', $id)->update(['status' => $status]);
            $this->actingAs($owner)->get($uri)->assertOk()->assertSee('PR-EDIT-ACCESS');
            $this->actingAs($other)->get($uri)->assertRedirect($show)->assertSessionHas('error');
        }
        DB::table('payment_requests')->where('id', $id)->update(['status' => 'submitted']);
        $this->actingAs($owner)->get($uri)->assertRedirect($show);
        $this->actingAs($accountant)->get($uri)->assertOk()->assertSee('Đã gửi duyệt');
        $this->actingAs($hr)->get($uri)->assertRedirect($show);
        DB::table('payment_requests')->where('id', $id)->update(['created_by' => $hr->id]);
        $this->actingAs($hr)->get($uri)->assertOk()->assertSee('PR-EDIT-ACCESS');

        DB::table('payment_requests')->where('id', $id)->update(['status' => 'admin_approved']);
        $this->actingAs($accountant)->get($uri)->assertRedirect($show);
        $this->actingAs($hr)->get($uri)->assertRedirect($show);
    }
}
