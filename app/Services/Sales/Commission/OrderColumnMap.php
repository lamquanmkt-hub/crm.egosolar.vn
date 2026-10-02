<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

/**
 * Tên bảng và cột thật mà báo cáo hoa hồng cần, đã dò xong một lần.
 *
 * ## Vì sao cần
 * Trước đây `build()` khai 20 biến rời (`$orderTotalCol`, `$paymentAmountCol`…)
 * rồi mỗi closure phía sau phải bắt lại đúng những biến nó dùng qua `use (...)`.
 * Danh sách `use` đó dài tới mức không ai đọc nổi, và thêm một cột là phải sửa
 * năm chỗ. Gom thành một value object thì chỉ còn truyền một thứ.
 *
 * Đối tượng này bất biến: dò xong là dùng suốt lượt dựng báo cáo.
 */
final class OrderColumnMap
{
    /**
     * @param  list<string>  $vatAmountCols  mọi cột tiền VAT có thật, theo thứ tự ưu tiên
     */
    private function __construct(
        public readonly ?string $orderTable,
        public readonly ?string $dateCol,
        public readonly ?string $totalCol,
        public readonly ?string $beforeVatCol,
        public readonly array $vatAmountCols,
        public readonly ?string $vatAmountCol,
        public readonly ?string $vatPercentCol,
        public readonly ?string $paidCol,
        public readonly ?string $statusCol,
        public readonly ?string $completionStatusCol,
        public readonly ?string $codeCol,
        public readonly ?string $salesCol,
        public readonly ?string $customerCol,
        public readonly ?string $customerIdCol,
        public readonly ?string $leadIdCol,
        public readonly ?string $paymentTable,
        public readonly ?string $paymentOrderCol,
        public readonly ?string $paymentAmountCol,
        public readonly ?string $paymentStatusCol,
        public readonly ?string $itemTable,
        public readonly ?string $itemOrderCol,
    ) {}

    public static function discover(CommissionSchema $schema): self
    {
        $orderTable = $schema->hasTable('crm_orders')
            ? 'crm_orders'
            : ($schema->hasTable('orders') ? 'orders' : null);

        $vatAmountCols = $orderTable
            ? array_values(array_filter(
                ['tax_amount', 'vat_amount', 'total_vat', 'total_tax'],
                fn (string $column) => $schema->hasColumn($orderTable, $column),
            ))
            : [];

        $paymentTable = $schema->findTable([
            'crm_order_payments',
            'order_payments',
            'crm_payments',
            'payments',
            'payment_histories',
            'crm_payment_histories',
            'crm_order_payment_histories',
        ]);

        $itemTable = $schema->findTable([
            'crm_order_items',
            'crm_order_details',
            'order_items',
            'order_details',
            'crm_order_products',
            'crm_order_item_products',
            'order_product_items',
        ]);

        return new self(
            orderTable: $orderTable,
            dateCol: $schema->firstColumn($orderTable, ['order_date', 'ordered_at', 'date', 'created_at']),
            totalCol: $schema->firstColumn($orderTable, ['final_amount', 'grand_total', 'total_amount', 'total', 'amount']),
            beforeVatCol: $schema->firstColumn($orderTable, [
                'total_before_vat',
                'subtotal_before_vat',
                'before_vat_total',
                'amount_before_vat',
                'sub_total',
                'subtotal',
                'net_total',
                'net_amount',
                'total_without_vat',
                'total_ex_vat',
                'total_excluding_vat',
            ]),
            vatAmountCols: $vatAmountCols,
            vatAmountCol: $vatAmountCols[0] ?? null,
            vatPercentCol: $schema->firstColumn($orderTable, ['vat_percent', 'vat_rate', 'tax_percent', 'tax_rate', 'vat']),
            paidCol: $schema->firstColumn($orderTable, ['paid_amount', 'amount_paid', 'total_paid']),
            statusCol: $schema->firstColumn($orderTable, ['status', 'order_status']),
            /*
             * Cột dùng để XÉT đơn đã hoàn tất chưa — rộng hơn cột hiển thị vì
             * nhiều bản triển khai không có cột `status` mà theo dõi tiến độ qua
             * `current_department`. Tách khỏi `statusCol` để cột hiện trên màn
             * hình không đổi theo.
             */
            completionStatusCol: $schema->firstColumn($orderTable, ['status', 'order_status', 'current_department']),
            codeCol: $schema->firstColumn($orderTable, ['code', 'order_code', 'order_no']),
            salesCol: $schema->firstColumn($orderTable, ['sales_id', 'sale_id', 'user_id', 'created_by']),
            customerCol: $schema->firstColumn($orderTable, ['customer_name', 'lead_name', 'name']),
            customerIdCol: $schema->firstColumn($orderTable, ['customer_id', 'client_id', 'buyer_id']),
            leadIdCol: $schema->firstColumn($orderTable, ['lead_id', 'crm_lead_id']),
            paymentTable: $paymentTable,
            paymentOrderCol: $schema->firstColumn($paymentTable, ['order_id', 'crm_order_id']),
            paymentAmountCol: $schema->firstColumn($paymentTable, ['amount', 'paid_amount', 'payment_amount', 'money', 'value', 'total']),
            paymentStatusCol: $schema->firstColumn($paymentTable, ['status', 'payment_status']),
            itemTable: $itemTable,
            itemOrderCol: $schema->firstColumn($itemTable, ['order_id', 'crm_order_id', 'sale_order_id']),
        );
    }
}
