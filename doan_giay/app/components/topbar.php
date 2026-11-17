<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>
<header class="topbar">
  <div class="topbar-inner container">
    <span class="topbar-text">LỰA CHỌN CỦA BẠN. NHỊP ĐI CỦA BẠN.</span>
    <nav class="topbar-nav" aria-label="Tính năng nhanh">
      <a href="https://docs.google.com/forms/d/e/1FAIpQLScenq3j53w0ZO4l1bD0A8WrAWX0anuUuVeD_KiEXANTmy4Xhg/viewform">Trợ giúp</a>
      <span>|</span>
      <a href="index.php?page=orders">Theo dõi đơn hàng</a>
      <span>|</span>
      <?php if (is_logged_in()): ?>
        <?php if (is_staff()): ?>
          <a href="index.php?page=admin">Quản trị</a>
          <span>|</span>
        <?php endif; ?>
        <a href="index.php?page=account">Xin chào, <?php echo e($_SESSION['user']['name']); ?></a>
        <span>|</span>
        <form method="post" action="index.php" class="inline-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="logout">
          <button type="submit" class="link-btn">Đăng xuất</button>
        </form>
      <?php else: ?>
        <a href="index.php?page=login">Đăng nhập</a>
        <span>|</span>
        <a href="index.php?page=register">Đăng ký</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
