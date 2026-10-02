<?php

declare(strict_types=1);

use App\Models\User;

/*
| Popup trang chi tiết nhân viên bật/tắt bằng class `show`, và class đó chỉ có tác dụng nhờ
| biến thể tuỳ ý `tw:[&.show]:flex` sinh ra lúc build Tailwind. Nếu utility đó không được sinh
| (đổi tên class, quên build, đổi cấu hình prefix) thì popup sẽ KHÔNG mở mà không có lỗi nào —
| đúng kiểu hỏng im lặng. Test này đo computed style thật để chặn.
*/

it('popup ho so bat len bang class show', function () {
    $admin = $this->userWithRole('admin', ['name' => 'QTV Modal', 'email' => 'modal-admin@example.test']);
    $employee = User::factory()->create(['name' => 'NV Modal', 'email' => 'modal-nv@example.test']);

    $this->actingAs($admin);
    $page = visit('/nhan-su/employees/'.$employee->id);
    $page->assertNoJavaScriptErrors();

    $display = fn (): string => (string) $page->script(
        "getComputedStyle(document.getElementById('profileModal')).getPropertyValue('display')"
    );

    expect($display())->toBe('none', 'popup phải đóng khi mới vào trang');

    $page->script("openEmpModal('profileModal')");
    expect($display())->toBe('flex', 'class show phải làm popup hiện — utility tw:[&.show]:flex chưa được sinh?');

    $page->script("hideEmpModal('profileModal')");
    expect($display())->toBe('none');
});
