<?php
// Hệ thống gửi email tự động (Chào mừng, Quên mật khẩu, Xác nhận đơn hàng, Báo admin)
// Khi MAIL_MODE = 'outbox': Lưu email dạng HTML (.html) và văn bản (.txt) vào storage/outbox/ để mở xem trực tiếp khi demo trên XAMPP.
// Khi MAIL_MODE = 'php': Gửi thật qua hàm mail() của máy chủ SMTP.
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

/**
 * Gửi email chung (hỗ trợ SMTP Gmail, mail() PHP, và outbox dự phòng)
 */
function send_mail(string $to, string $subject, string $body, ?string $html = null): bool
{
    // 1. Luôn ghi một bản sao vào storage/outbox/ để có thể xem lại khi test hoặc khi mạng lỗi
    $dir = __DIR__ . '/../../storage/outbox';
    if (is_dir($dir) || mkdir($dir, 0755, true)) {
        $timestamp = date('Ymd_His');
        $uid = bin2hex(random_bytes(3));
        $baseName = $dir . '/' . $timestamp . '_' . $uid;
        file_put_contents($baseName . '.txt', "TO: $to\nSUBJECT: $subject\nDATE: " . date('c') . "\n\n$body\n");
        if ($html !== null) {
            $metaHeader = "<!-- TO: $to | SUBJECT: $subject | DATE: " . date('d/m/Y H:i:s') . " -->\n";
            file_put_contents($baseName . '.html', $metaHeader . $html);
        }
    }

    // 2. Nếu ở chế độ 'smtp' hoặc đã điền tài khoản SMTP Gmail
    if (MAIL_MODE === 'smtp' || (!empty(SMTP_USER) && !empty(SMTP_PASS))) {
        if (empty(SMTP_USER) || empty(SMTP_PASS)) {
            error_log("[MAIL SMTP CẢNH BÁO] MAIL_MODE = 'smtp' nhưng chưa điền SMTP_USER hoặc SMTP_PASS trong app/config/app.php. Email đã được lưu dự phòng vào storage/outbox/.");
            return false;
        }
        [$ok, $msg] = smtp_socket_send($to, $subject, $body, $html);
        if (!$ok) {
            error_log("[MAIL SMTP LỖI] Không thể gửi email tới $to: $msg");
        }
        return $ok;
    }

    // 3. Nếu ở chế độ 'php' (dùng hàm mail() gốc của hệ điều hành)
    if (MAIL_MODE === 'php') {
        $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromEmail = !empty(SMTP_USER) ? SMTP_USER : MAIL_FROM;
        if ($html !== null) {
            $headers = "From: " . $fromEmail . "\r\n" .
                       "Reply-To: " . $fromEmail . "\r\n" .
                       "MIME-Version: 1.0\r\n" .
                       "Content-Type: text/html; charset=UTF-8\r\n";
            return @mail($to, $subjectEncoded, $html, $headers);
        } else {
            $headers = "From: " . $fromEmail . "\r\n" .
                       "Reply-To: " . $fromEmail . "\r\n" .
                       "Content-Type: text/plain; charset=UTF-8\r\n";
            return @mail($to, $subjectEncoded, $body, $headers);
        }
    }

    // Chế độ 'outbox': chỉ lưu file, đã thực hiện ở bước 1
    return true;
}

/**
 * Gửi email trực tiếp qua giao thức Socket SMTP (hỗ trợ SSL/TLS Gmail, không cần composer)
 */
