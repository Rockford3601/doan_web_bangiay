<?php
if (!defined('SECURE_CHECK')) exit('Access denied');
$pdo = db();
$editId = get_int('edit');
$sale = ['flash_sale_id' => 0, 'name' => '', 'description' => '', 'status' => 'active',
         'start_time' => date('Y-m-d H:i:s'), 'end_time' => date('Y-m-d H:i:s', strtotime('+3 days'))];
$items = [];
if ($editId > 0) {
    $s = $pdo->prepare('SELECT * FROM flash_sale WHERE flash_sale_id = ?');
    $s->execute([$editId]);
    if ($f = $s->fetch()) {
        $sale = $f;
        $i = $pdo->prepare(
            'SELECT fi.*, v.sku, v.size, v.color, v.price, p.name AS product_name
               FROM flash_sale_item fi JOIN product_variant v ON v.variant_id = fi.variant_id JOIN product p ON p.product_id = v.product_id
              WHERE fi.flash_sale_id = ? ORDER BY fi.flash_sale_item_id'
        );
        $i->execute([$editId]);
        $items = $i->fetchAll();
    } else {
        $editId = 0;
    }
}
$list = $pdo->query('SELECT *, (status = \'active\' AND NOW() BETWEEN start_time AND end_time) AS live FROM flash_sale ORDER BY flash_sale_id DESC LIMIT 50')->fetchAll();
$variants = $editId > 0 ? $pdo->query("SELECT v.variant_id, v.sku, v.size, v.color, v.price, p.name FROM product_variant v JOIN product p ON p.product_id = v.product_id WHERE v.status = 'active' AND p.status = 'active' ORDER BY p.name, v.size")->fetchAll() : [];
$dt = fn($x) => date('Y-m-d\TH:i', strtotime($x));
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>

  <div class="two-col">
    <form method="post" action="index.php" class="form-card form-stack">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="admin_flash_save">
      <input type="hidden" name="flash_sale_id" value="<?php echo (int)$sale['flash_sale_id']; ?>">
      <h2><?php echo $editId ? 'Sửa đợt flash sale #' . $editId : 'Tạo đợt flash sale'; ?></h2>
      <label>Tên đợt <input type="text" name="name" required minlength="3" maxlength="150" value="<?php echo e($sale['name']); ?>"></label>
      <label>Mô tả <input type="text" name="description" maxlength="500" value="<?php echo e($sale['description'] ?? ''); ?>"></label>
      <div class="two-col">
        <label>Bắt đầu <input type="datetime-local" name="start_time" required value="<?php echo e($dt($sale['start_time'])); ?>"></label>
        <label>Kết thúc <input type="datetime-local" name="end_time" required value="<?php echo e($dt($sale['end_time'])); ?>"></label>
      </div>
      <label>Trạng thái
        <select name="status">
          <option value="active" <?php echo $sale['status'] === 'active' ? 'selected' : ''; ?>>Bật</option>
          <option value="inactive" <?php echo $sale['status'] === 'inactive' ? 'selected' : ''; ?>>Tắt</option>
        </select>
      </label>
      <div class="btn-row">
        <button type="submit" class="btn btn-dark">Lưu đợt sale</button>
        <?php if ($editId): ?><a class="btn btn-light" href="index.php?page=admin_flash">Tạo mới</a><?php endif; ?>
      </div>
    </form>

    <section>
      <h2>Các đợt flash sale</h2>
      <?php foreach ($list as $r): ?>
        <div class="form-card" style="padding:14px;margin-bottom:10px">
          <a href="index.php?page=admin_flash&edit=<?php echo (int)$r['flash_sale_id']; ?>"><strong><?php echo e($r['name']); ?></strong></a>
          <?php if ((int)$r['live']): ?><span class="badge badge-delivered">Đang diễn ra</span><?php elseif ($r['status'] !== 'active'): ?><span class="badge badge-cancelled">Tắt</span><?php endif; ?><br>
          <small class="muted"><?php echo e(date('d/m/Y H:i', strtotime($r['start_time']))); ?> → <?php echo e(date('d/m/Y H:i', strtotime($r['end_time']))); ?></small>
        </div>
      <?php endforeach; ?>
    </section>
  </div>

  <?php if ($editId): ?>
  <section class="form-card">
    <h2>Sản phẩm trong đợt sale</h2>
    <table class="table">
      <thead><tr><th>Sản phẩm</th><th>Giá thường</th><th>Giá sale</th><th>Đã bán / Suất</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><?php echo e($it['product_name']); ?><br><small class="muted"><?php echo e($it['sku']); ?> · size <?php echo e($it['size']); ?> · <?php echo e($it['color']); ?></small></td>
          <td><?php echo e(format_vnd($it['price'])); ?></td>
          <td><strong><?php echo e(format_vnd($it['sale_price'])); ?></strong></td>
          <td><?php echo (int)$it['sold_quantity']; ?> / <?php echo (int)$it['quantity_limit']; ?></td>
          <td>
            <form method="post" action="index.php" class="inline-form" onsubmit="return confirm('Xóa khỏi flash sale?');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="admin_flash_item_delete">
              <input type="hidden" name="flash_sale_id" value="<?php echo (int)$editId; ?>">
              <input type="hidden" name="flash_sale_item_id" value="<?php echo (int)$it['flash_sale_item_id']; ?>">
              <button type="submit" class="btn btn-light btn-sm">Xóa</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post" action="index.php" class="form-stack" style="margin-top:16px">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="admin_flash_item_save">
      <input type="hidden" name="flash_sale_id" value="<?php echo (int)$editId; ?>">
      <h3>Thêm / cập nhật sản phẩm</h3>
      <label>Biến thể
        <select name="variant_id" required>
          <option value="">-- chọn --</option>
          <?php foreach ($variants as $v): ?>
            <option value="<?php echo (int)$v['variant_id']; ?>"><?php echo e($v['name'] . ' · ' . $v['sku'] . ' · size ' . $v['size'] . ' · ' . $v['color'] . ' · ' . format_vnd($v['price'])); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="two-col">
        <label>Giá flash sale (₫) <input type="number" name="sale_price" required min="1" step="1000"></label>
        <label>Số suất <input type="number" name="quantity_limit" required min="1" value="10"></label>
      </div>
      <button type="submit" class="btn btn-dark">Lưu</button>
    </form>
  </section>
  <?php endif; ?>
</main>
