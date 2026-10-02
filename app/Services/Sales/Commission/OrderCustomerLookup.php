<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use Illuminate\Support\Facades\DB;

/**
 * Tra tên và nhóm khách hàng của một đơn.
 *
 * ## Vì sao phải dò nhiều lớp
 * Đơn hàng ở hệ thống này có thể trỏ tới khách theo ba cách, tuỳ đợt dữ liệu:
 * ghi thẳng tên vào đơn, trỏ `customer_id`, hoặc chỉ có `lead_id` mà khách thật
 * nằm sau lead. Nên phải đi lần lượt: đơn → bảng khách → bảng lead → khách của
 * lead.
 *
 * ## Vì sao tên và nhóm khách nằm CHUNG một lớp
 * Hai câu hỏi khác nhau nhưng đi đúng một con đường. Trước đây là hai closure
 * tách rời, mỗi cái tự viết lại toàn bộ chuỗi dò đó; sửa thứ tự ưu tiên ở một
 * chỗ mà quên chỗ kia là hai màn hình lệch nhau. Nay chuỗi dò chỉ có một bản.
 *
 * Mọi lỗi truy vấn đều bị nuốt và coi như "không tìm thấy": báo cáo hoa hồng
 * không được sập chỉ vì một bảng phụ thiếu cột.
 */
final class OrderCustomerLookup
{
    private const CUSTOMER_TABLES = ['crm_customers', 'customers', 'clients'];

    private const LEAD_TABLES = ['crm_leads', 'leads'];

    private const NAME_COLUMNS = ['name', 'full_name', 'customer_name', 'company_name', 'contact_name', 'display_name'];

    private const STATUS_COLUMNS = ['customer_status', 'status', 'type', 'customer_type'];

    /** Trên BẢN GHI lead, tên riêng của khách được ưu tiên hơn tên chung. */
    private const LEAD_NAME_COLUMNS = ['customer_name', 'company_name', 'name', 'full_name', 'contact_name'];

    private const LEAD_STATUS_COLUMNS = ['customer_status', 'status', 'type', 'source'];

    private const LEAD_CUSTOMER_ID_COLUMNS = ['customer_id', 'client_id'];

    /** Nhóm khách đôi khi nằm sẵn trên chính bản ghi đơn. */
    private const ORDER_STATUS_COLUMNS = ['customer_status', 'status_customer', 'customer_type', 'type'];

    private const UNKNOWN_NAME = '---';

    public function __construct(
        private readonly CommissionSchema $schema,
        private readonly OrderColumnMap $cols,
    ) {}

    /** Tên khách để hiển thị; không tra ra thì trả về dấu hiệu "không rõ". */
    public function name(object $order): string
    {
        $nameCol = $this->cols->customerCol;

        if ($nameCol && ! empty($order->{$nameCol})) {
            return trim((string) $order->{$nameCol});
        }

        $idCol = $this->cols->customerIdCol;

        if ($idCol && ! empty($order->{$idCol})) {
            $name = $this->nameFromCustomerTables($order->{$idCol});

            if ($name !== null) {
                return $name;
            }
        }

        $leadCol = $this->cols->leadIdCol;

        if ($leadCol && ! empty($order->{$leadCol})) {
            $name = $this->nameFromLead($order->{$leadCol});

            if ($name !== null) {
                return $name;
            }
        }

        return self::UNKNOWN_NAME;
    }

    /** Nhóm khách đã chuẩn hoá chữ thường; chuỗi rỗng nghĩa là không xác định. */
    public function status(object $order): string
    {
        foreach (self::ORDER_STATUS_COLUMNS as $column) {
            if (isset($order->{$column}) && trim((string) $order->{$column}) !== '') {
                return $this->normalize($order->{$column});
            }
        }

        $idCol = $this->cols->customerIdCol;

        if ($idCol && ! empty($order->{$idCol})) {
            $status = $this->statusFromCustomerTables($order->{$idCol});

            if ($status !== '') {
                return $status;
            }
        }

        $leadCol = $this->cols->leadIdCol;

        if ($leadCol && ! empty($order->{$leadCol})) {
            $status = $this->statusFromLead($order->{$leadCol});

            if ($status !== '') {
                return $status;
            }
        }

        return '';
    }

