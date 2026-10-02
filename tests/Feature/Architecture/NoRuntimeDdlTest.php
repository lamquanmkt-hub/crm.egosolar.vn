<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Mã ứng dụng KHÔNG được đổi lược đồ lúc chạy — chỉ migration mới được.
 *
 * ## Vì sao
 * Trước đây controller tự `Schema::create()` khi thấy thiếu bảng. Nghe thì
 * "tự lành", thực tế:
 *
 * - Lược đồ production đi trước repo, dựng lại môi trường từ repo là thiếu bảng.
 * - Mỗi request đều phải dò `information_schema` (đợt e3bf28d gỡ 1.455 truy vấn
 *   kiểu này).
 * - DDL ngầm làm hỏng cache lược đồ; {@see \App\Support\SchemaCache} phải tự huỷ
 *   khi bắt gặp câu DDL.
 * - Người sửa bảng không biết ai đã tạo nó, cột nào là chính thức.
 *
 * Commit b9a997d đã gỡ 946 dòng / 18 method loại này. Test giữ cho nó không
 * quay lại.
 *
 * ## Cạm bẫy khi tự viết mẫu tìm
 * `Schema::table` khớp nhầm `CommissionSchema::tableNames`. Phải chặn ký tự chữ
 * ngay TRƯỚC `Schema` — đã vấp đúng lỗi này lúc rà.
 */
final class NoRuntimeDdlTest extends TestCase
{
    /** @var array<string, string> mẫu => mô tả */
    private const FORBIDDEN = [
        '/(?<![A-Za-z0-9_])(?:\\\\)?Schema::(?:create|createDatabase|table|drop|dropIfExists|dropColumns|rename)\s*\(/' => 'Schema::create/table/drop/rename',
        '/(?<![A-Za-z0-9_])(?:\\\\)?Schema::connection\s*\([^)]*\)\s*->\s*(?:create|table|drop)/' => 'Schema::connection()->create/table/drop',
        '/->\s*getSchemaBuilder\s*\(\s*\)\s*->\s*(?:create|table|drop|dropIfExists|rename)\s*\(/' => 'getSchemaBuilder()->create/table/drop',
        '/(?<![A-Za-z0-9_])DB::(?:statement|unprepared)\s*\(\s*[\'"]\s*(?:CREATE|ALTER|DROP|RENAME|TRUNCATE)\s/i' => 'DB::statement("CREATE/ALTER/DROP ...")',
        '/(?<![A-Za-z0-9_])Artisan::call\s*\(\s*[\'"]migrate/' => "Artisan::call('migrate')",
    ];

    /**
     * Ngoại lệ tường minh kèm lý do — không nới mẫu tìm.
     *
     * @var array<string, string>
     */
    private const ALLOWED = [];

    public function test_khong_co_ddl_luc_chay_trong_app_va_routes(): void
    {
        $offenders = [];

        foreach ($this->phpFiles() as $file) {
            $relative = str_replace(base_path().'/', '', $file);

            if (isset(self::ALLOWED[$relative])) {
                continue;
            }

            $source = (string) file_get_contents($file);

            foreach (self::FORBIDDEN as $pattern => $label) {
                if (! preg_match_all($pattern, $source, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($m[0] as [$_, $offset]) {
                    $offenders[] = sprintf('%s:%d  (%s)',
                        $relative, substr_count($source, "\n", 0, $offset) + 1, $label);
                }
            }
        }

        $this->assertSame([], $offenders,
            "Có DDL lúc chạy trong mã ứng dụng:\n".implode("\n", $offenders)."\n\n".
            "Chuyển sang migration trong database/migrations. Nếu cần dò lược đồ để\n".
            'chạy được với CSDL cũ thì dùng `SchemaCache::hasTable/hasColumn` (chỉ ĐỌC).');
    }

    /** Dò lược đồ kiểu ĐỌC vẫn được phép — chốt lại để test trên không bị siết quá tay. */
    public function test_van_cho_phep_do_luoc_do_kieu_doc(): void
    {
        $source = (string) file_get_contents(app_path('Support/SchemaCache.php'));

        $this->assertStringContainsString('public static function hasTable', $source);
        $this->assertStringContainsString('public static function hasColumn', $source);
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = [];

        foreach ([app_path(), base_path('routes')] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }
}
