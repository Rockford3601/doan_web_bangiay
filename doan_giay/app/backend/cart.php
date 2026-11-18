<?php
// Hành động giỏ hàng và voucher. Giỏ hàng lưu trong CSDL, gắn với từng VARIANT (size + màu).
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

/**
 * Hàm phụ trợ tính toán tổng hợp tóm tắt giỏ hàng để trả về cho AJAX
 */
function get_cart_summary_data(int $uid): array
{
    $lines = get_cart_lines(get_or_create_cart_id($uid));
    $subtotal = cart_subtotal($lines);
    $voucher = null;
    $discount = 0.0;
    $code = (string)($_SESSION['voucher_code'] ?? '');
    if ($code !== '') {
        $v = find_voucher($code);
        [$ok] = voucher_check($v, $subtotal, $uid);
        if ($ok) {
            $voucher = $v;
            $discount = voucher_discount($v, $subtotal);
        } else {
            unset($_SESSION['voucher_code']);
        }
    }
    $totals = calc_totals($subtotal, $discount);
    $itemCount = 0;
    foreach ($lines as $l) {
        $itemCount += (int)$l['quantity'];
    }

    return [
        'cart_count'     => (int)cart_count(),
        'item_count'     => $itemCount,
        'line_count'     => count($lines),
        'is_empty'       => empty($lines),
        'subtotal'       => (float)$totals['subtotal'],
        'subtotal_fmt'   => format_vnd($totals['subtotal']),
        'shipping'       => (float)$totals['shipping'],
        'shipping_fmt'   => $totals['shipping'] > 0 ? format_vnd($totals['shipping']) : 'Miễn phí',
        'discount'       => (float)$totals['discount'],
        'discount_fmt'   => format_vnd($totals['discount']),
        'final'          => (float)$totals['final'],
        'final_fmt'      => format_vnd($totals['final']),
        'free_ship_text' => ($subtotal >= FREE_SHIP_FROM)
            ? 'Đơn hàng của bạn được miễn phí vận chuyển.'
            : ('Mua thêm ' . format_vnd(FREE_SHIP_FROM - $subtotal) . ' để được miễn phí vận chuyển.'),
        'voucher_code'   => $voucher ? $voucher['code'] : null,
    ];
}

function action_cart_add(): void
{
    require_login();
    $uid       = (int)$_SESSION['user']['user_id'];
    $variantId = post_int('variant_id');
    $qty       = post_int('quantity');
    $return    = safe_return_url(post_str('return'), 'index.php?page=cart');

    if ($variantId <= 0 || $qty < 1 || $qty > MAX_QTY_PER_ITEM) {
        $msg = 'Vui lòng chọn size/màu và số lượng hợp lệ (1-' . MAX_QTY_PER_ITEM . ').';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect($return);
    }

    $s = db()->prepare(
        "SELECT v.stock_quantity, p.name FROM product_variant v JOIN product p ON p.product_id = v.product_id
          WHERE v.variant_id = ? AND v.status = 'active' AND p.status = 'active'"
    );
    $s->execute([$variantId]);
    $v = $s->fetch();
    if (!$v) {
        $msg = 'Phiên bản sản phẩm này không còn bán.';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect($return);
    }

    $cartId = get_or_create_cart_id($uid);
    $s = db()->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_item WHERE cart_id = ? AND variant_id = ?');
    $s->execute([$cartId, $variantId]);
    $inCart = (int)$s->fetchColumn();

    $newQty = min($inCart + $qty, MAX_QTY_PER_ITEM);
    if ($newQty > (int)$v['stock_quantity']) {
        $msg = 'Chỉ còn ' . (int)$v['stock_quantity'] . ' sản phẩm trong kho (bạn đã có ' . $inCart . ' trong giỏ).';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect($return);
    }

    // UNIQUE(cart_id, variant_id): nếu đã có thì cập nhật số lượng thay vì thêm dòng mới
    db()->prepare(
        'INSERT INTO cart_item (cart_id, variant_id, quantity) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = ?'
    )->execute([$cartId, $variantId, $newQty, $newQty]);

    if (post_str('buy_now') === '1') {
        redirect('index.php?page=checkout');
    }

    $msg = 'Đã thêm "' . $v['name'] . '" vào giỏ hàng.';
    if (is_ajax()) {
        json_response([
            'success'    => true,
            'message'    => $msg,
            'cart_count' => (int)cart_count()
        ]);
    }

    flash_set('success', $msg);
    redirect($return);
}

