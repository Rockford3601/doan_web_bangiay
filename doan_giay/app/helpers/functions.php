<?php
// Các hàm dùng chung: session, bảo mật (XSS, CSRF), flash message, xác thực.
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

// Lỗi NGHIỆP VỤ do mình chủ động ném (vd: hết hàng). Thông báo của nó an toàn để hiện cho người dùng.
// (Không dùng RuntimeException vì PDOException kế thừa nó -> sẽ vô tình lộ lỗi SQL.)
class BusinessException extends Exception
{
}

/* ---------------------------------------------------------------
 * SESSION
 * ------------------------------------------------------------- */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');  // không nhận session id do người dùng tự bịa
    ini_set('session.use_only_cookies', '1'); // không cho session id nằm trên URL
    session_set_cookie_params([
        'lifetime' => 0,            // hết khi đóng trình duyệt
        'path'     => '/',
        'httponly' => true,         // JavaScript không đọc được cookie phiên (giảm rủi ro XSS)
        'samesite' => 'Lax',        // giảm rủi ro CSRF
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/* ---------------------------------------------------------------
 * TIỆN ÍCH CƠ BẢN
 * ------------------------------------------------------------- */
// Escape dữ liệu trước khi in ra HTML (chống XSS). LUÔN dùng e() khi echo dữ liệu từ người dùng/CSDL.
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_vnd($n): string
{
    return number_format((float)$n, 0, ',', '.') . ' ₫';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// Lấy dữ liệu POST/GET dạng chuỗi (nếu người dùng gửi mảng thì coi như rỗng, tránh lỗi)
function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}
function post_raw(string $key): string // dùng cho mật khẩu: không trim
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? $v : '';
}
function post_int(string $key): int
{
    return (int)post_str($key);
}
function get_str(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}
function get_int(string $key): int
{
    return (int)get_str($key);
}

// Chỉ cho phép chuyển hướng tới trang nội bộ dạng index.php?... (chống Open Redirect)
function safe_return_url(?string $url, string $default = 'index.php?page=home'): string
{
    if ($url !== null && preg_match('/^index\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $url)) {
        return $url;
    }
    return $default;
}

// URL hiện tại dạng tương đối (index.php?page=...) để quay lại sau khi đăng nhập
function current_relative_url(): string
{
    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $path = basename((string)parse_url($uri, PHP_URL_PATH));
    $path = ($path === '') ? 'index.php' : $path;
    $q    = (string)parse_url($uri, PHP_URL_QUERY);
    return $path . ($q !== '' ? '?' . $q : '');
}

function img_url(?string $path): string
{
    if (empty($path)) {
        return 'public/assets/imgs/placeholder.svg';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return 'public/' . ltrim($path, '/');
}

/* ---------------------------------------------------------------
 * FLASH MESSAGE (thông báo hiện 1 lần) + dữ liệu form cũ
 * ------------------------------------------------------------- */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    foreach ($_SESSION['flash'] as $f) {
        echo '<div class="flash flash-' . e($f['type']) . '">' . e($f['message']) . '</div>';
    }
    unset($_SESSION['flash']);
}

// Lấy lại dữ liệu form người dùng đã nhập (khi có lỗi) rồi xóa đi
function pull_old(string $name = 'old'): array
{
    $old = $_SESSION[$name] ?? [];
    unset($_SESSION[$name]);
    return is_array($old) ? $old : [];
}

/* ---------------------------------------------------------------
 * CSRF: mỗi form POST phải kèm token bí mật của phiên
 * ------------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Phiên làm việc đã hết hạn hoặc yêu cầu không hợp lệ. Hãy quay lại, tải lại trang rồi thử lại.');
    }
}

/* ---------------------------------------------------------------
 * XÁC THỰC / PHÂN QUYỀN
 * ------------------------------------------------------------- */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']['user_id']);
}

function is_admin(): bool
{
    return (($_SESSION['user']['role'] ?? '') === 'admin');
}

// Nhân viên (staff) hoặc admin đều vào được khu quản lý vận hành (đơn, sản phẩm, đổi trả, đánh giá)
function is_staff(): bool
{
    return in_array($_SESSION['user']['role'] ?? '', ['admin', 'staff'], true);
}

function require_staff(): void
{
    if (!is_logged_in()) {
        require_login();
    }
    if (!is_staff()) {
        http_response_code(403);
        exit('<h2 style="font-family:sans-serif;padding:40px">403 - Bạn không có quyền truy cập khu vực này.</h2>');
    }
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash_set('error', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('index.php?page=login&return=' . urlencode(safe_return_url(current_relative_url())));
    }
}

function require_admin(): void
{
    if (!is_logged_in()) {
        require_login();
    }
    if (!is_admin()) {
        http_response_code(403);
        exit('<h2 style="font-family:sans-serif;padding:40px">403 - Bạn không có quyền truy cập khu vực này.</h2>');
    }
}

function member_tier(int $points): string
{
    if ($points >= 2000) return 'Vàng';
    if ($points >= 500)  return 'Bạc';
    return 'Thành viên';
}

// Đồng bộ phiên đăng nhập với CSDL mỗi request: nếu tài khoản bị khóa/đổi quyền thì có hiệu lực ngay
function refresh_session_user(): void
{
    if (!is_logged_in()) {
        return;
    }
    $s = db()->prepare('SELECT user_id, username, name, role, status FROM users WHERE user_id = ?');
    $s->execute([(int)$_SESSION['user']['user_id']]);
    $u = $s->fetch();
    if (!$u || $u['status'] !== 'active') {
        unset($_SESSION['user']);
        flash_set('error', 'Tài khoản của bạn không còn hoạt động.');
        return;
    }
    $_SESSION['user'] = [
        'user_id'  => (int)$u['user_id'],
        'username' => $u['username'],
        'name'     => $u['name'],
        'role'     => $u['role'],
    ];
}
