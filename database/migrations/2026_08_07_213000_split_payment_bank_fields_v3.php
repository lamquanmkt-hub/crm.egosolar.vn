<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_requests')) {
            return;
        }

        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('bank_info');
            }
            if (! Schema::hasColumn('payment_requests', 'bank_account')) {
                $table->string('bank_account', 100)->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('payment_requests', 'bank_account_name')) {
                $table->string('bank_account_name')->nullable()->after('bank_account');
            }
        });

        // Backfill dữ liệu cũ dạng: Ngân hàng: ... | Số tài khoản: ... | Chủ tài khoản: ...
        DB::table('payment_requests')
            ->whereNotNull('bank_info')
            ->where('bank_info', '<>', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $text = trim((string) ($row->bank_info ?? ''));
                    if ($text === '') {
                        continue;
                    }

                    $bankName = null;
                    $bankAccount = null;
                    $bankAccountName = null;

                    if (preg_match('/Ngân hàng\s*:\s*([^|]+)/iu', $text, $m)) {
                        $bankName = trim($m[1]);
                    }
                    if (preg_match('/Số tài khoản\s*:\s*([^|]+)/iu', $text, $m)) {
                        $bankAccount = trim($m[1]);
                    }
                    if (preg_match('/Chủ tài khoản\s*:\s*([^|]+)/iu', $text, $m)) {
                        $bankAccountName = trim($m[1]);
                    }

                    // Fallback dữ liệu cũ gộp bằng dấu - hoặc |
                    if (! $bankName && ! $bankAccount && ! $bankAccountName) {
                        $parts = preg_split('/\s*[|·]\s*|\s+-\s+/', $text);
                        $parts = array_values(array_filter(array_map('trim', $parts), fn ($v) => $v !== ''));
                        if (count($parts) >= 3) {
                            $bankName = $parts[0];
                            $bankAccount = $parts[1];
                            $bankAccountName = implode(' - ', array_slice($parts, 2));
                        }
                    }

                    $update = [];
                    if ($bankName) {
                        $update['bank_name'] = $bankName;
                    }
                    if ($bankAccount) {
                        $update['bank_account'] = $bankAccount;
                    }
                    if ($bankAccountName) {
                        $update['bank_account_name'] = $bankAccountName;
                    }
                    if ($update) {
                        DB::table('payment_requests')->where('id', $row->id)->update($update);
                    }
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_requests')) {
            return;
        }
        Schema::table('payment_requests', function (Blueprint $table) {
            foreach (['bank_account_name', 'bank_account', 'bank_name'] as $column) {
                if (Schema::hasColumn('payment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
