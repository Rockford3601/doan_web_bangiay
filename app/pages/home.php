<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>

<?php include __DIR__ . '/../components/promo_banner.php'; ?>

<main>
  <section class="hero container">
    <div class="hero-copy">
      <span class="eyebrow accent">MỖI NGÀY, MỘT KHỞI ĐẦU MỚI</span>
      <h1>NHỊP ĐI<br />CỦA RIÊNG<br />BẠN.</h1>
      <p>
        Từ đường chạy đến góc phố quen.<br />
        Tìm đôi Nike dành cho cách bạn chuyển động.
      </p>
      <div class="cta-row">
        <a href="index.php?page=catalog" class="btn btn-dark">Khám phá ngay ↗</a>
        <a href="index.php?page=catalog" class="btn btn-light">Xem hàng mới</a>
      </div>
    </div>

    <div class="hero-visual">
      <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80" alt="Giày Nike Air Force 1" />
      <div class="product-tag">
        <div>
          <span>MỘT BIỂU TƯỢNG. VÔ VÀN CÁCH MẶC.</span>
          <strong>Air Force 1 ’07</strong>
        </div>
        <button type="button" aria-label="Xem sản phẩm">↗</button>
      </div>
    </div>
  </section>

  <section class="benefits container">
    <article class="benefit-item">
      <div class="benefit-icon icon-truck" aria-hidden="true"></div>
      <div>
        <h3>Giao hàng toàn quốc</h3>
        <p>Miễn phí với đơn từ 2.000.000 ₫</p>
      </div>
    </article>
    <article class="benefit-item">
      <div class="benefit-icon icon-return" aria-hidden="true"></div>
      <div>
        <h3>Đổi trả trong 30 ngày</h3>
        <p>Sản phẩm chưa sử dụng, còn nguyên hộp</p>
      </div>
    </article>
    <article class="benefit-item">
      <div class="benefit-icon icon-size" aria-hidden="true"></div>
      <div>
        <h3>Tư vấn chọn size</h3>
        <p>Hỗ trợ tìm kích cỡ phù hợp với bạn</p>
      </div>
    </article>
  </section>

  <section class="category-section container">
    <div class="section-head">
      <span class="eyebrow accent">ĐÔI GIÀY CHO MỌI NHỊP SỐNG</span>
      <h2>Bạn đang tìm điều gì?</h2>
    </div>

    <div class="category-grid">
      <article class="category-card">
        <img src="https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=700&q=80" alt="Dành cho nam" />
        <div class="category-meta">
          <div>
            <h3>Dành cho nam</h3>
            <p>Từ phố đến phòng tập</p>
          </div>
          <span>↗</span>
        </div>
      </article>
      <article class="category-card">
        <img src="https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=700&q=80" alt="Dành cho nữ" />
        <div class="category-meta">
          <div>
            <h3>Dành cho nữ</h3>
            <p>Phong cách theo cách bạn</p>
          </div>
          <span>↗</span>
        </div>
      </article>
      <article class="category-card">
        <img src="https://images.unsplash.com/photo-1511556820780-d912e42b4980?auto=format&fit=crop&w=700&q=80" alt="Dành cho trẻ em" />
        <div class="category-meta">
          <div>
            <h3>Dành cho trẻ em</h3>
            <p>Sẵn sàng cho ngày mới</p>
          </div>
          <span>↗</span>
        </div>
      </article>
    </div>
  </section>

  <section class="products-section container">
    <div class="section-head with-link">
      <div>
        <span class="eyebrow accent">ĐƯỢC CHỌN CHO BẠN</span>
        <h2>Những đôi giày đáng khám phá</h2>
      </div>
      <a href="index.php?page=catalog">Xem tất cả ↗</a>
    </div>

    <div class="product-grid">
      <article class="product-card">
        <div class="image-wrap">
          <img src="https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=700&q=80" alt="Nike Air Force 1 '07" />
          <button class="wishlist" type="button" aria-label="Yêu thích">♡</button>
          <span class="chip">BIỂU TƯỢNG</span>
        </div>
        <div class="product-info">
          <h3>Nike Air Force 1 '07</h3>
          <p>Giày lifestyle nam</p>
          <small>Trắng / Trắng</small>
          <strong>2.929.000 ₫</strong>
        </div>
      </article>

      <article class="product-card">
        <div class="image-wrap">
          <img src="https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=700&q=80" alt="Nike Pegasus 41" />
          <button class="wishlist" type="button" aria-label="Yêu thích">♡</button>
          <span class="chip">CHẠY BỘ</span>
        </div>
        <div class="product-info">
          <h3>Nike Pegasus 41</h3>
          <p>Giày chạy bộ nam</p>
          <small>Bạc / Đen / Cam</small>
          <strong>3.829.000 ₫</strong>
        </div>
      </article>

      <article class="product-card">
        <div class="image-wrap">
          <img src="https://images.unsplash.com/photo-1600269452121-4f2416e55c28?auto=format&fit=crop&w=700&q=80" alt="Nike Dunk Low Retro" />
          <button class="wishlist" type="button" aria-label="Yêu thích">♡</button>
        </div>
        <div class="product-info">
          <h3>Nike Dunk Low Retro</h3>
          <p>Giày lifestyle unisex</p>
          <small>Trắng / Đen</small>
          <strong>3.239.000 ₫</strong>
        </div>
      </article>

      <article class="product-card">
        <div class="image-wrap">
          <img src="https://images.unsplash.com/photo-1607522370275-f14206abe5d3?auto=format&fit=crop&w=700&q=80" alt="Nike Vomero 18" />
          <button class="wishlist" type="button" aria-label="Yêu thích">♡</button>
          <span class="chip">MỚI</span>
        </div>
        <div class="product-info">
          <h3>Nike Vomero 18</h3>
          <p>Giày chạy bộ nữ</p>
          <small>Kem / Hồng</small>
          <strong>4.699.000 ₫</strong>
        </div>
      </article>
    </div>
  </section>

  <section class="campaign container">
    <div class="campaign-image">
      <img src="https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=1200&q=80" alt="Chạy bộ cùng giày Nike" />
    </div>
    <div class="campaign-copy">
      <span class="eyebrow accent">CHẠY BỘ · PEGASUS 41</span>
      <h2>BẮT ĐẦU<br />TỪ MỘT<br />BƯỚC CHẠY.</h2>
      <p>
        Một vòng quanh công viên hay cung đường mới.<br />
        Khám phá lựa chọn cho buổi chạy tiếp theo.
      </p>
      <a href="index.php?page=catalog" class="btn btn-dark">Khám phá giày chạy bộ</a>
    </div>
  </section>

  <section class="collection container">
    <div class="section-head with-link">
      <div>
        <span class="eyebrow accent">KHÔNG BAO GIỜ LỖI NHỊP</span>
        <h2>Những biểu tượng của phong cách</h2>
      </div>
      <a href="index.php?page=catalog">Xem bộ sưu tập ↗</a>
    </div>

    <div class="collection-grid">
      <article class="collection-card">
        <img src="https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=700&q=80" alt="Air Force 1" />
        <div class="collection-meta">
          <h3>Air Force 1</h3>
          <span>↗</span>
        </div>
      </article>
      <article class="collection-card">
        <img src="https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=700&q=80" alt="Dunk" />
        <div class="collection-meta">
          <h3>Dunk</h3>
          <span>↗</span>
        </div>
      </article>
      <article class="collection-card">
        <img src="https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=700&q=80" alt="Air Max" />
        <div class="collection-meta">
          <h3>Air Max</h3>
          <span>↗</span>
        </div>
      </article>
    </div>
  </section>

  <section class="style-banner container">
    <div class="style-copy">
      <span class="eyebrow accent">GÓC CẢM HỨNG</span>
      <h2>Một đôi giày trắng.<br />Nhiều cách là chính bạn.</h2>
      <p>
        Quần denim, đồ thể thao hay những bộ đồ tối giản.<br />
        Khám phá cách phối Air Force 1 cho ngày thường.
      </p>
      <a href="#">Xem gợi ý phối đồ ↗</a>
    </div>
    <img src="https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1200&q=80" alt="Phối đồ với giày trắng" />
  </section>

  <section class="help-grid container">
    <article class="help-box">
      <div class="help-icon icon-shoe" aria-hidden="true"></div>
      <div>
        <h3>Vừa vặn từ bước đầu tiên</h3>
        <p>Đo chiều dài bàn chân và đối chiếu bảng size EU.</p>
        <a href="#">Xem hướng dẫn chọn size ↗</a>
      </div>
    </article>
    <article class="help-box">
      <div class="help-icon icon-choice" aria-hidden="true"></div>
      <div>
        <h3>Chọn giày theo nhu cầu</h3>
        <p>Lifestyle, chạy bộ hay tập luyện — tìm đúng đôi bạn cần.</p>
        <a href="#">Khám phá các dòng giày ↗</a>
      </div>
    </article>
  </section>

  <section class="newsletter container">
    <span class="eyebrow accent">THAM GIA CỘNG ĐỒNG TUYỂN CHỌN</span>
    <h2>ĐỪNG BỎ LỠ NHỊP MỚI.</h2>
    <p>
      Lưu những đôi giày yêu thích, quản lý đơn hàng và nhận tin về<br />
      bộ sưu tập mới. Bắt đầu với tài khoản miễn phí.
    </p>
    <div class="newsletter-form">
      <input type="email" placeholder="Địa chỉ email của bạn" aria-label="Email" />
      <button type="button" class="btn btn-dark">Đăng ký nhận tin</button>
      <button type="button" class="btn btn-light">Tạo tài khoản</button>
    </div>
    <small>Đăng ký đồng nghĩa với việc đồng ý Chính sách bảo mật. Bạn có thể hủy nhận tin bất cứ lúc nào.</small>
  </section>
</main>
