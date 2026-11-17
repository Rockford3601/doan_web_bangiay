<?php
// Đặt hàng, hủy đơn, đổi trạng thái đơn.
// QUAN TRỌNG: mọi thao tác thay đổi kho / voucher / đơn đều nằm trong TRANSACTION:
//   hoặc thành công tất cả, hoặc hoàn tác tất cả (rollBack) -> không bao giờ bị "trừ kho nhưng chưa có đơn".
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

// Các bước chuyển trạng thái hợp lệ (admin)
const ORDER_FLOW = [
    'pending'   => ['confirmed', 'cancelled'],
    'confirmed' => ['shipping', 'cancelled'],
    'shipping'  => ['delivered'],
    'delivered' => [],
    'cancelled' => [],
];

function log_stock(PDO $pdo, int $variantId, int $change, string $reason, ?int $orderId, ?int $userId): void
{
    $pdo->prepare('INSERT INTO stock_movement (variant_id, change_qty, reason, order_id, user_id) VALUES (?, ?, ?, ?, ?)')
        ->execute([$variantId, $change, $reason, $orderId, $userId]);
}

function log_status(PDO $pdo, int $orderId, ?string $old, string $new, ?int $by, ?string $note = null): void
{
    $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)')
        ->execute([$orderId, $old, $new, $by, $note]);
}

/* =====================================================================
 * ĐẶT HÀNG
 * ===================================================================*/
