<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>
<header class="main-header">
  <div class="container main-header-inner">
    <a href="index.php?page=home" class="brand-wrap" aria-label="Nike Tuyển Chọn">
      <div class="brand">NIKE</div>
      <div class="brand-divider"></div>
      <div class="brand-tag">TUYỂN CHỌN<br />MỖI NGÀY</div>
    </a>

    <nav class="main-nav" aria-label="Menu chính">
      <a href="index.php?page=catalog">Hàng mới</a>
      <a href="index.php?page=catalog1">Nam</a>
      <a href="index.php?page=catalog">Nữ</a>
      <a href="index.php?page=catalog">Trẻ em</a>
      <a href="index.php?page=catalog">Chạy bộ</a>
      <a href="index.php?page=catalog">Bộ sưu tập</a>
    </nav>

    <div class="header-tools">
      <button class="search-box" type="button" aria-label="Tìm kiếm">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>Tìm giày của bạn</span>
      </button>
      <button class="icon-btn" type="button" aria-label="Yêu thích">
        <i class="fa-regular fa-heart"></i>
      </button>
      <button class="icon-btn" type="button" aria-label="Tài khoản">
        <i class="fa-regular fa-user"></i>
      </button>
      <a href="index.php?page=cart" class="cart-badge" aria-label="Giỏ hàng">
        <i class="fa-solid fa-bag-shopping"></i>
        <span class="cart-count">2</span>
      </a>
    </div>
  </div>
</header>