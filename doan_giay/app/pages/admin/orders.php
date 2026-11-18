<?php
if (!defined('SECURE_CHECK')) exit('Access denied');
require_once __DIR__ . '/../../backend/orders.php'; // ORDER_FLOW

$pdo    = db();
$online = [WALLET_METHOD, 'Chuyển khoản'];           // các phương thức thanh toán online

/* ---------- Bộ lọc ---------- */
$filter = get_str('status');
if (!array_key_exists($filter, ORDER_STATUS)) $filter = '';
$payFilter = get_str('pay');
if (!array_key_exists($payFilter, PAYMENT_STATUS)) $payFilter = '';
$q = mb_substr(get_str('q'), 0, 60);

/* ---------- Số liệu cho thẻ lọc ---------- */
$counts = $pdo->query('SELECT order_status, COUNT(*) FROM orders GROUP BY order_status')->fetchAll(PDO::FETCH_KEY_PAIR);

/* ---------- Truy vấn danh sách (tham số hóa) ---------- */
$where  = [];
$params = [];
if ($filter !== '')    { $where[] = 'o.order_status = ?';   $params[] = $filter; }
if ($payFilter !== '') { $where[] = 'o.payment_status = ?'; $params[] = $payFilter; }
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    if (preg_match('/^#?(\d{1,9})$/', $q, $m)) {
        $where[]  = '(o.order_id = ? OR o.phone LIKE ? OR o.recipient_name LIKE ? OR u.username LIKE ?)';
        array_push($params, (int)$m[1], $like, $like, $like);
    } else {
        $where[]  = '(o.phone LIKE ? OR o.recipient_name LIKE ? OR u.username LIKE ? OR o.tracking_code LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
}
$sql = 'SELECT o.*, u.username, TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS age_min
          FROM orders o JOIN users u ON u.user_id = o.user_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY (o.order_status = 'pending') DESC, o.order_id DESC LIMIT 100";   // đơn chờ xác nhận lên đầu
$s = $pdo->prepare($sql);
$s->execute($params);
$orders = $s->fetchAll();

$details = [];
$history = [];
if ($orders) {
    $ids = array_map('intval', array_column($orders, 'order_id'));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $d = $pdo->prepare("SELECT order_detail_id, order_id, product_name, sku, size, color, quantity FROM order_detail WHERE order_id IN ($in) ORDER BY order_detail_id");
    $d->execute($ids);
    foreach ($d->fetchAll() as $row) $details[$row['order_id']][] = $row;
    $h = $pdo->prepare("SELECT order_id, old_status, new_status, note, created_at FROM order_status_history WHERE order_id IN ($in) ORDER BY history_id");
    $h->execute($ids);
    foreach ($h->fetchAll() as $row) $history[$row['order_id']][] = $row;
}

// URL hiện tại (giữ nguyên bộ lọc) để quay lại sau khi thao tác
$selfParams = array_filter(['page' => 'admin_orders', 'status' => $filter, 'pay' => $payFilter, 'q' => $q], fn($v) => $v !== '');
$self = 'index.php?' . http_build_query($selfParams, '', '&', PHP_QUERY_RFC3986);

