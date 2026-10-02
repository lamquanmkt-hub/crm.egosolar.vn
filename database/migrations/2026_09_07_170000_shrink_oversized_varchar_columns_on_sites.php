<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đưa `sites` xuống DƯỚI trần 8.126 byte/dòng của InnoDB bằng cách thu hẹp 4 cột varchar(255)
 * đang khai quá rộng so với dữ liệu thật.
 *
 * ## Vì sao
 * Sau CONTRACT (2026_09_07_150000) `sites` vẫn ≈ 9.559 byte/dòng tối đa (20 cột varchar utf8mb4,
 * mỗi ký tự 4 byte). Production chạy được chỉ vì `innodb_strict_mode=0`; máy dev/CI strict=1
 * từ chối mọi ALTER thêm/xoá cột kiểu INSTANT/INPLACE ("Row size too large") — tức là KHÔNG
 * migration Blueprint bình thường nào chạy được trên `sites` nữa. Chi tiết:
 * DB_NORMALIZATION_AUDIT.md — "trần kích thước dòng".
 *
 * ## Đã đo trên production (2026-09-07, 19 công trình)
 * contact_phone dài nhất 12 ký tự, contact_name 13, technician_name 55, monitoring_account 20.
 * Thu hẹp: contact_phone → 50; ba cột còn lại → 120. Tiết kiệm 2.440 byte → ≈ 7.100 byte, dưới trần.
 *
 * ## Không mất dữ liệu
 * `up()` đọc `MAX(CHAR_LENGTH())` từng cột TRƯỚC khi đổi; có giá trị dài hơn cỡ mới thì ném lỗi
 * và không đổi gì (MariaDB strict cũng sẽ từ chối cắt, nhưng không dựa vào cấu hình máy chủ).
 * `down()` nới lại varchar(255). Cả hai chiều là MỘT câu `ALTER … ALGORITHM=COPY` (nguyên tử,
 * qua kiểm tra strict — lý do như migration CONTRACT).
 */
return new class extends Migration
{
    /** cột => độ dài mới (ký tự). Cả 4 cột đều NULL, default NULL, không có index. */
    private const TARGET_LENGTHS = [
        'contact_phone' => 50,
        'contact_name' => 120,
        'technician_name' => 120,
        'monitoring_account' => 120,
    ];

    private const ORIGINAL_LENGTH = 255;

    public function up(): void
    {
        $toShrink = array_filter(
            self::TARGET_LENGTHS,
            fn (int $length, string $column): bool => ($this->currentLength($column) ?? 0) > $length,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($toShrink === []) {
            return;
        }

        foreach ($toShrink as $column => $length) {
            $longest = (int) DB::table('sites')->selectRaw("COALESCE(MAX(CHAR_LENGTH(`{$column}`)), 0) AS longest")->value('longest');
            if ($longest > $length) {
                throw new RuntimeException(sprintf(
                    'Không thu hẹp sites.%s xuống varchar(%d): đang có giá trị dài %d ký tự. Chưa đổi gì.',
                    $column,
                    $length,
                    $longest,
                ));
            }
        }

        $this->modify($toShrink);
    }

    public function down(): void
    {
        $toWiden = array_filter(
            self::TARGET_LENGTHS,
            fn (int $length, string $column): bool => ($this->currentLength($column) ?? self::ORIGINAL_LENGTH) < self::ORIGINAL_LENGTH,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($toWiden !== []) {
            $this->modify(array_fill_keys(array_keys($toWiden), self::ORIGINAL_LENGTH));
        }
    }

    /** @param  array<string, int>  $lengths  cột => độ dài */
    private function modify(array $lengths): void
    {
        DB::statement(sprintf(
            'ALTER TABLE `sites` %s, ALGORITHM=COPY',
            implode(', ', array_map(
                static fn (string $column, int $length): string => "MODIFY `{$column}` varchar({$length}) NULL DEFAULT NULL",
                array_keys($lengths),
                $lengths,
            )),
        ));
    }

    /** Độ dài khai báo hiện tại; null nếu cột không tồn tại. */
    private function currentLength(string $column): ?int
    {
        if (! Schema::hasColumn('sites', $column)) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT character_maximum_length AS length FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['sites', $column],
        );

        return $row === null ? null : (int) $row->length;
    }
};
