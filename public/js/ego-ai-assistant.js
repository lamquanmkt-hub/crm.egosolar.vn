(() => {
    'use strict';

    const panel = document.getElementById('crmAiPanel');
    if (!panel) return;

    const form = document.getElementById('crmAiForm');
    const input = document.getElementById('crmAiInput');
    const sendButton = document.getElementById('crmAiSend');
    const messages = document.getElementById('crmAiMessages');
    const status = document.getElementById('crmAiStatus');
    const select = document.getElementById('crmAiConversationSelect');
    const newButton = document.getElementById('crmAiNewChat');
    const deleteButton = document.getElementById('crmAiDeleteChat');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const urls = {
        chat: panel.dataset.chatUrl || '',
        conversations: panel.dataset.conversationsUrl || '',
        conversationTemplate: panel.dataset.conversationTemplate || '',
    };

    const storageKey = 'ego_ai_conversation_id';
    let conversationId = Number(localStorage.getItem(storageKey) || 0) || null;
    let busy = false;
    let initialized = false;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const endpointForConversation = (id) => urls.conversationTemplate.replace('__ID__', encodeURIComponent(id));

    const fetchJson = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrf ? {'X-CSRF-TOKEN': csrf} : {}),
                ...(options.headers || {}),
            },
            ...options,
        });

        let data = {};
        try { data = await response.json(); } catch (_) {}

        if (!response.ok) {
            throw new Error(data.message || `HTTP ${response.status}`);
        }

        return data;
    };

    const setStatus = (message = '', isError = false) => {
        if (!status) return;
        status.hidden = message === '';
        status.textContent = message;
        status.classList.toggle('is-error', isError);
    };

    const scrollBottom = () => {
        if (!messages) return;
        requestAnimationFrame(() => { messages.scrollTop = messages.scrollHeight; });
    };

    const renderActions = (actions) => {
        if (!Array.isArray(actions) || !actions.length) return '';
        const safe = actions
            .filter((item) => item && item.url && item.label)
            .slice(0, 8)
            .map((item) => `<a href="${escapeHtml(item.url)}"><i class="bi bi-box-arrow-up-right"></i>${escapeHtml(item.label)}</a>`)
            .join('');
        return safe ? `<div class="crm-ai-message__actions">${safe}</div>` : '';
    };

    const appendMessage = (role, content, actions = [], options = {}) => {
        const article = document.createElement('article');
        article.className = `crm-ai-message crm-ai-message--${role}`;
        if (options.id) article.dataset.messageId = String(options.id);

        const avatar = role === 'assistant'
            ? '<span class="crm-ai-message__avatar"><i class="bi bi-search" aria-hidden="true"></i></span>'
            : '';

        const bubbleContent = options.typing
            ? '<span class="crm-ai-typing"><span></span><span></span><span></span></span>'
            : `${escapeHtml(content)}${renderActions(actions)}`;

        article.innerHTML = `${avatar}<div class="crm-ai-message__bubble">${bubbleContent}</div>`;
        messages?.appendChild(article);
        scrollBottom();
        return article;
    };

    const resetMessages = () => {
        if (!messages) return;
        messages.innerHTML = `
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
            </div>`;
    };

    const updateConversationState = (id) => {
        conversationId = id ? Number(id) : null;
        if (conversationId) localStorage.setItem(storageKey, String(conversationId));
        else localStorage.removeItem(storageKey);
        if (select) select.value = conversationId ? String(conversationId) : '';
        if (deleteButton) deleteButton.disabled = !conversationId;
    };

    const loadConversations = async () => {
        if (!urls.conversations || !select) return;
        try {
            const data = await fetchJson(urls.conversations, {method: 'GET'});
            const items = Array.isArray(data.conversations) ? data.conversations : [];
            select.innerHTML = '<option value="">Lượt tìm kiếm mới</option>' + items.map((item) => (
                `<option value="${escapeHtml(item.id)}">${escapeHtml(item.title || 'Lượt tìm kiếm')}</option>`
            )).join('');
            select.value = conversationId ? String(conversationId) : '';
        } catch (_) {}
    };

    const loadConversation = async (id) => {
        if (!id) {
            updateConversationState(null);
            resetMessages();
            setStatus('');
            return;
        }

        setStatus('Đang tải lịch sử…');
        try {
            const data = await fetchJson(endpointForConversation(id), {method: 'GET'});
            messages.innerHTML = '';
            (data.messages || []).forEach((item) => appendMessage(item.role, item.content, item.actions || [], {id: item.id}));
            if (!(data.messages || []).length) resetMessages();
            updateConversationState(id);
            setStatus('');
        } catch (error) {
            updateConversationState(null);
            resetMessages();
            setStatus(error.message || 'Không tải được lịch sử.', true);
        }
    };

    const sendPrompt = async (prompt) => {
        const text = String(prompt || '').trim();
        if (!text || busy) return;

        busy = true;
        sendButton.disabled = true;
        input.disabled = true;
        setStatus('Đang lọc dữ liệu CRM…');
        messages?.querySelector('.crm-ai-suggestions')?.remove();
        appendMessage('user', text);
        input.value = '';
        input.style.height = 'auto';
        const typing = appendMessage('assistant', '', [], {typing: true});

        try {
            const data = await fetchJson(urls.chat, {
                method: 'POST',
                body: JSON.stringify({
                    message: text,
                    conversation_id: conversationId,
                }),
            });

            typing.remove();
            appendMessage('assistant', data.answer || 'Không có nội dung phản hồi.', data.actions || [], {id: data.message_id});
            updateConversationState(data.conversation_id);
            await loadConversations();
            setStatus('');
        } catch (error) {
            typing.remove();
            appendMessage('assistant', error.message || 'Tìm kiếm thông minh đang tạm thời không phản hồi.');
            setStatus(error.message || 'Có lỗi khi tìm kiếm dữ liệu.', true);
        } finally {
            busy = false;
            sendButton.disabled = false;
            input.disabled = false;
            input.focus();
        }
    };

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        sendPrompt(input.value);
    });

    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form?.requestSubmit();
        }
    });

    input?.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
    });

    messages?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-ai-prompt]');
        if (!button) return;
        sendPrompt(button.dataset.aiPrompt || '');
    });

    select?.addEventListener('change', () => loadConversation(select.value));

    newButton?.addEventListener('click', () => {
        updateConversationState(null);
        resetMessages();
        setStatus('');
        input?.focus();
    });

    deleteButton?.addEventListener('click', async () => {
        if (!conversationId || busy) return;
        if (!window.confirm('Xóa lịch sử tìm kiếm này?')) return;

        try {
            await fetchJson(endpointForConversation(conversationId), {method: 'DELETE'});
            updateConversationState(null);
            resetMessages();
            await loadConversations();
            setStatus('Đã xóa cuộc trò chuyện.');
        } catch (error) {
            setStatus(error.message || 'Không xóa được cuộc trò chuyện.', true);
        }
    });

    document.querySelectorAll('[data-crm-panel-toggle="ai"], [data-crm-open-panel="ai"]').forEach((button) => {
        button.addEventListener('click', async () => {
            window.setTimeout(() => input?.focus(), 130);
            if (!initialized) {
                initialized = true;
                await loadConversations();
                if (conversationId) await loadConversation(conversationId);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            const trigger = document.querySelector('[data-crm-panel-toggle="ai"]');
            trigger?.click();
        }
    });
})();
