<?php
// Xử lý đăng ký / đăng nhập / đăng xuất / hồ sơ / đổi mật khẩu.
// Mỗi hàm action_xxx() được index.php gọi khi form POST gửi lên action=xxx (đã kiểm tra CSRF).
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

// Hash giả để vẫn tốn thời gian verify khi username không tồn tại (tránh dò tài khoản qua thời gian phản hồi)
const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

function login_user(array $user): void
{
    session_regenerate_id(true); // đổi session id sau đăng nhập (chống session fixation)
    $_SESSION['user'] = [
        'user_id'  => (int)$user['user_id'],
        'username' => $user['username'],
        'name'     => $user['name'],
        'role'     => $user['role'],
    ];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function login_is_locked(string $ident): bool
{
    $s = db()->prepare(
        'SELECT COUNT(*) FROM login_attempt WHERE identifier = ? AND created_at > (NOW() - INTERVAL ' . (int)LOGIN_LOCK_MINUTES . ' MINUTE)'
    );
    $s->execute([strtolower($ident)]);
    return (int)$s->fetchColumn() >= MAX_LOGIN_FAILS;
}

function login_record_fail(string $ident): void
{
    db()->prepare('INSERT INTO login_attempt (identifier, ip) VALUES (?, ?)')
        ->execute([strtolower($ident), substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);
}

function login_clear_fails(string $ident): void
{
    db()->prepare('DELETE FROM login_attempt WHERE identifier = ?')->execute([strtolower($ident)]);
}

function action_register(): void
{
    require_once __DIR__ . '/../helpers/mailer.php';
    $username = post_str('username');
    $name     = post_str('name');
    $email    = strtolower(post_str('email'));
    $phone    = post_str('phone');
    $address  = post_str('address');
    $password = post_raw('password');
    $confirm  = post_raw('password_confirm');

    $errors = [];
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        $errors[] = 'Tên đăng nhập gồm 3-30 ký tự: chữ không dấu, số, dấu gạch dưới hoặc dấu chấm.';
    }
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Họ tên phải từ 2 đến 100 ký tự.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $errors[] = 'Email không hợp lệ.';
    }
    if ($phone !== '' && !preg_match('/^0\d{9,10}$/', $phone)) {
        $errors[] = 'Số điện thoại phải bắt đầu bằng 0 và có 10-11 chữ số.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }

    if (!$errors) {
        $s = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
        $s->execute([$username, $email]);
        if ((int)$s->fetchColumn() > 0) {
            $errors[] = 'Tên đăng nhập hoặc email đã được sử dụng.';
        }
    }

    if ($errors) {
        if (is_ajax()) {
            json_response(['success' => false, 'message' => implode('<br>', $errors), 'errors' => $errors]);
        }
        foreach ($errors as $err) {
            flash_set('error', $err);
        }
        $_SESSION['old'] = ['username' => $username, 'name' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address];
        redirect('index.php?page=register');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare(
            "INSERT INTO users (username, name, email, password_hash, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, 'customer', 'active')"
        );
        $s->execute([$username, $name, $email, password_hash($password, PASSWORD_DEFAULT), $phone === '' ? null : $phone, $address === '' ? null : $address]);
        $newUid = (int)$pdo->lastInsertId();

        // Tạo luôn địa chỉ mặc định trong user_address nếu khách hàng đã khai báo địa chỉ
        if ($address !== '') {
            $pdo->prepare('INSERT INTO user_address (user_id, recipient_name, phone, address, is_default) VALUES (?, ?, ?, ?, 1)')
                ->execute([$newUid, $name, $phone, $address]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = 'Tên đăng nhập hoặc email đã được sử dụng.';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect('index.php?page=register');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('register loi: ' . $e->getMessage());
        $msg = 'Không thể hoàn tất đăng ký lúc này.';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect('index.php?page=register');
    }

    $user = ['user_id' => $newUid, 'username' => $username, 'name' => $name, 'email' => $email, 'role' => 'customer'];
    
    // Gửi email chào mừng thành viên mới
    mail_send_welcome($user);

    login_user($user);
    $msg = 'Đăng ký thành công! Email thông báo chào mừng đã được gửi đến hộp thư của bạn.';
    if (is_ajax()) {
        json_response([
            'success'  => true,
            'message'  => $msg,
            'redirect' => 'index.php?page=home'
        ]);
    }
    flash_set('success', $msg);
    redirect('index.php?page=home');
}

// Kiểm tra nhanh username hoặc email đã tồn tại hay chưa bằng AJAX
function action_check_user_exists(): void
{
    $field = post_str('field'); // 'username' hoặc 'email'
    $value = post_str('value');

    if (!in_array($field, ['username', 'email'], true) || $value === '') {
        json_response(['exists' => false]);
    }

    $s = db()->prepare("SELECT COUNT(*) FROM users WHERE {$field} = ?");
    $s->execute([$value]);
    $exists = ((int)$s->fetchColumn() > 0);

    json_response([
        'success' => true,
        'exists'  => $exists,
        'message' => $exists ? ($field === 'username' ? 'Tên đăng nhập đã được sử dụng.' : 'Email đã được sử dụng.') : 'Hợp lệ.'
    ]);
}

function action_login(): void
{
    $ident    = post_str('identifier');
    $password = post_raw('password');
    $return   = safe_return_url(post_str('return'));
    $back     = 'index.php?page=login&return=' . urlencode($return);

    if ($ident === '' || $password === '') {
        $msg = 'Vui lòng nhập tên đăng nhập/email và mật khẩu.';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect($back);
    }
    if (login_is_locked($ident)) {
        $msg = 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau ' . LOGIN_LOCK_MINUTES . ' phút.';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect($back);
    }

    $s = db()->prepare('SELECT user_id, username, name, password_hash, role, status FROM users WHERE username = ? OR email = ? LIMIT 1');
    $s->execute([$ident, strtolower($ident)]);
    $user = $s->fetch();

    $ok = password_verify($password, $user ? $user['password_hash'] : DUMMY_HASH);
    if (!$user || !$ok) {
        login_record_fail($ident);
        $msg = 'Sai tên đăng nhập hoặc mật khẩu.';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg); // không nói rõ cái nào sai
        redirect($back);
    }
    if ($user['status'] !== 'active') {
        $msg = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ cửa hàng.';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect($back);
    }

    login_clear_fails($ident);
    // Nâng cấp hash nếu thuật toán mặc định đã đổi
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
    }
    login_user($user);

    $target = $return;
    // Admin/nhân viên đăng nhập bình thường (không có trang đích riêng) -> vào thẳng Dashboard quản trị
    if (in_array($user['role'], ['admin', 'staff'], true) && $return === 'index.php?page=home') {
        $target = 'index.php?page=admin';
    }

    if (is_ajax()) {
        json_response([
            'success'  => true,
            'message'  => 'Xin chào, ' . $user['name'] . '! Đang chuyển hướng...',
            'redirect' => $target
        ]);
    }

    flash_set('success', 'Xin chào, ' . $user['name'] . '!');
    redirect($target);
}

function action_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();               // mở phiên mới chỉ để hiện thông báo
    flash_set('success', 'Bạn đã đăng xuất.');
    redirect('index.php?page=home');
}

