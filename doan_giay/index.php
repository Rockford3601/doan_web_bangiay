<?php
// ĐIỂM VÀO DUY NHẤT của website (front controller).
// Luồng xử lý: nạp cấu hình -> mở session -> (nếu POST) chạy hành động -> (nếu GET) hiển thị trang.
define('SECURE_CHECK', true);   // các file include kiểm tra hằng này để không bị truy cập trực tiếp
ob_start();                     // gom đầu ra lại để còn đổi header/redirect/mã lỗi giữa chừng

date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. Nạp cấu hình + hàm dùng chung
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/helpers/cart_helper.php';
require_once __DIR__ . '/app/helpers/migrate.php';
require_once __DIR__ . '/app/helpers/recommend.php';
require_once __DIR__ . '/app/helpers/automation.php';
if (is_file(__DIR__ . '/app/config/secrets.php')) {   // khóa API (nếu có), không đưa lên GitHub
    require_once __DIR__ . '/app/config/secrets.php';
}

// Mọi lỗi không bắt được sẽ hiện trang thân thiện (APP_DEBUG = true thì kèm chi tiết để bạn sửa lỗi)
set_exception_handler(function (Throwable $e) {
    error_log('LOI: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<div style="font-family:sans-serif;max-width:760px;margin:60px auto;padding:24px;border:1px solid #f4c3bf;background:#fdeceb;border-radius:12px">';
    echo '<h2 style="margin-top:0">Đã xảy ra lỗi hệ thống</h2><p>Vui lòng thử lại sau. Nếu lỗi lặp lại hãy báo cho quản trị viên.</p>';
    if (APP_DEBUG) {
        echo '<pre style="white-space:pre-wrap;background:#fff;padding:12px;border-radius:8px">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
           . "\n\n" . htmlspecialchars(basename($e->getFile()) . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '<p><a href="index.php">← Về trang chủ</a></p></div>';
    exit;
});

// Header bảo mật cơ bản
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// 2. Session + đồng bộ thông tin người dùng đang đăng nhập
start_secure_session();
schema_ensure();            // tự bổ sung cột/bảng còn thiếu trong CSDL (xem app/helpers/migrate.php)
refresh_session_user();

// API chatbot (trả JSON)
if (isset($_GET['api']) && $_GET['api'] === 'chat') {
    require_once __DIR__ . '/app/backend/chatbot.php';
    api_chat();
    exit;
}

// API kiểm tra trạng thái thanh toán & mô phỏng thanh toán VietQR
if (isset($_GET['api']) && in_array($_GET['api'], ['check_payment', 'simulate_pay'], true)) {
    require_once __DIR__ . '/app/backend/orders.php';
    if ($_GET['api'] === 'check_payment') {
        api_check_payment();
    } else {
        api_simulate_pay();
    }
    exit;
}

// Tác vụ tự động (hủy đơn quá hạn thanh toán, tự xác nhận đơn đã trả tiền, dọn dữ liệu tạm): tối đa mỗi vài phút một lần
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    maintenance_tick();
}

// 3. XỬ LÝ HÀNH ĐỘNG (form POST). Chạy TRƯỚC khi in HTML để còn redirect được.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if (!array_key_exists($action, $actions)) {      // chỉ chạy hành động có trong whitelist
        http_response_code(400);
        exit('Hành động không hợp lệ.');
    }
    csrf_verify();                                    // mọi POST đều phải có token CSRF đúng
    require_once __DIR__ . '/app/backend/' . $actions[$action] . '.php';
    $handler = 'action_' . $action;
    $handler();
    exit;                                             // handler nào cũng redirect; phòng hờ thì dừng ở đây
}

// 4. HIỂN THỊ TRANG (GET). Tham số 'page' phải thuộc whitelist.
$page_key = isset($_GET['page']) && is_string($_GET['page']) ? trim($_GET['page']) : 'home';

if (array_key_exists($page_key, $pages)) {
    $current_page = $pages[$page_key];
} else {
    http_response_code(404);
    $current_page = [
        'title' => '404 Not Found | Tuyển Chọn',
        'css'   => 'home.css',
        'file'  => 'pages/404.php'
    ];
}

// 5. Kiểm tra quyền truy cập trang
if (($current_page['auth'] ?? '') === 'login') {
    require_login();
} elseif (($current_page['auth'] ?? '') === 'staff') {
    require_staff();
} elseif (($current_page['auth'] ?? '') === 'admin') {
    require_admin();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo e($current_page['title']); ?></title>
  <meta name="description" content="Trang web giày Nike theo phong cách Việt Nam." />

  <!-- CSS dùng chung (kèm timestamp để trình duyệt luôn tải file mới nhất không bị cache) -->
  <link rel="stylesheet" href="public/assets/css/global.css?v=<?php echo @filemtime(__DIR__ . '/public/assets/css/global.css') ?: 1; ?>" />
  <link rel="stylesheet" href="public/assets/css/app.css?v=<?php echo @filemtime(__DIR__ . '/public/assets/css/app.css') ?: 1; ?>" />

  <!-- CSS riêng cho trang đang xem (nếu có) -->
  <?php if (!empty($current_page['css'])): ?>
  <link rel="stylesheet" href="public/assets/css/<?php echo e($current_page['css']); ?>?v=<?php echo @filemtime(__DIR__ . '/public/assets/css/' . $current_page['css']) ?: 1; ?>" />
  <?php endif; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>
<body>
  <div class="khung_trang">

    <?php include __DIR__ . '/app/components/topbar.php'; ?>

    <?php include __DIR__ . '/app/components/header.php'; ?>

    <div class="container flash-area"><?php render_flash(); ?></div>

    <?php include __DIR__ . '/app/' . $current_page['file']; ?>

    <?php include __DIR__ . '/app/components/footer.php'; ?>

    <?php include __DIR__ . '/app/components/chatbot.php'; ?>

  </div>
  <!-- JavaScript dùng chung cho các chức năng AJAX -->
  <script src="public/assets/js/main.js?v=<?php echo @filemtime(__DIR__ . '/public/assets/js/main.js') ?: 1; ?>"></script>
</body>
</html>
