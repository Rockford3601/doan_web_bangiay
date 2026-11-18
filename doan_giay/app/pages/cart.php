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
  <div class="container cart-layout" id="cartContainer">
    <div class="breadcrumbs">Trang chủ / Giỏ hàng</div>
    <div class="page-head">
      <h1>Giỏ hàng của bạn</h1>
      <span id="pageHeadItemCount"><?php echo count($lines); ?> sản phẩm</span>
    </div>

    <?php if ($voucher_error !== ''): ?>
      <div class="flash flash-error" id="voucherErrorMsg"><?php echo e($voucher_error); ?></div>
    <?php endif; ?>

    <div id="cartEmptyState" class="empty-state" style="<?php echo $lines ? 'display:none;' : ''; ?>">
      <p>Giỏ hàng đang trống.</p>
      <a href="index.php?page=catalog" class="btn btn-dark">Tiếp tục mua sắm</a>
    </div>

    <?php if ($lines): ?>
    <div class="cart-content" id="cartContentWrapper">
      <div class="cart-items-panel">
        <div class="promo-banner" id="promoBanner">
          <span class="promo-icon" aria-hidden="true">✓</span>
          <span id="promoBannerText">
            <?php if ($subtotal >= FREE_SHIP_FROM): ?>
              Đơn hàng của bạn được miễn phí vận chuyển.
            <?php else: ?>
              Mua thêm <?php echo e(format_vnd(FREE_SHIP_FROM - $subtotal)); ?> để được miễn phí vận chuyển.
            <?php endif; ?>
          </span>
        </div>

        <div id="cartItemsList">
        <?php foreach ($lines as $l): ?>
        <article class="cart-item" id="cartItem-<?php echo (int)$l['cart_item_id']; ?>" data-id="<?php echo (int)$l['cart_item_id']; ?>">
          <img src="<?php echo e(img_url($l['thumbnail'])); ?>" alt="<?php echo e($l['name']); ?>"
               onerror="this.onerror=null; this.src='public/assets/imgs/placeholder.svg';" />
          <div class="item-info">
            <div class="item-top">
              <h2><a href="index.php?page=product&id=<?php echo (int)$l['product_id']; ?>"><?php echo e($l['name']); ?></a></h2>
              <strong class="item-line-total">
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
                <button type="button" class="btn-qty-minus" onclick="changeQty(<?php echo (int)$l['cart_item_id']; ?>, -1)" aria-label="Giảm số lượng">−</button>
                <span class="qty-display"><?php echo (int)$l['quantity']; ?></span>
                <button type="button" class="btn-qty-plus" onclick="changeQty(<?php echo (int)$l['cart_item_id']; ?>, 1)" aria-label="Tăng số lượng">+</button>
              </div>
              <div class="item-actions">
                <button type="button" class="delete-btn" onclick="removeCartItem(<?php echo (int)$l['cart_item_id']; ?>)" aria-label="Xóa sản phẩm">
                  <span aria-hidden="true">✕</span>
                </button>
              </div>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
        </div>

        <div class="cart-footer">
          <a href="index.php?page=catalog" class="continue-link">← Tiếp tục mua sắm</a>
          <span id="footerItemStats"><?php echo count($lines); ?> sản phẩm · <?php echo (int)$item_count; ?> đôi giày</span>
        </div>
      </div>

      <aside class="summary-panel" aria-label="Tóm tắt đơn hàng">
        <div class="summary-card">
          <h3>Tóm tắt đơn hàng</h3>

          <div id="voucherSection">
            <?php if ($voucher): ?>
              <form method="post" action="index.php" class="coupon-box" onsubmit="handleRemoveVoucher(event)">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="remove_voucher">
                <span>Mã <strong><?php echo e($voucher['code']); ?></strong> đã áp dụng</span>
                <button type="submit">Bỏ</button>
              </form>
            <?php else: ?>
              <form method="post" action="index.php" class="coupon-box" onsubmit="handleApplyVoucher(event)">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="apply_voucher">
                <input type="text" name="voucher_code" maxlength="30" placeholder="Nhập mã ưu đãi" aria-label="Mã ưu đãi" required>
                <button type="submit">Áp dụng</button>
              </form>
            <?php endif; ?>
          </div>

          <div class="summary-row">
            <span>Tạm tính (<span id="summaryItemCount"><?php echo (int)$item_count; ?></span> sản phẩm)</span>
            <strong id="summarySubtotal"><?php echo e(format_vnd($totals['subtotal'])); ?></strong>
          </div>
          <div class="summary-row">
            <span>Phí vận chuyển</span>
            <strong id="summaryShipping"><?php echo $totals['shipping'] > 0 ? e(format_vnd($totals['shipping'])) : 'Miễn phí'; ?></strong>
          </div>
          <div class="summary-row">
            <span>Giảm giá</span>
            <strong id="summaryDiscount">−<?php echo e(format_vnd($totals['discount'])); ?></strong>
          </div>
          <div class="divider"></div>
          <div class="summary-row total-row">
            <span>Tổng cộng</span>
            <strong id="summaryFinal"><?php echo e(format_vnd($totals['final'])); ?></strong>
          </div>
          <div class="vat-text">Đã bao gồm VAT</div>

          <?php if ($has_problem): ?>
            <div class="flash flash-error">Vui lòng xử lý các sản phẩm báo lỗi trước khi thanh toán.</div>
          <?php else: ?>
            <a class="primary-btn" id="btnCheckout" href="index.php?page=checkout">Thanh toán</a>
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

