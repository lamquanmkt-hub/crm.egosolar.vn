<?php

declare(strict_types=1);

namespace App\Support\Ui;

/**
 * Biết một lớp utility chi phối những thuộc tính CSS nào.
 *
 * ## Vì sao cần
 * `$attributes->class()` của Laravel chỉ NỐI chuỗi. Trên một phần tử mang cả lớp
 * của component lẫn lớp của nơi gọi, ai thắng KHÔNG do thứ tự trong HTML mà do
 * thứ tự trong TỆP CSS. Đo trong bản build: `.tw\:mb-0` ở byte 1942, `.tw\:mb-4`
 * ở 2116 — nên `<x-ui.alert class="tw:mb-0">` bị lớp nền `tw:mb-4` của component
 * đè, im lặng. Trước đây phải chữa bằng `tw:mb-0!`; nay component tự bỏ lớp nền
 * khi nơi gọi đã đặt cùng thuộc tính, và kết quả không còn phụ thuộc thứ tự CSS.
 *
 * Cùng bảng tra này còn dùng để canh: utility Bootstrap nào cũng `!important`,
 * nên đặt cạnh utility Tailwind cùng thuộc tính là Bootstrap thắng
 * (xem `ImportantDaGoBoTest`).
 */
final class LopTienIch
{
    /** Hậu tố cạnh của Bootstrap -> cạnh vật lý. `s`/`e` là start/end, LTR. */
    private const CANH_BS = [
        't' => ['top'], 'b' => ['bottom'], 's' => ['left'], 'e' => ['right'],
        'x' => ['left', 'right'], 'y' => ['top', 'bottom'],
        '' => ['top', 'right', 'bottom', 'left'],
    ];

    /** Tailwind có thêm `l`/`r` bên cạnh `s`/`e`. */
    private const CANH_TW = self::CANH_BS + ['l' => ['left'], 'r' => ['right']];

    /**
     * Những longhand mà lớp này chi phối. Rỗng nghĩa là "không biết" — và khi
     * không biết thì KHÔNG bỏ lớp nào, để nhầm lẫn nghiêng về phía an toàn.
     *
     * @return list<string>
     */
    public static function thuocTinh(string $lop): array
    {
        if ($lop === '') {
            return [];
        }

        return str_starts_with($lop, 'tw:') ? self::cuaTailwind($lop) : self::cuaBootstrap($lop);
    }

    /**
     * Bỏ khỏi $nen những lớp mà $noiGoi đã phủ TRỌN VẸN.
     *
     * Phủ trọn vẹn mới bỏ: `tw:py-2` không thay được `tw:p-4` vì nó không đặt
     * trái/phải — bỏ đi là mất đệm ngang. Trường hợp phủ một phần cứ để nguyên,
     * thứ tự CSS lo (đã đo: `.tw\:p-4` đứng trước `.tw\:py-2` nên `py-2` thắng
     * đúng phần nó phủ; `LopComponentCoTrongCssTest` canh thứ tự đó).
     */
    public static function nhuong(string $nen, string $noiGoi): string
    {
        $cuaNoiGoi = [];

        foreach (preg_split('/\s+/', trim($noiGoi)) ?: [] as $lop) {
            foreach (self::thuocTinh($lop) as $t) {
                $cuaNoiGoi[$t] = true;
            }
        }

        if ($cuaNoiGoi === []) {
            return $nen;
        }

        $giu = [];

        foreach (preg_split('/\s+/', trim($nen)) ?: [] as $lop) {
            $cua = self::thuocTinh($lop);

            if ($cua !== [] && array_diff($cua, array_keys($cuaNoiGoi)) === []) {
                continue;
            }

            $giu[] = $lop;
        }

        return implode(' ', $giu);
    }

    /**
     * Hai lớp có cùng chi phối thuộc tính nào không.
     *
     * @return list<string>
     */
    public static function chungThuocTinh(string $a, string $b): array
    {
        return array_values(array_intersect(self::thuocTinh($a), self::thuocTinh($b)));
    }

    /** @return list<string> */
    private static function cuaTailwind(string $lop): array
    {
        if (str_ends_with($lop, '!')) {
            // Có `!` thì nó tự thắng, không cần ai nhường.
            return [];
        }

        $than = substr($lop, 3);

        // Bỏ mọi tiền tố biến thể (`md:`, `hover:`…). Lớp chỉ đổi ở một breakpoint
        // KHÔNG thay thế được lớp nền vô điều kiện, nên trả rỗng.
        if (preg_match('/^[a-z0-9-]+:/', $than) === 1) {
            return [];
        }

        if (preg_match('/^-?([mp])([trblsexy]?)-/', $than, $m) === 1) {
            return array_map(static fn (string $c): string => $m[1].'-'.$c, self::CANH_TW[$m[2]]);
        }

        return self::chung($than);
    }

