<?php

declare(strict_types=1);

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một mốc nhắc bảo hành của công trình.
 *
 * Thay cho nhóm lặp `warranty_reminder_1_at` / `_2_at` / `_3_at`. Nhờ vậy thêm
 * mốc thứ tư chỉ là thêm một DÒNG, không phải đổi lược đồ.
 */
class SiteWarrantyReminder extends Model
{
    protected $table = 'site_warranty_reminders';

    /** Ô trên form công trình => số thứ tự mốc (form vẫn gửi 3 ô cố định; bảng chứa được nhiều hơn). */
    public const FORM_FIELDS = [
        'warranty_reminder_1_at' => 1,
        'warranty_reminder_2_at' => 2,
        'warranty_reminder_3_at' => 3,
    ];

    protected $fillable = ['site_id', 'sequence', 'remind_at'];

    protected $casts = [
        'sequence' => 'integer',
        'remind_at' => 'date',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
