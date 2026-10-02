<?php

declare(strict_types=1);

namespace App\View\Presenters\Sales;

use App\DTOs\Sales\KpiModuleCard;
use App\DTOs\Sales\KpiOpsItem;

/**
 * Chuẩn bị giá trị cho view `sales.kpi.settings` (trang cấu hình KPI sales).
 *
 * Trước 2026-09-08 view tự tính trong 4 khối `@php` (35 dòng): số module bật, khối lượng việc,
 * khấu trừ tối đa, trạng thái bật/target từng thẻ, và danh sách công tắc vận hành; bảng định nghĩa
 * 16 chỉ tiêu nằm trong controller. Nay bảng định nghĩa là hằng ở đây (dữ liệu trình bày), controller
 * chỉ đưa cấu hình đã lưu. Tên khoá trả về giữ tên biến cũ của view; kiểm bằng so HTML 2 bộ cấu hình.
 */
final class SalesKpiSettingsPresenter
{
    /** @var list<array<string, string|null>> */
    private const CORE_MODULES = [
        ['enabled_key' => 'enable_posts', 'target_key' => 'posts_target', 'title' => 'Bài đăng group', 'unit' => 'bài/ngày', 'icon' => 'bi-megaphone', 'tone' => 'blue', 'description' => 'Số bài đăng group điện mặt trời mỗi ngày.'],
        ['enabled_key' => 'enable_calls', 'target_key' => 'calls_target', 'title' => 'Cuộc gọi nghe máy', 'unit' => 'call/ngày', 'icon' => 'bi-telephone-outbound', 'tone' => 'cyan', 'description' => 'Số cuộc gọi khách hàng nghe máy được tính KPI.'],
        ['enabled_key' => 'enable_company_data', 'target_key' => 'company_data_target', 'title' => 'Data công ty đã gọi', 'unit' => 'data/ngày', 'icon' => 'bi-database-check', 'tone' => 'violet', 'description' => 'Số data công ty cấp mà sales đã xử lý trong ngày.'],
        ['enabled_key' => 'enable_follow_up', 'target_key' => null, 'title' => 'Follow up khách cũ', 'unit' => 'bắt buộc', 'icon' => 'bi-chat-dots', 'tone' => 'green', 'description' => 'Checklist follow up toàn bộ khách hôm trước.'],
    ];

    /** @var list<array<string, string|null>> */
    private const SUGGESTED_MODULES = [
        ['enabled_key' => 'enable_new_leads', 'target_key' => 'new_leads_target', 'title' => 'Lead mới', 'unit' => 'lead/ngày', 'icon' => 'bi-person-plus', 'description' => 'Khách hàng/lead mới tự khai thác hoặc được phân bổ.'],
        ['enabled_key' => 'enable_quotes', 'target_key' => 'quotes_target', 'title' => 'Báo giá gửi khách', 'unit' => 'báo giá/ngày', 'icon' => 'bi-file-earmark-text', 'description' => 'Số báo giá gửi khách trong ngày.'],
        ['enabled_key' => 'enable_customer_care', 'target_key' => 'customer_care_target', 'title' => 'Chăm sóc khách hàng', 'unit' => 'khách/ngày', 'icon' => 'bi-heart', 'description' => 'Số khách được chăm sóc, nhắc lại, hỏi nhu cầu.'],
        ['enabled_key' => 'enable_meetings', 'target_key' => 'meetings_target', 'title' => 'Lịch hẹn / meeting', 'unit' => 'lịch/ngày', 'icon' => 'bi-calendar2-check', 'description' => 'Lịch tư vấn, khảo sát, demo hoặc gặp khách.'],
        ['enabled_key' => 'enable_zalo_messages', 'target_key' => 'zalo_messages_target', 'title' => 'Tin nhắn Zalo', 'unit' => 'tin/ngày', 'icon' => 'bi-send', 'description' => 'Tin nhắn chăm sóc, tư vấn, follow qua Zalo.'],
        ['enabled_key' => 'enable_debt_follow', 'target_key' => 'debt_follow_target', 'title' => 'Follow công nợ', 'unit' => 'case/ngày', 'icon' => 'bi-wallet2', 'description' => 'Theo dõi khách còn công nợ hoặc cần nhắc thanh toán.'],
        ['enabled_key' => 'enable_order_follow', 'target_key' => 'order_follow_target', 'title' => 'Bám đơn hàng', 'unit' => 'đơn/ngày', 'icon' => 'bi-box-seam', 'description' => 'Theo dõi đơn đang xử lý, giao hàng, thanh toán, xuất kho.'],
        ['enabled_key' => 'enable_technical_coordination', 'target_key' => 'technical_coordination_target', 'title' => 'Phối hợp kỹ thuật', 'unit' => 'việc/ngày', 'icon' => 'bi-tools', 'description' => 'Phối hợp khảo sát, kỹ thuật, cấu hình hệ thống.'],
        ['enabled_key' => 'enable_overdue_tasks', 'target_key' => 'overdue_tasks_target', 'title' => 'Task quá hạn tối đa', 'unit' => 'task', 'icon' => 'bi-alarm', 'description' => 'Giới hạn số công việc quá hạn được phép tồn.'],
        ['enabled_key' => 'enable_training', 'target_key' => 'training_target', 'title' => 'Học sản phẩm', 'unit' => 'mục/ngày', 'icon' => 'bi-mortarboard', 'description' => 'Học sản phẩm, chính sách, kịch bản tư vấn.'],
        ['enabled_key' => 'enable_quality_score', 'target_key' => 'quality_score_target', 'title' => 'Điểm chất lượng', 'unit' => 'điểm', 'icon' => 'bi-stars', 'description' => 'Chất lượng tư vấn, ghi chú CRM, thái độ chăm sóc.'],
        ['enabled_key' => 'enable_revenue_pipeline', 'target_key' => 'revenue_pipeline_target', 'title' => 'Pipeline doanh số', 'unit' => 'VNĐ', 'icon' => 'bi-graph-up-arrow', 'description' => 'Giá trị cơ hội/báo giá đang theo đuổi.'],
    ];

