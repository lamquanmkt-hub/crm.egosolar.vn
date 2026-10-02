<?php

declare(strict_types=1);

namespace App\View\Presenters\Projects;

use App\DTOs\Projects\SiteMaterialRow;
use App\Models\Projects\Site;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị giá trị cho view `sites.show`.
 *
 * Trước 2026-09-07 view tự tính 290 dòng trong `@php`: quyền xem giá vốn / tạo đơn vật tư, 5
 * closure định dạng, phân nhóm vật tư thực tế từ cột `note`, tổng hợp tài chính, tiến độ thu,
 * trạng thái, thông số hệ thống; rồi 5 khối nhỏ tính lại theo từng đợt thu / dòng vật tư.
 * Tên khoá trả về giữ tên biến cũ của view. Kiểm bằng so HTML 4 trang trước/sau.
 */
final class SiteDetailPresenter
{
    private const COST_ROLES = ['admin', 'accounting', 'manager', 'management', 'warehouse'];

    private const MATERIAL_REQUEST_ROLES = ['admin', 'technical', 'sales', 'warehouse', 'kho'];

    private const GROUP_MAIN_STOCK = 'Thiết bị chính - Trong kho';

    private const GROUP_MAIN_EXTERNAL = 'Thiết bị chính - Ngoài kho';

    private const GROUP_SUB_STOCK = 'Vật tư phụ - Trong kho';

    private const GROUP_SUB_EXTERNAL = 'Vật tư phụ - Ngoài kho';

    private const GROUP_CLASSES = [
        self::GROUP_MAIN_STOCK => 'group-main-stock',
        self::GROUP_MAIN_EXTERNAL => 'group-main-external',
        self::GROUP_SUB_STOCK => 'group-sub-stock',
        self::GROUP_SUB_EXTERNAL => 'group-sub-external',
    ];

    private const STATUS_INFO = [
        'planning' => ['label' => 'Chuẩn bị', 'class' => 'status-muted', 'icon' => 'bi-hourglass-split'],
        'installing' => ['label' => 'Đang lắp đặt', 'class' => 'status-warning', 'icon' => 'bi-tools'],
        'done' => ['label' => 'Hoàn thành', 'class' => 'status-success', 'icon' => 'bi-check2-circle'],
        'warranty' => ['label' => 'Bảo hành', 'class' => 'status-info', 'icon' => 'bi-shield-check'],
    ];

    private const TERM_STATUS = [
        'paid' => ['label' => 'Đã thu đủ', 'class' => 'bg-success'],
        'partial' => ['label' => 'Thu một phần', 'class' => 'bg-warning text-dark'],
    ];

    private const TERM_STATUS_DEFAULT = ['label' => 'Chưa thu', 'class' => 'bg-secondary'];

    public function __construct(private readonly SiteDetailFormat $format) {}

