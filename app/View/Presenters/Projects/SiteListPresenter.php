<?php

declare(strict_types=1);

namespace App\View\Presenters\Projects;

use App\DTOs\Projects\SiteKpiCard;
use App\DTOs\Projects\SiteListRow;
use App\Support\DisplayFormat;

/**
 * Chuẩn bị trang danh sách công trình (`sites/index`).
 *
 * Thay ba khối `@php` của view:
 *  1. khối đầu **135 dòng**: quyền, 5 lượt `request()`, 4 closure định dạng, 10 lượt tra
 *     `SchemaCache`, và một vòng gộp tài chính chạy qua toàn bộ công trình của trang;
 *  2. mảng `$kpi` bốn ô trạng thái;
 *  3. khối **52 dòng NGAY TRONG `@forelse`** — chạy lại cho từng dòng bảng.
 *
 * ## Bảy biến CHẾT đã bỏ (đo bằng `grep -oF`, mỗi biến chỉ xuất hiện đúng ở chỗ gán)
 * `$hasReceiptsSite`, `$hasMaterialRequestsSite`, `$hasMaterialRequestTotalCost`,
 * `$hasPaymentsSite`, `$hasPaymentRequestsSite` — năm biến này gọi `SchemaCache::hasTable()` +
 * `hasColumn()`, tức **10 lượt tra lược đồ mỗi lần mở trang** rồi vứt đi. Cùng với `$doneCount`
 * và `$totalCost`: cộng dồn qua mọi dòng rồi không ai in ra.
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 *
 * ## Định dạng
 * `$fmtMoney` và `$fmtDate` của bản cũ **trùng từng ký tự** với `DisplayFormat::money()` /
 * `::date()` (đã đối chiếu cả nhánh trống `—` và nhánh không parse được), nên uỷ quyền về đó.
 * `$fmtNum` thì giữ lại ở đây: nó cắt số 0 vô nghĩa theo kiểu riêng của trang và dùng dấu phân
 * cách MẶC ĐỊNH của PHP (`1,234.5`), khác quy ước của `DisplayFormat`.
 */
final class SiteListPresenter
{
    /**
     * Trạng thái → nhãn, LỚP badge, icon. Bản cũ là chuỗi `if/elseif` trong vòng lặp.
     *
     * Lớp là chuỗi đầy đủ chứ không phải tên tông (`secondary`…): view cũ ghép `bg-{{ $tone }}`
     * lúc chạy, mà Tailwind quét mã nguồn theo VĂN BẢN nên chuỗi ghép động không bao giờ được
     * sinh ra. Màu lấy từ `.bg-*` của bootstrap@5.3.3 đo trên trang thật; chữ luôn trắng
     * (`.badge` khai `color:#fff` cho mọi biến thể, kể cả warning/info).
     */
    private const STATUSES = [
        'planning' => ['Chuẩn bị', 'tw:bg-[#6c757d] tw:text-[#ffffff]', 'bi-hourglass-split'],
        'installing' => ['Đang lắp đặt', 'tw:bg-[#ffc107] tw:text-[#ffffff]', 'bi-tools'],
        'done' => ['Hoàn thành', 'tw:bg-[#198754] tw:text-[#ffffff]', 'bi-check2-circle'],
        'warranty' => ['Bảo hành', 'tw:bg-[#0dcaf0] tw:text-[#ffffff]', 'bi-shield-check'],
    ];

    /** Trạng thái lạ rơi vào đây, giữ đúng giá trị mặc định của bản cũ (`bg-secondary`). */
    private const STATUS_DEFAULT = ['—', 'tw:bg-[#6c757d] tw:text-[#ffffff]', 'bi-dot'];

    /** Tông ô KPI — số đo từ `.kpi-*` trong khối `<style>` cũ của trang. */
    private const KPI_TONES = [
        'ego' => 'tw:bg-[rgba(11,201,170,0.12)] tw:text-[#0f766e]',
        'secondary' => 'tw:bg-[rgba(100,116,139,0.10)] tw:text-[#475569]',
        'warn' => 'tw:bg-[rgba(245,158,11,0.12)] tw:text-[#b45309]',
        'info' => 'tw:bg-[rgba(59,130,246,0.10)] tw:text-[#1d4ed8]',
    ];

