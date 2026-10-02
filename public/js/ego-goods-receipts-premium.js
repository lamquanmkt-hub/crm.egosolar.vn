(function () {
    'use strict';

    var products = [];
    var oldItems = [];
    var activeRow = null;
    var activeFilter = 'all';

    function normalize(value) {
        return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function money(value) {
        return Math.round(Number(value) || 0).toLocaleString('vi-VN') + ' đ';
    }

    function quantity(value) {
        return (Number(value) || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
    }

    function parseJsonNode(id) {
        var node = document.getElementById(id);
        if (!node) return [];
        try {
            return JSON.parse(node.textContent || '[]');
        } catch (error) {
            console.error('Không đọc được dữ liệu JSON: ' + id, error);
            return [];
        }
    }

    function findProduct(id) {
        var numericId = Number(id || 0);
        return products.find(function (product) { return Number(product.id) === numericId; }) || null;
    }

    function reindexItems() {
        var rows = document.querySelectorAll('#grItems .receipt-item-row');
        rows.forEach(function (row, index) {
            var number = row.querySelector('[data-row-number]');
            if (number) number.textContent = String(index + 1);
            row.querySelectorAll('[data-name]').forEach(function (field) {
                field.name = 'items[' + index + '][' + field.dataset.name + ']';
            });
        });

        var counter = document.querySelector('[data-item-count]');
        if (counter) counter.textContent = String(rows.length);
    }

    function calculateRow(row) {
        if (!row) return;
        var qty = parseFloat((row.querySelector('[data-name="qty"]') || {}).value || 0);
        var price = parseFloat((row.querySelector('[data-name="unit_price"]') || {}).value || 0);
        var vat = parseFloat((row.querySelector('[data-name="vat_percent"]') || {}).value || 0);
        var total = qty * price * (1 + vat / 100);
        var target = row.querySelector('[data-total]');
        if (target) target.value = money(total);
    }

    function calculateTotals() {
        var subtotal = 0;
        var vatTotal = 0;

        document.querySelectorAll('#grItems .receipt-item-row').forEach(function (row) {
            var qty = parseFloat((row.querySelector('[data-name="qty"]') || {}).value || 0);
            var price = parseFloat((row.querySelector('[data-name="unit_price"]') || {}).value || 0);
            var vat = parseFloat((row.querySelector('[data-name="vat_percent"]') || {}).value || 0);
            var base = qty * price;
            subtotal += base;
            vatTotal += base * vat / 100;
            calculateRow(row);
        });

        var subtotalEl = document.querySelector('[data-receipt-subtotal]');
        var vatEl = document.querySelector('[data-receipt-vat]');
        var totalEl = document.querySelector('[data-receipt-total]');
        if (subtotalEl) subtotalEl.textContent = money(subtotal);
        if (vatEl) vatEl.textContent = money(vatTotal);
        if (totalEl) totalEl.textContent = money(subtotal + vatTotal);
    }

    function resetProductRow(row) {
        var input = row.querySelector('[data-product-id]');
        if (input) input.value = '';

        var trigger = row.querySelector('[data-product-trigger]');
        if (trigger) {
            trigger.classList.remove('is-selected');
            var name = trigger.querySelector('[data-product-name]');
            var meta = trigger.querySelector('[data-product-meta]');
            if (name) name.textContent = 'Chọn sản phẩm';
            if (meta) meta.textContent = 'Nhấn để mở danh mục SKU';
        }

        var link = row.querySelector('[data-product-link]');
        if (link) {
            link.hidden = true;
            link.href = link.dataset.baseHref || link.href.split('?')[0];
        }
    }

    function applyProductToRow(row, product) {
        if (!row || !product) return;
        var input = row.querySelector('[data-product-id]');
        var trigger = row.querySelector('[data-product-trigger]');
        var link = row.querySelector('[data-product-link]');

        if (input) input.value = String(product.id);
        if (trigger) {
            trigger.classList.add('is-selected');
            var name = trigger.querySelector('[data-product-name]');
            var meta = trigger.querySelector('[data-product-meta]');
            if (name) name.textContent = product.name || ('Sản phẩm #' + product.id);
            if (meta) {
                meta.textContent = (product.sku || 'Chưa có SKU') + ' · Tồn ' + quantity(product.stock) + (product.unit ? ' ' + product.unit : '');
            }
        }

        if (link) {
            if (!link.dataset.baseHref) link.dataset.baseHref = link.href.split('?')[0];
            link.href = link.dataset.baseHref + '?search=' + encodeURIComponent(product.sku || product.name || '');
            link.hidden = false;
        }
    }

    function addItem(data) {
        var template = document.getElementById('grItemTemplate');
        var root = document.getElementById('grItems');
        if (!template || !root) return;

        root.appendChild(template.content.cloneNode(true));
        var row = root.lastElementChild;
        data = data || {};

        var qty = row.querySelector('[data-name="qty"]');
        var price = row.querySelector('[data-name="unit_price"]');
        var vat = row.querySelector('[data-name="vat_percent"]');
        var note = row.querySelector('[data-name="note"]');
        if (qty && data.qty !== undefined) qty.value = data.qty;
        if (price && data.unit_price !== undefined) price.value = data.unit_price;
        if (vat && data.vat_percent !== undefined) vat.value = data.vat_percent;
        if (note && data.note !== undefined) note.value = data.note;

        var product = findProduct(data.product_id);
        if (product) applyProductToRow(row, product);

        reindexItems();
        calculateTotals();
    }

    function removeItem(button) {
        var rows = document.querySelectorAll('#grItems .receipt-item-row');
        var row = button.closest('.receipt-item-row');
        if (!row) return;

        if (rows.length === 1) {
            row.querySelectorAll('input').forEach(function (input) {
                if (input.type === 'hidden') input.value = '';
                else if (input.dataset.name === 'qty') input.value = '1';
                else if (input.hasAttribute('data-total')) input.value = '0 đ';
                else if (input.dataset.name === 'unit_price' || input.dataset.name === 'vat_percent') input.value = '0';
                else input.value = '';
            });
            resetProductRow(row);
        } else {
            row.remove();
        }

        reindexItems();
        calculateTotals();
    }

    function openPicker(trigger) {
        activeRow = trigger.closest('.receipt-item-row');
        var modal = document.getElementById('grProductModal');
        if (!modal) return;

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('receipt-product-modal-open');

        var search = document.getElementById('grProductSearch');
        if (search) {
            search.value = '';
            window.setTimeout(function () { search.focus(); }, 50);
        }

        activeFilter = 'all';
        document.querySelectorAll('[data-product-filter]').forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.productFilter === 'all');
        });
        renderProducts();
    }

    function closePicker() {
        var modal = document.getElementById('grProductModal');
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('receipt-product-modal-open');
        activeRow = null;
    }

    function renderProducts() {
        var root = document.getElementById('grProductResults');
        if (!root) return;

        var search = document.getElementById('grProductSearch');
        var query = normalize(search ? search.value : '');
        var filtered = products.filter(function (product) {
            var stock = Number(product.stock) || 0;
            if (activeFilter === 'in-stock' && stock <= 0) return false;
            if (activeFilter === 'out-stock' && stock > 0) return false;
            if (!query) return true;
            return normalize(product.sku + ' ' + product.name + ' ' + product.unit).indexOf(query) !== -1;
        });

        var count = document.querySelector('[data-product-result-count]');
        if (count) count.textContent = filtered.length.toLocaleString('vi-VN') + ' sản phẩm';
        root.innerHTML = '';

        if (!filtered.length) {
            root.innerHTML = '<div class="receipt-product-modal__empty"><i class="bi bi-search"></i>Không tìm thấy sản phẩm phù hợp.</div>';
            return;
        }

        filtered.slice(0, 150).forEach(function (product) {
            var result = document.createElement('article');
            result.className = 'receipt-product-result';
            result.tabIndex = 0;
            var stock = Number(product.stock) || 0;
            result.innerHTML =
                '<div class="receipt-product-result__main">' +
                    '<span class="receipt-product-result__icon"><i class="bi bi-box"></i></span>' +
                    '<div class="receipt-product-result__copy"><b></b><small></small></div>' +
                '</div>' +
                '<div class="receipt-product-result__stock' + (stock <= 0 ? ' is-zero' : '') + '"><span>Tồn hiện tại</span><b></b></div>' +
                '<button type="button" class="receipt-product-result__choose">Chọn</button>';

            result.querySelector('.receipt-product-result__copy b').textContent = product.name || ('Sản phẩm #' + product.id);
            result.querySelector('.receipt-product-result__copy small').textContent = (product.sku || 'Chưa có SKU') + (product.unit ? ' · ' + product.unit : '');
            result.querySelector('.receipt-product-result__stock b').textContent = quantity(stock) + (product.unit ? ' ' + product.unit : '');

            function choose() { selectProduct(product); }
            result.querySelector('button').addEventListener('click', choose);
            result.addEventListener('dblclick', choose);
            result.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') choose();
            });
            root.appendChild(result);
        });

        if (filtered.length > 150) {
            var note = document.createElement('div');
            note.className = 'receipt-product-modal__empty';
            note.textContent = 'Đang hiển thị 150 kết quả đầu tiên. Hãy nhập thêm từ khóa để lọc chính xác hơn.';
            root.appendChild(note);
        }
    }

    function selectProduct(product) {
        if (!activeRow) return;
        applyProductToRow(activeRow, product);
        closePicker();
    }

    function toggleReceipt(id) {
        var detail = document.getElementById('grReceiptDetail' + id);
        if (!detail) return;
        var willOpen = detail.hidden;

        document.querySelectorAll('.receipt-detail-row').forEach(function (row) {
            row.hidden = true;
        });
        document.querySelectorAll('[data-receipt-toggle]').forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
        });

        detail.hidden = !willOpen;
        document.querySelectorAll('[data-receipt-toggle="' + id + '"]').forEach(function (button) {
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    }

    function validateForm(event) {
        var form = event.currentTarget;
        var emptyProduct = Array.prototype.find.call(form.querySelectorAll('[data-product-id]'), function (input) {
            return !input.value;
        });

        if (emptyProduct) {
            event.preventDefault();
            var row = emptyProduct.closest('.receipt-item-row');
            var trigger = row ? row.querySelector('[data-product-trigger]') : null;
            if (trigger) {
                trigger.focus();
                openPicker(trigger);
            }
            return false;
        }

        var submitter = event.submitter;
        if (submitter && submitter.matches('[data-confirm-post]')) {
            if (!window.confirm('Xác nhận lưu phiếu và cộng toàn bộ hàng hóa vào kho?')) {
                event.preventDefault();
                return false;
            }
        }
        return true;
    }

    function init() {
        var page = document.querySelector('.ego-goods-receipts-page');
        if (!page) return;

        products = parseJsonNode('grProductsData');
        oldItems = parseJsonNode('grOldItemsData');

        if (oldItems.length) oldItems.forEach(addItem);
        else addItem();

        document.querySelectorAll('[data-add-item]').forEach(function (button) {
            button.addEventListener('click', function () { addItem(); });
        });

        var itemsRoot = document.getElementById('grItems');
        if (itemsRoot) {
            itemsRoot.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-product-trigger]');
                if (trigger) {
                    openPicker(trigger);
                    return;
                }
                var remove = event.target.closest('[data-remove-item]');
                if (remove) removeItem(remove);
            });
            itemsRoot.addEventListener('input', calculateTotals);
            itemsRoot.addEventListener('change', calculateTotals);
        }

        document.querySelectorAll('[data-close-product-modal]').forEach(function (button) {
            button.addEventListener('click', closePicker);
        });

        var search = document.getElementById('grProductSearch');
        if (search) search.addEventListener('input', renderProducts);

        document.querySelectorAll('[data-product-filter]').forEach(function (button) {
            button.addEventListener('click', function () {
                activeFilter = button.dataset.productFilter || 'all';
                document.querySelectorAll('[data-product-filter]').forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                });
                renderProducts();
            });
        });

        document.querySelectorAll('[data-receipt-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                toggleReceipt(button.dataset.receiptToggle);
            });
        });

        document.addEventListener('keydown', function (event) {
            var modal = document.getElementById('grProductModal');
            if (event.key === 'Escape' && modal && !modal.hidden) closePicker();
        });

        var form = document.getElementById('egoGoodsReceiptForm');
        if (form) form.addEventListener('submit', validateForm);
        calculateTotals();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
