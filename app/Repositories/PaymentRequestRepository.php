<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PaymentRequestRepositoryInterface;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Repository cho ĐNTT (`payment_requests`) — gom mọi truy cập thô về một chỗ.
 *
 * Dùng {@see SchemaCache} thay cho `Schema::hasTable/hasColumn` trực tiếp để
 * không bắn thêm truy vấn `information_schema` trong mỗi vòng lặp.
 *
 * @see \App\Contracts\Repositories\PaymentRequestRepositoryInterface giải thích
 *      vì sao lớp này dùng query builder thay vì Eloquent.
 */
final class PaymentRequestRepository implements PaymentRequestRepositoryInterface
{
    private const TABLE = 'payment_requests';

    /**
     * Các bảng con có thể có, tuỳ bản triển khai. Chỉ đụng vào bảng thật sự tồn
     * tại và thật sự có cột `payment_request_id`.
     *
     * @var list<string>
     */
    private const CHILD_TABLES = [
        'payment_request_attachments',
        'payment_attachments',
        'payment_request_files',
        'payment_request_approvals',
        'payment_request_histories',
        'payment_request_logs',
    ];

    /**
     * Bảng con chứa tệp đính kèm — được nhân bản khi sao chép phiếu.
     *
     * @var list<string>
     */
    private const ATTACHMENT_TABLES = [
        'payment_request_attachments',
        'payment_attachments',
        'payment_request_files',
    ];

    public function find(int $id): ?object
    {
        if (! SchemaCache::hasTable(self::TABLE)) {
            return null;
        }

        return DB::table(self::TABLE)->where('id', $id)->first();
    }

    /**
     * @return list<string>
     */
    public function columns(): array
    {
        return SchemaCache::columns(self::TABLE);
    }

    public function hasColumn(string $column): bool
    {
        return SchemaCache::hasColumn(self::TABLE, $column);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        DB::table(self::TABLE)->where('id', $id)->update($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function insert(array $data): int
    {
        return (int) DB::table(self::TABLE)->insertGetId($data);
    }

    /**
     * Xoá mềm khi bảng có `deleted_at`, ngược lại xoá cứng.
     *
     * Trên production hiện tại bảng KHÔNG có `deleted_at`, nên nhánh chạy thật
     * là xoá cứng. Giữ cả hai nhánh vì code cũ vốn viết vậy và có bản triển khai
     * khác đã thêm cột này.
     */
    public function delete(int $id): void
    {
        if ($this->hasColumn('deleted_at')) {
            DB::table(self::TABLE)->where('id', $id)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table(self::TABLE)->where('id', $id)->delete();
    }

    public function deleteChildRows(int $id): void
    {
        foreach (self::CHILD_TABLES as $table) {
            if ($this->childTableUsable($table)) {
                DB::table($table)->where('payment_request_id', $id)->delete();
            }
        }
    }

    public function copyAttachments(int $fromId, int $toId): void
    {
        foreach (self::ATTACHMENT_TABLES as $table) {
            if (! $this->childTableUsable($table)) {
                continue;
            }

            $rows = DB::table($table)->where('payment_request_id', $fromId)->get();

            foreach ($rows as $row) {
                $data = (array) $row;

                unset($data['id']);
                $data['payment_request_id'] = $toId;

                if (SchemaCache::hasColumn($table, 'created_at')) {
                    $data['created_at'] = now();
                }

                if (SchemaCache::hasColumn($table, 'updated_at')) {
                    $data['updated_at'] = now();
                }

                DB::table($table)->insert($data);
            }
        }
    }

    /**
     * Số lớn nhất đang dùng cho tiền tố mã, ví dụ `PR-2026-` -> 71 nếu mã cao
     * nhất là `PR-2026-00071`.
     *
     * Cắt chuỗi ngay trong SQL để không phải kéo toàn bộ mã về PHP.
     */
    public function nextCodeSequence(string $prefix): int
    {
        $startPosition = strlen($prefix) + 1;

        $max = DB::table(self::TABLE)
            ->where('code', 'like', $prefix.'%')
            ->selectRaw("MAX(CAST(SUBSTRING(code, {$startPosition}) AS UNSIGNED)) as max_no")
            ->value('max_no');

        return ((int) $max) + 1;
    }

    /**
     * Bảng con vừa tồn tại vừa có khoá liên kết về phiếu.
     */
    private function childTableUsable(string $table): bool
    {
        return SchemaCache::hasTable($table)
            && SchemaCache::hasColumn($table, 'payment_request_id');
    }
}
