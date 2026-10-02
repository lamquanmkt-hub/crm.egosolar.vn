(() => {
    'use strict';

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    document.querySelectorAll('[data-permission-form]').forEach((form) => {
        const items = [...form.querySelectorAll('[data-permission-item]')];
        const search = form.closest('.ego-settings-card')?.querySelector('[data-permission-search]');
        const checkAll = form.closest('.ego-settings-card')?.querySelector('[data-check-all]');
        const uncheckAll = form.closest('.ego-settings-card')?.querySelector('[data-uncheck-all]');

        const visibleCheckboxes = () => items
            .filter((item) => item.style.display !== 'none')
            .flatMap((item) => [...item.querySelectorAll('input[type="checkbox"]')])
            .filter((input) => !input.disabled);

        search?.addEventListener('input', () => {
            const query = normalize(search.value);

            items.forEach((item) => {
                const haystack = normalize(item.dataset.search || item.textContent);
                item.style.display = !query || haystack.includes(query) ? '' : 'none';
            });
        });

        checkAll?.addEventListener('click', () => {
            visibleCheckboxes().forEach((input) => { input.checked = true; });
        });

        uncheckAll?.addEventListener('click', () => {
            visibleCheckboxes().forEach((input) => { input.checked = false; });
        });

        /*
         | Ma trận CRUD: nút "tất cả" ở đầu mỗi HÀNG (một trang) và mỗi CỘT
         | (một thao tác). Trước đây markup có nút nhưng chưa có xử lý nên bấm
         | không có gì xảy ra — với 60+ trang × 4 thao tác thì tick tay là cực hình.
         |
         | Quy tắc: nếu còn ô nào chưa tick thì tick hết; đã tick đủ thì bỏ hết.
         | Chỉ đụng vào ô đang HIỆN (không bị bộ lọc tìm kiếm ẩn đi) và không bị
         | khoá — bấm "tất cả" mà lại bật cả những dòng người dùng vừa lọc ra
         | ngoài tầm nhìn là chuyện bất ngờ khó chịu.
         */
        const crudCells = (selector) => [...form.querySelectorAll(selector)]
            .filter((input) => !input.disabled && input.closest('[data-permission-item]')?.style.display !== 'none');

        const toggleCells = (cells) => {
            if (!cells.length) return;
            const turnOn = cells.some((input) => !input.checked);
            cells.forEach((input) => { input.checked = turnOn; });
        };

        form.querySelectorAll('[data-crud-row]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = CSS.escape(button.dataset.crudRow);
                toggleCells(crudCells(`[data-crud-cell][data-crud-cell-row="${row}"]`));
            });
        });

        form.querySelectorAll('[data-crud-column]').forEach((button) => {
            button.addEventListener('click', () => {
                const column = CSS.escape(button.dataset.crudColumn);
                toggleCells(crudCells(`[data-crud-cell][data-crud-cell-column="${column}"]`));
            });
        });

        // Vai trò admin khoá toàn bộ ô tick -> nút "tất cả" cũng vô nghĩa, ẩn đi
        // thay vì để người dùng bấm mãi không thấy gì.
        if (form.querySelector('[data-crud-cell]') && !form.querySelector('[data-crud-cell]:not(:disabled)')) {
            form.querySelectorAll('[data-crud-row], [data-crud-column]').forEach((button) => {
                button.hidden = true;
            });
            form.querySelector('.ego-crud-matrix-wrap')?.classList.add('is-locked');
        }

        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"], button:not([type])');
            if (!button) return;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang lưu...';
        });
    });

    const userSearch = document.querySelector('[data-user-search]');
    const userCards = [...document.querySelectorAll('[data-user-name]')];

    userSearch?.addEventListener('input', () => {
        const query = normalize(userSearch.value);
        userCards.forEach((card) => {
            card.style.display = !query || normalize(card.dataset.userName).includes(query) ? '' : 'none';
        });
    });
})();
