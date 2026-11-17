<?php
// Trang quản lý thuộc tính sản phẩm: Màu sắc, Size, và SKU
// Admin có thể thêm/sửa/xóa màu sắc, size và tùy chỉnh mã SKU
if (!defined('SECURE_CHECK')) exit('Access denied');
$pdo = db();

// 1. Lấy danh sách màu sắc
$colors = $pdo->query('SELECT * FROM color ORDER BY color_id')->fetchAll();

// Đếm số biến thể đang dùng mỗi màu (truy vấn riêng trên bảng product_variant để tránh lỗi xung đột Collation)
$colorCounts = [];
try {
    $stmt = $pdo->query('SELECT color, COUNT(*) AS total FROM product_variant GROUP BY color');
    while ($row = $stmt->fetch()) {
        $colorCounts[mb_strtolower($row['color'])] = (int)$row['total'];
    }
} catch (Throwable $e) {
    // Nếu chưa có bảng product_variant thì bỏ qua
}

// 2. Lấy danh sách size
$sizes = $pdo->query('SELECT * FROM size_option ORDER BY sort_order, size_id')->fetchAll();

// Đếm số biến thể đang dùng mỗi size
$sizeCounts = [];
try {
    $stmt = $pdo->query('SELECT size, COUNT(*) AS total FROM product_variant GROUP BY size');
    while ($row = $stmt->fetch()) {
        $sizeCounts[mb_strtolower($row['size'])] = (int)$row['total'];
    }
} catch (Throwable $e) {
    // Nếu chưa có bảng product_variant thì bỏ qua
}

// Lấy danh sách SKU (tra cứu & tùy chỉnh)
$skuSearch = get_str('sku_search');
$skuRows = [];
if ($skuSearch !== '') {
    $s = $pdo->prepare(
        'SELECT v.variant_id, v.sku, v.size, v.color, v.price, v.stock_quantity, v.status,
                p.name AS product_name, p.product_id
           FROM product_variant v
           JOIN product p ON p.product_id = v.product_id
          WHERE v.sku LIKE ? OR p.name LIKE ?
          ORDER BY v.sku
          LIMIT 50'
    );
    $like = '%' . $skuSearch . '%';
    $s->execute([$like, $like]);
    $skuRows = $s->fetchAll();
} else {
    $skuRows = $pdo->query(
        'SELECT v.variant_id, v.sku, v.size, v.color, v.price, v.stock_quantity, v.status,
                p.name AS product_name, p.product_id
           FROM product_variant v
           JOIN product p ON p.product_id = v.product_id
          ORDER BY v.variant_id DESC
          LIMIT 30'
    )->fetchAll();
}

