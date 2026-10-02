<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\Role;
use App\Models\Projects\Site as ProjectSite;
use App\Services\SiteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1l CONTRACT — ba ô `warranty_reminder_N_at` của form công trình ghi thẳng vào
 * `site_warranty_reminders` (ô trống = xoá mốc, ô không gửi = không đụng).
 */
final class SiteWarrantyReminderWriteTest extends TestCase
{
    use DatabaseTransactions;

    private const COMPANY = 998501;

    public function test_site_service_tao_sua_xoa_moc_nhac_theo_o_form(): void
    {
        $service = app(SiteService::class);

        $site = $service->create([
            'name' => 'Moc nhac', 'company_id' => self::COMPANY,
            'warranty_reminder_1_at' => '2027-01-01', 'warranty_reminder_2_at' => '', 'warranty_reminder_3_at' => null,
        ]);
        $this->assertSame([1 => '2027-01-01'], $this->reminders($site), 'ô trống/null không tạo mốc');

        $service->update($site, ['name' => 'Moc nhac (sua)']);
        $this->assertSame([1 => '2027-01-01'], $this->reminders($site), 'không gửi ô nào → giữ nguyên');

        $service->update($site, ['warranty_reminder_1_at' => '', 'warranty_reminder_3_at' => '2028-06-30']);
        $this->assertSame([3 => '2028-06-30'], $this->reminders($site), 'ô trống xoá mốc 1, ô có ngày tạo mốc 3');

        $service->update($site, ['warranty_reminder_3_at' => '2028-07-01']);
        $this->assertSame([3 => '2028-07-01'], $this->reminders($site), 'sửa ngày của mốc đã có');
        $this->assertSame('Moc nhac (sua)', $site->refresh()->name);
    }

    public function test_form_tao_cong_trinh_ghi_moc_nhac_vao_bang_moi(): void
    {
        DB::table('companies')->insert(['id' => self::COMPANY, 'code' => 'EGOT998', 'name' => 'EGO Test', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->post('/cong-trinh', [
            'company_id' => self::COMPANY, 'name' => 'Cong trinh moc nhac', 'address' => '1 Duong Test', 'priority' => 'normal', 'lead_engineer_id' => $admin->id,
            'warranty_reminder_1_at' => '2027-01-15', 'warranty_reminder_2_at' => '', 'warranty_reminder_3_at' => '2027-12-01',
        ])->assertSessionHasNoErrors();

        $site = ProjectSite::query()->where('name', 'Cong trinh moc nhac')->firstOrFail();
        $this->assertSame([1 => '2027-01-15', 3 => '2027-12-01'], $this->reminders($site));
    }

    /** @return array<int, string> sequence => Y-m-d */
    private function reminders(ProjectSite $site): array
    {
        return $site->warrantyReminders()->get()
            ->mapWithKeys(fn ($reminder): array => [(int) $reminder->sequence => $reminder->remind_at->format('Y-m-d')])
            ->all();
    }
}
