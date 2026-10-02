{{--
    EGO_VIEW_CHET — VIEW CHẾT, KHÔNG AI RENDER (rà soát 2026-09-04)

    Không @include nào gọi partial này. products/serials dựng modal ngay trong tệp.

    Partial mồ côi.

    CHƯA XOÁ theo yêu cầu: chỉ đánh dấu để lần sau khỏi rà lại.
    Nếu bạn đấu view này vào một route/@include, hãy XOÁ dấu này —
    tests/Feature/View/DeadViewsMarkedTest.php sẽ báo đỏ để nhắc.
--}}
<div class="modal fade" id="serialModal" tabindex="-1" aria-labelledby="serialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary tw:text-[#ffffff]">
                <h5 class="modal-title" id="serialModalLabel">
                    <i class="bi bi-upc-scan"></i>
                    Nhập Serial/IMEI - <span id="serial-modal-warehouse-name"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <x-ui.alert variant="info" class="tw:flex tw:items-start">
                    <i class="bi bi-info-circle-fill tw:mr-2 tw:mt-1"></i>
                    <div>
                        <strong>Hướng dẫn:</strong>
                        <ul class="tw:mb-0 tw:mt-1 ps-3">
                            <li>Nhập mỗi serial trên một dòng</li>
                            <li>Serial sẽ tự động chuyển thành <strong>IN HOA</strong></li>
                            <li>Chỉ chấp nhận: A-Z, 0-9, dấu gạch (- _ /)</li>
                            <li>Độ dài: 6 - 50 ký tự</li>
                        </ul>
                    </div>
                </x-ui.alert>

                <div id="serial-errors" style="display:none;"></div>

                <div class="tw:flex tw:justify-between tw:items-center tw:mb-2">
                    <label class="form-label tw:mb-0">
                        <strong>Đã nhập: <span id="serial-entered-count" class="tw:text-[#0d6efd]">0</span> serial</strong>
                    </label>
                </div>

                <textarea id="serial-textarea"
                          class="form-control font-monospace"
                          rows="8"
                          placeholder="56000NAW258L1292&#10;56000NAW258L1293&#10;..."></textarea>

                <div id="serial-list-preview" class="tw:mt-4"></div>

                <div class="tw:flex tw:gap-2 tw:mt-4">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-serial-paste">
                        <i class="bi bi-clipboard"></i> Paste từ clipboard
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-serial-clear">
                        <i class="bi bi-trash"></i> Xóa tất cả
                    </button>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x"></i> Hủy
                </button>
                <button type="button" class="btn btn-primary" id="btn-serial-save">
                    <i class="bi bi-check-lg"></i> Lưu Serial
                </button>
            </div>
        </div>
    </div>
</div>
