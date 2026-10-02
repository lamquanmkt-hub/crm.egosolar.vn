<?php

namespace App\Models\Payments;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    protected $fillable = [
        'payment_content',
        'doc_type',          // ✅ thêm dòng này
        'code',
        'created_by',
        'company',
        'receiver_name',
        'department',
        'reason',
        'amount',
        'bank_info',
        'bank_name',
        'bank_account',
        'bank_account_name',
        'status',
        'admin_approved_by',
        'admin_approved_at',
        'admin_note',
        'accounting_approved_by',
        'accounting_approved_at',
        'accounting_note',
        'payment_due_date',
        'company_id',
        'site_id',
        'cost_type',
    ];

    // Công ty
    public const COMPANY_EGO = 'Công ty TNHH Ego Viet Nam';

    public const COMPANY_EGP = 'Công ty TNHH Thương Mại Kỹ Thuật Quốc Tế EGO';

    public static function companyOptions(): array
    {
        return [
            self::COMPANY_EGO => self::COMPANY_EGO,
            self::COMPANY_EGP => self::COMPANY_EGP,
        ];
    }

    // Map trạng thái -> tiếng Việt
    public const STATUS_LABELS = [
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'admin_approved' => 'Giám đốc đã duyệt',
        'admin_rejected' => 'Giám đốc từ chối',
        'accounting_approved' => 'Kế toán đã chi',
        'accounting_rejected' => 'Kế toán từ chối',
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    // Người tạo
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Giám đốc duyệt
    public function director()
    {
        return $this->belongsTo(User::class, 'admin_approved_by');
    }

    // Kế toán duyệt/chi
    public function accountant()
    {
        return $this->belongsTo(User::class, 'accounting_approved_by');
    }

    /**
     * Chứng từ đính kèm, sắp theo id tăng dần.
     *
     * Thứ tự ghim ở đây vì trước kia hai view tự truy vấn lại bảng
     * `payment_attachments` kèm `orderBy('id')` thay vì dùng quan hệ đã eager
     * load — bỏ truy vấn đó đi mà quan hệ không có thứ tự thì danh sách chứng từ
     * đổi thứ tự tuỳ CSDL trả về.
     */
    public function attachments()
    {
        return $this->hasMany(\App\Models\Payments\PaymentAttachment::class)
            ->orderBy('id');
    }

    protected $casts = [
        'payment_due_date' => 'date',
    ];
}
