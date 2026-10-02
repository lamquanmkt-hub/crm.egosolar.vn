<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ghi lại lỗi bị `catch` nuốt khi dò lược đồ hoặc phân giải lớp.
 *
 * ⚠️ Trong PHPDoc này KHÔNG viết tên lớp cũ dạng đầy đủ kèm `::class`:
 * ClassReferenceResolutionTest quét cả bình luận và sẽ báo đỏ vì lớp đó
 * không còn tồn tại (đã vấp khi viết tệp này).
 *
 * ## Vì sao cần
 * Trang hoa hồng rỗng suốt SÁU TUẦN vì lời gọi `app(...)` trỏ tới
 * CommissionEngineService bằng namespace CŨ (lớp đã chuyển sang
 * Services/CRM/Commission); `catch (\Throwable)` bọc ngoài nuốt
 * `BindingResolutionException` rồi gán mảng rỗng. Không một dòng log nào, trang
 * vẫn HTTP 200 — nên không ai biết.
 *
 * Rơi về giá trị dự phòng là hợp lý (trang không nên trắng vì thiếu một cột),
 * nhưng rơi IM LẶNG thì không: không có gì để truy khi số liệu sai.
 *
 * ## Chỉ log MỘT LẦN cho mỗi khoá trong một tiến trình
 * Vài chỗ gọi nằm trong global scope hoặc vòng lặp — mỗi truy vấn một dòng log
 * thì log ngập và mất luôn tác dụng. Một dòng đủ để biết mà truy.
 */
final class ProbeFailureLog
{
    /** @var array<string, true> khoá đã ghi trong tiến trình này */
    private static array $seen = [];

    /**
     * @param  string  $where  vị trí gọi, dạng `Lớp::method` — dùng làm khoá chống lặp
     * @param  array<string, mixed>  $context  dữ liệu giúp truy ngược
     */
    public static function warn(string $where, Throwable $e, array $context = []): void
    {
        if (isset(self::$seen[$where])) {
            return;
        }

        self::$seen[$where] = true;

        Log::warning('PROBE_FAILED', [
            'where' => $where,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ] + $context);
    }

    /** Cho test: quên các khoá đã ghi. */
    public static function forget(): void
    {
        self::$seen = [];
    }
}
