<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\Role;
use App\Models\Projects\Site as ProjectSite;
use App\Services\Projects\SiteQuoteLookup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P1l CONTRACT — mọi chỗ đọc báo giá lấy bản version cao nhất trong `site_quotes`
 * (helper SQL, model, danh sách công trình, công nợ dự án, khớp khách hàng); công trình
 * chưa báo giá thì đọc ra null/0. 21 cột `quote_*` không còn trong `sites`.
 */
final class SiteQuoteReadTest extends TestCase
{
    use DatabaseTransactions;

    private const COMPANY = 998501;

    public function test_cot_cu_da_xoa_va_helper_sql_model_doc_ban_version_cao_nhat(): void
    {
        $this->assertFalse(Schema::hasColumn('sites', 'quote_no'), 'đợt CONTRACT đã xoá cột quote_*');
        $this->assertFalse(Schema::hasColumn('sites', 'warranty_reminder_1_at'), 'đợt CONTRACT đã xoá cột warranty_reminder_*');

        [$withTwoVersions, $withoutQuote] = $this->seedSites();

        $rows = DB::table('sites')
            ->whereIn('id', [$withTwoVersions, $withoutQuote])
            ->orderBy('id')
            ->selectRaw('id, '.SiteQuoteLookup::latest('customer_company').' as company, '.SiteQuoteLookup::latest('grand_total').' as total')
            ->get();
        $this->assertSame(['Cong ty V2', '200.00'], [$rows[0]->company, (string) $rows[0]->total], 'hai bản → đọc v2');
        $this->assertSame([null, null], [$rows[1]->company, $rows[1]->total], 'chưa báo giá → null');

        $site = ProjectSite::query()->find($withTwoVersions);
        $this->assertSame(['Cong ty V2', 200.0], [$site->quoteCustomerCompany(), $site->quoteGrandTotal()]);
        $empty = ProjectSite::query()->find($withoutQuote);
        $this->assertSame([null, 0.0], [$empty->quoteCustomerCompany(), $empty->quoteGrandTotal()]);

        $this->expectException(\InvalidArgumentException::class);
        SiteQuoteLookup::latest('customer_company; drop table x');
    }

    public function test_danh_sach_cong_trinh_va_cong_no_tim_theo_ten_tren_ban_moi_nhat(): void
    {
        $this->seedSites();
        $admin = $this->userWithRole(Role::Admin->value);

        $this->assertStringContainsString('Cong trinh hai ban', $this->actingAs($admin)->get('/cong-trinh?q=Cong+ty+V2')->assertOk()->getContent());
        $this->assertStringNotContainsString('Cong trinh hai ban', $this->actingAs($admin)->get('/cong-trinh?q=Cong+ty+V1')->assertOk()->getContent(), 'tên trên bản v1 đã bị v2 thay thế');

        $html = $this->actingAs($admin)->get('/finance/project-receivables?company_id='.self::COMPANY)->assertOk()->getContent();
        $this->assertStringContainsString('Cong ty V2', $html, 'tên khách trên trang công nợ lấy từ bản v2');
        $this->assertStringNotContainsString('Cong ty V1', $html);
        $this->assertStringContainsString('Cong trinh chua bao gia', $html, 'công trình chưa báo giá vẫn hiện (tên khách rơi về tên công trình)');
    }

    public function test_khach_hang_khop_cong_trinh_theo_email_tren_ban_moi_nhat(): void
    {
        $this->seedSites();
        $admin = $this->userWithRole(Role::Admin->value);
        $matching = (int) DB::table('crm_customers')->insertGetId(['name' => 'Khach v2', 'email' => 'v2@example.test', 'company_id' => self::COMPANY, 'created_at' => now(), 'updated_at' => now()]);
        $stale = (int) DB::table('crm_customers')->insertGetId(['name' => 'Khach v1', 'email' => 'v1@example.test', 'company_id' => self::COMPANY, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertStringContainsString('Cong trinh hai ban', $this->actingAs($admin)->get('/customers/'.$matching)->assertOk()->getContent(), 'email bản v2 khớp');
        $this->assertStringNotContainsString('Cong trinh hai ban', $this->actingAs($admin)->get('/customers/'.$stale)->assertOk()->getContent(), 'email bản v1 không còn khớp');
    }

    /** @return array{0: int, 1: int} [site có v1+v2, site chưa báo giá] */
    private function seedSites(): array
    {
        $withTwoVersions = ProjectSite::query()->forceCreate(['name' => 'Cong trinh hai ban', 'company_id' => self::COMPANY, 'contract_amount' => 0]);
        $withoutQuote = ProjectSite::query()->forceCreate(['name' => 'Cong trinh chua bao gia', 'company_id' => self::COMPANY, 'contract_amount' => 0]);

        $money = ['subtotal' => 0, 'discount_amount' => 0, 'vat_percent' => 0, 'vat_amount' => 0, 'created_at' => now(), 'updated_at' => now()];
        DB::table('site_quotes')->insert([
            array_merge($money, ['site_id' => $withTwoVersions->id, 'version' => 1, 'code' => 'BG-V1', 'customer_company' => 'Cong ty V1', 'customer_email' => 'v1@example.test', 'customer_tax_code' => '0101', 'grand_total' => 100]),
            array_merge($money, ['site_id' => $withTwoVersions->id, 'version' => 2, 'code' => 'BG-V2', 'customer_company' => 'Cong ty V2', 'customer_email' => 'v2@example.test', 'customer_tax_code' => '0202', 'grand_total' => 200]),
        ]);

        return [(int) $withTwoVersions->id, (int) $withoutQuote->id];
    }
}
