<?php
if (!defined('SECURE_CHECK')) exit('Access denied');
$rows = db()->query(
    'SELECT r.review_id, r.rating, r.comment, r.status, r.created_at, u.username, p.name AS product_name
       FROM review r JOIN users u ON u.user_id = r.user_id JOIN product p ON p.product_id = r.product_id
      ORDER BY r.review_id DESC LIMIT 200'
)->fetchAll();
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>
  <?php if (!$rows): ?><p class="muted">Chưa có đánh giá nào.</p><?php endif; ?>
  <div class="table-scroll">
  <table class="table">
    <thead><tr><th>Sản phẩm</th><th>Người đánh giá</th><th>Sao</th><th>Nhận xét</th><th>Ngày</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?php echo e($r['product_name']); ?></td>
        <td><?php echo e($r['username']); ?></td>
        <td class="stars"><?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']); ?></td>
        <td><?php echo e($r['comment'] ?? ''); ?></td>
        <td><?php echo e(date('d/m/Y', strtotime($r['created_at']))); ?></td>
        <td><span class="badge <?php echo $r['status'] === 'visible' ? 'badge-delivered' : 'badge-cancelled'; ?>"><?php echo $r['status'] === 'visible' ? 'Hiển thị' : 'Đã ẩn'; ?></span></td>
        <td>
          <form method="post" action="index.php" class="inline-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="admin_review_toggle">
            <input type="hidden" name="review_id" value="<?php echo (int)$r['review_id']; ?>">
            <button type="submit" class="btn btn-light btn-sm"><?php echo $r['status'] === 'visible' ? 'Ẩn' : 'Hiện'; ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</main>
