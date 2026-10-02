<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\DTOs\Hr\EmployeeCard;
use App\Models\User;
use App\Support\DisplayFormat;
use Illuminate\Support\Collection;

/**
 * Danh bạ nhân sự cho trang `hr.employees.index` (và `org-chart`, cùng dữ liệu).
 *
 * Gom hai việc trước đây tách đôi và lặp nhau: controller xếp nhân viên vào nhóm phòng ban
 * (Ban giám đốc, Marketing & Sales, Kế toán & Kho, từng phòng, Chưa gán) còn view tự tính lại
 * chữ "Phòng ban" của card bằng cùng bộ từ khoá. Nay một chỗ quyết định thế nào là
 * marketing/sales, kế toán/kho, ban giám đốc, trưởng nhóm — và dựng {@see EmployeeCard}.
 */
final class EmployeeDirectoryService
{
    private const BOARD_GROUP = 'Ban giám đốc';

    private const UNASSIGNED_GROUP = 'Chưa gán phòng ban';

    private const INACTIVE_GROUP = 'Đã ngừng hợp tác';

    private const BOARD_ROLES = ['admin', 'management', 'super-admin', 'director'];

    private const LEADER_ROLES = ['admin', 'management', 'sales_manager', 'manager', 'leader', 'lead'];

    private const GROUP_PRIORITY = ['marketing-sales' => 1, 'accounting-warehouse' => 2, 'unassigned' => 99];

    /**
     * Thứ tự dò ảnh đại diện. Cột `users.avatar` che quan hệ `avatar()` (MorphOne) khi đọc
     * bằng data_get, nên thực tế chỉ `avatar` (đường dẫn/URL) có tác dụng; giữ danh sách cũ
     * để hành vi không đổi.
     */
    private const AVATAR_CANDIDATES = [
        'avatar.url', 'avatar.file_url', 'avatar.path', 'avatar',
        'photo', 'photo_url', 'profile_photo_url', 'image', 'image_url',
    ];

    /**
     * Ba khu của trang: ban giám đốc, từng nhóm phòng ban, và người đã ngừng hợp tác — mỗi khu
     * là danh sách {@see EmployeeCard}. Nhóm phòng ban trùng tên "Ban giám đốc" vẫn được trả về
     * (view bỏ qua khi in) để `@forelse` không rơi vào nhánh rỗng khi chỉ còn nhóm đó.
     *
     * @param  Collection<int, User>  $employees  đã lọc theo form, đã nạp department/position/roles
     * @return array{boardCards: Collection<int, EmployeeCard>, departmentGroups: Collection<int, array<string, mixed>>, inactiveCards: Collection<int, EmployeeCard>}
     */
    public function viewData(Collection $employees): array
    {
        $active = $employees->filter(fn (User $user) => (int) $user->is_active === 1)->values();
        $inactive = $employees->filter(fn (User $user) => (int) $user->is_active !== 1)->values();
        $board = $this->boardUsers($active);

        return [
            'boardCards' => $board->map(fn (User $user) => $this->card($user, self::BOARD_GROUP, true, 'GD')),
            'departmentGroups' => $this->departmentGroups($active, $board)
                ->map(fn (array $group) => [
                    'key' => $group['key'],
                    'name' => $group['name'],
                    'subtitle' => $group['subtitle'],
                    'leader' => $group['leader'],
                    'cards' => $group['members']->map(fn (User $user) => $this->card(
                        $user,
                        $group['name'],
                        $group['leader'] !== null && $group['leader']->id === $user->id,
                        'NV',
                    )),
                ])
                ->values(),
            'inactiveCards' => $inactive->map(fn (User $user) => $this->card($user, null, false, 'NV')),
        ];
    }

    /**
     * Ban giám đốc: theo role, chức vụ hoặc phòng ban; người có dáng trưởng nhóm xếp trước.
     *
     * @param  Collection<int, User>  $activeEmployees
     * @return Collection<int, User>
     */
    public function boardUsers(Collection $activeEmployees): Collection
    {
        return $activeEmployees
            ->filter(fn (User $user) => $this->isBoardMember($user))
            ->sortBy(fn (User $user) => [
                ! $this->isLikelyLeader($user),
                strtolower($user->name ?? ''),
            ])
            ->values();
    }

