{{-- ⚠️ VIEW CHẾT — KHÔNG CÒN ĐƯỢC INCLUDE (đánh dấu 2026-09-05)

     Khối @if ở cuối tệp RỖNG từ commit đầu tiên (8a2fa03): toàn bộ phần PHP bên
     dưới tính ra $egoCompanies rồi không in ra gì cả. Không view nào khác dùng
     $egoCompanies / $egoActiveId / $egoShort — đã rà toàn bộ resources/views và app.

     Trước đây layouts/app.blade.php include tệp này nên mỗi trang phải trả giá:
     5 lần đọc session, 3 lần kiểm schema, 1 câu SELECT trên companies.

     Giữ lại thay vì xoá vì đây có thể là phần chuyển công ty làm dở. Muốn dùng
     lại thì viết nội dung cho khối @if rồi include lại. --}}
@php
    use Illuminate\Support\Facades\DB;

    $egoCompanies = collect();
    $egoActiveId = 0;

    foreach ([
        'active_company_id',
        'current_company_id',
        'selected_company_id',
        'company_id',
        'ego_company_id'
    ] as $key) {
        if (session()->has($key) && session($key)) {
            $egoActiveId = (int) session($key);
            break;
        }
    }

    try {
        if (auth()->check() && \App\Support\SchemaCache::hasTable('companies')) {
            $query = DB::table('companies')->select('id', 'name');

            if (\App\Support\SchemaCache::hasColumn('companies', 'code')) {
                $query->addSelect('code');
            }

            if (\App\Support\SchemaCache::hasColumn('companies', 'is_active')) {
                $query->where('is_active', 1);
            }

            $egoCompanies = $query->orderBy('id')->limit(10)->get();

            if (!$egoActiveId && $egoCompanies->count()) {
                $egoActiveId = (int) $egoCompanies->first()->id;
            }
        }
    } catch (\Throwable $e) {
        $egoCompanies = collect();
    }

    $egoShort = function ($company) {
        $code = strtoupper((string) ($company->code ?? ''));
        $name = mb_strtoupper((string) ($company->name ?? ''), 'UTF-8');

        if (
            str_contains($code, 'QT') ||
            str_contains($code, 'INT') ||
            str_contains($name, 'QUỐC TẾ') ||
            str_contains($name, 'INTERNATIONAL')
        ) {
            return 'QT';
        }

        if (
            str_contains($code, 'VN') ||
            str_contains($name, 'VIỆT NAM')
        ) {
            return 'VN';
        }

        return $code ? mb_substr($code, 0, 3, 'UTF-8') : ('C' . ($company->id ?? ''));
    };

    $egoCompanies = $egoCompanies
        ->map(function ($company) use ($egoShort) {
            $company->ego_short = $egoShort($company);
            return $company;
        })
        ->filter(fn($company) => in_array($company->ego_short, ['VN', 'QT'], true))
        ->unique('ego_short')
        ->values();
@endphp

@if(auth()->check() && $egoCompanies->count())
@endif