function action_cart_update(): void
{
    require_login();
    $uid    = (int)$_SESSION['user']['user_id'];
    $itemId = post_int('cart_item_id');
    $qty    = post_int('quantity');

    // chỉ lấy dòng thuộc giỏ của CHÍNH người này (chống sửa giỏ người khác)
    $s = db()->prepare(
        'SELECT ci.cart_item_id, v.stock_quantity FROM cart_item ci
           JOIN cart ca ON ca.cart_id = ci.cart_id
           JOIN product_variant v ON v.variant_id = ci.variant_id
          WHERE ci.cart_item_id = ? AND ca.user_id = ?'
    );
    $s->execute([$itemId, $uid]);
    $row = $s->fetch();
    if (!$row) {
        $msg = 'Không tìm thấy sản phẩm trong giỏ.';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect('index.php?page=cart');
    }

    $msg = '';
    if ($qty <= 0) {
        db()->prepare('DELETE FROM cart_item WHERE cart_item_id = ?')->execute([$itemId]);
        $msg = 'Đã xóa sản phẩm khỏi giỏ.';
    } elseif ($qty > MAX_QTY_PER_ITEM || $qty > (int)$row['stock_quantity']) {
        $maxAllowed = min(MAX_QTY_PER_ITEM, (int)$row['stock_quantity']);
        $msg = 'Số lượng tối đa có thể mua là ' . $maxAllowed . '.';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect('index.php?page=cart');
    } else {
        db()->prepare('UPDATE cart_item SET quantity = ? WHERE cart_item_id = ?')->execute([$qty, $itemId]);
        $msg = 'Đã cập nhật số lượng.';
    }

    if (is_ajax()) {
        $summary = get_cart_summary_data($uid);
        $lineTotalFmt = '0 ₫';
        $lines = get_cart_lines(get_or_create_cart_id($uid));
        foreach ($lines as $l) {
            if ((int)$l['cart_item_id'] === $itemId) {
                $lineTotalFmt = format_vnd($l['line_total']);
                break;
            }
        }
        json_response([
            'success'        => true,
            'message'        => $msg,
            'item_id'        => $itemId,
            'quantity'       => $qty,
            'line_total_fmt' => $lineTotalFmt,
            'summary'        => $summary
        ]);
    }

    if ($msg !== '') {
        flash_set('info', $msg);
    }
    redirect('index.php?page=cart');
}

function action_cart_remove(): void
{
    require_login();
    $uid    = (int)$_SESSION['user']['user_id'];
    $itemId = post_int('cart_item_id');

    $s = db()->prepare(
        'DELETE ci FROM cart_item ci JOIN cart ca ON ca.cart_id = ci.cart_id
          WHERE ci.cart_item_id = ? AND ca.user_id = ?'
    );
    $s->execute([$itemId, $uid]);

    if (is_ajax()) {
        $summary = get_cart_summary_data($uid);
        json_response([
            'success' => true,
            'message' => 'Đã xóa sản phẩm khỏi giỏ.',
            'item_id' => $itemId,
            'summary' => $summary
        ]);
    }

    flash_set('info', 'Đã xóa sản phẩm khỏi giỏ.');
    redirect('index.php?page=cart');
}

function action_apply_voucher(): void
{
    require_login();
    $uid  = (int)$_SESSION['user']['user_id'];
    $code = strtoupper(post_str('voucher_code'));
    $back = safe_return_url(post_str('return'), 'index.php?page=cart');

    if ($code === '') {
        $msg = 'Vui lòng nhập mã giảm giá.';
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
        redirect($back);
    }
    $lines    = get_cart_lines(get_or_create_cart_id($uid));
    $subtotal = cart_subtotal($lines);
    [$ok, $msg] = voucher_check(find_voucher($code), $subtotal, $uid);

    if ($ok) {
        $_SESSION['voucher_code'] = $code; // chỉ lưu MÃ; số tiền giảm luôn được tính lại ở server
        if (is_ajax()) {
            json_response([
                'success' => true,
                'message' => 'Đã áp dụng mã ' . $code . '.',
                'summary' => get_cart_summary_data($uid)
            ]);
        }
        flash_set('success', 'Đã áp dụng mã ' . $code . '.');
    } else {
        unset($_SESSION['voucher_code']);
        if (is_ajax()) {
            json_response(['success' => false, 'message' => $msg]);
        }
        flash_set('error', $msg);
    }
    redirect($back);
}

function action_remove_voucher(): void
{
    require_login();
    $uid = (int)$_SESSION['user']['user_id'];
    unset($_SESSION['voucher_code']);

    if (is_ajax()) {
        json_response([
            'success' => true,
            'message' => 'Đã bỏ mã giảm giá.',
            'summary' => get_cart_summary_data($uid)
        ]);
    }

    flash_set('info', 'Đã bỏ mã giảm giá.');
    redirect(safe_return_url(post_str('return'), 'index.php?page=cart'));
}
