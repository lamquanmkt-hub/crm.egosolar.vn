<?php

declare(strict_types=1);

/*
| Truy cập bàn phím + trình đọc màn hình cho `finance/supplier-debts`.
|
| Hai lỗi có THẬT, đo được trước khi sửa:
|  1. **28 ô nhập không có tên** — không `<label for>`, không bọc trong `<label>`, không
|     `aria-label`, không `title`. Trình đọc màn hình chỉ đọc "edit text". Tám ô trong số đó
|     còn không có cả `placeholder` nên không có gợi ý nào.
|  2. **Không thấy tiêu điểm bàn phím.** Lớp nền đặt `outline:none` mà không có kiểu `:focus`
|     thay thế → focus vào ô nhập KHÔNG đổi gì: outline none, không bóng, viền y nguyên.
|     WCAG 2.4.7 (Focus Visible) mức AA.
|
| ⚠️ `tw:outline-2` chỉ đặt ĐỘ RỘNG. Lớp nền đặt `outline-style:none` nên vòng vẫn không hiện —
| phải khai thêm `[outline-style:solid]`. Đã đo thấy đúng lỗi đó ở lượt sửa đầu.
*/

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $now = '2026-09-01 08:30:00';
    DB::table('finance_supplier_debts')->insert([
        'supplier_name' => 'NCC A11y', 'document_no' => 'HD-A11Y', 'document_date' => '2026-09-01',
        'debt_month' => '2026-09-01', 'due_date' => '2026-10-01',
        'total_amount' => 100_000_000, 'paid_amount' => 30_000_000, 'status' => 'partial',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->actingAs($this->userWithRole('admin', [
        'name' => 'QTV A11y', 'email' => 'sd-a11y@example.test',
    ]));
});

it('moi o nhap deu co ten cho trinh doc man hinh', function () {
    $page = visit('/finance/supplier-debts');
    $page->assertNoJavaScriptErrors();

    $khongTen = (string) $page->script(<<<'JS'
        (function () {
            var ra = [];
            document.querySelectorAll('main input:not([type=hidden]):not([type=submit]), main select, main textarea')
                .forEach(function (e) {
                    var ten = (e.id && document.querySelector('label[for="' + e.id + '"]'))
                        || e.closest('label') || e.getAttribute('aria-label')
                        || e.getAttribute('aria-labelledby') || e.getAttribute('title');
                    if (!ten) ra.push(e.tagName.toLowerCase() + '[' + (e.name || '?') + ']');
                });
            return ra.join(', ');
        })()
    JS);

    expect($khongTen)->toBe('', 'ô nhập không có tên: '.$khongTen);
});

it('o nhap co vien tieu diem nhin thay duoc', function () {
    $page = visit('/finance/supplier-debts');

    // `:focus-visible` LUÔN khớp với ô nhập văn bản, kể cả khi focus bằng script — nên đo được.
    // Với <button> thì spec chỉ cho khớp sau tương tác BÀN PHÍM, không đo bằng script được;
    // nút mang cùng bộ lớp nên không kiểm riêng ở đây.
    // Chỉ đo ô ĐANG HIỆN: `e.focus()` không di chuyển tiêu điểm vào phần tử nằm trong khối
    // `display:none` (hàng chi tiết đóng), nên `:focus-visible` không khớp và phép đo vô nghĩa.
    // Các ô ẩn được kiểm ở test nguồn bên dưới.
    foreach (['input[name=keyword]', 'select[name=period]', 'select[name=status]'] as $sel) {
        $ra = (string) $page->script(
            '(function(){var e=document.querySelector('.json_encode($sel).');'
            .'if(!e) return "KHÔNG THẤY";e.focus();var c=getComputedStyle(e);'
            .'return c.outlineStyle+"|"+c.outlineWidth+"|"+c.outlineColor})()'
        );

        expect($ra)->toBe('solid|2px|rgb(37, 99, 235)', "{$sel} không có vòng tiêu điểm");
    }
});

it('select bat buoc co lua chon rong lam cho giu', function () {
    $page = visit('/finance/supplier-debts');

    // `<select required>` mà option đầu đã có giá trị thì `required` vô nghĩa — và người dùng
    // dễ gửi nhầm công ty mặc định. Option rỗng đứng đầu buộc phải chọn có ý thức.
    $ra = (string) $page->script(
        '(function(){var s=document.querySelector("select[name=company_name]");'
        .'return s ? (s.options[0].value === "" ? "rong:" + s.value : "KHONG-RONG") : "khong-thay"})()'
    );

    expect($ra)->toBe('rong:', 'select công ty phải mặc định chưa chọn gì');
});

it('moi o nhap deu mang lop vien tieu diem trong nguon', function () {
    // Kiểm ở NGUỒN vì ô nằm trong hàng chi tiết đang đóng không focus được để đo lúc chạy.
    //
    // ⚠️ KHÔNG dùng regex `<input[^>]*>`: cú pháp Blade `{{ $round->id }}` có dấu `>` nên regex
    // cắt thẻ giữa chừng và báo thiếu lớp một cách sai. Phải tự quét, bỏ qua vùng `{{ … }}`.
    $ketThucThe = function (string $s, int $bd): int {
        $n = strlen($s);
        $nhay = null;

        for ($i = $bd; $i < $n; $i++) {
            $c = $s[$i];

            if ($nhay !== null) {
                if ($c === $nhay) {
                    $nhay = null;
                }

                continue;
            }

            if ($c === '"' || $c === "'") {
                $nhay = $c;

                continue;
            }

            if (substr($s, $i, 2) === '{{') {
                $k = strpos($s, '}}', $i);
                $i = $k === false ? $i + 1 : $k + 1;

                continue;
            }

            if ($c === '>') {
                return $i;
            }
        }

        return $n - 1;
    };

    $thieu = [];

    foreach (['finance/supplier-debts/index', 'finance/supplier-debts/_debt_files_manager'] as $view) {
        $nguon = (string) file_get_contents(resource_path("views/{$view}.blade.php"));
        $viTri = strpos($nguon, '<script');
        $markup = $viTri === false ? $nguon : substr($nguon, 0, $viTri);

        foreach (['input', 'select', 'textarea', 'button'] as $ten) {
            $vt = 0;

            while (($bd = strpos($markup, '<'.$ten, $vt)) !== false) {
                $sau = $markup[$bd + 1 + strlen($ten)] ?? '';

                if (ctype_alnum($sau) || $sau === '-') {
                    $vt = $bd + 1;

                    continue;
                }

                $kt = $ketThucThe($markup, $bd);
                $the = substr($markup, $bd, $kt - $bd + 1);
                $vt = $kt + 1;

                if (str_contains($the, 'type="hidden"')) {
                    continue;
                }

                if (! str_contains($the, 'focus-visible:outline')) {
                    $thieu[] = $view.': '.substr(trim(preg_replace('/\s+/', ' ', $the)), 0, 70);
                }
            }
        }
    }

    expect($thieu)->toBe([], implode("\n", $thieu));
});
