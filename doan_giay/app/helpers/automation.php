<?php
// TỰ ĐỘNG HÓA QUẢN LÝ ĐƠN HÀNG & THANH TOÁN.
// XAMPP không có cron sẵn, nên tác vụ được kích hoạt "cơ hội" khi có người truy cập web (tối đa mỗi MAINT_INTERVAL_MIN phút).
// Muốn chạy đều đặn kể cả khi không có ai truy cập: dùng Windows Task Scheduler gọi cron/maintenance.php (xem docs).
//
// Các quy tắc (bật/tắt/chỉnh trong app/config/app.php):
//  1. Đơn thanh toán ONLINE chưa trả tiền quá AUTO_CANCEL_UNPAID_HOURS giờ  -> tự hủy (hoàn kho, flash sale, voucher).
//  2. Đơn "Chờ xác nhận" đã thanh toán (AUTO_CONFIRM_PAID)                   -> tự "Đã xác nhận".
//  3. Đơn COD giá trị nhỏ (AUTO_CONFIRM_COD_UNDER)                           -> tự "Đã xác nhận" sau 10 phút (để khách kịp hủy).
//  4. Dọn bảng tạm: lượt đăng nhập sai cũ, token đặt lại mật khẩu đã hết hạn.
// Mọi thao tác đều đi qua cancel_order()/change_order_status() nên có transaction, khóa dòng và nhật ký trạng thái như thao tác tay.
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

function run_maintenance(): array
{
    require_once __DIR__ . '/../backend/orders.php';
    $pdo = db();
    $res = ['cancelled' => 0, 'confirmed' => 0, 'cleaned' => 0];

    // 1. Tự hủy đơn online quá hạn thanh toán
    if (AUTO_CANCEL_UNPAID_HOURS > 0) {
        $s = $pdo->prepare(
            "SELECT order_id FROM orders
              WHERE order_status = 'pending' AND payment_status = 'unpaid' AND payment_method IN (?, ?)
                AND created_at < (NOW() - INTERVAL " . (int)AUTO_CANCEL_UNPAID_HOURS . " HOUR)
              ORDER BY order_id LIMIT 50"
        );
        $s->execute([WALLET_METHOD, 'Chuyển khoản']);
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) {
            [$ok] = cancel_order((int)$id, 0, true, 'Hệ thống tự hủy: quá ' . AUTO_CANCEL_UNPAID_HOURS . ' giờ chưa thanh toán');
            if ($ok) $res['cancelled']++;
        }
    }

    // 2. Tự xác nhận đơn đã thanh toán
    if (AUTO_CONFIRM_PAID) {
        $ids = $pdo->query("SELECT order_id FROM orders WHERE order_status = 'pending' AND payment_status = 'paid' ORDER BY order_id LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            [$ok] = change_order_status((int)$id, 'confirmed', 0, 'Tự động xác nhận: đã thanh toán');
            if ($ok) $res['confirmed']++;
        }
    }

    // 3. Tự xác nhận đơn COD nhỏ (đã chờ >= 10 phút)
    if (AUTO_CONFIRM_COD_UNDER > 0) {
        $s = $pdo->prepare(
            "SELECT order_id FROM orders
              WHERE order_status = 'pending' AND payment_method = 'COD' AND final_amount <= ?
                AND created_at < (NOW() - INTERVAL 10 MINUTE)
              ORDER BY order_id LIMIT 50"
        );
        $s->execute([AUTO_CONFIRM_COD_UNDER]);
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) {
            [$ok] = change_order_status((int)$id, 'confirmed', 0, 'Tự động xác nhận: đơn COD giá trị nhỏ');
            if ($ok) $res['confirmed']++;
        }
    }

    // 4. Dọn dữ liệu tạm
    $res['cleaned'] += (int)$pdo->exec('DELETE FROM login_attempt WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    $res['cleaned'] += (int)$pdo->exec('DELETE FROM password_reset WHERE expires_at < (NOW() - INTERVAL 1 DAY)');
    return $res;
}

// Gọi ở mỗi request GET. UPDATE có điều kiện đảm bảo CHỈ MỘT request giành được quyền chạy trong mỗi chu kỳ.
function maintenance_tick(): void
{
    try {
        $u = db()->prepare('UPDATE system_job SET last_run = NOW() WHERE job_name = ? AND last_run < (NOW() - INTERVAL ' . (int)MAINT_INTERVAL_MIN . ' MINUTE)');
        $u->execute(['maintenance']);
        if ($u->rowCount() === 1) {
            run_maintenance();
        }
    } catch (Throwable $e) {
        error_log('maintenance loi: ' . $e->getMessage());   // lỗi tác vụ nền không được làm hỏng trang đang xem
    }
}

function maintenance_summary(array $r): string
{
    return 'Đã chạy tự động: hủy ' . (int)$r['cancelled'] . ' đơn quá hạn thanh toán, tự xác nhận ' . (int)$r['confirmed'] . ' đơn, dọn ' . (int)$r['cleaned'] . ' bản ghi tạm.';
}
