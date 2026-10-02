<?php

declare(strict_types=1);

namespace App\Services\Marketing;

/**
 * Đổi một đường dẫn lưu trong DB thành đường dẫn thật trên đĩa.
 *
 * Dữ liệu cũ lưu đường dẫn mỗi kiểu một khác: có bản ghi lưu `storage/app/...`,
 * có bản ghi lưu `public/uploads/...`, có bản ghi chỉ lưu mỗi tên tệp. Lớp này
 * thử lần lượt các vị trí quen thuộc cho tới khi gặp tệp có thật.
 */
final class LocalFilePathResolver
{
    /**
     * Thử phân giải một đường dẫn.
     *
     * @return array{url: string|null, real: string|null}|null
     *                                                         `url` khi là địa chỉ ngoài, `real` khi tìm thấy tệp trên đĩa,
     *                                                         null khi không phân giải được.
     */
    public function resolve(string $path): ?array
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL) !== false) {
            return ['url' => $path, 'real' => null];
        }

        foreach ($this->candidatesFor($path) as $candidate) {
            if (is_file($candidate)) {
                return ['url' => null, 'real' => $candidate];
            }
        }

        return null;
    }

    /**
     * Các vị trí có thể chứa tệp, theo thứ tự thử.
     *
     * @return list<string>
     */
    private function candidatesFor(string $path): array
    {
        $clean = ltrim($path, '/');
        $withoutStorage = str_starts_with($clean, 'storage/') ? substr($clean, 8) : $clean;
        $withoutPublic = str_starts_with($clean, 'public/') ? substr($clean, 7) : $clean;
        $basename = basename($clean);

        return [
            base_path($clean),
            storage_path('app/'.$clean),
            storage_path('app/public/'.$clean),
            storage_path('app/public/'.$withoutStorage),
            storage_path('app/public/'.$withoutPublic),
            public_path($clean),
            public_path('storage/'.$clean),
            public_path('storage/'.$withoutStorage),
            public_path('uploads/'.$basename),
            public_path('marketing/'.$basename),
            storage_path('app/public/uploads/'.$basename),
            storage_path('app/public/marketing/'.$basename),
        ];
    }
}