    /**
     * @param  list<array<string, mixed>>  $paymentTerms  từ SiteService::getPaymentTerms
     * @param  array<string, mixed>  $financeSummary  từ SiteService::getFinanceSummary
     * @param  list<array<string, mixed>>  $paymentReceipts  từ SiteService::getPaymentReceipts
     * @param  array<string, mixed>  $systemSummary  từ SiteService::getSystemSummary
     * @param  Collection<int, object>  $actualMaterials  dòng vật tư đã xuất kho (query builder)
     * @return array<string, mixed>
     */
    public function viewData(Site $site, array $paymentTerms, array $financeSummary, array $paymentReceipts, array $systemSummary, Collection $actualMaterials, ?User $user): array
    {
        $rows = $actualMaterials->map(fn (object $item) => $this->materialRow($item))->values();
        $mainMaterials = $rows->filter(fn (SiteMaterialRow $row) => str_starts_with($row->group, 'Thiết bị chính'))->values();
        $subMaterials = $rows->filter(fn (SiteMaterialRow $row) => str_starts_with($row->group, 'Vật tư phụ'))->values();

        $contractAmount = (float) ($financeSummary['contract_amount'] ?? ($site->contract_amount ?? 0));
        $receivedAmount = (float) ($financeSummary['received_amount'] ?? 0);
        $remainingReceivable = (float) ($financeSummary['remaining_receivable'] ?? max(0, $contractAmount - $receivedAmount));
        $materialCost = (float) ($financeSummary['material_cost'] ?? 0);
        $actualTotalCost = (float) $rows->sum(fn (SiteMaterialRow $row) => $row->lineTotal);
        if ($materialCost <= 0 && $actualTotalCost > 0) {
            $materialCost = $actualTotalCost;
        }
        $laborCost = (float) ($site->labor_cost ?? 0);
        $transportCost = (float) ($site->transport_cost ?? 0);
        $otherCost = (float) ($site->other_cost ?? 0);
        $totalCost = $materialCost + $laborCost + $transportCost + $otherCost;

        $status = (string) ($site->status ?? '');
        $terms = array_map(fn (array $term) => $this->term($term), $paymentTerms);
        $systemKwp = (float) ($systemSummary['system_kwp'] ?? ($site->system_kwp ?? 0));
        $systemKwAc = (float) ($systemSummary['system_kw_ac'] ?? ($site->system_kw_ac ?? 0));

        return [
            'site' => $site,
            'fmt' => $this->format,
            'canSeeCost' => $user !== null && ($user->hasAnyRole(self::COST_ROLES) || $user->can('finance.view') || $user->can('finance.manage')),
            'canCreateMaterialRequest' => $user !== null && $user->hasAnyRole(self::MATERIAL_REQUEST_ROLES),
            'paymentTerms' => $terms,
            'paymentReceipts' => $paymentReceipts,
            'systemSummary' => $systemSummary,
            'mainMaterials' => $mainMaterials,
            'subMaterials' => $subMaterials,
            'mainSummary' => $this->summary($mainMaterials),
            'subSummary' => $this->summary($subMaterials),

            'contractAmount' => $contractAmount,
            'receivedAmount' => $receivedAmount,
            'remainingReceivable' => $remainingReceivable,
            'materialCost' => $materialCost,
            'laborCost' => $laborCost,
            'transportCost' => $transportCost,
            'otherCost' => $otherCost,
            'totalCost' => $totalCost,
            'grossProfit' => $contractAmount - $totalCost,
            'paidPercent' => $this->percentOf($receivedAmount, $contractAmount),
            'costPercent' => $this->percentOf($totalCost, $contractAmount),
            'totalTermAmount' => array_sum(array_column($terms, 'amount')),
            'status' => $status,
            'statusInfo' => self::STATUS_INFO[$status] ?? ['label' => $status ?: '—', 'class' => 'status-muted', 'icon' => 'bi-dot'],
            'debtLabel' => $remainingReceivable > 0 ? 'Còn công nợ' : 'Đã thu đủ',
            'debtClass' => $remainingReceivable > 0 ? 'debt-danger' : 'debt-ok',

            'systemKwp' => $systemKwp,
            'pvKwp' => (float) ($systemSummary['pv_kwp'] ?? $systemKwp),
            'inverterKw' => (float) ($systemSummary['inverter_kw'] ?? $systemKwAc),
            'batteryKwh' => (float) ($systemSummary['battery_kwh'] ?? 0),
            'deviceCount' => (int) ($systemSummary['device_count'] ?? 0),

            // Hộp "sửa thanh toán" cũ (JS ở cuối view). `receipt_date` luôn null vì phiếu thu
            // từ SiteService chỉ có `paid_at` — giữ nguyên hành vi cũ, không tự sửa ở đây.
            'paymentEditorRows' => collect($paymentReceipts)->map(fn (array $receipt) => [
                'id' => $receipt['id'] ?? null,
                'term_id' => $receipt['site_payment_term_id'] ?? null,
                'amount' => (float) ($receipt['amount'] ?? 0),
                'payment_method' => $receipt['payment_method'] ?? 'cash',
                'receipt_date' => ($receipt['receipt_date'] ?? null) ?: ($receipt['payment_date'] ?? null),
                'note' => $receipt['note'] ?? null,
            ])->filter(fn (array $row) => ! empty($row['id']))->values(),
            'paymentEditorTerms' => collect($terms)->map(fn (array $term) => [
                'id' => $term['id'] ?? null,
                'name' => $term['name'] ?? null,
                'amount' => $term['amount'],
            ])->filter(fn (array $term) => ! empty($term['id']))->values(),
        ];
    }

    /**
     * Đợt thu: ép số, tính còn lại nếu thiếu, nhãn + màu theo `computed_status`.
     *
     * @param  array<string, mixed>  $term
     * @return array<string, mixed>
     */
    private function term(array $term): array
    {
        $amount = (float) ($term['amount'] ?? 0);
        $paid = (float) ($term['paid_amount'] ?? 0);
        $status = self::TERM_STATUS[(string) ($term['computed_status'] ?? 'pending')] ?? self::TERM_STATUS_DEFAULT;

        // array_merge chứ không `+`: `+` giữ chuỗi thô "150000000.00" của DB thay vì số đã ép.
        return array_merge($term, [
            'amount' => $amount,
            'paid_amount' => $paid,
            'remaining_amount' => (float) ($term['remaining_amount'] ?? max(0, $amount - $paid)),
            'status_label' => $status['label'],
            'status_class' => $status['class'],
        ]);
    }

