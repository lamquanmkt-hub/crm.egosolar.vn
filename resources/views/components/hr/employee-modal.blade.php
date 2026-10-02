{{-- Vỏ popup của trang nhân viên. Bật/tắt bằng class `show` để dùng lại đúng JS cũ của trang
     (`openEmpModal`/`hideEmpModal`), không kéo Bootstrap JS về. --}}
@props(['id', 'title', 'small' => false])

<div id="{{ $id }}"
     data-ego-emp-modal
     class="tw:fixed tw:inset-0 tw:z-[9999] tw:hidden tw:items-center tw:justify-center tw:bg-[rgba(15,23,42,.52)] tw:p-[18px] tw:[&.show]:flex"
     onclick="closeEmpModal(event, '{{ $id }}')">
    <div class="tw:max-h-[92vh] tw:w-full tw:overflow-auto tw:rounded-[22px] tw:border tw:border-solid tw:border-[#e2e8f0] tw:bg-white tw:shadow-[0_30px_90px_rgba(15,23,42,.28)] {{ $small ? 'tw:max-w-[640px]' : 'tw:max-w-[980px]' }}"
         onclick="event.stopPropagation()">
        <div class="tw:sticky tw:top-0 tw:z-[2] tw:flex tw:items-center tw:justify-between tw:gap-3 tw:border-b tw:border-solid tw:border-[#dbeafe] tw:bg-[linear-gradient(135deg,#eff6ff,#ecfeff)] tw:px-[18px] tw:py-4">
            <h3 class="tw:m-0 tw:text-lg tw:font-extrabold tw:text-[#0f172a]">{{ $title }}</h3>
            <x-ui.close-button in="modal" onclick="hideEmpModal('{{ $id }}')" />
        </div>

        {{ $slot }}
    </div>
</div>