<script>
// Token CSRF bảo vệ yêu cầu AJAX
var csrfToken = '<?php echo csrf_token(); ?>';

// Cập nhật lại các chỉ số giao diện giỏ hàng từ dữ liệu JSON trả về
function updateCartUI(summary) {
  // Cập nhật số lượng trên icon giỏ hàng ở header
  document.querySelectorAll('.cart-count').forEach(function (el) {
    el.textContent = summary.cart_count;
  });

  // Nếu giỏ hàng trống, ẩn bảng sản phẩm và hiện thông báo trống
  if (summary.is_empty) {
    var wrapper = document.getElementById('cartContentWrapper');
    if (wrapper) wrapper.style.display = 'none';
    var emptyBox = document.getElementById('cartEmptyState');
    if (emptyBox) emptyBox.style.display = 'block';
    var headCount = document.getElementById('pageHeadItemCount');
    if (headCount) headCount.textContent = '0 sản phẩm';
    return;
  }

  // Cập nhật số liệu tóm tắt đơn hàng
  var subtotalEl = document.getElementById('summarySubtotal');
  if (subtotalEl) subtotalEl.textContent = summary.subtotal_fmt;

  var shippingEl = document.getElementById('summaryShipping');
  if (shippingEl) shippingEl.textContent = summary.shipping_fmt;

  var discountEl = document.getElementById('summaryDiscount');
  if (discountEl) discountEl.textContent = '−' + summary.discount_fmt;

  var finalEl = document.getElementById('summaryFinal');
  if (finalEl) finalEl.textContent = summary.final_fmt;

  var itemCountEl = document.getElementById('summaryItemCount');
  if (itemCountEl) itemCountEl.textContent = summary.item_count;

  var headCountEl = document.getElementById('pageHeadItemCount');
  if (headCountEl) headCountEl.textContent = summary.line_count + ' sản phẩm';

  var footerStatsEl = document.getElementById('footerItemStats');
  if (footerStatsEl) footerStatsEl.textContent = summary.line_count + ' sản phẩm · ' + summary.item_count + ' đôi giày';

  var bannerTextEl = document.getElementById('promoBannerText');
  if (bannerTextEl) bannerTextEl.textContent = summary.free_ship_text;
}

