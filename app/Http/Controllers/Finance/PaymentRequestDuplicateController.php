<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Contracts\Repositories\PaymentRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Finance\PaymentRequestDuplicator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sao chép một ĐNTT thành phiếu nháp mới (`POST /payment-requests/{id}/ban-sao`).
 *
 * Trước đây là closure 114 dòng trong `routes/finance.php`. Toàn bộ luật sao
 * chép nằm ở {@see PaymentRequestDuplicator}; controller chỉ còn việc kiểm tra
 * phiếu gốc và điều hướng.
 */
final class PaymentRequestDuplicateController extends Controller
{
    public function __construct(
        private readonly PaymentRequestRepositoryInterface $paymentRequests,
        private readonly PaymentRequestDuplicator $duplicator,
    ) {}

    public function __invoke(Request $request, int $id): RedirectResponse
    {
        $source = $this->paymentRequests->find($id);

        abort_if($source === null, 404);

        $newId = $this->duplicator->duplicate($source, $request->user()?->id);

        return redirect('/payment-requests/'.$newId.'/edit')
            ->with('success', 'Đã sao chép phiếu mới thành công.');
    }
}
