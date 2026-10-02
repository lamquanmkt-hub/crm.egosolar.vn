<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MoneyParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Đặc tả parser tiền tệ dùng chung cho luồng đơn hàng.
 */
final class MoneyParserTest extends TestCase
{
    #[DataProvider('moneyProvider')]
    public function test_parses_money_values(mixed $input, float $expected): void
    {
        $this->assertSame($expected, MoneyParser::parse($input));
    }

    /**
     * @return iterable<string, array{mixed, float}>
     */
    public static function moneyProvider(): iterable
    {
        yield 'số nguyên' => [1500, 1500.0];
        yield 'số thực' => [1500.5, 1500.5];
        yield 'chuỗi rỗng' => ['', 0.0];
        yield 'null' => [null, 0.0];
        yield 'mảng' => [[1, 2], 0.0];

        // Định dạng Việt Nam — đây là ca mà parser cũ của OrderRequest trả 0
        // và làm mất giá dòng hàng khi lưu đơn.
        yield 'ngăn cách hàng nghìn kiểu VN' => ['1.234.567', 1234567.0];
        yield 'kèm đơn vị đ' => ['1.234.567 đ', 1234567.0];
        yield 'kèm VNĐ' => ['2.000.000 VNĐ', 2000000.0];
        yield 'kèm khoảng trắng cứng' => ["1.000.000\xc2\xa0đ", 1000000.0];

        // Định dạng Anh–Mỹ
        yield 'ngăn cách hàng nghìn kiểu Anh' => ['1,234,567', 1234567.0];
        yield 'thập phân kiểu Anh' => ['1,234.56', 1234.56];

        // Thập phân kiểu VN
        yield 'thập phân dấu phẩy' => ['1,5', 1.5];
        yield 'thập phân dấu chấm' => ['1.5', 1.5];

        yield 'số âm' => ['-1.000.000', -1000000.0];
        yield 'chỉ có dấu trừ' => ['-', 0.0];
        yield 'rác' => ['abc', 0.0];
    }

    /** Số lượng luôn là số nguyên và không nhỏ hơn giá trị tối thiểu. */
    public function test_parse_quantity(): void
    {
        $this->assertSame(3, MoneyParser::parseQuantity('3'));
        $this->assertSame(1, MoneyParser::parseQuantity('0', minimum: 1));
        $this->assertSame(1, MoneyParser::parseQuantity('abc', minimum: 1));
        $this->assertSame(1234, MoneyParser::parseQuantity('1.234'));
    }

    /** Phần trăm bị kẹp trong khoảng cho phép. */
    public function test_parse_percent_is_clamped(): void
    {
        $this->assertSame(10.0, MoneyParser::parsePercent('10'));
        $this->assertSame(100.0, MoneyParser::parsePercent('150'));
        $this->assertSame(0.0, MoneyParser::parsePercent('-5'));
    }
}
