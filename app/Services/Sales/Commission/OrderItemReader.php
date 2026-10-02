<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use App\Support\MoneyParser;
use App\Support\SchemaCache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Đọc các dòng hàng của một đơn.
 *
 * ## Vì sao tách riêng
 * Ba chỗ trong báo cáo cần đúng bộ dòng hàng này (tính trước VAT theo bảng giá,
 * tính trước VAT theo %VAT của dòng, và gom tên sản phẩm). Cả ba đều tự đọc
 * danh sách cột rồi tự định nghĩa lại `$has(...)`, và cả ba đều lặp lại cùng một
 * quy tắc lấy số lượng. Nay quy tắc nằm ở một chỗ.
 *
 * Lớp này CỐ Ý không bắt ngoại lệ: bên gọi đã bọc `try` để rơi xuống nguồn dữ
 * liệu dự phòng, nên nuốt lỗi ở đây sẽ làm hỏng chuỗi dự phòng đó.
 */
final class OrderItemReader
{
    /** @var list<string>|null Danh sách cột, đọc một lần cho cả lượt dựng báo cáo. */
    private ?array $columns = null;

    public function __construct(private readonly OrderColumnMap $cols) {}

    /** Chỉ đọc được khi biết cả bảng dòng hàng lẫn cột khoá ngoại về đơn. */
    public function isReady(): bool
    {
        return $this->cols->itemTable !== null && $this->cols->itemOrderCol !== null;
    }

    public function has(string $column): bool
    {
        if ($this->columns === null) {
            $this->columns = SchemaCache::columns((string) $this->cols->itemTable);
        }

        return in_array($column, $this->columns, true);
    }

    /** @return Collection<int, object> */
    public function rowsFor(mixed $orderId): Collection
    {
        return $this->queryFor($orderId)->get();
    }

    /**
     * Câu truy vấn thô, để bên gọi còn `join` thêm khi cần lấy tên từ danh mục.
     *
     * @param  string|null  $alias  bí danh bảng; có `join` thì bắt buộc phải đặt
     */
    public function queryFor(mixed $orderId, ?string $alias = null): Builder
    {
        $table = (string) $this->cols->itemTable;
        $column = (string) $this->cols->itemOrderCol;

        if ($alias === null) {
            return DB::table($table)->where($column, $orderId);
        }

        return DB::table($table.' as '.$alias)->where($alias.'.'.$column, $orderId);
    }

    /**
     * Số lượng của một dòng.
     *
     * Thiếu hoặc bằng 0 thì coi là 1 — dòng hàng không có số lượng vẫn là một
     * món đã bán, tính 0 sẽ làm mất doanh số.
     */
    public function quantity(object $item): float
    {
        $qty = $this->has('quantity') ? MoneyParser::parse($item->quantity ?? 0) : 0.0;

        if ($qty <= 0 && $this->has('qty')) {
            $qty = MoneyParser::parse($item->qty ?? 0);
        }

        return $qty > 0 ? $qty : 1.0;
    }
}
