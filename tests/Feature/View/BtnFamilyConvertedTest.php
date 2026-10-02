<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Họ `btn` đã chuyển hết sang <x-ui.button> ở view sống (đợt 2026-09-06).
 *
 * ## Hai lớp canh
 * 1. MÃ NGUỒN: không view sống nào còn token `btn` trong `class="…"`. Đếm theo TOKEN
 *    (tách khoảng trắng), không dùng `\bbtn` — `\b` coi `-` là ranh giới nên khớp cả
 *    `op-btn`, `pt-btn`… và từng thổi 135 nút thành "1.410" (xem skill, Bước 3.8).
 * 2. TRANG THẬT: 30 trang từng chứa nút Bootstrap vẫn render 200 với dữ liệu đủ để
 *    các nhánh có điều kiện (file đính kèm, đơn vật tư, kế hoạch marketing…) cùng hiện,
 *    và HTML trả về không còn phần tử mang lớp `btn`.
 *
 * ## Cách đã kiểm giao diện (không lặp lại trong test)
 * 130 nút render trên 30 trang, đo 4 trạng thái (mặc định/hover/active/focus-visible)
 * bằng sự kiện chuột/bàn phím thật qua CDP, trước và sau khi chuyển. Lệch còn lại chỉ
 * là kiểu "Bootstrap-fallback": `.btn` trần không có biến `--bs-btn-hover-*` nên khi
 * hover/active/focus chữ và viền rơi về màu mặc định của body — component KHÔNG tái
 * hiện khuyết tật đó (ghi ở skill, Bước 3.15).
 *
 * View chết (EGO_VIEW_CHET) được miễn: chúng không render, và danh sách đó do
 * DeadViewsMarkedTest giữ.
 */
