<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$pdo = db();
$uid = (int)$_SESSION['user']['user_id'];

$lines    = get_cart_lines(get_or_create_cart_id($uid));
$subtotal = cart_subtotal($lines);

// Voucher đang áp dụng
$voucher = null;
$discount = 0.0;
$code = (string)($_SESSION['voucher_code'] ?? '');
if ($code !== '') {
    $v = find_voucher($code);
    [$ok, $msg] = voucher_check($v, $subtotal, $uid);
    if ($ok) {
        $voucher = $v;
        $discount = voucher_discount($v, $subtotal);
    } else {
        unset($_SESSION['voucher_code']);
    }
}
$totals = calc_totals($subtotal, $discount);

$has_problem = false;
foreach ($lines as $l) {
    if (!$l['available']) $has_problem = true;
}

// Điền sẵn form: ưu tiên dữ liệu vừa nhập (khi có lỗi), sau đó địa chỉ mặc định, cuối cùng là thông tin tài khoản
$form = pull_old('checkout_old');
$has_default_address = false;
if (!$form) {
    $s = $pdo->prepare('SELECT recipient_name, phone, address FROM user_address WHERE user_id = ? ORDER BY is_default DESC, address_id DESC LIMIT 1');
    $s->execute([$uid]);
    $addr = $s->fetch();
    if ($addr && !empty($addr['address'])) {
        $form = ['recipient_name' => $addr['recipient_name'], 'phone' => $addr['phone'], 'shipping_address' => $addr['address']];
        $has_default_address = true;
    } else {
        $s = $pdo->prepare('SELECT name, phone, address FROM users WHERE user_id = ?');
        $s->execute([$uid]);
        $u = $s->fetch();
        $form = ['recipient_name' => $u['name'] ?? '', 'phone' => $u['phone'] ?? '', 'shipping_address' => $u['address'] ?? ''];
        if (!empty($u['address'])) {
            $has_default_address = true;
        }
    }
}
$form += ['note' => '', 'payment_method' => 'COD'];
?>
<main class="page-wrap wide">
  <div class="breadcrumbs"><a href="index.php?page=cart">Giỏ hàng</a> / Thanh toán</div>
  <h1>Thanh toán</h1>

  <?php if (!$lines): ?>
    <div class="empty-state">
      <p>Giỏ hàng đang trống.</p>
      <a href="index.php?page=catalog" class="btn btn-dark">Tiếp tục mua sắm</a>
    </div>
  <?php else: ?>
  <div class="checkout-layout">
    <form method="post" action="index.php" class="form-card form-stack" id="checkoutForm" onsubmit="handleAjaxCheckout(event)">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="place_order">

      <div id="checkoutErrorBox" style="display:none;margin-bottom:14px;padding:12px;border-radius:8px;font-size:14px;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;"></div>

      <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Thông tin giao hàng</h2>
        <?php if ($has_default_address): ?>
          <span style="font-size:12px; color:#2e7d32; font-weight:600; display:inline-flex; align-items:center; gap:4px; background:#e8f5e9; padding:3px 8px; border-radius:4px;">
            <i class="fa-solid fa-check"></i> Nhận tự động từ tài khoản
          </span>
        <?php endif; ?>
      </div>
      <label>Họ tên người nhận
        <input type="text" name="recipient_name" required maxlength="100" value="<?php echo e($form['recipient_name']); ?>">
      </label>
      <label>Số điện thoại
        <input type="tel" name="phone" required maxlength="11" pattern="0[0-9]{9,10}" value="<?php echo e($form['phone']); ?>">
      </label>
      <label>Địa chỉ giao hàng (số nhà, đường, phường/xã, quận/huyện, tỉnh/thành)
        <textarea name="shipping_address" required minlength="10" maxlength="500" rows="3"><?php echo e($form['shipping_address']); ?></textarea>
      </label>
      <label>Ghi chú cho shop (không bắt buộc)
        <textarea name="note" maxlength="500" rows="2"><?php echo e($form['note']); ?></textarea>
      </label>

      <h2>Phương thức thanh toán</h2>
      <?php foreach (PAYMENT_METHODS as $key => $label): ?>
        <label class="radio-row">
          <input type="radio" name="payment_method" value="<?php echo e($key); ?>" <?php echo ($form['payment_method'] === $key) ? 'checked' : ''; ?> required>
          <?php echo e($label); ?>
        </label>
      <?php endforeach; ?>

      <?php if ($has_problem): ?>
        <div class="flash flash-error">Giỏ hàng có sản phẩm không còn đủ hàng. <a href="index.php?page=cart">Quay lại giỏ hàng</a> để chỉnh sửa.</div>
      <?php else: ?>
        <button type="submit" class="btn btn-dark btn-block" id="placeOrderBtn">Đặt hàng · <span id="btnFinalTotal"><?php echo e(format_vnd($totals['final'])); ?></span></button>
      <?php endif; ?>
    </form>

    <aside class="form-card">
      <h2>Đơn hàng của bạn</h2>
      <?php foreach ($lines as $l): ?>
        <div class="mini-line">
          <img src="<?php echo e(img_url($l['thumbnail'])); ?>" alt="" onerror="this.onerror=null; this.src='public/assets/imgs/placeholder.svg';">
          <div>
            <strong><?php echo e($l['name']); ?></strong><br>
            <small class="muted">Size <?php echo e($l['size']); ?> · <?php echo e($l['color']); ?> · SL <?php echo (int)$l['quantity']; ?></small>
          </div>
          <span><?php echo e(format_vnd($l['line_total'])); ?></span>
        </div>
      <?php endforeach; ?>
      <div class="summary-row"><span>Tạm tính</span><strong id="ckSubtotal"><?php echo e(format_vnd($totals['subtotal'])); ?></strong></div>
      <div class="summary-row"><span>Phí vận chuyển</span><strong id="ckShipping"><?php echo $totals['shipping'] > 0 ? e(format_vnd($totals['shipping'])) : 'Miễn phí'; ?></strong></div>
      <div class="summary-row"><span>Giảm giá</span><strong id="ckDiscount">−<?php echo e(format_vnd($totals['discount'])); ?></strong></div>
      <div class="summary-row total-row"><span>Tổng cộng</span><strong id="ckFinal"><?php echo e(format_vnd($totals['final'])); ?></strong></div>
    </aside>
  </div>

  <script>
  // Xử lý Đặt hàng qua AJAX không tải lại trang
  function handleAjaxCheckout(event) {
    event.preventDefault(); // Chặn hành vi submit mặc định
    var form = event.target;
    var btn = document.getElementById('placeOrderBtn');
    var errBox = document.getElementById('checkoutErrorBox');

    btn.disabled = true;
    var originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý đơn hàng...';
    errBox.style.display = 'none';

    var fd = new FormData(form);

    ajaxPost('index.php', fd)
      .then(function(res) {
        if (res.success) {
          showToast(res.message, 'success');
          // Chuyển hướng tới trang thành công / thanh toán
          setTimeout(function() {
            window.location.href = res.redirect || ('index.php?page=order_success&id=' + res.order_id);
          }, 600);
        } else {
          btn.disabled = false;
          btn.innerHTML = originalText;
          errBox.style.display = 'block';
          errBox.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + res.message;
          showToast(res.message, 'error');
          // Cuộn mượt tới vị trí thông báo lỗi
          errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      })
      .catch(function() {
        btn.disabled = false;
        btn.innerHTML = originalText;
        errBox.style.display = 'block';
        errBox.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Lỗi kết nối máy chủ. Vui lòng thử lại.';
        showToast('Lỗi kết nối máy chủ', 'error');
      });
  }
  </script>
  <?php endif; ?>
</main>
