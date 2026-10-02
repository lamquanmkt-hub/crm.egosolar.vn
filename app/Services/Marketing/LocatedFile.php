<?php

declare(strict_types=1);

namespace App\Services\Marketing;

/**
 * Một tệp đã tìm được, kèm điểm khớp.
 *
 * Trước đây là mảng kết hợp `['score' => ..., 'table' => ..., 'row' => ...]`
 * truyền qua lại giữa các closure — không ai biết chắc trong đó có khoá gì.
 */
final readonly class LocatedFile
{
    /**
     * @param  int  $score  Điểm khớp; càng cao càng khả năng đúng
     * @param  string  $table  Bảng tìm thấy, hiển thị cho người dùng biết nguồn
     * @param  string  $name  Tên tệp để hiển thị
     * @param  string|null  $url  URL ngoài (nếu tệp lưu ở nơi khác)
     * @param  string|null  $realPath  Đường dẫn thật trên đĩa
     */
    public function __construct(
        public int $score,
        public string $table,
        public string $name,
        public ?string $url = null,
        public ?string $realPath = null,
    ) {}

    /** Tệp nằm ở dịch vụ ngoài, chỉ nhúng được bằng URL. */
    public function isRemote(): bool
    {
        return $this->url !== null && $this->url !== '';
    }

    /** Phần mở rộng, chữ thường, không có dấu chấm. */
    public function extension(): string
    {
        $source = $this->name !== '' ? $this->name : (string) $this->realPath;

        return strtolower(pathinfo($source, PATHINFO_EXTENSION));
    }
}