final class BtnFamilyConvertedTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private int $actor = 0;

    protected function seedActorId(): int
    {
        return $this->actor;
    }

    /**
     * Token `btn` trần hoặc `btn-close`/`btn-close-white` — không phải `op-btn`.
     * `btn-close` là thành phần riêng của Bootstrap, đã thay bằng <x-ui.close-button>
     * (2026-09-06) nên cũng không được xuất hiện lại.
     */
    private static function hasBtnToken(string $classAttr): bool
    {
        $tokens = preg_split('/\s+/', trim($classAttr)) ?: [];

        return array_intersect(['btn', 'btn-close', 'btn-close-white'], $tokens) !== [];
    }

    #[Test]
    public function khong_view_song_nao_con_token_btn(): void
    {
        $deadViewPaths = array_map(
            static fn (array $v): string => resource_path('views/'.$v[0].'.blade.php'),
            DeadViewsMarkedTest::deadViews(),
        );
        $violations = [];

        foreach ($this->dsBlade() as $file) {
            if (in_array($file, $deadViewPaths, true)) {
                continue;
            }
            $source = (string) file_get_contents($file);
            // `(?<![-:\w])` để KHÔNG vơ luôn `x-bind:class="…"` / `:class="…"` của Alpine —
            // trong đó là biểu thức JS, không phải danh sách lớp.
            preg_match_all('/(?<![-:\\w])class="([^"]*)"/', $source, $m);
            foreach ($m[1] as $classAttr) {
                if (self::hasBtnToken($classAttr)) {
                    $violations[] = str_replace(resource_path('views/'), '', $file).'  class="'.$classAttr.'"';
                }
            }
        }

        $this->assertSame([], $violations, "Còn nút Bootstrap `btn` ở view sống — dùng <x-ui.button>:\n".implode("\n", $violations));
    }

    #[Test]
    public function ba_muoi_trang_render_va_khong_con_phan_tu_btn(): void
    {
        $user = $this->userWithRole(Role::Admin->value, [
            'id' => 999800, 'name' => 'Nguoi Dung Canh', 'email' => 'nguoi.dung@example.test',
        ]);
        $this->actor = (int) $user->id;
        foreach (['page.products', 'product.view', 'products.manage', 'categories.manage',
            'page.warehouses', 'warehouse.manage', 'warehouse.stock_check',
            'page.orders', 'orders.manage', 'order.view', 'order.edit'] as $perm) {
            Permission::findOrCreate($perm, 'web');
            $user->givePermissionTo($perm);
        }
        $now = '2026-09-01 08:00:00';
        $this->seedShippableOrder();

        DB::table('sites')->insert(['id' => 940001, 'name' => 'Cong trinh canh test', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('material_requests')->insert([
            'id' => 997501, 'site_id' => 940001, 'warehouse_id' => $this->seed['warehouseId'],
            'created_by' => $user->id, 'status' => 'draft', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('content_calendars')->insert([
            'id' => 920002, 'publish_date' => '2026-09-03', 'platform' => 'facebook',
            'content_type' => 'post', 'title' => 'Canh test', 'created_by' => $user->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('content_feedbacks')->insert([
            'id' => 930003, 'content_calendar_id' => 920002, 'user_id' => $user->id,
            'message' => 'Gop y', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('content_feedbacks')->insert([
            'id' => 930004, 'content_calendar_id' => 920002, 'parent_id' => 930003,
            'user_id' => $user->id, 'message' => 'Tra loi', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('tasks')->insert([
            'id' => 910000, 'title' => 'Canh test', 'status' => 'in_progress',
            'assignee_id' => $user->id, 'requester_id' => $user->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('weekly_tasks')->insert([
            'id' => 950001, 'title' => 'Viec tuan canh test', 'created_at' => $now, 'updated_at' => $now,
            // để nút wt-mini (file đang có) render
            'attachments' => json_encode([['name' => 'bao-cao.pdf', 'path' => 'weekly/bao-cao.pdf', 'mime' => 'application/pdf', 'size' => 12345]]),
        ]);
        // file kết quả để 2 <label class="btn ..."> của tasks/show render
        DB::table('task_attachments')->insert([
            'id' => 911001, 'task_id' => 910000, 'uploaded_by' => $user->id, 'type' => 'result',
            'file_name' => 'ket-qua.pdf', 'file_path' => 'tasks/ket-qua.pdf', 'file_mime' => 'application/pdf', 'file_size' => 2048,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        // kế hoạch marketing để nút "Tạo task từ kế hoạch" (btnx-primary) render
        DB::table('mkt_plans')->insert([
            'id' => 960001, 'month' => '2026-09-01', 'name' => 'Ke hoach canh test', 'status' => 'draft',
            'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('technical_kpi_payrolls')->insert([
            'id' => 998100, 'user_id' => $user->id, 'employee_name' => 'Nguyen Van A',
            'position_name' => 'Ky thuat', 'payroll_month' => '2026-09', 'month_label' => '09/2026',
            'gross_salary' => 15000000, 'status' => 'draft', 'created_by' => $user->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('accounts')->insert([
            'id' => 998110, 'name' => 'Tai khoan canh test', 'code' => 'TK-CT', 'type' => 'bank',
            'opening_balance' => 0, 'current_balance' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('payments')->insert([
            'id' => 998111, 'account_id' => 998110, 'code' => 'PC-CT', 'payment_date' => '2026-09-01',
            'payee_name' => 'Nguoi nhan', 'category' => 'other', 'payment_method' => 'cash', 'amount' => 500000,
            'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('receipts')->insert([
            'id' => 998112, 'account_id' => 998110, 'code' => 'PT-CT', 'receipt_date' => '2026-09-01',
            'payer_name' => 'Nguoi nop', 'category' => 'other', 'payment_method' => 'cash', 'amount' => 700000,
            'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $urls = [
            'cc-show' => '/marketing/reports/content-calendar/920002',
            'sites-show' => '/cong-trinh/940001',
            'hr-dashboard' => '/nhan-su',
            'sites-create' => '/cong-trinh/create',
            'products' => '/products',
            'products-input' => '/products/input',
            'products-output' => '/products/output',
            'weekly-edit' => '/marketing/reports/weekly-tasks/950001/edit',
            'kpi' => '/sales/kpi',
            'sites' => '/cong-trinh',
            'sites-edit' => '/cong-trinh/940001/edit',
            'weekly-create' => '/marketing/reports/weekly-tasks/create',
            'weekly' => '/marketing/reports/weekly-tasks',
            'receipts' => '/finance/receipts',
            'payments' => '/finance/payments',
            'kpi-settings' => '/sales/kpi/settings',
            'kpi-my' => '/sales/kpi/my',
            'profile' => '/profile',
            'att-settings' => '/nhan-su/cham-cong/settings',
            'task' => '/chat/tasks/910000',
            'products-history' => '/products/history',
            'mr-edit' => '/don-vat-tu/997501/edit',
            'mr-create' => '/don-vat-tu/create',
            'progress' => '/marketing/progress?plan_id=960001',
            'cc' => '/marketing/reports/content-calendar',
            'progress-monthly' => '/marketing/progress/monthly?plan_id=960001',
            'weekly-show' => '/marketing/reports/weekly-tasks/950001',
            'orders-edit' => '/orders/'.$this->seed['orderId'].'/edit',
            'luong-edit' => '/ky-thuat/luong/998100/edit',
            'users' => '/users',
        ];

        foreach ($urls as $name => $url) {
            $html = (string) $this->actingAs($user)->get($url)->assertOk()->getContent();
            // `(?<![-:\w])` để KHÔNG vơ luôn `x-bind:class="…"` / `:class="…"` của Alpine —
            // trong đó là biểu thức JS, không phải danh sách lớp.
            preg_match_all('/(?<![-:\\w])class="([^"]*)"/', $html, $m);
            $remaining = array_values(array_filter($m[1], [self::class, 'hasBtnToken']));
            $this->assertSame([], $remaining, "$name ($url) vẫn render phần tử mang lớp `btn`/`btn-close`.");
            $this->assertStringContainsString('tw:select-none', $html, "$name ($url) không thấy dấu của <x-ui.button>.");
        }
    }
}
