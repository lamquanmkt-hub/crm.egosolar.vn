<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\View\Presenters\Hr\RecruitmentPagePresenter;
use Tests\TestCase;

/**
 * RecruitmentPagePresenter thay 3 khối `@php` của trang tuyển dụng (2026-09-28).
 *
 * Khối đầu khai ba bản đồ màu KHÔNG ai dùng (mỗi biến chỉ xuất hiện đúng một lần — ở chỗ gán) và
 * một giá trị mặc định thừa cho `$active` (controller luôn truyền). Hai khối sau là hai phép lọc.
 */
final class RecruitmentPagePresenterTest extends TestCase
{
    private const VIEW = 'resources/views/hr/recruitment/index.blade.php';

    private function offer(string $status, ?string $onboarding = null): object
    {
        return (object) ['id' => 1, 'status' => $status, 'onboarding_status' => $onboarding];
    }

    private function candidate(string $status): object
    {
        return (object) ['id' => 1, 'full_name' => 'Ứng viên', 'status' => $status];
    }

    public function test_view_khong_con_php_va_ba_bang_mau_chet_da_bi_xoa(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $source);
        // Ba bản đồ này chưa bao giờ được dùng; nếu ai đó đưa lại thì phải dùng thật.
        $this->assertStringNotContainsString('$statusPill', $source);
        $this->assertStringNotContainsString('$interviewPill', $source);
        $this->assertStringNotContainsString('$offerPill', $source);
    }

    public function test_loc_thu_moi_dang_tiep_nhan(): void
    {
        $accepted = $this->offer('accepted');
        $onboarded = $this->offer('onboarded');
        // status không thuộc danh sách NHƯNG đã có ghi chú tiến độ -> vẫn tính
        $draftWithProgress = $this->offer('draft', 'đang chuẩn bị');
        $draft = $this->offer('draft');
        $rejected = $this->offer('rejected');

        $data = (new RecruitmentPagePresenter)->viewData(
            [$accepted, $onboarded, $draftWithProgress, $draft, $rejected],
            []
        );

        $this->assertSame([$accepted, $onboarded, $draftWithProgress], $data['onboardingOffers']);
        $this->assertSame(3, $data['onboardingOfferCount']);
    }

    public function test_ghi_chu_tien_do_rong_thi_khong_tinh(): void
    {
        // Bản cũ dùng `! empty(...)` nên chuỗi rỗng và '0' đều KHÔNG tính.
        $data = (new RecruitmentPagePresenter)->viewData([
            $this->offer('draft', ''),
            $this->offer('draft', '0'),
            $this->offer('draft', null),
        ], []);

        $this->assertSame([], $data['onboardingOffers']);
        $this->assertSame(0, $data['onboardingOfferCount']);
    }

    public function test_loc_ung_vien_luu_ho_so(): void
    {
        $archived = $this->candidate('archived');
        $rejected = $this->candidate('rejected');
        $notFit = $this->candidate('not_fit');
        $failed = $this->candidate('interview_failed');
        $new = $this->candidate('new');
        $hired = $this->candidate('hired');

        $data = (new RecruitmentPagePresenter)->viewData(
            [],
            [$archived, $rejected, $notFit, $failed, $new, $hired]
        );

        $this->assertSame([$archived, $rejected, $notFit, $failed], $data['archiveCandidates']);
        $this->assertSame(4, $data['archiveCandidateCount']);
        $this->assertNotContains($new, $data['archiveCandidates']);
        $this->assertNotContains($hired, $data['archiveCandidates']);
    }

    public function test_danh_sach_rong(): void
    {
        $data = (new RecruitmentPagePresenter)->viewData([], []);

        $this->assertSame([], $data['onboardingOffers']);
        $this->assertSame(0, $data['onboardingOfferCount']);
        $this->assertSame([], $data['archiveCandidates']);
        $this->assertSame(0, $data['archiveCandidateCount']);
    }
}
