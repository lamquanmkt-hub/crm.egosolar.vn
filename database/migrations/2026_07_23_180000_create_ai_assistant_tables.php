<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->string('title', 160)->nullable();
                $table->string('model', 100)->nullable();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamps();

                $table->index(['user_id', 'company_id', 'last_message_at'], 'ai_conv_user_company_last_idx');
            });
        }

        if (! Schema::hasTable('ai_messages')) {
            Schema::create('ai_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
                $table->string('role', 20);
                $table->longText('content');
                $table->json('meta')->nullable();
                $table->unsignedInteger('input_tokens')->nullable();
                $table->unsignedInteger('output_tokens')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'id'], 'ai_msg_conversation_id_idx');
            });
        }

        if (! Schema::hasTable('ai_tool_logs')) {
            Schema::create('ai_tool_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->string('tool_name', 120);
                $table->json('arguments')->nullable();
                $table->unsignedInteger('result_count')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('status', 30)->default('success');
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at'], 'ai_tool_user_created_idx');
                $table->index(['tool_name', 'created_at'], 'ai_tool_name_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_logs');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
