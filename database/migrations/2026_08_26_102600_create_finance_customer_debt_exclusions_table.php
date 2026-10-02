<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_customer_debt_exclusions')) {
            return;
        }

        Schema::create('finance_customer_debt_exclusions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('company_id')->default(0);
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'company_id'], 'finance_debt_exclusion_order_company_unique');
            $table->index('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_customer_debt_exclusions');
    }
};
