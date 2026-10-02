@extends('layouts.app')

@section('content')
<div class="tw:w-full tw:mx-auto tw:px-6 tw:mt-4">

    {{-- HEADER --}}
    <div class="tw:flex tw:justify-between tw:items-center tw:mb-4">
        <div>
            <h4 class="tw:font-bold tw:mb-0">Ngân sách & Chỉ số Marketing</h4>
            <small class="tw:text-[rgba(33,37,41,0.75)]">Marketing / Quảng cáo</small>
        </div>
    </div>

    @if (session('success'))
        <x-ui.alert variant="success" class="tw:py-2">{{ session('success') }}</x-ui.alert>
    @endif

    {{-- KPI TỔNG --}}
    <div class="tw:row tw:g-3 tw:mb-4">
        <div class="tw:md:col12-3">
            <x-ui.card class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
                <x-ui.card-body>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Ngân sách</div>
                    <div class="tw:text-[20px] tw:font-bold">{{ number_format($totalBudget) }} đ</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:md:col12-3">
            <x-ui.card class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
                <x-ui.card-body>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Đã chi (ngân sách)</div>
                    <div class="tw:text-[20px] tw:font-bold">{{ number_format($totalSpent) }} đ</div>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Chi theo chỉ số: {{ number_format($sumSpend) }} đ</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:md:col12-2">
            <x-ui.card class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
                <x-ui.card-body>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Lead</div>
                    <div class="tw:text-[20px] tw:font-bold">{{ number_format($sumLeads) }}</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:md:col12-2">
            <x-ui.card class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
                <x-ui.card-body>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Đơn</div>
                    <div class="tw:text-[20px] tw:font-bold">{{ number_format($sumOrders) }}</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:md:col12-2">
            <x-ui.card class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
                <x-ui.card-body>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">ROAS</div>
                    <div class="tw:text-[20px] tw:font-bold">{{ $roas }}</div>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">CPL {{ number_format($cpl) }} | CPO {{ number_format($cpo) }}</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>
    </div>

    {{-- THANH ĐIỀU KHIỂN --}}
    <div class="tw:flex tw:flex-wrap tw:gap-2 tw:justify-between tw:items-center tw:mb-4">
        <div class="tw:flex tw:gap-2">
            <x-ui.button variant="outline-secondary" type="button"
                          x-on:click="$dispatch('toggle-disclosure', 'budgetSummary')">
                Tổng hợp ngân sách
            </x-ui.button>

            <x-ui.button variant="success" type="button"
                          x-on:click="$dispatch('open-modal', 'adsModal')">
                + Thêm
            </x-ui.button>
        </div>
        <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Xem tổng → lọc → nhập</div>
    </div>

    {{-- TỔNG HỢP NGÂN SÁCH --}}
<x-ui.disclosure name="budgetSummary" :open="$hasFilter">
        <x-ui.card class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:mb-4">
            <x-ui.card-header class="tw:bg-white tw:font-semibold">Tổng hợp theo tháng & kênh</x-ui.card-header>
            <x-ui.table-wrap>
                <x-ui.table size="sm" hover class="tw:mb-0">
                    <x-ui.table-head>
                        <tr>
                            <th>Khoảng</th>
                            <th>Kênh</th>
                            <th class="tw:text-right">Ngân sách</th>
                            <th class="tw:text-right">Đã chi</th>
                            <th class="tw:text-right">% tiêu</th>
                        </tr>
                    </x-ui.table-head>
                    <tbody>
                        @forelse($summary as $s)
                            <tr>
                                <td>{{ $s->monthText }}</td>
                                <td>{{ $s->platform }}</td>
                                <td class="tw:text-right">{{ number_format($s->totalBudget) }}</td>
                                <td class="tw:text-right">{{ number_format($s->totalSpent) }}</td>
                                <td class="tw:text-right">{{ $s->percentSpent }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]">Chưa có dữ liệu</td></tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.table-wrap>
        </x-ui.card>
