<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Hàng rào kiến trúc — phân tích tĩnh, không boot app, không chạm DB
|--------------------------------------------------------------------------
|
| Chỉ khai những luật repo ĐANG đạt, để test luôn xanh và ai phá là biết ngay.
| Muốn siết thêm thì sửa code trước rồi mới thêm luật — đừng thêm luật rồi
| tắt đi, vì luật bị tắt thì không còn là hàng rào.
|
| ⚠️ Giữ phạm vi hẹp: quét `App\Services` hay `App\Http\Controllers` làm
| php-parser ăn hết 512MB (giới hạn trong phpunit.xml) rồi chết giữa chừng.
| Hai tầng đó đã có test hợp đồng riêng ở tests/Feature/Architecture/.
*/

/*
 * Tầng Domain (state machine, quy tắc nghiệp vụ) không được biết tới HTTP và
 * framework.
 *
 * TODO: mục tiêu cuối là bỏ luôn `App\Models` khỏi danh sách — hiện
 * App\Domain\MaterialRequest\States còn cầm thẳng model Eloquent. Khi tách xong
 * thì thêm 'App\Models' vào mảng dưới đây.
 */
arch('tầng Domain không phụ thuộc framework hay HTTP')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'App\Http']);

arch('App\Enums chỉ chứa enum')
    ->expect('App\Enums')
    ->toBeEnums();

arch('DTO là readonly — chỉ chở dữ liệu, không đổi trạng thái')
    ->expect('App\DTOs')
    ->classes()
    ->toBeReadonly();

arch('App\Contracts chỉ chứa interface')
    ->expect('App\Contracts')
    ->toBeInterfaces();

arch('Action là final — một luồng nghiệp vụ, không kế thừa')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal();

/*
 * Presenter tầng trình bày (app/View/Presenters): nhận dữ liệu controller đã truy vấn, trả giá trị
 * cho Blade. Phải thuần: không Facade, không query builder, không đọc request/session — mọi thứ đi
 * qua tham số để test không cần DB. `final` vì không có interface (KISS, một người gọi).
 */
arch('presenter tầng trình bày là final và không chạm Facade/DB/HTTP')
    ->expect('App\View\Presenters')
    ->classes()
    ->toBeFinal()
    ->not->toUse(['Illuminate\Support\Facades', 'Illuminate\Database\Query', 'Illuminate\Http', 'App\Http']);
