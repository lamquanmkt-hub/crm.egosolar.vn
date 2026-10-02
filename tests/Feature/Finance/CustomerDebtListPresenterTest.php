<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\View\Presenters\Finance\CustomerDebtListPresenter;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Presenter trang công nợ khách hàng — dựng dữ liệu trong bộ nhớ, assert GIÁ TRỊ THẬT.
 *
 * Kế thừa `PHPUnit\Framework\TestCase` (không boot app) vì lớp này thuần.
 */
final class CustomerDebtListPresenterTest extends TestCase
{
    /**
     * Paginator LUÔN truyền `currentPage` tường minh: bỏ trống thì nó gọi
     * `Paginator::resolveCurrentPage()`, resolver do Laravel cài lúc boot và đọc `$app['request']`
     * — chạy cả bộ sẽ đỏ "Target class [request] does not exist", chạy riêng tệp thì không lộ.
     *
     * @param  list<object>  $items
     */
    private function paginator(array $items): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, count($items), 20, 1);
    }

    /** @param array<string, mixed> $ghiDe @param list<object> $orders */
    private function khach(array $ghiDe = [], array $orders = []): object
    {
        $c = (object) array_merge([
            'group_key' => 'kh-1',
            'customer_name' => 'Công ty Ánh Dương',
            'total_orders' => 3,
            'total_amount' => 10_000_000,
            'paid_amount' => 4_000_000,
            'debt_amount' => 6_000_000,
        ], $ghiDe);
        $c->orders = $orders;

        return $c;
    }

    /** @param array<string, mixed> $ghiDe */
    private function don(array $ghiDe = []): object
    {
        return (object) array_merge([
            'id' => 7,
            'order_code' => 'ORD-001',
            'total_amount' => 5_000_000,
            'paid_amount' => 1_000_000,
            'debt_amount' => 4_000_000,
            'payment_recorded' => 0,
            'created_at' => '2026-09-01 08:30:00',
        ], $ghiDe);
    }

    public function test_tong_cua_trang_dinh_dang_kieu_viet_nam(): void
    {
        $data = (new CustomerDebtListPresenter)->viewData(
            $this->paginator([]),
            ['total_customers' => 12, 'total_amount' => 10_000_000, 'paid_amount' => 4_000_000, 'debt_amount' => 6_000_000],
        );

        $this->assertSame('12', $data['totalCustomersText']);
        // Tiền ở trang này có DẤU CÁCH trước `đ` -> đúng DisplayFormat::money().
        $this->assertSame('10.000.000 đ', $data['revenueTotalText']);
        $this->assertSame('4.000.000 đ', $data['paidTotalText']);
        $this->assertSame('6.000.000 đ', $data['debtTotalText']);
    }

    public function test_thieu_total_customers_thi_lay_tong_cua_paginator(): void
    {
        $data = (new CustomerDebtListPresenter)->viewData(
            $this->paginator([$this->khach(), $this->khach(['group_key' => 'kh-2'])]),
            ['total_amount' => 0, 'paid_amount' => 0, 'debt_amount' => 0],
        );

        $this->assertSame('2', $data['totalCustomersText'], 'rơi về $customers->total() như bản cũ');
    }

    public function test_trang_thai_khach_theo_so_no(): void
    {
        $data = (new CustomerDebtListPresenter)->viewData(
            $this->paginator([
                $this->khach(['group_key' => 'a', 'debt_amount' => 6_000_000]),
                $this->khach(['group_key' => 'b', 'debt_amount' => 0]),
                // Nợ ÂM (đã thu quá) cũng tính là xong: bản cũ so `> 0`.
                $this->khach(['group_key' => 'c', 'debt_amount' => -500]),
            ]),
            [],
        );

        $rows = $data['customers']->items();
        $this->assertStringContainsString('tw:text-[#b91c1c]', $rows[0]->statusClass, 'tông đỏ');
        $this->assertSame('Công nợ', $rows[0]->statusText);
        $this->assertStringContainsString('tw:text-[#047857]', $rows[1]->statusClass, 'tông xanh');
        $this->assertSame('Đã hoàn thành', $rows[1]->statusText);
        $this->assertSame('Đã hoàn thành', $rows[2]->statusText);
    }

    /**
     * Cờ "đã hoàn thành" của ĐƠN có BA vế `||`; hai vế đầu dễ quên khi chép lại.
     *
     * @param  array<string, mixed>  $ghiDe
     */
    #[DataProvider('trangThaiDon')]
    public function test_trang_thai_don_giu_du_ba_ve(array $ghiDe, string $mong): void
    {
        $data = (new CustomerDebtListPresenter)->viewData(
            $this->paginator([$this->khach([], [$this->don($ghiDe)])]),
            [],
        );

        $this->assertSame($mong, $data['customers']->items()[0]->orders[0]->statusText);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function trangThaiDon(): array
    {
        return [
            'còn nợ thật' => [[], 'Công nợ'],
            'tổng tiền = 0' => [['total_amount' => 0], 'Đã hoàn thành'],
            'tổng tiền âm' => [['total_amount' => -1], 'Đã hoàn thành'],
            'nợ = 0' => [['debt_amount' => 0], 'Đã hoàn thành'],
            'nợ âm' => [['debt_amount' => -1], 'Đã hoàn thành'],
            'đã ghi nhận thanh toán' => [['payment_recorded' => 1], 'Đã hoàn thành'],
            'payment_recorded null' => [['payment_recorded' => null], 'Công nợ'],
        ];
    }

    /**
     * Câu hỏi xác nhận xoá: bản cũ ghép trong <script> từ hai thuộc tính `data-*`; nay presenter
     * dựng sẵn để Alpine gọi thẳng `window.confirm()`. Giữ NGUYÊN từng chữ và từng chỗ xuống dòng —
     * đây là chuỗi người dùng đọc, đổi một dấu cách cũng là đổi hành vi.
     */
    public function test_cau_hoi_xoa_giu_nguyen_tung_chu_va_cho_xuong_dong(): void
    {
        $data = (new CustomerDebtListPresenter)->viewData(
            $this->paginator([$this->khach([], [$this->don(['order_code' => 'ORD-9', 'debt_amount' => 2_500_000])])]),
            [],
        );

        $this->assertSame(
            "Xóa khoản công nợ này khỏi danh sách?\n\n"
            ."Mã đơn: ORD-9\n"
            ."Còn nợ: 2.500.000 đ\n\n"
            .'Đơn hàng CRM sẽ không bị xóa.',
            $data['customers']->items()[0]->orders[0]->deleteConfirmText
        );
    }

    /** Mã đơn có dấu nháy vẫn phải ra chuỗi JS hợp lệ khi qua `@js` trong thuộc tính HTML. */
    public function test_cau_hoi_xoa_qua_js_an_toan_voi_dau_nhay(): void
    {
        $data = (new CustomerDebtListPresenter)->viewData(
            $this->paginator([$this->khach([], [$this->don(['order_code' => 'ORD-\'"<X>'])])]),
            [],
        );

        $html = \Illuminate\Support\Js::from($data['customers']->items()[0]->orders[0]->deleteConfirmText)->toHtml();

        // `Js::toHtml()` BỌC giá trị trong hai nháy đơn (đó là dấu chuỗi JS), còn mọi ký tự bên
        // trong đều thành `\uXXXX`. View nhúng nó vào thuộc tính `x-on:submit="…"` — nháy KÉP —
        // nên điều phải chốt là: 0 nháy kép, và đúng 2 nháy đơn (chỉ hai cái bọc ngoài).
        $this->assertSame(0, substr_count($html, '"'), 'còn nháy kép thô — vỡ thuộc tính HTML');
        $this->assertSame(2, substr_count($html, "'"), 'nháy đơn lọt vào trong — vỡ chuỗi JS');
        $this->assertStringNotContainsString('<', $html, 'còn dấu < thô — thoát được ra khỏi thuộc tính');
        $this->assertStringStartsWith("'", $html);
        $this->assertStringEndsWith("'", $html);
    }

    public function test_id_hang_chi_tiet_la_md5_cua_khoa_nhom(): void
    {
        $data = (new CustomerDebtListPresenter)->viewData(
            $this->paginator([$this->khach(['group_key' => 'kh-abc'])]),
            [],
        );

        $this->assertSame(md5('kh-abc'), $data['customers']->items()[0]->detailId);
    }

    public function test_chu_cai_dau_ten_khach_an_toan_voi_tieng_viet(): void
    {
        $data = (new CustomerDebtListPresenter)->viewData(
            $this->paginator([
                $this->khach(['group_key' => 'a', 'customer_name' => 'Ánh Dương']),
                $this->khach(['group_key' => 'b', 'customer_name' => '']),
            ]),
            [],
        );

        $rows = $data['customers']->items();
        // `mb_substr` chứ không `substr`: `substr('Á', 0, 1)` cắt giữa ký tự UTF-8 ra rác.
        $this->assertSame('Á', $rows[0]->avatarInitial);
        $this->assertSame('', $rows[1]->avatarInitial);
    }

    public function test_ngay_trong_ra_chuoi_rong_chu_khong_phai_gach_dai(): void
    {
        $data = (new CustomerDebtListPresenter)->viewData(
            $this->paginator([$this->khach([], [
                $this->don(['created_at' => '2026-09-01 08:30:00']),
                $this->don(['id' => 8, 'created_at' => null]),
            ])]),
            [],
        );

        $orders = $data['customers']->items()[0]->orders;
        $this->assertSame('01/09/2026 08:30', $orders[0]->createdAtText);
        // Bản cũ `optional(...)->format()` in RỖNG, không phải `—` của DisplayFormat::date().
        $this->assertSame('', $orders[1]->createdAtText);
    }
}
