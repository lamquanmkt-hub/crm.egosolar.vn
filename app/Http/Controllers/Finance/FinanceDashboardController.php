<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\CRM\Orders\Order;
use App\Models\Department;
use App\Models\Payroll;
use App\Models\User;
use App\Support\ProbeFailureLog;
use App\Support\SchemaCache;
use App\View\Presenters\Finance\FinanceDashboardPresenter;
use App\View\Presenters\Finance\SalaryDetailPresenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Dashboard tài chính: tổng quan thu chi, công nợ và bảng lương.
 */
class FinanceDashboardController extends Controller
{
    public function __construct(
        private readonly SalaryDetailPresenter $salaryDetailPresenter,
    ) {}

    /**
     * Trang tổng quan tài chính với các chỉ số thu chi, công nợ, lợi nhuận.
     */
    public function index(Request $request, FinanceDashboardPresenter $presenter)
    {
        $filters = [
            'keyword' => trim((string) $request->get('keyword', '')),
            'from_date' => $request->get('from_date'),
            'to_date' => $request->get('to_date'),
            'order_status' => $request->get('order_status', ''),
        ];

        $totalOrders = $this->getFilteredOrdersQuery($filters)->count();

        $pendingPaymentRequests = $this->getPendingPaymentRequestsCount($filters);
        $pendingDisbursement = $this->getPendingPaymentRequestsAmount($filters);

        $totalReceivableBase = $this->getDebtBaseTotal($filters);
        $collectedAmount = $this->getDebtPaidTotal($filters);
        $receivableAmount = $this->getDebtRemainTotal($filters);
        $overdueReceivables = $this->getOverdueDebtTotal($filters);

        $totalRevenue = $this->getTotalRevenue($filters);
        $totalCost = $this->getTotalCost($filters);
        $grossProfit = $totalRevenue - $totalCost;

        $grossMargin = $totalRevenue > 0
            ? round(($grossProfit / $totalRevenue) * 100, 2)
            : 0;

        $cashInPeriod = $this->getCashInPeriod($filters);
        $cashOutPeriod = $this->getCashOutPeriod($filters);
        $netCashFlow = $cashInPeriod - $cashOutPeriod;

        $collectionRate = $totalReceivableBase > 0
            ? round(($collectedAmount / $totalReceivableBase) * 100, 1)
            : 0;

        $recentOrderProfits = $this->getRecentOrderProfits($filters);
        $orderStatuses = $this->getOrderStatuses();

        // View chỉ in: mọi phép định dạng (tiền, phần trăm, ngày, lớp màu theo dấu) ở presenter.
        return view('finance.index', array_merge(
            compact('filters', 'orderStatuses'),
            $presenter->viewData(compact(
                'totalOrders',
                'pendingPaymentRequests',
                'pendingDisbursement',
                'totalReceivableBase',
                'collectedAmount',
                'receivableAmount',
                'overdueReceivables',
                'totalRevenue',
                'totalCost',
                'grossProfit',
                'grossMargin',
                'cashInPeriod',
                'cashOutPeriod',
                'netCashFlow',
                'collectionRate',
            ), $recentOrderProfits),
        ));
    }

    /**
     * Hiển thị trang đề nghị thanh toán.
     */
    public function paymentRequest()
    {
        return view('finance.payment-request');
    }

    /**
     * Bảng lương tháng: gộp chấm công, phạt đi trễ, lương kỹ thuật và payroll đã lưu.
     */
    public function salary(Request $request)
    {
        $month = $request->get('month', now()->format('Y-m'));
        $departmentId = $request->get('department_id');
        $keyword = trim((string) $request->get('keyword', ''));

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = (clone $start)->endOfMonth();

        $standardDaysAuto = $this->calculateStandardWorkdays($month);

        $employeesQuery = User::query()
            ->with(['department', 'position'])
            ->orderBy('name');

        if (SchemaCache::hasColumn('users', 'is_active')) {
            $employeesQuery->where(function ($q) {
                $q->where('is_active', 1)
                    ->orWhereNull('is_active');
            });
        }

        if (! empty($departmentId)) {
            $employeesQuery->where('department_id', $departmentId);
        }

        if (! empty($keyword)) {
            $employeesQuery->where('name', 'like', '%'.$keyword.'%');
        }

        $employees = $employeesQuery->get();

        $attendanceMap = AttendanceRecord::query()
            ->select('user_id', DB::raw('COUNT(id) as working_days'))
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('check_in_at')
            ->groupBy('user_id')
            ->pluck('working_days', 'user_id');

        $minutesMap = AttendanceRecord::query()
            ->select('user_id', DB::raw('SUM(work_minutes) as total_minutes'))
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('user_id')
            ->pluck('total_minutes', 'user_id');

        $lateCountMap = AttendanceRecord::query()
            ->select('user_id', DB::raw('COUNT(id) as late_count'))
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->where('late_minutes', '>', 0)
            ->groupBy('user_id')
            ->pluck('late_count', 'user_id');

        $attendanceSetting = AttendanceSetting::first();

        $latePenaltyPerTime = 0;

        if (
            $attendanceSetting &&
            SchemaCache::hasColumn($attendanceSetting->getTable(), 'late_penalty_per_time')
        ) {
            $latePenaltyPerTime = (int) ($attendanceSetting->late_penalty_per_time ?? 0);
        }

        $payrollMap = Payroll::query()
            ->where('payroll_month', $month)
            ->get()
            ->keyBy('user_id');

        $technicalSalaryMap = $this->getTechnicalSalaryMap($month);

        $employees->transform(function ($employee) use (
            $attendanceMap,
            $minutesMap,
            $lateCountMap,
            $latePenaltyPerTime,
            $payrollMap,
            $technicalSalaryMap,
            $standardDaysAuto
        ) {
            $payroll = $payrollMap->get($employee->id);

            $workingDaysAuto = (float) ($attendanceMap[$employee->id] ?? 0);
            $lateCount = (int) ($lateCountMap[$employee->id] ?? 0);
            $latePenaltyTotal = $lateCount * $latePenaltyPerTime;

            $technicalSalaryTotal = (float) ($technicalSalaryMap[$employee->id] ?? 0);
            $isTechnical = $this->isTechnicalEmployee($employee) || $technicalSalaryTotal > 0;

            $baseSalaryFromUser = $this->getEmployeeBaseSalary($employee);

            $employee->working_days = $payroll
                ? (float) $payroll->working_days
                : $workingDaysAuto;

            $employee->standard_days = $standardDaysAuto;

            $employee->basic_salary = $payroll
                ? (float) $payroll->basic_salary
                : $baseSalaryFromUser;

            if ($isTechnical && $technicalSalaryTotal > 0) {
                $employee->basic_salary = $technicalSalaryTotal;
            }

            $employee->allowance = $payroll ? (float) $payroll->allowance : 0;
            $employee->commission = $payroll ? (float) $payroll->commission : 0;
            $employee->bonus = $payroll ? (float) $payroll->bonus : 0;
            $employee->advance = $payroll ? (float) $payroll->advance : 0;
            $employee->other_deduction = $payroll ? (float) $payroll->other_deduction : 0;
            $employee->net_salary = $payroll ? (float) $payroll->net_salary : 0;
            $employee->salary_note = $payroll ? $payroll->note : null;
            $employee->payroll_saved = (bool) $payroll;

            $employee->late_count = $lateCount;
            $employee->late_penalty_per_time = $latePenaltyPerTime;
            $employee->late_penalty_total = $latePenaltyTotal;

            $employee->total_minutes = (int) ($minutesMap[$employee->id] ?? 0);

            $employee->is_technical_salary_auto = $isTechnical && $technicalSalaryTotal > 0;
            $employee->technical_salary_total = $technicalSalaryTotal;

            return $employee;
        });

        $departments = SchemaCache::hasTable('departments')
            ? Department::orderBy('name')->get()
            : collect();

        $savedPayrolls = $payrollMap->count();

        $totalNetSalary = $employees->sum(function ($employee) {
            return (float) ($employee->net_salary ?? 0);
        });

        $totalBasicSalary = $employees->sum(function ($employee) {
            return (float) ($employee->basic_salary ?? 0);
        });

        $totalDeduction = $employees->sum(function ($employee) {
            return (float) ($employee->other_deduction ?? 0)
                + (float) ($employee->advance ?? 0)
                + (float) ($employee->late_penalty_total ?? 0);
        });

        return view('finance.salary', [
            'employees' => $employees,
            'departments' => $departments,
            'month' => $month,
            'workingDays' => 0,
            'attendanceMap' => $attendanceMap,
            'latePenaltyPerTime' => $latePenaltyPerTime,
            'savedPayrolls' => $savedPayrolls,
            'totalNetSalary' => $totalNetSalary,
            'totalBasicSalary' => $totalBasicSalary,
            'totalDeduction' => $totalDeduction,
        ]);
    }

