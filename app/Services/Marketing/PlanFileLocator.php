<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Tìm tệp đính kèm của kế hoạch marketing khi KHÔNG biết nó nằm ở bảng nào.
 *
 * ## Vì sao phải dò mò như vậy
 * Qua nhiều đời, tệp đính kèm được lưu ở những bảng khác nhau
 * (`mkt_plan_attachments`, `marketing_plan_files`, `attachments`, `files`...)
 * với tên cột khác nhau (`path`, `file_path`, `url`, `filename`...). Không có
 * bảng nào là nguồn sự thật, nên endpoint xem trước phải quét rồi CHẤM ĐIỂM để
 * đoán bản ghi nào đúng.
 *
 * Đây là cách chữa triệu chứng, không phải thiết kế: gốc rễ là dữ liệu đính kèm
 * chưa được gom về một bảng. Ghi lại ở đây để ai đọc sau không tưởng là chủ ý.
 *
 * Trước 2026-08-05 toàn bộ đoạn này nằm trong một closure 344 dòng ở
 * `routes/marketing.php`.
 */
final class PlanFileLocator
{
    /**
     * Bảng ưu tiên quét trước — đặt lên đầu để thường chỉ cần vài lượt là thấy.
     *
     * @var list<string>
     */
    private const PRIORITY_TABLES = [
        'mkt_plan_attachments',
        'marketing_plan_attachments',
        'marketing_plan_files',
        'marketing_plan_uploads',
        'marketing_files',
        'plan_files',
        'attachments',
        'files',
        'media',
    ];

    /**
     * Cột có thể chứa đường dẫn tệp.
     *
     * @var list<string>
     */
    private const PATH_COLUMNS = [
        'path', 'file_path', 'filepath', 'storage_path', 'stored_path', 'full_path',
        'url', 'file', 'attachment', 'filename', 'file_name', 'original_name', 'name',
    ];

    /**
     * Cột có thể chứa tên hiển thị của tệp.
     *
     * @var list<string>
     */
    private const NAME_COLUMNS = ['original_name', 'file_name', 'filename', 'name', 'title'];

    private const FILE_EXTENSION_PATTERN = '/\.(xlsx|xls|ods|csv|pdf|png|jpg|jpeg|webp|gif|docx?|pptx?)($|\?)/i';

    public function __construct(private readonly LocalFilePathResolver $paths) {}

    /**
     * Tìm tệp khớp nhất, hoặc null nếu không thấy.
     *
     * @param  int  $id  Id bản ghi tệp
     * @param  string  $desiredName  Tên tệp người dùng mong đợi (từ query `name`)
     * @param  int|string|null  $planId  Id kế hoạch, dùng để tăng điểm khớp
     */
    public function locate(int $id, string $desiredName = '', int|string|null $planId = null): ?LocatedFile
    {
        $desiredBase = mb_strtolower(pathinfo($desiredName, PATHINFO_FILENAME), 'UTF-8');
        $desiredExt = mb_strtolower(pathinfo($desiredName, PATHINFO_EXTENSION), 'UTF-8');

        $candidates = [];

        foreach ($this->tablesToScan() as $table) {
            foreach ($this->candidatesFromTable($table, $id, $desiredName, $desiredBase, $desiredExt, $planId) as $candidate) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (LocatedFile $a, LocatedFile $b): int => $b->score <=> $a->score);

        return $candidates[0];
    }

    /**
     * Danh sách bảng cần quét: ưu tiên trước, rồi tới mọi bảng còn lại.
     *
     * @return list<string>
     */
    private function tablesToScan(): array
    {
        $all = [];

        try {
            foreach (DB::select('SHOW TABLES') as $row) {
                $values = (array) $row;
                $all[] = (string) reset($values);
            }
        } catch (\Throwable) {
            $all = [];
        }

        return array_values(array_unique(array_merge(self::PRIORITY_TABLES, $all)));
    }

    /**
     * Các ứng viên tìm được trong một bảng.
     *
     * @return list<LocatedFile>
     */
    private function candidatesFromTable(
        string $table,
        int $id,
        string $desiredName,
        string $desiredBase,
        string $desiredExt,
        int|string|null $planId,
    ): array {
        try {
            if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'id')) {
                return [];
            }

            $row = DB::table($table)->where('id', $id)->first();

