{{-- EGO_AI_ASSISTANT_PANEL_START --}}
<div class="crm-topbar__panel-wrap crm-ai-panel-wrap" data-crm-panel-wrap="ai">
    <button
        type="button"
        class="crm-topbar__action-button crm-ai-mobile-toggle"
        data-crm-panel-toggle="ai"
        aria-controls="crmAiPanel"
        aria-expanded="false"
        title="Tìm kiếm thông minh"
    >
        <span class="crm-topbar__action-icon"><i class="bi bi-search" aria-hidden="true"></i></span>
        <span class="crm-topbar__action-label">Tìm kiếm</span>
    </button>

    <section
        class="crm-topbar__panel crm-ai-panel"
        id="crmAiPanel"
        data-crm-panel="ai"
        data-chat-url="{{ route('ai-assistant.chat') }}"
        data-conversations-url="{{ route('ai-assistant.conversations') }}"
        data-conversation-template="{{ url('/ai-assistant/conversations/__ID__') }}"
        aria-hidden="true"
    >
        <header class="crm-topbar__panel-header crm-ai-panel__header">
            <div class="crm-ai-panel__identity">
                <span class="crm-ai-panel__logo"><i class="bi bi-search" aria-hidden="true"></i></span>
                <div>
                    <span class="crm-topbar__panel-kicker">EGO SEARCH • KHÔNG TỐN PHÍ API</span>
                    <h2>Tìm kiếm thông minh</h2>
                </div>
            </div>
            <div class="crm-topbar__panel-header-actions">
                <button type="button" class="crm-topbar__panel-icon" id="crmAiNewChat" aria-label="Lượt tìm kiếm mới" title="Lượt tìm kiếm mới">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                </button>
                <button type="button" class="crm-topbar__panel-icon" id="crmAiDeleteChat" aria-label="Xóa lịch sử này" title="Xóa lịch sử này" disabled>
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                </button>
                <button type="button" class="crm-topbar__panel-icon crm-topbar__panel-close" data-crm-panel-close aria-label="Đóng tìm kiếm">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        </header>

        <div class="crm-ai-panel__toolbar">
            <label for="crmAiConversationSelect">Lịch sử</label>
            <select id="crmAiConversationSelect" aria-label="Chọn lịch sử tìm kiếm">
                <option value="">Lượt tìm kiếm mới</option>
            </select>
            <span class="crm-ai-panel__company" title="Mặc định tìm trong công ty đang chọn">
                <i class="bi bi-buildings" aria-hidden="true"></i>
                {{ $activeCompanyName ?? session('active_company_name', 'Công ty hiện tại') }}
            </span>
        </div>

        <div class="crm-ai-panel__messages" id="crmAiMessages" aria-live="polite">
            <article class="crm-ai-message crm-ai-message--assistant">
                <span class="crm-ai-message__avatar"><i class="bi bi-search" aria-hidden="true"></i></span>
                <div class="crm-ai-message__bubble">
                    <strong>Tìm kiếm dữ liệu CRM bằng câu tiếng Việt.</strong>
                    <p>Hiện hỗ trợ Đề nghị thanh toán và Công việc. Hệ thống tự nhận đúng module trước khi lọc dữ liệu.</p>
                </div>
            </article>

            <div class="crm-ai-suggestions" id="crmAiSuggestions">
                <button type="button" data-ai-prompt="Có công việc nào tôi chưa làm không?">Việc tôi chưa làm</button>
                <button type="button" data-ai-prompt="Công việc nào của tôi đang quá hạn?">Việc quá hạn</button>
                <button type="button" data-ai-prompt="Các đề nghị thanh toán đang chờ duyệt trong tháng này">Phiếu chờ duyệt</button>
                <button type="button" data-ai-prompt="Tổng hợp đề nghị thanh toán tháng này">Tổng hợp thanh toán</button>
            </div>
        </div>

        <div class="crm-ai-panel__status" id="crmAiStatus" hidden></div>

        <form class="crm-ai-composer" id="crmAiForm">
            <textarea
                id="crmAiInput"
                rows="1"
                maxlength="2000"
                placeholder="Ví dụ: Có công việc nào tôi chưa làm không?"
                autocomplete="off"
            ></textarea>
            <button type="submit" id="crmAiSend" aria-label="Tìm kiếm" title="Tìm kiếm">
                <i class="bi bi-arrow-up" aria-hidden="true"></i>
            </button>
        </form>
        <div class="crm-ai-panel__disclaimer">Kết quả được lọc trực tiếp từ CRM theo quyền tài khoản. Không gọi OpenAI và không phát sinh chi phí API.</div>
    </section>
</div>
{{-- EGO_AI_ASSISTANT_PANEL_END --}}
