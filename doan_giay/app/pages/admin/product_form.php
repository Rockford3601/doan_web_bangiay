<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$pdo = db();
$id  = get_int('id');
$product = ['product_id' => 0, 'category_id' => 0, 'name' => '', 'description' => '', 'base_price' => '', 'sale_price' => '', 'badge' => 'none', 'gender' => 'unisex', 'status' => 'active', 'thumbnail' => ''];
$variants = [];
$moves = [];
$gallery = [];

if ($id > 0) {
    $s = $pdo->prepare('SELECT * FROM product WHERE product_id = ?');
    $s->execute([$id]);
    $found = $s->fetch();
    if (!$found) {
        echo '<main class="page-wrap"><div class="empty-state"><p>Không tìm thấy sản phẩm.</p><a class="btn btn-dark" href="index.php?page=admin_products">Quay lại</a></div></main>';
        return;
    }
    $product = $found;
    $s = $pdo->prepare('SELECT * FROM product_variant WHERE product_id = ? ORDER BY variant_id');
    $s->execute([$id]);
    $variants = $s->fetchAll();

    $s = $pdo->prepare('SELECT image_id, image_url, sort_order FROM product_image WHERE product_id = ? ORDER BY sort_order, image_id');
    $s->execute([$id]);
    $gallery = $s->fetchAll();

    $s = $pdo->prepare(
        'SELECT m.change_qty, m.reason, m.created_at, v.sku FROM stock_movement m
           JOIN product_variant v ON v.variant_id = m.variant_id
          WHERE v.product_id = ? ORDER BY m.movement_id DESC LIMIT 15'
    );
    $s->execute([$id]);
    $moves = $s->fetchAll();
}
$categories = $pdo->query('SELECT category_id, name FROM category ORDER BY name')->fetchAll();
$brands = $pdo->query('SELECT brand_id, name FROM brand ORDER BY name')->fetchAll();
// Danh sách màu sắc và size có sẵn để gợi ý cho admin
$availableColors = $pdo->query("SELECT name, hex_code FROM color WHERE status = 'active' ORDER BY name")->fetchAll();
$availableSizes  = $pdo->query("SELECT name FROM size_option WHERE status = 'active' ORDER BY sort_order, size_id")->fetchAll();