            if ($row === null) {
                return [];
            }

            $values = (array) $row;
            $baseScore = $this->scoreRow($table, $values, $desiredBase, $desiredExt, $planId);

            $found = [];

            foreach ($this->pathsIn($values) as $path) {
                $resolved = $this->paths->resolve($path);

                if ($resolved === null) {
                    continue;
                }

                $name = $this->displayName($values, $desiredName, $path);
                $extension = mb_strtolower(pathinfo($name !== '' ? $name : $path, PATHINFO_EXTENSION), 'UTF-8');

                $found[] = new LocatedFile(
                    score: $baseScore + $this->extensionBonus($extension, $desiredExt),
                    table: $table,
                    name: $name,
                    url: $resolved['url'],
                    realPath: $resolved['real'],
                );
            }

            return $found;
        } catch (\Throwable) {
            // Bảng lạ, thiếu quyền, kiểu dữ liệu không đọc được... bỏ qua và đi tiếp:
            // một bảng hỏng không được làm chết cả trang xem trước.
            return [];
        }
    }

    /**
     * Điểm khớp của một bản ghi, chưa tính phần mở rộng tệp.
     *
     * @param  array<string, mixed>  $values
     */
    private function scoreRow(string $table, array $values, string $desiredBase, string $desiredExt, int|string|null $planId): int
    {
        $score = 0;

        // Tên bảng càng "marketing" càng nhiều khả năng đúng.
        $score += str_contains($table, 'mkt') ? 180 : 0;
        $score += str_contains($table, 'marketing') ? 150 : 0;
        $score += str_contains($table, 'plan') ? 120 : 0;

        if ($planId !== null && $planId !== '') {
            foreach (['plan_id', 'marketing_plan_id', 'mkt_plan_id'] as $field) {
                if (isset($values[$field]) && (string) $values[$field] === (string) $planId) {
                    $score += 500;
                }
            }
        }

        $allText = mb_strtolower(implode(' ', array_map('strval', $values)), 'UTF-8');

        if ($desiredBase !== '' && str_contains($allText, $desiredBase)) {
            $score += 700;
        }

        if ($desiredExt !== '' && str_contains($allText, '.'.$desiredExt)) {
            $score += 500;
        }

        return $score;
    }

    /**
     * Thưởng/phạt theo phần mở rộng.
     *
     * Phạt nặng trường hợp người dùng muốn xem Excel mà bản ghi lại trỏ vào ảnh
     * (thường là ảnh thu nhỏ đi kèm) — trước đây hay chọn nhầm.
     */
    private function extensionBonus(string $extension, string $desiredExt): int
    {
        if ($desiredExt === '') {
            return 0;
        }

        $bonus = $extension === $desiredExt ? 1000 : 0;

        $wantsSpreadsheet = in_array($desiredExt, ['xlsx', 'xls', 'ods', 'csv'], true);
        $gotImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

        return $wantsSpreadsheet && $gotImage ? $bonus - 1000 : $bonus;
    }

    /**
     * Mọi đường dẫn có thể có trong một dòng dữ liệu.
     *
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function pathsIn(array $values): array
    {
        $paths = [];

        foreach (self::PATH_COLUMNS as $column) {
            if (! empty($values[$column])) {
                $paths[] = trim((string) $values[$column]);
            }
        }

        // Quét thêm mọi cột khác: có bảng đặt tên cột hoàn toàn khác quy ước.
        foreach ($values as $value) {
            $text = trim((string) $value);

            if ($text !== '' && preg_match(self::FILE_EXTENSION_PATTERN, $text) === 1) {
                $paths[] = $text;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Tên hiển thị: ưu tiên tên người dùng yêu cầu, rồi tới cột tên có đuôi tệp.
     *
     * @param  array<string, mixed>  $values
     */
    private function displayName(array $values, string $desiredName, string $path): string
    {
        foreach (self::NAME_COLUMNS as $column) {
            if (empty($values[$column])) {
                continue;
            }

            if (preg_match('/\.(xlsx|xls|ods|csv|pdf|png|jpg|jpeg|webp|gif|docx?|pptx?)$/i', (string) $values[$column]) === 1) {
                return basename((string) $values[$column]);
            }
        }

        return $desiredName !== '' ? $desiredName : basename($path);
    }
}
