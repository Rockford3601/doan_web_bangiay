<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$uid   = (int)$_SESSION['user']['user_id'];
$lines = get_cart_lines(get_or_create_cart_id($uid));
$subtotal = cart_subtotal($lines);

// Voucher: session chỉ lưu MÃ, số tiền giảm luôn được tính lại tại đây
$voucher = null;
$discount = 0.0;
$voucher_error = '';
$code = (string)($_SESSION['voucher_code'] ?? '');
if ($code !== '') {
    $v = find_voucher($code);
    [$ok, $msg] = voucher_check($v, $subtotal, $uid);
    if ($ok) {
        $voucher  = $v;
        $discount = voucher_discount($v, $subtotal);
    } else {
        unset($_SESSION['voucher_code']);
        $voucher_error = $msg;
    }
}
$totals = calc_totals($subtotal, $discount);

$item_count = 0;
$has_problem = false;
foreach ($lines as $l) {
    $item_count += $l['quantity'];
    if (!$l['available']) $has_problem = true;
}
?>
<main class="cart-main">
  <div class="container cart-layout">
    <div class="breadcrumbs">Trang chủ / Giỏ hàng</div>
    <div class="page-head">
      <h1>Giỏ hàng của bạn</h1>
      <span><?php echo count($lines); ?> sản phẩm</span>
    </div>

    <?php if ($voucher_error !== ''): ?>
      <div class="flash flash-error"><?php echo e($voucher_error); ?></div>
    <?php endif; ?>

    <?php if (!$lines): ?>
      <div class="empty-state">
        <p>Giỏ hàng đang trống.</p>
        <a href="index.php?page=catalog" class="btn btn-dark">Tiếp tục mua sắm</a>
      </div>
    <?php else: ?>
    <div class="cart-content">
      <div class="cart-items-panel">
        <div class="promo-banner">
          <span class="promo-icon" aria-hidden="true">✓</span>
          <?php if ($subtotal >= FREE_SHIP_FROM): ?>
            <span>Đơn hàng của bạn được miễn phí vận chuyển.</span>
          <?php else: ?>
            <span>Mua thêm <?php echo e(format_vnd(FREE_SHIP_FROM - $subtotal)); ?> để được miễn phí vận chuyển.</span>
          <?php endif; ?>
        </div>

        <?php foreach ($lines as $l): ?>
        <article class="cart-item">
          <img src="<?php echo e(img_url($l['thumbnail'])); ?>" alt="<?php echo e($l['name']); ?>"
               onerror="this.onerror=null; this.src='public/assets/imgs/placeholder.svg';" />
          <div class="item-info">
            <div class="item-top">
              <h2><a href="index.php?page=product&id=<?php echo (int)$l['product_id']; ?>"><?php echo e($l['name']); ?></a></h2>
              <strong>
                <?php echo e(format_vnd($l['line_total'])); ?>
              </strong>
            </div>
            <div class="meta">
              Đơn giá: <?php echo e(format_vnd($l['unit_price'])); ?>
              <?php if ($l['flash_item_id'] !== null): ?>
                <s class="muted"><?php echo e(format_vnd($l['regular_price'])); ?></s> <span class="badge badge-sale">⚡ Flash sale</span>
              <?php endif; ?>
            </div>
            <div class="meta"><?php echo e($l['color']); ?> · SKU <?php echo e($l['sku']); ?></div>
            <div class="size-row"><span>Size: EU <?php echo e($l['size']); ?></span></div>
            <?php if (!$l['available']): ?>
              <div class="flash flash-error">
                <?php echo ((int)$l['stock_quantity'] <= 0 || $l['v_status'] !== 'active' || $l['p_status'] !== 'active')
                    ? 'Sản phẩm này đã hết hàng hoặc ngừng bán. Hãy xóa khỏi giỏ.'
                    : 'Chỉ còn ' . (int)$l['stock_quantity'] . ' sản phẩm. Hãy giảm số lượng.'; ?>
              </div>
            <?php endif; ?>
            <div class="controls-row">
              <div class="qty-box" aria-label="Số lượng">
                <form method="post" action="index.php">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="cart_update">
                  <input type="hidden" name="cart_item_id" value="<?php echo (int)$l['cart_item_id']; ?>">
                  <input type="hidden" name="quantity" value="<?php echo (int)$l['quantity'] - 1; ?>">
                  <button type="submit" aria-label="Giảm số lượng">−</button>
                </form>
                <span><?php echo (int)$l['quantity']; ?></span>
                <form method="post" action="index.php">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="cart_update">
                  <input type="hidden" name="cart_item_id" value="<?php echo (int)$l['cart_item_id']; ?>">
                  <input type="hidden" name="quantity" value="<?php echo (int)$l['quantity'] + 1; ?>">
                  <button type="submit" aria-label="Tăng số lượng">+</button>
                </form>
              </div>
              <div class="item-actions">
                <form method="post" action="index.php">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="cart_remove">
                  <input type="hidden" name="cart_item_id" value="<?php echo (int)$l['cart_item_id']; ?>">
                  <button type="submit" class="delete-btn" aria-label="Xóa sản phẩm"><span aria-hidden="true">✕</span></button>
                </form>
              </div>
            </div>
          </div>
        </article>
        <?php endforeach; ?>

        <div class="cart-footer">
          <a href="index.php?page=catalog" class="continue-link">← Tiếp tục mua sắm</a>
          <span><?php echo count($lines); ?> sản phẩm · <?php echo (int)$item_count; ?> đôi giày</span>
        </div>
      </div>

      <aside class="summary-panel" aria-label="Tóm tắt đơn hàng">
        <div class="summary-card">
          <h3>Tóm tắt đơn hàng</h3>

          <?php if ($voucher): ?>
            <form method="post" action="index.php" class="coupon-box">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="remove_voucher">
              <span>Mã <strong><?php echo e($voucher['code']); ?></strong> đã áp dụng</span>
              <button type="submit">Bỏ</button>
            </form>
          <?php else: ?>
            <form method="post" action="index.php" class="coupon-box">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="apply_voucher">
              <input type="text" name="voucher_code" maxlength="30" placeholder="Nhập mã ưu đãi" aria-label="Mã ưu đãi">
              <button type="submit">Áp dụng</button>
            </form>
          <?php endif; ?>

          <div class="summary-row">
            <span>Tạm tính (<?php echo (int)$item_count; ?> sản phẩm)</span>
            <strong><?php echo e(format_vnd($totals['subtotal'])); ?></strong>
          </div>
          <div class="summary-row">
            <span>Phí vận chuyển</span>
            <strong><?php echo $totals['shipping'] > 0 ? e(format_vnd($totals['shipping'])) : 'Miễn phí'; ?></strong>
          </div>
          <div class="summary-row">
            <span>Giảm giá</span>
            <strong>−<?php echo e(format_vnd($totals['discount'])); ?></strong>
          </div>
          <div class="divider"></div>
          <div class="summary-row total-row">
            <span>Tổng cộng</span>
            <strong><?php echo e(format_vnd($totals['final'])); ?></strong>
          </div>
          <div class="vat-text">Đã bao gồm VAT</div>

          <?php if ($has_problem): ?>
            <div class="flash flash-error">Vui lòng xử lý các sản phẩm báo lỗi trước khi thanh toán.</div>
          <?php else: ?>
            <a class="primary-btn" href="index.php?page=checkout">Thanh toán</a>
          <?php endif; ?>
          <div class="secure-row">
            <span class="secure-icon" aria-hidden="true"></span>
            <span>Thông tin của bạn được bảo mật</span>
          </div>
        </div>

        <div class="help-box">
          <h4>Cần hỗ trợ với đơn hàng?</h4>
          <p>Gọi 1900 6868 (9:00–18:00)<br />Hoặc nhắn tin để được tư vấn chọn size.</p>
        </div>
      </aside>
    </div>
    <?php endif; ?>
  </div>
</main>