// Nếu chưa có biến thể nào (thêm mới sản phẩm), tạo 1 dòng trống mặc định
if (empty($variants)) {
    $variants[] = ['variant_id' => 0, 'size' => '', 'color' => '', 'sku' => '', 'price' => $product['base_price'], 'stock_quantity' => 0, 'status' => 'active'];
}
$reasons = ['order' => 'Khách đặt hàng', 'cancel' => 'Hủy đơn (hoàn kho)', 'admin_adjust' => 'Admin chỉnh kho', 'admin_init' => 'Nhập kho ban đầu'];
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>
  <h2><?php echo $id > 0 ? 'Sửa sản phẩm #' . $id : 'Thêm sản phẩm mới'; ?></h2>

  <form method="post" action="index.php" enctype="multipart/form-data" class="form-card form-stack" onsubmit="return validateProductForm()">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="admin_product_save">
    <input type="hidden" name="product_id" value="<?php echo (int)$product['product_id']; ?>">

    <label>Tên sản phẩm
      <input type="text" name="name" required maxlength="200" value="<?php echo e($product['name']); ?>">
    </label>
    <div class="two-col">
      <label>Danh mục
        <select name="category_id" required>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo (int)$c['category_id']; ?>" <?php echo ((int)$product['category_id'] === (int)$c['category_id']) ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Thương hiệu
        <select name="brand_id">
          <option value="0">-- chưa chọn --</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?php echo (int)$b['brand_id']; ?>" <?php echo ((int)($product['brand_id'] ?? 0) === (int)$b['brand_id']) ? 'selected' : ''; ?>><?php echo e($b['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Dành cho
        <select name="gender">
          <?php foreach (GENDERS as $k => $label): ?>
            <option value="<?php echo e($k); ?>" <?php echo ($product['gender'] === $k) ? 'selected' : ''; ?>><?php echo e($label); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="two-col">
      <label>Giá cơ bản (₫)
        <input type="number" name="base_price" id="basePriceInput" required min="0" step="1000" value="<?php echo e($product['base_price'] !== '' ? (int)$product['base_price'] : ''); ?>" oninput="calcDiscountPreview()">
      </label>
      <label>Trạng thái hiển thị
        <select name="status">
          <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Đang bán</option>
          <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Ẩn</option>
        </select>
      </label>
    </div>
    <div class="two-col">
      <label>Nhãn / Trạng thái sản phẩm (Tag)
        <select name="badge" id="productBadgeSelect" onchange="toggleSalePriceInput()">
          <option value="none" <?php echo ($product['badge'] ?? 'none') === 'none' ? 'selected' : ''; ?>>-- Bình thường (Không nhãn) --</option>
          <option value="new" <?php echo ($product['badge'] ?? '') === 'new' ? 'selected' : ''; ?>>✨ Sản phẩm mới (MỚI)</option>
          <option value="sale" <?php echo ($product['badge'] ?? '') === 'sale' ? 'selected' : ''; ?>>⚡ Đang giảm giá (SALE)</option>
          <option value="hot" <?php echo ($product['badge'] ?? '') === 'hot' ? 'selected' : ''; ?>>🔥 Bán chạy / Nổi bật (HOT)</option>
        </select>
      </label>
      <label id="salePriceGroup">Giá khuyến mãi sau giảm (₫)
        <input type="number" name="sale_price" id="salePriceInput" min="0" step="1000"
               value="<?php echo (!empty($product['sale_price']) && (float)$product['sale_price'] > 0) ? (int)$product['sale_price'] : ''; ?>"
               placeholder="Nhập giá sale (để trống nếu không giảm)" oninput="calcDiscountPreview()">
        <small id="discountPreviewText" class="hint" style="display:block;margin-top:4px;font-weight:600;"></small>
      </label>
    </div>
    <label>Mô tả
      <textarea name="description" rows="4"><?php echo e($product['description'] ?? ''); ?></textarea>
    </label>
    <label>Ảnh đại diện (JPG/PNG/WEBP, tối đa 2MB)
      <input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp">
    </label>
    <?php if (!empty($product['thumbnail'])): ?>
      <img class="thumb-lg" src="<?php echo e(img_url($product['thumbnail'])); ?>" alt="" onerror="this.onerror=null; this.src='public/assets/imgs/placeholder.svg';">
    <?php endif; ?>

    <h3>Ảnh xem thêm (thư viện ảnh)</h3>
    <?php if ($gallery): ?>
    <div class="gallery-admin">
      <label class="radio-row"><input type="radio" name="make_cover" value="0" checked> Giữ nguyên ảnh đại diện</label>
      <?php foreach ($gallery as $g): ?>
        <div class="gallery-row">
          <img src="<?php echo e(img_url($g['image_url'])); ?>" alt="" onerror="this.onerror=null; this.src='public/assets/imgs/placeholder.svg';">
          <div>
            <small class="muted"><?php echo e(basename($g['image_url'])); ?></small>
            <?php if (($product['thumbnail'] ?? '') === $g['image_url']): ?> <span class="badge badge-confirmed">Đang là ảnh đại diện</span><?php endif; ?>
          </div>
          <label>Thứ tự <input type="number" name="img_sort[<?php echo (int)$g['image_id']; ?>]" min="0" max="999" value="<?php echo (int)$g['sort_order']; ?>"></label>
          <label><input type="radio" name="make_cover" value="<?php echo (int)$g['image_id']; ?>"> Đặt làm ảnh đại diện</label>
          <label><input type="checkbox" name="img_delete[]" value="<?php echo (int)$g['image_id']; ?>"> Xóa</label>
        </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <p class="muted">Chưa có ảnh xem thêm.</p>
    <?php endif; ?>
    <label>Thêm ảnh mới (có thể chọn nhiều ảnh cùng lúc, tối đa 6 ảnh mỗi lần)
      <input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp">
    </label>

    <!-- Danh sách gợi ý Màu sắc và Size chuẩn đã quản lý trong CSDL -->
    <datalist id="colorList">
      <?php foreach ($availableColors as $ac): ?>
        <option value="<?php echo e($ac['name']); ?>"><?php echo !empty($ac['hex_code']) ? '(' . e($ac['hex_code']) . ')' : ''; ?></option>
      <?php endforeach; ?>
    </datalist>

    <datalist id="sizeList">
      <?php foreach ($availableSizes as $as): ?>
        <option value="<?php echo e($as['name']); ?>"></option>
      <?php endforeach; ?>
    </datalist>

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-top:20px;">
      <h3 style="margin:0;">Biến thể sản phẩm (Kích cỡ, Màu sắc, Tồn kho &amp; SKU)</h3>
      <a href="index.php?page=admin_attributes" target="_blank" class="btn btn-light btn-sm" style="font-size:12px;text-decoration:none;">
        ⚙️ Quản lý Màu sắc &amp; Size riêng biệt ↗
      </a>
    </div>

    <!-- Hộp công cụ sinh biến thể tự động (Màu sắc × Size) -->
    <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:14px;margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <strong style="color:#0f172a;font-size:14px;">⚡ Công cụ tạo biến thể tự động (Tránh nhập thủ công &amp; Trùng lặp):</strong>
      </div>

      <!-- Chọn nhanh từ danh sách màu có sẵn -->
      <div style="margin-bottom:10px;">
        <span style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;color:#334155;">1. Chọn các màu sắc áp dụng:</span>
        <div style="display:flex;flex-wrap:wrap;gap:6px;max-height:85px;overflow-y:auto;padding:4px 0;">
          <?php foreach ($availableColors as $ac): ?>
            <label style="margin:0;font-size:12px;background:#fff;border:1px solid #cbd5e1;padding:3px 8px;border-radius:14px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
              <input type="checkbox" class="cb-color" value="<?php echo e($ac['name']); ?>">
              <?php if (!empty($ac['hex_code'])): ?>
                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?php echo e($ac['hex_code']); ?>;border:1px solid #94a3b8;"></span>
              <?php endif; ?>
              <?php echo e($ac['name']); ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Chọn nhanh từ danh sách size có sẵn -->
      <div style="margin-bottom:10px;">
        <span style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;color:#334155;">2. Chọn các kích cỡ (size) áp dụng:</span>
        <div style="display:flex;flex-wrap:wrap;gap:6px;padding:4px 0;">
          <?php foreach ($availableSizes as $as): ?>
            <label style="margin:0;font-size:12px;background:#fff;border:1px solid #cbd5e1;padding:3px 8px;border-radius:14px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
              <input type="checkbox" class="cb-size" value="<?php echo e($as['name']); ?>">
              Size <?php echo e($as['name']); ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:10px;align-items:end;">
        <label style="margin:0;font-size:13px;">Tồn kho mặc định mỗi size
          <input type="number" id="genStock" value="10" min="0" style="height:34px;font-size:13px;">
        </label>
        <button type="button" class="btn btn-dark btn-sm" onclick="generateFromCheckboxes()" style="height:34px;font-weight:600;">
          + Sinh tổ hợp Màu × Size
        </button>
        <button type="button" class="btn btn-light btn-sm" onclick="generateAllSkus()" style="height:34px;" title="Tự động sinh lại toàn bộ SKU cho bảng biến thể bên dưới">
          🏷️ Tự tạo SKU toàn bộ
        </button>
      </div>
      <small class="hint" style="display:block;margin-top:6px;">
        Hệ thống sẽ tự động ghép các cặp Màu và Size đã chọn, đồng thời tự tạo mã SKU chuẩn không bị trùng lặp.
      </small>
    </div>

    <!-- Nơi chứa input hidden các biến thể bị xóa -->
    <div id="deletedVariantsContainer"></div>

    <div class="table-scroll">
    <table class="table variant-table" id="variantTable">
      <thead>
        <tr>
          <th>Kích cỡ (Size)</th>
          <th>Màu sắc</th>
          <th>Mã SKU</th>
          <th>Giá bán (₫)</th>
          <th>Tồn kho</th>
          <th>Trạng thái</th>
          <th>Xóa</th>
        </tr>
      </thead>
      <tbody id="variantTableBody">
      <?php foreach ($variants as $i => $v): ?>
        <tr class="variant-row" data-variant-id="<?php echo (int)$v['variant_id']; ?>">
          <td>
            <input type="hidden" name="variants[<?php echo $i; ?>][variant_id]" value="<?php echo (int)$v['variant_id']; ?>">
            <input type="text" list="sizeList" name="variants[<?php echo $i; ?>][size]" maxlength="20" placeholder="Chọn hoặc nhập size" value="<?php echo e($v['size']); ?>" class="w-sm var-size-input" required onchange="autoFillRowSku(this)">
          </td>
          <td>
            <input type="text" list="colorList" name="variants[<?php echo $i; ?>][color]" maxlength="50" placeholder="Chọn hoặc nhập màu" value="<?php echo e($v['color']); ?>" class="var-color-input" required onchange="autoFillRowSku(this)">
          </td>
          <td>
            <div style="display:flex;gap:4px;align-items:center;">
              <input type="text" name="variants[<?php echo $i; ?>][sku]" maxlength="50" placeholder="VD: SP-DEN-40" value="<?php echo e($v['sku']); ?>" class="var-sku-input" required style="font-family:monospace;font-weight:600;">
              <button type="button" class="btn btn-light btn-sm" onclick="generateRowSku(this)" title="Tự tạo mã SKU chuẩn" style="padding:4px 6px;">⚡</button>
            </div>
          </td>
          <td><input type="number" name="variants[<?php echo $i; ?>][price]" min="0" step="1000" placeholder="Giá tiền" value="<?php echo e($v['price'] !== '' ? (int)$v['price'] : ''); ?>" required></td>
          <td><input type="number" name="variants[<?php echo $i; ?>][stock_quantity]" min="0" value="<?php echo (int)$v['stock_quantity']; ?>" class="w-sm" required></td>
          <td>
            <select name="variants[<?php echo $i; ?>][status]">
              <option value="active" <?php echo $v['status'] === 'active' ? 'selected' : ''; ?>>Đang bán</option>
              <option value="inactive" <?php echo $v['status'] === 'inactive' ? 'selected' : ''; ?>>Ngừng bán</option>
            </select>
          </td>
          <td style="text-align:center;">
            <button type="button" class="btn btn-sm btn-danger" onclick="removeVariantRow(this, <?php echo (int)$v['variant_id']; ?>)" title="Xóa dòng này">
              ✕
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <div style="margin: 12px 0;display:flex;gap:8px;">
      <button type="button" class="btn btn-light btn-sm" onclick="addEmptyVariantRow()">
        + Thêm 1 dòng biến thể trống
      </button>
    </div>


    <div class="btn-row">
      <button type="submit" class="btn btn-dark">Lưu sản phẩm</button>
      <a class="btn btn-light" href="index.php?page=admin_products">Quay lại</a>
    </div>
  </form>

  <?php if ($moves): ?>
  <section class="form-card">
    <h3>Nhật ký kho gần đây</h3>
    <table class="table">
      <thead><tr><th>Thời gian</th><th>SKU</th><th>Thay đổi</th><th>Lý do</th></tr></thead>
      <tbody>
      <?php foreach ($moves as $m): ?>
        <tr>
          <td><?php echo e(date('d/m/Y H:i', strtotime($m['created_at']))); ?></td>
          <td><?php echo e($m['sku']); ?></td>
          <td><?php echo ((int)$m['change_qty'] > 0 ? '+' : '') . (int)$m['change_qty']; ?></td>
          <td><?php echo e($reasons[$m['reason']] ?? $m['reason']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>

  <script>
  function toggleSalePriceInput() {
    var b = document.getElementById('productBadgeSelect');
    var sp = document.getElementById('salePriceInput');
    if (b && b.value === 'sale') {
      if (sp && !sp.value) sp.focus();
    }
    calcDiscountPreview();
  }

  function calcDiscountPreview() {
    var baseEl = document.getElementById('basePriceInput');
    var saleEl = document.getElementById('salePriceInput');
    var badgeEl = document.getElementById('productBadgeSelect');
    var hint = document.getElementById('discountPreviewText');
    if (!baseEl || !saleEl || !hint) return;

    var base = parseFloat(baseEl.value) || 0;
    var sale = parseFloat(saleEl.value) || 0;

    if (sale > 0 && base > 0) {
      if (sale >= base) {
        hint.style.color = '#c62828';
        hint.textContent = '⚠ Giá khuyến mãi phải nhỏ hơn giá cơ bản!';
      } else {
        var pct = Math.round((1 - sale / base) * 100);
        var diff = base - sale;
        hint.style.color = '#2e7d32';
        hint.textContent = '⚡ Giảm ' + pct + '% (tiết kiệm ' + diff.toLocaleString('vi-VN') + ' ₫). Tag hiển thị: Sale -' + pct + '%';
        if (badgeEl && badgeEl.value === 'none') {
          badgeEl.value = 'sale';
        }
      }
    } else {
      if (badgeEl && badgeEl.value === 'sale') {
        hint.style.color = '#e65100';
        hint.textContent = 'Hãy nhập giá khuyến mãi để hiển thị % giảm giá trên thẻ sản phẩm.';
      } else {
        hint.textContent = '';
      }
    }
  }
  document.addEventListener('DOMContentLoaded', calcDiscountPreview);

  // ---------- Quản lý Biến thể (Thêm / Xóa / Sinh tự động ma trận Màu × Size) ----------
  var variantIndexCounter = document.querySelectorAll('#variantTableBody tr').length || 0;

  function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function removeVietnameseTones(str) {
    if (!str) return '';
    str = str.replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, "a");
    str = str.replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, "e");
    str = str.replace(/ì|í|ị|ỉ|ĩ/g, "i");
    str = str.replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, "o");
    str = str.replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, "u");
    str = str.replace(/ỳ|ý|ỵ|ỷ|ỹ/g, "y");
    str = str.replace(/đ/g, "d");
    str = str.replace(/À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ/g, "A");
    str = str.replace(/È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ/g, "E");
    str = str.replace(/Ì|Í|Ị|Ỉ|Ĩ/g, "I");
    str = str.replace(/Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ/g, "O");
    str = str.replace(/Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ/g, "U");
    str = str.replace(/Ỳ|Ý|Ỵ|Ỷ|Ỹ/g, "Y");
    str = str.replace(/Đ/g, "D");
    return str.replace(/[^A-Za-z0-9]/g, "").toUpperCase();
  }

  // Lấy tiền tố mã sản phẩm từ Tên sản phẩm
  function getProductSkuPrefix() {
    var nameEl = document.querySelector('input[name="name"]');
    var pName = nameEl ? nameEl.value.trim() : '';
    if (!pName) return 'SP';
    var words = pName.split(/\s+/).filter(function(w){ return w.length > 0; });
    if (words.length >= 2) {
      // Lấy chữ cái đầu của mỗi từ: ví dụ "Air Force" -> "AF"
      var initial = '';
      for (var i = 0; i < Math.min(words.length, 3); i++) {
        var clean = removeVietnameseTones(words[i]);
        if (clean.length > 0) initial += clean[0];
      }
      return initial || 'SP';
    }
    return removeVietnameseTones(pName).substring(0, 4) || 'SP';
  }

  // Tự sinh mã SKU cho 1 dòng biến thể
  function makeSku(color, size) {
    var prefix = getProductSkuPrefix();
    var colorCode = removeVietnameseTones(color).substring(0, 3) || 'COL';
    var sizeCode  = removeVietnameseTones(size);
    return prefix + '-' + colorCode + '-' + sizeCode;
  }

  // Khi admin gõ/chọn màu hoặc size, nếu ô SKU chưa có hoặc muốn tự điền
  function autoFillRowSku(input) {
    var row = input.closest('tr');
    if (!row) return;
    var sizeInput = row.querySelector('.var-size-input');
    var colorInput = row.querySelector('.var-color-input');
    var skuInput = row.querySelector('.var-sku-input');
    if (sizeInput && colorInput && skuInput) {
      if (skuInput.value.trim() === '' && sizeInput.value.trim() !== '' && colorInput.value.trim() !== '') {
        skuInput.value = makeSku(colorInput.value.trim(), sizeInput.value.trim());
      }
    }
  }

  // Bấm nút icon ⚡ ở dòng biến thể để tự động tạo SKU cho dòng đó
  function generateRowSku(btn) {
    var row = btn.closest('tr');
    if (!row) return;
    var sizeInput = row.querySelector('.var-size-input');
    var colorInput = row.querySelector('.var-color-input');
    var skuInput = row.querySelector('.var-sku-input');
    if (!sizeInput || !colorInput || !skuInput) return;

    var size = sizeInput.value.trim();
    var color = colorInput.value.trim();
    if (!size || !color) {
      alert('Vui lòng chọn hoặc nhập Size và Màu sắc trước khi sinh mã SKU!');
      return;
    }
    skuInput.value = makeSku(color, size);
  }

  // Tự tạo SKU cho tất cả các dòng hiện có
  function generateAllSkus() {
    var rows = document.querySelectorAll('#variantTableBody tr');
    var updated = 0;
    rows.forEach(function(row) {
      var sizeInput = row.querySelector('.var-size-input');
      var colorInput = row.querySelector('.var-color-input');
      var skuInput = row.querySelector('.var-sku-input');
      if (sizeInput && colorInput && skuInput) {
        var s = sizeInput.value.trim();
        var c = colorInput.value.trim();
        if (s && c) {
          skuInput.value = makeSku(c, s);
          updated++;
        }
      }
    });
    alert('Đã cập nhật mã SKU cho ' + updated + ' dòng biến thể!');
  }

  function addEmptyVariantRow(size, color, sku, price, stock) {
    variantIndexCounter++;
    var idx = variantIndexCounter;
    var basePrice = document.getElementById('basePriceInput') ? document.getElementById('basePriceInput').value : '';
    size = size || '';
    color = color || '';
    sku = sku || '';
    price = (price !== undefined && price !== '') ? price : basePrice;
    stock = stock !== undefined ? stock : 10;

    var tbody = document.getElementById('variantTableBody');
    var tr = document.createElement('tr');
    tr.className = 'variant-row';
    tr.setAttribute('data-variant-id', '0');

    tr.innerHTML = '<td>' +
      '<input type="hidden" name="variants[' + idx + '][variant_id]" value="0">' +
      '<input type="text" list="sizeList" name="variants[' + idx + '][size]" maxlength="20" placeholder="Chọn size" value="' + escapeHtml(size) + '" class="w-sm var-size-input" required onchange="autoFillRowSku(this)">' +
      '</td>' +
      '<td><input type="text" list="colorList" name="variants[' + idx + '][color]" maxlength="50" placeholder="Chọn màu" value="' + escapeHtml(color) + '" class="var-color-input" required onchange="autoFillRowSku(this)"></td>' +
      '<td>' +
        '<div style="display:flex;gap:4px;align-items:center;">' +
          '<input type="text" name="variants[' + idx + '][sku]" maxlength="50" placeholder="VD: SP-DEN-40" value="' + escapeHtml(sku) + '" class="var-sku-input" required style="font-family:monospace;font-weight:600;">' +
          '<button type="button" class="btn btn-light btn-sm" onclick="generateRowSku(this)" title="Tự tạo mã SKU chuẩn" style="padding:4px 6px;">⚡</button>' +
        '</div>' +
      '</td>' +
      '<td><input type="number" name="variants[' + idx + '][price]" min="0" step="1000" placeholder="Giá tiền" value="' + escapeHtml(price) + '" required></td>' +
      '<td><input type="number" name="variants[' + idx + '][stock_quantity]" min="0" value="' + escapeHtml(stock) + '" class="w-sm" required></td>' +
      '<td>' +
        '<select name="variants[' + idx + '][status]">' +
          '<option value="active" selected>Đang bán</option>' +
          '<option value="inactive">Ngừng bán</option>' +
        '</select>' +
      '</td>' +
      '<td style="text-align:center;">' +
        '<button type="button" class="btn btn-sm btn-danger" onclick="removeVariantRow(this, 0)" title="Xóa dòng này">✕</button>' +
      '</td>';

    tbody.appendChild(tr);
  }

  function removeVariantRow(btn, variantId) {
    var tr = btn.closest('tr');
    if (variantId > 0) {
      if (!confirm('Bạn có chắc muốn xóa biến thể này?')) return;
      var container = document.getElementById('deletedVariantsContainer');
      var hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'delete_variant_ids[]';
      hiddenInput.value = variantId;
      container.appendChild(hiddenInput);
    }
    if (tr) tr.remove();
  }

  // Sinh biến thể từ các checkbox Màu và Size được tick chọn
  function generateFromCheckboxes() {
    var selectedColors = [];
    document.querySelectorAll('.cb-color:checked').forEach(function(cb) {
      selectedColors.push(cb.value);
    });

    var selectedSizes = [];
    document.querySelectorAll('.cb-size:checked').forEach(function(cb) {
      selectedSizes.push(cb.value);
    });

    if (selectedColors.length === 0 || selectedSizes.length === 0) {
      alert('Vui lòng tích chọn ít nhất 1 Màu sắc và 1 Size ở các ô phía trên!');
      return;
    }

    var stockVal  = parseInt(document.getElementById('genStock').value, 10) || 0;
    var basePrice = document.getElementById('basePriceInput') ? document.getElementById('basePriceInput').value : '';

    // Thu thập các cặp màu-size đã có trong bảng để tránh tạo trùng
    var existingPairs = {};
    document.querySelectorAll('#variantTableBody tr').forEach(function(r) {
      var sInput = r.querySelector('.var-size-input');
      var cInput = r.querySelector('.var-color-input');
      if (sInput && cInput) {
        var k = cInput.value.trim().toLowerCase() + '|' + sInput.value.trim().toLowerCase();
        existingPairs[k] = true;
      }
    });

    var count = 0;
    for (var c = 0; c < selectedColors.length; c++) {
      for (var s = 0; s < selectedSizes.length; s++) {
        var color = selectedColors[c];
        var size  = selectedSizes[s];
        var key = color.toLowerCase() + '|' + size.toLowerCase();

        // Nếu cặp Màu - Size này đã có rồi thì bỏ qua không tạo trùng
        if (existingPairs[key]) continue;

        var autoSku = makeSku(color, size);
        addEmptyVariantRow(size, color, autoSku, basePrice, stockVal);
        existingPairs[key] = true;
        count++;
      }
    }

    if (count > 0) {
      alert('Đã thêm ' + count + ' biến thể mới không trùng lặp! Hãy kiểm tra và nhấn "Lưu sản phẩm".');
    } else {
      alert('Các biến thể ứng với các Màu và Size bạn chọn đều đã tồn tại trong bảng!');
    }
  }

  // Kiểm tra trùng lặp trên form trước khi gửi lên máy chủ
  function validateProductForm() {
    var rows = document.querySelectorAll('#variantTableBody tr');
    var seenSkus = {};
    var seenPairs = {};

    for (var i = 0; i < rows.length; i++) {
      var r = rows[i];
      var sizeInput = r.querySelector('.var-size-input');
      var colorInput = r.querySelector('.var-color-input');
      var skuInput = r.querySelector('.var-sku-input');

      if (!sizeInput || !colorInput || !skuInput) continue;

      var size = sizeInput.value.trim();
      var color = colorInput.value.trim();
      var sku = skuInput.value.trim().toUpperCase();

      if (!size || !color || !sku) {
        alert('Dòng biến thể thứ ' + (i + 1) + ' chưa điền đủ Size, Màu hoặc SKU!');
        if (!size) sizeInput.focus();
        else if (!color) colorInput.focus();
        else skuInput.focus();
        return false;
      }

      // Kiểm tra trùng SKU
      if (seenSkus[sku]) {
        alert('Lỗi trùng lặp SKU: Mã SKU "' + sku + '" xuất hiện nhiều hơn 1 lần trong bảng biến thể!');
        skuInput.focus();
        return false;
      }
      seenSkus[sku] = true;

      // Kiểm tra trùng cặp Màu + Size
      var pairKey = color.toLowerCase() + '|' + size.toLowerCase();
      if (seenPairs[pairKey]) {
        alert('Lỗi trùng lặp biến thể: Cặp Màu "' + color + '" và Size "' + size + '" bị lặp lại 2 lần!');
        colorInput.focus();
        return false;
      }
      seenPairs[pairKey] = true;
    }

    return true;
  }
  </script>
</main>
