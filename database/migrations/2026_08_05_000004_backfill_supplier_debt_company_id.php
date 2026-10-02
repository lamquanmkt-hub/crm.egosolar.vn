<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Điền khoá ngoại `finance_supplier_debts.company_id` còn trống từ tên công ty.
 *
 * Đo trên production 2026-08-05: 17/25 dòng có `company_name` nhưng KHÔNG có
 * `company_id`, nghĩa là ở những dòng đó cột cache đang là nguồn dữ liệu duy
 * nhất — không thể phục hồi từ quan hệ nếu cột bị đổi/xoá.
 *
 * ## Quy tắc AN TOÀN (cố ý hẹp)
 * - CHỈ điền khi `company_id` đang NULL. Không bao giờ ghi đè giá trị có sẵn:
 *   khi hai bên mâu thuẫn thì đó là quyết định nghiệp vụ, không phải việc của
 *   migration (xem DB_NORMALIZATION_AUDIT.md, ca `payment_requests.company`).
 * - CHỈ điền khi tên khớp ĐÚNG MỘT công ty (so sánh bỏ hoa/thường và khoảng
 *   trắng thừa). Khớp 0 hoặc nhiều hơn 1 thì bỏ qua — 5 dòng ghi tắt
 *   "TMKT Quốc Tế EGO" không khớp công ty nào nên vẫn để nguyên cho người xử lý.
 *
 * Không xoá cột `company_name` (đang là snapshot hiển thị trong báo cáo).
 * Idempotent: chạy lại không đổi gì thêm.
 */
return new class extends Migration
{
    private const TABLE = 'finance_supplier_debts';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)
            || ! Schema::hasColumn(self::TABLE, 'company_id')
            || ! Schema::hasColumn(self::TABLE, 'company_name')
            || ! Schema::hasTable('companies')) {
            return;
        }

        $companyIdByName = $this->companyIdByNormalizedName();

        if ($companyIdByName === []) {
            return;
        }

        $names = DB::table(self::TABLE)
            ->whereNull('company_id')
            ->whereNotNull('company_name')
            ->where('company_name', '<>', '')
            ->distinct()
            ->pluck('company_name');

        foreach ($names as $name) {
            $companyId = $companyIdByName[$this->normalize((string) $name)] ?? null;

            if ($companyId === null) {
                continue;
            }

            DB::table(self::TABLE)
                ->whereNull('company_id')
                ->where('company_name', $name)
                ->update(['company_id' => $companyId]);
        }
    }

    /**
     * Không rollback: điền khoá ngoại là sửa dữ liệu ĐÚNG hơn, xoá đi chỉ làm
     * mất thông tin. Cột cũ vẫn còn nguyên nên không có gì để hoàn tác.
     */
    public function down(): void
    {
        // Cố ý để trống — xem PHPDoc.
    }

    /**
     * Map tên công ty đã chuẩn hoá => id, BỎ QUA tên trùng nhau.
     *
     * @return array<string, int>
     */
    private function companyIdByNormalizedName(): array
    {
        $byName = [];
        $ambiguous = [];

        foreach (DB::table('companies')->select('id', 'name')->get() as $company) {
            $key = $this->normalize((string) $company->name);

            if ($key === '') {
                continue;
            }

            if (isset($byName[$key])) {
                $ambiguous[$key] = true;

                continue;
            }

            $byName[$key] = (int) $company->id;
        }

        return array_diff_key($byName, $ambiguous);
    }

    /** Chuẩn hoá tên để so khớp: bỏ hoa/thường và khoảng trắng thừa. */
    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));
    }
};
