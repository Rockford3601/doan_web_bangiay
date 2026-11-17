<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>
<nav class="admin-nav">
  <?php
  // Nhân viên (staff) chỉ thấy phần vận hành; Admin thấy thêm phần cấu hình
  $__items = ['admin' => 'Tổng quan', 'admin_products' => 'Sản phẩm & kho', 'admin_orders' => 'Đơn hàng', 'admin_returns' => 'Đổi trả', 'admin_reviews' => 'Đánh giá'];
  if (is_admin()) {
      $__items += ['admin_catalog' => 'Danh mục & thương hiệu', 'admin_attributes' => 'Màu / Size / SKU', 'admin_flash' => 'Flash sale', 'admin_vouchers' => 'Voucher', 'admin_users' => 'Người dùng'];
  }
  $__cur = get_str('page') ?: 'admin';
  foreach ($__items as $k => $label):
      $active = ($__cur === $k) || ($k === 'admin_products' && $__cur === 'admin_product_form');
  ?>
    <a href="index.php?page=<?php echo e($k); ?>" class="<?php echo $active ? 'active' : ''; ?>"><?php echo e($label); ?></a>
  <?php endforeach; ?>
</nav>
