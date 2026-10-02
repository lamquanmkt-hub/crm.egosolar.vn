<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\DTOs\Sales\KpiModuleCard;
use App\DTOs\Sales\KpiOpsItem;
use App\View\Presenters\Sales\SalesKpiSettingsPresenter;
use Tests\TestCase;

/** {@see SalesKpiSettingsPresenter} thay 4 khối `@php` của sales/kpi/settings (2026-09-08). Không cần DB. */
final class SalesKpiSettingsPresenterTest extends TestCase
{
    public function test_dem_module_bat_khoi_luong_va_khau_tru_toi_da(): void
    {
        $data = (new SalesKpiSettingsPresenter)->viewData([
            'posts_target' => 120, 'calls_target' => 50, 'company_data_target' => 30, 'penalty_per_missing' => 150000,
            'enable_posts' => 1, 'enable_calls' => '1', 'enable_company_data' => 0, 'enable_follow_up' => 1,
            'enable_new_leads' => 1, 'new_leads_target' => 12, 'enable_penalty' => 1, 'enable_callio_sync' => 1,
        ]);

        $this->assertSame([3, 1, 200], [$data['coreOn'], $data['suggestedOn'], $data['workload']]);
        $this->assertSame([450000, '450.000đ', '150.000đ'], [$data['maxPenalty'], $data['maxPenaltyText'], $data['penaltyPerMissingText']], 'phạt × số module core bật; tiền viết liền "đ" như trang cũ');
        $this->assertCount(4, $data['coreModules']);
        $this->assertCount(12, $data['suggestedModules']);

        $calls = $data['coreModules'][1];
        $this->assertInstanceOf(KpiModuleCard::class, $calls);
        $this->assertSame(['enable_calls', 'calls_target', 50, true, 'cyan'], [$calls->enabledKey, $calls->targetKey, $calls->targetValue, $calls->isOn, $calls->tone]);
        $followUp = $data['coreModules'][3];
        $this->assertSame([null, 1, true], [$followUp->targetKey, $followUp->targetValue, $followUp->isOn], 'checklist không có target → in 1');
        $newLeads = $data['suggestedModules'][0];
        $this->assertSame([true, 12, null], [$newLeads->isOn, $newLeads->targetValue, $newLeads->tone]);
        $this->assertSame(0, $data['suggestedModules'][1]->targetValue, 'thẻ gợi ý chưa có target → 0');

        $ops = $data['ops'];
        $this->assertCount(5, $ops);
        $this->assertInstanceOf(KpiOpsItem::class, $ops[0]);
        $this->assertSame(['enable_warnings', false], [$ops[0]->key, $ops[0]->isOn], 'không có khoá → tắt');
        $this->assertSame(['enable_callio_sync', true], [$ops[3]->key, $ops[3]->isOn]);
    }

    public function test_tat_khau_tru_thi_phat_bang_0_va_khong_module_nao_bat_van_nhan_1(): void
    {
        $data = (new SalesKpiSettingsPresenter)->viewData(['penalty_per_missing' => 200000, 'enable_penalty' => 0]);
        $this->assertSame([0, 0, '0đ', '200.000đ'], [$data['coreOn'], $data['maxPenalty'], $data['maxPenaltyText'], $data['penaltyPerMissingText']]);

        $data = (new SalesKpiSettingsPresenter)->viewData(['penalty_per_missing' => 200000, 'enable_penalty' => 1]);
        $this->assertSame(200000, $data['maxPenalty'], 'không module core nào bật vẫn nhân với 1');
    }
}