function action_place_order(): void
{
    require_login();
    $uid = (int)$_SESSION['user']['user_id'];

    $name    = post_str('recipient_name');
    $phone   = post_str('phone');
    $address = post_str('shipping_address');
    $note    = post_str('note');
    $method  = post_str('payment_method');

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100)       $errors[] = 'Tên người nhận phải từ 2 đến 100 ký tự.';
    if (!preg_match('/^0\d{9,10}$/', $phone))                  $errors[] = 'Số điện thoại phải bắt đầu bằng 0 và có 10-11 chữ số.';
    if (mb_strlen($address) < 10 || mb_strlen($address) > 500) $errors[] = 'Địa chỉ giao hàng cần ghi đầy đủ (10-500 ký tự).';
    if (mb_strlen($note) > 500)                                $errors[] = 'Ghi chú tối đa 500 ký tự.';
    if (!array_key_exists($method, PAYMENT_METHODS))           $errors[] = 'Vui lòng chọn phương thức thanh toán hợp lệ.';

    if ($errors) {
        foreach ($errors as $err) {
            flash_set('error', $err);
        }
        $_SESSION['checkout_old'] = ['recipient_name' => $name, 'phone' => $phone, 'shipping_address' => $address, 'note' => $note, 'payment_method' => $method];
        redirect('index.php?page=checkout');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $cartId = get_or_create_cart_id($uid);
        $lines  = get_cart_lines($cartId);
        if (!$lines) {
            throw new BusinessException('Giỏ hàng đang trống.');
        }

        // ---- 1. Trừ kho: mỗi dòng 1 câu UPDATE có điều kiện (nguyên tử) ----
        // Nếu 2 người cùng mua món cuối: người đến trước trừ thành công (rowCount = 1),
        // người đến sau thấy stock_quantity < số lượng nên WHERE không khớp (rowCount = 0) -> báo hết hàng.
        $subtotal = 0.0;
        foreach ($lines as $l) { // $lines đã sắp xếp theo variant_id: tránh deadlock khi 2 đơn khóa chéo
            $qty = (int)$l['quantity'];
            $upd = $pdo->prepare("UPDATE product_variant SET stock_quantity = stock_quantity - ? WHERE variant_id = ? AND status = 'active' AND stock_quantity >= ?");
            $upd->execute([$qty, $l['variant_id'], $qty]);
            if ($upd->rowCount() !== 1) {
                throw new BusinessException('"' . $l['name'] . '" (size ' . $l['size'] . ', ' . $l['color'] . ') không còn đủ hàng. Vui lòng kiểm tra lại giỏ.');
            }
            if ($l['flash_item_id'] !== null) {
                $fl = $pdo->prepare('UPDATE flash_sale_item SET sold_quantity = sold_quantity + ? WHERE flash_sale_item_id = ? AND sold_quantity + ? <= quantity_limit');
                $fl->execute([$qty, $l['flash_item_id'], $qty]);
                if ($fl->rowCount() !== 1) {
                    throw new BusinessException('Suất flash sale của "' . $l['name'] . '" vừa hết. Vui lòng đặt lại.');
                }
            }
            $subtotal += (float)$l['line_total'];
        }

        // ---- 2. Voucher: khóa dòng voucher rồi kiểm tra lại toàn bộ điều kiện ----
        $voucher  = null;
        $discount = 0.0;
        $code     = (string)($_SESSION['voucher_code'] ?? '');
        if ($code !== '') {
            $voucher = find_voucher($code, true);
            [$ok, $msg] = voucher_check($voucher, $subtotal, $uid);
            if (!$ok) {
                unset($_SESSION['voucher_code']);
                throw new BusinessException($msg);
            }
            $discount = voucher_discount($voucher, $subtotal);
            $vu = $pdo->prepare('UPDATE voucher SET used_count = used_count + 1 WHERE voucher_id = ? AND (usage_limit = 0 OR used_count < usage_limit)');
            $vu->execute([$voucher['voucher_id']]);
            if ($vu->rowCount() !== 1) {
                throw new BusinessException('Mã giảm giá vừa hết lượt sử dụng.');
            }
        }

        $totals = calc_totals($subtotal, $discount);

        // ---- 3. Tạo đơn (lưu cứng địa chỉ, giá tại thời điểm mua) ----
        $pdo->prepare(
            "INSERT INTO orders (user_id, address_id, voucher_id, total_amount, shipping_fee, discount_amount, final_amount,
                                 payment_method, payment_status, order_status, recipient_name, phone, shipping_address, note)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 'unpaid', 'pending', ?, ?, ?, ?)"
        )->execute([
            $uid, $voucher ? $voucher['voucher_id'] : null,
            $totals['subtotal'], $totals['shipping'], $totals['discount'], $totals['final'],
            $method, $name, $phone, $address, $note === '' ? null : $note,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare(
            'INSERT INTO order_detail (order_id, variant_id, product_name, sku, size, color, quantity, unit_price, subtotal, flash_sale_item_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lines as $l) {
            $ins->execute([$orderId, $l['variant_id'], $l['name'], $l['sku'], $l['size'], $l['color'],
                           $l['quantity'], $l['unit_price'], $l['line_total'], $l['flash_item_id']]);
            log_stock($pdo, (int)$l['variant_id'], -(int)$l['quantity'], 'order', $orderId, $uid);
        }

        if ($voucher) {
            // UNIQUE(voucher_id, user_id): dù bấm đặt hàng 2 lần, 1 người chỉ dùng được 1 lần
            $pdo->prepare('INSERT INTO voucher_usage (voucher_id, user_id, order_id) VALUES (?, ?, ?)')
                ->execute([$voucher['voucher_id'], $uid, $orderId]);
        }

        $pdo->prepare("INSERT INTO payment (order_id, payment_method, transaction_code, amount, payment_status) VALUES (?, ?, NULL, ?, 'unpaid')")
            ->execute([$orderId, $method, $totals['final']]);
        log_status($pdo, $orderId, null, 'pending', $uid, 'Khách đặt hàng');

        $pdo->prepare('DELETE FROM cart_item WHERE cart_id = ?')->execute([$cartId]);

        $pdo->commit();
    } catch (BusinessException $e) {          // lỗi nghiệp vụ do mình chủ động ném: hiện thẳng cho khách
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('error', $e->getMessage());
        redirect('index.php?page=cart');
    } catch (Throwable $e) {                 // lỗi hệ thống: ghi log, không lộ chi tiết cho khách
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('place_order loi: ' . $e->getMessage());
        flash_set('error', 'Không thể đặt hàng lúc này. Vui lòng thử lại (hoặc kiểm tra mã giảm giá đã dùng chưa).');
        redirect('index.php?page=cart');
    }

    unset($_SESSION['voucher_code'], $_SESSION['checkout_old']);
    require_once __DIR__ . '/../helpers/mailer.php';
    mail_send_order_notifications($orderId, 'placed');

    if ($method === WALLET_METHOD) {
        redirect('index.php?page=pay_sim&id=' . $orderId);   // chuyển sang trang thanh toán ví (mô phỏng)
    }
    if ($method === 'Chuyển khoản') {
        redirect('index.php?page=pay_transfer&id=' . $orderId); // chuyển sang trang quét mã VietQR
    }
    redirect('index.php?page=order_success&id=' . $orderId);
}

