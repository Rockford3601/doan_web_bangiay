<?php
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

// Kiểm tra Session Admin bảo mật
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo "<h2 style='text-align:center; padding-top:50px; color:red;'>403 Forbidden - Bạn không có quyền truy cập trang quản trị!</h2>";
    exit();
}
?>
<!-- Nội dung trang Quản trị Admin -->
<h1>Trang Quản Trị Hệ Thống</h1>
