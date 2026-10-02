<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Support\SchemaCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Tests\TestCase;

/**
 * Migration thu hẹp 4 cột varchar của `sites` (2026_09_07_170000): chốt chặn không mất dữ liệu
 * phải từ chối khi có giá trị dài hơn cỡ mới và KHÔNG đổi gì; hai chiều up/down đảo được.
 *
 * KHÔNG dùng `DatabaseTransactions` (có DDL); tự chuyển sang DB riêng của worker khi chạy song song
 * và tự dọn — cùng lý do với DropLegacySiteColumnsMigrationTest.
 */
final class ShrinkSiteVarcharColumnsMigrationTest extends TestCase
{
    private const SITE = 991201;

    private const COMPANY = 998501;

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
        DB::table('sites')->where('id', self::SITE)->delete();
        $this->migration()->up();
        SchemaCache::flush();

        parent::tearDown();
    }

    public function test_tu_choi_khi_co_gia_tri_dai_hon_va_dao_duoc_hai_chieu(): void
    {
        $this->assertSame([50, 120, 120, 120], $this->lengths(), 'lược đồ test đã ở trạng thái thu hẹp');

        $migration = $this->migration();
        $migration->down();
        $this->assertSame([255, 255, 255, 255], $this->lengths(), 'down() nới lại 255');

        DB::table('sites')->insert([
            'id' => self::SITE, 'name' => 'Dai qua', 'company_id' => self::COMPANY,
            'contact_phone' => str_repeat('9', 60), 'contact_name' => str_repeat('a', 120),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $migration->up();
            $this->fail('up() phải từ chối vì contact_phone dài 60 > 50');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sites.contact_phone', $e->getMessage());
            $this->assertStringContainsString('60 ký tự', $e->getMessage());
        }
        $this->assertSame([255, 255, 255, 255], $this->lengths(), 'từ chối thì không đổi gì');
        $this->assertSame(60, mb_strlen((string) DB::table('sites')->where('id', self::SITE)->value('contact_phone')), 'dữ liệu nguyên vẹn');

        DB::table('sites')->where('id', self::SITE)->update(['contact_phone' => '0909 000 111']);
        $migration->up();
        $this->assertSame([50, 120, 120, 120], $this->lengths(), 'giá trị vừa cỡ (contact_name đúng 120) → thu hẹp được');
        $this->assertSame(str_repeat('a', 120), DB::table('sites')->where('id', self::SITE)->value('contact_name'));

        $migration->up();
        $this->assertSame([50, 120, 120, 120], $this->lengths(), 'chạy lại không đổi gì');
    }

    /** @return list<int> contact_phone, contact_name, technician_name, monitoring_account */
    private function lengths(): array
    {
        return array_map(
            fn (string $column): int => (int) DB::selectOne(
                'SELECT character_maximum_length AS length FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                ['sites', $column],
            )->length,
            ['contact_phone', 'contact_name', 'technician_name', 'monitoring_account'],
        );
    }

    private function migration(): Migration
    {
        return require base_path('database/migrations/2026_09_07_170000_shrink_oversized_varchar_columns_on_sites.php');
    }
}
