<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Support\ListColumn;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Regression cho lỗi so khớp id trong cột danh sách.
 *
 * Code cũ dùng `LIKE '%5%'` nên user 5 khớp luôn bản ghi của user 15/25/50 —
 * dashboard kỹ thuật hiện nhầm lịch của người khác. Test chạy thẳng trên
 * MariaDB để chắc chắn biểu thức SQL hoạt động đúng với cả JSON lẫn CSV.
 */
final class ListColumnTest extends TestCase
{
    use DatabaseTransactions;

    /** Khớp đúng phần tử, không khớp id chỉ trùng chuỗi con. */
    public function test_matches_whole_element_only(): void
    {
        $cases = [
            ['[3,5,7]', 5, true],
            ['[15,25,50]', 5, false],
            ['[5]', 5, true],
            ['[]', 5, false],
            ['3, 5, 7', 5, true],
            ['15,25,50', 5, false],
            ['["3","5"]', 5, true],
            [null, 5, false],
            ['[105]', 5, false],
            ['[3,5,7]', 7, true],
            ['[3,5,7]', 3, true],
        ];

        foreach ($cases as [$stored, $id, $expected]) {
            $this->assertSame(
                $expected,
                $this->listContains($stored, $id),
                sprintf('Giá trị %s với id %d phải trả %s', var_export($stored, true), $id, var_export($expected, true)),
            );
        }
    }

    /** Tên cột không hợp lệ bị từ chối (không cho nối chuỗi vào SQL). */
    public function test_rejects_unsafe_column_names(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ListColumn::containsId('col; DROP TABLE users', 1);
    }

    /** Danh sách TÊN: khớp trọn tên, không cắt theo chuỗi con. */
    public function test_matches_whole_name_element(): void
    {
        $cases = [
            ['["Nguyễn Văn A","Trần B"]', 'Nguyễn Văn A', true],
            ['["Nguyễn Văn A","Trần B"]', 'Nguyễn Văn', false],
            ['Nguyễn Văn A, Trần B', 'Trần B', true],
            ['Nguyễn Văn A, Trần B', 'Trần', false],
            [null, 'Trần B', false],
        ];

        foreach ($cases as [$stored, $name, $expected]) {
            $this->assertSame(
                $expected,
                $this->listContainsText($stored, $name),
                sprintf('Giá trị %s với tên "%s" phải trả %s', var_export($stored, true), $name, var_export($expected, true)),
            );
        }
    }

    /** Chạy biểu thức tên thật trên DB. */
    private function listContainsText(?string $stored, string $value): bool
    {
        [$sql, $bindings] = ListColumn::containsText('value', $value);

        $row = DB::selectOne(
            'SELECT ('.$sql.') AS matched FROM (SELECT ? AS value) AS t',
            [...$bindings, $stored],
        );

        return (bool) ($row->matched ?? false);
    }

    /** Chạy biểu thức thật trên DB với một giá trị cho trước. */
    private function listContains(?string $stored, int $id): bool
    {
        [$sql, $bindings] = ListColumn::containsId('value', $id);

        // Thứ tự binding theo đúng thứ tự dấu ? xuất hiện trong câu SQL:
        // biểu thức INSTR nằm ở SELECT (trước), giá trị cột ở subquery (sau).
        $row = DB::selectOne(
            'SELECT ('.$sql.') AS matched FROM (SELECT ? AS value) AS t',
            [...$bindings, $stored],
        );

        return (bool) ($row->matched ?? false);
    }
}
