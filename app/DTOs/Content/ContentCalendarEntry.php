<?php

declare(strict_types=1);

namespace App\DTOs\Content;

/**
 * Một mục lịch nội dung đã sẵn sàng để in trên trang lịch biên tập (danh sách, agenda, kanban,
 * modal sửa). Dựng bởi {@see ContentCalendarPresenter::entry()}; view chỉ đọc thuộc tính.
 */
final readonly class ContentCalendarEntry
{
    /**
     * @param  list<string>  $assignees  tên người phụ trách, đã bỏ trùng (không phân biệt hoa thường)
     * @param  array<string, string>  $assigneeInitials  tên → chữ cái đầu in hoa
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public ?string $link,
        public string $linkHost,
        public string $status,
        public string $statusColor,
        public string $statusLabel,
        public ?string $contentType,
        public string $campaignText,
        public int $filesCount,
        public ?int $assigneeUserId,
        public ?string $assignee,
        public ?string $platform,
        public string $platformType,
        public string $platformAccount,
        public string $platformIcon,
        public string $platformLabel,
        public string $publishYmd,
        public string $publishDmy,
        public string $publishDm,
        public array $assignees,
        public array $assigneeInitials,
        public string $searchText,
    ) {}
}
