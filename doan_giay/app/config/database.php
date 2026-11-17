<?php
// Kết nối CSDL bằng PDO. Mọi truy vấn trong dự án dùng prepared statement qua hàm db().
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

const DB_HOST = 'localhost';
const DB_NAME = 'web_giaydep';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo = null; // chỉ tạo kết nối 1 lần cho mỗi request

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lỗi SQL -> ném exception
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,                    // prepare thật => chống SQL injection triệt để
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
                ]
            );
        } catch (PDOException $e) {
            error_log('Loi ket noi CSDL: ' . $e->getMessage());
            http_response_code(500);
            $err = 'Không thể kết nối cơ sở dữ liệu. Hãy kiểm tra MySQL trong XAMPP đã bật và đã import file SQL chưa.';
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $err .= '<br><br><small style="color:#666;">Chi tiết lỗi (APP_DEBUG): ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small>';
            }
            exit($err);
        }
    }
    return $pdo;
}
