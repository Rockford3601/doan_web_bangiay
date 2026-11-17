<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$s = db()->prepare(
    "SELECT p.product_id, p.name, p.base_price, p.sale_price, p.badge, p.thumbnail, c.name AS category_name
       FROM favorite f
       JOIN product p ON p.product_id = f.product_id
       JOIN category c ON c.category_id = p.category_id
      WHERE f.user_id = ? AND p.status = 'active'
      ORDER BY f.favorite_id DESC"
);
$s->execute([(int)$_SESSION['user']['user_id']]);
$items = $s->fetchAll();
?>
<div class="khungchua_danhmuc">
  <div class="banner_danhmuc"><h2>SẢN PHẨM YÊU THÍCH (<?php echo count($items); ?>)</h2></div>
  <main class="noidung_danhmuc">
    <?php if (!$items): ?>
      <p style="color:white;padding:20px;">Bạn chưa yêu thích sản phẩm nào. <a href="index.php?page=catalog" style="text-decoration:underline">Khám phá ngay</a></p>
    <?php endif; ?>
    <?php foreach ($items as $product): ?>
      <?php include __DIR__ . '/../components/product_card.php'; ?>
    <?php endforeach; ?>
  </main>
</div>
<div class="container"><?php render_recommend_section('Gợi ý cho bạn', recommend_products(4)); ?></div>
