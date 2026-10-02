<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Debug\RestConformanceAuditor;
use Illuminate\Console\Command;

/**
 * Đo mức độ tuân REST của bảng định tuyến — CHỈ ĐỌC.
 *
 * Dùng để theo dõi tiến độ chuyển đổi từng module thay vì làm mù:
 *   php artisan routes:rest-audit
 *   php artisan routes:rest-audit --module=orders
 */
final class AuditRestConformance extends Command
{
    protected $signature = 'routes:rest-audit {--module= : Chỉ xem một module}';

    protected $description = 'Chấm điểm mức độ tuân REST của bảng định tuyến (chỉ đọc)';

    public function handle(RestConformanceAuditor $auditor): int
    {
        $report = $auditor->audit();
        $filter = $this->option('module');

        $this->components->info(sprintf(
            '%d/%d route đạt chuẩn REST (%.1f%%)',
            $report['summary']['conformant'],
            $report['summary']['total'],
            $report['summary']['percent'],
        ));

        $labels = [
            'verb_in_url' => 'Động từ nằm trong URL',
            'wrong_http_verb' => 'Sai động từ HTTP',
            'unnamed' => 'Route không đặt tên',
            'singular_resource' => 'Tên tài nguyên số ít',
        ];

        $this->newLine();
        $this->line('  <options=bold>Lỗi theo loại</>');

        foreach ($report['issues'] as $key => $count) {
            if ($count > 0) {
                $this->line(sprintf('    %-26s %4d', $labels[$key] ?? $key, $count));
            }
        }

        $this->newLine();
        $this->line('  <options=bold>Theo module</> (sắp theo số lỗi giảm dần)');
        $this->line(sprintf('    %-24s %6s %7s %8s', 'module', 'route', 'lỗi', 'đạt'));

        foreach ($report['modules'] as $module) {
            if ($filter !== null && $module['module'] !== $filter) {
                continue;
            }

            if ($module['issues'] === 0 && $filter === null) {
                continue;
            }

            $this->line(sprintf(
                '    %-24s %6d %7d %7.1f%%',
                $module['module'],
                $module['total'],
                $module['issues'],
                $module['percent'],
            ));

            if ($filter !== null) {
                foreach ($module['samples'] as $sample) {
                    $this->line('        '.$sample);
                }
            }
        }

        if ($filter === null) {
            $this->newLine();
            $this->line('  Xem chi tiết một module:  <fg=yellow>php artisan routes:rest-audit --module=orders</>');
        }

        return self::SUCCESS;
    }
}
