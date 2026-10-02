<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\View\Presenters\Order\MyOrdersPresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Presenter trang `orders/my-orders` — dựng dữ liệu trong bộ nhớ, assert GIÁ TRỊ THẬT.
 *
 * Kế thừa `PHPUnit\Framework\TestCase` (không boot app) vì lớp này thuần.
 */
final class MyOrdersPresenterTest extends TestCase
{
    /**
     * Một `Order` giả với ĐỦ thuộc tính presenter đọc tới.
     *
     * stdClass ném lỗi khi thiếu thuộc tính (Eloquent thì trả null), nên phải khai đủ.
     *
     * @param  array<string, mixed>  $ghiDe
     */
    private function order(array $ghiDe = []): object
    {
        $o = (object) array_merge([
            'id' => 1,
            'order_code' => 'ORD-001',
            'total_amount' => 1_234_567,
            'current_department' => 'sales',
        ], $ghiDe);

        $o->lead = (object) ['customer' => (object) ['name' => 'Khách A', 'phone' => '0900000001']];

        // ⚠️ `array_key_exists`, KHÔNG `??`: nơi gọi truyền `null` TƯỜNG MINH để kiểm nhánh
        // "đơn chưa có trạng thái", mà `??` coi null là vắng mặt nên rơi về giá trị mặc định.
        $o->currentStatusType = array_key_exists('currentStatusType', $ghiDe)
            ? $ghiDe['currentStatusType']
            : (object) ['name' => 'Đang duyệt', 'color' => '#0d6efd'];

        return $o;
    }

    /** @return array{total_orders: int, pending: int, completed: int} */
    private function stats(int $total, int $pending, int $completed): array
    {
        return ['total_orders' => $total, 'pending' => $pending, 'completed' => $completed];
    }

    public function test_phan_tram_lam_tron_va_khong_chia_cho_0(): void
    {
        $data = (new MyOrdersPresenter)->viewData($this->stats(8, 3, 5), []);
        $this->assertSame(63.0, round($data['completedPercent'], 6), '5/8 = 62,5% -> round() ra 63');
        $this->assertSame(38.0, round($data['pendingPercent'], 6), '3/8 = 37,5% -> 38');

        // Chưa có đơn nào: guard `?: 1` của bản cũ giữ mẫu số là 1, ra 0% chứ không phải NaN.
        $rong = (new MyOrdersPresenter)->viewData($this->stats(0, 0, 0), []);
        $this->assertSame(0.0, round($rong['completedPercent'], 6));
        $this->assertSame(0.0, round($rong['pendingPercent'], 6));
    }

    /** @param string $dept @param string $mong */
    #[DataProvider('boPhan')]
    public function test_tong_badge_theo_bo_phan(string $dept, string $mong, string $nhan): void
    {
        $data = (new MyOrdersPresenter)->viewData(
            $this->stats(1, 0, 1),
            [$this->order(['current_department' => $dept])],
        );

        $row = $data['orderRows'][0];
        $this->assertSame($mong, $row->departmentBadge);
        $this->assertSame($nhan, $row->departmentLabel);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function boPhan(): array
    {
        return [
            // Màu lấy từ `.bg-*` của bootstrap@5.3.3, đo trên trang thật.
            'sales' => ['sales', 'tw:bg-[#6c757d]', 'Sales'],
            'ketoan' => ['ketoan', 'tw:bg-[#0dcaf0]', 'Ketoan'],
            'duyet1' => ['duyet1', 'tw:bg-[#ffc107]', 'Duyet1'],
            'duyet2' => ['duyet2', 'tw:bg-[#0d6efd]', 'Duyet2'],
            'kho' => ['kho', 'tw:bg-[#212529]', 'Kho'],
            'completed' => ['completed', 'tw:bg-[#198754]', 'Completed'],
            // Bộ phận lạ rơi về mặc định; `ucfirst` chỉ hoa chữ CÁI ĐẦU, không tách gạch dưới.
            'bộ phận lạ' => ['bo_phan_la', 'tw:bg-[#6c757d]', 'Bo_phan_la'],
            'rỗng' => ['', 'tw:bg-[#6c757d]', ''],
        ];
    }

    public function test_don_chua_co_trang_thai_thi_dung_mau_va_nhan_mac_dinh(): void
    {
        $data = (new MyOrdersPresenter)->viewData(
            $this->stats(1, 1, 0),
            [$this->order(['currentStatusType' => null])],
        );

        $row = $data['orderRows'][0];
        $this->assertSame('N/A', $row->statusName);
        $this->assertSame('#6c757d', $row->statusColor);
    }

    public function test_tien_dinh_dang_kieu_viet_nam_khong_kem_don_vi(): void
    {
        $data = (new MyOrdersPresenter)->viewData(
            $this->stats(1, 0, 1),
            [$this->order(['total_amount' => 1_234_567])],
        );

        // Bản cũ: number_format($x, 0, ',', '.') rồi view tự nối `đ` LIỀN, không có khoảng trắng —
        // nên KHÔNG dùng DisplayFormat::money() (hàm đó trả '1.234.567 đ').
        $this->assertSame('1.234.567', $data['orderRows'][0]->totalText);
    }

    public function test_doc_ten_khach_qua_chuoi_quan_he(): void
    {
        $data = (new MyOrdersPresenter)->viewData($this->stats(1, 0, 1), [$this->order()]);

        $row = $data['orderRows'][0];
        $this->assertSame('Khách A', $row->customerName);
        $this->assertSame('0900000001', $row->customerPhone);
        $this->assertSame('ORD-001', $row->orderCode);
    }
}
