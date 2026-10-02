<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\DTOs\Hr\EmployeeCard;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Services\Hr\EmployeeDirectoryService;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * {@see EmployeeDirectoryService} thay 11 khối `@php` của hr/employees/index (2026-09-07) và
 * nhận luôn phần gom nhóm phòng ban từ controller. Model dựng trong bộ nhớ (setRelation), không
 * chạm DB — chỉ cần app cho asset().
 */
final class EmployeeDirectoryServiceTest extends TestCase
{
    private EmployeeDirectoryService $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = new EmployeeDirectoryService;
    }

    public function test_card_anh_dai_dien_theo_ba_dang_duong_dan(): void
    {
        $this->assertSame('https://cdn.example.test/a.png', $this->card(avatar: 'https://cdn.example.test/a.png')->avatarUrl);
        $this->assertSame(asset('storage/avatars/mk.png'), $this->card(avatar: '/storage/avatars/mk.png')->avatarUrl);
        $this->assertSame(asset('storage/avatars/vu.png'), $this->card(avatar: 'storage/avatars/vu.png')->avatarUrl);
        $this->assertSame(asset('storage/avatars/ky.jpg'), $this->card(avatar: '/avatars/ky.jpg')->avatarUrl);
        $this->assertNull($this->card(avatar: '  ')->avatarUrl);
        $this->assertNull($this->card(avatar: null)->avatarUrl);
    }

    public function test_card_chu_tat_ten_va_du_phong(): void
    {
        $this->assertSame('TK', $this->card(name: 'Trần Văn Kỹ')->initials);
        $this->assertSame('VV', $this->card(name: 'Vũ')->initials, 'tên một từ lặp chữ đầu');
        $this->assertSame('NV', $this->card(name: ' ')->initials, 'tên rỗng → chữ dự phòng');
        $this->assertSame('GD', $this->directory->card($this->user(name: ''), 'Ban giám đốc', true, 'GD')->initials);
    }

    public function test_card_chu_phong_ban_ghep_phong_nhom_va_nhom_suy_ra(): void
    {
        $this->assertSame('Marketing, Marketing & Sales', $this->card(department: 'Marketing', position: 'Nhân viên Marketing', group: 'Marketing')->departmentText);
        $this->assertSame('Marketing, Marketing & Sales', $this->card(department: 'Marketing', position: 'Nhân viên Marketing', group: 'Marketing & Sales')->departmentText);
        $this->assertSame('Kỹ thuật', $this->card(department: 'Kỹ thuật', position: 'Trưởng phòng Kỹ thuật', group: 'Ban giám đốc')->departmentText, 'nhóm Ban giám đốc không ghép');
        $this->assertSame('Kế toán & Kho', $this->card(role: 'warehouse')->departmentText, 'role kho suy ra nhóm');
        $this->assertSame('Chưa gán phòng ban', $this->card()->departmentText);
        $this->assertSame('Chưa gán phòng ban', $this->card(group: 'Chưa gán phòng ban')->departmentText);
    }

    public function test_card_chuc_vu_luong_va_dien_thoai(): void
    {
        $card = $this->card(position: 'Thực tập sinh Marketing', internship: 3000000, official: 20000000);
        $this->assertSame(['Lương thực tập', '3.000.000 đ'], [$card->salaryLabel, $card->salaryText]);
        $card = $this->card(position: 'Nhân viên thử việc', probation: 8000000);
        $this->assertSame(['Lương thử việc', '8.000.000 đ'], [$card->salaryLabel, $card->salaryText]);
        $card = $this->card(position: 'Kỹ sư', official: 12000000.4);
        $this->assertSame(['Lương chính thức', '12.000.000 đ'], [$card->salaryLabel, $card->salaryText]);
        $this->assertSame('—', $this->card()->salaryText);
        $this->assertSame('Chưa gán chức vụ', $this->card()->positionText);
        $this->assertSame('Chưa cập nhật số điện thoại', $this->card()->phoneText);
        $this->assertSame('0900000002', $this->card(phone: '0900000002')->phoneText);
        $this->assertFalse($this->card(active: 0)->active);
    }

    public function test_gom_nhom_phong_ban_uu_tien_va_chon_truong_nhom(): void
    {
        $director = $this->user(id: 1, name: 'Nguoi Dung Canh', department: 'Ban giám đốc', position: 'Giám đốc điều hành', role: 'admin');
        $lead = $this->user(id: 2, name: 'Trần Văn Kỹ', department: 'Kỹ thuật', position: 'Trưởng phòng Kỹ thuật');
        $engineer = $this->user(id: 3, name: 'An Kỹ Sư', department: 'Kỹ thuật', position: 'Kỹ sư');
        $marketer = $this->user(id: 4, name: 'Lê Thị Marketing', department: 'Marketing', position: 'Nhân viên Marketing');
        $keeper = $this->user(id: 5, name: 'Đỗ Kho', department: 'Kho vận', position: 'Thủ kho');
        $nobody = $this->user(id: 6, name: 'Hoàng Không Phòng');
        $left = $this->user(id: 7, name: 'Mai Nghỉ', department: 'Kỹ thuật', active: 0);
        $all = collect([$engineer, $lead, $marketer, $keeper, $nobody, $director, $left]);

        $board = $this->directory->boardUsers($all->where('is_active', 1)->values());
        $this->assertSame([1], $board->pluck('id')->all());

        $groups = $this->directory->departmentGroups($all, $board);
        $this->assertSame(
            ['Marketing & Sales', 'Kế toán & Kho', 'Ban giám đốc', 'Kỹ thuật', 'Chưa gán phòng ban'],
            $groups->pluck('name')->all(),
            'phòng "Marketing"/"Kho vận" gộp vào nhóm chung; nhóm gộp trước, phòng thật theo tên, chưa gán cuối; người nghỉ không vào nhóm',
        );
        $technical = $groups->firstWhere('name', 'Kỹ thuật');
        $this->assertSame([2, 3], $technical['members']->pluck('id')->all(), 'trưởng phòng xếp trước');
        $this->assertSame(2, $technical['leader']->id);
        $this->assertSame([4], $groups->firstWhere('name', 'Marketing & Sales')['members']->pluck('id')->all());
        $this->assertSame([5], $groups->firstWhere('name', 'Kế toán & Kho')['members']->pluck('id')->all(), 'phòng "Kho vận" suy ra nhóm Kế toán & Kho');
        $this->assertSame(6, $groups->firstWhere('name', 'Chưa gán phòng ban')['leader']->id, 'không ai có dáng trưởng nhóm → người đầu');

        $data = $this->directory->viewData($all);
        $this->assertContainsOnlyInstancesOf(EmployeeCard::class, $data['boardCards']);
        $this->assertTrue($data['boardCards'][0]->isLeader);
        $cards = $data['departmentGroups']->firstWhere('name', 'Kỹ thuật')['cards'];
        $this->assertSame([true, false], $cards->map(fn (EmployeeCard $card) => $card->isLeader)->all());
        $this->assertSame('Kỹ thuật', $cards[1]->departmentText);
        $this->assertSame(['Mai Nghỉ'], $data['inactiveCards']->pluck('name')->all());
        $this->assertFalse($data['inactiveCards'][0]->active);
    }

    private function card(
        ?string $name = 'Nguyễn Văn A',
        ?string $avatar = null,
        ?string $department = null,
        ?string $position = null,
        ?string $role = null,
        ?string $group = null,
        ?string $phone = null,
        int $active = 1,
        mixed $official = null,
        mixed $probation = null,
        mixed $internship = null,
    ): EmployeeCard {
        $user = $this->user(1, $name, $department, $position, $role, $active, $avatar, $phone, $official, $probation, $internship);

        return $this->directory->card($user, $group, false, 'NV');
    }

    /** User dựng trong bộ nhớ với quan hệ gắn sẵn — không chạm DB. */
    private function user(
        int $id = 1,
        ?string $name = 'Nguyễn Văn A',
        ?string $department = null,
        ?string $position = null,
        ?string $role = null,
        int $active = 1,
        ?string $avatar = null,
        ?string $phone = null,
        mixed $official = null,
        mixed $probation = null,
        mixed $internship = null,
    ): User {
        // forceFill: avatar/lương/is_active không nằm trong $fillable của User.
        $user = (new User)->forceFill([
            'id' => $id, 'name' => $name, 'email' => "u{$id}@example.test", 'phone_number' => $phone, 'is_active' => $active, 'avatar' => $avatar,
            'official_salary' => $official, 'probation_salary' => $probation, 'internship_salary' => $internship,
        ]);
        $user->setRelation('department', $department === null ? null : new Department(['name' => $department]));
        $user->setRelation('position', $position === null ? null : new Position(['name' => $position]));
        $user->setRelation('roles', new Collection($role === null ? [] : [new Role(['name' => $role, 'guard_name' => 'web'])]));

        return $user;
    }
}
