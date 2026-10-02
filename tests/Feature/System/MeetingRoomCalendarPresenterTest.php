<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\View\Presenters\System\MeetingRoomCalendarPresenter;
use Carbon\Carbon;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** {@see MeetingRoomCalendarPresenter} thay 5 khối `@php` của meeting-room-bookings/index (2026-09-07). Đồng hồ cố định. */
final class MeetingRoomCalendarPresenterTest extends TestCase
{
    private MeetingRoomCalendarPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-07 09:00:00');
        $this->presenter = new MeetingRoomCalendarPresenter;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_co_mo_modal_tao_moi_va_query_chuyen_thang(): void
    {
        $base = $this->data();
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['title' => ['Thiếu tiêu đề']]));

        $this->assertTrue($this->present($base, $errors)['hasCreateErrors'], 'có lỗi và không đang sửa → mở modal tạo');
        $this->assertFalse($this->present($base, $errors, 'edit', 5)['hasCreateErrors'], 'đang sửa → không mở modal tạo');
        $this->assertTrue($this->present($base, new ViewErrorBag, 'create')['hasCreateErrors']);
        $this->assertFalse($this->present($base, new ViewErrorBag)['hasCreateErrors']);

        $data = $this->present(array_merge($base, ['room' => 'Phòng họp lớn']), new ViewErrorBag);
        $this->assertSame(['2026-08', '2026-10', '2026-09', 'Tháng 09/2026'], [$data['previousMonth'], $data['nextMonth'], $data['currentMonth'], $data['monthLabel']]);
        $this->assertSame(['month' => '2026-08', 'room_name' => 'Phòng họp lớn'], $data['previousMonthQuery'], 'bỏ tham số rỗng');
        $this->assertSame(['month' => '2026-10', 'room_name' => 'Phòng họp lớn'], $data['nextMonthQuery']);
        $this->assertSame(['month' => '2026-09', 'room_name' => 'Phòng họp lớn'], $data['currentMonthQuery']);
        $this->assertSame(['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'], $data['dayNames']);
    }

    public function test_o_lich_toi_da_ba_lich_va_agenda_gom_theo_ngay(): void
    {
        $bookings = collect([
            $this->booking(1, '2026-09-07 09:00:00', '2026-09-07 10:00:00', 'used'),
            $this->booking(2, '2026-09-07 10:30:00', '2026-09-07 11:00:00', 'unused'),
            $this->booking(3, '2026-09-07 14:00:00', '2026-09-07 16:00:00', 'unused'),
            $this->booking(4, '2026-09-07 16:30:00', '2026-09-07 17:00:00', 'used'),
            $this->booking(5, '2026-09-15 08:30:00', '2026-09-15 09:30:00', 'unused'),
        ]);
        $days = [Carbon::parse('2026-08-31'), Carbon::parse('2026-09-07'), Carbon::parse('2026-09-15'), Carbon::parse('2026-09-16')];

        $data = $this->present($this->data($bookings, $days), new ViewErrorBag);

        $cells = $data['calendarCells'];
        $this->assertCount(4, $cells);
        $this->assertSame(['2026-08-31', false, false, 0, 0, 0], [$cells[0]['dateKey'], $cells[0]['isCurrentMonth'], $cells[0]['isToday'], $cells[0]['count'], count($cells[0]['bookings']), $cells[0]['remaining']]);
        $this->assertSame([true, true, 4, 3, 1], [$cells[1]['isCurrentMonth'], $cells[1]['isToday'], $cells[1]['count'], count($cells[1]['bookings']), $cells[1]['remaining']], 'hôm nay: 4 lịch, hiện 3, còn 1');
        $this->assertSame([1, '09:00', '10:00', true], [$cells[1]['bookings'][0]['booking']->id, $cells[1]['bookings'][0]['start']->format('H:i'), $cells[1]['bookings'][0]['end']->format('H:i'), $cells[1]['bookings'][0]['used']]);
        $this->assertSame([1, 1, 0], [$cells[2]['count'], count($cells[2]['bookings']), $cells[2]['remaining']]);

        $groups = $data['agendaGroups'];
        $this->assertSame(['2026-09-07', '2026-09-15'], array_column($groups, 'dateKey'));
        $this->assertSame('07', $groups[0]['date']->format('d'));
        $this->assertSame([1, 2, 3, 4], array_map(fn (array $entry) => $entry['booking']->id, $groups[0]['bookings']), 'agenda không cắt 3');
        $this->assertFalse($groups[0]['bookings'][1]['used']);
    }

    private function present(array $input, ViewErrorBag $errors, mixed $openModal = null, mixed $editingId = null): array
    {
        return $this->presenter->viewData($input['monthBookings'], $input['calendarDays'], $input['monthStart'], $input['room'], $input['usageStatus'], $errors, $openModal, $editingId);
    }

    private function data(?\Illuminate\Support\Collection $bookings = null, array $days = []): array
    {
        return ['monthBookings' => $bookings ?? collect(), 'calendarDays' => $days, 'monthStart' => Carbon::parse('2026-09-01'), 'room' => '', 'usageStatus' => ''];
    }

    private function booking(int $id, string $start, string $end, string $usage): object
    {
        return (object) ['id' => $id, 'title' => 'Lịch '.$id, 'room_name' => 'Phòng họp lớn', 'start_at' => $start, 'end_at' => $end, 'usage_status' => $usage];
    }
}
