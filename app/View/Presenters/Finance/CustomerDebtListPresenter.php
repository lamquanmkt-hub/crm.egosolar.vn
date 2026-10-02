<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\CustomerDebtOrderRow;
use App\DTOs\Finance\CustomerDebtRow;
use App\Support\DisplayFormat;

/**
 * Chuẩn bị trang công nợ khách hàng (`finance/debt-customers`).
 *
 * Thay ba khối `@php`:
 *  1. bốn con số tổng của trang (ép kiểu từ `$fullSummary`);
 *  2. khối trong `@forelse` — cặp lớp/chữ trạng thái của TỪNG khách;
 *  3. khối trong `@foreach` LỒNG — cờ "đã hoàn thành" và cặp lớp/chữ của TỪNG đơn.
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 *
 * Tiền dùng `DisplayFormat::money()`: bản cũ là `number_format($x, 0, ',', '.').' đ'` — trùng đúng
 * từng ký tự, kể cả DẤU CÁCH trước `đ` (khác `orders/my-orders`, trang đó nối liền).
 */
final class CustomerDebtListPresenter
{
    /**
     * Huy hiệu trạng thái — CHUỖI LỚP đầy đủ, không phải tên biến thể BEM.
     *
     * Tông của CHẤM tròn đi kèm bằng biến thể `[&>span]` thay vì một lớp riêng: bản CSS cũ dùng
     * luật hậu duệ `.finance-status--success .finance-status__dot`, mà Tailwind không có lớp
     * tương đương nên phải khai từ phía cha.
     */
    private const STATUS_DEBT = ['tw:inline-flex tw:items-center tw:gap-2 tw:py-2 tw:px-3 tw:rounded-[999px] tw:text-[12px] tw:font-black tw:leading-none tw:whitespace-nowrap tw:border tw:border-solid tw:shadow-[0_6px_16px_rgba(15,23,42,0.08)] tw:[background:linear-gradient(180deg,#fee2e2,#fecaca)] tw:text-[#b91c1c] tw:border-[#fca5a5] tw:[&>span]:bg-[#ef4444] tw:[&>span]:shadow-[0_0_0_4px_rgba(239,68,68,0.16)]', 'Công nợ'];

    private const STATUS_DONE = ['tw:inline-flex tw:items-center tw:gap-2 tw:py-2 tw:px-3 tw:rounded-[999px] tw:text-[12px] tw:font-black tw:leading-none tw:whitespace-nowrap tw:border tw:border-solid tw:shadow-[0_6px_16px_rgba(15,23,42,0.08)] tw:[background:linear-gradient(180deg,#dcfce7,#bbf7d0)] tw:text-[#047857] tw:border-[#86efac] tw:[&>span]:bg-[#10b981] tw:[&>span]:shadow-[0_0_0_4px_rgba(16,185,129,0.16)]', 'Đã hoàn thành'];

    /**
     * @param  mixed  $customers  paginator các nhóm khách (giữ nguyên để view còn `->links()`)
     * @param  array<string, mixed>  $fullSummary  bốn con số đã gộp sẵn ở controller
     * @return array{customers: mixed, totalCustomersText: string, revenueTotalText: string, paidTotalText: string, debtTotalText: string}
     */
    public function viewData(mixed $customers, array $fullSummary): array
    {
        return [
            'customers' => $customers->through(
                fn (object $c): CustomerDebtRow => $this->dongKhach($c)
            ),

            // Bản cũ: `(int) ($fullSummary['total_customers'] ?? $customers->total())`.
            'totalCustomersText' => DisplayFormat::number(
                $fullSummary['total_customers'] ?? $customers->total()
            ),
            'revenueTotalText' => DisplayFormat::money($fullSummary['total_amount'] ?? 0),
            'paidTotalText' => DisplayFormat::money($fullSummary['paid_amount'] ?? 0),
            'debtTotalText' => DisplayFormat::money($fullSummary['debt_amount'] ?? 0),
        ];
    }

    private function dongKhach(object $c): CustomerDebtRow
    {
        $ten = (string) ($c->customer_name ?? '');
        [$lop, $chu] = ((float) ($c->debt_amount ?? 0)) > 0 ? self::STATUS_DEBT : self::STATUS_DONE;

        $donHang = [];
        foreach ($c->orders ?? [] as $order) {
            $donHang[] = $this->dongDon($order);
        }

        return new CustomerDebtRow(
            // Bản cũ gọi `md5($customer->group_key)` HAI lần (nút mở và thuộc tính id) — cùng một
            // giá trị, nay tính một lần.
            detailId: md5((string) ($c->group_key ?? '')),
            customerName: $ten,
            avatarInitial: mb_substr($ten, 0, 1),
            totalOrdersText: DisplayFormat::number($c->total_orders ?? 0),
            totalText: DisplayFormat::money($c->total_amount ?? 0),
            paidText: DisplayFormat::money($c->paid_amount ?? 0),
            debtText: DisplayFormat::money($c->debt_amount ?? 0),
            statusClass: $lop,
            statusText: $chu,
            orders: $donHang,
        );
    }

    private function dongDon(object $order): CustomerDebtOrderRow
    {
        $tong = (float) ($order->total_amount ?? 0);
        $no = (float) ($order->debt_amount ?? 0);

        // Giữ y điều kiện cũ, kể cả hai nhánh dễ quên: tổng tiền ≤ 0 và nợ ≤ 0 đều tính là XONG.
        $xong = $tong <= 0 || $no <= 0 || ((int) ($order->payment_recorded ?? 0)) === 1;
        [$lop, $chu] = $xong ? self::STATUS_DONE : self::STATUS_DEBT;

        return new CustomerDebtOrderRow(
            id: (int) ($order->id ?? 0),
            orderCode: (string) ($order->order_code ?? ''),
            totalText: DisplayFormat::money($tong),
            paidText: DisplayFormat::money($order->paid_amount ?? 0),
            debtText: DisplayFormat::money($no),
            statusClass: $lop,
            statusText: $chu,
            createdAtText: $this->ngay($order->created_at ?? null),
            deleteConfirmText: $this->cauHoiXoa((string) ($order->order_code ?? ''), DisplayFormat::money($no)),
        );
    }

    /**
     * Ngày `d/m/Y H:i`; RỖNG khi trống.
     *
     * Không dùng `DisplayFormat::date()`: hàm đó trả gạch dài `—` cho giá trị trống, còn bản cũ
     * `optional($order->created_at)->format(...)` in ra CHUỖI RỖNG. Giữ đúng từng ký tự.
     */
    /**
     * Câu hỏi trước khi xoá — dựng ở đây chứ không phải trong view.
     *
     * Bản cũ ghép chuỗi này trong <script> của trang từ hai thuộc tính `data-*`. Nay Alpine gọi
     * `window.confirm()` thẳng với chuỗi đã dựng sẵn, nên nó là DỮ LIỆU HIỂN THỊ như mọi chuỗi
     * khác của presenter. Giữ nguyên từng chữ và từng chỗ xuống dòng của bản cũ.
     */
    private function cauHoiXoa(string $maDon, string $conNo): string
    {
        return "Xóa khoản công nợ này khỏi danh sách?\n\n"
            ."Mã đơn: {$maDon}\n"
            ."Còn nợ: {$conNo}\n\n"
            .'Đơn hàng CRM sẽ không bị xóa.';
    }

    private function ngay(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
