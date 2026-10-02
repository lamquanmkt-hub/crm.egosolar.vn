<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use App\Support\ProbeFailureLog;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dò tên bảng và cột thật trong CSDL.
 *
 * ## Vì sao phải dò
 * Lược đồ của hệ thống này thay đổi qua nhiều đợt: cùng một khái niệm có thể nằm
 * ở `crm_orders` hay `orders`, cột tổng tiền có thể là `final_amount`,
 * `grand_total` hay `total_amount`. Báo cáo hoa hồng phải chạy được trên mọi
 * biến thể, nên nó hỏi lược đồ thay vì giả định.
 *
 * Trước đây phần này là bốn closure khai giữa một method 1.299 dòng trong
 * `CommissionReportData`, bị hàng trăm dòng phía sau bắt lại qua `use`.
 */
final class CommissionSchema
{
    /** Danh sách bảng, đọc một lần rồi dùng lại — `SHOW TABLES` không rẻ. */
    private ?Collection $tableNames = null;

    public function hasTable(string $table): bool
    {
        return SchemaCache::hasTable($table);
    }

    public function hasColumn(string $table, string $column): bool
    {
        return $this->hasTable($table) && SchemaCache::hasColumn($table, $column);
    }

    /**
     * Cột đầu tiên trong danh sách ứng viên mà bảng thật sự có.
     *
     * @param  list<string>  $candidates
     */
    public function firstColumn(?string $table, array $candidates): ?string
    {
        if ($table === null) {
            return null;
        }

        foreach ($candidates as $column) {
            if ($this->hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Bảng đầu tiên khớp tên chính xác; không có thì khớp theo tên chứa.
     *
     * Bước khớp mờ là chủ ý: bảng thanh toán ở các bản triển khai cũ có tiền tố
     * khác nhau, khớp chính xác sẽ bỏ sót.
     *
     * @param  list<string>  $candidates
     */
    public function findTable(array $candidates): ?string
    {
        $tables = $this->tableNames();

        foreach ($candidates as $name) {
            if ($tables->contains($name)) {
                return $name;
            }
        }

        foreach ($candidates as $name) {
            $found = $tables->first(fn ($table) => str_contains(strtolower((string) $table), strtolower($name)));

            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /** @return Collection<int, string> */
    private function tableNames(): Collection
    {
        if ($this->tableNames !== null) {
            return $this->tableNames;
        }

        try {
            $this->tableNames = collect(DB::select('SHOW TABLES'))
                ->map(fn ($row) => array_values((array) $row)[0] ?? null)
                ->filter()
                ->values();
        } catch (\Throwable $e) {
            ProbeFailureLog::warn('CommissionSchema::tableNames', $e);

            $this->tableNames = collect();
        }

        return $this->tableNames;
    }
}