    /**
     * Gom nhân viên đang hoạt động thành các nhóm phòng ban (gộp Marketing & Sales, Kế toán & Kho)
     * kèm leader; một người thuộc nhiều nhóm nếu phòng ban/chức vụ/role gợi ý nhiều nhóm.
     *
     * @param  Collection<int, User>  $employees
     * @param  Collection<int, User>|null  $boardUsers  ban giám đốc — xếp đầu nhóm
     * @return Collection<int, array{key: string, name: string, subtitle: string, leader: User|null, members: Collection<int, User>}>
     */
    public function departmentGroups(Collection $employees, ?Collection $boardUsers = null): Collection
    {
        $grouped = [];
        $boardUserIds = collect($boardUsers)->pluck('id')->all();

        foreach ($employees as $employee) {
            if ((int) $employee->is_active !== 1) {
                continue;
            }

            $departmentNames = $this->extractDepartmentNames($employee);
            if (empty($departmentNames)) {
                $departmentNames = [self::UNASSIGNED_GROUP];
            }

            foreach ($departmentNames as $departmentName) {
                $normalized = mb_strtolower(trim($departmentName));

                if ($this->isMarketingSales($normalized)) {
                    $key = 'marketing-sales';
                    $name = 'Marketing & Sales';
                    $subtitle = '1 trưởng nhóm chung, 2 nhánh nhân sự: Marketing và Sales';
                } elseif ($this->isAccountingWarehouse($normalized)) {
                    $key = 'accounting-warehouse';
                    $name = 'Kế toán & Kho';
                    $subtitle = 'Nhóm kế toán, tài chính nội bộ và kho vận';
                } elseif ($normalized === 'chưa gán phòng ban') {
                    $key = 'unassigned';
                    $name = self::UNASSIGNED_GROUP;
                    $subtitle = 'Nhân sự chưa được phân về bộ phận cụ thể';
                } else {
                    $key = 'department-'.md5($normalized);
                    $name = $departmentName;
                    $subtitle = 'Nhân sự thuộc '.$departmentName;
                }

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'key' => $key,
                        'name' => $name,
                        'subtitle' => $subtitle,
                        'members' => collect(),
                        'leader' => null,
                    ];
                }

                if (! $grouped[$key]['members']->contains(fn ($member) => $member->id === $employee->id)) {
                    $grouped[$key]['members']->push($employee);
                }
            }
        }

        return collect($grouped)
            ->map(function (array $group) use ($boardUserIds) {
                $members = $group['members']
                    ->sortBy(fn (User $user) => [
                        in_array($user->id, $boardUserIds, true) ? 0 : 1,
                        ! $this->isLikelyLeader($user),
                        strtolower($user->name ?? ''),
                    ])
                    ->values();

                $leader = $members->first(fn (User $user) => $this->isLikelyLeader($user))
                    ?? $members->first();

                return [
                    'key' => $group['key'],
                    'name' => $group['name'],
                    'subtitle' => $group['subtitle'],
                    'leader' => $leader,
                    'members' => $members,
                ];
            })
            ->sortBy(fn (array $group) => [
                self::GROUP_PRIORITY[$group['key']] ?? 10,
                strtolower($group['name']),
            ])
            ->values();
    }

    /**
     * Card một nhân sự: ảnh/chữ tắt, chữ phòng ban (phòng + nhóm đang đứng + nhóm suy ra),
     * chức vụ, lương hiện hành theo loại chức vụ.
     *
     * @param  string|null  $groupName  tên nhóm card đang đứng trong, ghép vào chữ phòng ban nếu là phòng thật
     * @param  string  $initialsFallback  chữ hiện khi tên rỗng (GD ban giám đốc, NV còn lại)
     */
    public function card(User $employee, ?string $groupName, bool $isLeader, string $initialsFallback): EmployeeCard
    {
        [$salaryLabel, $salary] = $this->currentSalary($employee);

        return new EmployeeCard(
            id: (int) $employee->id,
            name: (string) $employee->name,
            email: $employee->email,
            phoneText: $employee->phone_number ?: 'Chưa cập nhật số điện thoại',
            active: (int) $employee->is_active === 1,
            isLeader: $isLeader,
            avatarUrl: $this->avatarUrl($employee),
            initials: $this->initials($employee->name) ?: $initialsFallback,
            departmentText: $this->departmentText($employee, $groupName),
            positionText: $this->positionText($employee),
            salaryLabel: $salaryLabel,
            salaryText: $salary !== null ? DisplayFormat::money($salary) : '—',
        );
    }

    /** Suy ra danh sách tên phòng ban của user từ department, position và role. */
    private function extractDepartmentNames(User $user): array
    {
        $names = [];

        if (! empty(optional($user->department)->name)) {
            $names[] = trim($user->department->name);
        }

        $positionName = mb_strtolower(trim((string) optional($user->position)->name));
        $roleName = mb_strtolower(trim((string) optional($user->roles->first())->name));
        $departmentName = mb_strtolower(trim((string) optional($user->department)->name));

        if ($this->isMarketingSales($positionName) || $this->isMarketingSales($roleName) || $this->isMarketingSales($departmentName)) {
            $names[] = 'Marketing & Sales';
        }

        if ($this->isAccountingWarehouse($positionName) || $this->isAccountingWarehouse($roleName) || $this->isAccountingWarehouse($departmentName)) {
            $names[] = 'Kế toán & Kho';
        }

        return array_values(array_unique(array_filter($names)));
    }

    /**
     * Chữ "Phòng ban" trên card: phòng thật + nhóm đang đứng (nếu là phòng thật) + nhóm suy ra
     * từ chức vụ/role. Không có gì thì "Chưa gán phòng ban".
     */
    private function departmentText(User $employee, ?string $groupName): string
    {
        $departments = [];

        if (! empty(optional($employee->department)->name)) {
            $departments[] = trim($employee->department->name);
        }

        if (! empty($groupName) && ! in_array($groupName, [self::BOARD_GROUP, self::UNASSIGNED_GROUP, self::INACTIVE_GROUP], true)) {
            $departments[] = trim($groupName);
        }

        $positionName = mb_strtolower(trim((string) optional($employee->position)->name));
        $roleName = mb_strtolower(trim((string) optional($employee->roles->first())->name));

        if ($this->isMarketingSales($positionName) || $this->isMarketingSales($roleName)) {
            $departments[] = 'Marketing & Sales';
        }

        if ($this->isAccountingWarehouse($positionName) || $this->isAccountingWarehouse($roleName)) {
            $departments[] = 'Kế toán & Kho';
        }

        $departments = array_values(array_unique(array_filter($departments)));

        return count($departments) ? implode(', ', $departments) : self::UNASSIGNED_GROUP;
    }

    private function positionText(User $employee): string
    {
        $positionText = trim((string) optional($employee->position)->name);

        return $positionText !== '' ? $positionText : 'Chưa gán chức vụ';
    }

    /**
     * Lương hiện hành theo loại chức vụ: thực tập → internship_salary, thử việc → probation_salary,
     * còn lại official_salary.
     *
     * @return array{0: string, 1: mixed}
     */
    private function currentSalary(User $employee): array
    {
        $positionName = mb_strtolower((string) optional($employee->position)->name);

        if (str_contains($positionName, 'thực tập') || str_contains($positionName, 'thuc tap') || str_contains($positionName, 'intern')) {
            return ['Lương thực tập', $employee->internship_salary ?? null];
        }
        if (str_contains($positionName, 'thử việc') || str_contains($positionName, 'thu viec') || str_contains($positionName, 'probation')) {
            return ['Lương thử việc', $employee->probation_salary ?? null];
        }

        return ['Lương chính thức', $employee->official_salary ?? null];
    }

    /** URL tuyệt đối giữ nguyên; đường dẫn tương đối quy về `storage/…` của asset(). */
    private function avatarUrl(User $employee): ?string
    {
        foreach (self::AVATAR_CANDIDATES as $path) {
            $candidate = data_get($employee, $path);
            if (! $candidate || ! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
                return $candidate;
            }
            if (str_starts_with($candidate, '/storage/')) {
                return asset(ltrim($candidate, '/'));
            }
            if (str_starts_with($candidate, 'storage/')) {
                return asset($candidate);
            }

            return asset('storage/'.ltrim($candidate, '/'));
        }

        return null;
    }

    /** Chữ cái đầu của từ đầu và từ cuối, in hoa; tên một từ thì lặp chữ đầu. */
    private function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];
        $first = $words[0] ?? '';
        $last = count($words) > 1 ? $words[count($words) - 1] : '';

        return mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last ?: $first, 0, 1));
    }

    /** Ban giám đốc theo role, chức vụ hoặc phòng ban. */
    private function isBoardMember(User $user): bool
    {
        $role = mb_strtolower((string) optional($user->roles->first())->name);
        $position = mb_strtolower((string) optional($user->position)->name);
        $department = mb_strtolower((string) optional($user->department)->name);

        return in_array($role, self::BOARD_ROLES, true)
            || str_contains($position, 'giám đốc')
            || str_contains($position, 'director')
            || str_contains($position, 'ceo')
            || str_contains($position, 'founder')
            || str_contains($department, 'ban giám đốc');
    }

    /** Có dáng trưởng nhóm / quản lý theo role hoặc tên chức vụ. */
    private function isLikelyLeader(User $user): bool
    {
        $role = mb_strtolower((string) optional($user->roles->first())->name);
        $position = mb_strtolower((string) optional($user->position)->name);

        return in_array($role, self::LEADER_ROLES, true)
            || str_contains($position, 'trưởng')
            || str_contains($position, 'phó')
            || str_contains($position, 'manager')
            || str_contains($position, 'lead')
            || str_contains($position, 'head')
            || str_contains($position, 'giám đốc');
    }

    private function isMarketingSales(string $text): bool
    {
        return str_contains($text, 'marketing')
            || str_contains($text, 'sale')
            || str_contains($text, 'sales')
            || str_contains($text, 'kinh doanh');
    }

    private function isAccountingWarehouse(string $text): bool
    {
        return str_contains($text, 'kế toán')
            || str_contains($text, 'ke toan')
            || str_contains($text, 'accounting')
            || str_contains($text, 'kho')
            || str_contains($text, 'warehouse');
    }
}
