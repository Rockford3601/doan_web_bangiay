<?php
// Chạy tác vụ tự động từ dòng lệnh (không cần có người truy cập web). CHỈ chạy được bằng CLI.
// Windows Task Scheduler: Action = Start a program
//   Program : D:\xampp\php\php.exe
//   Arguments: "D:\xampp\htdocs\doan_giay\cron\maintenance.php"      (lặp lại mỗi 5-10 phút)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
define('SECURE_CHECK', true);
date_default_timezone_set('Asia/Ho_Chi_Minh');
$root = dirname(__DIR__);
require_once $root . '/app/config/app.php';
require_once $root . '/app/config/database.php';
require_once $root . '/app/helpers/functions.php';
require_once $root . '/app/helpers/cart_helper.php';
require_once $root . '/app/helpers/automation.php';

try {
    db()->prepare('UPDATE system_job SET last_run = NOW() WHERE job_name = ?')->execute(['maintenance']);
    echo date('Y-m-d H:i:s') . ' - ' . maintenance_summary(run_maintenance()) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'LOI: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