// 1. AJAX: Tăng / giảm số lượng sản phẩm (+ / -)
function changeQty(cartItemId, delta) {
  var itemEl = document.getElementById('cartItem-' + cartItemId);
  if (!itemEl) return;
  var qtyDisplay = itemEl.querySelector('.qty-display');
  var currentQty = parseInt(qtyDisplay.textContent, 10) || 1;
  var newQty = currentQty + delta;

  var fd = new FormData();
  fd.append('action', 'cart_update');
  fd.append('csrf_token', csrfToken);
  fd.append('cart_item_id', cartItemId);
  fd.append('quantity', newQty);

  ajaxPost('index.php', fd)
    .then(function (res) {
      if (res.success) {
        if (newQty <= 0) {
          // Xóa dòng khỏi giao diện
          itemEl.remove();
        } else {
          // Cập nhật số lượng và thành tiền của dòng
          qtyDisplay.textContent = res.quantity;
          var lineTotalEl = itemEl.querySelector('.item-line-total');
          if (lineTotalEl) lineTotalEl.textContent = res.line_total_fmt;
        }
        updateCartUI(res.summary);
      } else {
        showToast(res.message, 'error');
      }
    })
    .catch(function () {
      showToast('Lỗi kết nối khi cập nhật số lượng', 'error');
    });
}

// 2. AJAX: Xóa sản phẩm khỏi giỏ hàng
function removeCartItem(cartItemId) {
  var itemEl = document.getElementById('cartItem-' + cartItemId);
  if (!itemEl) return;

  var fd = new FormData();
  fd.append('action', 'cart_remove');
  fd.append('csrf_token', csrfToken);
  fd.append('cart_item_id', cartItemId);

  ajaxPost('index.php', fd)
    .then(function (res) {
      if (res.success) {
        showToast(res.message, 'info');
        // Hiệu ứng mờ dần rồi xóa thẻ HTML
        itemEl.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
        itemEl.style.opacity = '0';
        itemEl.style.transform = 'translateX(-20px)';
        setTimeout(function () {
          itemEl.remove();
          updateCartUI(res.summary);
        }, 250);
      } else {
        showToast(res.message, 'error');
      }
    })
    .catch(function () {
      showToast('Lỗi kết nối khi xóa sản phẩm', 'error');
    });
}

// 3. AJAX: Áp dụng mã giảm giá (Voucher)
function handleApplyVoucher(event) {
  event.preventDefault();
  var form = event.target;
  var fd = new FormData(form);

  ajaxPost('index.php', fd)
    .then(function (res) {
      if (res.success) {
        showToast(res.message, 'success');
        updateCartUI(res.summary);
        // Đổi form nhập thành nút gỡ mã
        var vSec = document.getElementById('voucherSection');
        if (vSec && res.summary.voucher_code) {
          vSec.innerHTML = '<form method="post" action="index.php" class="coupon-box" onsubmit="handleRemoveVoucher(event)">' +
            '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
            '<input type="hidden" name="action" value="remove_voucher">' +
            '<span>Mã <strong>' + res.summary.voucher_code + '</strong> đã áp dụng</span>' +
            '<button type="submit">Bỏ</button></form>';
        }
      } else {
        showToast(res.message, 'error');
      }
    })
    .catch(function () {
      showToast('Lỗi kết nối khi áp dụng mã ưu đãi', 'error');
    });
}

// 4. AJAX: Bỏ mã giảm giá
function handleRemoveVoucher(event) {
  event.preventDefault();
  var form = event.target;
  var fd = new FormData(form);

  ajaxPost('index.php', fd)
    .then(function (res) {
      if (res.success) {
        showToast(res.message, 'info');
        updateCartUI(res.summary);
        // Trả lại form nhập voucher
        var vSec = document.getElementById('voucherSection');
        if (vSec) {
          vSec.innerHTML = '<form method="post" action="index.php" class="coupon-box" onsubmit="handleApplyVoucher(event)">' +
            '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
            '<input type="hidden" name="action" value="apply_voucher">' +
            '<input type="text" name="voucher_code" maxlength="30" placeholder="Nhập mã ưu đãi" aria-label="Mã ưu đãi" required>' +
            '<button type="submit">Áp dụng</button></form>';
        }
      } else {
        showToast(res.message, 'error');
      }
    })
    .catch(function () {
      showToast('Lỗi kết nối khi bỏ mã ưu đãi', 'error');
    });
}
</script>
