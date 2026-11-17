<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$uid = (int)$_SESSION['user']['user_id'];
$s = db()->prepare('SELECT * FROM orders WHERE order_id = ? AND user_id = ?'); // chỉ xem được đơn của chính mình
$s->execute([get_int('id'), $uid]);
$order = $s->fetch();
?>
<main class="page-wrap">
  <?php if (!$order): ?>
    <div class="empty-state"><p>Không tìm thấy đơn hàng.</p><a class="btn btn-dark" href="index.php?page=orders">Xem đơn hàng của tôi</a></div>
  <?php else: ?>
    <div class="auth-card success-card">
      <h1>✓ Đặt hàng thành công!</h1>
      <p>Mã đơn hàng của bạn: <strong>#<?php echo (int)$order['order_id']; ?></strong></p>
      <p>Tổng thanh toán: <strong><?php echo e(format_vnd($order['final_amount'])); ?></strong> (<?php echo e($order['payment_method']); ?>)</p>
      <?php if ($order['payment_method'] === 'Chuyển khoản'): ?>
        <div class="flash flash-info">
          Vui lòng chuyển khoản <strong><?php echo e(format_vnd($order['final_amount'])); ?></strong> với nội dung <strong>DH<?php echo (int)$order['order_id']; ?></strong>.<br>
          <?php echo e(BANK_INFO); ?>
        </div>
      <?php endif; ?>
      <p>Giao đến: <?php echo e($order['recipient_name']); ?> · <?php echo e($order['phone']); ?><br><?php echo e($order['shipping_address']); ?></p>
      <p class="btn-row">
        <a class="btn btn-dark" href="index.php?page=orders">Xem đơn hàng của tôi</a>
        <a class="btn btn-light" href="index.php?page=catalog">Tiếp tục mua sắm</a>
      </p>

      <?php if (in_array($order['order_status'], ['pending', 'confirmed'], true)): ?>
        <div style="margin-top:16px;padding-top:16px;border-top:1px dashed #cbd5e1;text-align:center;">
          <small class="muted" style="display:block;margin-bottom:8px;">Lỡ chọn nhầm size, màu hoặc mẫu sản phẩm?</small>
          <form method="post" action="index.php" onsubmit="return confirm('Bạn có chắc muốn hủy đơn hàng này để đặt lại không? Kho và mã giảm giá sẽ được hoàn lại ngay lập tức.');" class="inline-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="cancel_order">
            <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
            <input type="hidden" name="cancel_reason" value="Khách đặt nhầm size / màu sắc khi mua">
            <button type="submit" class="btn btn-sm btn-light" style="color:#c62828;border-color:#fca5a5;">
              ✕ Hủy đơn hàng này để đặt lại
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>
