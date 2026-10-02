/**
 * Điểm vào cho bản dựng cổ điển (IIFE) đặt ở public/js/bootstrap-compat.js.
 *
 * VÌ SAO KHÔNG GỘP VÀO app.js: @vite phát ra <script type="module">, luôn hoãn
 * đến sau khi phân tích xong trang. Mà layout có @stack('scripts') chèn script
 * nội tuyến chạy ngay lúc phân tích, và 26 chỗ trong view gọi thẳng
 * new bootstrap.Modal(...). Nếu để trong module thì tới lúc đó window.bootstrap
 * vẫn chưa có. Bản này nạp đúng chỗ bootstrap.bundle.min.js cũ nên thứ tự
 * thực thi không đổi một nhịp nào.
 */

import { startBootstrapCompat } from './index.js';

startBootstrapCompat();