function smtp_socket_send(string $to, string $subject, string $bodyText, ?string $htmlBody = null): array
{
    $host = SMTP_HOST;
    $port = (int)SMTP_PORT;
    $secure = strtolower(SMTP_SECURE);
    $user = trim(SMTP_USER);
    $pass = str_replace(' ', '', trim(SMTP_PASS)); // Bỏ khoảng trắng nếu người dùng paste mật khẩu 16 ký tự có dấu cách
    $timeout = 15;

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ]
    ]);

    $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return [false, "Không thể kết nối đến máy chủ SMTP $remote:$port ($errno: $errstr)"];
    }

    stream_set_timeout($socket, $timeout);

    $lastResp = '';
    $read = function(?int $expectedCode = 250) use ($socket, &$lastResp): bool {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $data .= $line;
            // Dòng cuối của SMTP response có dạng: 'XYZ ' (ký tự thứ 4 là khoảng trắng)
            if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') {
                break;
            }
        }
        $lastResp = trim($data);
        $code = (int)substr($data, 0, 3);
        if ($expectedCode !== null && $code !== $expectedCode) {
            return false;
        }
        return true;
    };

    $write = function(string $cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    // 1. Nhận lời chào từ SMTP server (220)
    if (!$read(220)) {
        fclose($socket);
        return [false, "Lỗi kết nối ban đầu từ SMTP: $lastResp"];
    }

    // 2. Gửi EHLO
    $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    if (!$read(250)) {
        $write("HELO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!$read(250)) {
            fclose($socket);
            return [false, "Lỗi EHLO/HELO: $lastResp"];
        }
    }

    // Nâng cấp TLS nếu cấu hình TLS
    if ($secure === 'tls') {
        $write("STARTTLS");
        if (!$read(220)) {
            fclose($socket);
            return [false, "Lỗi STARTTLS: $lastResp"];
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return [false, "Kích hoạt mã hóa TLS thất bại"];
        }
        $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!$read(250)) {
            fclose($socket);
            return [false, "Lỗi EHLO sau STARTTLS: $lastResp"];
        }
    }

    // 3. Đăng nhập AUTH LOGIN
    $write("AUTH LOGIN");
    if (!$read(334)) {
        fclose($socket);
        return [false, "Máy chủ không chấp nhận AUTH LOGIN: $lastResp"];
    }

    $write(base64_encode($user));
    if (!$read(334)) {
        fclose($socket);
        return [false, "Tài khoản Gmail bị từ chối: $lastResp"];
    }

    $write(base64_encode($pass));
    if (!$read(235)) {
        fclose($socket);
        return [false, "Mật khẩu ứng dụng Gmail bị từ chối (Lỗi xác thực): $lastResp. Vui lòng kiểm tra lại 'Mật khẩu ứng dụng' 16 ký tự tại myaccount.google.com/apppasswords"];
    }

    // 4. Khởi tạo người gửi & người nhận
    $write("MAIL FROM: <$user>");
    if (!$read(250)) {
        fclose($socket);
        return [false, "Lỗi MAIL FROM: $lastResp"];
    }

    $write("RCPT TO: <$to>");
    if (!$read(250)) {
        fclose($socket);
        return [false, "Lỗi RCPT TO ($to): $lastResp"];
    }

    // 5. Gửi nội dung thư (DATA)
    $write("DATA");
    if (!$read(354)) {
        fclose($socket);
        return [false, "Lỗi lệnh DATA: $lastResp"];
    }

    $boundary = '=_Nike_' . md5(uniqid(microtime(), true));
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Nike - Vietnam';
    $fromEncoded = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $user . '>';
    $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $headers = [
        "From: $fromEncoded",
        "Reply-To: $fromEncoded",
        "To: <$to>",
        "Subject: $subjectEncoded",
        "Date: " . date('r'),
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
    ];

    $emailData = implode("\r\n", $headers) . "\r\n\r\n";
    
    // Plain-text
    $emailData .= "--$boundary\r\n";
    $emailData .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $emailData .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $emailData .= chunk_split(base64_encode($bodyText)) . "\r\n";

    // HTML
    if ($htmlBody !== null) {
        $emailData .= "--$boundary\r\n";
        $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
        $emailData .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $emailData .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    }

    $emailData .= "--$boundary--\r\n.";

    $write($emailData);
    if (!$read(250)) {
        fclose($socket);
        return [false, "Gửi nội dung email thất bại: $lastResp"];
    }

    $write("QUIT");
    fclose($socket);
    return [true, "Gửi email thành công tới $to"];
}

