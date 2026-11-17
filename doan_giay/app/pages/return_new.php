<?php
if (!defined('SECURE_CHECK')) exit('Access denied');
require_once __DIR__ . '/../backend/returns.php';

$uid = (int)$_SESSION['user']['user_id'];
$orderId = get_int('order_id');
$s = db()->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ? AND order_status = 'delivered'");
$s->execute([$orderId, $uid]);
$ok = (bool)$s->fetch();
$days = $ok ? days_since_delivered($orderId) : null;
$allowed = ($ok && $days !== null && $days <= RETURN_DAYS);

$details = [];
if ($allowed) {
    $d = db()->prepare(
        "SELECT d.order_detail_id, d.product_name, d.size, d.color, d.quantity,
                d.quantity - COALESCE((SELECT SUM(r.quantity) FROM return_request r WHERE r.order_detail_id = d.order_detail_id AND r.status <> 'rejected'), 0) AS remaining
           FROM order_detail d WHERE d.order_id = ?"
    );
    $d->execute([$orderId]);
    $details = $d->fetchAll();
}
?>
<main class="page-wrap wide">
  <div class="breadcrumbs"><a href="index.php?page=orders">Đơn hàng của tôi</a> / Đổi/Trả hàng đơn #<?php echo (int)$orderId; ?></div>
  <h1>Yêu cầu đổi / trả hàng</h1>

  <?php if (!$allowed): ?>
    <div class="empty-state">
      <p><?php echo $ok ? 'Đã quá ' . (int)RETURN_DAYS . ' ngày kể từ khi nhận hàng nên không thể đổi/trả.' : 'Chỉ đơn hàng đã giao của bạn mới được đổi/trả.'; ?></p>
      <a class="btn btn-dark" href="index.php?page=orders">Quay lại</a>
    </div>
  <?php else: ?>
    <form method="post" action="index.php" class="form-card form-stack">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="return_request">
      <input type="hidden" name="order_id" value="<?php echo (int)$orderId; ?>">

      <table class="table">
        <thead><tr><th>Sản phẩm</th><th>Size / Màu</th><th>Đã mua</th><th>Còn có thể đổi/trả</th><th>Số lượng yêu cầu</th></tr></thead>
        <tbody>
        <?php foreach ($details as $r): ?>
          <tr>
            <td><?php echo e($r['product_name']); ?></td>
            <td><?php echo e($r['size']); ?> / <?php echo e($r['color']); ?></td>
            <td><?php echo (int)$r['quantity']; ?></td>
            <td><?php echo max(0, (int)$r['remaining']); ?></td>
            <td><input type="number" class="w-sm" name="qty[<?php echo (int)$r['order_detail_id']; ?>]" min="0" max="<?php echo max(0, (int)$r['remaining']); ?>" value="0" <?php echo (int)$r['remaining'] <= 0 ? 'disabled' : ''; ?>></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div>
        <?php foreach (RETURN_TYPES as $k => $label): ?>
          <label class="radio-row"><input type="radio" name="request_type" value="<?php echo e($k); ?>" <?php echo $k === 'return' ? 'checked' : ''; ?>> <?php echo e($label); ?></label>
        <?php endforeach; ?>
      </div>
      <label>Lý do (5-500 ký tự)
        <textarea name="reason" required minlength="5" maxlength="500" rows="3"></textarea>
      </label>
      <button type="submit" class="btn btn-dark">Gửi yêu cầu</button>
    </form>
  <?php endif; ?>
</main>
