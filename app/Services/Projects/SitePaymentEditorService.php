<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Support\DisplayFormat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dữ liệu cho hộp thoại "Sửa thanh toán" ở trang chi tiết công trình.
 *
 * ## Vì sao tách khỏi Blade
 * Trước đây là 74 dòng `@php` ở giữa `sites/show.blade.php` (một tệp 2.100 dòng):
 * hai câu truy vấn có `leftJoin`, rồi định dạng tiền, ngày, tên người tạo ngay
 * trong view. Nằm lẫn giữa HTML nên vừa khó thấy vừa không test được.
 *
 * Trả về dữ liệu ĐÃ ĐỊNH DẠNG SẴN vì view chỉ đổ thẳng vào `@json()` cho JS dùng.
 */
final class SitePaymentEditorService
{
    /** Nhãn hình thức thanh toán. Mã lạ thì hiện nguyên mã, không nuốt. */
    public const METHOD_LABELS = [
        'cash' => 'Tiền mặt',
        'bank_transfer' => 'Chuyển khoản',
        'transfer' => 'Chuyển khoản',
        'bank' => 'Chuyển khoản',
        'card' => 'Thẻ',
        'other' => 'Khác',
    ];

    /**
     * @return array{egoFixSiteId: int, egoFixPayments: Collection<int, array<string, mixed>>, egoFixTermOptions: Collection<int, array<string, mixed>>}
     */
    public function viewData(int $siteId): array
    {
        if ($siteId <= 0) {
            return [
                'egoFixSiteId' => 0,
                'egoFixPayments' => collect(),
                'egoFixTermOptions' => collect(),
            ];
        }

        $terms = DB::table('site_payment_terms')
            ->where('site_id', $siteId)
            ->orderBy('id')
            ->get();

        return [
            // View dùng id này để dựng URL cho JS; trả kèm để view khỏi tự lấy lại
            // từ route rồi lệch với id đã dùng để truy vấn.
            'egoFixSiteId' => $siteId,
            'egoFixPayments' => $this->payments($siteId),
            'egoFixTermOptions' => $terms->map(fn ($t): array => [
                'id' => (int) $t->id,
                'name' => $t->name,
                'amount_text' => $this->tien((float) $t->amount),
            ])->values(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function payments(int $siteId): Collection
    {
        return DB::table('receipts as r')
            ->leftJoin('site_payment_terms as t', 't.id', '=', 'r.site_payment_term_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->where('r.site_id', $siteId)
            ->whereNotNull('r.site_payment_term_id')
            ->orderBy('r.receipt_date')
            ->orderBy('r.id')
            ->get([
                'r.id', 'r.amount', 'r.payment_method', 'r.receipt_date', 'r.created_at',
                'r.note', 'r.created_by', 'r.site_payment_term_id',
                't.name as term_name', 'u.name as creator_name', 'u.email as creator_email',
            ])
            ->map(function ($r): array {
                [$ngayValue, $ngayText] = $this->ngay($r->receipt_date ?? null);
                $method = $r->payment_method ?: 'cash';

                return [
                    'id' => (int) $r->id,
                    'term_id' => $r->site_payment_term_id ? (int) $r->site_payment_term_id : null,
                    'term_name' => $r->term_name ?: 'Chưa gắn đợt',
                    'amount' => (float) $r->amount,
                    'amount_text' => $this->tien((float) $r->amount),
                    'payment_method' => $method,
                    'payment_method_text' => self::METHOD_LABELS[$method] ?? $method,
                    'receipt_date' => $ngayValue,
                    'receipt_date_text' => $ngayText,
                    'creator_text' => $r->creator_name
                        ?: ($r->creator_email ?: ($r->created_by ? 'User #'.$r->created_by : '—')),
                    'note' => $r->note ?: '—',
                ];
            })
            ->values();
    }

    /**
     * Ngày thu: trả về [giá trị cho input date, chữ hiển thị].
     *
     * Nhánh phòng thủ ('0000-00-00', chuỗi rỗng, parse hỏng) HIỆN KHÔNG THỂ XẢY RA
     * — đã kiểm production 2026-09-05: `receipts.receipt_date` là `date NOT NULL`,
     * sql_mode có `NO_ZERO_DATE`, và 0/26 phiếu có ngày rỗng. Giữ lại vì bản trong
     * view cũng có, bỏ đi là đổi hành vi ở tình huống không kiểm chứng được: nếu
     * sql_mode nới ra hoặc có đợt nhập dữ liệu cũ thì mất nhánh này là vỡ trang
     * thay vì hiện dấu gạch ngang. Vì không dựng được dữ liệu như vậy trên DB test
     * nên nhánh này KHÔNG có test — đó là lý do, không phải sót.
     *
     * @return array{string, string}
     */
    private function ngay(mixed $tho): array
    {
        if (empty($tho) || $tho === '0000-00-00') {
            return ['', '—'];
        }

        try {
            $ngay = Carbon::parse((string) $tho);

            return [$ngay->format('Y-m-d'), $ngay->format('d/m/Y')];
        } catch (Throwable) {
            return [(string) $tho, (string) $tho];
        }
    }

    private function tien(float $so): string
    {
        return DisplayFormat::money($so);
    }
}
