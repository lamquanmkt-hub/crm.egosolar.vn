<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Bộ nhớ đệm kết quả kiểm tra schema (`hasTable` / `hasColumn`).
 *
 * ## Vì sao cần
 * `Schema::hasTable()` / `Schema::hasColumn()` của Laravel KHÔNG cache: mỗi lần
 * gọi là một truy vấn `information_schema`. Codebase này có hơn 1.400 lời gọi
 * như vậy (di sản của việc code phải chạy được trên nhiều phiên bản schema),
 * riêng sidebar — render ở MỌI trang — đã tốn hơn 20 truy vấn chỉ để hỏi
 * "bảng/cột này có tồn tại không". Trên MariaDB production, truy vấn
 * `information_schema` với hàng trăm bảng là loại truy vấn đắt.
 *
 * ## Hai tầng
 * 1. **Trong request** — mảng tĩnh, O(1), luôn bật.
 * 2. **Giữa các request** — cache của Laravel, bật khi `ego.schema_cache_ttl > 0`.
 *    Mỗi bảng lưu đúng một khoá chứa danh sách cột.
 *
 * ## Huỷ cache
 * - Chạy migration (`MigrationsEnded`) → `flush()`.
 * - Câu lệnh DDL lúc chạy → `flush()`. (Từ 2026-09-02 controller KHÔNG còn tạo
 *   bảng lúc chạy nữa; nhánh này giữ lại cho migration và lệnh artisan.)
 * `flush()` tăng số phiên bản trong cache nên vô hiệu hoá toàn bộ khoá cũ một
 * lần, không cần biết trước đã lưu những bảng nào (chạy được với mọi cache
 * store, kể cả store không hỗ trợ tag như file).
 */
final class SchemaCache
{
    /** Tiền tố khoá cache. */
    private const CACHE_PREFIX = 'ego:schema';

    /** @var array<string, bool> tên bảng => có tồn tại */
    private static array $tables = [];

    /** @var array<string, array<string, bool>> tên bảng => [tên cột => có tồn tại] */
    private static array $columns = [];

    /** Số phiên bản schema đang dùng trong request này (null = chưa đọc). */
    private static ?int $version = null;

    /**
     * Bảng có tồn tại không.
     *
     * Nếu danh sách cột của bảng đã nạp trước đó thì trả lời ngay: bảng có cột
     * nghĩa là bảng tồn tại.
     */
    public static function hasTable(string $table): bool
    {
        if (isset(self::$tables[$table])) {
            return self::$tables[$table];
        }

        return self::columnMap($table) !== [];
    }

    /**
     * Cột có tồn tại trong bảng không.
     *
     * Chỉ nạp danh sách cột MỘT lần cho mỗi bảng: hỏi 10 cột của cùng một bảng
     * vẫn chỉ tốn 1 truy vấn thay vì 10.
     */
    public static function hasColumn(string $table, string $column): bool
    {
        return self::columnMap($table)[$column] ?? false;
    }

    /**
     * Toàn bộ tên cột của bảng (rỗng nếu bảng không tồn tại).
     *
     * @return list<string>
     */
    public static function columns(string $table): array
    {
        return array_keys(self::columnMap($table));
    }

    /**
     * Bảng có đủ TẤT CẢ các cột chỉ định không.
     *
     * @param  list<string>  $columns
     */
    public static function hasColumns(string $table, array $columns): bool
    {
        $map = self::columnMap($table);

        foreach ($columns as $column) {
            if (! isset($map[$column])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Xoá cache (cả hai tầng) — gọi sau migration hoặc khi schema đổi lúc chạy.
     */
    public static function flush(): void
    {
        self::$tables = [];
        self::$columns = [];

        if (self::ttl() > 0) {
            try {
                // ⚠️ KHÔNG dùng Cache::increment(): với cache store `database`
                // (đúng cấu hình production) increment trên khoá CHƯA tồn tại
                // trả false và không tạo gì cả — số phiên bản mãi là 0 nên
                // flush() sẽ không vô hiệu hoá được cache. Store `array` dùng
                // khi test lại tự tạo khoá, nên lỗi này ẩn hoàn toàn khỏi test.
                // Đọc rồi ghi đè là cách chạy đúng trên MỌI store.
                $key = self::CACHE_PREFIX.':version';
                Cache::forever($key, (int) Cache::get($key, 0) + 1);
            } catch (Throwable) {
                // Cache hỏng thì tầng trong-request vẫn đúng, bỏ qua.
            }
        }

        self::$version = null;
    }

    /**
     * Câu lệnh SQL có làm thay đổi cấu trúc bảng không.
     *
     * Dùng để tự huỷ cache: repo này còn vài chỗ tạo bảng ngay lúc chạy
     * (`ensureSchema()` trong controller/service), nếu không huỷ thì trong
     * cùng request cache vẫn báo "bảng chưa tồn tại" sau khi đã tạo xong.
     */
    public static function isDdl(string $sql): bool
    {
        $normalized = ltrim($sql);

        foreach (['create table', 'alter table', 'drop table', 'rename table'] as $prefix) {
            if (stripos($normalized, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bản đồ cột của một bảng, nạp tối đa MỘT lần cho mỗi bảng mỗi request.
     *
     * @return array<string, true>
     */
    private static function columnMap(string $table): array
    {
        if (isset(self::$columns[$table])) {
            return self::$columns[$table];
        }

        $listing = self::cachedListing($table);

        if ($listing === null) {
            try {
                $listing = Schema::getColumnListing($table);
            } catch (Throwable) {
                // Không ghi nhớ khi lỗi kết nối — lần sau còn hỏi lại được.
                return [];
            }

            self::rememberListing($table, $listing);
        }

        self::$columns[$table] = array_fill_keys($listing, true);
        self::$tables[$table] = $listing !== [];

        return self::$columns[$table];
    }

    /**
     * Danh sách cột lấy từ cache liên-request (null = không có/không bật).
     *
     * @return list<string>|null
     */
    private static function cachedListing(string $table): ?array
    {
        if (self::ttl() <= 0) {
            return null;
        }

        try {
            $cached = Cache::get(self::cacheKey($table));
        } catch (Throwable) {
            return null;
        }

        return is_array($cached) ? array_values($cached) : null;
    }

    /**
     * Ghi danh sách cột vào cache liên-request.
     *
     * @param  list<string>  $listing
     */
    private static function rememberListing(string $table, array $listing): void
    {
        $ttl = self::ttl();

        if ($ttl <= 0) {
            return;
        }

        try {
            Cache::put(self::cacheKey($table), $listing, $ttl);
        } catch (Throwable) {
            // Không ghi được cache thì thôi, không ảnh hưởng tính đúng đắn.
        }
    }

    /** Khoá cache của một bảng, kèm số phiên bản để `flush()` vô hiệu hoá hàng loạt. */
    private static function cacheKey(string $table): string
    {
        return self::CACHE_PREFIX.':v'.self::version().':'.$table;
    }

    /** Số phiên bản schema hiện tại (tăng lên mỗi lần flush). */
    private static function version(): int
    {
        if (self::$version !== null) {
            return self::$version;
        }

        try {
            return self::$version = (int) Cache::get(self::CACHE_PREFIX.':version', 0);
        } catch (Throwable) {
            return self::$version = 0;
        }
    }

    /** Thời gian sống của cache liên-request, tính bằng giây (0 = tắt). */
    private static function ttl(): int
    {
        return max(0, (int) config('ego.schema_cache_ttl', 0));
    }
}
