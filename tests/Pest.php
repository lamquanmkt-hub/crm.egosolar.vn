<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;

/*
|--------------------------------------------------------------------------
| Cấu hình Pest
|--------------------------------------------------------------------------
|
| Chỉ áp cho các file test viết theo cú pháp Pest (closure). Toàn bộ test class
| PHPUnit sẵn có KHÔNG bị ảnh hưởng — chúng tự khai `extends Tests\TestCase` và
| `use DatabaseTransactions` rồi.
|
| ⚠️ Dùng DatabaseTransactions, KHÔNG dùng RefreshDatabase: DB test là bản sao
| schema production dựng từ database/schema/mysql-schema.sql, không dựng lại được
| đúng bằng migration. Ngoài ra TRUNCATE trong MariaDB là DDL gây commit ngầm,
| phá transaction và đã từng làm mất bảng `users` — xem docblock
| Tests\TestCase::truncateTables().
*/

uses(Tests\TestCase::class, DatabaseTransactions::class)->in('Feature');

// Browser test: plugin pest-plugin-browser phục vụ app NGAY TRONG tiến trình test
// (amphp SocketHttpServer + HttpKernel của Laravel), nên actingAs() và
// DatabaseTransactions dùng được y như Feature test — không có tiến trình server riêng.
uses(Tests\TestCase::class, DatabaseTransactions::class)->in('Browser');

// Unit test không boot app, không chạm DB.
uses(PHPUnit\Framework\TestCase::class)->in('Unit');

// Test kiến trúc gom vào group riêng để chạy nhanh: ./vendor/bin/pest --group=arch
uses()->group('arch')->in('Architecture');
