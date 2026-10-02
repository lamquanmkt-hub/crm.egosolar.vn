<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html as HtmlWriter;

/**
 * Dựng phần thân HTML để xem nhanh một tệp: ảnh, PDF, bảng tính, CSV.
 *
 * Chỉ trả về đoạn HTML bên trong trang; khung trang và CSS nằm ở view
 * `marketing.plan.file-preview`.
 */
final class FilePreviewRenderer
{
    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    /** @var list<string> */
    private const SPREADSHEET_EXTENSIONS = ['xlsx', 'xls', 'ods'];

    /**
     * Kết quả dựng: HTML thân trang + danh sách tab sheet (nếu là bảng tính).
     *
     * @return array{body: string, tabs: list<array{label: string, url: string, active: bool}>}
     */
    public function render(LocatedFile $file, int $sheetIndex, string $previewUrl, array $query): array
    {
        if ($file->isRemote()) {
            return ['body' => $this->iframe((string) $file->url), 'tabs' => []];
        }

        $real = (string) $file->realPath;
        $extension = $file->extension();

        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return ['body' => $this->image($real, $extension), 'tabs' => []];
        }

        if ($extension === 'pdf') {
            return ['body' => $this->iframe('data:application/pdf;base64,'.base64_encode((string) file_get_contents($real))), 'tabs' => []];
        }

        if (in_array($extension, self::SPREADSHEET_EXTENSIONS, true)) {
            return $this->spreadsheet($real, $sheetIndex, $previewUrl, $query);
        }

        if ($extension === 'csv') {
            return ['body' => $this->csv($real), 'tabs' => []];
        }

        return [
            'body' => $this->notice('Định dạng chưa hỗ trợ xem nhanh: '.e($extension)),
            'tabs' => [],
        ];
    }

    private function iframe(string $src): string
    {
        return '<iframe src="'.e($src).'"></iframe>';
    }

    /**
     * Nhúng ảnh dạng base64 để không phải mở thêm route phục vụ tệp tĩnh —
     * tệp nằm ngoài thư mục public nên không có URL trực tiếp.
     */
    private function image(string $real, string $extension): string
    {
        $mime = @mime_content_type($real) ?: 'image/'.($extension === 'jpg' ? 'jpeg' : $extension);
        $data = base64_encode((string) file_get_contents($real));

        return '<div class="preview-body"><img src="data:'.e($mime).';base64,'.$data.'"></div>';
    }

    /**
     * Bảng tính: chuyển sang HTML bằng PhpSpreadsheet, mỗi sheet một tab.
     *
     * @param  array<string, mixed>  $query
     * @return array{body: string, tabs: list<array{label: string, url: string, active: bool}>}
     */
    private function spreadsheet(string $real, int $sheetIndex, string $previewUrl, array $query): array
    {
        if (! class_exists(IOFactory::class)) {
            return [
                'body' => $this->notice('Server chưa có PhpSpreadsheet. Chạy: <b>composer require phpoffice/phpspreadsheet</b>'),
                'tabs' => [],
            ];
        }

        try {
            $reader = IOFactory::createReaderForFile($real);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($real);

            $sheetCount = $spreadsheet->getSheetCount();

            if ($sheetIndex >= $sheetCount) {
                $sheetIndex = 0;
            }

            $tabs = [];

            for ($index = 0; $index < $sheetCount; $index++) {
                $tabs[] = [
                    'label' => $spreadsheet->getSheet($index)->getTitle(),
                    'url' => $previewUrl.'?'.http_build_query(array_merge($query, ['sheet' => $index])),
                    'active' => $index === $sheetIndex,
                ];
            }

            $writer = new HtmlWriter($spreadsheet);
            $writer->setSheetIndex($sheetIndex);
            $writer->setUseInlineCss(true);

            ob_start();
            $writer->save('php://output');
            $html = (string) ob_get_clean();

            $spreadsheet->disconnectWorksheets();

            return [
                'body' => '<div class="preview-body"><div class="excel-wrap">'.$this->stripHtmlShell($html).'</div></div>',
                'tabs' => $tabs,
            ];
        } catch (\Throwable $e) {
            return [
                'body' => $this->notice('Không đọc được Excel: '.e($e->getMessage())),
                'tabs' => [],
            ];
        }
    }

    /**
     * Bỏ khung trang mà PhpSpreadsheet tự sinh, chỉ giữ phần bảng.
     */
    private function stripHtmlShell(string $html): string
    {
        $html = (string) preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);

        return (string) preg_replace('/<html[^>]*>|<\/html>|<head[^>]*>.*?<\/head>|<body[^>]*>|<\/body>/is', '', $html);
    }

    private function csv(string $real): string
    {
        $lines = file($real);

        if ($lines === false) {
            return $this->notice('Không đọc được tệp CSV.');
        }

        $table = '<table style="border-collapse:collapse;width:100%;font-size:13px">';

        foreach (array_map('str_getcsv', $lines) as $rowIndex => $row) {
            $tag = $rowIndex === 0 ? 'th' : 'td';
            $table .= '<tr>';

            foreach ($row as $cell) {
                $table .= '<'.$tag.' style="border:1px solid #d7dee8;padding:7px 9px">'.e((string) $cell).'</'.$tag.'>';
            }

            $table .= '</tr>';
        }

        $table .= '</table>';

        return '<div class="preview-body"><div class="excel-wrap">'.$table.'</div></div>';
    }

    private function notice(string $message): string
    {
        return '<div class="preview-body"><div class="notice">'.$message.'</div></div>';
    }
}
