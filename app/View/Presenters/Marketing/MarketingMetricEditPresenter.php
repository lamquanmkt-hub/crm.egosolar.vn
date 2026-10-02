<?php

declare(strict_types=1);

namespace App\View\Presenters\Marketing;

use App\DTOs\Marketing\MarketingMetricFormValues;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Chuẩn bị biểu mẫu `marketing/metrics_edit`.
 *
 * Thay khối `@php` 15 dòng: ba lượt `json_decode` cột breakdown, hai lượt `Carbon::parse()->format()`
 * viết thẳng trong `value=`, và chuỗi dự phòng `$metric ?? $m ?? $row ?? null` (controller luôn
 * truyền `metric` nên hai nhánh sau là code chết).
 *
 * Lớp này thuần: `old()` vào bằng tham số `$oldInput`.
 */
final class MarketingMetricEditPresenter
{
    /** Các dải tuổi trong biểu mẫu — giữ đúng thứ tự bản cũ. */
    public const AGE_RANGES = ['18-24', '25-34', '35-44', '45-54', '55+'];

    /** Khu vực trong biểu mẫu. */
    public const REGIONS = ['HCM', 'Hà Nội', 'Khác'];

    /** Kênh quảng cáo trong biểu mẫu. */
    public const PLATFORMS = ['Facebook', 'Google', 'TikTok', 'Zalo', 'Khác'];

    /**
     * @param  array<string, mixed>  $oldInput
     * @return array{formValues: MarketingMetricFormValues}
     */
    public function viewData(object $metric, array $oldInput): array
    {
        $gender = $this->bang($metric->gender_breakdown ?? null);
        $age = $this->bang($metric->age_breakdown ?? null);
        $region = $this->bang($metric->region_breakdown ?? null);

        $cu = fn (string $khoa, mixed $macDinh): string => $this->cu($oldInput, $khoa, $macDinh);

        return [
            'formValues' => new MarketingMetricFormValues(
                id: (int) ($metric->id ?? 0),
                dateFrom: $cu('date_from', $this->ngay($metric->date_from ?? null)),
                dateTo: $cu('date_to', $this->ngay($metric->date_to ?? null)),
                platform: $cu('platform', $metric->platform ?? ''),
                campaignId: $cu('campaign_id', $metric->campaign_id ?? ''),
                // Bản cũ: `old('reach', $m->reach ?? 0)` — thiếu giá trị thì in 0, không in rỗng.
                reach: $cu('reach', $metric->reach ?? 0),
                leads: $cu('leads', $metric->leads ?? 0),
                spend: $cu('spend', $metric->spend ?? 0),
                note: $cu('note', $metric->note ?? ''),
                gender: $this->oTheoKhoa($oldInput, 'gender', $gender, ['male', 'female', 'unknown']),
                age: $this->oTheoKhoa($oldInput, 'age', $age, self::AGE_RANGES),
                region: $this->oTheoKhoa($oldInput, 'region', $region, self::REGIONS),
            ),
        ];
    }

    /**
     * Gộp `old()` ĐÚNG ngữ nghĩa Laravel: nếu khoá CÓ trong old input thì lấy giá trị đó, **kể cả
     * khi nó là null**; chỉ khi khoá KHÔNG có mới rơi về giá trị mặc định.
     *
     * ⚠️ Dùng `?? $macDinh` là SAI: middleware `ConvertEmptyStringsToNull` biến ô bỏ trống thành
     * null, nên sau khi validation lỗi, `old('platform')` trả **null** và bản cũ in ra RỖNG — còn
     * `??` lại rơi về giá trị trong DB và tự chọn lại lựa chọn cũ. Đo được: trang lỗi có thêm một
     * `<option selected>` không đúng.
     */
    private function cu(array $oldInput, string $khoa, mixed $macDinh): string
    {
        if (Arr::has($oldInput, $khoa)) {
            return (string) (Arr::get($oldInput, $khoa) ?? '');
        }

        return (string) ($macDinh ?? '');
    }

    /**
     * Breakdown: model đã cast `array`, nhưng bản cũ còn tự `json_decode` khi giá trị là CHUỖI.
     * Giữ cả hai nhánh để hành vi không đổi kể cả khi cast bị tắt hoặc dữ liệu cũ lạ.
     *
     * @return array<string, mixed>
     */
    private function bang(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Gộp `old('<nhom>.<khoa>')` với giá trị trong breakdown; thiếu cả hai thì 0 (đúng bản cũ).
     *
     * @param  array<string, mixed>  $oldInput
     * @param  array<string, mixed>  $breakdown
     * @param  list<string>  $khoa
     * @return array<string, int|string>
     */
    private function oTheoKhoa(array $oldInput, string $nhom, array $breakdown, array $khoa): array
    {
        $ra = [];

        foreach ($khoa as $k) {
            $khoaOld = $nhom.'.'.$k;
            $ra[$k] = Arr::has($oldInput, $khoaOld)
                ? (Arr::get($oldInput, $khoaOld) ?? '')
                : ($breakdown[$k] ?? 0);
        }

        return $ra;
    }

    /** `Y-m-d` cho `<input type="date">`; trống → `''` (bản cũ: toán tử ba ngôi trong view). */
    private function ngay(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : '';
    }
}
