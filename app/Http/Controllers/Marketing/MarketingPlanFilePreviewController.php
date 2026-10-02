<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\FilePreviewRenderer;
use App\Services\Marketing\PlanFileLocator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Xem nhanh tệp đính kèm của kế hoạch marketing
 * (`GET /marketing/plan/file-preview/{file}`).
 *
 * Trước 2026-08-05 đây là một closure DÀI 344 DÒNG trong `routes/marketing.php`
 * — dài nhất hệ thống — gồm cả dò bảng, phân giải đường dẫn, đọc Excel và nối
 * chuỗi HTML. Nay chia làm bốn phần rõ việc:
 *
 *   PlanFileLocator        tìm bản ghi tệp giữa các bảng (chấm điểm)
 *   LocalFilePathResolver  đổi đường dẫn trong DB thành đường dẫn thật
 *   FilePreviewRenderer    dựng thân HTML theo từng định dạng
 *   view file-preview      khung trang + CSS
 */
final class MarketingPlanFilePreviewController extends Controller
{
    /**
     * Tệp Excel lớn cần nhiều bộ nhớ và thời gian hơn mặc định.
     *
     * Giữ nguyên hai con số của bản cũ; hạ xuống là có tệp không mở được.
     */
    private const MEMORY_LIMIT = '1024M';

    private const TIME_LIMIT_SECONDS = 180;

    public function __construct(
        private readonly PlanFileLocator $locator,
        private readonly FilePreviewRenderer $renderer,
    ) {}

    public function __invoke(Request $request, int $file): View
    {
        ini_set('memory_limit', self::MEMORY_LIMIT);
        set_time_limit(self::TIME_LIMIT_SECONDS);

        $desiredName = trim((string) $request->query('name', ''));
        $sheetIndex = max(0, (int) $request->query('sheet', 0));

        $located = $this->locator->locate($file, $desiredName, $request->query('plan_id'));

        if ($located === null) {
            return view('marketing.plan.file-preview', [
                'title' => 'Không tìm thấy file',
                'source' => null,
                'tabs' => [],
                'body' => '<div class="preview-body"><div class="notice">'.
                    'Không tìm thấy file ID #'.e((string) $file).' trong database/storage.'.
                    '</div></div>',
            ]);
        }

        $rendered = $this->renderer->render(
            $located,
            $sheetIndex,
            url('/marketing/plan/file-preview/'.$file),
            $request->query(),
        );

        return view('marketing.plan.file-preview', [
            'title' => $located->name !== '' ? $located->name : ($desiredName !== '' ? $desiredName : 'File #'.$file),
            'source' => $located->table,
            'tabs' => $rendered['tabs'],
            'body' => $rendered['body'],
        ]);
    }
}