$statusOpts = ['active' => 'Hiển thị', 'inactive' => 'Ẩn'];
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>

  <h2>Quản lý thuộc tính: Màu sắc, Kích cỡ (Size) &amp; SKU</h2>

  <div class="two-col" style="margin-bottom: 24px;">
    <!-- ==================== CỘT 1: MÀU SẮC ==================== -->
    <section class="form-card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h3 style="margin:0;">🎨 Tùy chỉnh Màu sắc</h3>
        <span class="badge badge-confirmed"><?php echo count($colors); ?> màu</span>
      </div>

      <!-- Form thêm màu mới -->
      <form method="post" action="index.php" class="form-stack" style="margin-bottom:16px;padding:12px;background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="admin_color_save">
        <input type="hidden" name="color_id" value="0">
        <strong style="color:#0f172a;">+ Thêm màu sắc mới</strong>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
          <label style="margin:0;flex:2;min-width:120px;">Tên màu
            <input type="text" name="name" required maxlength="50" placeholder="VD: Xanh navy" style="height:34px;">
          </label>
          <label style="margin:0;flex:1;min-width:80px;">Mã hex
            <input type="text" name="hex_code" maxlength="7" placeholder="#000080" style="height:34px;">
          </label>
          <button type="submit" class="btn btn-dark btn-sm" style="height:34px;">Thêm</button>
        </div>
      </form>

      <!-- Danh sách màu hiện có -->
      <div class="table-scroll">
      <table class="table" style="font-size:13px;">
        <thead>
          <tr>
            <th>Tên màu</th>
            <th>Mã hex / Xem</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($colors as $c): 
          $usedCount = $colorCounts[mb_strtolower($c['name'])] ?? 0;
        ?>
          <tr>
            <form method="post" action="index.php">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="admin_color_save">
              <input type="hidden" name="color_id" value="<?php echo (int)$c['color_id']; ?>">
              <td>
                <input type="text" name="name" required maxlength="50" value="<?php echo e($c['name']); ?>" style="padding:4px 6px;width:100px;">
                <?php if ($usedCount > 0): ?>
                  <small class="muted" style="display:block;font-size:11px;"><?php echo (int)$usedCount; ?> biến thể dùng</small>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:6px;">
                  <input type="text" name="hex_code" maxlength="7" value="<?php echo e($c['hex_code'] ?? ''); ?>" style="padding:4px;width:65px;">
                  <?php if (!empty($c['hex_code'])): ?>
                    <span style="display:inline-block;width:20px;height:20px;border-radius:50%;border:1px solid #ccc;background:<?php echo e($c['hex_code']); ?>;"></span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <select name="status" style="padding:4px;">
                  <option value="active" <?php echo $c['status'] === 'active' ? 'selected' : ''; ?>>Hiện</option>
                  <option value="inactive" <?php echo $c['status'] === 'inactive' ? 'selected' : ''; ?>>Ẩn</option>
                </select>
              </td>
              <td class="nowrap">
                <button type="submit" class="btn btn-dark btn-sm" style="padding:4px 8px;" title="Lưu thay đổi">Lưu</button>
            </form>
            <form method="post" action="index.php" class="inline-form" onsubmit="return confirm('Bạn có chắc muốn xóa hoặc ẩn màu này?');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="admin_color_delete">
              <input type="hidden" name="color_id" value="<?php echo (int)$c['color_id']; ?>">
              <button type="submit" class="btn btn-light btn-sm" style="padding:4px 8px;color:#c62828;" title="Xóa màu">✕</button>
            </form>
              </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </section>

    <!-- ==================== CỘT 2: SIZE ==================== -->
    <section class="form-card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h3 style="margin:0;">📏 Tùy chỉnh Kích cỡ (Size)</h3>
        <span class="badge badge-confirmed"><?php echo count($sizes); ?> size</span>
      </div>

      <!-- Form thêm size mới -->
      <form method="post" action="index.php" class="form-stack" style="margin-bottom:16px;padding:12px;background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="admin_size_save">
        <input type="hidden" name="size_id" value="0">
        <strong style="color:#0f172a;">+ Thêm size mới</strong>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
          <label style="margin:0;flex:2;min-width:100px;">Tên size
            <input type="text" name="name" required maxlength="20" placeholder="VD: 39.5 hoặc XL" style="height:34px;">
          </label>
          <label style="margin:0;flex:1;min-width:60px;">Thứ tự
            <input type="number" name="sort_order" min="0" max="999" value="0" style="height:34px;">
          </label>
          <button type="submit" class="btn btn-dark btn-sm" style="height:34px;">Thêm</button>
        </div>
      </form>

      <!-- Danh sách size hiện có -->
      <div class="table-scroll">
      <table class="table" style="font-size:13px;">
        <thead>
          <tr>
            <th>Size</th>
            <th>Thứ tự</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($sizes as $sz): 
          $usedCount = $sizeCounts[mb_strtolower($sz['name'])] ?? 0;
        ?>
          <tr>
            <form method="post" action="index.php">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="admin_size_save">
              <input type="hidden" name="size_id" value="<?php echo (int)$sz['size_id']; ?>">
              <td>
                <input type="text" name="name" required maxlength="20" value="<?php echo e($sz['name']); ?>" style="padding:4px 6px;width:75px;">
                <?php if ($usedCount > 0): ?>
                  <small class="muted" style="display:block;font-size:11px;"><?php echo (int)$usedCount; ?> SP dùng</small>
                <?php endif; ?>
              </td>
              <td>
                <input type="number" name="sort_order" min="0" max="999" value="<?php echo (int)$sz['sort_order']; ?>" style="padding:4px;width:55px;">
              </td>
              <td>
                <select name="status" style="padding:4px;">
                  <option value="active" <?php echo $sz['status'] === 'active' ? 'selected' : ''; ?>>Hiện</option>
                  <option value="inactive" <?php echo $sz['status'] === 'inactive' ? 'selected' : ''; ?>>Ẩn</option>
                </select>
              </td>
              <td class="nowrap">
                <button type="submit" class="btn btn-dark btn-sm" style="padding:4px 8px;" title="Lưu thay đổi">Lưu</button>
            </form>
            <form method="post" action="index.php" class="inline-form" onsubmit="return confirm('Bạn có chắc muốn xóa hoặc ẩn size này?');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="admin_size_delete">
              <input type="hidden" name="size_id" value="<?php echo (int)$sz['size_id']; ?>">
              <button type="submit" class="btn btn-light btn-sm" style="padding:4px 8px;color:#c62828;" title="Xóa size">✕</button>
            </form>
              </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </section>
  </div>

  <!-- ==================== PHẦN 3: TÙY CHỈNH & TRA CỨU MÃ SKU ==================== -->
  <section class="form-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
      <div>
        <h3 style="margin:0;">Quản lý &amp; Tùy chỉnh mã SKU</h3>
      </div>
      <form method="get" action="index.php" style="display:flex;gap:6px;align-items:center;margin:0;">
        <input type="hidden" name="page" value="admin_attributes">
        <input type="text" name="sku_search" placeholder="Tìm theo SKU hoặc tên SP..." value="<?php echo e($skuSearch); ?>" style="height:34px;font-size:13px;width:220px;">
        <button type="submit" class="btn btn-dark btn-sm" style="height:34px;">Tìm</button>
        <?php if ($skuSearch !== ''): ?>
          <a href="index.php?page=admin_attributes" class="btn btn-light btn-sm" style="height:34px;line-height:34px;">✕ Bỏ lọc</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($skuRows): ?>
    <div class="table-scroll">
    <table class="table">
      <thead>
        <tr>
          <th>Sản phẩm</th>
          <th>Màu</th>
          <th>Size</th>
          <th>Mã SKU hiện tại &amp; Sửa trực tiếp</th>
          <th>Giá bán</th>
          <th>Tồn kho</th>
          <th>Trạng thái</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($skuRows as $r): ?>
        <tr>
          <td>
            <strong><?php echo e($r['product_name']); ?></strong>
            <small class="muted" style="display:block;">#SP <?php echo (int)$r['product_id']; ?></small>
          </td>
          <td><span class="badge badge-light"><?php echo e($r['color']); ?></span></td>
          <td><span class="badge badge-light"><?php echo e($r['size']); ?></span></td>
          <td>
            <!-- Form cho phép sửa trực tiếp mã SKU ngay tại đây -->
            <form method="post" action="index.php" class="inline-form" style="display:flex;gap:6px;align-items:center;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="admin_variant_sku_update">
              <input type="hidden" name="variant_id" value="<?php echo (int)$r['variant_id']; ?>">
              <input type="text" name="sku" required maxlength="50" pattern="[A-Za-z0-9_\-]{2,50}"
                     value="<?php echo e($r['sku']); ?>"
                     title="2-50 ký tự: chữ, số, dấu gạch nối hoặc gạch dưới"
                     style="font-family:monospace;font-weight:700;padding:4px 8px;width:160px;">
              <button type="submit" class="btn btn-dark btn-sm" style="padding:4px 8px;">Đổi SKU</button>
            </form>
          </td>
          <td><?php echo e(format_vnd($r['price'])); ?></td>
          <td><?php echo (int)$r['stock_quantity']; ?></td>
          <td>
            <span class="badge <?php echo $r['status'] === 'active' ? 'badge-delivered' : 'badge-cancelled'; ?>">
              <?php echo $r['status'] === 'active' ? 'Đang bán' : 'Ngừng bán'; ?>
            </span>
          </td>
          <td>
            <a class="btn btn-light btn-sm" href="index.php?page=admin_product_form&id=<?php echo (int)$r['product_id']; ?>">Sửa toàn bộ SP</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?>
      <p class="muted"><?php echo $skuSearch !== '' ? 'Không tìm thấy SKU nào phù hợp với từ khóa "' . e($skuSearch) . '".' : 'Chưa có biến thể sản phẩm nào.'; ?></p>
    <?php endif; ?>
  </section>
</main>