/**
 * Khung HTML email chung phong cách Nike tối giản
 */
function mail_layout(string $title, string $contentHtml): string
{
    $siteUrl = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    return '<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
<style>
  body { margin:0; padding:0; background-color:#f5f5f5; font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color:#111; line-height:1.6; }
  .email-container { max-width:600px; margin:30px auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e5e5; box-shadow:0 4px 16px rgba(0,0,0,0.04); }
  .email-header { background:#111111; padding:24px 32px; text-align:left; }
  .email-header h1 { margin:0; color:#ffffff; font-size:20px; font-weight:800; letter-spacing:1px; text-transform:uppercase; }
  .email-header p { margin:4px 0 0; color:#aaaaaa; font-size:12px; }
  .email-body { padding:32px; }
  .email-title { font-size:20px; font-weight:700; margin:0 0 16px; color:#111; }
  .email-desc { font-size:14px; color:#555; margin-bottom:24px; }
  .info-box { background:#f9f9f9; border-radius:8px; border:1px solid #eee; padding:16px 20px; margin-bottom:24px; }
  .info-row { display:flex; justify-content:space-between; margin-bottom:8px; font-size:13.5px; }
  .info-row:last-child { margin-bottom:0; }
  .info-label { color:#777; }
  .info-val { font-weight:600; color:#111; }
  .btn-wrap { margin:28px 0; text-align:center; }
  .btn-action { display:inline-block; background:#111111; color:#ffffff !important; padding:12px 28px; border-radius:6px; font-size:14px; font-weight:700; text-decoration:none; text-transform:uppercase; letter-spacing:0.5px; }
  .btn-action:hover { background:#c74721; }
  .item-table { width:100%; border-collapse:collapse; margin:20px 0; font-size:13.5px; }
  .item-table th { text-align:left; padding:10px 12px; border-bottom:2px solid #e5e5e5; color:#555; font-size:12px; text-transform:uppercase; }
  .item-table td { padding:12px; border-bottom:1px solid #eee; vertical-align:middle; }
  .total-row { font-size:15px; font-weight:700; color:#111; }
  .badge-tag { display:inline-block; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:700; text-transform:uppercase; background:#e8f5e9; color:#2e7d32; }
  .badge-tag-pending { background:#fff3e0; color:#e65100; }
  .email-footer { background:#fafafa; border-top:1px solid #eee; padding:20px 32px; font-size:12px; color:#888; text-align:center; }
  .email-footer a { color:#111; text-decoration:underline; }
</style>
</head>
<body>
<div class="email-container">
  <div class="email-header">
    <h1>NIKE - VIỆT NAM</h1>
    <p>LỰA CHỌN CỦA BẠN. NHỊP ĐI CỦA BẠN.</p>
  </div>
  <div class="email-body">
    ' . $contentHtml . '
  </div>
  <div class="email-footer">
    <p>Email này được gửi tự động từ hệ thống website <a href="' . htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') . '">Nike - Vietnam</a>.</p>
    <p>Hotline hỗ trợ: 1900 6868 · Email: ' . htmlspecialchars(MAIL_FROM, ENT_QUOTES, 'UTF-8') . '</p>
  </div>
</div>
</body>
</html>';
}

/**
 * 1. Gửi email chào mừng khi khách đăng ký tài khoản thành công
 */
function mail_send_welcome(array $user): bool
{
    $to = (string)$user['email'];
    $name = (string)$user['name'];
    $username = (string)($user['username'] ?? $user['email']);
    $subject = 'Chào mừng bạn đến với Nike - Vietnam';

    $bodyText = "Xin chào $name,\n\n" .
                "Cảm ơn bạn đã đăng ký tài khoản tại Nike - Vietnam!\n" .
                "Tên đăng nhập: $username\n" .
                "Email: $to\n\n" .
                "Bạn có thể bắt đầu mua sắm và nhận các đặc quyền tích điểm ngay hôm nay.\n" .
                "Trân trọng,\nĐội ngũ Tuyển Chọn";

    $html = mail_layout('Chào mừng thành viên mới', '
      <h2 class="email-title">Chào mừng ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '!</h2>
      <p class="email-desc">Chúc mừng bạn đã tạo tài khoản thành công tại <strong>Nike - Vietnam </strong>. Bạn đã chính thức trở thành thành viên và có thể tận hưởng các ưu đãi thành viên, tích lũy điểm thưởng theo mỗi đơn hàng.</p>
      
      <div class="info-box">
        <div class="info-row"><span class="info-label">Họ và tên:</span> <span class="info-val">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span></div>
        <div class="info-row"><span class="info-label">Tên đăng nhập:</span> <span class="info-val">' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</span></div>
        <div class="info-row"><span class="info-label">Email tài khoản:</span> <span class="info-val">' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '</span></div>
      </div>

      <div class="btn-wrap">
        <a href="index.php?page=catalog" class="btn-action">Khám phá sản phẩm ngay</a>
      </div>
    ');

    return send_mail($to, $subject, $bodyText, $html);
}

/**
 * 2. Gửi email quên mật khẩu
 */
function mail_send_password_reset(array $user, string $resetLink): bool
{
    $to = (string)$user['email'];
    $name = (string)$user['name'];
    $subject = 'Đặt lại mật khẩu tài khoản Nike - Vietnam';

    $bodyText = "Xin chào $name,\n\n" .
                "Hệ thống nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.\n" .
                "Nhấn vào liên kết sau để đặt lại mật khẩu (hiệu lực 30 phút):\n" .
                "$resetLink\n\n" .
                "Nếu bạn không yêu cầu, vui lòng bỏ qua email này.\n" .
                "Trân trọng,\nĐội ngũ Tuyển Chọn";

    $html = mail_layout('Đặt lại mật khẩu', '
      <h2 class="email-title">Yêu cầu đặt lại mật khẩu</h2>
      <p class="email-desc">Xin chào <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>, chúng tôi vừa nhận được yêu cầu khôi phục mật khẩu cho tài khoản của bạn tại Nike - Vietnam.</p>
      
      <p>Vui lòng nhấn vào nút bên dưới để tiến hành tạo mật khẩu mới. Liên kết này có thời hạn sử dụng trong <strong>30 phút</strong>.</p>

      <div class="btn-wrap">
        <a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" class="btn-action">Đặt lại mật khẩu</a>
      </div>

      <p style="font-size:12.5px; color:#888;">Nếu nút bấm trên không hoạt động, bạn có thể copy liên kết sau vào trình duyệt:<br>
      <a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" style="color:#c74721; word-break:break-all;">' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '</a></p>
      <p style="font-size:12.5px; color:#888;">Nếu bạn không gửi yêu cầu này, vui lòng bỏ qua email. Mật khẩu của bạn vẫn hoàn toàn an toàn.</p>
    ');

    return send_mail($to, $subject, $bodyText, $html);
}

/**
 * 3. Gửi email thông báo đơn hàng cho khách hàng và quản trị viên
 * $eventType: 'placed' (khi vừa đặt hàng) hoặc 'paid' (khi thanh toán thành công qua VietQR / Ví)
 */
function mail_send_order_notifications(int $orderId, string $eventType = 'placed'): void
{
    $pdo = db();
    $s = $pdo->prepare('SELECT o.*, u.email AS user_email, u.name AS user_name 
                          FROM orders o 
                          INNER JOIN users u ON o.user_id = u.user_id 
                         WHERE o.order_id = ?');
    $s->execute([$orderId]);
    $order = $s->fetch();
    if (!$order) return;

    $d = $pdo->prepare('SELECT * FROM order_detail WHERE order_id = ? ORDER BY order_detail_id');
    $d->execute([$orderId]);
    $details = $d->fetchAll();

    $customerEmail = (string)$order['user_email'];
    $customerName = (string)$order['recipient_name'] ?: $order['user_name'];
    $finalAmountVnd = format_vnd($order['final_amount']);
    $isPaid = ($order['payment_status'] === 'paid' || $eventType === 'paid');

    // Tạo bảng sản phẩm HTML
    $itemsHtml = '';
    $itemsText = '';
    foreach ($details as $item) {
        $itemsHtml .= '<tr>
          <td><strong>' . htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') . '</strong><br><small style="color:#777">Size: ' . htmlspecialchars($item['size'], ENT_QUOTES, 'UTF-8') . ' | Màu: ' . htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') . '</small></td>
          <td style="text-align:center;">' . (int)$item['quantity'] . '</td>
          <td style="text-align:right;">' . htmlspecialchars(format_vnd($item['unit_price']), ENT_QUOTES, 'UTF-8') . '</td>
          <td style="text-align:right; font-weight:600;">' . htmlspecialchars(format_vnd($item['subtotal']), ENT_QUOTES, 'UTF-8') . '</td>
        </tr>';
        $itemsText .= "- " . $item['product_name'] . " (Size " . $item['size'] . ", " . $item['color'] . ") x" . $item['quantity'] . " = " . format_vnd($item['subtotal']) . "\n";
    }

    $statusBadge = $isPaid 
        ? '<span class="badge-tag">ĐÃ THANH TOÁN</span>'
        : '<span class="badge-tag badge-tag-pending">CHỜ THANH TOÁN</span>';

    // Nội dung chung tóm tắt đơn
    $orderSummaryHtml = '
      <div class="info-box">
        <div class="info-row"><span class="info-label">Mã đơn hàng:</span> <span class="info-val">#' . (int)$order['order_id'] . ' ' . $statusBadge . '</span></div>
        <div class="info-row"><span class="info-label">Ngày đặt:</span> <span class="info-val">' . date('d/m/Y H:i', strtotime($order['created_at'])) . '</span></div>
        <div class="info-row"><span class="info-label">Người nhận:</span> <span class="info-val">' . htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars($order['phone'], ENT_QUOTES, 'UTF-8') . '</span></div>
        <div class="info-row"><span class="info-label">Địa chỉ giao:</span> <span class="info-val">' . htmlspecialchars($order['shipping_address'], ENT_QUOTES, 'UTF-8') . '</span></div>
        <div class="info-row"><span class="info-label">Phương thức:</span> <span class="info-val">' . htmlspecialchars($order['payment_method'], ENT_QUOTES, 'UTF-8') . '</span></div>
        ' . (!empty($order['note']) ? '<div class="info-row"><span class="info-label">Ghi chú:</span> <span class="info-val">' . htmlspecialchars($order['note'], ENT_QUOTES, 'UTF-8') . '</span></div>' : '') . '
      </div>

      <table class="item-table">
        <thead>
          <tr>
            <th>Sản phẩm</th>
            <th style="text-align:center;">SL</th>
            <th style="text-align:right;">Đơn giá</th>
            <th style="text-align:right;">Thành tiền</th>
          </tr>
        </thead>
        <tbody>' . $itemsHtml . '</tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="text-align:right; color:#777;">Tạm tính:</td>
            <td style="text-align:right;">' . htmlspecialchars(format_vnd($order['total_amount']), ENT_QUOTES, 'UTF-8') . '</td>
          </tr>
          <tr>
            <td colspan="3" style="text-align:right; color:#777;">Phí vận chuyển:</td>
            <td style="text-align:right;">' . htmlspecialchars(format_vnd($order['shipping_fee']), ENT_QUOTES, 'UTF-8') . '</td>
          </tr>
          ' . ($order['discount_amount'] > 0 ? '<tr>
            <td colspan="3" style="text-align:right; color:#2e7d32;">Giảm giá:</td>
            <td style="text-align:right; color:#2e7d32;">−' . htmlspecialchars(format_vnd($order['discount_amount']), ENT_QUOTES, 'UTF-8') . '</td>
          </tr>' : '') . '
          <tr class="total-row">
            <td colspan="3" style="text-align:right;">TỔNG THANH TOÁN:</td>
            <td style="text-align:right; color:#c74721; font-size:16px;">' . htmlspecialchars($finalAmountVnd, ENT_QUOTES, 'UTF-8') . '</td>
          </tr>
        </tfoot>
      </table>
    ';

    /* --- GỬI EMAIL CHO KHÁCH HÀNG --- */
    if (filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        if ($isPaid) {
            $customerSubject = "Xác nhận thanh toán thành công - Đơn hàng #{$orderId}";
            $customerHeadline = "Thanh toán thành công!";
            $customerSub = "Cảm ơn bạn! Chúng tôi đã nhận được thanh toán cho đơn hàng <strong>#{$orderId}</strong>. Đơn hàng đang được chuẩn bị và sẽ sớm được bàn giao cho đơn vị vận chuyển.";
        } else {
            $customerSubject = "Xác nhận đặt hàng thành công - Đơn hàng #{$orderId}";
            $customerHeadline = "Cảm ơn bạn đã đặt hàng!";
            $customerSub = "Đơn hàng <strong>#{$orderId}</strong> của bạn đã được ghi nhận vào hệ thống. Chi tiết đơn hàng của bạn như sau:";
        }

        $customerBodyText = "Xin chào $customerName,\n\n$customerSubject\n\nMã đơn: #$orderId\nTổng tiền: $finalAmountVnd\nPhương thức: {$order['payment_method']}\nĐịa chỉ giao: {$order['shipping_address']}\n\nDanh sách sản phẩm:\n$itemsText\nTrân trọng cảm ơn bạn!";

        $customerHtml = mail_layout($customerSubject, '
          <h2 class="email-title">' . $customerHeadline . '</h2>
          <p class="email-desc">' . $customerSub . '</p>
          ' . $orderSummaryHtml . '
          <div class="btn-wrap">
            <a href="index.php?page=orders" class="btn-action">Theo dõi đơn hàng của bạn</a>
          </div>
        ');

        send_mail($customerEmail, $customerSubject, $customerBodyText, $customerHtml);
    }

    /* --- GỬI EMAIL THÔNG BÁO CHO QUẢN TRỊ VIÊN --- */
    $adminEmail = defined('ADMIN_EMAIL') ? trim(ADMIN_EMAIL) : '';
    // Nếu chưa cấu hình hoặc để domain ảo .local thì tự động dùng SMTP_USER làm email admin
    if ($adminEmail === '' || str_ends_with($adminEmail, '.local')) {
        $adminEmail = !empty(SMTP_USER) ? SMTP_USER : '';
    }

    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $adminSubject = $isPaid 
            ? "[ĐƠN MỚI ĐÃ THANH TOÁN] Đơn #{$orderId} - Khách {$customerName} ({$finalAmountVnd})"
            : "[ĐƠN HÀNG MỚI] Đơn #{$orderId} - Khách {$customerName} ({$finalAmountVnd})";

        $adminHeadline = $isPaid ? "Thông báo đơn hàng mới (Đã thanh toán)" : "Thông báo đơn hàng mới cần xử lý";
        $adminSub = "Website vừa ghi nhận đơn hàng <strong>#{$orderId}</strong> từ khách hàng <strong>" . htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') . "</strong>.";

        $adminBodyText = "THÔNG BÁO QUẢN TRỊ VIÊN\n$adminSubject\n\nKhách hàng: $customerName\nSĐT: {$order['phone']}\nĐịa chỉ: {$order['shipping_address']}\nTổng thanh toán: $finalAmountVnd\nPhương thức: {$order['payment_method']}\n\nChi tiết:\n$itemsText";

        $adminHtml = mail_layout($adminSubject, '
          <h2 class="email-title" style="color:#c74721;">' . $adminHeadline . '</h2>
          <p class="email-desc">' . $adminSub . '</p>
          ' . $orderSummaryHtml . '
          <div class="btn-wrap">
            <a href="index.php?page=admin_orders" class="btn-action">Vào trang quản trị đơn hàng</a>
          </div>
        ');

        send_mail($adminEmail, $adminSubject, $adminBodyText, $adminHtml);
    }
}
