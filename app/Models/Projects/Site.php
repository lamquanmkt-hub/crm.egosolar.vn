<?php

namespace App\Models\Projects;

use App\Models\Payment;
use App\Models\Payments\PaymentRequest;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    protected $table = 'sites';

    protected $fillable = [
        // Core
        'company_id',
        'created_by',
        'project_type',
        'project_code',
        'project_phase',
        'progress_percent',
        'priority',
        'lead_engineer_id',
        'target_completion_at',
        'workflow_version',
        'workflow_current_step',
        'workflow_status',
        'workflow_progress_percent',
        'legacy_source',
        'legacy_source_id',
        'name',
        'status',
        'address',
        'contact_name',
        'contact_phone',
        'note',
        // update
        'deployment_started_at',
        'completed_at',
        'solar_panel_qty',
        'solar_panel_wp',
        'battery_kwh',

        // System info
        'system_kwp',
        'system_kw_ac',
        'system_type',
        'phase',
        'installed_at',
        'warranty_to',
        'technician_name',
        'monitoring_link',
        'monitoring_account',
        'stage',

        // Finance
        'contract_amount',
        'contract_amount_before_vat',
        'vat_rate',
        'contract_amount_after_vat',
        'handover_at',
        'warranty_started_at',
        'labor_cost',
        'transport_cost',
        'other_cost',
        'other_cost_note',

        'contract_signed_at',
        'finance_note',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'warranty_to' => 'date',
        'contract_signed_at' => 'date',

        'system_kwp' => 'float',
        'system_kw_ac' => 'float',
        'deployment_started_at' => 'date',
        'completed_at' => 'date',
        'solar_panel_qty' => 'integer',
        'solar_panel_wp' => 'float',
        'battery_kwh' => 'float',
        'contract_amount' => 'decimal:2',
        'contract_amount_before_vat' => 'decimal:2',
        'vat_rate' => 'decimal:3',
        'contract_amount_after_vat' => 'decimal:2',
        'handover_at' => 'date',
        'warranty_started_at' => 'date',
        'workflow_progress_percent' => 'integer',
        'progress_percent' => 'integer',
        'target_completion_at' => 'date',
    ];

    /**
     * Các bản báo giá của công trình (mới nhất trước).
     *
     * Thay cho 21 cột `quote_*` từng nằm ngay trong `sites` (tách 2026_09_04_110000,
     * xoá cột cũ 2026_09_07_150000).
     */
    public function quotes()
    {
        return $this->hasMany(SiteQuote::class)->orderByDesc('version');
    }

    /** Bản báo giá version cao nhất — bản đang có hiệu lực của công trình. */
    public function latestQuote(): HasOne
    {
        return $this->hasOne(SiteQuote::class)->ofMany('version', 'max');
    }

    /** Tên công ty khách trên bản báo giá mới nhất; chưa báo giá → null. */
    public function quoteCustomerCompany(): ?string
    {
        return $this->latestQuote?->customer_company;
    }

    /** Tổng tiền bản báo giá mới nhất; chưa báo giá → 0. */
    public function quoteGrandTotal(): float
    {
        return (float) ($this->latestQuote?->grand_total ?? 0);
    }

    /** Các mốc nhắc bảo hành, thay cho nhóm lặp `warranty_reminder_1/2/3_at`. */
    public function warrantyReminders()
    {
        return $this->hasMany(SiteWarrantyReminder::class)->orderBy('sequence');
    }

    public function plannedMaterials()
    {
        return $this->hasMany(
            \App\Models\Projects\SitePlannedMaterial::class,
            'site_id'
        );
    }

    public function devices()
    {
        return $this->hasMany(
            \App\Models\Projects\SiteDevice::class,
            'site_id'
        );
    }

    public function paymentTerms()
    {
        return $this->hasMany(
            \App\Models\Projects\SitePaymentTerm::class,
            'site_id'
        );
    }

    public function materialRequests()
    {
        return $this->hasMany(
            \App\Models\Projects\MaterialRequest::class,
            'site_id'
        );
    }

    public function receipts()
    {
        return $this->hasMany(
            Receipt::class,
            'site_id'
        );
    }

    public function payments()
    {
        return $this->hasMany(
            Payment::class,
            'site_id'
        );
    }

    public function paymentRequests()
    {
        return $this->hasMany(
            PaymentRequest::class,
            'site_id'
        );
    }

    public function leadEngineer()
    {
        return $this->belongsTo(\App\Models\User::class, 'lead_engineer_id');
    }

    public function tasks()
    {
        return $this->hasMany(\App\Models\Tasks\Task::class, 'site_id');
    }
}
