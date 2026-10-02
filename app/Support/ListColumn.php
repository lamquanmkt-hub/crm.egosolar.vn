<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * So khớp CHÍNH XÁC một phần tử nằm trong cột lưu danh sách (CSV hoặc JSON array).
 *
 * ## Vấn đề đang sửa
 * Vài bảng còn vi phạm 1NF: nhét nhiều giá trị vào MỘT cột
 * (`solar_maintenance_schedules.assigned_user_ids` lưu id,
 * `content_calendars.assignees` lưu TÊN người). Code cũ tìm bằng
 * `LIKE '%<giá trị>%'` — **sai kết quả**:
 *
 * - Với id: user 5 khớp luôn bản ghi của user 15, 25, 50, 105...
 * - Với tên: dashboard so id người dùng vào danh sách TÊN, nên chỉ khớp khi
 *   tên tình cờ chứa dãy số đó — gần như luôn sai.
 *
 * Ngoài ra `LIKE` mở đầu bằng `%` không dùng được index nên luôn quét toàn bảng.
 *
 * ## Cách khắc phục
 * Chuẩn hoá giá trị cột về dạng `,a,b,c,` rồi tìm chuỗi `,<giá trị>,` — khớp
 * trọn phần tử, đúng cho cả hai định dạng đang có trong DB:
 *   - JSON:  `[3,5,7]` / `["Tên A","Tên B"]`
 *   - CSV:   `3, 5, 7` / `Tên A, Tên B`
 *
 * ## Giới hạn (cố ý)
 * Vẫn quét toàn bảng — biểu thức trên cột không dùng được index. Đây chỉ là lớp
 * vá ĐÚNG-ĐẮN cho dữ liệu chưa chuẩn hoá; đường đi đúng là bảng quan hệ con
 * (xem DB_NORMALIZATION_AUDIT.md), và người gọi NÊN ưu tiên bảng đó khi có.
 */
final class ListColumn
{
    /**
     * Điều kiện "id nằm trong cột danh sách id".
     *
     * @param  string  $column  Định danh cột do lập trình viên viết cứng
     * @return array{0: string, 1: list<string>} [câu SQL, các binding]
     */
    public static function containsId(string $column, int $id): array
    {
        // Id không chứa khoảng trắng nên bỏ hết space cho gọn.
        $normalized = self::stripWrappers($column, stripSpaces: true);

        return ["INSTR({$normalized}, ?) > 0", [','.$id.',']];
    }

    /**
     * Điều kiện "chuỗi nằm trong cột danh sách chuỗi" (vd danh sách tên người).
     *
     * KHÔNG bỏ khoảng trắng bên trong phần tử (tên người có dấu cách), chỉ gộp
     * dấu phân cách `", "` về `","`.
     *
     * @return array{0: string, 1: list<string>} [câu SQL, các binding]
     */
    public static function containsText(string $column, string $value): array
    {
        $normalized = self::stripWrappers($column, stripSpaces: false);
        $value = trim($value);

        return ["INSTR({$normalized}, ?) > 0", [','.$value.',']];
    }

    /**
     * Biểu thức SQL chuẩn hoá cột danh sách về dạng `,phần tử,phần tử,`.
     */
    private static function stripWrappers(string $column, bool $stripSpaces): string
    {
        self::assertSafeColumn($column);

        $expression = "COALESCE({$column}, '')";

        foreach (['[', ']', '"'] as $wrapper) {
            $expression = "REPLACE({$expression}, '{$wrapper}', '')";
        }

        $expression = $stripSpaces
            ? "REPLACE({$expression}, ' ', '')"
            : "REPLACE({$expression}, ', ', ',')";

        return "CONCAT(',', {$expression}, ',')";
    }

    /**
     * Chặn mọi thứ không phải định danh cột — không cho nối chuỗi lạ vào SQL.
     */
    private static function assertSafeColumn(string $column): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            throw new InvalidArgumentException("Tên cột không hợp lệ: {$column}");
        }
    }
}
