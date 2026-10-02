<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Ai được sửa/xoá hồ sơ tài chính ĐÃ HOÀN TẤT.
 *
 * Trước đây điều kiện này là một phép so email viết cứng, lặp lại 12 lần trong
 * 5 file. Nay chỉ còn một nơi đọc `config('ego.finance_full_access_emails')`.
 *
 * ⚠️ Đây vẫn là đặc quyền theo DANH TÍNH chứ không theo vai trò. Lớp này cố ý
 * KHÔNG đổi luật — chỉ gom mối lại để sau này thay ruột bằng permission
 * (`finance.edit_completed`) mà không phải sờ tới 5 file.
 */
final class FinanceFullAccess
{
    /**
     * Người dùng này có toàn quyền trên hồ sơ tài chính đã hoàn tất không.
     */
    public function allows(?Authenticatable $user): bool
    {
        $email = strtolower(trim((string) ($user->email ?? '')));

        if ($email === '') {
            return false;
        }

        return in_array($email, $this->emails(), true);
    }

    /**
     * Danh sách email có đặc quyền (đã chuẩn hoá chữ thường).
     *
     * @return list<string>
     */
    public function emails(): array
    {
        /** @var list<string> $emails */
        $emails = config('ego.finance_full_access_emails', []);

        return $emails;
    }

    /**
     * Email đầu tiên trong danh sách — dùng để ghép câu thông báo cho người dùng
     * ("Chỉ user X được sửa"), thay cho chuỗi viết cứng trong thông báo cũ.
     */
    public function primaryEmail(): string
    {
        return $this->emails()[0] ?? '';
    }
}
