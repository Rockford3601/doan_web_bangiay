<?php
if (!defined('SECURE_CHECK')) exit('Access denied');
$s = db()->prepare(
    'SELECT r.*, d.product_name, d.size, d.color FROM return_request r JOIN order_detail d ON d.order_detail_id = r.order_detail_id
      WHERE r.user_id = ? ORDER BY r.return_id DESC LIMIT 100'
);
$s->execute([(int)$_SESSION['user']['user_id']]);
$rows = $s->fetchAll();
?>
<main class="page-wrap wide">
  <h1>Yêu cầu đổi / trả của tôi</h1>
  <?php if (!$rows): ?>
    <div class="empty-state"><p>Bạn chưa có yêu cầu đổi/trả nào.</p><a class="btn btn-dark" href="index.php?page=orders">Đơn hàng của tôi</a></div>
  <?php else: ?>
  <div class="table-scroll">
  <table class="table">
    <thead><tr><th>Mã</th><th>Đơn</th><th>Sản phẩm</th><th>SL</th><th>Hình thức</th><th>Lý do</th><th>Trạng thái</th><th>Hoàn tiền</th><th>Phản hồi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>#<?php echo (int)$r['return_id']; ?></td>
        <td>#<?php echo (int)$r['order_id']; ?></td>
        <td><?php echo e($r['product_name']); ?><br><small class="muted"><?php echo e($r['size']); ?> / <?php echo e($r['color']); ?></small></td>
        <td><?php echo (int)$r['quantity']; ?></td>
        <td><?php echo e(RETURN_TYPES[$r['request_type']] ?? $r['request_type']); ?></td>
        <td><?php echo e($r['reason']); ?></td>
        <td><span class="badge badge-<?php echo e($r['status'] === 'rejected' ? 'cancelled' : ($r['status'] === 'requested' ? 'pending' : 'confirmed')); ?>"><?php echo e(RETURN_STATUS[$r['status']] ?? $r['status']); ?></span></td>
        <td><?php echo (float)$r['refund_amount'] > 0 ? e(format_vnd($r['refund_amount'])) : '—'; ?></td>
        <td><?php echo e($r['admin_note'] ?? ''); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</main>