    /** @var list<array<string, string>> */
    private const OPS = [
        ['key' => 'enable_warnings', 'title' => 'Cảnh báo thiếu KPI', 'desc' => 'Hiển thị warning khi nhân viên chưa nhập hoặc thiếu KPI.', 'icon' => 'bi-bell'],
        ['key' => 'enable_penalty', 'title' => 'Tính khấu trừ dự kiến', 'desc' => 'Tắt mục này thì KPI vẫn tính %, nhưng phạt = 0đ.', 'icon' => 'bi-cash-coin'],
        ['key' => 'enable_manager_approval', 'title' => 'Quản lý duyệt KPI', 'desc' => 'Chuẩn bị workflow duyệt trước khi chốt lương.', 'icon' => 'bi-shield-check'],
        ['key' => 'enable_callio_sync', 'title' => 'Đồng bộ Callio', 'desc' => 'Chuẩn bị auto-sync số cuộc gọi từ Callio.', 'icon' => 'bi-cloud-arrow-down'],
        ['key' => 'enable_lock_after_days', 'title' => 'Khóa sửa sau số ngày', 'desc' => 'Giới hạn nhân viên sửa KPI sau ngày đã nhập.', 'icon' => 'bi-lock'],
    ];

    /**
     * @param  array<string, mixed>  $settings  cấu hình đã lưu (SalesKpiSettingsService::salesKpiSettings)
     * @return array<string, mixed>
     */
    public function viewData(array $settings): array
    {
        $enabled = fn (string $key): bool => (int) ($settings[$key] ?? 0) === 1;
        $coreModules = array_map(fn (array $module) => $this->moduleCard($module, $settings), self::CORE_MODULES);
        $suggestedModules = array_map(fn (array $module) => $this->moduleCard($module, $settings), self::SUGGESTED_MODULES);
        $coreOn = count(array_filter($coreModules, fn (KpiModuleCard $module) => $module->isOn));
        $maxPenalty = $enabled('enable_penalty') ? (int) ($settings['penalty_per_missing'] ?? 0) * max(1, $coreOn) : 0;

        return [
            'coreModules' => $coreModules,
            'suggestedModules' => $suggestedModules,
            'ops' => array_map(fn (array $item) => new KpiOpsItem(
                key: $item['key'],
                title: $item['title'],
                description: $item['desc'],
                icon: $item['icon'],
                isOn: $enabled($item['key']),
            ), self::OPS),
            'coreOn' => $coreOn,
            'suggestedOn' => count(array_filter($suggestedModules, fn (KpiModuleCard $module) => $module->isOn)),
            'workload' => (int) ($settings['posts_target'] ?? 0) + (int) ($settings['calls_target'] ?? 0) + (int) ($settings['company_data_target'] ?? 0),
            'maxPenalty' => $maxPenalty,
            'maxPenaltyText' => self::money($maxPenalty),
            'penaltyPerMissingText' => self::money($settings['penalty_per_missing'] ?? 0),
        ];
    }

    /** @param  array<string, string|null>  $module */
    private function moduleCard(array $module, array $settings): KpiModuleCard
    {
        $targetKey = $module['target_key'] ?? null;

        return new KpiModuleCard(
            enabledKey: (string) $module['enabled_key'],
            targetKey: $targetKey,
            title: (string) $module['title'],
            unit: (string) $module['unit'],
            icon: (string) $module['icon'],
            tone: $module['tone'] ?? null,
            description: (string) $module['description'],
            isOn: (int) ($settings[$module['enabled_key']] ?? 0) === 1,
            // Thẻ không có target (checklist) in 1 để form vẫn hợp lệ.
            targetValue: $targetKey ? (int) ($settings[$targetKey] ?? 0) : 1,
        );
    }

    /** Tiền trên trang này viết liền "đ" (DisplayFormat::money có dấu cách) — giữ đúng chữ đang hiện. */
    private static function money(mixed $value): string
    {
        return number_format((int) ($value ?? 0), 0, ',', '.').'đ';
    }
}
