<?php

declare(strict_types=1);

namespace App\DTOs\Technical;

use App\Models\SolarMaintenanceSchedule;
use Illuminate\Support\Carbon;

/** Một dòng lịch O&M trên dashboard: model gốc + các giá trị dẫn xuất view cũ tự tính trong `@php`. */
final readonly class MaintenanceScheduleRow
{
    public function __construct(
        public SolarMaintenanceSchedule $schedule,
        public bool $overdue,
        public int $roundNo,
        public int $totalRounds,
        public ?string $leaderName,
        public bool $isUnassigned,
        public string $avatarInitial,
        public ?Carbon $scheduledDate,
        public string $timeLabel,
        public string $timeTone,
        public mixed $capacity,
    ) {}
}
