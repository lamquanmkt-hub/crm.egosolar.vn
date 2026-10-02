<?php

declare(strict_types=1);

namespace App\Services\Debug;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Chấm điểm mức độ tuân REST của bảng định tuyến.
 *
 * ## Định nghĩa "REST" dùng ở đây
 * KHÔNG bắt đổi slug tiếng Việt sang tiếng Anh — URL là thứ người dùng nhìn
 * thấy và đang được bookmark. Chỉ soi phần CẤU TRÚC:
 *
 * 1. **Động từ nằm trong URL** — `/orders/{id}/duyet`, `/payment-requests/{id}/copy`.
 *    Cách REST: hành động là tài nguyên con (`POST /orders/{order}/approvals`)
 *    hoặc dùng đúng động từ HTTP.
 * 2. **Sai động từ HTTP** — `POST` để sửa (phải `PUT`/`PATCH`), `POST`/`GET` để
 *    xoá (phải `DELETE`).
 * 3. **Tên tài nguyên số ít** — `/company-management` thay vì danh từ số nhiều.
 * 4. **Route không tên** — không refactor URL được vì không biết ai đang gọi.
 *
 * Công cụ này CHỈ ĐỌC, dùng để đo tiến độ chuyển đổi từng đợt.
 */
final class RestConformanceAuditor
{
    /** @var list<string>|null Cache đoạn URI đóng vai tập tài nguyên */
    private ?array $resourceSegments = null;

    /**
     * Động từ hay bị nhét vào URL (cả tiếng Việt lẫn tiếng Anh).
     *
     * ⚠️ KHÔNG có `create` và `edit`: `GET /orders/create` và
     * `GET /orders/{order}/edit` là hai route resource CHUẨN của Laravel, dùng
     * để hiện form. Chấm chúng là lỗi thì công cụ sẽ xúi sửa code vốn đã đúng.
     *
     * ⚠️ KHÔNG có `download`: nó đi cặp với `preview` để chỉ HAI BIỂU DIỄN của
     * cùng một tài nguyên (đính kèm vs xem trong trang), và là quy ước cả ngành
     * dùng (GitHub, Google Drive, S3). Từng đổi thành `/tep` rồi trả lại: đổi
     * chỉ để qua bảng đo, nhưng làm đường dẫn tiếng Anh lẫn một từ tiếng Việt,
     * đi ngược quy ước, và bắt phải sửa 3 chỗ hard-code URL trong Blade — rủi ro
     * thật đổi lấy lợi ích bằng không. Muốn chuẩn hơn thì dùng content
     * negotiation, không phải đổi tên đường dẫn.
     */
    private const URL_VERBS = [
        'huy', 'xoa', 'sua', 'them', 'tao', 'luu', 'gui',
        'copy', 'clone', 'sync', 'export',
        'delete', 'update', 'store', 'save',
    ];

    /**
     * Thao tác NẠP/XUẤT hàng loạt — quy ước quen thuộc, KHÔNG chấm là lỗi.
     *
     * `POST /marketing/leads/import`, `GET /marketing/leads/upload` mô tả một
     * quy trình nhập liệu, không phải CRUD trên một tài nguyên. Đổi tên chúng
     * chỉ làm URL lạ hơn mà không rõ nghĩa hơn.
     *
     * @var list<string>
     */
    private const BULK_OPERATION_VERBS = ['import', 'upload', 'sync', 'sync-customers'];

    /**
     * Động từ CHỈ CHUYỂN TRẠNG THÁI — cố ý KHÔNG chấm là lỗi.
     *
     * `POST /orders/{order}/submit`, `/cancel`, `/approve`, `/reject`... mô tả
     * một SỰ KIỆN nghiệp vụ, không phải thao tác CRUD trên tài nguyên. Đây là
     * quy ước phổ biến và đọc là hiểu ngay.
     *
     * Bài học đã trả giá: tôi từng đổi `/submit` -> `/luot-gui-duyet` và
     * `/cancel` -> `/luot-huy` cho "đúng lý thuyết". Kết quả: từ ghép tiếng Việt
     * gượng ép, nhét vào đường dẫn tiếng Anh, không ai đọc ra nghĩa — đổi chỉ để
     * qua bảng đo chứ không ai được lợi. Đã trả lại.
     *
     * Muốn chuẩn REST hơn thì mô hình hoá thành tài nguyên con thật sự
     * (`POST /orders/{order}/approvals` ghi vào bảng crm_order_approvals) — đó
     * là đổi THIẾT KẾ, phải làm có chủ đích, không phải đổi tên đường dẫn.
     *
     * @var list<string>
     */
    private const STATE_TRANSITION_VERBS = [
        // Tiếng Anh và tiếng Việt của CÙNG một khái niệm — phải cùng cách chấm,
        // nếu không công cụ sẽ xúi đổi `duyet` mà tha `approve`.
        'submit', 'cancel', 'approve', 'reject', 'duyet', 'tu-choi',
        'send', 'toggle', 'assign', 'process',
        // `restore` = khôi phục bản ghi đã xoá mềm: cũng là chuyển trạng thái,
        // cùng loại với `cancel`. Đứng riêng thì thành bất nhất.
        'restore', 'khoi-phuc',
        // Bản tiếng Việt của `submit` — phải cùng cách chấm, nếu không công cụ
        // lại thiên vị ngôn ngữ như vụ duyet/approve.
        'gui-duyet',
        'bat-dau', 'nhan-viec', 'nop-ket-qua', 'tra-lai', 'xac-nhan', 'phan-cong',
    ];

