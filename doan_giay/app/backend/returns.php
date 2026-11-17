<?php
// Đổi / trả hàng và hoàn tiền.
// Luồng: Khách gửi yêu cầu (requested) -> Nhân viên duyệt (approved) hoặc từ chối (rejected)
//        -> Nhận lại hàng (received: cộng lại kho) -> Hoàn tiền (refunded) nếu là "trả hàng", hoặc Hoàn tất (completed) nếu là "đổi hàng".
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}
require_once __DIR__ . '/orders.php'; // log_stock()

const RETURN_FLOW = [
    'requested' => ['approved', 'rejected'],
    'approved'  => ['received'],
    'received'  => ['refunded', 'completed'],
    'rejected'  => [],
    'refunded'  => [],
    'completed' => [],
];

// Số ngày kể từ khi đơn được giao (null nếu chưa giao)
function days_since_delivered(int $orderId): ?int
{
    $s = db()->prepare(
        "SELECT DATEDIFF(NOW(), COALESCE((SELECT MAX(created_at) FROM order_status_history WHERE order_id = o.order_id AND new_status = 'delivered'), o.updated_at))
           FROM orders o WHERE o.order_id = ? AND o.order_status = 'delivered'"
    );
    $s->execute([$orderId]);
    $v = $s->fetchColumn();
    return ($v === false || $v === null) ? null : (int)$v;
}

function action_return_request(): void
{
    require_login();
    $uid     = (int)$_SESSION['user']['user_id'];
    $orderId = post_int('order_id');
    $type    = post_str('request_type');
    $reason  = post_str('reason');
    $qtys    = (isset($_POST['qty']) && is_array($_POST['qty'])) ? $_POST['qty'] : [];
    $back    = 'index.php?page=return_new&order_id=' . $orderId;

    if (!array_key_exists($type, RETURN_TYPES)) {
        flash_set('error', 'Vui lòng chọn hình thức đổi hoặc trả.');
        redirect($back);
    }
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
        flash_set('error', 'Lý do đổi/trả cần từ 5 đến 500 ký tự.');
        redirect($back);
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ? AND order_status = 'delivered' FOR UPDATE"); // khóa đơn: tránh gửi trùng đồng thời
        $s->execute([$orderId, $uid]);
        if (!$s->fetch()) {
            throw new BusinessException('Chỉ đơn hàng đã giao của bạn mới được đổi/trả.');
        }
        $days = days_since_delivered($orderId);
        if ($days === null || $days > RETURN_DAYS) {
            throw new BusinessException('Đã quá ' . RETURN_DAYS . ' ngày kể từ khi nhận hàng nên không thể đổi/trả.');
        }

        $d = $pdo->prepare('SELECT order_detail_id, quantity FROM order_detail WHERE order_id = ?');
        $d->execute([$orderId]);
        $details = $d->fetchAll();

        $a = $pdo->prepare("SELECT order_detail_id, SUM(quantity) AS q FROM return_request WHERE order_id = ? AND status <> 'rejected' GROUP BY order_detail_id");
        $a->execute([$orderId]);
        $already = $a->fetchAll(PDO::FETCH_KEY_PAIR);

        $ins = $pdo->prepare('INSERT INTO return_request (order_id, order_detail_id, user_id, request_type, quantity, reason) VALUES (?, ?, ?, ?, ?, ?)');
        $count = 0;
        foreach ($details as $row) {
            $q = (int)($qtys[$row['order_detail_id']] ?? 0);
            if ($q <= 0) continue;
            $remain = (int)$row['quantity'] - (int)($already[$row['order_detail_id']] ?? 0);
            if ($q > $remain) {
                throw new BusinessException('Số lượng yêu cầu vượt quá số lượng còn có thể đổi/trả (' . max(0, $remain) . ').');
            }
            $ins->execute([$orderId, $row['order_detail_id'], $uid, $type, $q, $reason]);
            $count++;
        }
        if ($count === 0) {
            throw new BusinessException('Vui lòng chọn ít nhất một sản phẩm và số lượng.');
        }
        $pdo->commit();
    } catch (BusinessException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('error', $e->getMessage());
        redirect($back);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('return_request loi: ' . $e->getMessage());
        flash_set('error', 'Không thể gửi yêu cầu lúc này.');
        redirect($back);
    }
    flash_set('success', 'Đã gửi yêu cầu. Cửa hàng sẽ phản hồi sớm.');
    redirect('index.php?page=returns');
}

