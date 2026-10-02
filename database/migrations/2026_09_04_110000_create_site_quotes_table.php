<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tách 21 cột `quote_*` khỏi `sites` thành bảng `site_quotes`.
 *
 * ## Vì sao tách
 * Báo giá là một THỰC THỂ RIÊNG, không phải thuộc tính của công trình: một công
 * trình có thể có nhiều bản báo giá qua các lần chỉnh. Nhét vào `sites` thì mỗi
 * công trình chỉ chứa nổi ĐÚNG MỘT báo giá, và 21 cột đó chiếm 1/4 số cột của
 * bảng nhưng chỉ có nghĩa với những công trình đang chào giá.
 *
 * ## Đã đo trước khi làm (production, 2026-09-04)
 * 19 công trình, và **0 bản ghi** có `quote_no`, `quote_grand_total <> 0`,
 * `quote_scope` hay `quote_pdf_path`. Nên phần chép dữ liệu gần như chắc chắn
 * không có gì để chép — vẫn viết đầy đủ và idempotent, phòng trường hợp có dữ
 * liệu phát sinh giữa lúc viết và lúc deploy.
 *
 * ## Đây là bước EXPAND, chưa phải CONTRACT
 * KHÔNG đụng tới 21 cột cũ trong `sites`. Theo quy tắc của dự án
 * (SKILL mục 4), việc xoá cột chỉ được làm ở một migration RIÊNG đợt sau, sau
 * khi code đã ghi song song qua ít nhất một lần deploy và số liệu hai bên khớp.
 *
 * Kiểu cột chép nguyên từ `sites` để không thu hẹp: varchar(50)/(255),
 * decimal(15,2), decimal(6,2), text, longtext.
 */
return new class extends Migration
{
    /** quote_* trong `sites`  =>  tên cột trong `site_quotes` */
    private const COLUMN_MAP = [
        'quote_no' => 'code',
        'quote_status' => 'status',
        'quote_valid_until' => 'valid_until',
        'quote_date' => 'issued_on',
        'quote_customer_tax_code' => 'customer_tax_code',
        'quote_customer_email' => 'customer_email',
        'quote_customer_company' => 'customer_company',
        'quote_application_note' => 'application_note',
        'quote_config_summary' => 'config_summary',
        'quote_approved_at' => 'approved_at',
        'quote_sent_at' => 'sent_at',
        'quote_subtotal' => 'subtotal',
        'quote_discount_amount' => 'discount_amount',
        'quote_vat_percent' => 'vat_percent',
        'quote_vat_amount' => 'vat_amount',
        'quote_grand_total' => 'grand_total',
        'quote_pdf_path' => 'pdf_path',
        'quote_om_terms' => 'om_terms',
        'quote_warranty_terms' => 'warranty_terms',
        'quote_commercial_terms' => 'commercial_terms',
        'quote_scope' => 'scope',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('site_quotes')) {
            Schema::create('site_quotes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();

                // Cho phép nhiều bản báo giá trên một công trình — lý do chính của đợt tách.
                $table->unsignedSmallInteger('version')->default(1);

                $table->string('code', 50)->nullable();
                $table->string('status', 50)->nullable()->default('draft');
                $table->date('valid_until')->nullable();
                $table->date('issued_on')->nullable();

                $table->string('customer_tax_code', 50)->nullable();
                $table->string('customer_email')->nullable();
                $table->string('customer_company')->nullable();

                $table->text('application_note')->nullable();
                $table->text('config_summary')->nullable();

                $table->timestamp('approved_at')->nullable();
                $table->timestamp('sent_at')->nullable();

                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('vat_percent', 6, 2)->default(0);
                $table->decimal('vat_amount', 15, 2)->default(0);
                $table->decimal('grand_total', 15, 2)->default(0);

                $table->string('pdf_path')->nullable();

                $table->longText('om_terms')->nullable();
                $table->longText('warranty_terms')->nullable();
                $table->longText('commercial_terms')->nullable();
                $table->longText('scope')->nullable();

                $table->timestamps();

                $table->unique(['site_id', 'version']);
                $table->index('code');
                $table->index('status');
            });
        }

        $this->backfill();
    }

    /**
     * Chép báo giá đang nằm trong `sites` sang bảng mới.
     *
     * Idempotent: bỏ qua công trình đã có bản version 1. Chỉ chép công trình
     * THỰC SỰ có dữ liệu báo giá — không tạo bản rỗng cho 19 công trình chưa
     * chào giá.
     */
    private function backfill(): void
    {
        foreach (array_keys(self::COLUMN_MAP) as $column) {
            if (! Schema::hasColumn('sites', $column)) {
                return; // Lược đồ không có cụm quote_* -> không có gì để chép.
            }
        }

        $existing = DB::table('site_quotes')->where('version', 1)->pluck('site_id')->all();
        $now = now();

        DB::table('sites')
            ->select(array_merge(['id'], array_keys(self::COLUMN_MAP)))
            ->orderBy('id')
            ->chunkById(200, function ($sites) use ($existing, $now): void {
                $rows = [];

                foreach ($sites as $site) {
                    if (in_array((int) $site->id, $existing, true) || ! $this->hasQuoteData($site)) {
                        continue;
                    }

                    $row = ['site_id' => $site->id, 'version' => 1, 'created_at' => $now, 'updated_at' => $now];

                    foreach (self::COLUMN_MAP as $from => $to) {
                        $row[$to] = $site->{$from};
                    }

                    $rows[] = $row;
                }

                if ($rows !== []) {
                    DB::table('site_quotes')->insert($rows);
                }
            });
    }

    /** Chỉ những trường NGƯỜI DÙNG nhập mới tính là "có báo giá" — cột tiền mặc định 0 thì không. */
    private function hasQuoteData(object $site): bool
    {
        foreach (['quote_no', 'quote_pdf_path', 'quote_scope', 'quote_config_summary', 'quote_customer_company'] as $column) {
            if (trim((string) ($site->{$column} ?? '')) !== '') {
                return true;
            }
        }

        return (float) ($site->quote_grand_total ?? 0) != 0.0
            || (float) ($site->quote_subtotal ?? 0) != 0.0;
    }

    /** Chỉ gỡ đúng thứ `up()` tạo ra. Cột `quote_*` trong `sites` vẫn nguyên vẹn. */
    public function down(): void
    {
        Schema::dropIfExists('site_quotes');
    }
};