/* =====================================================================
 * HỦY ĐƠN (dùng chung cho khách và admin)
 * Trả về [true|false, 'thông báo']
 * ===================================================================*/
function cancel_order(int $orderId, int $actorId, bool $asAdmin, ?string $note = null): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();

        $s = $pdo->prepare('SELECT * FROM orders WHERE order_id = ? FOR UPDATE'); // khóa đơn: tránh vừa hủy vừa giao
        $s->execute([$orderId]);
        $o = $s->fetch();

        if (!$o || (!$asAdmin && (int)$o['user_id'] !== $actorId)) {
            throw new BusinessException('Không tìm thấy đơn hàng.');
        }
        // Cả khách hàng và admin đều được hủy khi đơn ở trạng thái Chờ xác nhận (pending) hoặc Đã xác nhận (confirmed)
        // Khi đơn đã chuyển sang "Đang giao" (shipping) hoặc "Đã giao" (delivered) thì hàng đã xuất kho cho bưu tá, không thể tự hủy trực tiếp
        $allowed = ['pending', 'confirmed'];
        if (!in_array($o['order_status'], $allowed, true)) {
            throw new BusinessException('Đơn hàng ở trạng thái "' . (ORDER_STATUS[$o['order_status']] ?? $o['order_status']) . '" không thể tự hủy do hàng đã được đóng gói vận chuyển. Vui lòng liên hệ cửa hàng hoặc yêu cầu Đổi/Trả khi nhận hàng.');
        }

        // Hoàn lại kho (và suất flash sale)
        $d = $pdo->prepare('SELECT variant_id, quantity, flash_sale_item_id FROM order_detail WHERE order_id = ?');
        $d->execute([$orderId]);
        foreach ($d->fetchAll() as $row) {
            $pdo->prepare('UPDATE product_variant SET stock_quantity = stock_quantity + ? WHERE variant_id = ?')
                ->execute([$row['quantity'], $row['variant_id']]);
            log_stock($pdo, (int)$row['variant_id'], (int)$row['quantity'], 'cancel', $orderId, $actorId);
            if ($row['flash_sale_item_id']) {
                $pdo->prepare('UPDATE flash_sale_item SET sold_quantity = GREATEST(sold_quantity - ?, 0) WHERE flash_sale_item_id = ?')
                    ->execute([$row['quantity'], $row['flash_sale_item_id']]);
            }
        }

        // Hoàn lại lượt dùng voucher
        if ($o['voucher_id']) {
            $pdo->prepare('UPDATE voucher SET used_count = GREATEST(used_count - 1, 0) WHERE voucher_id = ?')->execute([$o['voucher_id']]);
            $pdo->prepare('DELETE FROM voucher_usage WHERE voucher_id = ? AND order_id = ?')->execute([$o['voucher_id'], $orderId]);
        }

        // Nếu đã thanh toán thì đánh dấu chờ hoàn tiền
        $payStatus = ($o['payment_status'] === 'paid') ? 'refund_pending' : $o['payment_status'];
        $pdo->prepare("UPDATE orders SET order_status = 'cancelled', payment_status = ? WHERE order_id = ?")->execute([$payStatus, $orderId]);
        $pdo->prepare('UPDATE payment SET payment_status = ? WHERE order_id = ?')->execute([$payStatus, $orderId]);
        log_status($pdo, $orderId, $o['order_status'], 'cancelled', $actorId, ($note !== null && $note !== '') ? $note : ($asAdmin ? 'Admin hủy đơn' : 'Khách hủy đơn'));

        $pdo->commit();
        return [true, 'Đã hủy đơn hàng #' . $orderId . '.'];
    } catch (BusinessException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('cancel_order loi: ' . $e->getMessage());
        return [false, 'Không thể hủy đơn lúc này.'];
    }
}