// Nhân viên xử lý yêu cầu: trả về [true|false, 'thông báo']
function process_return(int $returnId, string $new, string $note, int $staffId): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare(
            'SELECT rr.*, d.variant_id, d.unit_price, o.total_amount, o.payment_status
               FROM return_request rr
               JOIN order_detail d ON d.order_detail_id = rr.order_detail_id
               JOIN orders o ON o.order_id = rr.order_id
              WHERE rr.return_id = ? FOR UPDATE'
        );
        $s->execute([$returnId]);
        $r = $s->fetch();
        if (!$r) {
            throw new BusinessException('Không tìm thấy yêu cầu.');
        }
        if (!in_array($new, RETURN_FLOW[$r['status']] ?? [], true)) {
            throw new BusinessException('Không thể chuyển từ "' . (RETURN_STATUS[$r['status']] ?? '?') . '" sang "' . (RETURN_STATUS[$new] ?? '?') . '".');
        }
        if ($new === 'refunded' && $r['request_type'] !== 'return') {
            throw new BusinessException('Yêu cầu ĐỔI hàng không hoàn tiền; hãy chọn "Hoàn tất".');
        }
        if ($new === 'completed' && $r['request_type'] !== 'exchange') {
            throw new BusinessException('Yêu cầu TRẢ hàng cần chuyển sang "Đã hoàn tiền".');
        }

        $refund = (float)$r['refund_amount'];
        if ($new === 'received') {
            // Hàng trả về kho (giả định còn nguyên vẹn, có thể bán lại)
            $pdo->prepare('UPDATE product_variant SET stock_quantity = stock_quantity + ? WHERE variant_id = ?')->execute([$r['quantity'], $r['variant_id']]);
            log_stock($pdo, (int)$r['variant_id'], (int)$r['quantity'], 'return', (int)$r['order_id'], $staffId);
        }
        if ($new === 'refunded') {
            $refund = (float)$r['unit_price'] * (int)$r['quantity'];
            if (in_array($r['payment_status'], ['paid', 'partial_refund'], true)) {
                $t = $pdo->prepare("SELECT COALESCE(SUM(refund_amount), 0) FROM return_request WHERE order_id = ? AND status = 'refunded'");
                $t->execute([$r['order_id']]);
                $totalRefunded = (float)$t->fetchColumn() + $refund;
                $pay = ($totalRefunded >= (float)$r['total_amount']) ? 'refunded' : 'partial_refund';
                $pdo->prepare('UPDATE orders SET payment_status = ? WHERE order_id = ?')->execute([$pay, $r['order_id']]);
                $pdo->prepare('UPDATE payment SET payment_status = ? WHERE order_id = ?')->execute([$pay, $r['order_id']]);
            }
        }
        $pdo->prepare('UPDATE return_request SET status = ?, refund_amount = ?, admin_note = ? WHERE return_id = ?')
            ->execute([$new, $refund, $note === '' ? $r['admin_note'] : $note, $returnId]);
        $pdo->commit();
        return [true, 'Yêu cầu #' . $returnId . ' đã chuyển sang "' . RETURN_STATUS[$new] . '".'];
    } catch (BusinessException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('process_return loi: ' . $e->getMessage());
        return [false, 'Không thể cập nhật yêu cầu lúc này.'];
    }
}
