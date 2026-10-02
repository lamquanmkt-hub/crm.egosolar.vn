{{-- Viên nhãn tròn của trang công trình; dùng 8 chỗ nên tách ra để khỏi lặp chuỗi utility.
     Số đo lấy từ `.pill-soft` / `.pill-date` trong khối <style> cũ của chính trang.

     Bảng tông viết thẳng trong `@class` chứ không qua mảng trong `@php`: `rules/blade-views.md`
     cấm `@php` trong view, và chuỗi lớp phải TĨNH trong mã nguồn thì Tailwind mới quét thấy.

     `date` là biến thể riêng của `.pill-date`: không `gap`, không in đậm, nền trắng đục. --}}
@props(['tone' => 'slate'])

<span {{ $attributes->class([
    'tw:inline-flex tw:items-center tw:py-[6px] tw:px-[10px] tw:rounded-[999px]',
    'tw:border tw:border-solid tw:text-[12px] tw:leading-none tw:whitespace-nowrap',
    'tw:gap-[6px] tw:font-bold' => $tone !== 'date',
    'tw:bg-[rgba(11,201,170,0.10)] tw:text-[#0f766e] tw:border-[rgba(11,201,170,0.22)]' => $tone === 'ego',
    'tw:bg-[rgba(100,116,139,0.10)] tw:text-[#334155] tw:border-[rgba(100,116,139,0.18)]' => $tone === 'slate',
    'tw:bg-[rgba(148,163,184,0.12)] tw:text-[#64748b] tw:border-[rgba(148,163,184,0.22)]' => $tone === 'muted',
    'tw:bg-[rgba(59,130,246,0.08)] tw:text-[#1d4ed8] tw:border-[rgba(59,130,246,0.18)]' => $tone === 'owner',
    'tw:bg-[rgba(255,255,255,0.9)] tw:text-[#0f172a] tw:border-[rgba(0,0,0,0.06)]' => $tone === 'date',
]) }}>{{ $slot }}</span>
