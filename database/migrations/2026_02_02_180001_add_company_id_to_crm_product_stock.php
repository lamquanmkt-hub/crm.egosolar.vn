<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Add columns
        if (Schema::hasTable('crm_product_stock')) {
            Schema::table('crm_product_stock', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_product_stock', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->after('warehouse_id')->default(1)->index();
                }

                if (! Schema::hasColumn('crm_product_stock', 'last_updated')) {
                    $table->timestamp('last_updated')->nullable()->after('qty');
                }
            });
        }

        // 2) Backfill
        DB::table('crm_product_stock')->whereNull('company_id')->update(['company_id' => 1]);

        /*
         * THỨ TỰ QUAN TRỌNG: tạo unique MỚI trước, xoá unique CŨ sau.
         *
         * `crm_product_stock_product_id_warehouse_id_unique` là index duy nhất
         * phủ `product_id`, mà khoá ngoại `crm_product_stock_product_id_foreign`
         * đang dựa vào nó. Xoá trước khi có index thay thế thì MariaDB từ chối:
         * "Cannot drop index ... needed in a foreign key constraint" (lỗi 1553).
         * Bản cũ xoá trước nên chạy lại từ DB rỗng là chết ở đây.
         *
         * Unique mới có `product_id` ở vị trí trái nhất nên khoá ngoại dùng được
         * ngay sau khi tạo.
         */
        $newIndexName = 'crm_ps_product_company_warehouse_unique';

        if (! Schema::hasIndex('crm_product_stock', $newIndexName)) {
            if (Schema::hasTable('crm_product_stock')) {
                Schema::table('crm_product_stock', function (Blueprint $table) use ($newIndexName) {
                    $table->unique(['product_id', 'company_id', 'warehouse_id'], $newIndexName);
                });
            }
        }

        $oldIndexName = 'crm_product_stock_product_id_warehouse_id_unique';

        if (Schema::hasIndex('crm_product_stock', $oldIndexName)) {
            if (Schema::hasTable('crm_product_stock')) {
                Schema::table('crm_product_stock', function (Blueprint $table) use ($oldIndexName) {
                    $table->dropUnique($oldIndexName);
                });
            }
        }
    }

    public function down(): void
    {
        $newIndexName = 'crm_ps_product_company_warehouse_unique';
        $hasNew = ! empty(DB::select(
            'SHOW INDEX FROM `crm_product_stock` WHERE Key_name = ?',
            [$newIndexName]
        ));

        if ($hasNew) {
            if (Schema::hasTable('crm_product_stock')) {
                Schema::table('crm_product_stock', function (Blueprint $table) use ($newIndexName) {
                    $table->dropUnique($newIndexName);
                });
            }
        }

        if (Schema::hasTable('crm_product_stock')) {
            Schema::table('crm_product_stock', function (Blueprint $table) {
                if (Schema::hasColumn('crm_product_stock', 'company_id')) {
                    $table->dropColumn('company_id');
                }

                // tuỳ bạn có muốn drop last_updated không
                // if (Schema::hasColumn('crm_product_stock', 'last_updated')) {
                //     $table->dropColumn('last_updated');
                // }
            });
        }

        // (Optional) restore unique cũ nếu bạn muốn:
        // $oldIndexName = 'crm_product_stock_product_id_warehouse_id_unique';
        // $hasOld = !empty(DB::select("SHOW INDEX FROM `crm_product_stock` WHERE Key_name = ?", [$oldIndexName]));
        // if (!$hasOld) {
        //     Schema::table('crm_product_stock', function (Blueprint $table) use ($oldIndexName) {
        //         $table->unique(['product_id','warehouse_id'], $oldIndexName);
        //     });
        // }
    }
};
