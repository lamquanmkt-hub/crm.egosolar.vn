<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class EgoDefaultCompany
{
    /**
     * Tìm đúng công ty EGO Việt Nam từ bảng companies.
     */
    public static function vietnamCompany(): ?object
    {
        if (! SchemaCache::hasTable('companies')) {
            return null;
        }

        $columns = SchemaCache::columns('companies');
        $select = array_values(array_intersect(
            ['id', 'code', 'name', 'short_name', 'is_active', 'active'],
            $columns
        ));

        if (! in_array('id', $select, true)) {
            $select[] = 'id';
        }

        $companies = DB::table('companies')->select($select)->orderBy('id')->get();

        return $companies
            ->sortByDesc(fn ($company): int => self::vietnamScore($company))
            ->first(fn ($company): bool => self::vietnamScore($company) > 0);
    }

    /**
     * Danh sách công ty còn hoạt động, dùng cho dropdown chọn công ty.
     *
     * Gom về đây vì ba view `sites/index`, `sites/create`, `sites/edit` trước đây
     * chép nguyên một khối truy vấn giống hệt nhau vào đầu tệp. Sửa điều kiện lọc
     * ở một chỗ mà quên hai chỗ kia là ra ba danh sách khác nhau trên ba trang.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function activeOptions(): Collection
    {
        if (! SchemaCache::hasTable('companies')) {
            return collect();
        }

        $query = DB::table('companies')->select('id', 'code', 'name');

        if (SchemaCache::hasColumn('companies', 'is_active')) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Nhãn hiển thị theo id công ty: "MÃ - Tên".
     *
     * @param  \Illuminate\Support\Collection<int, object>|null  $options
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function optionLabels(?Collection $options = null): Collection
    {
        return ($options ?? self::activeOptions())->mapWithKeys(fn ($c): array => [
            (int) $c->id => trim(($c->code ?? '').' - '.($c->name ?? '')),
        ]);
    }

    public static function vietnamCompanyOrFail(): object
    {
        $company = self::vietnamCompany();

        if (! $company) {
            throw new RuntimeException(
                'Không tìm thấy công ty EGO Việt Nam trong bảng companies.'
            );
        }

        return $company;
    }

    /**
     * Ghi EGO Việt Nam vào toàn bộ khóa session cũ/mới của CRM.
     */
    public static function forceSession(Request $request): object
    {
        $company = self::vietnamCompanyOrFail();
        $companyId = (int) $company->id;
        $companyName = trim((string) ($company->name ?? 'Công ty TNHH EGO Việt Nam'));

        foreach (self::sessionKeys() as $key) {
            $request->session()->put($key, $companyId);
        }

        $request->session()->put('active_company_name', $companyName);
        $request->session()->save();

        return $company;
    }

    /**
     * Chỉ vô hiệu hóa EGO Quốc Tế, không xóa dữ liệu lịch sử.
     */
    public static function deactivateInternationalCompanies(): array
    {
        if (! SchemaCache::hasTable('companies')) {
            return [];
        }

        $columns = SchemaCache::columns('companies');
        $select = array_values(array_intersect(['id', 'code', 'name', 'short_name'], $columns));
        $companies = DB::table('companies')->select($select)->orderBy('id')->get();
        $disabled = [];

        foreach ($companies as $company) {
            if (! self::isInternational($company)) {
                continue;
            }

            if (in_array('is_active', $columns, true)) {
                DB::table('companies')->where('id', $company->id)->update([
                    'is_active' => 0,
                    'updated_at' => now(),
                ]);
            } elseif (in_array('active', $columns, true)) {
                DB::table('companies')->where('id', $company->id)->update([
                    'active' => 0,
                ]);
            }

            $disabled[] = [
                'id' => (int) $company->id,
                'code' => (string) ($company->code ?? ''),
                'name' => (string) ($company->name ?? ''),
            ];
        }

        $vn = self::vietnamCompany();

        if ($vn) {
            $payload = [];

            if (in_array('is_active', $columns, true)) {
                $payload['is_active'] = 1;
                if (in_array('updated_at', $columns, true)) {
                    $payload['updated_at'] = now();
                }
            } elseif (in_array('active', $columns, true)) {
                $payload['active'] = 1;
            }

            if ($payload !== []) {
                DB::table('companies')->where('id', $vn->id)->update($payload);
            }
        }

        return $disabled;
    }

    public static function sessionKeys(): array
    {
        return [
            'company_id',
            'selected_company_id',
            'current_company_id',
            'ego_company_id',
            'active_company_id',
        ];
    }

    private static function vietnamScore(object $company): int
    {
        $text = self::normalizedCompanyText($company);

        if (self::isInternational($company)) {
            return 0;
        }

        $score = 0;

        if (preg_match('/(^|\s|_)EGO(\s|_)?VN($|\s|_)/', $text)) {
            $score += 100;
        }

        if (str_contains($text, 'VIET NAM')) {
            $score += 80;
        }

        if (preg_match('/(^|\s|_)VN($|\s|_)/', $text)) {
            $score += 30;
        }

        return $score;
    }

    private static function isInternational(object $company): bool
    {
        $text = self::normalizedCompanyText($company);

        return str_contains($text, 'QUOC TE')
            || str_contains($text, 'INTERNATIONAL')
            || str_contains($text, 'EGO QT')
            || str_contains($text, 'EGO_QT')
            || preg_match('/(^|\s|_)QT($|\s|_)/', $text) === 1;
    }

    private static function normalizedCompanyText(object $company): string
    {
        return Str::upper(Str::ascii(implode(' ', [
            $company->code ?? '',
            $company->name ?? '',
            $company->short_name ?? '',
        ])));
    }
}
