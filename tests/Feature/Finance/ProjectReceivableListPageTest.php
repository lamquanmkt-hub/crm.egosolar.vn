<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\ProjectReceivableKpi;
use App\DTOs\Finance\ProjectReceivableRow;
use App\View\Presenters\Finance\ProjectReceivableListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard trang `finance/project-receivables/index` sau đợt 2026-10-01.
 */
final class ProjectReceivableListPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/finance/project-receivables/index.blade.php';

    private const CONTROLLER_KEYS = [
        'projects', 'summary', 'keyword', 'status', 'fromDate', 'toDate', 'companyId', 'companies',
    ];

    private const LOOP_AND_BLADE_VARIABLES = ['row', 'company', 'loop', 'errors', 'slot', 'attributes', 'component'];

    public function test_view_chi_in_khong_tu_tinh(): void
    {
        // Bỏ chú thích Blade trước khi kiểm — chú thích không ra HTML nhưng hay nhắc lại đúng tên
        // thứ đang bị cấm và làm guard đỏ oan.
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        $this->assertStringNotContainsString('@php', $view, 'view còn khối @php');
        $this->assertStringNotContainsString('number_format', $view, 'view còn tự định dạng số');
        $this->assertStringNotContainsString('Carbon', $view, 'view còn tự parse ngày');
        $this->assertStringNotContainsString('<style', $view, 'view dùng lại khối style nội tuyến');
        $this->assertStringNotContainsString('class="pr-', $view, 'view dùng lại hệ class pr-* cũ');
        $this->assertStringNotContainsString('<script', $view);
        $this->assertKhongLamDungImportant($view, 'finance/project-receivables');

        // Ba ngưỡng responsive của khối @media cũ (1100px và 700px) phải viết N+1: Tailwind sinh
        // `not all and (min-width:N)` = `< N`, còn CSS gốc là `<= N`.
        $this->assertStringContainsString('tw:max-[1101px]', $view);
        $this->assertStringContainsString('tw:max-[701px]', $view);
        $this->assertStringNotContainsString('tw:max-[1100px]', $view);
        $this->assertStringNotContainsString('tw:max-[700px]', $view);

        $data = (new ProjectReceivableListPresenter)->viewData([], []);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $view, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys($data));

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');
    }

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        foreach (['row' => ProjectReceivableRow::class, 'kpi' => ProjectReceivableKpi::class] as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z0-9]+)/', $view, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien ({$dto})");
        }
    }

    public function test_trang_that_in_dung_gia_tri(): void
    {
        Carbon::setTestNow('2026-10-01 09:15:00');

        $admin = $this->userWithRole('admin', ['id' => 770001, 'name' => 'QT Guard']);

        DB::table('sites')->insert([
            ['id' => 770010, 'project_code' => 'CT-GUARD', 'name' => 'Công trình Guard',
                'address' => '12 Nguyễn Trãi', 'contact_phone' => '0909000111', 'contact_name' => 'Ông A',
                'progress_percent' => 45, 'project_phase' => 'Thi công', 'status' => 'in_progress',
                'created_at' => '2026-09-01 08:00:00', 'updated_at' => '2026-09-01 08:00:00'],
            ['id' => 770011, 'project_code' => null, 'name' => 'Công trình thiếu dữ liệu',
                'address' => null, 'contact_phone' => null, 'contact_name' => null,
                'progress_percent' => 0, 'project_phase' => null, 'status' => null,
                'created_at' => '2026-09-02 08:00:00', 'updated_at' => '2026-09-02 08:00:00'],
        ]);

        $html = (string) $this->actingAs($admin)->get('/finance/project-receivables')->assertOk()->getContent();

        $this->assertStringContainsString('CT-GUARD', $html);
        $this->assertStringContainsString('CT-770011', $html, 'không có mã thì ghép CT-<id>');
        $this->assertStringContainsString('Chưa có địa chỉ', $html);
        $this->assertStringContainsString('Chưa có SĐT', $html);
        $this->assertStringContainsString('Không còn đợt chờ thu', $html);
        $this->assertStringContainsString('Tiến độ: 45% · Thi công', $html);
        $this->assertStringContainsString('scope="col"', $html);
    }

    public function test_khong_co_cong_trinh_thi_in_dong_thong_bao(): void
    {
        $admin = $this->userWithRole('admin', ['id' => 770002, 'name' => 'QT Guard 2']);

        $html = (string) $this->actingAs($admin)
            ->get('/finance/project-receivables?keyword=khong-bao-gio-co-ma-nay')
            ->assertOk()->getContent();

        $this->assertStringContainsString('Không có công trình phù hợp bộ lọc.', $html);
    }
}
