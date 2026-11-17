<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$uid = (int)$_SESSION['user']['user_id'];
$s = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY order_id DESC LIMIT 50');
$s->execute([$uid]);
$orders = $s->fetchAll();

// Lấy chi tiết của tất cả đơn bằng 1 truy vấn (id đều là số nguyên lấy từ CSDL)
$details = [];
if ($orders) {
    $ids = array_map('intval', array_column($orders, 'order_id'));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $d = db()->prepare("SELECT d.*, v.product_id FROM order_detail d LEFT JOIN product_variant v ON v.variant_id = d.variant_id WHERE d.order_id IN ($in) ORDER BY d.order_detail_id");
    $d->execute($ids);
    foreach ($d->fetchAll() as $row) {
        $details[$row['order_id']][] = $row;
    }
}
?>
<main class="page-wrap wide">
  <h1>Đơn hàng của tôi</h1>
  <p><a class="btn btn-light btn-sm" href="index.php?page=returns">Yêu cầu đổi/trả của tôi</a></p>
  <?php if (!$orders): ?>
    <div class="empty-state"><p>Bạn chưa có đơn hàng nào.</p><a class="btn btn-dark" href="index.php?page=catalog">Mua sắm ngay</a></div>
  <?php endif; ?>

  <?php foreach ($orders as $o): ?>
    <section class="order-card">
      <header class="order-head">
        <div>
          <strong>Đơn #<?php echo (int)$o['order_id']; ?></strong>
          <span class="muted"> · <?php echo e(date('d/m/Y H:i', strtotime($o['created_at']))); ?></span>
        </div>
        <div>
          <span class="badge badge-<?php echo e($o['order_status']); ?>"><?php echo e(ORDER_STATUS[$o['order_status']] ?? $o['order_status']); ?></span>
          <span class="badge badge-pay-<?php echo e($o['payment_status']); ?>"><?php echo e(PAYMENT_STATUS[$o['payment_status']] ?? $o['payment_status']); ?></span>
        </div>
      </header>

      <table class="table">
        <thead><tr><th>Sản phẩm</th><th>Size/Màu</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>
        <tbody>
        <?php foreach ($details[$o['order_id']] ?? [] as $row): ?>
          <tr>
            <td>
              <?php if (!empty($row['product_id'])): ?>
                <a href="index.php?page=product&id=<?php echo (int)$row['product_id']; ?>#pdReviews" style="color:inherit;font-weight:600;text-decoration:underline;">
                  <?php echo e($row['product_name']); ?>
                </a>
              <?php else: ?>
                <?php echo e($row['product_name']); ?>
              <?php endif; ?>
            </td>
            <td><?php echo e($row['size']); ?> / <?php echo e($row['color']); ?></td>
            <td><?php echo (int)$row['quantity']; ?></td>
            <td><?php echo e(format_vnd($row['unit_price'])); ?></td>
            <td><?php echo e(format_vnd($row['subtotal'])); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div class="order-foot">
        <div class="muted">
          <?php if (!empty($o['tracking_code'])): ?>Mã vận đơn: <strong><?php echo e($o['tracking_code']); ?></strong><br><?php endif; ?>
          <?php if ($o['order_status'] === 'pending' && $o['payment_status'] === 'unpaid' && in_array($o['payment_method'], [WALLET_METHOD, 'Chuyển khoản'], true) && AUTO_CANCEL_UNPAID_HOURS > 0): ?>
            <strong>Vui lòng thanh toán trước <?php echo e(date('H:i d/m/Y', strtotime($o['created_at']) + AUTO_CANCEL_UNPAID_HOURS * 3600)); ?></strong>, quá hạn đơn sẽ tự hủy.<br>
          <?php endif; ?>
          Giao đến: <?php echo e($o['recipient_name'] ?: '—'); ?> · <?php echo e($o['phone'] ?: ''); ?> · <?php echo e($o['shipping_address'] ?? ''); ?><br>
          Phí vận chuyển <?php echo e(format_vnd($o['shipping_fee'])); ?> · Giảm giá −<?php echo e(format_vnd($o['discount_amount'])); ?> · Thanh toán: <?php echo e($o['payment_method']); ?>
        </div>
        <div class="order-total">
          <strong><?php echo e(format_vnd($o['final_amount'])); ?></strong>
          <?php if ($o['payment_method'] === WALLET_METHOD && $o['payment_status'] === 'unpaid' && $o['order_status'] !== 'cancelled'): ?>
            <a class="btn btn-dark btn-sm" href="index.php?page=pay_sim&id=<?php echo (int)$o['order_id']; ?>">Thanh toán ví</a>
          <?php endif; ?>
          <?php if ($o['order_status'] === 'delivered'): ?>
            <a class="btn btn-light btn-sm" href="index.php?page=return_new&order_id=<?php echo (int)$o['order_id']; ?>">Đổi/Trả hàng</a>
          <?php endif; ?>
          <?php if (in_array($o['order_status'], ['pending', 'confirmed'], true)): ?>
            <button type="button" class="btn btn-light btn-sm" style="color:#c62828;border-color:#fca5a5;" onclick="openCancelModal(<?php echo (int)$o['order_id']; ?>)">
              Hủy đơn
            </button>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endforeach; ?>

  <!-- Hộp thoại xác nhận Hủy đơn hàng (dùng thẻ dialog chuẩn HTML5) -->
  <dialog id="cancelModal" style="border:none;border-radius:12px;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.25);max-width:440px;width:90%;">
    <form method="post" action="index.php" class="form-stack">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="cancel_order">
      <input type="hidden" name="order_id" id="cancelOrderIdInput" value="0">
      
      <h3 style="margin-top:0;color:#c62828;">Hủy đơn hàng <span id="cancelOrderCodeText"></span></h3>
      <p style="font-size:13px;color:#64748b;margin:0 0 12px 0;line-height:1.5;">
        Nếu bạn lỡ đặt nhầm mẫu, chọn sai size hoặc màu sắc, bạn có thể hủy đơn và đặt lại đơn mới.
      </p>

      <label style="font-size:13px;font-weight:600;display:block;">Vui lòng chọn lý do hủy:
        <select name="cancel_reason" required style="width:100%;height:38px;margin-top:6px;border-radius:6px;border:1px solid #cbd5e1;padding:0 8px;">
          <option value="Đặt nhầm kích cỡ (size) hoặc màu sắc">Đặt nhầm kích cỡ (size) hoặc màu sắc</option>
          <option value="Đặt nhầm mẫu sản phẩm">Đặt nhầm mẫu sản phẩm</option>
          <option value="Muốn đổi địa chỉ / số điện thoại nhận hàng">Muốn đổi địa chỉ / số điện thoại nhận hàng</option>
          <option value="Đổi ý, không còn nhu cầu mua nữa">Đổi ý, không còn nhu cầu mua nữa</option>
          <option value="Lý do khác">Lý do khác</option>
        </select>
      </label>

      <div class="btn-row" style="margin-top:18px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-dark" style="background:#c62828;border-color:#c62828;">Xác nhận hủy đơn</button>
        <button type="button" class="btn btn-light" onclick="document.getElementById('cancelModal').close()">Giữ lại đơn</button>
      </div>
    </form>
  </dialog>

  <script>
  function openCancelModal(orderId) {
    var modal = document.getElementById('cancelModal');
    var input = document.getElementById('cancelOrderIdInput');
    var text  = document.getElementById('cancelOrderCodeText');
    if (modal && input) {
      input.value = orderId;
      if (text) text.textContent = '#' + orderId;
      modal.showModal();
    }
  }
  </script>
</main>