$actionLabel = ['confirmed' => 'Xác nhận đơn', 'shipping' => 'Bắt đầu giao', 'delivered' => 'Đã giao xong'];
$fmtMin = function (int $m): string { return $m >= 60 ? intdiv($m, 60) . ' giờ ' . ($m % 60) . ' phút' : $m . ' phút'; };
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>


  <!-- ===== Thẻ trạng thái + tìm kiếm ===== -->
  <div class="filter-tabs">
    <a href="index.php?page=admin_orders" class="<?php echo ($filter === '' && $payFilter === '' && $q === '') ? 'active' : ''; ?>">Tất cả (<?php echo array_sum($counts); ?>)</a>
    <?php foreach (ORDER_STATUS as $k => $label): ?>
      <a href="index.php?page=admin_orders&status=<?php echo e($k); ?>" class="<?php echo $filter === $k ? 'active' : ''; ?>"><?php echo e($label); ?> (<?php echo (int)($counts[$k] ?? 0); ?>)</a>
    <?php endforeach; ?>
  </div>

  <form method="get" action="index.php" class="filter-bar">
    <input type="hidden" name="page" value="admin_orders">
    <input type="hidden" name="status" value="<?php echo e($filter); ?>">
    <input type="search" name="q" maxlength="60" placeholder="Tìm mã đơn, tên, SĐT, tài khoản, mã vận đơn" value="<?php echo e($q); ?>">
    <select name="pay">
      <option value="">Mọi trạng thái thanh toán</option>
      <?php foreach (PAYMENT_STATUS as $k => $label): ?>
        <option value="<?php echo e($k); ?>" <?php echo $payFilter === $k ? 'selected' : ''; ?>><?php echo e($label); ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-dark btn-sm">Lọc</button>
    <a class="btn btn-light btn-sm" href="index.php?page=admin_orders">Xóa lọc</a>
  </form>

  <!-- ===== Thao tác hàng loạt (ô tích chọn nằm trong danh sách, liên kết bằng thuộc tính form="bulkForm") ===== -->
  <form method="post" action="index.php" id="bulkForm" class="bulk-bar">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="admin_orders_bulk">
    <input type="hidden" name="return" value="<?php echo e($self); ?>">
    <label class="radio-row"><input type="checkbox" id="checkAll"> Chọn tất cả</label>
    <span class="muted">Đơn đã chọn:</span>
    <button type="submit" name="new_status" value="confirmed" class="btn btn-dark btn-sm">Xác nhận</button>
    <button type="submit" name="new_status" value="shipping" class="btn btn-dark btn-sm">Bắt đầu giao</button>
    <button type="submit" name="new_status" value="delivered" class="btn btn-dark btn-sm">Đã giao xong</button>
    <input type="text" name="note" maxlength="200" placeholder="Lý do (bắt buộc khi hủy đơn)" class="w-lg">
    <button type="submit" name="new_status" value="cancelled" class="btn btn-danger btn-sm">Hủy đơn</button>
  </form>

  <?php if (!$orders): ?><p class="muted">Không có đơn hàng nào phù hợp.</p><?php endif; ?>

  <?php foreach ($orders as $o):
      $oid  = (int)$o['order_id'];
      $next = ORDER_FLOW[$o['order_status']] ?? [];
      $isOnline  = in_array($o['payment_method'], $online, true);
      $age  = (int)$o['age_min'];
      $isStale   = ($o['order_status'] === 'pending' && $age >= STALE_PENDING_HOURS * 60);
      $expireIn  = ($o['order_status'] === 'pending' && $o['payment_status'] === 'unpaid' && $isOnline && AUTO_CANCEL_UNPAID_HOURS > 0)
                   ? AUTO_CANCEL_UNPAID_HOURS * 60 - $age : null;
  ?>
  <section class="order-card <?php echo $isStale ? 'is-stale' : ''; ?>" id="adminOrderCard-<?php echo $oid; ?>">
    <header class="order-head">
      <div>
        <label class="check-inline"><input type="checkbox" class="bulk-check" name="ids[]" value="<?php echo $oid; ?>" form="bulkForm"></label>
        <strong>Đơn #<?php echo $oid; ?></strong>
        <span class="muted"> · <?php echo e(date('d/m/Y H:i', strtotime($o['created_at']))); ?> · tài khoản <?php echo e($o['username']); ?></span>
      </div>
      <div>
        <?php if ($isStale): ?><span class="badge badge-cancelled">Quá hạn xử lý <?php echo e($fmtMin($age)); ?></span><?php endif; ?>
        <?php if ($expireIn !== null): ?>
          <span class="badge badge-pending"><?php echo $expireIn > 0 ? 'Tự hủy sau ' . e($fmtMin($expireIn)) : 'Sắp bị hủy tự động'; ?></span>
        <?php endif; ?>
        <span class="badge badge-<?php echo e($o['order_status']); ?> order-status-badge"><?php echo e(ORDER_STATUS[$o['order_status']] ?? $o['order_status']); ?></span>
        <span class="badge badge-pay-<?php echo e($o['payment_status']); ?> order-pay-badge"><?php echo e(PAYMENT_STATUS[$o['payment_status']] ?? $o['payment_status']); ?></span>
      </div>
    </header>

    <div class="order-foot">
      <div>
        <?php foreach ($details[$oid] ?? [] as $row): ?>
          <div style="margin-bottom: 6px;">
            <strong><?php echo e($row['product_name']); ?></strong> — size <?php echo e($row['size']); ?>, <?php echo e($row['color']); ?> × <?php echo (int)$row['quantity']; ?>
            <span style="display:inline-block;margin-left:8px;font-size:12px;background:#f1f5f9;padding:2px 8px;border-radius:4px;border:1px solid #cbd5e1;font-family:monospace;">
              SKU: <strong><?php echo e($row['sku'] ?: '—'); ?></strong>
            </span>
          </div>
        <?php endforeach; ?>
        <div class="muted" style="margin-top:8px">
          <?php echo e($o['recipient_name'] ?: '—'); ?> · <?php echo e($o['phone']); ?> · <?php echo e($o['shipping_address'] ?? ''); ?>
          <?php if (!empty($o['note'])): ?><br>Ghi chú của khách: <?php echo e($o['note']); ?><?php endif; ?>
          <br>Thanh toán: <?php echo e($o['payment_method']); ?>
          <?php if (!empty($o['tracking_code'])): ?> · Mã vận đơn: <strong><?php echo e($o['tracking_code']); ?></strong><?php endif; ?>
        </div>
        <?php if (!empty($history[$oid])): ?>
        <details class="timeline">
          <summary>Lịch sử xử lý (<?php echo count($history[$oid]); ?>)</summary>
          <ul>
          <?php foreach ($history[$oid] as $h): ?>
            <li><span class="muted"><?php echo e(date('d/m H:i', strtotime($h['created_at']))); ?></span>
                <?php echo $h['old_status'] ? e(ORDER_STATUS[$h['old_status']] ?? $h['old_status']) . ' → ' : ''; ?><strong><?php echo e(ORDER_STATUS[$h['new_status']] ?? $h['new_status']); ?></strong>
                <?php echo !empty($h['note']) ? ' — ' . e($h['note']) : ''; ?></li>
          <?php endforeach; ?>
          </ul>
        </details>
        <?php endif; ?>
      </div>

      <div class="order-total">
        <strong><?php echo e(format_vnd($o['final_amount'])); ?></strong>
        <div class="order-actions">
          <?php foreach ($next as $st): if ($st === 'cancelled') continue; ?>
            <form method="post" action="index.php" class="act-form">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="admin_order_status">
              <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
              <input type="hidden" name="new_status" value="<?php echo e($st); ?>">
              <input type="hidden" name="return" value="<?php echo e($self); ?>">
              <?php if ($st === 'shipping'): ?>
                <input type="text" name="tracking_code" maxlength="50" placeholder="Mã vận đơn (nếu có)" class="w-md" pattern="[A-Za-z0-9._\-]{3,50}">
              <?php endif; ?>
              <button type="submit" class="btn btn-dark btn-sm"><?php echo e($actionLabel[$st] ?? ORDER_STATUS[$st]); ?></button>
            </form>
          <?php endforeach; ?>

          <?php if ($o['payment_status'] === 'unpaid' && $o['order_status'] !== 'cancelled'): ?>
            <form method="post" action="index.php" class="act-form">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="admin_order_paid">
              <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
              <input type="hidden" name="return" value="<?php echo e($self); ?>">
              <input type="text" name="transaction_code" maxlength="100" placeholder="Mã giao dịch" class="w-md">
              <button type="submit" class="btn btn-light btn-sm">Đã nhận tiền</button>
            </form>
          <?php endif; ?>

          <?php if ($o['payment_status'] === 'refund_pending'): ?>
            <form method="post" action="index.php" class="act-form">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="admin_order_refund">
              <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
              <input type="hidden" name="return" value="<?php echo e($self); ?>">
              <input type="text" name="refund_code" maxlength="100" placeholder="Mã giao dịch hoàn tiền" class="w-md">
              <button type="submit" class="btn btn-dark btn-sm">Đã hoàn tiền</button>
            </form>
          <?php endif; ?>

          <?php if (in_array('cancelled', $next, true)): ?>
            <form method="post" action="index.php" class="act-form" onsubmit="return confirm('Hủy đơn #<?php echo $oid; ?>? Kho và voucher sẽ được hoàn lại.');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="admin_order_status">
              <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
              <input type="hidden" name="new_status" value="cancelled">
              <input type="hidden" name="return" value="<?php echo e($self); ?>">
              <input type="text" name="note" required minlength="3" maxlength="200" placeholder="Lý do hủy đơn" class="w-md">
              <button type="submit" class="btn btn-danger btn-sm">Hủy đơn</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endforeach; ?>
