<?php
if (!defined('SECURE_CHECK')) exit('Access denied');
$pdo = db();
$cats   = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM product p WHERE p.category_id = c.category_id) AS n FROM category c ORDER BY c.category_id')->fetchAll();
$brands = $pdo->query('SELECT b.*, (SELECT COUNT(*) FROM product p WHERE p.brand_id = b.brand_id) AS n FROM brand b ORDER BY b.brand_id')->fetchAll();
$statusOpts = ['active' => 'Hiển thị', 'inactive' => 'Ẩn'];
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>

  <div class="two-col">
    <section>
      <h2>Danh mục</h2>
      <?php foreach (array_merge($cats, [['category_id' => 0, 'name' => '', 'description' => '', 'status' => 'active', 'n' => 0]]) as $c): ?>
      <form method="post" action="index.php" class="form-card form-stack">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="admin_category_save">
        <input type="hidden" name="category_id" value="<?php echo (int)$c['category_id']; ?>">
        <strong><?php echo $c['category_id'] ? 'Danh mục #' . (int)$c['category_id'] . ' (' . (int)$c['n'] . ' sản phẩm)' : '+ Thêm danh mục mới'; ?></strong>
        <input type="text" name="name" required minlength="2" maxlength="100" placeholder="Tên danh mục" value="<?php echo e($c['name']); ?>">
        <input type="text" name="description" maxlength="500" placeholder="Mô tả" value="<?php echo e($c['description'] ?? ''); ?>">
        <div class="btn-row">
          <select name="status"><?php foreach ($statusOpts as $k => $l): ?><option value="<?php echo e($k); ?>" <?php echo $c['status'] === $k ? 'selected' : ''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select>
          <button type="submit" class="btn btn-dark btn-sm">Lưu</button>
        </div>
      </form>
      <?php endforeach; ?>
    </section>

    <section>
      <h2>Thương hiệu</h2>
      <?php foreach (array_merge($brands, [['brand_id' => 0, 'name' => '', 'status' => 'active', 'n' => 0]]) as $b): ?>
      <form method="post" action="index.php" class="form-card form-stack">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="admin_brand_save">
        <input type="hidden" name="brand_id" value="<?php echo (int)$b['brand_id']; ?>">
        <strong><?php echo $b['brand_id'] ? 'Thương hiệu #' . (int)$b['brand_id'] . ' (' . (int)$b['n'] . ' sản phẩm)' : '+ Thêm thương hiệu mới'; ?></strong>
        <input type="text" name="name" required minlength="2" maxlength="100" placeholder="Tên thương hiệu" value="<?php echo e($b['name']); ?>">
        <div class="btn-row">
          <select name="status"><?php foreach ($statusOpts as $k => $l): ?><option value="<?php echo e($k); ?>" <?php echo $b['status'] === $k ? 'selected' : ''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select>
          <button type="submit" class="btn btn-dark btn-sm">Lưu</button>
        </div>
      </form>
      <?php endforeach; ?>
    </section>
  </div>
</main>
