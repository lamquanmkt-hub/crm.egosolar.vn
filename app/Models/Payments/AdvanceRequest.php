<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdvanceRequest extends Model
{
    protected $fillable = [
        'code', 'created_by', 'company', 'recipient_name', 'amount', 'reason', 'needed_date',
        'settlement_due_date', 'bank_name', 'bank_account', 'bank_account_name', 'status', 'note',
        'submitted_at', 'admin_approved_by', 'admin_approved_at', 'accounting_approved_by', 'accounting_approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'needed_date' => 'date',
        'settlement_due_date' => 'date',
        'submitted_at' => 'datetime',
        'admin_approved_at' => 'datetime',
        'accounting_approved_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function settlementRequest(): HasOne
    {
        return $this->hasOne(SettlementRequest::class, 'advance_request_id');
    }
}
