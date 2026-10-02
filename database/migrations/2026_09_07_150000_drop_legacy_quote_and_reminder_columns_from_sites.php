<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đợt CONTRACT của P1l: xoá 21 cột `quote_*` và 3 cột `warranty_reminder_N_at` khỏi `sites`.
 *
 * ## Điều kiện đã đạt trước khi viết (REFACTOR_ROADMAP.md, P1l)
 * - EXPAND 2026-09-04: tạo `site_quotes` / `site_warranty_reminders` + backfill.
 * - Bước 1 (deploy 817c771, 2026-09-07 13:43): ghi song song + đọc ưu tiên bảng mới.
 * - Bước 2 ngay sau deploy: `sites:check-normalisation` trên production KHỚP —
 *   19 công trình, 0 có dữ liệu báo giá (cả 19 chỉ mang `quote_status='draft'` mặc định),
 *   8 mốc nhắc = 8 dòng.
 *
 * ## Không mất dữ liệu
 * `up()` CHÉP LẦN CUỐI từ cột cũ sang bảng mới trước khi xoá, cùng luật với bước ghi song
 * song (cột cũ là nguồn sự thật: bản version cao nhất nhận giá trị, chưa có bản mà có dữ
 * liệu thì tạo v1; mốc nhắc tạo/sửa/xoá theo cột cũ) — nên kể cả nơi nào đó chép sót trong
 * lúc chạy song song cũng không mất. `down()` dựng lại 24 cột đúng kiểu cũ và chép ngược từ
 * bảng mới (bản version cao nhất; mốc 1–3 — mốc thứ 4 trở đi không có chỗ chứa ở lược đồ cũ).
 *
 * ## Vì sao DDL viết tay MỘT câu `ALTER … ALGORITHM=COPY` thay vì Blueprint
 * `sites` có ~13 KB dung lượng varchar (83 cột) — vượt trần 8126 byte/dòng của InnoDB. Production
 * (MariaDB 10.11, `innodb_strict_mode=0`) chỉ cảnh báo; máy dev/CI strict=1 thì đường INSTANT /
 * INPLACE (Laravel 11+ thêm/xoá MỖI cột một câu ALTER) bị từ chối "Row size too large" giữa chừng,
 * để lại bảng nửa vời (đã gặp thật 2026-09-07: còn đúng 5/24 cột). Một câu COPY (chép lại bảng —
 * 19 dòng, không đáng kể) là nguyên tử và luôn qua kiểm tra đó; INPLACE thì lúc qua lúc không tuỳ
 * lịch sử instant-alter của bảng. Xem DB_NORMALIZATION_AUDIT.md — "trần kích thước dòng".
 */
