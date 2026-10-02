<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * TestCase gốc cho toàn bộ test suite.
 *
 * Feature test chạy trên DB `egosolar_test` (bản sao schema production,
 * dựng từ database/schema/mysql-schema.sql — xem phpunit.xml).
 * Guard bên dưới chặn tuyệt đối việc chạy test lên bất kỳ DB nào khác
 * (đặc biệt là DB production) để không thể mất dữ liệu thật.
 */
abstract class TestCase extends BaseTestCase
{
    /** Tên DB duy nhất được phép dùng khi test có chạm DB. */
    private const ALLOWED_TEST_DATABASE = 'egosolar_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSafeTestDatabase();
    }

    /**
     * Chặn test nếu connection mặc định không trỏ vào DB test cho phép.
     */
    private function assertSafeTestDatabase(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        // `php artisan test --parallel` (ParallelTesting của Laravel) đặt tên DB mỗi tiến
        // trình là `<db>_test_<n>` — vẫn nằm trong họ DB test, cho qua; tên khác thì chặn.
        $laDbTest = $database === self::ALLOWED_TEST_DATABASE
            || preg_match('/^'.preg_quote(self::ALLOWED_TEST_DATABASE, '/').'_test_\d+$/', $database) === 1;

        if ($database !== '' && $database !== ':memory:' && ! $laDbTest) {
            self::fail(sprintf(
                'TỪ CHỐI chạy test: DB đang cấu hình là "%s" — chỉ cho phép "%s" để bảo vệ dữ liệu.',
                $database,
                self::ALLOWED_TEST_DATABASE,
            ));
        }
    }

    /**
     * Tạo user kèm role Spatie (tạo role nếu chưa có) — dùng cho feature test
     * cần vượt qua Policy phân quyền theo role.
     *
     * DB test chỉ có schema, KHÔNG có dữ liệu phân quyền như production, nên
     * quyền nào bị `role_permissions.always_enforce_permissions` bắt buộc
     * (vd `page.orders`) phải cấp tường minh qua $permissions.
     *
     * @param  string  $role  Tên role, ví dụ 'admin', 'sales', 'accounting'
     * @param  array<string, mixed>  $attributes  Ghi đè thuộc tính user
     * @param  list<string>  $permissions  Permission cấp thẳng cho user
     */
    /**
     * Utility Tailwind ĐƯỢC PHÉP mang dấu `!` — nay chỉ còn ĐÚNG MỘT.
     *
     * Đợt quy đổi Bootstrap→Tailwind từng rải 4.231 dấu `!` trên 154 view, vì
     * utility Bootstrap BẢN THÂN NÓ đã `!important` (`.mb-3{margin-bottom:1rem
     * !important}`) nên bỏ dấu là đổi kết quả ở mọi phần tử có luật khác tranh.
     *
     * Nhưng đó là suy luận, không phải số đo. Gỡ sạch cả 4.231 dấu rồi đo lại 11
     * trang × 6 khổ màn: chỉ 339/2.636.550 giá trị lệch, trên 60 phần tử — tức
     * ~99% số dấu là thừa. Truy tiếp 60 phần tử đó ra đúng BA nguyên nhân gốc,
     * và hai trong ba là lỗi thật chứ không phải chỗ cần `!`:
     *
     *  1. `layouts/app.blade.php` — nút đóng toast mang CẢ `tw:mr-2` lẫn `m-auto`
     *     của Bootstrap (`margin:auto!important`). Sửa gốc: bỏ `m-auto`, viết
     *     thẳng `tw:my-auto tw:ml-auto tw:mr-2`.
     *  2. `<x-ui.label class="tw:mb-1">` — BASE của component là `tw:mb-2`.
     *     `$attributes->class()` chỉ NỐI chuỗi; ai thắng là do THỨ TỰ TRONG TỆP
     *     CSS, mà Tailwind xuất `mb-1` trước `mb-2`. Sửa gốc: component nhường
     *     lề khi nơi gọi đã tự đặt `mb-*` (xem App\View\Components\Ui\Label).
     *  3. `products/{index_input,edit}` — ô trống của bảng cần `tw:py-6`, nhưng
     *     `public/css/ego-inventory-enterprise.css` có
     *     `.ego-inventory-data-table tbody td{padding:11px 9px!important}`.
     *     Đây là chỗ DUY NHẤT thật sự cần `!`: muốn thắng một khai báo đã
     *     `!important` thì không còn cách nào khác. Gỡ được khi luật CSS kia bỏ
     *     `!important` — việc riêng của trang đó.
     *
     * Bài học ghi lại: `!` là dấu vết của tranh chấp độ ưu tiên, và gần như lần
     * nào cũng có một lớp Bootstrap sót lại hoặc một component ghi đè nhầm đứng
     * sau nó. Tìm ra lớp đó rẻ hơn nhiều so với sống chung với `!`.
     *
     * @var list<string>
     */
    protected const TAILWIND_IMPORTANT_CHO_PHEP = [
        'tw:py-6!',
        // Đợt text-* 2026-09-07: màu chữ quy đổi từ `text-muted/-danger/-success…` (vốn
        // `!important`). Chỉ gắn `!` ở phần tử mà CSS của chính view khai `color` cho lớp/thẻ
        // của nó (`.value{color}`, `.table td{color}`…) — dò tĩnh + đo 42 trang, 121 chỗ.
        // Khi trang đó chuyển hết sang Tailwind (Bước 4) thì luật tranh chấp biến mất và
        // dấu `!` bỏ được.
        'tw:text-[rgba(33,37,41,0.75)]!', 'tw:text-[#dc3545]!', 'tw:text-[#198754]!', 'tw:text-[#0d6efd]!',
        'tw:text-[#212529]!', 'tw:text-[#ffffff]!', 'tw:text-[#ffc107]!', 'tw:text-[#6c757d]!',
        'tw:text-[#0dcaf0]!', 'tw:text-[#000000]!',
        // Đợt `hr/overtime/index` 2026-10-01: bán kính quy đổi từ `rounded-pill`/`rounded-4`/
        // `rounded-3` (cả ba vốn `!important`). Phải giữ `!` vì `<x-ui.button|input|select>`
        // KHÔNG dùng `LopTienIch::nhuong`, nên lớp nền của component đứng cạnh lớp nơi gọi và
        // THỨ TỰ TỆP CSS quyết định — đo được bán kính rơi về 6px của component khi bỏ `!`.
        // Bỏ được khi ba component đó có prop bán kính hoặc biết nhường.
        'tw:rounded-[50rem]!', 'tw:rounded-[1rem]!', 'tw:rounded-[0.5rem]!',
        // Đợt `payment_methods/index` 2026-10-01: đệm ngang của ô đầu/cuối bảng, quy đổi từ
        // `ps-4`/`pe-4` (cả hai vốn `!important`). Cần `!` vì `<x-ui.table>` đặt đệm ô bằng biến thể
        // CON TRỰC TIẾP `[&>thead>tr>th]` / `[&>tbody>tr>td]` — độ đặc hiệu (0,1,3) đè utility trên
        // chính ô (0,1,0); đo được 24px rơi về 8px khi bỏ `!`. Bỏ được khi component có prop đệm theo ô.
        'tw:pl-6!', 'tw:pr-6!',
    ];

    /**
     * Chốt kỷ luật về dấu `!` TRONG THUỘC TÍNH class: chỉ cho phép đúng danh sách
     * utility ở trên.
     *
     * CỐ Ý không đụng tới `!important` bên trong `<style>` của view: nhiều view đã
     * có sẵn từ trước, dọn chúng là việc riêng của từng trang chứ không phải hệ quả
     * của đợt quy đổi utility này.
     */

    /**
     * Mọi tệp .blade.php dưới resources/views.
     *
     * Trước đây bốn test tự chép lại đúng đoạn này; gom về một chỗ để thêm test
     * mới không phải chép bản thứ năm.
     *
     * @return list<string>
     */
    protected function dsBlade(): array
    {
        $ra = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $tep) {
            if ($tep->isFile() && str_ends_with($tep->getFilename(), '.blade.php')) {
                $ra[] = $tep->getPathname();
            }
        }

        sort($ra);

        return $ra;
    }

    protected function assertKhongLamDungImportant(string $nguon, string $noi = ''): void
    {
        // `(?<![-:\w])` để KHÔNG vơ luôn `x-bind:class="…"` / `:class="…"` của Alpine —
        // trong đó là biểu thức JS, không phải danh sách lớp.
        preg_match_all('/(?<![-:\\w])class="([^"]*)"/', $nguon, $m);
        $viPham = [];

        foreach ($m[1] as $danhSach) {
            foreach (preg_split('/\s+/', trim($danhSach)) ?: [] as $lop) {
                if ($lop === '') {
                    continue;
                }

                $duocPhep = in_array($lop, static::TAILWIND_IMPORTANT_CHO_PHEP, true);

                if (str_ends_with($lop, '!') && ! $duocPhep) {
                    $viPham[] = $lop;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($viPham)), trim(
            $noi.' — dấu ! chỉ được dùng cho utility quy đổi từ Bootstrap '
            .'(xem TAILWIND_IMPORTANT_CHO_PHEP). Lớp khác thì sửa độ ưu tiên.'
        ));
    }

    protected function userWithRole(string $role, array $attributes = [], array $permissions = []): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    /**
     * User không có role, chỉ có đúng các permission chỉ định — dùng để kiểm
     * chứng các quyền `always_enforce_permissions` mà không kéo theo role.
     *
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithPermissions(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    /**
     * Xoá sạch dữ liệu các bảng chỉ định — dùng khi test cần trạng thái bảng
     * rỗng xác định trước.
     *
     * ⚠️ CỐ Ý dùng DELETE chứ KHÔNG dùng TRUNCATE: trong MySQL/MariaDB,
     * TRUNCATE là lệnh DDL nên gây **commit ngầm**, phá luôn transaction của
     * `DatabaseTransactions` — dữ liệu test rò rỉ sang test sau và dữ liệu có
     * sẵn của DB test bị xoá vĩnh viễn. Đã xảy ra thật (bảng users bị xoá).
     * DELETE chạy trong transaction nên rollback bình thường.
     */
    protected function truncateTables(string ...$tables): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                DB::table($table)->delete();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
