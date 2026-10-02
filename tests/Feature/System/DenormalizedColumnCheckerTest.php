<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Models\User;
use App\Services\Debug\DenormalizedColumnChecker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Công cụ dò lệch cho các cột phi chuẩn hoá (3NF loại B/C).
 *
 * Dùng bảng thật của schema production để không phải giả lập DB.
 */
final class DenormalizedColumnCheckerTest extends TestCase
{
    use DatabaseTransactions;

    private DenormalizedColumnChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new DenormalizedColumnChecker;
    }

    /** Kiểu `value`: phát hiện cột cache khác giá trị nguồn. */
    public function test_value_check_detects_drifted_cache_column(): void
    {
        $companyId = $this->seedCompany('Công ty Gốc');

        $this->seedSupplierDebt($companyId, 'Công ty Gốc');
        $driftedId = $this->seedSupplierDebt($companyId, 'Tên Cũ Đã Đổi');

        $result = $this->runCheckFor('finance_supplier_debts', 'company_name');

        $this->assertSame(1, $result['mismatched']);
        $this->assertStringContainsString((string) $driftedId, implode(' ', $result['samples']));
        $this->assertStringContainsString('Tên Cũ Đã Đổi', implode(' ', $result['samples']));
    }

    /** Kiểu `value`: dữ liệu khớp thì không báo lệch. */
    public function test_value_check_passes_when_cache_matches(): void
    {
        $companyId = $this->seedCompany('Công ty Khớp');
        $this->seedSupplierDebt($companyId, 'Công ty Khớp');

        $this->assertSame(0, $this->runCheckFor('finance_supplier_debts', 'company_name')['mismatched']);
    }

    /** Đếm được dòng có giá trị nhưng thiếu khoá ngoại. */
    public function test_counts_rows_with_value_but_missing_foreign_key(): void
    {
        $before = $this->runCheckFor('finance_supplier_debts', 'company_name')['orphan'];

        $this->seedSupplierDebt(null, 'Công ty Không Có Id');

        $this->assertSame($before + 1, $this->runCheckFor('finance_supplier_debts', 'company_name')['orphan']);
    }

    /**
     * Kiểu `mapping`: bắt MÂU THUẪN chứ không bắt khác cách viết.
     *
     * Đây là ca thật trên production: cùng một chuỗi tên công ty lại trỏ về hai
     * company_id khác nhau.
     */
    public function test_mapping_check_detects_contradiction_not_formatting(): void
    {
        $first = $this->seedCompany('CÔNG TY A');
        $second = $this->seedCompany('CÔNG TY B');

        // Khác cách viết hoa/thường so với companies.name — KHÔNG được coi là lỗi.
        $this->seedPaymentRequest($first, 'Công ty A');
        $this->seedPaymentRequest($first, 'Công ty A');

        $result = $this->runCheckFor('payment_requests', 'company');
        $this->assertSame(0, $result['mismatched'], 'Khác cách viết không phải mâu thuẫn.');

        // Cùng chuỗi nhưng trỏ về công ty khác -> mâu thuẫn thật.
        $this->seedPaymentRequest($second, 'Công ty A');

        $result = $this->runCheckFor('payment_requests', 'company');
        $this->assertGreaterThan(0, $result['mismatched']);
        $this->assertStringContainsString('trỏ về 2 id khác nhau', implode(' ', $result['samples']));
    }

    /** Bảng/cột không tồn tại thì bỏ qua, không làm vỡ lệnh. */
    public function test_skips_unknown_tables(): void
    {
        config(['ego.denormalized_columns' => [[
            'check' => 'value',
            'table' => 'bang_khong_ton_tai_abc',
            'column' => 'x',
            'foreign_key' => 'y_id',
            'references' => 'companies',
            'source_column' => 'name',
        ]]]);

        $report = $this->checker->run();

        $this->assertCount(1, $report);
        $this->assertNotNull($report[0]['skipped']);
    }

    /**
     * Lấy kết quả kiểm tra của một cột.
     *
     * @return array<string, mixed>
     */
    private function runCheckFor(string $table, string $column): array
    {
        foreach ($this->checker->run() as $item) {
            if ($item['table'] === $table && $item['column'] === $column) {
                return $item;
            }
        }

        $this->fail("Không tìm thấy cấu hình cho {$table}.{$column}");
    }

    private function seedCompany(string $name): int
    {
        return (int) DB::table('companies')->insertGetId([
            'code' => 'CHK-'.uniqid(),
            'name' => $name,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedSupplierDebt(?int $companyId, string $companyName): int
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

    private function seedPaymentRequest(int $companyId, string $companyText): int
    {
        return (int) DB::table('payment_requests')->insertGetId([
            'code' => 'PR-'.uniqid(),
            'company_id' => $companyId,
            'company' => $companyText,
            'created_by' => User::factory()->create()->id,
            'receiver_name' => 'Người nhận Test',
            'amount' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