    /**
     * Xuất bảng lương tháng ra Excel gồm sheet tổng hợp và chi tiết từng nhân viên.
     */
    public function exportSalaryExcel(Request $request)
    {
        $month = $request->get('month', now()->format('Y-m'));
        $departmentId = $request->get('department_id');
        $keyword = trim((string) $request->get('keyword', ''));

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = (clone $start)->endOfMonth();
        $standardDaysAuto = $this->calculateStandardWorkdays($month);

        $employeesQuery = User::query()
            ->with(['department', 'position'])
            ->orderBy('name');

        if (SchemaCache::hasColumn('users', 'is_active')) {
            $employeesQuery->where(function ($q) {
                $q->where('is_active', 1)
                    ->orWhereNull('is_active');
            });
        }

        if (! empty($departmentId)) {
            $employeesQuery->where('department_id', $departmentId);
        }

        if (! empty($keyword)) {
            $employeesQuery->where('name', 'like', '%'.$keyword.'%');
        }

        $employees = $employeesQuery->get();

        $attendanceMap = AttendanceRecord::query()
            ->select('user_id', DB::raw('COUNT(id) as working_days'))
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('check_in_at')
            ->groupBy('user_id')
            ->pluck('working_days', 'user_id');

        $minutesMap = AttendanceRecord::query()
            ->select('user_id', DB::raw('SUM(work_minutes) as total_minutes'))
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('user_id')
            ->pluck('total_minutes', 'user_id');

        $lateCountMap = AttendanceRecord::query()
            ->select('user_id', DB::raw('COUNT(id) as late_count'))
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->where('late_minutes', '>', 0)
            ->groupBy('user_id')
            ->pluck('late_count', 'user_id');

        $attendanceSetting = AttendanceSetting::first();
        $latePenaltyPerTime = 0;

        if (
            $attendanceSetting &&
            SchemaCache::hasColumn($attendanceSetting->getTable(), 'late_penalty_per_time')
        ) {
            $latePenaltyPerTime = (int) ($attendanceSetting->late_penalty_per_time ?? 0);
        }

        $payrollMap = Payroll::query()
            ->where('payroll_month', $month)
            ->get()
            ->keyBy('user_id');

        $technicalSalaryMap = $this->getTechnicalSalaryMap($month);

        $rows = $employees->map(function ($employee) use (
            $attendanceMap,
            $minutesMap,
            $lateCountMap,
            $latePenaltyPerTime,
            $payrollMap,
            $technicalSalaryMap,
            $standardDaysAuto
        ) {
            $payroll = $payrollMap->get($employee->id);

            $salaryNoteRaw = $payroll ? (string) ($payroll->note ?? '') : '';
            $salaryMeta = $salaryNoteRaw ? json_decode($salaryNoteRaw, true) : [];
            $salaryMeta = is_array($salaryMeta) ? $salaryMeta : [];

            $income = $salaryMeta['income_breakdown'] ?? [];
            $deduction = $salaryMeta['deduction_breakdown'] ?? [];
            $noteText = $salaryMeta['note_text'] ?? $salaryNoteRaw;

            $workingDays = $payroll
                ? (float) $payroll->working_days
                : (float) ($attendanceMap[$employee->id] ?? 0);

            $standardDays = (float) $standardDaysAuto;

            $baseSalaryFromUser = $this->getEmployeeBaseSalary($employee);
            $basicSalary = $payroll
                ? (float) $payroll->basic_salary
                : $baseSalaryFromUser;

            $technicalSalaryTotal = (float) ($technicalSalaryMap[$employee->id] ?? 0);
            $isTechnical = $this->isTechnicalEmployee($employee) || $technicalSalaryTotal > 0;

            if ($isTechnical && $technicalSalaryTotal > 0) {
                $basicSalary = $technicalSalaryTotal;
            }

            $businessTrip = (float) ($income['business_trip'] ?? 0);
            $meal = (float) ($income['meal'] ?? 0);
            $phone = (float) ($income['phone'] ?? 0);
            $housing = (float) ($income['housing'] ?? 0);
            $fuel = (float) ($income['fuel'] ?? 0);
            $child = (float) ($income['child'] ?? 0);
            $province = (float) ($income['province'] ?? 0);

            $allowanceFromBreakdown = $businessTrip + $meal + $phone + $housing + $fuel + $child + $province;
            $allowanceTotal = $payroll ? (float) $payroll->allowance : $allowanceFromBreakdown;

            $commission = $payroll ? (float) $payroll->commission : 0;
            $bonus = $payroll ? (float) $payroll->bonus : 0;
            $advance = $payroll ? (float) $payroll->advance : 0;

            $bhxh = (float) ($deduction['bhxh'] ?? 0);
            $bhyt = (float) ($deduction['bhyt'] ?? 0);
            $bhtn = (float) ($deduction['bhtn'] ?? 0);
            $pit = (float) ($deduction['pit'] ?? 0);
            $otherOnly = (float) ($deduction['other'] ?? 0);

            $lateCount = (int) ($lateCountMap[$employee->id] ?? 0);
            $latePenaltyAuto = $lateCount * $latePenaltyPerTime;
            $latePenalty = (float) ($deduction['late_penalty'] ?? $latePenaltyAuto);

            $otherDeductionHidden = $payroll
                ? (float) $payroll->other_deduction
                : ($bhxh + $bhyt + $bhtn + $pit + $otherOnly + $latePenalty);

            if ($payroll && empty($deduction)) {
                $otherOnly = max($otherDeductionHidden - $latePenalty, 0);
            }

            $salaryByDays = $standardDays > 0
                ? ($basicSalary / $standardDays) * $workingDays
                : 0;

            $grossTotal = $salaryByDays + $allowanceTotal + $commission + $bonus;
            $totalDeduction = $advance + $otherDeductionHidden;

            $netSalary = $payroll
                ? (float) $payroll->net_salary
                : ($grossTotal - $totalDeduction);

            return (object) [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'department_name' => optional($employee->department)->name ?? 'Chưa có phòng ban',
                'position_name' => optional($employee->position)->name ?? 'Chưa có chức vụ',

                'working_days' => $workingDays,
                'standard_days' => $standardDays,
                'missing_days' => max($standardDays - $workingDays, 0),
                'total_minutes' => (int) ($minutesMap[$employee->id] ?? 0),
                'total_hours' => round(((int) ($minutesMap[$employee->id] ?? 0)) / 60, 2),

                'basic_salary' => $basicSalary,
                'salary_by_days' => $salaryByDays,

                'business_trip' => $businessTrip,
                'meal' => $meal,
                'phone' => $phone,
                'housing' => $housing,
                'fuel' => $fuel,
                'child' => $child,
                'province' => $province,
                'allowance_total' => $allowanceTotal,

                'commission' => $commission,
                'bonus' => $bonus,
                'gross_total' => $grossTotal,

                'bhxh' => $bhxh,
                'bhyt' => $bhyt,
                'bhtn' => $bhtn,
                'pit' => $pit,
                'advance' => $advance,
                'other_only' => $otherOnly,
                'late_count' => $lateCount,
                'late_penalty_per_time' => $latePenaltyPerTime,
                'late_penalty' => $latePenalty,
                'other_deduction_hidden' => $otherDeductionHidden,
                'total_deduction' => $totalDeduction,

                'net_salary' => $netSalary,
                'payroll_saved' => (bool) $payroll,
                'technical_salary_total' => $technicalSalaryTotal,
                'is_technical_salary_auto' => $isTechnical && $technicalSalaryTotal > 0,
                'note_text' => $noteText,
            ];
        });

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name', 'CRM'))
            ->setTitle('Bảng lương tháng '.$month);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAF6FF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $sectionStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
        ];

