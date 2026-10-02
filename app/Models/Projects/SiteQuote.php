<?php

declare(strict_types=1);

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một bản báo giá của công trình.
 *
 * Tách ra từ 21 cột `quote_*` từng nằm trong `sites`. Cột `version` là lý do
 * chính của đợt tách: trước đây mỗi công trình chỉ chứa nổi đúng một báo giá.
 */
class SiteQuote extends Model
{
    protected $table = 'site_quotes';

    protected $fillable = [
        'site_id', 'version', 'code', 'status', 'valid_until', 'issued_on',
        'customer_tax_code', 'customer_email', 'customer_company',
        'application_note', 'config_summary', 'approved_at', 'sent_at',
        'subtotal', 'discount_amount', 'vat_percent', 'vat_amount', 'grand_total',
        'pdf_path', 'om_terms', 'warranty_terms', 'commercial_terms', 'scope',
    ];

    protected $casts = [
        'version' => 'integer',
        'valid_until' => 'date',
        'issued_on' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
