<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks') && ! Schema::hasColumn('tasks', 'proposal_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                if (! Schema::hasColumn('tasks', 'proposal_id')) {
                    $table->unsignedBigInteger('proposal_id')->nullable()->after('site_id')->index();
                }
            });
        }

        // Backfill an toàn nếu đã từng có task liên kết bằng link_url trước khi migration chạy.
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'proposal_id')) {
            DB::table('tasks')
                ->whereNull('proposal_id')
                ->whereNotNull('link_url')
                ->orderBy('id')
                ->chunkById(200, function ($tasks) {
                    foreach ($tasks as $task) {
                        $link = (string) ($task->link_url ?? '');
                        if (! preg_match('~/de-xuat/(\\d+)(?:[/?#]|$)~', $link, $match)) {
                            continue;
                        }

                        $proposalId = (int) $match[1];
                        if ($proposalId <= 0) {
                            continue;
                        }

                        DB::table('tasks')
                            ->where('id', $task->id)
                            ->whereNull('proposal_id')
                            ->update(['proposal_id' => $proposalId]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'proposal_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex(['proposal_id']);
                $table->dropColumn('proposal_id');
            });
        }
    }
};
