<?php

declare(strict_types=1);

namespace App\Services\Debug;

use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Dò dữ liệu đã lệch ở các cột phi chuẩn hoá (vi phạm 3NF loại B/C).
 *
 * ## Vì sao cần
 * Một sự thật lưu ở hai chỗ thì sớm muộn hai chỗ sẽ khác nhau. Kiểm toán
 * 2026-08-05 tìm thấy 17 cột phụ thuộc bắc cầu; phần là snapshot chứng từ (giữ
 * nguyên là ĐÚNG), phần là bản sao cho tiện đọc — loại sau cần canh chừng.
 *
 * Lớp này CHỈ ĐỌC. Nó không tự sửa dữ liệu: quyết định lấy bản nào làm chuẩn là
 * việc của nghiệp vụ, nhất là khi hai bản mâu thuẫn nhau.
 *
 * ## Hai kiểu kiểm tra
 * - `value`: cột cache phải bằng giá trị nguồn tra qua khoá ngoại.
 * - `mapping`: cột varchar và khoá ngoại phải ánh xạ 1-1. Dùng khi app vẫn đang
 *   tin cột varchar — lúc đó so khớp chuỗi trực tiếp sẽ toàn báo giả vì hai bên
 *   viết tắt/viết hoa khác nhau, cái cần bắt là MÂU THUẪN (cùng một chuỗi trỏ
 *   về hai id khác nhau, hoặc ngược lại).
 *
 * @see config/ego.php khoá `denormalized_columns`
 * @see DB_NORMALIZATION_AUDIT.md
 */
final class DenormalizedColumnChecker
{
    /** Số dòng ví dụ tối đa trả về cho mỗi phát hiện. */
    private const SAMPLE_LIMIT = 5;

    /**
     * Chạy toàn bộ cấu hình và trả về báo cáo.
     *
     * @return list<array{table: string, column: string, check: string, total: int, mismatched: int, orphan: int, samples: list<string>, note: string, skipped: string|null}>
     */
    public function run(): array
    {
        $report = [];

        foreach ((array) config('ego.denormalized_columns', []) as $definition) {
            $report[] = $this->inspect((array) $definition);
        }

        return $report;
    }

    /**
     * Kiểm tra một cặp cột theo định nghĩa.
     *
     * @param  array<string, mixed>  $definition
     * @return array{table: string, column: string, check: string, total: int, mismatched: int, orphan: int, samples: list<string>, note: string, skipped: string|null}
     */
    private function inspect(array $definition): array
    {
        $table = (string) ($definition['table'] ?? '');
        $column = (string) ($definition['column'] ?? '');
        $foreignKey = (string) ($definition['foreign_key'] ?? '');
        $references = (string) ($definition['references'] ?? '');
        $sourceColumn = (string) ($definition['source_column'] ?? '');
        $check = (string) ($definition['check'] ?? 'value');

        $result = [
            'table' => $table,
            'column' => $column,
            'check' => $check,
            'total' => 0,
            'mismatched' => 0,
            'orphan' => 0,
            'samples' => [],
            'note' => (string) ($definition['note'] ?? ''),
            'skipped' => null,
        ];

        if (! SchemaCache::hasColumns($table, [$column, $foreignKey]) || ! SchemaCache::hasColumn($references, $sourceColumn)) {
            $result['skipped'] = 'Thiếu bảng hoặc cột — bỏ qua.';

            return $result;
        }

        $result['total'] = (int) DB::table($table)->count();
        $result['orphan'] = (int) DB::table($table)
            ->whereNull($foreignKey)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->count();

        return $check === 'mapping'
            ? $this->checkMapping($result, $table, $column, $foreignKey)
            : $this->checkValue($result, $table, $column, $foreignKey, $references, $sourceColumn);
    }

    /**
     * Cột cache phải bằng giá trị nguồn.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function checkValue(
        array $result,
        string $table,
        string $column,
        string $foreignKey,
        string $references,
        string $sourceColumn,
    ): array {
        $rows = DB::table($table.' as t')
            ->join($references.' as r', 'r.id', '=', 't.'.$foreignKey)
            ->whereRaw("TRIM(COALESCE(t.{$column}, '')) <> TRIM(COALESCE(r.{$sourceColumn}, ''))")
            ->select('t.id', 't.'.$column.' as cached', 'r.'.$sourceColumn.' as source')
            ->limit(self::SAMPLE_LIMIT)
            ->get();

        $result['mismatched'] = (int) DB::table($table.' as t')
            ->join($references.' as r', 'r.id', '=', 't.'.$foreignKey)
            ->whereRaw("TRIM(COALESCE(t.{$column}, '')) <> TRIM(COALESCE(r.{$sourceColumn}, ''))")
            ->count();

        $result['samples'] = $rows
            ->map(static fn ($row): string => sprintf(
                '#%s: lưu "%s" ≠ nguồn "%s"',
                $row->id,
                (string) $row->cached,
                (string) $row->source,
            ))
            ->all();

        return $result;
    }

    /**
     * Cột varchar và khoá ngoại phải ánh xạ 1-1.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function checkMapping(array $result, string $table, string $column, string $foreignKey): array
    {
        $pairs = DB::table($table)
            ->select($column.' as text', $foreignKey.' as fk', DB::raw('COUNT(*) as n'))
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->whereNotNull($foreignKey)
            ->groupBy($column, $foreignKey)
            ->get();

        $idsPerText = [];
        $textsPerId = [];

        foreach ($pairs as $pair) {
            $text = (string) $pair->text;
            $fk = (int) $pair->fk;

            $idsPerText[$text][$fk] = (int) $pair->n;
            $textsPerId[$fk][$text] = (int) $pair->n;
        }

        $samples = [];
        $mismatched = 0;

        foreach ($idsPerText as $text => $ids) {
            if (count($ids) > 1) {
                $mismatched += array_sum($ids);
                $samples[] = sprintf(
                    'Chuỗi "%s" trỏ về %d id khác nhau: %s',
                    $text,
                    count($ids),
                    implode(', ', array_map(
                        static fn (int $id, int $n): string => "id={$id} ({$n} dòng)",
                        array_keys($ids),
                        $ids,
                    )),
                );
            }
        }

        foreach ($textsPerId as $id => $texts) {
            if (count($texts) > 1) {
                $samples[] = sprintf(
                    'id=%d được gọi bằng %d chuỗi khác nhau: %s',
                    $id,
                    count($texts),
                    implode(' | ', array_keys($texts)),
                );
            }
        }

        $result['mismatched'] = $mismatched;
        $result['samples'] = array_slice($samples, 0, self::SAMPLE_LIMIT);

        return $result;
    }
}