function action_cancel_order(): void
{
    require_login();
    $orderId = post_int('order_id');
    $reason  = trim(post_str('cancel_reason'));
    if ($reason === '') {
        $reason = 'Khách đặt nhầm sản phẩm / màu / size';
    }
    $note = 'Khách hủy đơn: ' . mb_substr($reason, 0, 200);

    [$ok, $msg] = cancel_order($orderId, (int)$_SESSION['user']['user_id'], false, $note);
    flash_set($ok ? 'success' : 'error', $msg);

    // Nếu hủy từ trang đặt hàng thành công hoặc trang khác, redirect về trang orders
    redirect('index.php?page=orders');
}

/* =====================================================================
 * ĐỔI TRẠNG THÁI ĐƠN (admin)
 * ===================================================================*/
function change_order_status(int $orderId, string $new, int $adminId, ?string $note = null, ?string $tracking = null): array
{
    if ($new === 'cancelled') {
        return cancel_order($orderId, $adminId, true, $note);
    }
    if ($tracking !== null && !preg_match('/^[A-Za-z0-9._\-]{3,50}$/', $tracking)) {
        return [false, 'Mã vận đơn gồm 3-50 ký tự: chữ, số, dấu . _ -'];
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT * FROM orders WHERE order_id = ? FOR UPDATE');
        $s->execute([$orderId]);
        $o = $s->fetch();
        if (!$o) {
            throw new BusinessException('Không tìm thấy đơn hàng.');
        }
        if (!in_array($new, ORDER_FLOW[$o['order_status']] ?? [], true)) {
            throw new BusinessException('Không thể chuyển từ "' . (ORDER_STATUS[$o['order_status']] ?? '?') . '" sang "' . (ORDER_STATUS[$new] ?? '?') . '".');
        }

        // Online chưa thanh toán thì chưa nên giao hàng
        if ($new === 'shipping' && $o['payment_method'] !== 'COD' && $o['payment_status'] !== 'paid') {
            throw new BusinessException('Đơn thanh toán online chưa được thanh toán, chưa thể giao hàng.');
        }

        if ($new === 'shipping' && $tracking !== null) {
            $pdo->prepare('UPDATE orders SET order_status = ?, tracking_code = ? WHERE order_id = ?')->execute([$new, $tracking, $orderId]);
            $note = trim(($note ?? '') . ' Mã vận đơn: ' . $tracking);
        } else {
            $pdo->prepare('UPDATE orders SET order_status = ? WHERE order_id = ?')->execute([$new, $orderId]);
        }
        log_status($pdo, $orderId, $o['order_status'], $new, $adminId, $note);

        if ($new === 'delivered') {
            // COD: nhận hàng = đã thu tiền
            if ($o['payment_status'] === 'unpaid' && $o['payment_method'] === 'COD') {
                $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE order_id = ?")->execute([$orderId]);
                $pdo->prepare("UPDATE payment SET payment_status = 'paid', paid_at = NOW() WHERE order_id = ?")->execute([$orderId]);
            }
            // Cộng điểm: UNIQUE(order_id) + INSERT IGNORE => mỗi đơn chỉ cộng đúng 1 lần
            $points = (int)floor((float)$o['final_amount'] / POINT_PER_VND);
            if ($points > 0) {
                $ins = $pdo->prepare('INSERT IGNORE INTO point_transaction (user_id, order_id, points, reason) VALUES (?, ?, ?, ?)');
                $ins->execute([$o['user_id'], $orderId, $points, 'Hoàn thành đơn #' . $orderId]);
                if ($ins->rowCount() === 1) {
                    $pdo->prepare('UPDATE users SET points = points + ? WHERE user_id = ?')->execute([$points, $o['user_id']]);
                }
            }
        }
        $pdo->commit();
        return [true, 'Đơn #' . $orderId . ' đã chuyển sang "' . ORDER_STATUS[$new] . '".'];
    } catch (BusinessException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'Đơn #' . $orderId . ': ' . $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('change_order_status loi: ' . $e->getMessage());
        return [false, 'Đơn #' . $orderId . ': không thể cập nhật lúc này.'];
    }
}

