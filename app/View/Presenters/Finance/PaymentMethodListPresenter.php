<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\PaymentMethodRow;
use Illuminate\Support\Str;

/**
 * Chuẩn bị trang `payment_methods/index`.
 *
 * Lý do tồn tại: view gọi `Str::limit(...)` — Facade trong Blade, `rules/blade-views.md` cấm.
 * `Str` ở đây là hàm chuỗi thuần (không I/O, không trạng thái) nên presenter vẫn thuần.
 */
final class PaymentMethodListPresenter
{
    /**
     * @param  iterable<object>  $methods
     * @return array{methodRows: list<PaymentMethodRow>}
     */
    public function viewData(iterable $methods): array
    {
        $rows = [];

        foreach ($methods as $method) {
            $rows[] = new PaymentMethodRow(
                id: (int) ($method->id ?? 0),
                methodName: (string) ($method->method_name ?? ''),
                code: (string) ($method->code ?? ''),
                // Bản cũ: `Str::limit($method->description, 50)` — mô tả null ra chuỗi rỗng.
                descriptionText: Str::limit((string) ($method->description ?? ''), 50),
                isActive: (bool) ($method->is_active ?? false),
            );
        }

        return ['methodRows' => $rows];
    }
}
