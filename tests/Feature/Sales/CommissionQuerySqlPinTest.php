<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Role;
use App\Http\Controllers\CRM\SalesCommissionController;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ghim CHUỖI SQL mà màn hình hoa hồng sinh ra, trước khi tách controller.
 *
 * ## Vì sao ghim SQL chứ không ghim số liệu
 * CSDL test không có bản ghi nghiệp vụ nào, nên so kết quả chỉ chứng minh được
 * nhánh dữ liệu rỗng. Nhưng `buildQuery()` là hàm THUẦN dựng truy vấn: cùng
 * lược đồ + cùng tham số thì phải ra cùng một câu SQL. So chuỗi SQL và danh sách
 * binding bắt được mọi thay đổi hành vi khi tách hàm — kể cả đổi thứ tự cột hay
 * mất một mệnh đề.
 *
 * ## Vì sao dùng reflection
 * `buildQuery` và `orderTotalBeforeVatSubquery` là `private`. Tách chúng ra lớp
 * riêng chính là việc sắp làm; test này phải chạy được ở CẢ HAI trạng thái nên
 * gọi qua reflection thay vì đổi tầm nhìn chỉ để test.
 *
 * ## Đã kiểm: không có SQL injection ở đây
 * 19 biến được nội suy vào chuỗi SQL đều dựng từ danh sách tên cột CỐ ĐỊNH trong
 * mã (dò lược đồ rồi chọn cột nào có thật). Biến duy nhất đến từ người dùng là
 * `$kw = $request->get('q')`, và nó đi vào GIÁ TRỊ ràng buộc của
 * `->where(..., 'like', "%{$kw}%")` — Laravel bind tham số, không nối vào SQL.
 */
final class CommissionQuerySqlPinTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, array{0: array<string, string>}> */
    public static function filters(): array
    {
        return [
            'không lọc' => [[]],
            'có từ khoá' => [['q' => 'ĐH-001']],
            'từ khoá có ký tự SQL' => [['q' => "'; DROP TABLE crm_orders; --"]],
            'lọc theo sales' => [['sales_user_id' => '7']],
            'lọc số tiền tối thiểu' => [['min_commission' => '1000000']],
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    #[DataProvider('filters')]
    public function test_sql_va_binding_khop_ban_ghim(array $query): void
    {
        [$sql, $bindings] = $this->buildSql($query);

        $path = $this->pinPath($query);

        if (! is_file($path)) {
            @mkdir(dirname($path), 0755, true);
            file_put_contents($path, json_encode(
                ['sql' => $sql, 'bindings' => $bindings],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $this->markTestSkipped('vừa tạo bản ghim mới: '.basename($path).' — chạy lại để so.');
        }

        /** @var array{sql: string, bindings: array<int, mixed>} $pinned */
        $pinned = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($pinned['sql'], $sql,
            "Câu SQL của màn hình hoa hồng đã đổi.\n".
            "Nếu đây là thay đổi CÓ CHỦ Ý thì xoá tệp ghim rồi chạy lại để tạo bản mới:\n".$path);

        $this->assertSame($pinned['bindings'], $bindings, 'Danh sách binding đã đổi.');
    }

    /** Ràng buộc phải là THAM SỐ, không được nối chuỗi vào SQL. */
    public function test_tu_khoa_nguoi_dung_di_vao_binding_chu_khong_vao_sql(): void
    {
        $payload = "'; DROP TABLE crm_orders; --";

        [$sql, $bindings] = $this->buildSql(['q' => $payload]);

        $this->assertStringNotContainsString('DROP TABLE', $sql,
            'Từ khoá người dùng bị nối thẳng vào SQL — đây là lỗ hổng SQL injection.');

        $this->assertContains('%'.$payload.'%', $bindings,
            'Từ khoá phải xuất hiện trong danh sách binding.');
    }

    /**
     * @param  array<string, string>  $query
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildSql(array $query): array
    {
        $user = $this->userWithRole(Role::Admin->value, ['is_active' => 1]);
        $this->actingAs($user);

        $controller = app(SalesCommissionController::class);

        $method = new \ReflectionMethod($controller, 'buildQuery');
        $method->setAccessible(true);

        $builder = $method->invoke(
            $controller,
            Request::create('/sales/commissions', 'GET', $query),
            $user,
            Carbon::parse('2026-01-01')->startOfDay(),
            Carbon::parse('2026-01-31')->endOfDay(),
        );

        /*
         * id VÀ TÊN người dùng đều đổi mỗi lần chạy (helper tạo user dùng faker),
         * nên phải thay bằng chỗ giữ chỗ. Bỏ sót phần tên thì bản ghim đỏ ngẫu
         * nhiên — đã vấp đúng vậy khi viết test này.
         */
        $bindings = array_map(
            static fn (mixed $b): mixed => match (true) {
                $b === $user->id => '<<user_id>>',
                $b === $user->name => '<<user_name>>',
                default => $b,
            },
            $builder->getBindings(),
        );

        /*
         * Cho binding đi qua đúng phép chuyển JSON mà bản ghim dùng. Nếu không,
         * float 1000000.0 của PHP so với 1000000 đọc ra từ JSON sẽ khác kiểu và
         * test đỏ ngẫu nhiên — đã vấp.
         */
        $bindings = json_decode((string) json_encode($bindings), true, 512, JSON_THROW_ON_ERROR);

        return [$builder->toSql(), $bindings];
    }

    /** @param array<string, string> $query */
    private function pinPath(array $query): string
    {
        ksort($query);

        return base_path('tests/Fixtures/commission-sql/'
            .($query === [] ? 'khong-loc' : substr(md5(json_encode($query)), 0, 12)).'.json');
    }
}