    /** Tiền tố hạ tầng, không tính vào điểm. */
    private const SKIP_PREFIXES = ['_debugbar', '_ignition', 'sanctum', 'telescope', 'horizon', 'livewire'];

    /** URI hạ tầng khớp CHÍNH XÁC (không phải tiền tố): health-check của Laravel. */
    private const SKIP_EXACT = ['up', 'storage/{path}'];

    /**
     * Báo cáo theo từng module (lấy từ đoạn đầu của URI).
     *
     * @return array{
     *     summary: array{total: int, conformant: int, percent: float},
     *     issues: array<string, int>,
     *     modules: list<array{module: string, total: int, issues: int, percent: float, samples: list<string>}>
     * }
     */
    public function audit(): array
    {
        $byModule = [];
        $issueCounts = ['verb_in_url' => 0, 'wrong_http_verb' => 0, 'unnamed' => 0, 'singular_resource' => 0];
        $total = 0;
        $conformant = 0;

        foreach ($this->auditableRoutes() as $route) {
            $issues = $this->issuesFor($route);
            $module = $this->moduleOf($route);

            $total++;
            $byModule[$module] ??= ['module' => $module, 'total' => 0, 'issues' => 0, 'samples' => []];
            $byModule[$module]['total']++;

            if ($issues === []) {
                $conformant++;

                continue;
            }

            $byModule[$module]['issues']++;

            foreach ($issues as $issue) {
                $issueCounts[$issue]++;
            }

            if (count($byModule[$module]['samples']) < 3) {
                $byModule[$module]['samples'][] = sprintf(
                    '%s /%s  [%s]',
                    implode('|', array_diff($route->methods(), ['HEAD'])),
                    $route->uri(),
                    implode(', ', $issues),
                );
            }
        }

        $modules = array_values($byModule);

        foreach ($modules as $index => $module) {
            $modules[$index]['percent'] = $module['total'] > 0
                ? round(($module['total'] - $module['issues']) / $module['total'] * 100, 1)
                : 100.0;
        }

        usort($modules, static fn (array $a, array $b): int => $b['issues'] <=> $a['issues']);

        return [
            'summary' => [
                'total' => $total,
                'conformant' => $conformant,
                'percent' => $total > 0 ? round($conformant / $total * 100, 1) : 100.0,
            ],
            'issues' => $issueCounts,
            'modules' => $modules,
        ];
    }

    /**
     * Các vấn đề REST của một route.
     *
     * @return list<string>
     */
    public function issuesFor(RoutingRoute $route): array
    {
        $issues = [];
        $uri = $route->uri();
        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        $segments = array_filter(explode('/', $uri), static fn (string $s): bool => $s !== '' && ! str_starts_with($s, '{'));

        $accepted = array_merge(self::STATE_TRANSITION_VERBS, self::BULK_OPERATION_VERBS);

        foreach ($segments as $segment) {
            if (in_array($segment, $accepted, true)) {
                continue;
            }

            if (in_array($segment, self::URL_VERBS, true) || $this->startsWithVerb($segment, $accepted)) {
                $issues[] = 'verb_in_url';
                break;
            }
        }

        $last = end($segments) ?: '';

        if (in_array('POST', $methods, true) && in_array($last, ['update', 'sua', 'edit-save'], true)) {
            $issues[] = 'wrong_http_verb';
        }

        if (array_intersect($methods, ['GET', 'POST']) && in_array($last, ['delete', 'xoa', 'destroy'], true)) {
            $issues[] = 'wrong_http_verb';
        }

        /*
        | ⚠️ Không so `getName() === null` được: khi chạy `route:cache` (tức là
        | trên production), Laravel TỰ gán tên `generated::xxxxx` cho mọi route
        | chưa đặt tên. Nếu chỉ kiểm tra null thì điểm trên production sẽ cao
        | hơn local đúng bằng số route không tên — công cụ đo nói dối ở chính
        | môi trường cần đo nhất.
        */
        $name = $route->getName();

        if ($name === null || str_starts_with($name, 'generated::')) {
            $issues[] = 'unnamed';
        }

        /*
        | Tên số ít CHỈ tính là lỗi khi đoạn đó thực sự là một TẬP TÀI NGUYÊN,
        | tức có route dạng `<đoạn>/{tham-số}`. Nếu không thì nó chỉ là namespace
        | module (`/marketing/...`, `/finance/...`) — chấm lỗi ở đây là báo nhầm
        | và làm hỏng cả bảng điểm.
        */
        $first = $segments === [] ? '' : reset($segments);

        if ($first !== ''
            && in_array($first, $this->resourceSegments(), true)
            && ! str_contains($first, '-')
            && preg_match('/^[a-z]+$/', $first) === 1
            && Str::singular($first) === $first
            && Str::plural($first) !== $first) {
            $issues[] = 'singular_resource';
        }

        return array_values(array_unique($issues));
    }

