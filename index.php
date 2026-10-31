<?php
// Define SECURE_CHECK constant to prevent direct access to component files
define('SECURE_CHECK', true);

// 1. Load Cấu hình hệ thống (Đã thêm / trước app/)
require_once __DIR__ . '/app/config/app.php';

// 2. Xử lý tham số 'page' từ URL
$page_key = isset($_GET['page']) ? trim($_GET['page']) : 'home';

// 3. Kiểm tra trang có thuộc Whitelist không
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($current_page['title']); ?></title>
  <meta name="description" content="Trang web giày Nike theo phong cách Việt Nam." />
  
  <!-- CSS Dùng chung -->
  <link rel="stylesheet" href="public/assets/css/global.css" />
  
  <!-- CSS Riêng cho trang đang xem -->
  <link rel="stylesheet" href="public/assets/css/<?php echo $current_page['css']; ?>" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>
<body>
  <div class="khung_trang">
    
    <?php include __DIR__ . '/app/components/topbar.php'; ?>

    <?php include __DIR__ . '/app/components/header.php'; ?>

    <?php include __DIR__ . '/app/' . $current_page['file']; ?>

    <?php include __DIR__ . '/app/components/footer.php'; ?>

  </div>
</body>
</html>