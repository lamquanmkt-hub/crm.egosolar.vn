<?php

declare(strict_types=1);

/*
| Trang `payment_requests/show` sau đợt 2026-09-30.
|
| Hai thứ hỏng IM LẶNG nếu không đo bằng trình duyệt thật:
|  1. Bộ xem trước chứng từ nay là Alpine — sai tên sự kiện thì bấm không ra gì.
|  2. 🚨 `@includeIf` của partial `_buibichthao_actions` trước nằm SAU `@endsection`, nên với đúng
|     một tài khoản (email khớp điều kiện trong partial) nội dung của nó in ra TRƯỚC
|     `<!DOCTYPE html>`. Trình duyệt bỏ qua doctype và render trang ở QUIRKS MODE — không lỗi,
|     không cảnh báo, chỉ là box model cũ làm bố cục sai. Đo `document.compatMode` mới thấy.
*/

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->nv = $this->userWithRole('technical', [
        'id' => 800002, 'name' => 'NV Browser', 'email' => 'pr-browser@example.test',
    ]);
    $this->thao = $this->userWithRole('accounting', [
        'id' => 800003, 'name' => 'Bùi Bích Thảo', 'email' => 'buibichthao@egosolar.vn',
    ]);

    DB::table('payment_requests')->insert([[
        'id' => 800010, 'code' => 'PR-BRW', 'doc_type' => 'payment_voucher', 'created_by' => 800002,
        'receiver_name' => 'Nguyễn Nhận', 'department' => 'Kỹ thuật', 'company' => 'EGO',
        'reason' => 'Lý do', 'amount' => 12500000, 'payment_due_date' => '2026-10-15',
        'payment_content' => 'Nội dung', 'status' => 'draft',
        'created_at' => '2026-09-20 08:00:00', 'updated_at' => '2026-09-20 08:00:00',
    ]]);

    DB::table('payment_attachments')->insert([[
        'id' => 800020, 'payment_request_id' => 800010, 'original_name' => 'hoa-don.pdf',
        'path' => 'pa/a.pdf', 'mime_type' => 'application/pdf', 'size' => 204800,
        'created_at' => now(), 'updated_at' => now(),
    ]]);
});

it('hop xem truoc chung tu mo va dong bang Alpine', function () {
    $this->actingAs($this->nv);
    $page = visit('/payment-requests/800010');
    $page->assertNoJavaScriptErrors();

    $hop = "'#payxAttachmentPreviewModal'";
    $coShow = fn (): string => (string) $page->script(
        "document.querySelector('#payxAttachmentPreviewModal').classList.contains('show') ? '1' : '0'"
    );

    expect($coShow())->toBe('0', 'vào trang thì hộp xem trước phải đóng');

    // Bấm vào thẻ chứng từ → phát sự kiện `xem-truoc`.
    $page->script(<<<'JS'
        new Promise(ok => {
            document.querySelector('.payx-file-preview-card').click();
            requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(() => ok(1), 150)));
        })
    JS);

    expect($coShow())->toBe('1', 'bấm thẻ chứng từ phải mở hộp xem trước');
    expect((string) $page->script("document.querySelector('.payx-attachment-preview-title').textContent"))
        ->toBe('hoa-don.pdf', 'tiêu đề phải là tên tệp');
    expect((string) $page->script("document.querySelector('.payx-attachment-preview-frame').getAttribute('src')"))
        ->toContain('/preview');
    expect((string) $page->script('document.body.style.overflow'))
        ->toBe('hidden', 'mở hộp thì khoá cuộn nền');

    // Nút Đóng.
    $page->script(<<<'JS'
        new Promise(ok => {
            document.querySelector('.payx-attachment-preview-close').click();
            requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(() => ok(1), 150)));
        })
    JS);

    expect($coShow())->toBe('0', 'bấm Đóng phải đóng hộp');
    expect((string) $page->script("document.querySelector('.payx-attachment-preview-frame').getAttribute('src')"))
        ->toBe('about:blank', 'đóng thì phải nhả iframe');
    expect((string) $page->script('document.body.style.overflow'))->toBe('');
});

it('trang luon o standards mode voi moi tai khoan', function () {
    foreach ([['thường', $this->nv], ['buibichthao', $this->thao]] as [$ten, $actor]) {
        $this->actingAs($actor);
        $page = visit('/payment-requests/800010');

        // 🚨 `BackCompat` = quirks mode. Xảy ra khi có bất kỳ nội dung nào đứng trước `<!DOCTYPE>`.
        expect((string) $page->script('document.compatMode'))
            ->toBe('CSS1Compat', "vai {$ten}: trang rơi vào quirks mode — có nội dung trước doctype?");
        expect((string) $page->script('document.doctype ? "1" : "0"'))
            ->toBe('1', "vai {$ten}: mất doctype");
    }
});
