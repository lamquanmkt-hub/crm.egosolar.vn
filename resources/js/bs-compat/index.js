/**
 * Thay Bootstrap JS bằng mã trong dự án.
 *
 * VÌ SAO: layout đang kéo bootstrap.bundle.min.js 80KB từ CDN chỉ để dùng sáu
 * cơ chế (modal, offcanvas, collapse, tab, alert, dropdown, toast). Bản này làm
 * đúng sáu việc đó, không phụ thuộc mạng ngoài, và mở đường chuyển dần view
 * sang Alpine mà không cần đổi 40 tệp cùng lúc.
 *
 * TƯƠNG THÍCH: giữ nguyên cả hai đường vào — thuộc tính data-bs-* trong Blade
 * và API window.bootstrap.* mà 9 tệp public/js đang gọi.
 */

import { Alert, wireAlert } from './alert.js';
import { Collapse, wireCollapse } from './collapse.js';
import { Dropdown, wireDropdown } from './dropdown.js';
import { Modal, wireModal } from './modal.js';
import { Offcanvas, wireOffcanvas } from './offcanvas.js';
import { Tab, wireTab } from './tab.js';
import { Toast, wireToast } from './toast.js';

export function startBootstrapCompat() {
    wireModal();
    wireOffcanvas();
    wireCollapse();
    wireTab();
    wireAlert();
    wireDropdown();
    wireToast();

    window.bootstrap = Object.assign(window.bootstrap || {}, {
        Alert, Collapse, Dropdown, Modal, Offcanvas, Tab, Toast,
    });
}

export { Alert, Collapse, Dropdown, Modal, Offcanvas, Tab, Toast };