    private function materialRow(object $item): SiteMaterialRow
    {
        $inCatalog = ! empty($item->product_id);
        $note = (string) ($item->note ?? '');
        $group = $this->materialGroup($note, $inCatalog);
        $cleanNote = $this->cleanNote($note);
        $parts = $this->noteParts($cleanNote);

        return new SiteMaterialRow(
            requestId: (int) $item->material_request_id,
            requestCreatedAt: $item->request_created_at ?? null,
            productId: $inCatalog ? (int) $item->product_id : null,
            inCatalog: $inCatalog,
            sku: $item->product_sku ?? null,
            name: $inCatalog ? ($item->product_name ?? '—') : ($parts[0] ?? 'Vật tư ngoài kho'),
            group: $group,
            groupClass: self::GROUP_CLASSES[$group] ?? 'group-muted',
            qty: (float) ($item->qty ?? 0),
            unit: $this->materialUnit($item, $parts),
            unitCost: (float) ($item->unit_cost ?? 0),
            vatPercent: (float) ($item->vat_percent ?? 0),
            lineTotal: (float) ($item->line_total ?? 0),
            note: $this->materialNote($inCatalog, $cleanNote, $parts),
        );
    }

    /** Nhóm ghi trong ngoặc vuông ở đầu ghi chú; không có thì theo có/không mã sản phẩm. */
    private function materialGroup(string $note, bool $inCatalog): string
    {
        foreach ([self::GROUP_MAIN_STOCK, self::GROUP_MAIN_EXTERNAL, self::GROUP_SUB_STOCK, self::GROUP_SUB_EXTERNAL] as $group) {
            if (str_contains($note, '['.$group.']')) {
                return $group;
            }
        }

        return $inCatalog ? self::GROUP_MAIN_STOCK : self::GROUP_SUB_EXTERNAL;
    }

    private function cleanNote(string $note): string
    {
        return trim((string) preg_replace(
            '/\[(Thiết bị chính - Trong kho|Vật tư phụ - Trong kho|Thiết bị chính - Ngoài kho|Vật tư phụ - Ngoài kho)\]\s*/u',
            '',
            $note,
        ));
    }

    /** @return list<string> các đoạn "a | b | c" đã trim, bỏ rỗng */
    private function noteParts(string $cleanNote): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $cleanNote)), fn (string $part) => $part !== ''));
    }

    /** ĐVT: cột unit → ĐVT của sản phẩm → đoạn "ĐVT: x" trong ghi chú → `—`. */
    private function materialUnit(object $item, array $parts): string
    {
        if (! empty($item->unit)) {
            return (string) $item->unit;
        }
        if (! empty($item->product_unit)) {
            return (string) $item->product_unit;
        }
        foreach ($parts as $part) {
            if (preg_match('/^ĐVT:\s*(.*)$/u', $part, $m)) {
                return trim($m[1] ?? '');
            }
        }

        return '—';
    }

    /** Ghi chú còn lại: hàng trong catalog giữ nguyên; hàng ngoài bỏ đoạn tên và đoạn ĐVT. */
    private function materialNote(bool $inCatalog, string $cleanNote, array $parts): string
    {
        if ($inCatalog) {
            return $cleanNote !== '' ? $cleanNote : '—';
        }
        $rest = [];
        foreach ($parts as $index => $part) {
            if ($index === 0 || preg_match('/^ĐVT:\s*/u', $part)) {
                continue;
            }
            $rest[] = $part;
        }

        return count($rest) ? implode(' | ', $rest) : '—';
    }

    /**
     * @param  Collection<int, SiteMaterialRow>  $rows
     * @return array{rows: int, qty: float, cost: float, stock: int, external: int}
     */
    private function summary(Collection $rows): array
    {
        return [
            'rows' => $rows->count(),
            'qty' => (float) $rows->sum(fn (SiteMaterialRow $row) => $row->qty),
            'cost' => (float) $rows->sum(fn (SiteMaterialRow $row) => $row->lineTotal),
            'stock' => $rows->filter(fn (SiteMaterialRow $row) => $row->inCatalog)->count(),
            'external' => $rows->filter(fn (SiteMaterialRow $row) => ! $row->inCatalog)->count(),
        ];
    }

    /** % của phần so với tổng, kẹp trong [0, 100]; tổng 0 → 0. */
    private function percentOf(float $part, float $total): float|int
    {
        return $total > 0 ? min(100, max(0, ($part / $total) * 100)) : 0;
    }
}
