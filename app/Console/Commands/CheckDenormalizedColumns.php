<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Debug\DenormalizedColumnChecker;
use Illuminate\Console\Command;

/**
 * Báo cáo dữ liệu đã lệch ở các cột phi chuẩn hoá (3NF loại B/C).
 *
 * CHỈ ĐỌC — an toàn chạy trên production, kể cả trong giờ làm việc.
 * Trả mã thoát 1 khi phát hiện lệch nên cắm được vào cron/CI để cảnh báo sớm.
 *
 * Ví dụ:
 *   php artisan db:check-denormalized
 *   php artisan db:check-denormalized --quiet   # chỉ lấy mã thoát
 */
final class CheckDenormalizedColumns extends Command
{
    protected $signature = 'db:check-denormalized';

    protected $description = 'Dò dữ liệu đã lệch ở các cột lưu trùng lặp (vi phạm 3NF) — chỉ đọc, không sửa';

    public function handle(DenormalizedColumnChecker $checker): int
    {
        $report = $checker->run();

        if ($report === []) {
            $this->components->info('Chưa cấu hình cột nào trong ego.denormalized_columns.');

            return self::SUCCESS;
        }

        $hasDrift = false;

        foreach ($report as $item) {
            $label = $item['table'].'.'.$item['column'];

            if ($item['skipped'] !== null) {
                $this->components->warn("{$label} — {$item['skipped']}");

                continue;
            }

            $problem = $item['mismatched'] > 0 || $item['orphan'] > 0;
            $hasDrift = $hasDrift || $problem;

            $summary = sprintf(
                '%s: %d/%d dòng lệch, %d dòng có giá trị nhưng thiếu khoá ngoại',
                $label,
                $item['mismatched'],
                $item['total'],
                $item['orphan'],
            );

            $problem ? $this->components->error($summary) : $this->components->info($summary);

            foreach ($item['samples'] as $sample) {
                $this->line('     • '.$sample);
            }

            if ($problem && $item['note'] !== '') {
                $this->line('     → '.$item['note']);
            }
        }

        if ($hasDrift) {
            $this->newLine();
            $this->components->warn('Có dữ liệu lệch. Xem DB_NORMALIZATION_AUDIT.md để biết cách xử lý từng cột.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
