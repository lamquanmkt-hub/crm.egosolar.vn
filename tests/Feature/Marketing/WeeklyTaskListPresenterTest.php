<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\View\Presenters\Marketing\WeeklyTaskListPresenter;
use Tests\TestCase;

/**
 * WeeklyTaskListPresenter thay 2 khối `@php` của bảng công việc tuần.
 *
 * Khẳng định giá trị thật, kể cả các nhánh mặc định của `match()` cũ và chỗ CỐ Ý khác quy ước
 * chung: ô trống hiển thị gạch NGANG `-`, không phải gạch DÀI `—` của DisplayFormat::date.
 */
final class WeeklyTaskListPresenterTest extends TestCase
{
    private function task(array $attributes = []): object
    {
        return (object) array_merge([
            'id' => 1, 'title' => 'Việc A', 'priority' => 'high', 'category' => 'Content',
            'assignee' => 'An', 'start_date' => '2026-01-05', 'due_date' => '2026-01-09',
            'status' => 'pending',
        ], $attributes);
    }

    public function test_danh_sach_rong(): void
    {
        $data = (new WeeklyTaskListPresenter)->viewData([]);

        $this->assertSame([], $data['taskRows']);
        $this->assertSame(0, $data['taskCount']);
    }

    public function test_badge_va_chu_hoa_cho_tung_muc_uu_tien(): void
    {
        $data = (new WeeklyTaskListPresenter)->viewData([
            $this->task(['priority' => 'high']),
            $this->task(['priority' => 'medium']),
            $this->task(['priority' => 'low']),
        ]);

        $this->assertSame('bg-danger', $data['taskRows'][0]->priorityBadge);
        $this->assertSame('HIGH', $data['taskRows'][0]->priorityText);
        $this->assertSame('bg-warning text-dark', $data['taskRows'][1]->priorityBadge);
        $this->assertSame('bg-info text-dark', $data['taskRows'][2]->priorityBadge);
        $this->assertSame(3, $data['taskCount']);
    }

    public function test_badge_trang_thai_va_nhanh_mac_dinh(): void
    {
        $data = (new WeeklyTaskListPresenter)->viewData([
            $this->task(['status' => 'done']),
            $this->task(['status' => 'doing']),
            $this->task(['status' => 'pending']),
            // Cột enum có 'overdue' nhưng bản cũ KHÔNG có nhánh cho nó -> bg-secondary.
            $this->task(['status' => 'overdue']),
        ]);

        $this->assertSame('bg-success', $data['taskRows'][0]->statusBadge);
        $this->assertSame('bg-primary', $data['taskRows'][1]->statusBadge);
        $this->assertSame('bg-secondary', $data['taskRows'][2]->statusBadge);
        $this->assertSame('bg-secondary', $data['taskRows'][3]->statusBadge);
        $this->assertSame('OVERDUE', $data['taskRows'][3]->statusText);
    }

    public function test_gia_tri_la_hoac_trong_thi_ve_mac_dinh(): void
    {
        $data = (new WeeklyTaskListPresenter)->viewData([
            $this->task(['priority' => '', 'status' => null, 'category' => null, 'assignee' => null,
                'start_date' => null, 'due_date' => null]),
        ]);

        $row = $data['taskRows'][0];
        $this->assertSame('bg-secondary', $row->priorityBadge);
        $this->assertSame('N/A', $row->priorityText);
        $this->assertSame('bg-secondary', $row->statusBadge);
        $this->assertSame('N/A', $row->statusText);
        $this->assertSame('-', $row->category);
        $this->assertSame('-', $row->assignee);
        // Gạch NGANG, KHÔNG phải gạch dài — xem chú thích trong presenter.
        $this->assertSame('-', $row->startDateText);
        $this->assertSame('-', $row->dueDateText);
    }

    public function test_ngay_dinh_dang_y_m_d_va_chuoi_la_thi_tra_nguyen(): void
    {
        $data = (new WeeklyTaskListPresenter)->viewData([
            $this->task(['start_date' => '2026-01-05 13:45:00', 'due_date' => 'không phải ngày']),
        ]);

        $row = $data['taskRows'][0];
        // Bỏ giờ, chỉ còn Y-m-d — đúng chủ đích của bản cũ.
        $this->assertSame('2026-01-05', $row->startDateText);
        // Parse thất bại thì trả nguyên chuỗi, như catch của closure cũ.
        $this->assertSame('không phải ngày', $row->dueDateText);
    }

    public function test_uu_tien_la_cung_ve_badge_mac_dinh(): void
    {
        // Cột là enum nên giá trị này không xuất hiện từ DB, nhưng nhánh default của bản cũ
        // vẫn tồn tại — test trực tiếp presenter để nhánh đó không thành mã chết không ai kiểm.
        $data = (new WeeklyTaskListPresenter)->viewData([$this->task(['priority' => 'khẩn'])]);

        $this->assertSame('bg-secondary', $data['taskRows'][0]->priorityBadge);
        // `strtoupper` của PHP làm việc theo BYTE, không hiểu UTF-8: 'khẩn' -> 'KHẩN' (chữ ẩ
        // nhiều byte nên không đổi). Bản cũ cũng dùng strtoupper nên presenter giữ y hệt;
        // đổi sang mb_strtoupper sẽ cho 'KHẨN' — tức là ĐỔI hành vi, không phải sửa lỗi.
        $this->assertSame('KHẩN', $data['taskRows'][0]->priorityText);
    }
}
