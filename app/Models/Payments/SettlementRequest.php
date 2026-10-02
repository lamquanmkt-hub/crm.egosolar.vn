<?php

namespace App\Models\Payments;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementRequest extends Model
{
    protected $fillable = [
        'code', 'advance_request_id', 'created_by', 'company', 'recipient_name',
        'advance_amount', 'actual_amount', 'refund_amount', 'difference_amount', 'settlement_type',
        'attachments', 'reason', 'status', 'note', 'submitted_at', 'admin_approved_by', 'admin_approved_at',
        'accounting_approved_by', 'accounting_approved_at',
    ];

    protected $casts = [
        'advance_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'admin_approved_at' => 'datetime',
        'accounting_approved_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function advanceRequest(): BelongsTo
    {
        return $this->belongsTo(AdvanceRequest::class, 'advance_request_id');
    }
}
