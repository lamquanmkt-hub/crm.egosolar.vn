<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * `catch (\Throwable)` bọc quanh dò lược đồ / phân giải lớp KHÔNG được im lặng.
 *
 * ## Sự cố có thật
 * Trang hoa hồng rỗng suốt SÁU TUẦN. Lời gọi `app(...)` trỏ tới
 * CommissionEngineService bằng namespace cũ, `catch (\Throwable)` bọc ngoài nuốt
 * `BindingResolutionException` rồi gán mảng rỗng. Trang vẫn HTTP 200, log không
 * một dòng — nên không ai biết mà truy.
 *
 * ## Luật
 * Nếu khối `try` có dò lược đồ (`Schema::`, `SchemaCache::`, `hasTable`,
 * `hasColumn`, `DB::select`) hoặc phân giải lớp (`app(`, `resolve(`), thì khối
 * `catch` phải LÀM MỘT TRONG HAI:
 *
 * - ghi lại: `Log::`, `logger(`, `report(`, `throw`, hoặc {@see \App\Support\ProbeFailureLog}
 * - hoặc báo cho người dùng: `back()`, `redirect(`, `abort(`, `response()`
 *
 * Rơi về giá trị dự phòng vẫn được — trang không nên trắng chỉ vì thiếu một cột.
 * Nhưng rơi IM LẶNG thì không: khi số liệu sai sẽ không có gì để lần.
 *
 * Đợt 2026-09-04 đã xử lý 24 chỗ như vậy.
 */
final class SilentProbeCatchTest extends TestCase
{
    private const LOGGING = '/Log::|logger\(|report\(|throw\b|logError|logException|->log\(|ProbeFailureLog/i';

    private const VISIBLE = "/back\(\)|redirect\(|abort\(|response\(\)|->with\('error'|withErrors/";

    private const PROBE = '/Schema::|SchemaCache::|hasTable|hasColumn|getColumnListing|DB::select|app\(|resolve\(/';

    /**
     * Chỗ được miễn, kèm lý do — phải nêu tường minh, không dùng ký tự đại diện.
     *
     * @var array<string, string>
     */
    private const ALLOWED = [
        // Trả nguyên thông điệp lỗi ra mảng kết quả cho người gọi đọc.
        'app/Services/Debug/TableMetadataReader.php' => 'trả lỗi ra kết quả, không nuốt',
    ];

    public function test_khong_con_catch_im_lang_boc_quanh_do_luoc_do(): void
    {
        $silent = [];

        foreach ($this->phpFiles() as $file) {
            $relative = str_replace(base_path().'/', '', $file);

            if (isset(self::ALLOWED[$relative])) {
                continue;
            }

            $source = (string) file_get_contents($file);

            foreach ($this->catchBlocks($source) as [$offset, $catchBody, $tryBody]) {
                if (preg_match(self::LOGGING, $catchBody) || preg_match(self::VISIBLE, $catchBody)) {
                    continue;
                }

                if (! preg_match(self::PROBE, $tryBody)) {
                    continue;
                }

                $line = substr_count($source, "\n", 0, $offset) + 1;
                $silent[] = $relative.':'.$line;
            }
        }

        $this->assertSame([], $silent,
            "`catch (\\Throwable)` nuốt lỗi im lặng quanh phần dò lược đồ:\n".
            implode("\n", $silent)."\n\n".
            "Thêm `ProbeFailureLog::warn('Lớp::method', \$e);` vào đầu khối catch,\n".
            'hoặc báo lỗi cho người dùng. Giữ nguyên giá trị dự phòng phía sau.');
    }

    /**
     * @return list<array{0: int, 1: string, 2: string}> [vị trí, thân catch, thân try]
     */
    private function catchBlocks(string $source): array
    {
        preg_match_all('/catch\s*\(\s*\\\\?Throwable\s+\$\w+\s*\)\s*\{/', $source, $m, PREG_OFFSET_CAPTURE);

        $found = [];

        foreach ($m[0] as [$match, $offset]) {
            $open = $offset + strlen($match) - 1;
            $depth = 0;
            $end = $open;

            for ($i = $open, $len = strlen($source); $i < $len; $i++) {
                if ($source[$i] === '{') {
                    $depth++;
                } elseif ($source[$i] === '}') {
                    $depth--;

                    if ($depth === 0) {
                        $end = $i;
                        break;
                    }
                }
            }

            $tryStart = strrpos(substr($source, 0, $offset), 'try');

            $found[] = [
                $offset,
                substr($source, $open, $end - $open + 1),
                $tryStart === false ? '' : substr($source, $tryStart, $offset - $tryStart),
            ];
        }

        return $found;
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