return new class extends Migration
{
    /** cột `quote_*` trong `sites` => cột trong `site_quotes` */
    private const QUOTE_COLUMN_MAP = [
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

    /** cột `warranty_reminder_N_at` => sequence trong `site_warranty_reminders` */
    private const REMINDER_COLUMN_MAP = [
        'warranty_reminder_1_at' => 1,
        'warranty_reminder_2_at' => 2,
        'warranty_reminder_3_at' => 3,
    ];

    /** Cột tiền NOT NULL DEFAULT 0 ở cả hai bên. */
    private const MONEY_COLUMNS = ['subtotal', 'discount_amount', 'vat_percent', 'vat_amount', 'grand_total'];

    /** Định nghĩa 24 cột cũ đúng như production trước CONTRACT — để `down()` dựng lại y nguyên. */
    private const LEGACY_COLUMN_DEFINITIONS = [
        'quote_no' => 'varchar(50) NULL',
        'quote_status' => "varchar(50) NULL DEFAULT 'draft'",
        'quote_valid_until' => 'date NULL',
        'quote_date' => 'date NULL',
        'quote_customer_tax_code' => 'varchar(50) NULL',
        'quote_customer_email' => 'varchar(255) NULL',
        'quote_customer_company' => 'varchar(255) NULL',
        'quote_application_note' => 'text NULL',
        'quote_config_summary' => 'text NULL',
        'warranty_reminder_1_at' => 'date NULL',
        'warranty_reminder_2_at' => 'date NULL',
        'warranty_reminder_3_at' => 'date NULL',
        'quote_approved_at' => 'timestamp NULL',
        'quote_sent_at' => 'timestamp NULL',
        'quote_grand_total' => 'decimal(15,2) NOT NULL DEFAULT 0',
        'quote_vat_amount' => 'decimal(15,2) NOT NULL DEFAULT 0',
        'quote_vat_percent' => 'decimal(6,2) NOT NULL DEFAULT 0',
        'quote_discount_amount' => 'decimal(15,2) NOT NULL DEFAULT 0',
        'quote_subtotal' => 'decimal(15,2) NOT NULL DEFAULT 0',
        'quote_pdf_path' => 'varchar(255) NULL',
        'quote_om_terms' => 'longtext NULL',
        'quote_warranty_terms' => 'longtext NULL',
        'quote_commercial_terms' => 'longtext NULL',
        'quote_scope' => 'longtext NULL',
    ];

    public function up(): void
    {
        $legacyColumns = array_values(array_filter(
            array_merge(array_keys(self::QUOTE_COLUMN_MAP), array_keys(self::REMINDER_COLUMN_MAP)),
            static fn (string $column): bool => Schema::hasColumn('sites', $column),
        ));

        if ($legacyColumns === []) {
            return; // Đã CONTRACT rồi (chạy lại) hoặc lược đồ chưa từng có cụm này.
        }

        $this->copyLegacyColumnsToNewTables($legacyColumns);

        DB::statement(sprintf(
            'ALTER TABLE `sites` %s, ALGORITHM=COPY',
            implode(', ', array_map(static fn (string $column): string => "DROP COLUMN `{$column}`", $legacyColumns)),
        ));
    }

    public function down(): void
    {
        $missing = array_filter(
            self::LEGACY_COLUMN_DEFINITIONS,
            static fn (string $column): bool => ! Schema::hasColumn('sites', $column),
            ARRAY_FILTER_USE_KEY,
        );

        if ($missing !== []) {
            DB::statement(sprintf(
                'ALTER TABLE `sites` %s, ALGORITHM=COPY',
                implode(', ', array_map(
                    static fn (string $column, string $definition): string => "ADD COLUMN `{$column}` {$definition}",
                    array_keys($missing),
                    $missing,
                )),
            ));
        }

        $this->copyNewTablesToLegacyColumns();
    }

    /**
     * Cột cũ là nguồn sự thật cho tới đúng thời điểm này — chép đè lên bản version cao nhất.
     *
     * Chỉ đụng tới những cột CÒN TỒN TẠI: nếu lần chạy trước gãy giữa chừng (ALTER thêm/xoá
     * từng cột) thì cột đã mất không được ghi null đè lên bảng mới.
     *
     * @param  list<string>  $presentColumns
     */
    private function copyLegacyColumnsToNewTables(array $presentColumns): void
    {
        $now = now();
        $quoteColumns = array_intersect_key(self::QUOTE_COLUMN_MAP, array_flip($presentColumns));
        $reminderColumns = array_intersect_key(self::REMINDER_COLUMN_MAP, array_flip($presentColumns));

        DB::table('sites')
            ->select(array_merge(['id'], $presentColumns))
            ->orderBy('id')
            ->chunkById(200, function ($sites) use ($now, $quoteColumns, $reminderColumns): void {
                foreach ($sites as $site) {
                    $values = [];
                    foreach ($quoteColumns as $legacy => $column) {
                        $value = $site->{$legacy} ?? null;
                        $values[$column] = in_array($column, self::MONEY_COLUMNS, true) ? ($value ?? 0) : $value;
                    }

                    $latest = DB::table('site_quotes')->where('site_id', $site->id)->orderByDesc('version')->first(['id']);
                    if ($latest !== null && $values !== []) {
                        DB::table('site_quotes')->where('id', $latest->id)->update(array_merge($values, ['updated_at' => $now]));
                    } elseif ($latest === null && $this->hasQuoteData($site)) {
                        DB::table('site_quotes')->insert(array_merge($values, [
                            'site_id' => $site->id, 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
                        ]));
                    }

                    foreach ($reminderColumns as $legacy => $sequence) {
                        $value = $site->{$legacy} ?? null;
                        $reminder = DB::table('site_warranty_reminders')->where('site_id', $site->id)->where('sequence', $sequence);

                        if ($value === null || $value === '') {
                            $reminder->delete();
                        } elseif ($reminder->exists()) {
                            $reminder->update(['remind_at' => $value, 'updated_at' => $now]);
                        } else {
                            DB::table('site_warranty_reminders')->insert([
                                'site_id' => $site->id, 'sequence' => $sequence, 'remind_at' => $value,
                                'created_at' => $now, 'updated_at' => $now,
                            ]);
                        }
                    }
                }
            });
    }

    /** Chiều ngược cho `down()`: bản version cao nhất → `quote_*`, mốc 1–3 → `warranty_reminder_N_at`. */
    private function copyNewTablesToLegacyColumns(): void
    {
        $latestVersions = DB::table('site_quotes')
            ->selectRaw('site_id, MAX(version) as version')
            ->groupBy('site_id')
            ->get();

        foreach ($latestVersions as $row) {
            $quote = DB::table('site_quotes')->where('site_id', $row->site_id)->where('version', $row->version)->first();
            if ($quote === null) {
                continue;
            }

            $values = [];
            foreach (self::QUOTE_COLUMN_MAP as $legacy => $column) {
                $values[$legacy] = $quote->{$column} ?? (in_array($column, self::MONEY_COLUMNS, true) ? 0 : null);
            }
            DB::table('sites')->where('id', $row->site_id)->update($values);
        }

        $columnBySequence = array_flip(self::REMINDER_COLUMN_MAP);
        foreach (DB::table('site_warranty_reminders')->orderBy('id')->get() as $reminder) {
            $column = $columnBySequence[(int) $reminder->sequence] ?? null;
            if ($column !== null) {
                DB::table('sites')->where('id', $reminder->site_id)->update([$column => $reminder->remind_at]);
            }
        }
    }

    /** Cùng luật với backfill EXPAND và ghi song song: chỉ trường người dùng nhập mới tính là có báo giá. */
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
};