function action_profile_update(): void
{
    require_login();
    $uid     = (int)$_SESSION['user']['user_id'];
    $name    = post_str('name');
    $phone   = post_str('phone');
    $address = post_str('address');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        flash_set('error', 'Họ tên phải từ 2 đến 100 ký tự.');
        redirect('index.php?page=account');
    }
    if ($phone !== '' && !preg_match('/^0\d{9,10}$/', $phone)) {
        flash_set('error', 'Số điện thoại không hợp lệ.');
        redirect('index.php?page=account');
    }

    $pdo = db();
    $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ? WHERE user_id = ?')
        ->execute([$name, $phone === '' ? null : $phone, $address === '' ? null : $address, $uid]);

    // Đồng bộ vào bảng user_address (địa chỉ mặc định)
    if ($address !== '') {
        $checkAddr = $pdo->prepare('SELECT address_id FROM user_address WHERE user_id = ? AND is_default = 1 LIMIT 1');
        $checkAddr->execute([$uid]);
        $existingId = $checkAddr->fetchColumn();
        if ($existingId) {
            $pdo->prepare('UPDATE user_address SET recipient_name = ?, phone = ?, address = ? WHERE address_id = ?')
                ->execute([$name, $phone, $address, $existingId]);
        } else {
            $pdo->prepare('INSERT INTO user_address (user_id, recipient_name, phone, address, is_default) VALUES (?, ?, ?, ?, 1)')
                ->execute([$uid, $name, $phone, $address]);
        }
    }

    $_SESSION['user']['name'] = $name;
    flash_set('success', 'Đã cập nhật thông tin cá nhân và địa chỉ mặc định.');
    redirect('index.php?page=account');
}

