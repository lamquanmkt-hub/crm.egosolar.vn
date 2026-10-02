<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Canh giữ 5 trang biểu mẫu nhân sự: thêm/sửa phòng ban, thêm/sửa chức danh,
 * tạo đơn tăng ca.
 *
 * Không trang nào có luật CSS bám `.btn`; các lớp `rounded-pill`, `rounded-4`,
 * `px-4` là utility Bootstrap và mang `!important` nên vẫn thắng utility Tailwind.
 * Chỉ đổi thẻ, không bù gì — đã đo từng giá trị để chắc.
 *
 * Bốn nút "Lưu"/"Cập nhật" của phòng ban và chức danh KHÔNG khai `type` và dựa
 * vào mặc định submit của HTML. Component mặc định `button`, thiếu khai báo là
 * bốn biểu mẫu này im lặng ngừng lưu.
 */
final class HrFormButtonsTest extends TestCase
{
    use DatabaseTransactions;

    /** @return list<string> */
    private function buttonTags(string $view): array
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));
        preg_match_all('/<x-ui\.button\b(?:[^>"]|"[^"]*")*>/s', $source, $m);

        return $m[0];
    }

    /** @return array<string, array{0: string}> */
    public static function views(): array
    {
        return [
            'thêm phòng ban' => ['hr/departments/create.blade.php'],
            'sửa phòng ban' => ['hr/departments/edit.blade.php'],
            'thêm chức danh' => ['hr/positions/create.blade.php'],
            'sửa chức danh' => ['hr/positions/edit.blade.php'],
            'tạo đơn tăng ca' => ['hr/overtime/create.blade.php'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('views')]
    public function test_da_chuyen_va_giu_submit(string $view): void
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*(?<![\w-])btn(?![\w-])[^"]*(?<![\w-])btn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)(?![\w-])/', $source);

        $tags = $this->buttonTags($view);
        $this->assertCount(3, $tags);

        $submits = 0;
        foreach ($tags as $tag) {
            if (str_contains($tag, 'href=')) {
                continue;
            }
            $this->assertStringContainsString('type="submit"', $tag,
                'Nút gửi form thiếu type="submit": '.mb_substr((string) preg_replace('/\s+/', ' ', $tag), 0, 80));
            $submits++;
        }

        $this->assertSame(1, $submits);
    }

    /** Năm trang đều render được. */
    public function test_render_nam_trang(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        DB::table('departments')->insert([
            'id' => 991010, 'name' => 'Phong canh test', 'code' => 'PCT',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'id' => 991011, 'name' => 'Chuc danh canh test', 'code' => 'CDT',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            '/nhan-su/departments/create',
            '/nhan-su/departments/991010/edit',
            '/nhan-su/positions/create',
            '/nhan-su/positions/991011/edit',
            '/nhan-su/tang-ca/create',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
}
