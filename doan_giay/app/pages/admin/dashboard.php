<?php
if (!defined('SECURE_CHECK')) exit('Access denied');
$pdo = db();

$byStatus = $pdo->query('SELECT order_status, COUNT(*) AS n FROM orders GROUP BY order_status')->fetchAll(PDO::FETCH_KEY_PAIR);
$revenue  = (float)$pdo->query("SELECT COALESCE(SUM(final_amount),0) FROM orders WHERE order_status = 'delivered'")->fetchColumn();
$month    = (float)$pdo->query("SELECT COALESCE(SUM(final_amount),0) FROM orders WHERE order_status = 'delivered' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$retPending = (int)$pdo->query("SELECT COUNT(*) FROM return_request WHERE status = 'requested'")->fetchColumn();
$stale = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending' AND created_at < (NOW() - INTERVAL " . (int)STALE_PENDING_HOURS . " HOUR)")->fetchColumn();
$refundWaiting = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'refund_pending'")->fetchColumn();
$users    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$products = (int)$pdo->query("SELECT COUNT(*) FROM product WHERE status = 'active'")->fetchColumn();

$low = $pdo->query(
    "SELECT p.product_id, p.name, v.size, v.color, v.sku, v.stock_quantity
       FROM product_variant v JOIN product p ON p.product_id = v.product_id
      WHERE v.status = 'active' AND p.status = 'active' AND v.stock_quantity <= 5
      ORDER BY v.stock_quantity, p.name LIMIT 10"
)->fetchAll();

$latest = $pdo->query(
    'SELECT o.order_id, o.recipient_name, o.final_amount, o.order_status, o.created_at FROM orders o ORDER BY o.order_id DESC LIMIT 5'
)->fetchAll();
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>

  <div class="stat-grid">
    <div class="stat"><span>Doanh thu (đơn đã giao)</span><strong><?php echo e(format_vnd($revenue)); ?></strong></div>
    <div class="stat"><span>Doanh thu tháng này</span><strong><?php echo e(format_vnd($month)); ?></strong></div>
    <div class="stat"><span>Đơn chờ xác nhận</span><strong><?php echo (int)($byStatus['pending'] ?? 0); ?></strong></div>
    <div class="stat"><span>Đang giao</span><strong><?php echo (int)($byStatus['shipping'] ?? 0); ?></strong></div>
    <a class="stat <?php echo $stale ? 'stat-warn' : ''; ?>" href="index.php?page=admin_orders&status=pending"><span>Đơn chờ xác nhận quá <?php echo (int)STALE_PENDING_HOURS; ?> giờ</span><strong><?php echo $stale; ?></strong></a>
    <a class="stat <?php echo $refundWaiting ? 'stat-warn' : ''; ?>" href="index.php?page=admin_orders&pay=refund_pending"><span>Đơn chờ hoàn tiền</span><strong><?php echo $refundWaiting; ?></strong></a>
    <div class="stat"><span>Đổi/trả chờ duyệt</span><strong><?php echo $retPending; ?></strong></div>
    <div class="stat"><span>Khách hàng</span><strong><?php echo $users; ?></strong></div>
    <div class="stat"><span>Sản phẩm đang bán</span><strong><?php echo $products; ?></strong></div>
  </div>

  <div class="two-col">
    <section class="form-card">
      <h2>Đơn hàng mới nhất</h2>
      <table class="table">
        <thead><tr><th>Mã</th><th>Người nhận</th><th>Tổng</th><th>Trạng thái</th></tr></thead>
        <tbody>
        <?php foreach ($latest as $o): ?>
          <tr>
            <td><a href="index.php?page=admin_orders&status=<?php echo e($o['order_status']); ?>">#<?php echo (int)$o['order_id']; ?></a></td>
            <td><?php echo e($o['recipient_name'] ?: '—'); ?></td>
            <td><?php echo e(format_vnd($o['final_amount'])); ?></td>
            <td><span class="badge badge-<?php echo e($o['order_status']); ?>"><?php echo e(ORDER_STATUS[$o['order_status']] ?? $o['order_status']); ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <section class="form-card">
      <h2>Sắp hết hàng </h2>
      <?php if (!$low): ?><p class="muted">Không có biến thể nào sắp hết hàng.</p><?php endif; ?>
      <table class="table">
        <tbody>
        <?php foreach ($low as $r): ?>
          <tr>
            <td><a href="index.php?page=admin_product_form&id=<?php echo (int)$r['product_id']; ?>"><?php echo e($r['name']); ?></a><br>
                <small class="muted"><?php echo e($r['sku']); ?> · size <?php echo e($r['size']); ?> · <?php echo e($r['color']); ?></small></td>
            <td><span class="badge <?php echo (int)$r['stock_quantity'] === 0 ? 'badge-cancelled' : 'badge-pending'; ?>">Còn <?php echo (int)$r['stock_quantity']; ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </div>
</main>