    /** @return list<string> */
    private static function cuaBootstrap(string $lop): array
    {
        if (preg_match('/^([mp])([tbsexy]?)-(?:sm-|md-|lg-|xl-|xxl-)?(?:auto|[0-5])$/', $lop, $m) === 1) {
            return array_map(static fn (string $c): string => $m[1].'-'.$c, self::CANH_BS[$m[2]]);
        }

        if (preg_match('/^d-(?:sm-|md-|lg-|xl-|xxl-)?\w/', $lop) === 1) {
            return ['display'];
        }

        if (preg_match('/^text-(?:sm-|md-|lg-|xl-|xxl-)?(?:start|center|end)$/', $lop) === 1) {
            return ['text-align'];
        }

        if (preg_match('/^fw-/', $lop) === 1) {
            return ['font-weight'];
        }

        if (preg_match('/^([wh])-(?:25|50|75|100|auto)$/', $lop, $m) === 1) {
            return [$m[1] === 'w' ? 'width' : 'height'];
        }

        return [];
    }

    /** Thuộc tính không có cạnh, tên giống nhau ở cả hai hệ. @return list<string> */
    private static function chung(string $than): array
    {
        return match (true) {
            (bool) preg_match('/^(?:block|flex|inline|inline-block|inline-flex|grid|hidden|table|contents)$/', $than) => ['display'],
            (bool) preg_match('/^text-(?:left|center|right|justify)$/', $than) => ['text-align'],
            (bool) preg_match('/^font-(?:thin|light|normal|medium|semibold|bold|extrabold|black)$/', $than) => ['font-weight'],
            (bool) preg_match('/^rounded(?:-|$)/', $than) => ['border-radius'],
            (bool) preg_match('/^(?:static|relative|absolute|fixed|sticky)$/', $than) => ['position'],
            (bool) preg_match('/^shadow(?:-|$)/', $than) => ['box-shadow'],
            // `tw:bg-*` phải khai là nền, nếu không `<x-ui.card-header class="tw:bg-white">`
            // sẽ ĐỨNG CẠNH lớp nền của component thay vì thay nó, và ai thắng lại phụ thuộc
            // thứ tự trong tệp CSS. Bản Bootstrap (`bg-white`) không cần vì nó `!important`
            // nên luôn thắng — chính chỗ này là thứ dễ hỏng âm thầm khi quy đổi sang `tw:`.
            (bool) preg_match('/^bg-/', $than) => ['background-color'],
            // `tw:align-*` = vertical-align. Thiếu dòng này thì `<x-ui.table class="tw:align-middle">`
            // đứng CẠNH `tw:align-top` của component thay vì thay nó, và thứ tự tệp CSS quyết định
            // ai thắng — đo thật: 11 ô `vertical-align` của cả một bảng sai, nhìn thấy được.
            // Neo `^align-` để biến thể như `tw:[&>thead]:align-bottom` không lọt vào đây.
            (bool) preg_match('/^align-/', $than) => ['vertical-align'],
            (bool) preg_match('/^w-/', $than) => ['width'],
            (bool) preg_match('/^h-/', $than) => ['height'],
            default => self::vienHoacChu($than),
        };
    }

    /**
     * `border-*` và `text-*` mỗi cái gánh nhiều thuộc tính khác nhau, phải tách.
     *
     * `tw:border` là ĐỘ DÀY, `tw:border-solid` là KIỂU, `tw:border-[#9eeaf9]` là
     * MÀU. Gộp chung thì `<x-ui.alert class="tw:border-0">` sẽ nuốt luôn màu viền
     * của biến thể. Tương tự `tw:text-[0.875em]` là cỡ chữ còn `tw:text-[#58151c]`
     * là màu — phân biệt bằng chính giá trị trong ngoặc.
     *
     * @return list<string>
     */
    private static function vienHoacChu(string $than): array
    {
        if (preg_match('/^border-(?:solid|dashed|dotted|double|hidden|none)$/', $than) === 1) {
            return ['border-style'];
        }

        if (preg_match('/^border(?:-[trblxyse])?(?:-\d+)?$/', $than) === 1) {
            return ['border-width'];
        }

        if (preg_match('/^border-/', $than) === 1) {
            return ['border-color'];
        }

        if (preg_match('/^text-\[(.+)\]$/', $than, $m) === 1) {
            return self::laMau($m[1]) ? ['color'] : ['font-size'];
        }

        if (preg_match('/^text-(?:xs|sm|base|lg|xl|\d?xl)$/', $than) === 1) {
            return ['font-size'];
        }

        if (preg_match('/^text-/', $than) === 1) {
            return ['color'];
        }

        return [];
    }

    /** Giá trị tuỳ ý trong `[...]`: là màu hay là kích thước. */
    private static function laMau(string $giaTri): bool
    {
        return str_starts_with($giaTri, '#')
            || (bool) preg_match('/^(?:rgb|hsl|oklch|color)a?\(/', $giaTri)
            || in_array($giaTri, ['transparent', 'currentColor', 'inherit'], true);
    }
}
