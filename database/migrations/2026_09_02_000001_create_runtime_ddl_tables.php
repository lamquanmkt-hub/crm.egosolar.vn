<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|------------------------------------------------------------------------------
| 33 bảng nghiệp vụ chưa bao giờ có migration
|------------------------------------------------------------------------------
|
| Chúng được tạo bằng DDL LÚC CHẠY trong controller (`Schema::create` /
| `DB::statement('CREATE TABLE ...')` giữa luồng request) — xem
| .claude/skills/laravel-clean-arch/README.md mục 1.2. Hệ quả: `php artisan migrate`
| trên DB rỗng chỉ dựng được 256/313 bảng, môi trường mới không chạy nổi.
|
| Migration này lấp khoảng trống bằng chính DDL của production, trích từ
| database/schema/mysql-schema.sql sang database/migrations/runtime_ddl_tables.sql.

|
| ⚠️ Migration này KHÔNG thay thế việc dọn DDL lúc chạy khỏi controller. Nó chỉ
| làm cho schema dựng lại được từ code. Bỏ được `Schema::create` trong controller
| rồi thì nên tách file .sql này thành từng migration có Blueprint đàng hoàng.
|
| An toàn với production: mọi lệnh đều `CREATE TABLE IF NOT EXISTS` về mặt hiệu
| lực nhờ kiểm `Schema::hasTable()` trước, nên chạy trên DB đã có đủ bảng là no-op.
*/
return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('migrations/runtime_ddl_tables.sql');

        if (! is_readable($path)) {
            return;
        }

        // Tắt kiểm khoá ngoại trong lúc tạo: các bảng tham chiếu lẫn nhau nên
        // không có thứ tự nào tạo tuần tự mà không vướng.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->statements($path) as $table => $sql) {
                if (Schema::hasTable($table)) {
                    continue;
                }

                DB::statement($sql);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // KHÔNG tự xoá: đây là bảng nghiệp vụ đang có dữ liệu thật trên production.
    }

    /**
     * Tách file .sql thành từng lệnh CREATE TABLE, khoá theo tên bảng.
     *
     * @return array<string, string>
     */
    private function statements(string $path): array
    {
        $sql = file_get_contents($path) ?: '';
        $out = [];

        foreach (explode(";\n", $sql) as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '' || ! str_contains($chunk, 'CREATE TABLE')) {
                continue;
            }

            $start = strpos($chunk, 'CREATE TABLE');
            $stmt = rtrim(substr($chunk, $start), ";\n ");

            if (preg_match('/^CREATE TABLE `([a-z_0-9]+)`/', $stmt, $m) === 1) {
                $out[$m[1]] = $stmt;
            }
        }

        return $out;
    }
};
