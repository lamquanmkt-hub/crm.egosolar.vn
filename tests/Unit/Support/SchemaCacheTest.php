<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SchemaCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SchemaCache phải trả lời ĐÚNG như Schema::* nhưng chỉ chạm DB một lần.
 */
final class SchemaCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        SchemaCache::flush();
    }

    protected function tearDown(): void
    {
        SchemaCache::flush();
        parent::tearDown();
    }

    /** Bảng có thật/không có thật đều trả lời đúng. */
    public function test_reports_table_existence_correctly(): void
    {
        $this->assertTrue(SchemaCache::hasTable('users'));
        $this->assertFalse(SchemaCache::hasTable('bang_khong_ton_tai_abc'));
    }

    /** Cột có thật/không có thật đều trả lời đúng. */
    public function test_reports_column_existence_correctly(): void
    {
        $this->assertTrue(SchemaCache::hasColumn('users', 'email'));
        $this->assertFalse(SchemaCache::hasColumn('users', 'cot_khong_ton_tai_abc'));
        $this->assertFalse(SchemaCache::hasColumn('bang_khong_ton_tai_abc', 'id'));
    }

    /** Hỏi lại cùng một bảng KHÔNG được chạm DB lần nữa. */
    public function test_table_check_hits_database_only_once(): void
    {
        SchemaCache::hasTable('users');

        $queries = $this->captureQueries(static function (): void {
            for ($i = 0; $i < 5; $i++) {
                SchemaCache::hasTable('users');
            }
        });

        $this->assertSame([], $queries);
    }

    /** Nhiều cột của cùng một bảng chỉ tốn ĐÚNG một truy vấn danh sách cột. */
    public function test_many_column_checks_on_same_table_cost_one_query(): void
    {
        $queries = $this->captureQueries(static function (): void {
            foreach (['id', 'name', 'email', 'password', 'created_at', 'khong_co'] as $column) {
                SchemaCache::hasColumn('users', $column);
            }
        });

        $columnListings = array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'column_name') || str_contains($sql, 'columns'),
        );

        $this->assertCount(
            1,
            $columnListings,
            'Danh sách cột phải chỉ nạp 1 lần cho mỗi bảng.',
        );
    }

    /** hasColumns yêu cầu đủ tất cả cột. */
    public function test_has_columns_requires_every_column(): void
    {
        $this->assertTrue(SchemaCache::hasColumns('users', ['id', 'email']));
        $this->assertFalse(SchemaCache::hasColumns('users', ['id', 'khong_co']));
    }

    /** columns() trả danh sách cột thật, rỗng nếu bảng không tồn tại. */
    public function test_columns_listing(): void
    {
        $this->assertContains('email', SchemaCache::columns('users'));
        $this->assertSame([], SchemaCache::columns('bang_khong_ton_tai_abc'));
    }

    /** flush() buộc lần hỏi kế tiếp phải chạm DB lại. */
    public function test_flush_forces_reload(): void
    {
        SchemaCache::hasTable('users');
        SchemaCache::flush();

        $queries = $this->captureQueries(static function (): void {
            SchemaCache::hasTable('users');
        });

        $this->assertNotSame([], $queries);
    }

    /**
     * Bật TTL: request sau dùng lại kết quả của request trước (mô phỏng bằng
     * cách xoá cache tĩnh nhưng giữ cache store).
     */
    public function test_cross_request_cache_avoids_database_when_ttl_enabled(): void
    {
        config(['ego.schema_cache_ttl' => 600]);
        SchemaCache::flush();

        SchemaCache::hasColumn('users', 'email');

        // Giả lập request mới: chỉ mất cache tĩnh, cache store vẫn còn.
        $this->resetStaticCacheOnly();

        $queries = $this->captureQueries(static function (): void {
            self::assertTrue(SchemaCache::hasColumn('users', 'email'));
            self::assertTrue(SchemaCache::hasTable('users'));
        });

        $this->assertSame([], $queries, 'Có TTL thì request sau không được chạm information_schema.');
    }

    /** flush() phải vô hiệu hoá cả cache liên-request. */
    public function test_flush_invalidates_cross_request_cache(): void
    {
        config(['ego.schema_cache_ttl' => 600]);
        SchemaCache::flush();

        SchemaCache::hasColumn('users', 'email');
        SchemaCache::flush();

        $queries = $this->captureQueries(static function (): void {
            SchemaCache::hasColumn('users', 'email');
        });

        $this->assertNotSame([], $queries, 'Sau flush phải đọc lại schema từ DB.');
    }

    /**
     * flush() phải vô hiệu hoá cache trên CẢ cache store `database` —
     * đúng cấu hình production (CACHE_STORE=database).
     *
     * Regression: bản đầu dùng Cache::increment(); với store `database`,
     * increment trên khoá chưa tồn tại trả false và không tạo gì, nên số phiên
     * bản mãi bằng 0 và cache schema KHÔNG BAO GIỜ bị huỷ sau migration.
     * Store `array` (dùng khi test) lại tự tạo khoá nên che mất lỗi này.
     */
    public function test_flush_invalidates_cache_on_database_store(): void
    {
        config([
            'ego.schema_cache_ttl' => 600,
            'cache.default' => 'database',
        ]);

        DB::table('cache')->where('key', 'like', '%ego:schema%')->delete();
        SchemaCache::flush();

        SchemaCache::hasColumn('users', 'email');
        $this->resetStaticCacheOnly();

        // Trước khi flush: đọc lại từ cache store, không chạm information_schema.
        // (Bản thân cache store `database` cũng chạy truy vấn bảng `cache`,
        // nên chỉ lọc đúng truy vấn schema.)
        $beforeFlush = array_filter(
            $this->captureQueries(static function (): void {
                SchemaCache::hasColumn('users', 'email');
            }),
            static fn (string $sql): bool => str_contains($sql, 'information_schema'),
        );

        $this->assertSame([], $beforeFlush);

        SchemaCache::flush();
        $this->resetStaticCacheOnly();

        $queries = $this->captureQueries(static function (): void {
            SchemaCache::hasColumn('users', 'email');
        });

        $this->assertNotSame(
            [],
            array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'information_schema')),
            'Sau flush phải đọc lại schema từ DB, kể cả khi dùng cache store database.',
        );
    }

    /** Nhận diện đúng câu lệnh DDL để tự huỷ cache. */
    public function test_detects_ddl_statements(): void
    {
        $this->assertTrue(SchemaCache::isDdl('create table `x` (id int)'));
        $this->assertTrue(SchemaCache::isDdl('  ALTER TABLE `x` ADD `y` int'));
        $this->assertTrue(SchemaCache::isDdl('drop table if exists `x`'));
        $this->assertFalse(SchemaCache::isDdl('select * from `x`'));
        $this->assertFalse(SchemaCache::isDdl('insert into `x` values (1)'));
    }

    /** Xoá cache tĩnh nhưng GIỮ cache store — mô phỏng request mới. */
    private function resetStaticCacheOnly(): void
    {
        foreach (['tables', 'columns'] as $property) {
            $reflection = new \ReflectionProperty(SchemaCache::class, $property);
            $reflection->setValue(null, []);
        }
    }

    /**
     * @return list<string>
     */
    private function captureQueries(callable $callback): array
    {
        $queries = [];

        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $callback();

        return $queries;
    }
}
