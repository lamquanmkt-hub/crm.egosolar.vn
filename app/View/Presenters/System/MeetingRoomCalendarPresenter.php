<?php

declare(strict_types=1);

namespace App\View\Presenters\System;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\ViewErrorBag;

/**
 * Chuẩn bị giá trị cho view `meeting-room-bookings.index` (lịch phòng họp theo tháng).
 *
 * Trước 2026-09-07 view tự tính trong 5 khối `@php`: cờ mở modal tạo mới sau khi gửi lỗi, closure
 * ghép query chuyển tháng, tên thứ, gom lịch theo ngày cho bản mobile; rồi mỗi ô lịch / mỗi lịch
 * lại parse Carbon và tính cờ. Nay mỗi ô và mỗi lịch được dựng một lần.
 */
final class MeetingRoomCalendarPresenter
{
    private const DAY_NAMES = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

    /** Số lịch hiện tối đa trong một ô lịch tháng; phần còn lại gộp thành "+N lịch khác". */
    private const VISIBLE_PER_DAY = 3;

    /**
     * @param  Collection<int, object>  $monthBookings  lịch trong tháng (đã lọc phòng/trạng thái, bỏ huỷ)
     * @param  list<Carbon>  $calendarDays  các ngày của lưới tháng (đủ tuần đầu/cuối)
     * @param  mixed  $openModal  session('open_booking_modal')
     * @param  mixed  $editingBookingId  session('editing_booking_id')
     * @return array<string, mixed> chỉ các giá trị dẫn xuất; controller gộp với dữ liệu gốc
     */
    public function viewData(Collection $monthBookings, array $calendarDays, Carbon $monthStart, string $room, string $usageStatus, ViewErrorBag $errors, mixed $openModal, mixed $editingBookingId): array
    {
        $bookingsByDate = $monthBookings->groupBy(static fn (object $booking): string => Carbon::parse($booking->start_at)->toDateString());
        $previousMonth = $monthStart->copy()->subMonthNoOverflow()->format('Y-m');
        $nextMonth = $monthStart->copy()->addMonthNoOverflow()->format('Y-m');
        $currentMonth = now()->format('Y-m');
        $query = fn (string $month): array => array_filter([
            'month' => $month,
            'room_name' => $room,
            'usage_status' => $usageStatus,
        ], static fn ($value) => $value !== '');

        return [
            'hasCreateErrors' => $openModal === 'create' || ($errors->any() && ! $editingBookingId),
            'monthLabel' => 'Tháng '.$monthStart->format('m/Y'),
            'previousMonth' => $previousMonth,
            'nextMonth' => $nextMonth,
            'currentMonth' => $currentMonth,
            'previousMonthQuery' => $query($previousMonth),
            'nextMonthQuery' => $query($nextMonth),
            'currentMonthQuery' => $query($currentMonth),
            'dayNames' => self::DAY_NAMES,
            'calendarCells' => array_map(fn (Carbon $day) => $this->cell($day, $bookingsByDate, $monthStart), $calendarDays),
            'agendaGroups' => $bookingsByDate
                ->map(fn (Collection $bookings, string $dateKey) => [
                    'dateKey' => $dateKey,
                    'date' => Carbon::parse($dateKey),
                    'bookings' => $bookings->map(fn (object $booking) => $this->entry($booking))->values()->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Một ô lịch tháng: ngày, thuộc tháng đang xem, hôm nay, tối đa 3 lịch và số lịch còn lại.
     *
     * @param  Collection<string, Collection<int, object>>  $bookingsByDate
     * @return array<string, mixed>
     */
    private function cell(Carbon $day, Collection $bookingsByDate, Carbon $monthStart): array
    {
        $dateKey = $day->toDateString();
        $bookings = $bookingsByDate->get($dateKey, collect());
        $visible = $bookings->take(self::VISIBLE_PER_DAY);

        return [
            'day' => $day,
            'dateKey' => $dateKey,
            'isCurrentMonth' => $day->month === $monthStart->month,
            'isToday' => $day->isToday(),
            'count' => $bookings->count(),
            'bookings' => $visible->map(fn (object $booking) => $this->entry($booking))->values()->all(),
            'remaining' => max(0, $bookings->count() - $visible->count()),
        ];
    }

    /** @return array{booking: object, start: Carbon, end: Carbon, used: bool} */
    private function entry(object $booking): array
    {
        return [
            'booking' => $booking,
            'start' => Carbon::parse($booking->start_at),
            'end' => Carbon::parse($booking->end_at),
            'used' => (string) $booking->usage_status === 'used',
        ];
    }
}
