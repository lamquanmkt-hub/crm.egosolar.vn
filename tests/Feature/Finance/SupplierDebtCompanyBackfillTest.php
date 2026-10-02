<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migration điền `finance_supplier_debts.company_id` từ tên công ty.
 *
 * Quy tắc phải giữ: chỉ điền chỗ trống, chỉ khi khớp đúng MỘT công ty.
 */
final class SupplierDebtCompanyBackfillTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Tên khớp đúng một công ty (kể cả khác hoa/thường, thừa khoảng trắng) thì được điền.
     *
     * Fixture CỐ Ý không dùng tên công ty thật: migration
     * 2026_04_30_000001_add_company_pdf_fields seed sẵn "CÔNG TY TNHH EGO VIỆT NAM",
     * nên trên DB dựng bằng migration sẽ thành hai công ty trùng tên và backfill bỏ qua
     * (đúng luật "trùng tên thì không đoán bừa"). Test khi đó đỏ vì trạng thái DB, không
     * phải vì logic sai — bản cũ chỉ xanh nhờ DB test dựng từ dump nên bảng companies rỗng.
     */
    public function test_fills_missing_company_id_on_exact_name_match(): void
    {
        $companyId = $this->seedCompany('CÔNG TY KIỂM THỬ HOA THƯỜNG');
        $debtId = $this->seedDebt(null, '  công ty kiểm thử   hoa thường ');

        $this->runBackfill();

        $this->assertSame($companyId, (int) $this->companyIdOf($debtId));
    }

    /** KHÔNG bao giờ ghi đè khoá ngoại đã có, kể cả khi tên chỉ về công ty khác. */
    public function test_never_overwrites_existing_company_id(): void
    {
        $first = $this->seedCompany('Công ty A');
        $second = $this->seedCompany('Công ty B');

        $debtId = $this->seedDebt($second, 'Công ty A');

        $this->runBackfill();

        $this->assertSame(
            $second,
            (int) $this->companyIdOf($debtId),
            'Mâu thuẫn giữa hai cột là quyết định nghiệp vụ, migration không được tự chọn.',
        );
        $this->assertNotSame($first, (int) $this->companyIdOf($debtId));
    }

    /** Tên không khớp công ty nào thì để nguyên (vd viết tắt). */
    public function test_leaves_unmatched_names_untouched(): void
    {
        $this->seedCompany('CÔNG TY TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO');
        $debtId = $this->seedDebt(null, 'Công ty TNHH TMKT Quốc Tế EGO');

        $this->runBackfill();

        $this->assertNull($this->companyIdOf($debtId));
    }

    /** Tên trùng ở nhiều công ty thì không đoán bừa. */
    public function test_skips_ambiguous_names(): void
    {
        $this->seedCompany('Công ty Trùng Tên');
        $this->seedCompany('Công ty Trùng Tên');

        $debtId = $this->seedDebt(null, 'Công ty Trùng Tên');

        $this->runBackfill();

        $this->assertNull($this->companyIdOf($debtId));
    }

    /** Chạy lại không đổi gì thêm. */
    public function test_is_idempotent(): void
    {
        $companyId = $this->seedCompany('Công ty Lặp');
        $debtId = $this->seedDebt(null, 'Công ty Lặp');

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame($companyId, (int) $this->companyIdOf($debtId));
    }

    private function runBackfill(): void
    {
        $migration = require database_path(
            'migrations/2026_08_05_000004_backfill_supplier_debt_company_id.php'
        );

        $migration->up();
    }

    private function companyIdOf(int $debtId): ?int
    {
        $value = DB::table('finance_supplier_debts')->where('id', $debtId)->value('company_id');

        return $value === null ? null : (int) $value;
    }

    private function seedCompany(string $name): int
    {
        return (int) DB::table('companies')->insertGetId([
            'code' => 'BF-'.uniqid(),
            'name' => $name,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedDebt(?int $companyId, string $companyName): int
    {
        return (int) DB::table('finance_supplier_debts')->insertGetId([
            'company_id' => $companyId,
            'company_name' => $companyName,
            'supplier_name' => 'NCC Test',
            'debt_month' => now()->startOfMonth()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