    /**
     * @param  mixed  $sites  paginator các bản ghi `sites` (giữ nguyên để view còn `->links()`)
     * @param  array<string, array<int, float|int|string>>  $siteTotals  bốn con số tiền đã gộp sẵn
     *                                                                   (`SiteFinancialTotalsService`)
     * @return array{sites: mixed, kpiCards: list<SiteKpiCard>, totalContractText: string, totalReceivedText: string, totalDebtText: string, totalProfit: float, totalProfitText: string, totalDebt: float, totalPaidPercent: float, totalPaidPercentText: string}
     */
    public function viewData(mixed $sites, array $siteTotals): array
    {
        $tong = $this->gopTaiChinh($sites, $siteTotals);
        $dem = $this->demTrangThai($sites);

        $firstItem = method_exists($sites, 'firstItem') ? $sites->firstItem() : null;
        $allTotal = method_exists($sites, 'total') ? (int) $sites->total() : (int) $sites->count();

        return [
            'sites' => $sites->through(fn (object $site, int $i): SiteListRow => $this->dong(
                $site,
                $i,
                $firstItem,
                $siteTotals,
            )),

            'kpiCards' => [
                new SiteKpiCard('Tổng công trình', (string) $allTotal, 'bi-buildings', self::KPI_TONES['ego']),
                new SiteKpiCard('Chuẩn bị', $this->soHoacGach($dem['planning']), 'bi-hourglass-split', self::KPI_TONES['secondary']),
                new SiteKpiCard('Đang lắp đặt', $this->soHoacGach($dem['installing']), 'bi-tools', self::KPI_TONES['warn']),
                new SiteKpiCard('Đang bảo hành', $this->soHoacGach($dem['warranty']), 'bi-shield-check', self::KPI_TONES['info']),
            ],

            'totalContractText' => DisplayFormat::money($tong['contract']),
            'totalReceivedText' => DisplayFormat::money($tong['received']),
            'totalDebt' => $tong['debt'],
            'totalDebtText' => DisplayFormat::money($tong['debt']),
            'totalProfit' => $tong['profit'],
            'totalProfitText' => DisplayFormat::money($tong['profit']),
            'totalPaidPercent' => $tyLeThu = $tong['contract'] > 0
                ? min(100, max(0, ($tong['received'] / $tong['contract']) * 100))
                : 0.0,
            'totalPaidPercentText' => DisplayFormat::percent($tyLeThu, 1),
        ];
    }

    /**
     * @param  array<string, array<int, float|int|string>>  $siteTotals
     * @return array{contract: float, received: float, debt: float, profit: float}
     */
    private function gopTaiChinh(mixed $sites, array $siteTotals): array
    {
        $contract = 0.0;
        $received = 0.0;
        $debt = 0.0;
        $profit = 0.0;

        foreach ($sites as $s) {
            $tien = $this->tienCuaMot($s, $siteTotals);
            $contract += $tien['contract'];
            $received += $tien['received'];
            $debt += $tien['debt'];
            $profit += $tien['profit'];
        }

        return ['contract' => $contract, 'received' => $received, 'debt' => $debt, 'profit' => $profit];
    }