</x-ui.disclosure>

    {{-- LỌC --}}
    <x-ui.card class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:mb-4">
    <x-ui.card-body>
        <form class="tw:row tw:g-2 tw:items-end" method="GET">
            <div class="tw:md:col12-3">
                <x-ui.label class="tw:text-[0.875em]">Từ ngày</x-ui.label>
                <x-ui.input type="text" name="from" value="{{ $from ?? '' }}" placeholder="dd/mm/yyyy" />
            </div>

            <div class="tw:md:col12-3">
                <x-ui.label class="tw:text-[0.875em]">Đến ngày</x-ui.label>
                <x-ui.input type="text" name="to" value="{{ $to ?? '' }}" placeholder="dd/mm/yyyy" />
            </div>

            <div class="tw:md:col12-3">
                <x-ui.label class="tw:text-[0.875em]">Kênh</x-ui.label>
                <x-ui.select name="platform">
                    <option value="">-- Tất cả --</option>
                    @foreach(['Facebook','Google','TikTok','Zalo','Khác'] as $p)
                        <option value="{{ $p }}" {{ ($platform ?? '')==$p?'selected':'' }}>{{ $p }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="tw:md:col12-3">
                <x-ui.label class="tw:text-[0.875em]">Chiến dịch (từ kho campaign)</x-ui.label>
                <x-ui.select name="campaign_id">
                    <option value="">-- Tất cả --</option>
                    @foreach($campaigns as $c)
                        <option value="{{ $c->id }}" {{ (string)($campaign_id ?? '') === (string)$c->id ? 'selected':'' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="tw:md:col12-2 tw:flex tw:gap-2">
                <x-ui.button variant="outline-secondary" class="tw:w-full" type="submit">Lọc</x-ui.button>
                <x-ui.button variant="light" class="tw:w-full" :href="route('marketing.budget')">Xóa</x-ui.button>
            </div>

            {{-- legacy month (nếu còn link cũ), không cần hiển thị --}}
            @if($legacyMonth !== null)
                <input type="hidden" name="month" value="{{ $legacyMonth }}">
            @endif
        </form>

        <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:mt-2">
            Định dạng: <b>dd/mm/yyyy</b>. Nếu không nhập sẽ mặc định tính <b>cả tháng hiện tại</b>.
        </div>
    </x-ui.card-body>
</x-ui.card>


    {{-- BẢNG NGÂN SÁCH --}}
    <x-ui.card class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
        <x-ui.card-header class="tw:bg-white tw:font-semibold">Danh sách ngân sách</x-ui.card-header>
        <x-ui.table-wrap>
            <x-ui.table hover class="tw:mb-0">
                <x-ui.table-head>
                    <tr>
                        <th>Tháng</th>
                        <th>Kênh</th>
                        <th>Chiến dịch</th>
                        <th class="tw:text-right">Ngân sách</th>
                        <th class="tw:text-right">Đã chi</th>
                        @if($canManage)
                            <th class="tw:text-right">Thao tác</th>
                        @endif
                    </tr>
                </x-ui.table-head>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r->monthText }}</td>
                            <td>{{ $r->platform }}</td>
                            <td>{{ $r->campaignName }}</td>
                            <td class="tw:text-right">{{ number_format($r->budget) }}</td>
                            <td class="tw:text-right">{{ number_format($r->actualSpent) }}</td>

                            @if($canManage)
                                <td class="tw:text-right">
                                    <x-ui.button variant="outline-primary" size="sm" :href="route('marketing.budget.edit',$r->id)">Sửa</x-ui.button>

                                    <form action="{{ route('marketing.budget.destroy', $r->id) }}"
                                          method="POST"
                                          class="tw:inline"
                                          onsubmit="return confirm('Xóa dòng ngân sách này?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="outline-danger" size="sm" type="submit">Xóa</x-ui.button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-4">Chưa có dữ liệu</td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.table-wrap>
        <x-ui.card-body>{{ $rows->links() }}</x-ui.card-body>
    </x-ui.card>

    {{-- CHỈ SỐ GẦN ĐÂY --}}
    <x-ui.card class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:mt-6">
        <x-ui.card-header class="tw:bg-white tw:font-semibold">Chỉ số marketing (gần đây)</x-ui.card-header>
        <x-ui.table-wrap>
            <x-ui.table hover class="tw:mb-0">
                <x-ui.table-head>
                    <tr>
                        <th>Ngày</th>
                        <th>Kênh</th>
                        <th>Chiến dịch</th>
                        <th class="tw:text-right">Chi</th>
                        <th class="tw:text-right">Reach</th>
                        <th class="tw:text-right">Lead</th>
                        <th>Giới tính</th>
                        <th>Độ tuổi</th>
                        <th>Khu vực</th>
                        <th class="tw:text-right" style="width:160px;">Thao tác</th>
                    </tr>
                </x-ui.table-head>
                <tbody>
                    @forelse($metricRows as $m)
                        <tr>
                            <td>
                                {{ $m->dateFromText }}
                                @if($m->dateToText !== '') - {{ $m->dateToText }} @endif
                            </td>
                            <td>{{ $m->platform }}</td>

                            {{-- ✅ FIX: đúng campaign theo từng dòng metrics --}}
                            <td>{{ $m->campaignName }}</td>

                            <td class="tw:text-right">{{ number_format($m->spend) }}</td>
                            <td class="tw:text-right">{{ number_format($m->reach) }}</td>
                            <td class="tw:text-right">{{ number_format($m->leads) }}</td>

                            <td>{{ $m->genderText }}</td>
                            <td>{{ $m->ageText }}</td>
                            <td>{{ $m->regionText }}</td>

                            <td class="tw:text-right">
                                @if($canManage)
                                    <x-ui.button variant="outline-primary" size="sm" :href="route('marketing.metrics.edit', $m->id)">Sửa</x-ui.button>

                                    <form action="{{ route('marketing.metrics.destroy', $m->id) }}"
                                          method="POST"
                                          class="tw:inline"
                                          onsubmit="return confirm('Xóa chỉ số này nhé?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="outline-danger" size="sm" type="submit">Xóa</x-ui.button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-4">Chưa có dữ liệu</td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.table-wrap>
    </x-ui.card>

    {{-- CAMPAIGN TỔNG HỢP --}}
    <x-ui.card class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:mt-6">
        <x-ui.card-header class="tw:bg-white tw:font-semibold">Campaign tổng hợp (Ngân sách + Chỉ số)</x-ui.card-header>
        <x-ui.table-wrap>
            <x-ui.table hover class="tw:align-middle tw:mb-0">
                <x-ui.table-head>
                    <tr>
                        <th>Tháng</th>
                        <th>Kênh</th>
                        <th>Chiến dịch</th>
                        <th class="tw:text-right">Ngân sách</th>
                        <th class="tw:text-right">Đã chi (NS)</th>
                        <th class="tw:text-right">Chi tiêu (chỉ số)</th>
                        <th class="tw:text-right">Reach</th>
                        <th class="tw:text-right">Lead</th>
                    </tr>
                </x-ui.table-head>
                <tbody>
    {{-- $cc chứ không phải $c: $c đã là biến vòng lặp của các dropdown chiến dịch trong trang --}}
    @forelse($campaignCombined as $cc)
        <tr>
            <td>
                @if($month)
                    {{ $cc->monthText }}
                @else
                    {{ $cc->monthRaw }}
                @endif
            </td>
            <td>{{ $cc->platform }}</td>
            <td>{{ $cc->campaignName }}</td>
            <td class="tw:text-right">{{ number_format($cc->budget) }}</td>
            <td class="tw:text-right">{{ number_format($cc->budgetSpent) }}</td>
            <td class="tw:text-right">{{ number_format($cc->spend) }}</td>
            <td class="tw:text-right">{{ number_format($cc->reach) }}</td>
            <td class="tw:text-right">{{ number_format($cc->leads) }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-4">Chưa có dữ liệu</td></tr>
    @endforelse
</tbody>
            </x-ui.table>
        </x-ui.table-wrap>
    </x-ui.card>

</div>

{{-- MODAL: Ngân sách + Chỉ số + Chiến dịch --}}
<x-ui.modal name="adsModal" size="lg"
            title="Thêm dữ liệu Marketing"
            subtitle="Chọn tab Ngân sách hoặc Chỉ số hoặc Chiến dịch">

    <x-ui.tabs :tabs="['budget' => 'Ngân sách', 'metric' => 'Chỉ số', 'campaign' => 'Chiến dịch']">

                    {{-- TAB: NGÂN SÁCH --}}
                    <x-ui.tab-panel name="budget">
                        <form method="POST" action="{{ route('marketing.budget.store') }}" class="tw:row tw:g-2">
                            @csrf

                            <div class="tw:md:col12-3">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Tháng</x-ui.label>
                                <x-ui.input type="month" name="month" required />
                            </div>

                            <div class="tw:md:col12-3">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Kênh</x-ui.label>
                                <x-ui.select name="platform" required>
                                    @foreach(['Facebook','Google','TikTok','Zalo','Khác'] as $p)
                                        <option value="{{ $p }}">{{ $p }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="tw:md:col12-6">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Chiến dịch</x-ui.label>
                                <x-ui.select name="campaign_id" required>
                                    <option value="">-- Chọn chiến dịch --</option>
                                    @foreach($campaigns as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="tw:md:col12-3">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Ngân sách (đ)</x-ui.label>
                                <x-ui.input type="number" name="budget" min="0" required />
                            </div>

                            <div class="tw:md:col12-3">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Đã chi (đ)</x-ui.label>
                                <x-ui.input type="number" name="actual_spent" min="0" value="0" />
                            </div>

                            <div class="tw:md:col12-6">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Ghi chú</x-ui.label>
                                <x-ui.input type="text" name="note" placeholder="Tuỳ chọn" />
                            </div>

                            <div class="tw:col12-12 tw:flex tw:justify-end tw:gap-2 tw:mt-2">
                                <x-ui.button variant="light" type="button"
                                              x-on:click="$dispatch('close-modal', 'adsModal')">Đóng</x-ui.button>
                                <x-ui.button variant="success" type="submit">Lưu ngân sách</x-ui.button>
                            </div>
                        </form>
                    </x-ui.tab-panel>

                    {{-- TAB: CHỈ SỐ --}}
                    <x-ui.tab-panel name="metric">
                        <form method="POST" action="{{ route('marketing.metrics.store') }}" class="tw:row tw:g-2">
                            @csrf

                            <div class="tw:md:col12-3">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Từ ngày</x-ui.label>
                                <x-ui.input type="date" name="date_from" required />
                            </div>

                            <div class="tw:md:col12-3">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Đến ngày</x-ui.label>
                                <x-ui.input type="date" name="date_to" required />
                            </div>

                            <div class="tw:md:col12-3">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Kênh</x-ui.label>
                                <x-ui.select name="platform" required>
                                    @foreach(['Facebook','Google','TikTok','Zalo','Khác'] as $p)
                                        <option value="{{ $p }}">{{ $p }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="tw:md:col12-3">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Chiến dịch</x-ui.label>
                                <x-ui.select name="campaign_id" required>
                                    <option value="">-- Chọn chiến dịch --</option>
                                    @foreach($campaigns as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="tw:md:col12-4">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Reach (tiếp cận)</x-ui.label>
                                <x-ui.input type="number" name="reach" min="0" required />
                            </div>

                            <div class="tw:md:col12-4">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Số lead</x-ui.label>
                                <x-ui.input type="number" name="leads" min="0" required />
                            </div>

                            <div class="tw:md:col12-4">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Chi tiêu (nếu có)</x-ui.label>
                                <x-ui.input type="number" name="spend" min="0" value="0" />
                            </div>

                            <div class="tw:col12-12">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Ghi chú</x-ui.label>
                                <x-ui.input type="text" name="note" placeholder="Tuỳ chọn" />
                            </div>

                            <div class="tw:col12-12 tw:flex tw:justify-end tw:gap-2 tw:mt-2">
                                <x-ui.button variant="light" type="button"
                                              x-on:click="$dispatch('close-modal', 'adsModal')">Đóng</x-ui.button>
                                <x-ui.button variant="primary" type="submit">Lưu chỉ số</x-ui.button>
                            </div>
                        </form>
                    </x-ui.tab-panel>

                    {{-- TAB: CHIẾN DỊCH --}}
                    <x-ui.tab-panel name="campaign">
                        <form method="POST" action="{{ route('marketing.campaigns.store') }}" class="tw:row tw:g-2">
                            @csrf

                            <div class="tw:md:col12-6">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Tên chiến dịch</x-ui.label>
                                <x-ui.input type="text" name="name" placeholder="VD: Goodwe 5kw" required />
                            </div>

                            <div class="tw:md:col12-6">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Kênh</x-ui.label>
                                <x-ui.select name="platform" required>
                                    @foreach(['Facebook','Google','TikTok','Zalo','Khác'] as $p)
                                        <option value="{{ $p }}">{{ $p }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="tw:col12-12">
                                <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Ghi chú</x-ui.label>
                                <x-ui.input type="text" name="note" placeholder="Tuỳ chọn" />
                            </div>

                            <div class="tw:col12-12 tw:flex tw:justify-end tw:gap-2 tw:mt-2">
                                <x-ui.button variant="light" type="button"
                                              x-on:click="$dispatch('close-modal', 'adsModal')">Đóng</x-ui.button>
                                <x-ui.button variant="primary" type="submit">Tạo chiến dịch</x-ui.button>
                            </div>
                        </form>
                    </x-ui.tab-panel>

    </x-ui.tabs>

</x-ui.modal>

@endsection
