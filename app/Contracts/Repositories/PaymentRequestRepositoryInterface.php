<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

/**
 * Truy cập dữ liệu bảng `payment_requests` và các bảng con của nó.
 *
 * ## Vì sao KHÔNG kế thừa RepositoryInterface / BaseRepository
 * Hai lớp đó làm việc trên Eloquent Model. Toàn bộ code ĐNTT đang chạy trên
 * production lại dùng query builder thô kèm dò schema (`hasTable`/`hasColumn`),
 * vì bảng con khác nhau giữa các bản triển khai — chỗ có
 * `payment_request_attachments`, chỗ chỉ có `payment_attachments`.
 *
 * Ép nó vào Eloquent lúc này là đổi hành vi lưu trữ của tiền bạc để đổi lấy
 * sự "đúng khuôn" — không đáng. Repository này giữ nguyên cách truy cập cũ,
 * chỉ gom về một chỗ: 37 lời gọi `DB::table('payment_requests')` nằm rải ở 13
 * file, mỗi chỗ tự dò schema một kiểu.
 */
interface PaymentRequestRepositoryInterface
{
    /**
     * Lấy một phiếu theo id.
     */
    public function find(int $id): ?object;

    /**
     * Danh sách cột thật của bảng `payment_requests`.
     *
     * @return list<string>
     */
    public function columns(): array;

    /**
     * Bảng có cột này không.
     */
    public function hasColumn(string $column): bool;

    /**
     * Ghi các cột đã lọc cho một phiếu.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): void;

    /**
     * Chèn một phiếu mới, trả về id.
     *
     * @param  array<string, mixed>  $data
     */
    public function insert(array $data): int;

    /**
     * Xoá phiếu: xoá mềm nếu bảng có `deleted_at`, ngược lại xoá cứng.
     */
    public function delete(int $id): void;

    /**
     * Xoá các bản ghi con (đính kèm, lịch sử duyệt, log...) của phiếu.
     */
    public function deleteChildRows(int $id): void;

    /**
     * Nhân bản các bản ghi đính kèm từ phiếu này sang phiếu khác.
     */
    public function copyAttachments(int $fromId, int $toId): void;

    /**
     * Số thứ tự kế tiếp cho mã phiếu dạng `PR-<năm>-<5 số>`.
     */
    public function nextCodeSequence(string $prefix): int;
}
