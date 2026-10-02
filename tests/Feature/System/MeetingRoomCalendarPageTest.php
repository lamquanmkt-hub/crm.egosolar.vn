<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Enums\Role;
use App\View\Presenters\System\MeetingRoomCalendarPresenter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** Lịch phòng họp sau khi dời 5 khối `@php` sang MeetingRoomCalendarPresenter (2026-09-07). */
final class MeetingRoomCalendarPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/meeting-room-bookings/index.blade.php';

    private const URL = '/booking-phong-hop';

    private const VIEW_VARIABLES_NOT_FROM_PRESENTER = ['cell', 'entry', 'group', 'dayName', 'roomOption', 'value', 'label', 'errors', 'loop', 'slot', 'attributes', 'component',
        'monthBookings', 'monthStart', 'rooms', 'usageStatuses', 'organizers', 'stats', 'room', 'usageStatus'];

    public function test_trang_in_o_lich_va_mo_modal_sau_khi_gui_sai(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $today = now()->toDateString();
        foreach ([['09:00', '10:00', 'used'], ['10:30', '11:00', 'unused'], ['14:00', '16:00', 'unused'], ['16:30', '17:00', 'used']] as $i => [$from, $to, $usage]) {
            DB::table('meeting_room_bookings')->insert(['room_name' => 'Phòng họp lớn', 'title' => 'Lịch '.$i, 'organizer_name' => 'An', 'attendees' => 2, 'start_at' => "$today $from:00", 'end_at' => "$today $to:00", 'status' => 'booked', 'usage_status' => $usage, 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $html = $this->actingAs($admin)->get(self::URL.'?month='.now()->format('Y-m'))->assertOk()->getContent();
        $this->assertStringContainsString('is-today', $html);
        $this->assertStringContainsString('mrb3-day__count">4<', $html);
        $this->assertStringContainsString('+1 lịch khác', $html, 'ô lịch chỉ hiện 3');
        $this->assertStringContainsString('data-open-create="0"', $html);
        $this->assertStringContainsString('class="mrb3-event is-used"', $html);
        $this->assertStringContainsString('<span class="mrb3-event__time">09:00</span>', $html);

        $this->actingAs($admin)->from(self::URL)->post(self::URL, ['room_name' => 'Phòng họp lớn'])->assertRedirect();
        $html = $this->actingAs($admin)->get(self::URL)->assertOk()->getContent();
        $this->assertStringContainsString('data-open-create="1"', $html, 'gửi sai → mở lại modal tạo');
    }

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);
        $this->assertStringNotContainsString('Carbon::parse', $source, 'không parse ngày trong view');

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $used = array_values(array_unique(array_diff($m[1], self::VIEW_VARIABLES_NOT_FROM_PRESENTER)));
        $provided = array_keys(app(MeetingRoomCalendarPresenter::class)->viewData(collect(), [], Carbon::now(), '', '', new ViewErrorBag, null, null));
        $this->assertSame([], array_values(array_diff($used, $provided)), 'biến view dùng mà presenter không trả');
    }
}
