<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                if (! Schema::hasColumn('tasks', 'progress_percent')) {
                    $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
                }
                if (! Schema::hasColumn('tasks', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('completed_at');
                }
                if (! Schema::hasColumn('tasks', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('submitted_at');
                }
                if (! Schema::hasColumn('tasks', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
                }
                if (! Schema::hasColumn('tasks', 'manager_feedback')) {
                    $table->text('manager_feedback')->nullable()->after('approved_by');
                }
                if (! Schema::hasColumn('tasks', 'revision_reason')) {
                    $table->text('revision_reason')->nullable()->after('manager_feedback');
                }
                if (! Schema::hasColumn('tasks', 'revision_requested_by')) {
                    $table->unsignedBigInteger('revision_requested_by')->nullable()->after('revision_reason');
                }
                if (! Schema::hasColumn('tasks', 'revision_requested_at')) {
                    $table->timestamp('revision_requested_at')->nullable()->after('revision_requested_by');
                }
                if (! Schema::hasColumn('tasks', 'resubmitted_at')) {
                    $table->timestamp('resubmitted_at')->nullable()->after('revision_requested_at');
                }
            });
        }

        if (! Schema::hasTable('task_work_reports')) {
            Schema::create('task_work_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->date('report_date')->nullable()->index();
                $table->unsignedTinyInteger('progress_percent')->default(0);
                $table->text('result_note')->nullable();
                $table->text('employee_note')->nullable();
                $table->string('status', 40)->default('submitted')->index();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('manager_feedback')->nullable();
                $table->text('revision_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tasks')) {
            return;
        }

        $taskColumns = Schema::getColumnListing('tasks');

        if (in_array('submitted_at', $taskColumns, true)) {
            DB::table('tasks')
                ->whereIn('status', ['submitted', 'revision', 'rejected', 'approved'])
                ->whereNull('submitted_at')
                ->orderBy('id')
                ->chunkById(200, function ($tasks) {
                    foreach ($tasks as $task) {
                        DB::table('tasks')->where('id', $task->id)->update([
                            'submitted_at' => $task->completed_at ?? $task->updated_at ?? $task->created_at ?? now(),
                        ]);
                    }
                });
        }

        if (in_array('approved_at', $taskColumns, true)) {
            DB::table('tasks')
                ->where('status', 'approved')
                ->whereNull('approved_at')
                ->orderBy('id')
                ->chunkById(200, function ($tasks) {
                    foreach ($tasks as $task) {
                        DB::table('tasks')->where('id', $task->id)->update([
                            'approved_at' => $task->completed_at ?? $task->updated_at ?? $task->created_at ?? now(),
                        ]);
                    }
                });
        }

        if (Schema::hasTable('task_work_reports')) {
            DB::table('tasks')
                ->where(function ($query) {
                    $query->whereNotNull('result_note')
                        ->orWhereIn('status', ['submitted', 'revision', 'rejected', 'approved']);
                })
                ->orderBy('id')
                ->chunkById(200, function ($tasks) {
                    foreach ($tasks as $task) {
                        $exists = DB::table('task_work_reports')
                            ->where('task_id', $task->id)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $submittedAt = $task->submitted_at
                            ?? $task->completed_at
                            ?? $task->updated_at
                            ?? $task->created_at
                            ?? now();

                        DB::table('task_work_reports')->insert([
                            'task_id' => $task->id,
                            'user_id' => $task->assignee_id,
                            'report_date' => date('Y-m-d', strtotime((string) $submittedAt)),
                            'progress_percent' => (int) ($task->progress_percent ?? 0),
                            'result_note' => $task->result_note,
                            'employee_note' => null,
                            'status' => (string) ($task->status ?? 'submitted'),
                            'submitted_at' => $submittedAt,
                            'approved_at' => $task->approved_at ?? (($task->status ?? null) === 'approved' ? $task->completed_at : null),
                            'approved_by' => $task->approved_by ?? null,
                            'manager_feedback' => $task->manager_feedback ?? null,
                            'revision_reason' => $task->revision_reason ?? null,
                            'created_at' => $submittedAt,
                            'updated_at' => $task->updated_at ?? $submittedAt,
                        ]);
                    }
                });
        }

        if (in_array('completed_at', $taskColumns, true)) {
            DB::table('tasks')
                ->whereIn('status', ['submitted', 'revision', 'rejected'])
                ->update(['completed_at' => null]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_work_reports');

        if (! Schema::hasTable('tasks')) {
            return;
        }

        $columns = [
            'submitted_at',
            'approved_at',
            'approved_by',
            'manager_feedback',
            'revision_reason',
            'revision_requested_by',
            'revision_requested_at',
            'resubmitted_at',
        ];

        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn('tasks', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