// Đổi trạng thái nhiều đơn một lúc. Mỗi đơn xử lý trong transaction riêng: đơn nào lỗi chỉ bỏ qua đơn đó.
// Trả về [số đơn thành công, [thông báo lỗi từng đơn]]
function bulk_change_status(array $ids, string $new, int $adminId, ?string $note): array
{
    $ok = 0;
    $fails = [];
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0))), 0, 100);
    foreach ($ids as $id) {
        [$r, $msg] = change_order_status($id, $new, $adminId, $note, null);
        if ($r) $ok++; else $fails[] = $msg;
    }
    return [$ok, $fails];
}

function mark_order_paid(int $orderId, string $transactionCode): array
{
    $pdo = db();
    $auto = false;
    try {
        $pdo->beginTransaction();
        $u = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE order_id = ? AND payment_status = 'unpaid' AND order_status <> 'cancelled'");
        $u->execute([$orderId]);
        if ($u->rowCount() !== 1) {
            throw new BusinessException('Đơn không ở trạng thái chờ thanh toán.');
        }
        $pdo->prepare("UPDATE payment SET payment_status = 'paid', paid_at = NOW(), transaction_code = ? WHERE order_id = ?")
            ->execute([$transactionCode === '' ? null : $transactionCode, $orderId]);

        // Tự động hóa: thanh toán xong -> tự xác nhận đơn đang chờ
        if (AUTO_CONFIRM_PAID) {
            $c = $pdo->prepare("UPDATE orders SET order_status = 'confirmed' WHERE order_id = ? AND order_status = 'pending'");
            $c->execute([$orderId]);
            if ($c->rowCount() === 1) {
                log_status($pdo, $orderId, 'pending', 'confirmed', null, 'Tự động xác nhận: đã thanh toán');
                $auto = true;
            }
        }
        $pdo->commit();

        // Gửi email thông báo thanh toán thành công cho khách và admin
        try {
            require_once __DIR__ . '/../helpers/mailer.php';
            mail_send_order_notifications($orderId, 'paid');
        } catch (Throwable $e) {
            error_log('Gui mail don paid loi: ' . $e->getMessage());
        }

        return [true, 'Đã ghi nhận thanh toán đơn #' . $orderId . ($auto ? ' và tự động xác nhận đơn.' : '.')];
    } catch (BusinessException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('mark_order_paid loi: ' . $e->getMessage());
        return [false, 'Mã giao dịch đã tồn tại hoặc có lỗi hệ thống.'];
    }
}