    /**
     * Bốn con số tiền của MỘT công trình.
     *
     * @param  array<string, array<int, float|int|string>>  $siteTotals
     * @return array{contract: float, received: float, debt: float, cost: float, profit: float, paidPercent: float}
     */
    private function tienCuaMot(object $s, array $siteTotals): array
    {
        $id = (int) ($s->id ?? 0);

        $contract = (float) ($s->contract_amount ?? 0);
        $received = (float) ($siteTotals['received'][$id] ?? 0);

        $extra = (float) ($s->labor_cost ?? 0)
            + (float) ($s->transport_cost ?? 0)
            + (float) ($s->other_cost ?? 0);

        $cost = (float) ($siteTotals['materialCost'][$id] ?? 0)
            + (float) ($siteTotals['paymentCost'][$id] ?? 0)
            + (float) ($siteTotals['requestCost'][$id] ?? 0)
            + $extra;

        return [
            'contract' => $contract,
            'received' => $received,
            'debt' => max(0, $contract - $received),
            'cost' => $cost,
            'profit' => $contract - $cost,
            'paidPercent' => $contract > 0 ? min(100, max(0, ($received / $contract) * 100)) : 0.0,
        ];
    }

    /** @return array{planning: int, installing: int, warranty: int} */
    private function demTrangThai(mixed $sites): array
    {
        $dem = ['planning' => 0, 'installing' => 0, 'warranty' => 0];

        foreach ($sites as $s) {
            $st = strtolower((string) ($s->status ?? ''));
            if (isset($dem[$st])) {
                $dem[$st]++;
            }
        }

        return $dem;
    }

    /** @param array<string, array<int, float|int|string>> $siteTotals */
    private function dong(object $site, int $i, ?int $firstItem, array $siteTotals): SiteListRow
    {
        $tien = $this->tienCuaMot($site, $siteTotals);
        [$label, $badge, $icon] = self::STATUSES[strtolower((string) ($site->status ?? ''))] ?? self::STATUS_DEFAULT;

        // Ba cột ngày của bản cũ đọc theo thứ tự dự phòng vì lược đồ từng đổi tên cột.
        $installedAt = $site->installed_at ?? $site->install_date ?? $site->installation_date ?? null;
        $warrantyTo = $site->warranty_to ?? $site->warranty_until ?? $site->warranty_end ?? null;

        $owner = $site->owner_name
            ?? ($site->owner->name ?? null)
            ?? $site->technician_name
            ?? ($site->technician->name ?? null)
            ?? '—';

        return new SiteListRow(
            site: $site,
            // Giữ y công thức cũ: có `firstItem()` và nó khác 0 thì cộng offset, không thì đếm từ 1.
            rowNo: $firstItem ? $firstItem + $i : $i + 1,
            kwpText: $this->so($site->system_kwp ?? null),
            kwText: $this->so($site->system_kw_ac ?? null),
            installedAtText: DisplayFormat::date($installedAt),
            warrantyToText: DisplayFormat::date($warrantyTo),
            statusLabel: $label,
            statusBadgeClass: $badge,
            statusIcon: $icon,
            ownerChips: $this->tachTen($owner),
            contractText: DisplayFormat::money($tien['contract']),
            receivedText: DisplayFormat::money($tien['received']),
            paidPercent: $tien['paidPercent'],
            paidPercentText: DisplayFormat::percent($tien['paidPercent'], 1),
        );
    }

    /**
     * Công suất: cắt số 0 vô nghĩa (`10.50` → `10.5`, `8.00` → `8`); trống → null.
     *
     * Dùng `number_format($n, 2)` với dấu MẶC ĐỊNH của PHP như bản cũ — không phải quy ước Việt
     * Nam của `DisplayFormat`, vì đổi là đổi thứ đang hiển thị.
     */
    private function so(mixed $n): ?string
    {
        if ($n === null || $n === '') {
            return null;
        }

        $s = rtrim(rtrim(number_format((float) $n, 2), '0'), '.');

        return $s === '' ? null : $s;
    }

    /**
     * Tách chuỗi người phụ trách theo `,` và `;`, bỏ khoảng trắng và phần tử rỗng.
     *
     * @return list<string>
     */
    private function tachTen(mixed $str): array
    {
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/[,;]+/u', (string) $str) ?: []
        )));
    }

    /** Bản cũ dùng `$count ?: '—'` — đếm bằng 0 thì in gạch dài. */
    private function soHoacGach(int $n): string
    {
        return $n !== 0 ? (string) $n : '—';
    }
}
