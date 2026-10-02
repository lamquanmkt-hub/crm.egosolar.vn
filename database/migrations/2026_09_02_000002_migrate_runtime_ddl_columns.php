<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|------------------------------------------------------------------------------
| Đưa các cột do controller tự ALTER lúc chạy vào migration
|------------------------------------------------------------------------------
|
| Trước đây 5 method `ensure*()` trong controller vừa tạo bảng vừa THÊM CỘT giữa
| luồng request (`DB::statement('ALTER TABLE ... ADD COLUMN ...')`). Hệ quả: schema
| production có những cột mà không migration nào biết, nên môi trường dựng từ
| migration bị thiếu cột và test đỏ.
|
| Migration này chép lại đúng các cột đó, idempotent, để gỡ được DDL khỏi controller.
| Nguồn: SupplierDebtController, HrDocumentController, OfficeSupplyProcessController,
| MarketingReportController, ChatController, EmployeeController, RecruitmentController.
|
| An toàn với production: mọi cột đều bọc `Schema::hasColumn` nên là no-op ở đó.
*/
return new class extends Migration
{
    /** @var array<string, array<string, callable(Blueprint): void>> */
    private function plan(): array
    {
        return [
            'finance_supplier_debts' => [
                'bank_info' => fn (Blueprint $t) => $t->text('bank_info')->nullable(),
                'source_type' => fn (Blueprint $t) => $t->string('source_type', 50)->nullable(),
                'source_id' => fn (Blueprint $t) => $t->unsignedBigInteger('source_id')->nullable(),
                'source_code' => fn (Blueprint $t) => $t->string('source_code', 120)->nullable(),
                'supplier_scope' => fn (Blueprint $t) => $t->string('supplier_scope', 30)->default('general'),
                'due_date' => fn (Blueprint $t) => $t->date('due_date')->nullable(),
            ],
            'finance_supplier_debt_payments' => [
                'payment_request_id' => fn (Blueprint $t) => $t->unsignedBigInteger('payment_request_id')->nullable(),
                'source_type' => fn (Blueprint $t) => $t->string('source_type', 50)->nullable(),
                'source_id' => fn (Blueprint $t) => $t->unsignedBigInteger('source_id')->nullable(),
            ],
            'hr_operation_statuses' => [
                'color' => fn (Blueprint $t) => $t->string('color', 30)->default('slate'),
            ],
            'hr_vpp_requests' => [
                'receiver_name' => fn (Blueprint $t) => $t->string('receiver_name')->nullable(),
                'received_note' => fn (Blueprint $t) => $t->text('received_note')->nullable(),
                'received_by' => fn (Blueprint $t) => $t->unsignedBigInteger('received_by')->nullable(),
                'received_at' => fn (Blueprint $t) => $t->timestamp('received_at')->nullable(),
                'completed_note' => fn (Blueprint $t) => $t->text('completed_note')->nullable(),
                'completed_at' => fn (Blueprint $t) => $t->timestamp('completed_at')->nullable(),
            ],
            'hr_vpp_items' => [
                'product_id' => fn (Blueprint $t) => $t->unsignedBigInteger('product_id')->nullable()->index(),
            ],
            'mkt_actual_kpi_daily' => [
                'source_type' => fn (Blueprint $t) => $t->string('source_type', 100)->nullable(),
                'raw_payload' => fn (Blueprint $t) => $t->longText('raw_payload')->nullable(),
            ],
            'conversations' => [
                'name' => fn (Blueprint $t) => $t->string('name')->nullable(),
                'is_internal' => fn (Blueprint $t) => $t->boolean('is_internal')->default(true),
                'department_id' => fn (Blueprint $t) => $t->unsignedBigInteger('department_id')->nullable(),
                'task_id' => fn (Blueprint $t) => $t->unsignedBigInteger('task_id')->nullable(),
            ],
            'messages' => [
                'attachment_path' => fn (Blueprint $t) => $t->string('attachment_path', 500)->nullable(),
                'attachment_name' => fn (Blueprint $t) => $t->string('attachment_name')->nullable(),
                'attachment_mime' => fn (Blueprint $t) => $t->string('attachment_mime', 150)->nullable(),
                'attachment_size' => fn (Blueprint $t) => $t->unsignedBigInteger('attachment_size')->nullable(),
            ],
            'conversation_user' => [
                'last_read_at' => fn (Blueprint $t) => $t->timestamp('last_read_at')->nullable(),
            ],

            // EmployeeController::ensureEmployeeSalaryColumns()
            'users' => [
                'official_salary' => fn (Blueprint $t) => $t->decimal('official_salary', 15, 2)->nullable(),
                'probation_salary' => fn (Blueprint $t) => $t->decimal('probation_salary', 15, 2)->nullable(),
                'internship_salary' => fn (Blueprint $t) => $t->decimal('internship_salary', 15, 2)->nullable(),
            ],

            // RecruitmentController::ensureTablesReady()
            'hr_recruitment_candidates' => [
                'cv_link' => fn (Blueprint $t) => $t->string('cv_link', 1000)->nullable(),
                'zalo' => fn (Blueprint $t) => $t->string('zalo')->nullable(),
                'contact_channel' => fn (Blueprint $t) => $t->string('contact_channel')->nullable(),
                'contacted_at' => fn (Blueprint $t) => $t->dateTime('contacted_at')->nullable(),
                'suitability' => fn (Blueprint $t) => $t->string('suitability')->nullable(),
                'reject_reason' => fn (Blueprint $t) => $t->text('reject_reason')->nullable(),
                'archive_note' => fn (Blueprint $t) => $t->text('archive_note')->nullable(),
                'archive_until' => fn (Blueprint $t) => $t->date('archive_until')->nullable(),
            ],
            'hr_recruitment_interviews' => [
                'status' => fn (Blueprint $t) => $t->string('status')->default('scheduled'),
                'cancel_reason' => fn (Blueprint $t) => $t->text('cancel_reason')->nullable(),
                'evaluation' => fn (Blueprint $t) => $t->text('evaluation')->nullable(),
                'evaluation_result' => fn (Blueprint $t) => $t->string('evaluation_result')->nullable(),
                'expected_start_date' => fn (Blueprint $t) => $t->date('expected_start_date')->nullable(),
            ],
            'hr_recruitment_offers' => [
                'response_note' => fn (Blueprint $t) => $t->text('response_note')->nullable(),
                'onboarding_status' => fn (Blueprint $t) => $t->string('onboarding_status')->nullable(),
                'onboarding_date' => fn (Blueprint $t) => $t->date('onboarding_date')->nullable(),
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->plan() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $missing = array_filter(
                $columns,
                static fn (callable $add, string $column): bool => ! Schema::hasColumn($table, $column),
                ARRAY_FILTER_USE_BOTH,
            );

            if ($missing === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($missing) {
                foreach ($missing as $add) {
                    $add($t);
                }
            });
        }
    }

    public function down(): void
    {
        // KHÔNG tự xoá: đây là cột đang mang dữ liệu thật trên production.
    }
};