        $cellStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $moneyColumns = function ($sheet, array $columns, int $startRow, int $endRow) {
            if ($endRow < $startRow) {
                return;
            }

            foreach ($columns as $column) {
                $sheet->getStyle($column.$startRow.':'.$column.$endRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            }
        };

        $autoSize = function ($sheet, string $lastColumn) {
            foreach (range('A', $lastColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        };

        $cleanSheetTitle = function ($name, $usedTitles) {
            $name = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', (string) $name);
            $name = trim(preg_replace('/\s+/', ' ', $name));

            if ($name === '') {
                $name = 'Nhan vien';
            }

            $base = function_exists('mb_substr') ? mb_substr($name, 0, 31) : substr($name, 0, 31);
            $title = $base;
            $i = 2;

            while (in_array($title, $usedTitles, true)) {
                $suffix = ' '.$i;
                $limit = 31 - strlen($suffix);
                $shortBase = function_exists('mb_substr') ? mb_substr($base, 0, $limit) : substr($base, 0, $limit);
                $title = $shortBase.$suffix;
                $i++;
            }

            return $title;
        };

        /*
        |--------------------------------------------------------------------------
        | Sheet 1: Tổng hợp lương
        |--------------------------------------------------------------------------
        */
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Tong hop luong');

        $summarySheet->mergeCells('A1:R1');
        $summarySheet->setCellValue('A1', 'BẢNG LƯƠNG NHÂN VIÊN THÁNG '.Carbon::parse($start)->format('m/Y'));
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $summarySheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $summarySheet->mergeCells('A2:R2');
        $summarySheet->setCellValue('A2', 'Ngày công chuẩn tự động theo cài đặt chấm công: '.$standardDaysAuto.' công');
        $summarySheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = [
            'STT',
            'Nhân viên',
            'Phòng ban',
            'Chức vụ',
            'Công thực tế',
            'Công chuẩn',
            'Thiếu công',
            'Giờ công',
            'Lương tháng',
            'Lương theo công',
            'Tổng phụ cấp',
            'Hoa hồng / OT',
            'Thưởng',
            'Tổng thu nhập',
            'Tổng khấu trừ',
            'Thực lĩnh',
            'Trạng thái',
            'Ghi chú',
        ];

        $summarySheet->fromArray($headers, null, 'A4');
        $summarySheet->getStyle('A4:R4')->applyFromArray($headerStyle);

        $rowNumber = 5;

        foreach ($rows as $index => $row) {
            $summarySheet->fromArray([
                $index + 1,
                $row->employee_name,
                $row->department_name,
                $row->position_name,
                $row->working_days,
                $row->standard_days,
                $row->missing_days,
                $row->total_hours,
                round($row->basic_salary),
                round($row->salary_by_days),
                round($row->allowance_total),
                round($row->commission),
                round($row->bonus),
                round($row->gross_total),
                round($row->total_deduction),
                round($row->net_salary),
                $row->payroll_saved ? 'Đã lưu' : 'Tự tính',
                $row->note_text,
            ], null, 'A'.$rowNumber);

            $rowNumber++;
        }

        $lastDataRow = $rowNumber - 1;

        if ($lastDataRow >= 5) {
            $totalRow = $rowNumber + 1;

            $summarySheet->setCellValue('A'.$totalRow, 'TỔNG');
            $summarySheet->mergeCells('A'.$totalRow.':H'.$totalRow);
            $summarySheet->setCellValue('I'.$totalRow, '=SUM(I5:I'.$lastDataRow.')');
            $summarySheet->setCellValue('J'.$totalRow, '=SUM(J5:J'.$lastDataRow.')');
            $summarySheet->setCellValue('K'.$totalRow, '=SUM(K5:K'.$lastDataRow.')');
            $summarySheet->setCellValue('L'.$totalRow, '=SUM(L5:L'.$lastDataRow.')');
            $summarySheet->setCellValue('M'.$totalRow, '=SUM(M5:M'.$lastDataRow.')');
            $summarySheet->setCellValue('N'.$totalRow, '=SUM(N5:N'.$lastDataRow.')');
            $summarySheet->setCellValue('O'.$totalRow, '=SUM(O5:O'.$lastDataRow.')');
            $summarySheet->setCellValue('P'.$totalRow, '=SUM(P5:P'.$lastDataRow.')');

            $summarySheet->getStyle('A4:R'.$totalRow)->applyFromArray($cellStyle);
            $summarySheet->getStyle('A'.$totalRow.':R'.$totalRow)->getFont()->setBold(true);
            $moneyColumns($summarySheet, ['I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'], 5, $totalRow);
        }

        $summarySheet->freezePane('A5');
        $autoSize($summarySheet, 'R');

        /*
        |--------------------------------------------------------------------------
        | Các sheet tiếp theo: Chi tiết từng nhân viên
        |--------------------------------------------------------------------------
        */
        $usedSheetTitles = ['Tong hop luong'];

        foreach ($rows as $row) {
            $sheetTitle = $cleanSheetTitle($row->employee_name, $usedSheetTitles);
            $usedSheetTitles[] = $sheetTitle;

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);

            $sheet->mergeCells('A1:D1');
            $sheet->setCellValue('A1', 'CHI TIẾT LƯƠNG - '.$row->employee_name);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells('A2:D2');
            $sheet->setCellValue('A2', 'Kỳ lương: '.$month.' | Công chuẩn: '.$standardDaysAuto.' công');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $currentRow = 4;

            $writeSection = function ($title, array $items) use ($sheet, &$currentRow, $headerStyle, $sectionStyle, $cellStyle) {
                $sheet->mergeCells('A'.$currentRow.':D'.$currentRow);
                $sheet->setCellValue('A'.$currentRow, $title);
                $sheet->getStyle('A'.$currentRow.':D'.$currentRow)->applyFromArray($sectionStyle);
                $currentRow++;

                $sheet->fromArray(['Hạng mục', 'Giá trị', 'Ghi chú', ''], null, 'A'.$currentRow);
                $sheet->getStyle('A'.$currentRow.':D'.$currentRow)->applyFromArray($headerStyle);
                $currentRow++;

                $startItemRow = $currentRow;

                foreach ($items as $item) {
                    $sheet->fromArray([
                        $item[0] ?? '',
                        $item[1] ?? '',
                        $item[2] ?? '',
                        $item[3] ?? '',
                    ], null, 'A'.$currentRow);

                    $currentRow++;
                }

                $sheet->getStyle('A'.($startItemRow - 2).':D'.($currentRow - 1))->applyFromArray($cellStyle);
                $currentRow++;
            };

            $writeSection('1. Thông tin nhân viên', [
                ['Mã nhân viên', $row->employee_id, '', ''],
                ['Họ tên', $row->employee_name, '', ''],
                ['Phòng ban', $row->department_name, '', ''],
                ['Chức vụ', $row->position_name, '', ''],
                ['Trạng thái bảng lương', $row->payroll_saved ? 'Đã lưu' : 'Tự tính', '', ''],
            ]);

            $writeSection('2. Ngày công & lương cơ bản', [
                ['Công chuẩn', $row->standard_days, 'Lấy tự động từ cài đặt chấm công', ''],
                ['Công thực tế', $row->working_days, 'Số ngày có check-in trong kỳ', ''],
                ['Thiếu công', $row->missing_days, 'Công chuẩn - công thực tế', ''],
                ['Tổng phút công', $row->total_minutes, '', ''],
                ['Tổng giờ công', $row->total_hours, '', ''],
                ['Lương tháng', round($row->basic_salary), '', ''],
                ['Lương theo công', round($row->salary_by_days), 'Lương tháng / công chuẩn × công thực tế', ''],
                ['Lương kỹ thuật tự động', $row->is_technical_salary_auto ? round($row->technical_salary_total) : 0, $row->is_technical_salary_auto ? 'Có lấy từ module kỹ thuật' : 'Không', ''],
            ]);

            $writeSection('3. Thu nhập / phụ cấp', [
                ['Công tác phí', round($row->business_trip), '', ''],
                ['Tiền ăn', round($row->meal), '', ''],
                ['Điện thoại', round($row->phone), '', ''],
                ['Nhà ở', round($row->housing), '', ''],
                ['Xăng xe', round($row->fuel), '', ''],
                ['Nuôi con nhỏ', round($row->child), '', ''],
                ['Công tác tỉnh', round($row->province), '', ''],
                ['Tổng phụ cấp', round($row->allowance_total), '', ''],
                ['Hoa hồng / OT', round($row->commission), '', ''],
                ['Thưởng', round($row->bonus), '', ''],
                ['Tổng thu nhập', round($row->gross_total), 'Lương theo công + phụ cấp + hoa hồng/OT + thưởng', ''],
            ]);

            $writeSection('4. Khấu trừ', [
                ['BHXH', round($row->bhxh), '', ''],
                ['BHYT', round($row->bhyt), '', ''],
                ['BHTN', round($row->bhtn), '', ''],
                ['Thuế TNCN', round($row->pit), '', ''],
                ['Tạm ứng', round($row->advance), '', ''],
                ['Khấu trừ khác', round($row->other_only), '', ''],
                ['Số lần đi trễ', $row->late_count, '', ''],
                ['Mức phạt/lần', round($row->late_penalty_per_time), '', ''],
                ['Phạt đi trễ', round($row->late_penalty), 'Số lần đi trễ × mức phạt/lần', ''],
                ['Tổng khấu trừ', round($row->total_deduction), '', ''],
            ]);

            $writeSection('5. Kết quả cuối cùng', [
                ['Tổng thu nhập', round($row->gross_total), '', ''],
                ['Tổng khấu trừ', round($row->total_deduction), '', ''],
                ['Thực lĩnh', round($row->net_salary), 'Tổng thu nhập - tổng khấu trừ', ''],
                ['Ghi chú', $row->note_text ?: '', '', ''],
            ]);

            $sheet->getStyle('B1:B'.$currentRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            $sheet->getStyle('C1:C'.$currentRow)
                ->getAlignment()
                ->setWrapText(true);

            $sheet->getStyle('A1:D'.$currentRow)
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->freezePane('A4');
            $autoSize($sheet, 'D');
        }

        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'bang-luong-'.$month.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Xem chi tiết lương của chính người đang đăng nhập.
     */
    public function mySalary(Request $request)
    {
        return $this->salaryDetail($request, auth()->user());
    }

    /**
     * Chi tiết lương một nhân viên theo tháng.
     */
    public function salaryDetail(Request $request, User $user)
    {
        $month = $request->get('month', now()->format('Y-m'));

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = (clone $start)->endOfMonth();
        $standardDaysAuto = $this->calculateStandardWorkdays($month);

        $workingDaysAuto = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('check_in_at')
            ->count();

        $totalMinutes = (int) AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->sum('work_minutes');

        $lateCount = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->where('late_minutes', '>', 0)
            ->count();

        $attendanceSetting = AttendanceSetting::first();
        $latePenaltyPerTime = 0;

        if (
            $attendanceSetting &&
            SchemaCache::hasColumn($attendanceSetting->getTable(), 'late_penalty_per_time')
        ) {
            $latePenaltyPerTime = (int) ($attendanceSetting->late_penalty_per_time ?? 0);
        }

        $payroll = Payroll::query()
            ->where('user_id', $user->id)
            ->where('payroll_month', $month)
            ->first();

        $salaryMeta = $this->decodePayrollNote($payroll?->note);
        $technicalSalaryMap = $this->getTechnicalSalaryMap($month);
        $technicalSalaryTotal = (float) ($technicalSalaryMap[$user->id] ?? 0);
        $technicalKpi = $this->getTechnicalKpiSnapshot($user->id, $month);

        $standardDays = $payroll ? (float) $payroll->standard_days : (float) $standardDaysAuto;
        $workingDays = $payroll ? (float) $payroll->working_days : (float) $workingDaysAuto;
        $basicSalary = $payroll ? (float) $payroll->basic_salary : $this->getEmployeeBaseSalary($user);

        if (($this->isTechnicalEmployee($user) || $technicalSalaryTotal > 0) && $technicalSalaryTotal > 0) {
            $basicSalary = $technicalSalaryTotal;
        }

        $componentValues = collect();
        if ($payroll && SchemaCache::hasTable('payroll_slip_component_values')) {
            $componentValues = DB::table('payroll_slip_component_values')
                ->where('payroll_id', $payroll->id)
                ->pluck('amount', 'component_id');
        }

        $components = $this->getPayrollSlipComponents();
        $componentRows = $components->map(function ($component) use (
            $payroll,
            $salaryMeta,
            $componentValues,
            $basicSalary,
            $standardDays,
            $workingDays,
            $lateCount,
            $latePenaltyPerTime,
            $technicalKpi
        ) {
            $amount = $this->resolvePayrollSlipComponentAmount(
                $component,
                $payroll,
                $salaryMeta,
                $componentValues,
                $basicSalary,
                $standardDays,
                $workingDays,
                $lateCount,
                $latePenaltyPerTime,
                $technicalKpi
            );

            return (object) [
                'definition' => $component,
                'amount' => $amount,
            ];
        });

        $incomeTotal = (float) $componentRows
            ->filter(fn ($row) => ($row->definition->section ?? '') === 'income' && (bool) ($row->definition->affects_total ?? false))
            ->sum('amount');

        $deductionTotal = (float) $componentRows
            ->filter(fn ($row) => ($row->definition->section ?? '') === 'deduction' && (bool) ($row->definition->affects_total ?? false))
            ->sum('amount');

        $calculatedNet = round($incomeTotal - $deductionTotal);
        if ($components->isEmpty()) {
            $incomeTotal = $standardDays > 0 ? ($basicSalary / $standardDays) * $workingDays : 0;
            $incomeTotal += (float) ($payroll->allowance ?? 0) + (float) ($payroll->commission ?? 0) + (float) ($payroll->bonus ?? 0);
            $deductionTotal = (float) ($payroll->advance ?? 0) + (float) ($payroll->other_deduction ?? 0) + ($lateCount * $latePenaltyPerTime);
            $calculatedNet = $payroll ? (float) $payroll->net_salary : round($incomeTotal - $deductionTotal);
        }

        $slipSettings = $this->payrollSlipSettingsMap();

        return view('finance.salary-detail', array_merge([
            'employee' => $user->load(['department', 'position']),
            'month' => $month,
            'workingDays' => $workingDays,
            'standardDays' => $standardDays,
            'totalMinutes' => $totalMinutes,
            'payroll' => $payroll,
            'salaryMeta' => $salaryMeta,
            'lateCount' => $lateCount,
            'latePenaltyPerTime' => $latePenaltyPerTime,
            'latePenaltyTotal' => $lateCount * $latePenaltyPerTime,
            'technicalKpi' => $technicalKpi,
            'componentRows' => $componentRows,
            'incomeTotal' => $incomeTotal,
            'deductionTotal' => $deductionTotal,
            'calculatedNet' => $calculatedNet,
            'slipSettings' => $slipSettings,
            'editMode' => $request->boolean('edit'),
            'canEditSlip' => $this->canEditPayrollSlip(),
            'isAdmin' => $this->isAdminUser(),
        ], $this->salaryDetailPresenter->viewData($componentRows, $salaryMeta, $technicalKpi)));
    }

    /**
     * Cấu hình mẫu phiếu lương. Chỉ quản trị viên được phép truy cập/thay đổi.
     */
    public function salarySlipSettings()
    {
        abort_unless($this->isAdminUser(), 403);

        return view('finance.salary-slip-settings', [
            'settings' => $this->payrollSlipSettingsMap(),
            'components' => $this->getPayrollSlipComponents(false),
            'sourceOptions' => $this->payrollSlipSourceOptions(),
        ]);
    }

    public function saveSalarySlipSettings(Request $request)
    {
        abort_unless($this->isAdminUser(), 403);

        if (! SchemaCache::hasTable('payroll_slip_settings')) {
            return back()->with('error', 'Chưa có bảng cấu hình phiếu lương. Hãy chạy migration của gói cập nhật.');
        }

        $data = $request->validate([
            'title' => 'required|string|max:190',
            'subtitle' => 'nullable|string|max:500',
            'footer_note' => 'nullable|string|max:1000',
        ]);

        $data['show_attendance'] = $request->boolean('show_attendance') ? '1' : '0';
        $data['show_kpi_summary'] = $request->boolean('show_kpi_summary') ? '1' : '0';
        $data['show_note'] = $request->boolean('show_note') ? '1' : '0';

        foreach ($data as $key => $value) {
            DB::table('payroll_slip_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return back()->with('success', 'Đã lưu cấu hình chung của phiếu lương.');
    }

    public function createSalarySlipComponent(Request $request)
    {
        abort_unless($this->isAdminUser(), 403);
        $data = $this->validateSalarySlipComponent($request);

        $baseCode = trim((string) ($request->input('code') ?: \Illuminate\Support\Str::slug($data['label'], '_')));
        $baseCode = $baseCode !== '' ? $baseCode : 'component';
        $code = $baseCode;
        $i = 2;
        while (DB::table('payroll_slip_components')->where('code', $code)->exists()) {
            $code = $baseCode.'_'.$i++;
        }

        DB::table('payroll_slip_components')->insert(array_merge($data, [
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]));

        return back()->with('success', 'Đã thêm thành phần phiếu lương: '.$data['label']);
    }

    public function updateSalarySlipComponent(Request $request, $component)
    {
        abort_unless($this->isAdminUser(), 403);
        $data = $this->validateSalarySlipComponent($request);

        DB::table('payroll_slip_components')
            ->where('id', $component)
            ->whereNull('deleted_at')
            ->update(array_merge($data, ['updated_at' => now()]));

        return back()->with('success', 'Đã cập nhật thành phần phiếu lương.');
    }

    public function deleteSalarySlipComponent($component)
    {
        abort_unless($this->isAdminUser(), 403);

        DB::table('payroll_slip_components')
            ->where('id', $component)
            ->whereNull('deleted_at')
            ->update([
                'is_active' => 0,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Đã xóa thành phần khỏi mẫu phiếu lương. Dữ liệu lịch sử vẫn được giữ.');
    }

    /**
     * Cập nhật một phiếu lương từ trang chi tiết. Các dòng cấu hình động được lưu riêng,
     * các nguồn lõi tiếp tục đồng bộ với bảng payrolls để không phá bảng lương hiện tại.
     */
    public function saveSalaryDetail(Request $request, User $user)
    {
        $month = $request->input('month', now()->format('Y-m'));
        try {
            Carbon::createFromFormat('Y-m', $month);
        } catch (\Throwable $e) {
            return back()->with('error', 'Kỳ lương không hợp lệ.');
        }

        $componentsInput = $request->input('components', []);
        $components = $this->getPayrollSlipComponents();
        $existing = Payroll::query()->where('user_id', $user->id)->where('payroll_month', $month)->first();
        $salaryMeta = $this->decodePayrollNote($existing?->note);

        $standardDays = $existing ? (float) $existing->standard_days : (float) $this->calculateStandardWorkdays($month);
        $workingDays = $existing ? (float) $existing->working_days : 0;
        $basicSalary = $existing ? (float) $existing->basic_salary : $this->getEmployeeBaseSalary($user);
        $core = [
            'standard_days' => $standardDays,
            'working_days' => $workingDays,
            'basic_salary' => $basicSalary,
            'allowance' => (float) ($existing->allowance ?? 0),
            'commission' => (float) ($existing->commission ?? 0),
            'bonus' => (float) ($existing->bonus ?? 0),
            'advance' => (float) ($existing->advance ?? 0),
            'other_deduction' => (float) ($existing->other_deduction ?? 0),
        ];

        $incomeBreakdown = is_array($salaryMeta['income_breakdown'] ?? null) ? $salaryMeta['income_breakdown'] : [];
        $deductionBreakdown = is_array($salaryMeta['deduction_breakdown'] ?? null) ? $salaryMeta['deduction_breakdown'] : [];

        DB::beginTransaction();
        try {
            foreach ($components as $component) {
                if (! (bool) ($component->editable_amount ?? false) || ! array_key_exists($component->id, $componentsInput)) {
                    continue;
                }

                $amount = max((float) $componentsInput[$component->id], 0);
                $source = (string) ($component->source ?? 'manual');

                if (array_key_exists($source, $core)) {
                    $core[$source] = $amount;

                    continue;
                }

                if (str_starts_with($source, 'income_')) {
                    $incomeBreakdown[substr($source, 7)] = $amount;

                    continue;
                }

                if (str_starts_with($source, 'deduction_')) {
                    $deductionBreakdown[substr($source, 10)] = $amount;

                    continue;
                }
            }

            $core['allowance'] = array_sum(array_map('floatval', $incomeBreakdown));
            $lateAuto = (float) ($deductionBreakdown['late_penalty'] ?? 0);
            $core['other_deduction'] = max(array_sum(array_map('floatval', $deductionBreakdown)), 0);

            $salaryMeta['income_breakdown'] = $incomeBreakdown;
            $salaryMeta['deduction_breakdown'] = $deductionBreakdown;
            $salaryMeta['note_text'] = trim((string) $request->input('note_text', $salaryMeta['note_text'] ?? ''));

            $payroll = Payroll::updateOrCreate(
                ['user_id' => $user->id, 'payroll_month' => $month],
                array_merge($core, [
                    'net_salary' => (float) ($existing->net_salary ?? 0),
                    'note' => json_encode($salaryMeta, JSON_UNESCAPED_UNICODE),
                    'created_by' => $existing?->created_by ?: auth()->id(),
                ])
            );

            if (SchemaCache::hasTable('payroll_slip_component_values')) {
                foreach ($components as $component) {
                    if (! (bool) ($component->editable_amount ?? false) || ! array_key_exists($component->id, $componentsInput)) {
                        continue;
                    }

                    $source = (string) ($component->source ?? 'manual');
                    if (! in_array($source, ['manual', 'fixed'], true)) {
                        continue;
                    }

                    DB::table('payroll_slip_component_values')->updateOrInsert(
                        ['payroll_id' => $payroll->id, 'component_id' => $component->id],
                        ['amount' => max((float) $componentsInput[$component->id], 0), 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }

            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $lateCount = AttendanceRecord::query()
                ->where('user_id', $user->id)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->where('late_minutes', '>', 0)
                ->count();
            $attendanceSetting = AttendanceSetting::first();
            $latePenaltyPerTime = ($attendanceSetting && SchemaCache::hasColumn($attendanceSetting->getTable(), 'late_penalty_per_time'))
                ? (int) ($attendanceSetting->late_penalty_per_time ?? 0)
                : 0;
            $technicalKpi = $this->getTechnicalKpiSnapshot($user->id, $month);
            $values = SchemaCache::hasTable('payroll_slip_component_values')
                ? DB::table('payroll_slip_component_values')->where('payroll_id', $payroll->id)->pluck('amount', 'component_id')
                : collect();

            $currentMeta = $this->decodePayrollNote($payroll->note);
            $rows = $components->map(function ($component) use ($payroll, $currentMeta, $values, $lateCount, $latePenaltyPerTime, $technicalKpi) {
                return (object) [
                    'definition' => $component,
                    'amount' => $this->resolvePayrollSlipComponentAmount(
                        $component,
                        $payroll,
                        $currentMeta,
                        $values,
                        (float) $payroll->basic_salary,
                        (float) $payroll->standard_days,
                        (float) $payroll->working_days,
                        $lateCount,
                        $latePenaltyPerTime,
                        $technicalKpi
                    ),
                ];
            });

            $incomeTotal = (float) $rows->filter(fn ($row) => $row->definition->section === 'income' && (bool) $row->definition->affects_total)->sum('amount');
            $deductionTotal = (float) $rows->filter(fn ($row) => $row->definition->section === 'deduction' && (bool) $row->definition->affects_total)->sum('amount');
            $payroll->net_salary = round($incomeTotal - $deductionTotal);
            $payroll->save();

            DB::commit();

            return redirect()->route('finance.salary.detail', ['user' => $user->id, 'month' => $month])
                ->with('success', 'Đã cập nhật phiếu lương.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Không thể cập nhật phiếu lương: '.$e->getMessage());
        }
    }

    /**
     * Lưu (tạo mới / cập nhật) bảng lương tháng cho nhiều nhân viên.
     */
    public function saveSalary(Request $request)
    {
        $rows = $request->input('rows', []);
        $month = $request->input('month', now()->format('Y-m'));

        try {
            Carbon::createFromFormat('Y-m', $month);
        } catch (\Throwable $e) {
            return back()->with('error', 'Kỳ lương không hợp lệ.');
        }

        $standardDaysAuto = $this->calculateStandardWorkdays($month);

        if (empty($rows) || ! is_array($rows)) {
            return back()->with('error', 'Không có dữ liệu bảng lương để lưu.');
        }

        DB::beginTransaction();

        try {
            $saved = 0;

            foreach ($rows as $row) {
                if (empty($row['user_id'])) {
                    continue;
                }

                $standardDays = max((float) ($row['standard_days'] ?? $standardDaysAuto), 0);
                $workingDays = max((float) ($row['working_days'] ?? 0), 0);
                $basicSalary = max((float) ($row['basic_salary'] ?? 0), 0);
                $allowance = max((float) ($row['allowance'] ?? 0), 0);
                $commission = max((float) ($row['commission'] ?? 0), 0);
                $bonus = max((float) ($row['bonus'] ?? 0), 0);
                $advance = max((float) ($row['advance'] ?? 0), 0);
                $otherDeduction = max((float) ($row['other_deduction'] ?? 0), 0);

                $salaryByDays = $standardDays > 0
                    ? ($basicSalary / $standardDays) * $workingDays
                    : 0;

                $calculatedNetSalary = round(
                    $salaryByDays
                    + $allowance
                    + $commission
                    + $bonus
                    - $advance
                    - $otherDeduction
                );

                $netSalary = isset($row['net_salary'])
                    ? (float) $row['net_salary']
                    : $calculatedNetSalary;

                Payroll::updateOrCreate(
                    [
                        'user_id' => (int) $row['user_id'],
                        'payroll_month' => $month,
                    ],
                    [
                        'standard_days' => $standardDays,
                        'working_days' => $workingDays,
                        'basic_salary' => $basicSalary,
                        'allowance' => $allowance,
                        'commission' => $commission,
                        'bonus' => $bonus,
                        'advance' => $advance,
                        'other_deduction' => $otherDeduction,
                        'net_salary' => $netSalary,
                        'note' => $row['note'] ?? null,
                        'created_by' => auth()->id(),
                    ]
                );

                $saved++;
            }

            DB::commit();

            return redirect()
                ->route('finance.salary', ['month' => $month])
                ->with('success', 'Đã lưu bảng lương thành công. Tổng số dòng đã lưu: '.$saved);
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Lưu bảng lương thất bại: '.$e->getMessage());
        }
    }

    /**
     * Tính số ngày công chuẩn của tháng theo cài đặt chấm công và ngày lễ.
     */
    private function calculateStandardWorkdays(string $month): int
    {
        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
        }

        $end = (clone $start)->endOfMonth();
        $setting = AttendanceSetting::first();

        $holidayDates = collect();

        if (SchemaCache::hasTable('attendance_holidays')) {
            $holidayDates = DB::table('attendance_holidays')
                ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
                ->pluck('holiday_date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->flip();
        }

        $saturdayCustomDates = [];

        if ($setting && SchemaCache::hasColumn($setting->getTable(), 'saturday_custom_dates')) {
            $rawCustomDates = $setting->saturday_custom_dates ?? [];

            if (is_string($rawCustomDates)) {
                $decoded = json_decode($rawCustomDates, true);
                $rawCustomDates = is_array($decoded) ? $decoded : preg_split('/[\s,;]+/', $rawCustomDates);
            }

            if (is_array($rawCustomDates)) {
                foreach ($rawCustomDates as $date) {
                    try {
                        $saturdayCustomDates[] = Carbon::parse($date)->toDateString();
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        $standardDays = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dateKey = $day->toDateString();
            $isWorkday = false;

            if ($day->dayOfWeekIso === 1) {
                $isWorkday = $setting ? (bool) ($setting->workday_monday ?? true) : true;
            } elseif ($day->dayOfWeekIso === 2) {
                $isWorkday = $setting ? (bool) ($setting->workday_tuesday ?? true) : true;
            } elseif ($day->dayOfWeekIso === 3) {
                $isWorkday = $setting ? (bool) ($setting->workday_wednesday ?? true) : true;
            } elseif ($day->dayOfWeekIso === 4) {
                $isWorkday = $setting ? (bool) ($setting->workday_thursday ?? true) : true;
            } elseif ($day->dayOfWeekIso === 5) {
                $isWorkday = $setting ? (bool) ($setting->workday_friday ?? true) : true;
            } elseif ($day->dayOfWeekIso === 6) {
                $saturdayMode = $setting->saturday_mode ?? 'off';
                $weekOfMonth = (int) ceil($day->day / 7);

                $isWorkday = match ($saturdayMode) {
                    'all' => true,
                    'odd' => $weekOfMonth % 2 === 1,
                    'even' => $weekOfMonth % 2 === 0,
                    'custom' => in_array($dateKey, $saturdayCustomDates, true),
                    default => false,
                };
            } elseif ($day->dayOfWeekIso === 7) {
                $isWorkday = $setting ? (bool) ($setting->workday_sunday ?? false) : false;
            }

            if ($isWorkday && ! $holidayDates->has($dateKey)) {
                $standardDays++;
            }
        }

        return max($standardDays, 1);
    }

    /**
     * Lấy lương cơ bản của nhân viên từ cột lương đầu tiên tồn tại trong bảng users.
     */
    private function getEmployeeBaseSalary(User $employee): float
    {
        $columns = [
            'official_salary',
            'basic_salary',
            'base_salary',
            'gross_salary',
            'monthly_salary',
            'salary',
        ];

        foreach ($columns as $column) {
            if (SchemaCache::hasColumn('users', $column) && isset($employee->{$column})) {
                return (float) $employee->{$column};
            }
        }

        return 0;
    }

    /**
     * Kiểm tra nhân viên có thuộc phòng ban / vai trò kỹ thuật hay không.
     */
    private function isTechnicalEmployee(User $employee): bool
    {
        $departmentName = mb_strtolower((string) optional($employee->department)->name, 'UTF-8');
        $positionName = mb_strtolower((string) optional($employee->position)->name, 'UTF-8');

        $text = $departmentName.' '.$positionName;

        if (
            str_contains($text, 'kỹ thuật') ||
            str_contains($text, 'ky thuat') ||
            str_contains($text, 'technical') ||
            str_contains($text, 'technical')
        ) {
            return true;
        }

        if (method_exists($employee, 'hasRole')) {
            try {
                return $employee->hasRole('technical')
                    || $employee->hasRole('kythuat')
                    || $employee->hasRole('Kỹ thuật')
                    || $employee->hasRole('technical');
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    private function canEditPayrollSlip(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($this->isAdminUser()) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['accounting', 'management']);
        }

        return in_array((string) ($user->role ?? ''), ['accounting', 'management'], true);
    }

    private function isAdminUser(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ((int) ($user->is_admin ?? 0) === 1 || (($user->role ?? null) === 'admin')) {
            return true;
        }

        return method_exists($user, 'hasRole') && $user->hasRole('admin');
    }

    private function decodePayrollNote($note): array
    {
        if (! is_string($note) || trim($note) === '') {
            return [
                'note_text' => '',
                'income_breakdown' => [],
                'deduction_breakdown' => [],
            ];
        }

        $decoded = json_decode($note, true);
        if (! is_array($decoded)) {
            return [
                'note_text' => $note,
                'income_breakdown' => [],
                'deduction_breakdown' => [],
            ];
        }

        $decoded['note_text'] = (string) ($decoded['note_text'] ?? '');
        $decoded['income_breakdown'] = is_array($decoded['income_breakdown'] ?? null) ? $decoded['income_breakdown'] : [];
        $decoded['deduction_breakdown'] = is_array($decoded['deduction_breakdown'] ?? null) ? $decoded['deduction_breakdown'] : [];

        return $decoded;
    }

    private function payrollSlipSettingsMap(): array
    {
        $defaults = [
            'title' => 'PHIẾU LƯƠNG NHÂN VIÊN',
            'subtitle' => 'Chi tiết thu nhập, khấu trừ và thực nhận theo kỳ lương',
            'footer_note' => 'Phiếu lương được tổng hợp từ dữ liệu chấm công, KPI và các khoản điều chỉnh đã được xác nhận.',
            'show_attendance' => '1',
            'show_kpi_summary' => '1',
            'show_note' => '1',
        ];

        if (! SchemaCache::hasTable('payroll_slip_settings')) {
            return $defaults;
        }

        $stored = DB::table('payroll_slip_settings')->pluck('setting_value', 'setting_key')->all();

        return array_merge($defaults, $stored);
    }

    private function getPayrollSlipComponents(bool $activeOnly = true)
    {
        if (! SchemaCache::hasTable('payroll_slip_components')) {
            return collect();
        }

        $query = DB::table('payroll_slip_components')->whereNull('deleted_at');
        if ($activeOnly) {
            $query->where('is_active', 1)->where('show_on_payslip', 1);
        }

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    private function payrollSlipSourceOptions(): array
    {
        return [
            'manual' => 'Nhập tay theo từng phiếu',
            'fixed' => 'Giá trị mặc định',
            'basic_salary' => 'Lương tháng',
            'standard_days' => 'Ngày công chuẩn',
            'working_days' => 'Ngày công thực tế',
            'salary_by_days' => 'Lương theo ngày công',
            'income_business_trip' => 'Công tác phí',
            'income_meal' => 'Phụ cấp cơm',
            'income_phone' => 'Phụ cấp điện thoại',
            'income_housing' => 'Phụ cấp nhà ở',
            'income_fuel' => 'Phụ cấp xăng xe',
            'income_child' => 'Phụ cấp con nhỏ',
            'income_province' => 'Phụ cấp tỉnh',
            'commission' => 'Hoa hồng / OT',
            'bonus' => 'Thưởng',
            'technical_kpi' => 'Lương KPI kỹ thuật đã duyệt',
            'deduction_bhxh' => 'BHXH',
            'deduction_bhyt' => 'BHYT',
            'deduction_bhtn' => 'BHTN',
            'deduction_pit' => 'Thuế TNCN',
            'advance' => 'Tạm ứng',
            'late_penalty' => 'Phạt đi trễ tự động',
            'deduction_other' => 'Khấu trừ khác',
        ];
    }

    private function validateSalarySlipComponent(Request $request): array
    {
        $validated = $request->validate([
            'label' => 'required|string|max:190',
            'section' => 'required|in:info,income,deduction',
            'source' => 'required|string|max:80',
            'display_format' => 'required|in:money,number,percent',
            'default_amount' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0|max:99999',
            'note' => 'nullable|string|max:1000',
        ]);

        $validated['default_amount'] = (float) ($validated['default_amount'] ?? 0);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['affects_total'] = $request->boolean('affects_total');
        $validated['editable_amount'] = $request->boolean('editable_amount');
        $validated['show_on_payslip'] = $request->boolean('show_on_payslip');
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function getTechnicalKpiSnapshot(int $userId, string $month)
    {
        if (! SchemaCache::hasTable('technical_kpi_payrolls')) {
            return null;
        }

        $monthColumn = $this->firstExistingColumn('technical_kpi_payrolls', ['payroll_month', 'salary_month', 'month', 'period']);
        $userColumn = $this->firstExistingColumn('technical_kpi_payrolls', ['user_id', 'employee_id', 'staff_id']);
        if (! $monthColumn || ! $userColumn) {
            return null;
        }

        $query = DB::table('technical_kpi_payrolls')
            ->where($userColumn, $userId)
            ->whereIn($monthColumn, $this->getMonthVariants($month));

        if (SchemaCache::hasColumn('technical_kpi_payrolls', 'status')) {
            $query->whereIn('status', ['approved', 'locked', 'confirmed']);
        }

        return $query->orderByDesc('id')->first();
    }

    private function resolvePayrollSlipComponentAmount(
        $component,
        $payroll,
        array $salaryMeta,
        $componentValues,
        float $basicSalary,
        float $standardDays,
        float $workingDays,
        int $lateCount,
        int $latePenaltyPerTime,
        $technicalKpi
    ): float {
        $source = (string) ($component->source ?? 'manual');
        $income = is_array($salaryMeta['income_breakdown'] ?? null) ? $salaryMeta['income_breakdown'] : [];
        $deduction = is_array($salaryMeta['deduction_breakdown'] ?? null) ? $salaryMeta['deduction_breakdown'] : [];

        if (isset($componentValues[$component->id]) && in_array($source, ['manual', 'fixed'], true)) {
            return (float) $componentValues[$component->id];
        }

        return match ($source) {
            'basic_salary' => $basicSalary,
            'standard_days' => $standardDays,
            'working_days' => $workingDays,
            'salary_by_days' => $standardDays > 0 ? ($basicSalary / $standardDays) * $workingDays : 0,
            'commission' => (float) ($payroll->commission ?? 0),
            'bonus' => (float) ($payroll->bonus ?? 0),
            'advance' => (float) ($payroll->advance ?? 0),
            'income_business_trip' => (float) ($income['business_trip'] ?? 0),
            'income_meal' => (float) ($income['meal'] ?? 0),
            'income_phone' => (float) ($income['phone'] ?? 0),
            'income_housing' => (float) ($income['housing'] ?? 0),
            'income_fuel' => (float) ($income['fuel'] ?? 0),
            'income_child' => (float) ($income['child'] ?? 0),
            'income_province' => (float) ($income['province'] ?? 0),
            'deduction_bhxh' => (float) ($deduction['bhxh'] ?? 0),
            'deduction_bhyt' => (float) ($deduction['bhyt'] ?? 0),
            'deduction_bhtn' => (float) ($deduction['bhtn'] ?? 0),
            'deduction_pit' => (float) ($deduction['pit'] ?? 0),
            'deduction_other' => (float) ($deduction['other'] ?? 0),
            'late_penalty' => (float) ($lateCount * $latePenaltyPerTime),
            'technical_kpi' => (float) ($technicalKpi->real_kpi_salary ?? 0),
            'fixed' => (float) ($component->default_amount ?? 0),
            default => (float) ($component->default_amount ?? 0),
        };
    }

    /**
     * Lấy map user_id => tổng lương kỹ thuật của tháng từ bảng payroll kỹ thuật khả dụng.
     */
    private function getTechnicalSalaryMap(string $month)
    {
        $tables = [
            'technical_payrolls',
            'technical_salaries',
            'technical_kpi_payrolls',
            'kythuat_payrolls',
            'ky_thuat_payrolls',
        ];

        $monthVariants = $this->getMonthVariants($month);

        foreach ($tables as $table) {
            if (! SchemaCache::hasTable($table)) {
                continue;
            }

            $userColumn = $this->firstExistingColumn($table, [
                'user_id',
                'employee_id',
                'staff_id',
            ]);

            $monthColumn = $this->firstExistingColumn($table, [
                'payroll_month',
                'salary_month',
                'month',
                'period',
            ]);

            $amountColumn = $this->firstExistingColumn($table, [
                'total_income',
                'total_salary',
                'net_salary',
                'final_salary',
                'gross_salary',
                'total_amount',
                'actual_salary',
                'take_home',
                'income',
            ]);

            if (! $userColumn || ! $monthColumn || ! $amountColumn) {
                continue;
            }

            try {
                $query = DB::table($table)
                    ->select($userColumn, DB::raw('SUM(COALESCE(`'.$amountColumn.'`, 0)) as amount'))
                    ->whereIn($monthColumn, $monthVariants);

                if ($table === 'technical_kpi_payrolls' && SchemaCache::hasColumn($table, 'status')) {
                    $query->whereIn('status', ['approved', 'locked', 'confirmed']);
                }

                return $query
                    ->groupBy($userColumn)
                    ->pluck('amount', $userColumn);
            } catch (\Throwable $e) {
                ProbeFailureLog::warn('FinanceDashboardController::getTechnicalSalaryMap', $e);

                continue;
            }
        }

        return collect();
    }

    /**
     * Sinh các biến thể định dạng chuỗi tháng để so khớp dữ liệu cũ.
     */
    private function getMonthVariants(string $month): array
    {
        try {
            $date = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

            return array_values(array_unique([
                $month,
                $date->format('Y-m'),
                $date->format('Y-m-01'),
                $date->format('m/Y'),
                $date->format('m-Y'),
                $date->format('F Y'),
                $date->format('M Y'),
            ]));
        } catch (\Throwable $e) {
            return [$month];
        }
    }

    /**
     * Trả về cột đầu tiên tồn tại trong bảng theo danh sách ưu tiên.
     */
    private function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (SchemaCache::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Dựng query đơn hàng theo bộ lọc từ khóa, trạng thái và khoảng ngày.
     */
    private function getFilteredOrdersQuery(array $filters)
    {
        $query = Order::query();

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];

            $query->where(function ($q) use ($keyword) {
                if (SchemaCache::hasColumn('crm_orders', 'order_code')) {
                    $q->orWhere('order_code', 'like', '%'.$keyword.'%');
                }

                if (SchemaCache::hasColumn('crm_orders', 'customer_name')) {
                    $q->orWhere('customer_name', 'like', '%'.$keyword.'%');
                }

                if (SchemaCache::hasColumn('crm_orders', 'customer_phone')) {
                    $q->orWhere('customer_phone', 'like', '%'.$keyword.'%');
                }
            });
        }

        if (! empty($filters['order_status']) && SchemaCache::hasColumn('crm_orders', 'status')) {
            $query->where('status', $filters['order_status']);
        }

        $dateColumn = $this->getOrderDateColumn();

        if ($dateColumn) {
            if (! empty($filters['from_date'])) {
                $query->whereDate($dateColumn, '>=', $filters['from_date']);
            }

            if (! empty($filters['to_date'])) {
                $query->whereDate($dateColumn, '<=', $filters['to_date']);
            }
        }

        return $query;
    }

    /**
     * Xác định cột ngày dùng để lọc đơn hàng.
     */
    private function getOrderDateColumn(): ?string
    {
        if (SchemaCache::hasColumn('crm_orders', 'order_date')) {
            return 'order_date';
        }

        if (SchemaCache::hasColumn('crm_orders', 'created_at')) {
            return 'created_at';
        }

        return null;
    }

    /**
     * Danh sách các trạng thái đơn hàng hiện có.
     */
    private function getOrderStatuses(): array
    {
        if (! SchemaCache::hasTable('crm_orders') || ! SchemaCache::hasColumn('crm_orders', 'status')) {
            return [];
        }

        return DB::table('crm_orders')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Đếm số đề nghị thanh toán chưa bị từ chối trong khoảng lọc.
     */
    private function getPendingPaymentRequestsCount(array $filters): int
    {
        if (! SchemaCache::hasTable('payment_requests') || ! SchemaCache::hasColumn('payment_requests', 'status')) {
            return 0;
        }

        $query = DB::table('payment_requests')
            ->whereNotIn('status', ['admin_rejected']);

        $dateColumn = $this->getPaymentRequestDateColumn();

        if ($dateColumn) {
            if (! empty($filters['from_date'])) {
                $query->whereDate($dateColumn, '>=', $filters['from_date']);
            }

            if (! empty($filters['to_date'])) {
                $query->whereDate($dateColumn, '<=', $filters['to_date']);
            }
        }

        return (int) $query->count();
    }

    /**
     * Tổng tiền đề nghị thanh toán chưa bị từ chối trong khoảng lọc.
     */
    private function getPendingPaymentRequestsAmount(array $filters): float
    {
        if (
            ! SchemaCache::hasTable('payment_requests') ||
            ! SchemaCache::hasColumn('payment_requests', 'status') ||
            ! SchemaCache::hasColumn('payment_requests', 'amount')
        ) {
            return 0;
        }

        $query = DB::table('payment_requests')
            ->whereNotIn('status', ['admin_rejected']);

        $dateColumn = $this->getPaymentRequestDateColumn();

        if ($dateColumn) {
            if (! empty($filters['from_date'])) {
                $query->whereDate($dateColumn, '>=', $filters['from_date']);
            }

            if (! empty($filters['to_date'])) {
                $query->whereDate($dateColumn, '<=', $filters['to_date']);
            }
        }

        return (float) $query->sum('amount');
    }

    /**
     * Xác định cột ngày dùng để lọc đề nghị thanh toán.
     */
    private function getPaymentRequestDateColumn(): ?string
    {
        if (SchemaCache::hasColumn('payment_requests', 'updated_at')) {
            return 'updated_at';
        }

        if (SchemaCache::hasColumn('payment_requests', 'created_at')) {
            return 'created_at';
        }

        return null;
    }

    /**
     * Dựng query công nợ khách hàng theo bộ lọc; trả về null nếu thiếu bảng.
     */
    private function getDebtBaseQuery(array $filters)
    {
        if (! SchemaCache::hasTable('crm_customer_debts')) {
            return null;
        }

        $query = DB::table('crm_customer_debts');

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];

            $query->where(function ($q) use ($keyword) {
                if (SchemaCache::hasColumn('crm_customer_debts', 'customer_name')) {
                    $q->orWhere('customer_name', 'like', '%'.$keyword.'%');
                }

                if (SchemaCache::hasColumn('crm_customer_debts', 'order_code')) {
                    $q->orWhere('order_code', 'like', '%'.$keyword.'%');
                }
            });
        }

        if (! empty($filters['order_status']) && SchemaCache::hasColumn('crm_customer_debts', 'status')) {
            $query->where('status', $filters['order_status']);
        }

        $dateColumn = $this->getDebtDateColumn();

        if ($dateColumn) {
            if (! empty($filters['from_date'])) {
                $query->whereDate($dateColumn, '>=', $filters['from_date']);
            }

            if (! empty($filters['to_date'])) {
                $query->whereDate($dateColumn, '<=', $filters['to_date']);
            }
        }

        return $query;
    }

    /**
     * Xác định cột ngày dùng để lọc công nợ khách hàng.
     */
    private function getDebtDateColumn(): ?string
    {
        if (SchemaCache::hasColumn('crm_customer_debts', 'debt_date')) {
            return 'debt_date';
        }

        if (SchemaCache::hasColumn('crm_customer_debts', 'created_at')) {
            return 'created_at';
        }

        return null;
    }

    /**
     * Tổng giá trị công nợ gốc theo bộ lọc.
     */
    private function getDebtBaseTotal(array $filters): float
    {
        $query = $this->getDebtBaseQuery($filters);

        if (! $query || ! SchemaCache::hasColumn('crm_customer_debts', 'total_amount')) {
            return 0;
        }

        return (float) $query->sum('total_amount');
    }

    /**
     * Tổng tiền khách đã thanh toán theo bộ lọc.
     */
    private function getDebtPaidTotal(array $filters): float
    {
        $query = $this->getDebtBaseQuery($filters);

        if (! $query || ! SchemaCache::hasColumn('crm_customer_debts', 'paid_amount')) {
            return 0;
        }

        return (float) $query->sum('paid_amount');
    }

    /**
     * Tổng công nợ còn lại theo bộ lọc.
     */
    private function getDebtRemainTotal(array $filters): float
    {
        $query = $this->getDebtBaseQuery($filters);

        if (! $query || ! SchemaCache::hasColumn('crm_customer_debts', 'debt_amount')) {
            return 0;
        }

        return (float) $query->sum('debt_amount');
    }

    /**
     * Tổng công nợ quá hạn theo bộ lọc.
     */
    private function getOverdueDebtTotal(array $filters): float
    {
        $query = $this->getDebtBaseQuery($filters);

        if (
            ! $query ||
            ! SchemaCache::hasColumn('crm_customer_debts', 'debt_amount') ||
            ! SchemaCache::hasColumn('crm_customer_debts', 'status')
        ) {
            return 0;
        }

        return (float) $query
            ->where('status', 'overdue')
            ->sum('debt_amount');
    }

    /**
     * Tổng doanh thu từ đơn hàng theo bộ lọc.
     */
    private function getTotalRevenue(array $filters): float
    {
        $query = $this->getFilteredOrdersQuery($filters);

        if (SchemaCache::hasColumn('crm_orders', 'total_amount')) {
            return (float) $query->sum('total_amount');
        }

        if (
            SchemaCache::hasTable('crm_order_items') &&
            SchemaCache::hasColumn('crm_order_items', 'line_total')
        ) {
            $orderIds = $query->pluck('id');

            return (float) DB::table('crm_order_items')
                ->whereIn('order_id', $orderIds)
                ->sum('line_total');
        }

        return 0;
    }

    /**
     * Tổng giá vốn (số lượng x giá đại lý) của các đơn theo bộ lọc.
     */
    private function getTotalCost(array $filters): float
    {
        if (
            ! SchemaCache::hasTable('crm_order_items') ||
            ! SchemaCache::hasTable('crm_product_catalog') ||
            ! SchemaCache::hasColumn('crm_order_items', 'order_id') ||
            ! SchemaCache::hasColumn('crm_order_items', 'product_id') ||
            ! SchemaCache::hasColumn('crm_order_items', 'quantity') ||
            ! SchemaCache::hasColumn('crm_product_catalog', 'id') ||
            ! SchemaCache::hasColumn('crm_product_catalog', 'price_agent')
        ) {
            return 0;
        }

        $orderIds = $this->getFilteredOrdersQuery($filters)->pluck('id');

        if ($orderIds->isEmpty()) {
            return 0;
        }

        return (float) DB::table('crm_order_items as oi')
            ->join('crm_product_catalog as p', 'p.id', '=', 'oi.product_id')
            ->whereIn('oi.order_id', $orderIds)
            ->selectRaw('SUM(COALESCE(oi.quantity, 0) * COALESCE(p.price_agent, 0)) as total_cost')
            ->value('total_cost');
    }

    /**
     * Tổng tiền thu vào trong kỳ từ crm_payments.
     */
    private function getCashInPeriod(array $filters): float
    {
        if (
            ! SchemaCache::hasTable('crm_payments') ||
            ! SchemaCache::hasColumn('crm_payments', 'amount')
        ) {
            return 0;
        }

        $dateColumn = SchemaCache::hasColumn('crm_payments', 'payment_date')
            ? 'payment_date'
            : (SchemaCache::hasColumn('crm_payments', 'created_at') ? 'created_at' : null);

        if (! $dateColumn) {
            return 0;
        }

        $query = DB::table('crm_payments');

        if (! empty($filters['from_date'])) {
            $query->whereDate($dateColumn, '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate($dateColumn, '<=', $filters['to_date']);
        }

        if (empty($filters['from_date']) && empty($filters['to_date'])) {
            $query->whereMonth($dateColumn, now()->month)
                ->whereYear($dateColumn, now()->year);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Tổng tiền chi ra trong kỳ từ các đề nghị thanh toán đã duyệt.
     */
    private function getCashOutPeriod(array $filters): float
    {
        if (
            ! SchemaCache::hasTable('payment_requests') ||
            ! SchemaCache::hasColumn('payment_requests', 'amount')
        ) {
            return 0;
        }

        $dateColumn = $this->getPaymentRequestDateColumn();

        if (! $dateColumn) {
            return 0;
        }

        $query = DB::table('payment_requests');

        if (SchemaCache::hasColumn('payment_requests', 'status')) {
            $query->whereIn('status', [
                'accounting_approved',
                'approved',
                'completed',
                'paid',
            ]);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate($dateColumn, '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate($dateColumn, '<=', $filters['to_date']);
        }

        if (empty($filters['from_date']) && empty($filters['to_date'])) {
            $query->whereMonth($dateColumn, now()->month)
                ->whereYear($dateColumn, now()->year);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Lợi nhuận gộp của 10 đơn hàng gần nhất theo bộ lọc.
     */
    private function getRecentOrderProfits(array $filters)
    {
        if (
            ! SchemaCache::hasTable('crm_orders') ||
            ! SchemaCache::hasTable('crm_order_items') ||
            ! SchemaCache::hasTable('crm_product_catalog')
        ) {
            return collect();
        }

        $requiredColumns = [
            ['crm_order_items', 'order_id'],
            ['crm_order_items', 'product_id'],
            ['crm_order_items', 'quantity'],
            ['crm_order_items', 'line_total'],
            ['crm_product_catalog', 'price_agent'],
            ['crm_orders', 'id'],
            ['crm_orders', 'order_code'],
        ];

        foreach ($requiredColumns as [$table, $column]) {
            if (! SchemaCache::hasColumn($table, $column)) {
                return collect();
            }
        }

        $orderIds = $this->getFilteredOrdersQuery($filters)->pluck('id');

        if ($orderIds->isEmpty()) {
            return collect();
        }

        $orderDateColumn = SchemaCache::hasColumn('crm_orders', 'order_date') ? 'order_date' : 'created_at';

        return DB::table('crm_order_items as oi')
            ->join('crm_orders as o', 'o.id', '=', 'oi.order_id')
            ->join('crm_product_catalog as p', 'p.id', '=', 'oi.product_id')
            ->whereIn('oi.order_id', $orderIds)
            ->selectRaw('
                o.id as order_id,
                o.order_code as order_code,
                o.'.$orderDateColumn.' as order_date,
                SUM(COALESCE(oi.line_total, 0)) as sale_amount,
                SUM(COALESCE(oi.quantity, 0) * COALESCE(p.price_agent, 0)) as cost_amount,
                SUM(COALESCE(oi.line_total, 0)) - SUM(COALESCE(oi.quantity, 0) * COALESCE(p.price_agent, 0)) as gross_profit,
                ROUND(
                    (
                        (SUM(COALESCE(oi.line_total, 0)) - SUM(COALESCE(oi.quantity, 0) * COALESCE(p.price_agent, 0)))
                        / NULLIF(SUM(COALESCE(oi.line_total, 0)), 0)
                    ) * 100,
                    2
                ) as margin_percent
            ')
            ->groupBy('o.id', 'o.order_code', 'o.'.$orderDateColumn)
            ->orderByDesc('o.'.$orderDateColumn)
            ->limit(10)
            ->get();
    }
}
