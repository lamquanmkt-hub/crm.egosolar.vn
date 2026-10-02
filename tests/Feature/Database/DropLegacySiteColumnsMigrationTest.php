<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Support\SchemaCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Kiểm phần CHÉP DỮ LIỆU hai chiều của migration CONTRACT `2026_09_07_150000`.
 *
 * CSDL test không có công trình nào nên chạy migration lúc dựng DB chỉ chứng minh được DDL.
 * Test này tự dựng dữ liệu, gọi `down()` (dựng lại cột cũ + chép ngược từ bảng mới) rồi
 * `up()` (chép lần cuối từ cột cũ — kể cả chỗ chép sót — rồi xoá cột), gọi `up()` hai lần
 * để chứng minh idempotent.
 *
 * KHÔNG dùng `DatabaseTransactions`: có DDL, MariaDB không rollback DDL. Tự dọn ở `tearDown()`
 * và luôn trả lược đồ về trạng thái CONTRACT.
 */
final class DropLegacySiteColumnsMigrationTest extends TestCase
{
    private const SITE_MIRRORED = 991101;

    private const SITE_LEGACY_ONLY = 991102;

    private const COMPANY = 998501;

    /**
     * Laravel chỉ chuyển sang DB riêng của worker (`…_test_N`) cho test dùng trait giao dịch;
     * test này có DDL nên không dùng trait → phải tự chuyển, kẻo ALTER `sites` trên DB gốc
     * đụng worker khác đang chạy cùng lúc (đã gặp: gãy giữa chừng, để lại bảng nửa vời).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $token = ParallelTesting::token();
        if ($token) {
            $connection = (string) config('database.default');
            $database = (string) config("database.connections.{$connection}.database");
            if (! str_contains($database, '_test_')) {
                config()->set("database.connections.{$connection}.database", $database.'_test_'.$token);
                DB::purge($connection);
            }
        }
    }

    protected function tearDown(): void
    {
        // Dọn dòng TRƯỚC rồi mới trả lược đồ về CONTRACT — để nếu up() gãy thì dữ liệu seed không sót lại.
        $ids = [self::SITE_MIRRORED, self::SITE_LEGACY_ONLY];
        DB::table('site_quotes')->whereIn('site_id', $ids)->delete();
        DB::table('site_warranty_reminders')->whereIn('site_id', $ids)->delete();
        DB::table('sites')->whereIn('id', $ids)->delete();
        $this->migration()->up();
        SchemaCache::flush();

        parent::tearDown();
    }

    public function test_down_dung_lai_cot_cu_tu_bang_moi_va_up_chep_not_roi_xoa(): void
    {
        $this->assertFalse(Schema::hasColumn('sites', 'quote_no'), 'lược đồ test phải đang ở trạng thái CONTRACT');

        $now = now();
        DB::table('sites')->insert([
            ['id' => self::SITE_MIRRORED, 'name' => 'Da chep', 'company_id' => self::COMPANY, 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::SITE_LEGACY_ONLY, 'name' => 'Chi cot cu', 'company_id' => self::COMPANY, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $money = ['subtotal' => 0, 'discount_amount' => 0, 'vat_percent' => 0, 'vat_amount' => 0, 'created_at' => $now, 'updated_at' => $now];
        DB::table('site_quotes')->insert([
            array_merge($money, ['site_id' => self::SITE_MIRRORED, 'version' => 1, 'code' => 'BG-1', 'customer_company' => null, 'grand_total' => 100]),
            array_merge($money, ['site_id' => self::SITE_MIRRORED, 'version' => 2, 'code' => 'BG-2', 'customer_company' => 'Cong ty 2', 'grand_total' => 200]),
        ]);
        DB::table('site_warranty_reminders')->insert([
            ['site_id' => self::SITE_MIRRORED, 'sequence' => 1, 'remind_at' => '2027-01-01', 'created_at' => $now, 'updated_at' => $now],
            ['site_id' => self::SITE_MIRRORED, 'sequence' => 3, 'remind_at' => '2027-03-03', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $migration = $this->migration();

        // --- down(): cột cũ trở lại và mang giá trị của bản version cao nhất / các mốc.
        $migration->down();
        SchemaCache::flush();
        $this->assertTrue(Schema::hasColumn('sites', 'quote_no') && Schema::hasColumn('sites', 'warranty_reminder_3_at'));

        $mirrored = DB::table('sites')->find(self::SITE_MIRRORED);
        $this->assertSame(['BG-2', 'Cong ty 2', 200.0], [$mirrored->quote_no, $mirrored->quote_customer_company, (float) $mirrored->quote_grand_total]);
        $this->assertSame(['2027-01-01', null, '2027-03-03'], [$mirrored->warranty_reminder_1_at, $mirrored->warranty_reminder_2_at, $mirrored->warranty_reminder_3_at]);
        $legacyOnly = DB::table('sites')->find(self::SITE_LEGACY_ONLY);
        $this->assertSame([null, 0.0], [$legacyOnly->quote_no, (float) $legacyOnly->quote_grand_total]);

        // --- Mô phỏng dữ liệu chỉ có ở cột cũ (như khi ghi song song từng lỗi ở đâu đó).
        DB::table('sites')->where('id', self::SITE_LEGACY_ONLY)->update([
            'quote_no' => 'BG-B', 'quote_customer_company' => 'Cong ty B', 'quote_grand_total' => 50, 'warranty_reminder_2_at' => '2029-02-02',
        ]);
        DB::table('sites')->where('id', self::SITE_MIRRORED)->update([
            'quote_grand_total' => 250, 'warranty_reminder_2_at' => '2027-02-02', 'warranty_reminder_3_at' => null,
        ]);

        // --- up(): chép lần cuối rồi xoá cột.
        $migration->up();
        SchemaCache::flush();
        $this->assertFalse(Schema::hasColumn('sites', 'quote_no') || Schema::hasColumn('sites', 'warranty_reminder_1_at'));

        $created = DB::table('site_quotes')->where('site_id', self::SITE_LEGACY_ONLY)->get();
        $this->assertCount(1, $created, 'công trình có dữ liệu chỉ ở cột cũ được tạo bản v1');
        $this->assertSame([1, 'BG-B', 'Cong ty B', 50.0], [(int) $created[0]->version, $created[0]->code, $created[0]->customer_company, (float) $created[0]->grand_total]);
        $this->assertSame([2 => '2029-02-02'], DB::table('site_warranty_reminders')->where('site_id', self::SITE_LEGACY_ONLY)->pluck('remind_at', 'sequence')->all(), 'mốc 2 được tạo');

        $versions = DB::table('site_quotes')->where('site_id', self::SITE_MIRRORED)->orderBy('version')->pluck('grand_total', 'version')->map(fn ($v): float => (float) $v)->all();
        $this->assertSame([1 => 100.0, 2 => 250.0], $versions, 'chỉ bản version cao nhất nhận giá trị cột cũ');
        $this->assertSame(
            [1 => '2027-01-01', 2 => '2027-02-02'],
            DB::table('site_warranty_reminders')->where('site_id', self::SITE_MIRRORED)->orderBy('sequence')->pluck('remind_at', 'sequence')->all(),
            'mốc 2 thêm, mốc 3 xoá theo cột cũ'
        );

        // --- up() lần nữa khi cột đã xoá: không lỗi, không đổi gì.
        $migration->up();
        $this->assertSame(2, DB::table('site_quotes')->where('site_id', self::SITE_MIRRORED)->count());
    }

    private function migration(): Migration
    {
        return require base_path('database/migrations/2026_09_07_150000_drop_legacy_quote_and_reminder_columns_from_sites.php');
    }
}