    /**
     * Đoạn URL có phải ĐỘNG TỪ GHÉP không, vd `tao-don-xuat`, `sua-vpp`, `xoa-vpp`.
     *
     * Bản đầu chỉ so khớp nguyên đoạn nên bỏ lọt hết nhóm này và báo 100% trong
     * khi kiểm chứng tay vẫn tìm ra 5 route còn động từ. Đo mà sai thì tệ hơn
     * không đo.
     *
     * @param  list<string>  $accepted
     */
    private function startsWithVerb(string $segment, array $accepted): bool
    {
        if (! str_contains($segment, '-')) {
            return false;
        }

        $head = explode('-', $segment)[0];

        if (in_array($head, $accepted, true)) {
            return false;
        }

        return in_array($head, self::URL_VERBS, true);
    }

    /**
     * Route nghiệp vụ cần soi (bỏ hạ tầng).
     *
     * @return list<RoutingRoute>
     */
    private function auditableRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (Str::startsWith($route->uri(), self::SKIP_PREFIXES)
                || in_array($route->uri(), self::SKIP_EXACT, true)) {
                continue;
            }

            $routes[] = $route;
        }

        return $routes;
    }

    /**
     * Các đoạn đầu URI thực sự đóng vai TẬP TÀI NGUYÊN (có route `<đoạn>/{param}`).
     *
     * @return list<string>
     */
    private function resourceSegments(): array
    {
        if ($this->resourceSegments !== null) {
            return $this->resourceSegments;
        }

        $looksLikeResource = [];
        $subCollections = [];

        $routeCountBySegment = [];

        foreach ($this->auditableRoutes() as $route) {
            $parts = explode('/', $route->uri());

            if (count($parts) < 2 || $parts[0] === '') {
                continue;
            }

            $routeCountBySegment[$parts[0]] = ($routeCountBySegment[$parts[0]] ?? 0) + 1;

            if (str_starts_with($parts[1], '{')) {
                $looksLikeResource[$parts[0]] = true;
            } else {
                // Đoạn con là danh từ -> dấu hiệu đây là NAMESPACE module
                $subCollections[$parts[0]][$parts[1]] = true;
            }
        }

        /*
        | Có `<đoạn>/{param}` thôi CHƯA đủ: `/chat/{id}` tồn tại nhưng `chat`
        | thực chất là namespace vì còn `/chat/orders`, `/chat/conversations`,
        | `/chat/tasks`... Quy ước: từ 3 tập con trở lên thì coi là namespace,
        | không chấm lỗi "tên số ít" nữa.
        */
        $resources = [];

        foreach (array_keys($looksLikeResource) as $segment) {
            // Chỉ có ĐÚNG MỘT route (vd `/storage/{path}` phục vụ file) thì đó
            // là điểm gắn, không phải tập tài nguyên có index/show.
            if (($routeCountBySegment[$segment] ?? 0) < 2) {
                continue;
            }

            if (count($subCollections[$segment] ?? []) < 3) {
                $resources[] = $segment;
            }
        }

        return $this->resourceSegments = $resources;
    }

    /** Module = đoạn đầu tiên của URI. */
    private function moduleOf(RoutingRoute $route): string
    {
        $segments = explode('/', $route->uri());
        $first = $segments[0] ?? '';

        return $first === '' || str_starts_with($first, '{') ? '(gốc)' : $first;
    }
}
