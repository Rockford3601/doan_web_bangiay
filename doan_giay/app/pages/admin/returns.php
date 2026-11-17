<?php
if (!defined('SECURE_CHECK')) exit('Access denied');
require_once __DIR__ . '/../../backend/returns.php'; // RETURN_FLOW

$filter = get_str('status');
if (!array_key_exists($filter, RETURN_STATUS)) $filter = '';
$sql = 'SELECT r.*, d.product_name, d.size, d.color, d.unit_price, u.username
          FROM return_request r JOIN order_detail d ON d.order_detail_id = r.order_detail_id JOIN users u ON u.user_id = r.user_id';
if ($filter !== '') {
    $s = db()->prepare($sql . ' WHERE r.status = ? ORDER BY r.return_id DESC LIMIT 100');
    $s->execute([$filter]);
} else {
    $s = db()->query($sql . ' ORDER BY r.return_id DESC LIMIT 100');
}
$rows = $s->fetchAll();
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>

  <div class="filter-tabs">
    <a href="index.php?page=admin_returns" class="<?php echo $filter === '' ? 'active' : ''; ?>">Tất cả</a>
    <?php foreach (RETURN_STATUS as $k => $label): ?>
      <a href="index.php?page=admin_returns&status=<?php echo e($k); ?>" class="<?php echo $filter === $k ? 'active' : ''; ?>"><?php echo e($label); ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$rows): ?><p class="muted">Không có yêu cầu nào.</p><?php endif; ?>

  <?php foreach ($rows as $r): $next = RETURN_FLOW[$r['status']] ?? []; ?>
  <section class="order-card">
    <header class="order-head">
      <div><strong>Yêu cầu #<?php echo (int)$r['return_id']; ?></strong>
        <span class="muted"> · đơn #<?php echo (int)$r['order_id']; ?> · <?php echo e($r['username']); ?> · <?php echo e(date('d/m/Y H:i', strtotime($r['created_at']))); ?></span></div>
      <div><span class="badge badge-confirmed"><?php echo e(RETURN_STATUS[$r['status']] ?? $r['status']); ?></span></div>
    </header>
    <div class="order-foot">
      <div>
        <strong><?php echo e($r['product_name']); ?></strong> — size <?php echo e($r['size']); ?>, <?php echo e($r['color']); ?> × <?php echo (int)$r['quantity']; ?>
        (<?php echo e(RETURN_TYPES[$r['request_type']] ?? $r['request_type']); ?>)<br>
        <span class="muted">Lý do: <?php echo e($r['reason']); ?></span><br>
        <?php if ((float)$r['refund_amount'] > 0): ?><span>Đã hoàn: <strong><?php echo e(format_vnd($r['refund_amount'])); ?></strong></span><br><?php endif; ?>
        <?php if (!empty($r['admin_note'])): ?><span class="muted">Ghi chú: <?php echo e($r['admin_note']); ?></span><?php endif; ?>
      </div>
      <?php if ($next): ?>
      <form method="post" action="index.php" class="inline-form order-total" style="align-items:stretch">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="admin_return_update">
        <input type="hidden" name="return_id" value="<?php echo (int)$r['return_id']; ?>">
        <input type="text" name="admin_note" maxlength="500" placeholder="Ghi chú cho khách (không bắt buộc)">
        <div class="btn-row">
        <?php foreach ($next as $st):
            if ($st === 'refunded' && $r['request_type'] !== 'return') continue;
            if ($st === 'completed' && $r['request_type'] !== 'exchange') continue; ?>
          <button type="submit" name="new_status" value="<?php echo e($st); ?>" class="btn <?php echo $st === 'rejected' ? 'btn-light' : 'btn-dark'; ?> btn-sm">→ <?php echo e(RETURN_STATUS[$st]); ?></button>
        <?php endforeach; ?>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </section>
  <?php endforeach; ?>
</main>
