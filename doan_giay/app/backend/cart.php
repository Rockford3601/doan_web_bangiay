<?php
// Hành động giỏ hàng và voucher. Giỏ hàng lưu trong CSDL, gắn với từng VARIANT (size + màu).
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

function action_cart_add(): void
{
    require_login();
    $uid       = (int)$_SESSION['user']['user_id'];
    $variantId = post_int('variant_id');
    $qty       = post_int('quantity');
    $return    = safe_return_url(post_str('return'), 'index.php?page=cart');

    if ($variantId <= 0 || $qty < 1 || $qty > MAX_QTY_PER_ITEM) {
        flash_set('error', 'Vui lòng chọn size/màu và số lượng hợp lệ (1-' . MAX_QTY_PER_ITEM . ').');
        redirect($return);
    }

    $s = db()->prepare(
        "SELECT v.stock_quantity, p.name FROM product_variant v JOIN product p ON p.product_id = v.product_id
          WHERE v.variant_id = ? AND v.status = 'active' AND p.status = 'active'"
    );
    $s->execute([$variantId]);
    $v = $s->fetch();
    if (!$v) {
        flash_set('error', 'Phiên bản sản phẩm này không còn bán.');
        redirect($return);
    }

    $cartId = get_or_create_cart_id($uid);
    $s = db()->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_item WHERE cart_id = ? AND variant_id = ?');
    $s->execute([$cartId, $variantId]);
    $inCart = (int)$s->fetchColumn();

    $newQty = min($inCart + $qty, MAX_QTY_PER_ITEM);
    if ($newQty > (int)$v['stock_quantity']) {
        flash_set('error', 'Chỉ còn ' . (int)$v['stock_quantity'] . ' sản phẩm trong kho (bạn đã có ' . $inCart . ' trong giỏ).');
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
    flash_set('success', 'Đã thêm "' . $v['name'] . '" vào giỏ hàng.');
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
        flash_set('error', 'Không tìm thấy sản phẩm trong giỏ.');
        redirect('index.php?page=cart');
    }

    if ($qty <= 0) {
        db()->prepare('DELETE FROM cart_item WHERE cart_item_id = ?')->execute([$itemId]);
        flash_set('info', 'Đã xóa sản phẩm khỏi giỏ.');
    } elseif ($qty > MAX_QTY_PER_ITEM || $qty > (int)$row['stock_quantity']) {
        flash_set('error', 'Số lượng tối đa có thể mua là ' . min(MAX_QTY_PER_ITEM, (int)$row['stock_quantity']) . '.');
    } else {
        db()->prepare('UPDATE cart_item SET quantity = ? WHERE cart_item_id = ?')->execute([$qty, $itemId]);
    }
    redirect('index.php?page=cart');
}

function action_cart_remove(): void
{
    require_login();
    $s = db()->prepare(
        'DELETE ci FROM cart_item ci JOIN cart ca ON ca.cart_id = ci.cart_id
          WHERE ci.cart_item_id = ? AND ca.user_id = ?'
    );
    $s->execute([post_int('cart_item_id'), (int)$_SESSION['user']['user_id']]);
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
        flash_set('error', 'Vui lòng nhập mã giảm giá.');
        redirect($back);
    }
    $lines    = get_cart_lines(get_or_create_cart_id($uid));
    $subtotal = cart_subtotal($lines);
    [$ok, $msg] = voucher_check(find_voucher($code), $subtotal, $uid);

    if ($ok) {
        $_SESSION['voucher_code'] = $code; // chỉ lưu MÃ; số tiền giảm luôn được tính lại ở server
        flash_set('success', 'Đã áp dụng mã ' . $code . '.');
    } else {
        unset($_SESSION['voucher_code']);
        flash_set('error', $msg);
    }
    redirect($back);
}

function action_remove_voucher(): void
{
    require_login();
    unset($_SESSION['voucher_code']);
    flash_set('info', 'Đã bỏ mã giảm giá.');
    redirect(safe_return_url(post_str('return'), 'index.php?page=cart'));
}
