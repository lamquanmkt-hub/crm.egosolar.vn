<?php

declare(strict_types=1);

namespace App\View\Presenters\Hr;

/**
 * Chuẩn bị trang tuyển dụng (`hr/recruitment/index`) — một view dùng chung cho 8 tab.
 *
 * Thay ba khối `@php`: khối đầu khai ba bản đồ màu **không ai dùng** (xem ghi chú dưới) cùng một
 * giá trị mặc định thừa cho `$active`; hai khối sau là hai phép lọc nằm trong nhánh `@if` của tab
 * "Tiếp nhận" và "Lưu hồ sơ".
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 */
final class RecruitmentPagePresenter
{
    /**
     * Thư mời được coi là "đang tiếp nhận" khi ở một trong hai trạng thái này, HOẶC đã có bất kỳ
     * ghi chú tiến độ tiếp nhận nào (`onboarding_status`) — kể cả khi status còn là `draft`.
     */
    private const ONBOARDING_STATUSES = ['accepted', 'onboarded'];

    /** Ứng viên vào khu "Lưu hồ sơ" khi đã kết thúc theo một trong bốn cách này. */
    private const ARCHIVE_STATUSES = ['archived', 'rejected', 'not_fit', 'interview_failed'];

    /**
     * @param  iterable<object>  $offers  các dòng `hr_recruitment_offers` đã join tên ứng viên
     * @param  iterable<object>  $candidates  các dòng `hr_recruitment_candidates`
     * @return array{onboardingOffers: list<object>, onboardingOfferCount: int, archiveCandidates: list<object>, archiveCandidateCount: int}
     */
    public function viewData(iterable $offers, iterable $candidates): array
    {
        $onboarding = [];
        foreach ($offers as $offer) {
            if (in_array($offer->status ?? '', self::ONBOARDING_STATUSES, true)
                || ! empty($offer->onboarding_status)) {
                $onboarding[] = $offer;
            }
        }

        $archived = [];
        foreach ($candidates as $candidate) {
            if (in_array($candidate->status ?? '', self::ARCHIVE_STATUSES, true)) {
                $archived[] = $candidate;
            }
        }

        return [
            'onboardingOffers' => $onboarding,
            'onboardingOfferCount' => count($onboarding),
            'archiveCandidates' => $archived,
            'archiveCandidateCount' => count($archived),
        ];
    }
}