</main>
<script>
  // Chọn tất cả + xác nhận khi hủy hàng loạt
  (function () {
    var all = document.getElementById('checkAll');
    if (all) all.addEventListener('change', function () {
      document.querySelectorAll('.bulk-check').forEach(function (c) { c.checked = all.checked; });
    });
    var bulk = document.getElementById('bulkForm');
    if (bulk) bulk.addEventListener('submit', function (e) {
      var n = document.querySelectorAll('.bulk-check:checked').length;
      var act = e.submitter ? e.submitter.value : '';
      if (n === 0) { alert('Bạn chưa chọn đơn hàng nào.'); e.preventDefault(); return; }
      if (act === 'cancelled' && !confirm('Hủy ' + n + ' đơn đã chọn? Kho và voucher sẽ được hoàn lại.')) e.preventDefault();
    });

    // AJAX: Cập nhật trạng thái từng đơn hàng không tải lại trang
    document.querySelectorAll('.act-form').forEach(function(form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = form.querySelector('button[type="submit"]');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        var fd = new FormData(form);

        ajaxPost('index.php', fd)
          .then(function(res) {
            btn.disabled = false;
            btn.textContent = originalText;
            if (res.success) {
              showToast(res.message, 'success');
              var card = document.getElementById('adminOrderCard-' + res.order_id);
              if (card) {
                if (res.new_status) {
                  var stBadge = card.querySelector('.order-status-badge');
                  if (stBadge) {
                    stBadge.className = 'badge badge-' + res.new_status + ' order-status-badge';
                    stBadge.textContent = res.status_label;
                  }
                }
                if (res.payment_status) {
                  var payBadge = card.querySelector('.order-pay-badge');
                  if (payBadge) {
                    payBadge.className = 'badge badge-pay-' + res.payment_status + ' order-pay-badge';
                    payBadge.textContent = res.payment_label;
                  }
                }
                // Làm mờ form sau khi thao tác xong
                form.style.opacity = '0.4';
                form.style.pointerEvents = 'none';
              }
            } else {
              showToast(res.message || 'Thao tác không thành công', 'error');
            }
          })
          .catch(function() {
            btn.disabled = false;
            btn.textContent = originalText;
            showToast('Lỗi kết nối máy chủ', 'error');
          });
      });
    });
  })();
</script>
