<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>

<main class="catalog-main">
  <div class="container">
    <div class="breadcrumbs">Trang chủ / Tất cả giày</div>

    <section class="catalog-header">
      <div class="catalog-title">
        <span class="eyebrow">KHÁM PHÁ NIKE</span>
        <h1>MỖI ĐÔI GIÀY. MỘT CÁ TÍNH.</h1>
        <p>Tìm đôi giày phù hợp với phong cách và những chuyển động của bạn.</p>
      </div>
    </section>

    <section class="toolbar">
      <div class="category-box">
        <span class="icon-search small"></span>
        <span>Nike</span>
        <span class="caret">⌄</span>
      </div>
      <div class="result-count">6 sản phẩm phù hợp</div>
      <div class="sort-box">
        <span>Sắp xếp theo:</span>
        <button type="button">Đề xuất <span>⌄</span></button>
      </div>
    </section>

    <section class="catalog-layout">
      <!-- Gọi Sidebar Bộ Lọc từ Components -->
      <?php include __DIR__ . '/../components/filter_sidebar.php'; ?>

      <section class="product-section">
        <div class="chip-row">
          <span class="chip">2.000.000 – 5.000.000 ₫</span>
          <a href="#">Xóa tất cả</a>
        </div>

        <div class="product-grid">
          <article class="product-card">
            <div class="image-wrap">
              <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80" alt="Nike Air Force 1 '07" />
              <button class="fav-btn" type="button" aria-label="Yêu thích">❤</button>
              <span class="tag">BIỂU TƯỢNG</span>
            </div>
            <div class="product-copy">
              <h3>Nike Air Force 1 '07</h3>
              <p>Giày lifestyle nam</p>
              <span>Trắng / Trắng</span>
              <strong>2.929.000 ₫</strong>
            </div>
          </article>

          <article class="product-card">
            <div class="image-wrap">
              <img src="https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=900&q=80" alt="Nike Pegasus 41" />
              <button class="fav-btn" type="button" aria-label="Yêu thích">❤</button>
              <span class="tag">CHẠY BỘ</span>
            </div>
            <div class="product-copy">
              <h3>Nike Pegasus 41</h3>
              <p>Giày chạy bộ nam</p>
              <span>Bạc / Đen / Cam</span>
              <strong>3.829.000 ₫</strong>
            </div>
          </article>

          <article class="product-card">
            <div class="image-wrap">
              <img src="https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=900&q=80" alt="Nike Zoom Fly" />
              <button class="fav-btn" type="button" aria-label="Yêu thích">❤</button>
            </div>
            <div class="product-copy">
              <h3>Nike Zoom Fly</h3>
              <p>Giày chạy bộ nữ</p>
              <span>Đen / Xám</span>
              <strong>4.159.000 ₫</strong>
            </div>
          </article>

          <article class="product-card">
            <div class="image-wrap">
              <img src="https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=900&q=80" alt="Nike Dunk Low Retro" />
              <button class="fav-btn" type="button" aria-label="Yêu thích">❤</button>
            </div>
            <div class="product-copy">
              <h3>Nike Dunk Low Retro</h3>
              <p>Giày lifestyle unisex</p>
              <span>Đen / Xám / Vàng</span>
              <strong>3.129.000 ₫</strong>
            </div>
          </article>

          <article class="product-card">
            <div class="image-wrap">
              <img src="https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=900&q=80" alt="Nike Metcon 9" />
              <button class="fav-btn" type="button" aria-label="Yêu thích">❤</button>
              <span class="tag">TẬP LUYỆN</span>
            </div>
            <div class="product-copy">
              <h3>Nike Metcon 9</h3>
              <p>Giày tập luyện nam</p>
              <span>Đen / Xanh</span>
              <strong>3.599.000 ₫</strong>
            </div>
          </article>

          <article class="product-card">
            <div class="image-wrap">
              <img src="https://images.unsplash.com/photo-1607522370275-f14206abe5d3?auto=format&fit=crop&w=900&q=80" alt="Nike G.T. Cut 3" />
              <button class="fav-btn" type="button" aria-label="Yêu thích">❤</button>
            </div>
            <div class="product-copy">
              <h3>Nike G.T. Cut 3</h3>
              <p>Giày bóng rổ nam</p>
              <span>Đỏ / Đen</span>
              <strong>4.499.000 ₫</strong>
            </div>
          </article>
        </div>
      </section>
    </section>
  </div>
</main>