    private function nameFromCustomerTables(mixed $customerId): ?string
    {
        foreach (self::CUSTOMER_TABLES as $table) {
            $name = $this->nameFromTable($table, $customerId);

            if ($name) {
                return $name;
            }
        }

        return null;
    }

    private function nameFromLead(mixed $leadId): ?string
    {
        foreach (self::LEAD_TABLES as $table) {
            if (! $this->schema->hasTable($table)) {
                continue;
            }

            // Lead cũng có thể có sẵn cột tên như một bảng khách bình thường.
            $name = $this->nameFromTable($table, $leadId);

            if ($name) {
                return $name;
            }

            $lead = $this->row($table, $leadId);

            if (! $lead) {
                continue;
            }

            foreach (self::LEAD_NAME_COLUMNS as $column) {
                if (isset($lead->{$column}) && trim((string) $lead->{$column}) !== '') {
                    return trim((string) $lead->{$column});
                }
            }

            foreach (self::LEAD_CUSTOMER_ID_COLUMNS as $column) {
                if (isset($lead->{$column}) && ! empty($lead->{$column})) {
                    $name = $this->nameFromCustomerTables($lead->{$column});

                    if ($name !== null) {
                        return $name;
                    }
                }
            }
        }

        return null;
    }

    private function statusFromCustomerTables(mixed $customerId): string
    {
        foreach (self::CUSTOMER_TABLES as $table) {
            $status = $this->normalize($this->rawValue($table, $customerId, self::STATUS_COLUMNS));

            if ($status !== '') {
                return $status;
            }
        }

        return '';
    }

    private function statusFromLead(mixed $leadId): string
    {
        foreach (self::LEAD_TABLES as $table) {
            if (! $this->schema->hasTable($table)) {
                continue;
            }

            $lead = $this->row($table, $leadId);

            if (! $lead) {
                continue;
            }

            foreach (self::LEAD_STATUS_COLUMNS as $column) {
                if (isset($lead->{$column}) && trim((string) $lead->{$column}) !== '') {
                    return $this->normalize($lead->{$column});
                }
            }

            foreach (self::LEAD_CUSTOMER_ID_COLUMNS as $column) {
                if (isset($lead->{$column}) && ! empty($lead->{$column})) {
                    $status = $this->statusFromCustomerTables($lead->{$column});

                    if ($status !== '') {
                        return $status;
                    }
                }
            }
        }

        return '';
    }

    private function nameFromTable(?string $table, mixed $id): ?string
    {
        $value = $this->rawValue($table, $id, self::NAME_COLUMNS);

        return $value ? trim((string) $value) : null;
    }

    /**
     * Giá trị thô của cột đầu tiên CÓ THẬT trong bảng.
     *
     * Trả về giá trị chưa xử lý chứ không phải chuỗi đã chuẩn hoá, vì tên và
     * nhóm khách có quy ước "rỗng" khác nhau: tên coi `'0'` là không có, nhóm
     * khách thì `'0'` vẫn là một giá trị.
     *
     * @param  list<string>  $candidates
     */
    private function rawValue(?string $table, mixed $id, array $candidates): mixed
    {
        if (! $table || ! $id || ! $this->schema->hasTable($table)) {
            return null;
        }

        $column = $this->schema->firstColumn($table, $candidates);

        if (! $column) {
            return null;
        }

        try {
            return DB::table($table)->where('id', $id)->value($column);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function row(string $table, mixed $id): ?object
    {
        try {
            return DB::table($table)->where('id', $id)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalize(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? strtolower($value) : '';
    }
}
