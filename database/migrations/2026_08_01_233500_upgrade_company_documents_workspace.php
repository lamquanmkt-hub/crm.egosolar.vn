<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_document_folders')) {
            Schema::table('company_document_folders', function (Blueprint $table): void {
                if (! Schema::hasColumn('company_document_folders', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('company_document_folders', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->index();
                }
                if (! Schema::hasColumn('company_document_folders', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (Schema::hasTable('company_document_files')) {
            Schema::table('company_document_files', function (Blueprint $table): void {
                if (! Schema::hasColumn('company_document_files', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('company_document_files', 'tags')) {
                    $table->json('tags')->nullable();
                }
                if (! Schema::hasColumn('company_document_files', 'approval_status')) {
                    $table->string('approval_status', 30)->default('not_required')->index();
                }
                if (! Schema::hasColumn('company_document_files', 'approval_note')) {
                    $table->text('approval_note')->nullable();
                }
                if (! Schema::hasColumn('company_document_files', 'version_no')) {
                    $table->unsignedInteger('version_no')->default(1);
                }
                if (! Schema::hasColumn('company_document_files', 'review_due_at')) {
                    $table->date('review_due_at')->nullable()->index();
                }
                if (! Schema::hasColumn('company_document_files', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->index();
                }
                if (! Schema::hasColumn('company_document_files', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (! Schema::hasColumn('company_document_files', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->index();
                }
                if (! Schema::hasColumn('company_document_files', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (! Schema::hasTable('company_document_versions')) {
            Schema::create('company_document_versions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('file_id')->index();
                $table->unsignedInteger('version_no');
                $table->string('original_name');
                $table->string('path');
                $table->string('mime')->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->text('change_note')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->timestamps();
                $table->unique(['file_id', 'version_no'], 'company_doc_versions_file_version_unique');
            });
        }

        if (! Schema::hasTable('company_document_activities')) {
            Schema::create('company_document_activities', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('file_id')->nullable()->index();
                $table->unsignedBigInteger('folder_id')->nullable()->index();
                $table->string('action', 60)->index();
                $table->text('description')->nullable();
                $table->json('meta')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        /*
         * Không tự động xóa cột/bảng khi rollback để bảo vệ metadata,
         * phiên bản và lịch sử đã phát sinh sau khi nâng cấp.
         */
    }
};
