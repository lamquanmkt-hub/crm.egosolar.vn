@php
    $debtFiles = collect($item->files ?? []);
@endphp
<div class="tw:mt-3 tw:border tw:border-solid tw:border-[#dbeafe] tw:rounded-2xl tw:bg-[#f8fbff] tw:p-3">
    <div class="tw:flex tw:justify-between tw:items-center tw:gap-[10px] tw:mb-[9px]">
        <div>
            <div class="tw:text-[13px] tw:font-[900] tw:text-[#0f172a]">Tệp công nợ</div>
            <div class="tw:text-[11px] tw:font-bold tw:text-[#64748b]">Chọn tệp rồi bấm “Thêm tệp”. Có thể xóa từng tệp bên dưới.</div>
        </div>
    </div>

    <form method="POST" action="{{ route('finance.supplier-debts.files.store', $item->id) }}" enctype="multipart/form-data" class="tw:grid tw:min-[901px]:[grid-template-columns:1fr_auto] tw:max-[901px]:[grid-template-columns:1fr] tw:gap-2 tw:items-center tw:mb-[10px]">
        @csrf
        <div class="tw:flex tw:items-center tw:gap-2 tw:min-h-10 tw:border tw:[border-style:dashed] tw:border-[#bfdbfe] tw:rounded-xl tw:bg-[#ffffff] tw:py-[6px] tw:px-2 tw:overflow-hidden tw:[&_input]:w-full tw:[&_input]:text-[12px]">
            <input name="attachments[]" type="file" multiple required aria-label="Chọn tệp để tải lên" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
        </div>
        <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:[border:0] tw:text-[#ffffff] tw:[background:linear-gradient(135deg,#2563eb,#1d4ed8)] tw:shadow-[0_16px_34px_rgba(37,99,235,0.22)]" type="submit" style="height:40px">+ Thêm tệp</button>
    </form>

    <div class="tw:grid tw:gap-[7px]">
        @forelse($debtFiles as $file)
            <div class="tw:grid tw:min-[901px]:[grid-template-columns:1fr_auto_auto] tw:max-[901px]:[grid-template-columns:1fr] tw:gap-2 tw:items-center tw:border tw:border-solid tw:border-[#e2e8f0] tw:rounded-xl tw:bg-[#ffffff] tw:py-2 tw:px-[9px]">
                <div>
                    <div class="tw:text-[#0f172a] tw:text-[12px] tw:font-extrabold tw:[word-break:break-word]">{{ $file->original_name ?? basename($file->path ?? '') }}</div>
                    <div class="tw:mt-[2px] tw:text-[#64748b] tw:text-[11px] tw:font-semibold">
                        {{ !empty($file->size) ? number_format(((float) $file->size) / 1024, 1, ',', '.') . ' KB' : 'Tệp đính kèm' }}
                    </div>
                </div>

                <a class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#1d4ed8] tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7]" href="{{ route('finance.supplier-debts.files.download', $file->id) }}" style="height:34px;text-decoration:none">Tải</a>

                <form method="POST" action="{{ route('finance.supplier-debts.files.destroy', $file->id) }}" onsubmit="return confirm('Xóa tệp này?')" style="margin:0">
                    @csrf
                    @method('DELETE')
                    <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#be123c] tw:bg-[#fff1f2] tw:border tw:border-solid tw:border-[#fecdd3]" type="submit" style="height:34px;padding:0 10px">Xóa</button>
                </form>
            </div>
        @empty
            <div class="tw:p-[9px] tw:rounded-xl tw:bg-[#ffffff] tw:text-[#64748b] tw:text-[12px] tw:font-bold">Chưa có tệp nào.</div>
        @endforelse
    </div>
</div>