// Đơn đã hủy nhưng khách đã trả tiền (payment_status = refund_pending) -> nhân viên chuyển tiền hoàn rồi bấm xác nhận
function mark_order_refunded(int $orderId, string $refundCode, int $adminId): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $u = $pdo->prepare("UPDATE orders SET payment_status = 'refunded' WHERE order_id = ? AND payment_status = 'refund_pending'");
        $u->execute([$orderId]);
        if ($u->rowCount() !== 1) {
            throw new BusinessException('Đơn không ở trạng thái chờ hoàn tiền.');
        }
        $pdo->prepare("UPDATE payment SET payment_status = 'refunded' WHERE order_id = ?")->execute([$orderId]);
        log_status($pdo, $orderId, 'cancelled', 'cancelled', $adminId, 'Đã hoàn tiền cho khách' . ($refundCode !== '' ? ' - mã GD ' . $refundCode : ''));
        $pdo->commit();
        return [true, 'Đã ghi nhận hoàn tiền đơn #' . $orderId . '.'];
    } catch (BusinessException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('mark_order_refunded loi: ' . $e->getMessage());
        return [false, 'Không thể ghi nhận hoàn tiền lúc này.'];
    }
}


/* =====================================================================
 * VÍ ĐIỆN TỬ (MÔ PHỎNG). Dùng để demo luồng "thanh toán online" mà không cần tài khoản merchant thật.
 * Muốn nối MoMo/VNPay thật: thay hàm này bằng việc kiểm tra chữ ký (HMAC) của cổng gửi về (IPN/return URL),
 * rồi vẫn gọi mark_order_paid() - hàm đó đã chống ghi nhận thanh toán 2 lần.
 * ===================================================================*/
function action_pay_simulate(): void
{
    require_login();
    $uid     = (int)$_SESSION['user']['user_id'];
    $orderId = post_int('order_id');
    $result  = post_str('result');

    $s = db()->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ? AND payment_method = ? AND payment_status = 'unpaid' AND order_status <> 'cancelled'");
    $s->execute([$orderId, $uid, WALLET_METHOD]);
    if (!$s->fetch()) {
        flash_set('error', 'Đơn hàng không ở trạng thái chờ thanh toán ví.');
        redirect('index.php?page=orders');
    }
    if ($result === 'success') {
        [$ok, $msg] = mark_order_paid($orderId, 'SIM' . strtoupper(bin2hex(random_bytes(5))));
        flash_set($ok ? 'success' : 'error', $msg);
        redirect('index.php?page=order_success&id=' . $orderId);
    }
    flash_set('info', 'Thanh toán chưa hoàn tất. Đơn vẫn được giữ; bạn có thể thanh toán lại trong "Đơn hàng của tôi" hoặc hủy đơn.');
    redirect('index.php?page=orders');
}

/**
 * API trả JSON kiểm tra trạng thái thanh toán (cho trang VietQR polling)
 */
function api_check_payment(): void
{
    header('Content-Type: application/json; charset=UTF-8');
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['error' => 'Chưa đăng nhập']);
        return;
    }
    $orderId = get_int('order_id');
    $uid = (int)$_SESSION['user']['user_id'];
    $s = db()->prepare('SELECT order_id, payment_status, order_status, final_amount FROM orders WHERE order_id = ? AND user_id = ?');
    $s->execute([$orderId, $uid]);
    $order = $s->fetch();
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy đơn hàng']);
        return;
    }
    echo json_encode([
        'order_id'       => (int)$order['order_id'],
        'payment_status' => $order['payment_status'],
        'order_status'   => $order['order_status'],
        'paid'           => ($order['payment_status'] === 'paid'),
    ]);
}

/**
 * API mô phỏng thanh toán thành công (cho demo/testing VietQR nhanh)
 */
function api_simulate_pay(): void
{
    header('Content-Type: application/json; charset=UTF-8');
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
        return;
    }
    $orderId = get_int('order_id') ?: post_int('order_id');
    $uid = (int)$_SESSION['user']['user_id'];
    $s = db()->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ? AND payment_status = 'unpaid' AND order_status <> 'cancelled'");
    $s->execute([$orderId, $uid]);
    if (!$s->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Đơn không hợp lệ hoặc đã thanh toán']);
        return;
    }
    $transCode = 'VQR' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
    [$ok, $msg] = mark_order_paid($orderId, $transCode);
    echo json_encode(['success' => $ok, 'message' => $msg, 'transaction_code' => $transCode]);
}