function action_change_password(): void
{
    require_login();
    $uid     = (int)$_SESSION['user']['user_id'];
    $current = post_raw('current_password');
    $new     = post_raw('new_password');
    $confirm = post_raw('new_password_confirm');

    $s = db()->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $s->execute([$uid]);
    $hash = $s->fetchColumn();

    if (!$hash || !password_verify($current, $hash)) {
        flash_set('error', 'Mật khẩu hiện tại không đúng.');
    } elseif (strlen($new) < 8) {
        flash_set('error', 'Mật khẩu mới phải có ít nhất 8 ký tự.');
    } elseif ($new !== $confirm) {
        flash_set('error', 'Mật khẩu mới nhập lại không khớp.');
    } else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        session_regenerate_id(true);
        flash_set('success', 'Đã đổi mật khẩu.');
    }
    redirect('index.php?page=account');
}


/* ---------------------------------------------------------------
 * QUÊN MẬT KHẨU: tạo token ngẫu nhiên, chỉ lưu BẢN BĂM (sha256) trong CSDL, hạn dùng 30 phút.
 * Liên kết đặt lại được gửi qua email (hoặc ghi vào storage/outbox/ khi chưa có SMTP).
 * Câu trả lời luôn giống nhau dù email có tồn tại hay không (không để lộ tài khoản).
 * ------------------------------------------------------------- */
function action_password_forgot(): void
{
    require_once __DIR__ . '/../helpers/mailer.php';
    $email = strtolower(post_str('email'));
    $generic = 'Nếu email tồn tại trong hệ thống, liên kết đặt lại mật khẩu đã được gửi (hiệu lực 30 phút).';

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $s = db()->prepare("SELECT user_id, name FROM users WHERE email = ? AND status = 'active'");
        $s->execute([$email]);
        $u = $s->fetch();
        if ($u) {
            // Giới hạn 3 yêu cầu / giờ / tài khoản
            $c = db()->prepare('SELECT COUNT(*) FROM password_reset WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
            $c->execute([$u['user_id']]);
            if ((int)$c->fetchColumn() < 3) {
                $token = bin2hex(random_bytes(32));
                db()->prepare('INSERT INTO password_reset (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 30 MINUTE)')
                    ->execute([$u['user_id'], hash('sha256', $token)]);
                $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
                $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
                $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/index.php?page=reset&token=' . $token;
                mail_send_password_reset(['email' => $email, 'name' => $u['name']], $link);
            }
        }
    }
    flash_set('info', $generic);
    redirect('index.php?page=forgot');
}

function action_password_reset(): void
{
    $token    = post_str('token');
    $password = post_raw('password');
    $confirm  = post_raw('password_confirm');
    $back     = 'index.php?page=reset&token=' . urlencode($token);

    if (strlen($password) < 8) {
        flash_set('error', 'Mật khẩu phải có ít nhất 8 ký tự.');
        redirect($back);
    }
    if ($password !== $confirm) {
        flash_set('error', 'Mật khẩu nhập lại không khớp.');
        redirect($back);
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT reset_id, user_id FROM password_reset WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
        $s->execute([hash('sha256', $token)]);
        $r = $s->fetch();
        if (!$r) {
            throw new BusinessException('Liên kết không hợp lệ hoặc đã hết hạn. Hãy yêu cầu lại.');
        }
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $r['user_id']]);
        $pdo->prepare('UPDATE password_reset SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$r['user_id']]); // vô hiệu mọi token cũ
        $pdo->prepare('DELETE FROM login_attempt WHERE identifier IN (SELECT username FROM users WHERE user_id = ?)')->execute([$r['user_id']]);
        $pdo->commit();
    } catch (BusinessException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('error', $e->getMessage());
        redirect('index.php?page=forgot');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('password_reset loi: ' . $e->getMessage());
        flash_set('error', 'Không thể đặt lại mật khẩu lúc này.');
        redirect($back);
    }
    flash_set('success', 'Đã đặt lại mật khẩu. Hãy đăng nhập bằng mật khẩu mới.');
    redirect('index.php?page=login');
}
