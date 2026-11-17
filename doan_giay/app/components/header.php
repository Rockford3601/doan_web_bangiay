<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>
<header class="main-header" id="mainHeader">
  <div class="container main-header-inner">
    
    <!-- Nút Hamburger (chỉ hiển thị trên Mobile < 768px) -->
    <button type="button" class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Mở menu điều hướng" aria-expanded="false">
      <i class="fa-solid fa-bars"></i>
    </button>

    <!-- Logo thương hiệu -->
    <a href="index.php?page=home" class="brand-wrap" aria-label="Nike - Vietnam">
      <div class="brand">NIKE</div>
      <div class="brand-divider"></div>
      <div class="brand-tag">VIETNAM<br />Just do it!</div>
    </a>

    <!-- Menu chính ngang trên Desktop (>= 1200px) và Tablet (768px - 1199px) -->
    <nav class="main-nav" id="mainNav" aria-label="Menu chính">
      <a href="index.php?page=catalog&sort=newest">Hàng mới</a>
      <a href="index.php?page=catalog&gender=nam">Nam</a>
      <a href="index.php?page=catalog&gender=nu">Nữ</a>
      <a href="index.php?page=catalog&gender=tre">Trẻ em</a>
      <a href="index.php?page=bosuutap">Bộ sưu tập</a>
    </nav>

    <!-- Công cụ bên phải: Tìm kiếm, Yêu thích, Tài khoản, Giỏ hàng -->
    <div class="header-tools">
      <form class="search-box" action="index.php" method="get" role="search">
        <input type="hidden" name="page" value="catalog">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" maxlength="100" placeholder="Tìm giày..."
               value="<?php echo e(get_str('q')); ?>" aria-label="Tìm kiếm">
      </form>
      <a class="icon-btn" href="index.php?page=favorites" aria-label="Yêu thích">
        <i class="fa-regular fa-heart"></i>
      </a>
      <a class="icon-btn" href="index.php?page=<?php echo is_logged_in() ? 'account' : 'login'; ?>" aria-label="Tài khoản">
        <i class="fa-regular fa-user"></i>
      </a>
      <a href="index.php?page=cart" class="cart-badge" aria-label="Giỏ hàng">
        <i class="fa-solid fa-bag-shopping"></i>
        <span class="cart-count"><?php echo (int)cart_count(); ?></span>
      </a>
    </div>
  </div>
</header>

<!-- MENU NGĂN KÉO DI ĐỘNG (HOÀN TOÀN ĐỘC LẬP, MẶC ĐỊNH ẨN TRÊN DESKTOP & TABLET) -->
<div class="mobile-drawer-backdrop" id="mobileDrawerBackdrop"></div>
<aside class="mobile-drawer" id="mobileDrawer" aria-label="Menu di động">
  <div class="mobile-drawer-head">
    <div class="brand" style="font-size:24px;">NIKE</div>
    <button type="button" class="mobile-drawer-close" id="mobileDrawerClose" aria-label="Đóng menu">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <div class="mobile-drawer-body">
    <div class="mobile-drawer-section-title">DANH MỤC SẢN PHẨM</div>
    <a class="mobile-drawer-link" href="index.php?page=catalog&sort=newest">Hàng mới</a>
    <a class="mobile-drawer-link" href="index.php?page=catalog&gender=nam">Giày Nam</a>
    <a class="mobile-drawer-link" href="index.php?page=catalog&gender=nu">Giày Nữ</a>
    <a class="mobile-drawer-link" href="index.php?page=catalog&gender=tre">Giày Trẻ em</a>
    <a class="mobile-drawer-link" href="index.php?page=bosuutap">Bộ sưu tập</a>

    <hr class="mobile-drawer-divider">

    <div class="mobile-drawer-section-title">TÀI KHOẢN & TIỆN ÍCH</div>
    <a class="mobile-drawer-link" href="index.php?page=orders"><i class="fa-solid fa-box-open"></i> Đơn hàng của tôi</a>
    <a class="mobile-drawer-link" href="index.php?page=favorites"><i class="fa-solid fa-heart"></i> Danh sách yêu thích</a>
    <?php if (is_logged_in()): ?>
      <?php if (is_staff()): ?>
        <a class="mobile-drawer-link" href="index.php?page=admin"><i class="fa-solid fa-gauge"></i> Trang quản trị</a>
      <?php endif; ?>
      <a class="mobile-drawer-link" href="index.php?page=account"><i class="fa-solid fa-user-check"></i> <?php echo e($_SESSION['user']['name']); ?></a>
      <form method="post" action="index.php" style="margin-top:10px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="mobile-drawer-logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</button>
      </form>
    <?php else: ?>
      <a class="mobile-drawer-link" href="index.php?page=login"><i class="fa-solid fa-arrow-right-to-bracket"></i> Đăng nhập</a>
      <a class="mobile-drawer-link" href="index.php?page=register"><i class="fa-solid fa-user-plus"></i> Đăng ký tài khoản</a>
    <?php endif; ?>
  </div>
</aside>

<script>
(function() {
  var toggle = document.getElementById('mobileNavToggle');
  var drawer = document.getElementById('mobileDrawer');
  var closeBtn = document.getElementById('mobileDrawerClose');
  var backdrop = document.getElementById('mobileDrawerBackdrop');
  if (!toggle || !drawer) return;

  function openDrawer() {
    drawer.classList.add('is-open');
    if (backdrop) backdrop.classList.add('is-open');
    document.body.classList.add('menu-open');
    toggle.setAttribute('aria-expanded', 'true');
  }
  function closeDrawer() {
    drawer.classList.remove('is-open');
    if (backdrop) backdrop.classList.remove('is-open');
    document.body.classList.remove('menu-open');
    toggle.setAttribute('aria-expanded', 'false');
  }

  toggle.addEventListener('click', openDrawer);
  if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
  if (backdrop) backdrop.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && drawer.classList.contains('is-open')) closeDrawer();
  });
})();
</script>
