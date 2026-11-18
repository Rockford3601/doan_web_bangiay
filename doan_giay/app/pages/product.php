<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$pdo = db();
$id  = get_int('id');

$s = $pdo->prepare("SELECT p.*, c.name AS category_name, b.name AS brand_name FROM product p
                      JOIN category c ON c.category_id = p.category_id
                      LEFT JOIN brand b ON b.brand_id = p.brand_id
                     WHERE p.product_id = ? AND p.status = 'active'");
$s->execute([$id]);
$product = $s->fetch();

if (!$product) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    return;
}

// Các biến thể (size + màu) còn bán
$s = $pdo->prepare("SELECT variant_id, size, color, sku, price, stock_quantity FROM product_variant
                     WHERE product_id = ? AND status = 'active'
                     ORDER BY color, CAST(size AS UNSIGNED), size");
$s->execute([$id]);
$variants = $s->fetchAll();

$basePrice = (float)$product['base_price'];
$salePrice = !empty($product['sale_price']) ? (float)$product['sale_price'] : 0.0;
$isProductSale = ($salePrice > 0 && $salePrice < $basePrice);
$productDiscountPercent = $isProductSale ? (int)round((1 - $salePrice / $basePrice) * 100) : 0;

foreach ($variants as &$v) {                 // gắn giá hiển thị (flash sale nếu có)
    $flash = active_flash_item((int)$v['variant_id']);
    if ($flash) {
        $v['display_price'] = (float)$flash['sale_price'];
        $v['is_flash']      = true;
    } elseif ($isProductSale) {
        $vPrice = (float)$v['price'];
        $v['display_price'] = ($vPrice == $basePrice) ? $salePrice : round($vPrice * ($salePrice / $basePrice));
        $v['is_flash']      = false;
    } else {
        $v['display_price'] = (float)$v['price'];
        $v['is_flash']      = false;
    }
}
unset($v);

// Nhóm theo màu để hiển thị
$byColor = [];
foreach ($variants as $v) {
    $byColor[$v['color']][] = $v;
}

// Trích xuất danh sách Màu độc nhất và Size độc nhất để người dùng chọn riêng
$distinctColors = array_values(array_unique(array_column($variants, 'color')));
$distinctSizes  = array_values(array_unique(array_column($variants, 'size')));
usort($distinctSizes, function($a, $b) {
    if (is_numeric($a) && is_numeric($b)) return $a - $b;
    return strnatcasecmp($a, $b);
});

// Ảnh: ảnh đại diện trước, rồi ảnh xem thêm theo thứ tự admin sắp xếp
$s = $pdo->prepare('SELECT image_url FROM product_image WHERE product_id = ? ORDER BY sort_order, image_id');
$s->execute([$id]);
$images = $s->fetchAll(PDO::FETCH_COLUMN);
if (!empty($product['thumbnail'])) {
    array_unshift($images, $product['thumbnail']);
}
$images = array_values(array_unique($images));

// Đánh giá
$s = $pdo->prepare("SELECT r.rating, r.comment, r.created_at, u.name FROM review r JOIN users u ON u.user_id = r.user_id
                     WHERE r.product_id = ? AND r.status = 'visible' ORDER BY r.created_at DESC LIMIT 20");
$s->execute([$id]);
$reviews = $s->fetchAll();
$avg = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : null;

$in_stock = array_filter($variants, fn($v) => (int)$v['stock_quantity'] > 0);
$fav = isset(favorite_ids()[$id]);
$self = 'index.php?page=product&id=' . $id;
$can_review = is_logged_in() && reviewable_detail_id((int)$_SESSION['user']['user_id'], $id) !== null;
$rec = recommend_products(4, $id);

// Vẽ một ô size. $interactive = true: có nút chọn (người đã đăng nhập); false: chỉ để xem (khách)
if (!function_exists('pd_size_pill')) {
    function pd_size_pill(array $v, bool $interactive): void
    {
        $stock = (int)$v['stock_quantity'];
        $out   = ($stock <= 0);
        if ($out)               $sub = 'Hết hàng';
        elseif ($stock <= 5)    $sub = 'Còn ' . $stock;
        elseif ($v['is_flash']) $sub = '⚡ Sale';
        else                    $sub = '';
        $label = 'Size ' . $v['size'] . ' · ' . $v['color'];

        echo '<label class="size-pill' . ($out ? ' is-out' : '') . ($interactive ? '' : ' is-static') . '">';
        if ($interactive) {
            echo '<input type="radio" name="variant_id" value="' . (int)$v['variant_id'] . '"'
               . ' data-price="' . e((string)(int)$v['display_price']) . '" data-stock="' . $stock . '" data-label="' . e($label) . '"'
               . ($out ? ' disabled' : ' required') . '>';
        }
        echo '<span class="size-face"><b>' . e($v['size']) . '</b><small>' . e($sub) . '</small></span></label>';
    }
}
?>
<main class="page-wrap wide">
  <div class="breadcrumbs"><a href="index.php?page=home">Trang chủ</a> / <a href="index.php?page=catalog">Sản phẩm</a> / <?php echo e($product['name']); ?></div>

  <div class="pd-layout">
    <div class="pd-gallery">
      <img id="pdMain" class="pd-main" src="<?php echo e(img_url($images[0] ?? '')); ?>"
           alt="<?php echo e($product['name']); ?>" onerror="this.onerror=null; this.src='public/assets/imgs/placeholder.svg';">
      <?php if (count($images) > 1): ?>
        <div class="pd-thumbs">
          <?php foreach ($images as $i => $img): ?>
            <button type="button" class="pd-thumb-btn <?php echo $i === 0 ? 'is-active' : ''; ?>" data-src="<?php echo e(img_url($img)); ?>" aria-label="Xem ảnh <?php echo $i + 1; ?>">
              <img class="pd-thumb" src="<?php echo e(img_url($img)); ?>" alt="" onerror="this.parentNode.remove()">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="pd-info">
      <small class="muted"><?php echo e($product['category_name']); ?> · <?php echo e(GENDERS[$product['gender']] ?? ''); ?><?php echo !empty($product['brand_name']) ? ' · ' . e($product['brand_name']) : ''; ?></small>
      <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-top:6px;">
        <h1 style="margin:0;"><?php echo e($product['name']); ?></h1>
        <?php if ($isProductSale || ($product['badge'] ?? '') === 'sale'): ?>
          <span class="badge-tag badge-tag-sale">Sale <?php echo $productDiscountPercent > 0 ? "-{$productDiscountPercent}%" : ''; ?></span>
        <?php endif; ?>
        <?php if (($product['badge'] ?? '') === 'new'): ?>
          <span class="badge-tag badge-tag-new">MỚI</span>
        <?php elseif (($product['badge'] ?? '') === 'hot'): ?>
          <span class="badge-tag badge-tag-hot">HOT</span>
        <?php endif; ?>
      </div>

      <div class="pd-price-row" style="display:flex;align-items:baseline;gap:12px;margin:12px 0;">
        <span class="pd-price" id="pdPrice" style="color:<?php echo $isProductSale ? '#e53935' : 'inherit'; ?>;font-size:26px;font-weight:800;margin:0;">
          <?php echo e(format_vnd($isProductSale ? $salePrice : $basePrice)); ?>
        </span>
        <?php if ($isProductSale): ?>
          <del class="pd-price-original" id="pdPriceOriginal" style="font-size:17px;opacity:0.6;color:#888;">
            <?php echo e(format_vnd($basePrice)); ?>
          </del>
          <span class="badge-tag badge-tag-sale" style="font-size:12px;padding:2px 8px;">-<?php echo $productDiscountPercent; ?>%</span>
        <?php endif; ?>
      </div>
      <div class="muted">
        <?php echo $avg !== null ? '★ ' . number_format($avg, 1) . ' (' . count($reviews) . ' đánh giá)' : 'Chưa có đánh giá'; ?>
      </div>
      <p class="pd-desc"><?php echo nl2br(e($product['description'] ?? '')); ?></p>

      <?php if (!$variants): ?>
        <div class="flash flash-error">Sản phẩm chưa có biến thể (size/màu) để bán.</div>

      <?php elseif (!$in_stock): ?>
        <div class="variant-picker-box">
          <div class="picker-section">
            <div class="picker-label">Màu sắc có sẵn: <strong><?php echo e(implode(', ', $distinctColors)); ?></strong></div>
          </div>
          <div class="picker-section">
            <div class="picker-label">Kích cỡ có sẵn: <strong><?php echo e(implode(', ', $distinctSizes)); ?></strong></div>
          </div>
        </div>
        <div class="flash flash-error">Sản phẩm hiện tạm hết hàng ở mọi phiên bản.</div>

      <?php elseif (!is_logged_in()): ?>
        <!-- Khách chưa đăng nhập: xem màu và size riêng biệt, cần đăng nhập để đặt hàng -->
        <div class="variant-picker-box">
          <div class="picker-section">
            <div class="picker-label">Màu sắc: <strong><?php echo e(implode(' · ', $distinctColors)); ?></strong></div>
            <div class="color-chips-row">
              <?php foreach ($distinctColors as $c): ?>
                <span class="color-chip-btn"><?php echo e($c); ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="picker-section" style="margin-top:14px;">
            <div class="size-title-row">
              <div class="picker-label">Kích cỡ (Size):</div>
              <button type="button" class="size-guide-btn" id="openSizeGuide">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 6v12M7 6v12M17 6v12"/></svg>
                Bảng quy đổi size
              </button>
            </div>
            <div class="size-chips-grid">
              <?php foreach ($distinctSizes as $s): ?>
                <span class="size-chip-btn"><b><?php echo e($s); ?></b></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <a class="btn btn-dark" href="index.php?page=login&return=<?php echo urlencode($self); ?>">Đăng nhập để chọn màu, size và mua hàng</a>

      <?php else: ?>
        <form method="post" action="index.php" class="form-stack" id="buyForm" onsubmit="return validateBuyForm()">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="cart_add">
          <input type="hidden" name="return" value="<?php echo e($self); ?>">
          <input type="hidden" name="variant_id" id="selectedVariantId" value="" required>

          <div class="variant-picker-box">
            <!-- 1. CHỌN MÀU SẮC -->
            <div class="picker-section">
              <div class="picker-label">
                Màu sắc: <strong id="selectedColorLabel" style="color:#0f172a;">Hãy chọn màu</strong>
              </div>
              <div class="color-chips-row" id="colorChipsRow">
                <?php foreach ($distinctColors as $c): ?>
                  <button type="button" class="color-chip-btn" data-color="<?php echo e($c); ?>" onclick="selectColor('<?php echo e(addslashes($c)); ?>')">
                    <?php echo e($c); ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- 2. CHỌN KÍCH CỠ (SIZE) -->
            <div class="picker-section" style="margin-top:14px;">
              <div class="size-title-row">
                <div class="picker-label">
                  Kích cỡ (Size EU): <strong id="selectedSizeLabel" style="color:#0f172a;">Hãy chọn size</strong>
                </div>
                <button type="button" class="size-guide-btn" id="openSizeGuide" aria-label="Xem bảng quy đổi size">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 6v12M7 6v12M17 6v12"/></svg>
                  Bảng quy đổi size
                </button>
              </div>
              <div class="size-chips-grid" id="sizeChipsGrid">
                <?php foreach ($distinctSizes as $s): ?>
                  <button type="button" class="size-chip-btn" data-size="<?php echo e($s); ?>" onclick="selectSize('<?php echo e(addslashes($s)); ?>')">
                    <b><?php echo e($s); ?></b>
                    <small class="stock-hint"></small>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- 3. THÔNG TIN PHIÊN BẢN ĐÃ CHỌN & SKU -->
            <div style="margin-top:14px;padding:12px;">
              
              
            </div>
          </div>

          <label style="margin-top:10px;">Số lượng
            <input type="number" name="quantity" id="qtyInput" value="1" min="1" max="<?php echo (int)MAX_QTY_PER_ITEM; ?>" required class="qty-input">
          </label>

          <div class="btn-row">
            <button type="submit" class="btn btn-dark" id="btnAddToCart">Thêm vào giỏ</button>
            <button type="submit" name="buy_now" value="1" class="btn btn-light" id="btnBuyNow">Mua ngay</button>
          </div>
        </form>
      <?php endif; ?>

      <form method="post" action="index.php" class="fav-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="favorite_toggle">
        <input type="hidden" name="product_id" value="<?php echo $id; ?>">
        <input type="hidden" name="return" value="<?php echo e($self); ?>">
        <button type="submit" class="fav-btn <?php echo $fav ? 'is-fav' : ''; ?>">
          <i class="<?php echo $fav ? 'fa-solid' : 'fa-regular'; ?> fa-heart"></i> <?php echo $fav ? 'Đã yêu thích' : 'Thêm vào yêu thích'; ?>
        </button>
      </form>
    </div>
  </div>

  <section class="pd-reviews">
    <h2>Đánh giá từ khách hàng</h2>
    <div id="reviewAjaxMsg"></div>
    <?php if ($can_review): ?>
      <form id="reviewForm" method="post" action="index.php" class="form-card form-stack" onsubmit="sendReviewAjax(event)">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="review_save">
        <input type="hidden" name="product_id" value="<?php echo $id; ?>">
        <strong>Bạn đã mua sản phẩm này - hãy chia sẻ cảm nhận</strong>
        <label>Đánh giá số sao:
          <div id="starRatingBox" style="font-size:26px;color:#e0a800;cursor:pointer;margin:4px 0 8px;user-select:none;">
            <span onclick="setStarRating(1)" class="star-item" data-val="1">★</span><span onclick="setStarRating(2)" class="star-item" data-val="2">★</span><span onclick="setStarRating(3)" class="star-item" data-val="3">★</span><span onclick="setStarRating(4)" class="star-item" data-val="4">★</span><span onclick="setStarRating(5)" class="star-item" data-val="5">★</span>
            <span id="starRatingLabel" style="font-size:14px;color:#555;margin-left:8px;font-weight:normal;">5 sao (Tuyệt vời)</span>
          </div>
          <input type="hidden" name="rating" id="ratingInput" value="5">
        </label>
        <label>Nhận xét (không bắt buộc) <textarea name="comment" rows="3" maxlength="1000" placeholder="Cảm nhận của bạn về sản phẩm..."></textarea></label>
        <button type="submit" class="btn btn-dark" id="btnSubmitReview">Gửi đánh giá</button>
      </form>
    <?php endif; ?>
    <?php if (!$reviews): ?>
      <p class="muted">Chưa có đánh giá nào.</p>
    <?php endif; ?>
    <?php foreach ($reviews as $r): ?>
      <article class="review-item">
        <strong><?php echo e($r['name']); ?></strong>
        <span class="stars"><?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']); ?></span>
        <span class="muted"><?php echo e(date('d/m/Y', strtotime($r['created_at']))); ?></span>
        <p><?php echo e($r['comment'] ?? ''); ?></p>
      </article>
    <?php endforeach; ?>
  </section>

  <?php render_recommend_section('Có thể bạn cũng thích', $rec); ?>
</main>

<!-- ===== Size Guide Modal ===== -->
<div id="sizeGuideModal" class="sg-overlay" role="dialog" aria-modal="true" aria-label="Bảng quy đổi size giày" hidden>
  <div class="sg-dialog">
    <div class="sg-header">
      <h2 class="sg-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 6v12M7 6v12M17 6v12"/></svg>
        Bảng Quy Đổi Size Giày
      </h2>
      <button type="button" class="sg-close" id="closeSizeGuide" aria-label="Đóng">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="sg-body">
      <div class="sg-img-wrap">
        <img src="<?php echo e(img_url('assets/imgs/size_chart.svg')); ?>"
             alt="Bảng quy đổi size giày US, UK, EU, CM/JP"
             class="sg-chart-img"
             loading="lazy">
      </div>
      <p class="sg-note">
        <strong>Mẹo chọn size:</strong> Đo chiều dài bàn chân (từ gót đến ngón dài nhất).
        Nếu số đo nằm giữa 2 size, hãy chọn <strong>size lớn hơn</strong> để thoải mái hơn.
      </p>
    </div>
    <div class="sg-footer">
      <button type="button" class="btn btn-dark" id="closeSizeGuideFooter">Đã hiểu, chọn size</button>
    </div>
  </div>
</div>

<script>
  // Đánh giá số sao (Star Rate)
  function setStarRating(stars) {
    var ratingInput = document.getElementById('ratingInput');
    if (ratingInput) {
      ratingInput.value = stars;
    }
    var items = document.querySelectorAll('#starRatingBox .star-item');
    for (var i = 0; i < items.length; i++) {
      items[i].textContent = (i < stars) ? '★' : '☆';
    }
    var labels = {
      1: '1 sao (Rất tệ)',
      2: '2 sao (Tệ)',
      3: '3 sao (Bình thường)',
      4: '4 sao (Tốt)',
      5: '5 sao (Tuyệt vời)'
    };
    var labelEl = document.getElementById('starRatingLabel');
    if (labelEl) {
      labelEl.textContent = labels[stars] || (stars + ' sao');
    }
  }

  // Gửi đánh giá bằng AJAX (chuẩn cơ bản W3Schools XMLHttpRequest)
  function sendReviewAjax(event) {
    event.preventDefault();

    var form = document.getElementById('reviewForm');
    if (!form) return;

    var btn = document.getElementById('btnSubmitReview');
    if (btn) btn.disabled = true;

    var productId = form.elements['product_id'].value;
    var csrfToken = form.elements['csrf_token'].value;
    var rating    = form.elements['rating'].value;
    var comment   = form.elements['comment'].value;
    var msgBox    = document.getElementById('reviewAjaxMsg');

    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        try {
          var res = JSON.parse(this.responseText);
          if (res.success) {
            msgBox.innerHTML = '<div style="background:#e8f5e9;color:#2e7d32;padding:12px;border-radius:6px;margin-bottom:12px;font-weight:600;">' + res.message + '</div>';
            form.style.display = 'none';
            setTimeout(function() {
              window.location.reload();
            }, 1000);
          } else {
            msgBox.innerHTML = '<div style="background:#ffebee;color:#c62828;padding:12px;border-radius:6px;margin-bottom:12px;">' + res.message + '</div>';
            if (btn) btn.disabled = false;
          }
        } catch (e) {
          msgBox.innerHTML = '<div style="background:#e8f5e9;color:#2e7d32;padding:12px;border-radius:6px;margin-bottom:12px;font-weight:600;">Đánh giá thành công!</div>';
          form.style.display = 'none';
          setTimeout(function() {
            window.location.reload();
          }, 1000);
        }
      }
    };
    xhttp.open("POST", "index.php", true);
    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.send("action=review_save&ajax=1&product_id=" + encodeURIComponent(productId) +
               "&csrf_token=" + encodeURIComponent(csrfToken) +
               "&rating=" + encodeURIComponent(rating) +
               "&comment=" + encodeURIComponent(comment));
  }

  (function () {
    // Bấm ảnh nhỏ để xem ảnh lớn
    var main = document.getElementById('pdMain');
    document.querySelectorAll('.pd-thumb-btn').forEach(function (b) {
      b.addEventListener('click', function () {
        main.src = b.getAttribute('data-src');
        document.querySelectorAll('.pd-thumb-btn').forEach(function (x) { x.classList.remove('is-active'); });
        b.classList.add('is-active');
      });
    });

    // ---- Dữ liệu biến thể từ máy chủ (PHP -> JSON) ----
    var variantsData = <?php echo json_encode($variants, JSON_UNESCAPED_UNICODE); ?>;
    var selectedColor = '';
    var selectedSize  = '';

    // Hàm chọn Màu sắc
    window.selectColor = function(color) {
      selectedColor = color;
      var lbl = document.getElementById('selectedColorLabel');
      if (lbl) lbl.textContent = color;

      // Cập nhật class active cho nút màu
      document.querySelectorAll('#colorChipsRow .color-chip-btn').forEach(function(btn) {
        if (btn.getAttribute('data-color') === color) {
          btn.classList.add('is-active');
        } else {
          btn.classList.remove('is-active');
        }
      });

      // Cập nhật trạng thái còn hàng / hết hàng của các size theo màu này
      updateSizesAvailability();

      // Cập nhật thông tin biến thể
      updateSelectedVariantInfo();
    };

    // Hàm chọn Kích cỡ (Size)
    window.selectSize = function(size) {
      var btn = document.querySelector('#sizeChipsGrid .size-chip-btn[data-size="' + size + '"]');
      if (btn && btn.classList.contains('is-out')) {
        return; // Size này của màu đang chọn đã hết hàng
      }

      selectedSize = size;
      var lbl = document.getElementById('selectedSizeLabel');
      if (lbl) lbl.textContent = size;

      // Cập nhật class active cho nút size
      document.querySelectorAll('#sizeChipsGrid .size-chip-btn').forEach(function(b) {
        if (b.getAttribute('data-size') === size) {
          b.classList.add('is-active');
        } else {
          b.classList.remove('is-active');
        }
      });

      updateSelectedVariantInfo();
    };

    // Kiểm tra xem với màu đang chọn, size nào còn hàng, size nào hết hàng
    function updateSizesAvailability() {
      document.querySelectorAll('#sizeChipsGrid .size-chip-btn').forEach(function(btn) {
        var s = btn.getAttribute('data-size');
        var hint = btn.querySelector('.stock-hint');

        // Tìm biến thể có color = selectedColor và size = s
        var match = null;
        for (var i = 0; i < variantsData.length; i++) {
          if (variantsData[i].color === selectedColor && String(variantsData[i].size) === String(s)) {
            match = variantsData[i];
            break;
          }
        }

        if (!match) {
          btn.classList.add('is-out');
          btn.title = 'Màu ' + selectedColor + ' không có size ' + s;
          if (hint) hint.textContent = '—';
        } else if (parseInt(match.stock_quantity, 10) <= 0) {
          btn.classList.add('is-out');
          btn.title = 'Hết hàng';
          if (hint) hint.textContent = 'Hết';
        } else {
          btn.classList.remove('is-out');
          btn.title = 'Còn hàng';
          var st = parseInt(match.stock_quantity, 10);
          if (hint) hint.textContent = st <= 5 ? ('Còn ' + st) : '';
        }
      });
    }

    // Cập nhật giá, SKU, thông tin tồn kho và gán variant_id vào form
    function updateSelectedVariantInfo() {
      var hiddenId    = document.getElementById('selectedVariantId');
      var pickInfo    = document.getElementById('pickInfo');
      var skuBox      = document.getElementById('skuBox');
      var skuDisplay  = document.getElementById('skuDisplay');
      var priceEl     = document.getElementById('pdPrice');
      var qtyInput    = document.getElementById('qtyInput');
      var btnAdd      = document.getElementById('btnAddToCart');
      var btnBuy      = document.getElementById('btnBuyNow');

      if (!selectedColor && !selectedSize) {
        if (pickInfo) pickInfo.innerHTML = '💡 Vui lòng bấm chọn Màu sắc và Kích cỡ giày ở trên.';
        if (hiddenId) hiddenId.value = '';
        if (skuBox) skuBox.style.display = 'none';
        return;
      }
      if (!selectedColor) {
        if (pickInfo) pickInfo.innerHTML = '👉 Bạn đã chọn Size <strong>' + selectedSize + '</strong>. Vui lòng bấm chọn thêm Màu sắc.';
        if (hiddenId) hiddenId.value = '';
        if (skuBox) skuBox.style.display = 'none';
        return;
      }
      if (!selectedSize) {
        if (pickInfo) pickInfo.innerHTML = '👉 Bạn đã chọn Màu <strong>' + selectedColor + '</strong>. Vui lòng bấm chọn Kích cỡ (Size).';
        if (hiddenId) hiddenId.value = '';
        if (skuBox) skuBox.style.display = 'none';
        return;
      }

      // Đã chọn đủ cả Màu và Size -> Tìm biến thể chính xác
      var match = null;
      for (var i = 0; i < variantsData.length; i++) {
        if (variantsData[i].color === selectedColor && String(variantsData[i].size) === String(selectedSize)) {
          match = variantsData[i];
          break;
        }
      }

      if (match) {
        var stock = parseInt(match.stock_quantity, 10);
        if (stock <= 0) {
          if (hiddenId) hiddenId.value = '';
          if (pickInfo) pickInfo.innerHTML = '<span style="color:#dc2626;font-weight:600;">⚠ Màu ' + selectedColor + ' - Size ' + selectedSize + ' tạm thời hết hàng.</span>';
          if (skuBox) skuBox.style.display = 'none';
          if (btnAdd) btnAdd.disabled = true;
          if (btnBuy) btnBuy.disabled = true;
        } else {
          if (hiddenId) hiddenId.value = match.variant_id;
          var dispPrice = parseInt(match.display_price, 10);
          if (priceEl && dispPrice > 0) {
            priceEl.textContent = dispPrice.toLocaleString('vi-VN') + ' ₫';
          }
          if (pickInfo) {
            pickInfo.innerHTML = '✅ Đã chọn: Màu <strong>' + selectedColor + '</strong> · Size <strong>' + selectedSize + '</strong> — <span style="color:#16a34a;font-weight:600;">Còn ' + stock + ' đôi trong kho</span>';
          }
          if (skuBox && skuDisplay) {
            skuDisplay.textContent = match.sku || '(Chưa có)';
            skuBox.style.display = 'block';
          }
          if (qtyInput) {
            var maxQ = <?php echo (int)MAX_QTY_PER_ITEM; ?>;
            qtyInput.max = Math.max(1, Math.min(maxQ, stock));
            if (parseInt(qtyInput.value, 10) > parseInt(qtyInput.max, 10)) qtyInput.value = qtyInput.max;
          }
          if (btnAdd) btnAdd.disabled = false;
          if (btnBuy) btnBuy.disabled = false;
        }
      } else {
        if (hiddenId) hiddenId.value = '';
        if (pickInfo) pickInfo.innerHTML = '<span style="color:#dc2626;">Rất tiếc, shop không có phiên bản Màu ' + selectedColor + ' với Size ' + selectedSize + '.</span>';
        if (skuBox) skuBox.style.display = 'none';
        if (btnAdd) btnAdd.disabled = true;
        if (btnBuy) btnBuy.disabled = true;
      }
    }

    // Kiểm tra trước khi submit form mua hàng
    window.validateBuyForm = function() {
      var hiddenId = document.getElementById('selectedVariantId');
      if (!hiddenId || !hiddenId.value) {
        alert('Vui lòng chọn đầy đủ Màu sắc và Kích cỡ giày trước khi thêm vào giỏ hàng!');
        return false;
      }
      return true;
    };

    // Tự động chọn Màu và Size đầu tiên còn hàng khi vào trang để trải nghiệm người dùng mượt mà nhất
    (function autoSelectFirstAvailable() {
      if (!variantsData || variantsData.length === 0) return;
      for (var i = 0; i < variantsData.length; i++) {
        if (parseInt(variantsData[i].stock_quantity, 10) > 0) {
          selectColor(variantsData[i].color);
          selectSize(variantsData[i].size);
          break;
        }
      }
    })();

    // AJAX: Thêm vào giỏ hàng không tải lại trang
    var btnAdd = document.getElementById('btnAddToCart');
    if (btnAdd) {
      btnAdd.addEventListener('click', function (e) {
        if (!window.validateBuyForm()) {
          e.preventDefault();
          return;
        }
        e.preventDefault();
        var form = document.getElementById('buyForm');
        var fd = new FormData(form);

        var originalText = btnAdd.textContent;
        btnAdd.disabled = true;
        btnAdd.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang thêm...';

        ajaxPost('index.php', fd)
          .then(function (res) {
            btnAdd.disabled = false;
            btnAdd.textContent = originalText;
            if (res.success) {
              showToast(res.message, 'success');
              // Cập nhật số lượng trên icon giỏ hàng header
              document.querySelectorAll('.cart-count').forEach(function (el) {
                el.textContent = res.cart_count;
              });
            } else {
              if (res.redirect) {
                window.location.href = res.redirect;
              } else {
                showToast(res.message || 'Lỗi thêm vào giỏ', 'error');
              }
            }
          })
          .catch(function () {
            btnAdd.disabled = false;
            btnAdd.textContent = originalText;
            showToast('Lỗi kết nối máy chủ', 'error');
          });
      });
    }
    // ---- Size Guide Modal ----
    (function () {
      var modal   = document.getElementById('sizeGuideModal');
      var openBtn = document.getElementById('openSizeGuide');
      var closeBtn  = document.getElementById('closeSizeGuide');
      var closeFooter = document.getElementById('closeSizeGuideFooter');
      if (!modal || !openBtn) return;

      function openModal() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        closeBtn.focus();
      }
      function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        openBtn.focus();
      }

      openBtn.addEventListener('click', openModal);
      closeBtn.addEventListener('click', closeModal);
      closeFooter.addEventListener('click', closeModal);

      // Click overlay to close
      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
      });

      // ESC key to close
      document.addEventListener('keydown', function (e) {
        if (!modal.hidden && e.key === 'Escape') closeModal();
      });
    })();
  })();
</script>
