{{--
    Trang xem nhanh tệp đính kèm của kế hoạch marketing.

    Trang ĐỘC LẬP (không extends layout) vì nó được mở trong iframe/tab riêng và
    cố ý không kéo theo sidebar, header hay JS của ứng dụng — tệp Excel dựng ra
    hàng nghìn thẻ, càng ít thứ khác càng nhanh.

    Trước 2026-08-05 toàn bộ HTML+CSS này là chuỗi nối trong một closure ở
    routes/marketing.php.

    Biến truyền vào:
      $title  string  Tên tệp
      $source string|null  Tên bảng tìm thấy, hiển thị cho người dùng biết nguồn
      $body   string  HTML thân trang, đã dựng sẵn (FilePreviewRenderer)
      $tabs   array   Danh sách sheet: label, url, active
--}}
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box }
        html, body { margin: 0; min-height: 100%; font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a }
        .preview-top { position: sticky; top: 0; z-index: 9999; background: #fff; border-bottom: 1px solid #e5e7eb }
        .preview-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px }
        .preview-title { font-size: 14px; font-weight: 900; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
        .preview-source { padding: 7px 10px; border: 1px solid #dbe3ef; border-radius: 999px; font-size: 12px; font-weight: 800; background: #f8fafc }
        .preview-tabs { display: flex; gap: 8px; flex-wrap: wrap; padding: 10px 14px; border-top: 1px solid #eef2f7; background: #fff }
        .preview-tab { display: inline-flex; align-items: center; justify-content: center; padding: 8px 12px; border-radius: 999px; border: 1px solid #dbe3ef; background: #fff; color: #0f172a; text-decoration: none; font-size: 12px; font-weight: 900 }
        .preview-tab.active { background: #2563eb; border-color: #2563eb; color: #fff }
        .preview-body { padding: 14px; overflow: auto }
        .notice { padding: 14px; border: 1px solid #dbe3ef; border-radius: 14px; background: #fff; color: #475569; line-height: 1.5 }
        iframe { width: 100%; height: calc(100vh - 56px); border: 0; background: #fff }
        img { display: block; max-width: 100%; height: auto; margin: 0 auto }
        .excel-wrap { background: #fff; border: 1px solid #dbe3ef; border-radius: 14px; overflow: auto; box-shadow: 0 12px 30px rgba(15, 23, 42, .06) }
        .excel-wrap table { border-collapse: collapse !important }
        .excel-wrap td, .excel-wrap th { border: 1px solid #d7dee8 !important }
    </style>
</head>
<body>
    <div class="preview-top">
        <div class="preview-bar">
            <div class="preview-title">{{ $title }}</div>
            @if (! empty($source))
                <div class="preview-source">{{ $source }}</div>
            @endif
        </div>

        @if (! empty($tabs))
            <div class="preview-tabs">
                @foreach ($tabs as $tab)
                    <a class="preview-tab {{ $tab['active'] ? 'active' : '' }}" href="{{ $tab['url'] }}">{{ $tab['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>

    {{--
        $body là HTML do FilePreviewRenderer dựng (bảng Excel, thẻ img, iframe).
        Mọi dữ liệu do người dùng nhập trong đó đã qua e() ở phía renderer, nên ở
        đây in thô là đúng — dùng {{ }} sẽ hiện ra mã HTML thay vì nội dung tệp.
    --}}
    {!! $body !!}
</body>
</html>
