<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>

<main class="cart-main">
  <div class="container cart-layout">
    <div class="breadcrumbs">Trang chủ / Giỏ hàng</div>
    <div class="page-head">
      <h1>Giỏ hàng của bạn</h1>
      <span>2 sản phẩm</span>
    </div>

    <div class="cart-content">
      <div class="cart-items-panel">
        <div class="promo-banner">
          <span class="promo-icon" aria-hidden="true">✓</span>
          <span>Đơn hàng của bạn được miễn phí vận chuyển.</span>
        </div>

        <article class="cart-item">
          <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80" alt="Nike Air Force 1 '07" />
          <div class="item-info">
            <div class="item-top">
              <h2>Nike Air Force 1 '07</h2>
              <strong>2.929.000 ₫</strong>
            </div>
            <div class="meta">Giày lifestyle nam</div>
            <div class="meta">Trắng / Trắng</div>
            <div class="size-row">
              <span>Size: EU 42</span>
              <button type="button">Thay đổi</button>
            </div>
            <div class="controls-row">
              <div class="qty-box" aria-label="Số lượng">
                <button type="button" aria-label="Giảm số lượng">−</button>
                <span>1</span>
                <button type="button" aria-label="Tăng số lượng">+</button>
              </div>
              <div class="item-actions">
                <button type="button" class="small-action">
                  <span class="mini-heart" aria-hidden="true"></span>
                  <span>Lưu yêu thích</span>
                </button>
                <button type="button" class="delete-btn" aria-label="Xóa sản phẩm">
                  <span aria-hidden="true">✕</span>
                </button>
              </div>
            </div>
          </div>
        </article>

        <article class="cart-item">
          <img src="https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=900&q=80" alt="Nike Pegasus 41" />
          <div class="item-info">
            <div class="item-top">
              <h2>Nike Pegasus 41</h2>
              <strong>3.829.000 ₫</strong>
            </div>
            <div class="meta">Giày chạy bộ nam</div>
            <div class="meta">Bạc / Đen / Cam</div>
            <div class="size-row">
              <span>Size: EU 42</span>
              <button type="button">Thay đổi</button>
            </div>
            <div class="controls-row">
              <div class="qty-box" aria-label="Số lượng">
                <button type="button" aria-label="Giảm số lượng">−</button>
                <span>1</span>
                <button type="button" aria-label="Tăng số lượng">+</button>
              </div>
              <div class="item-actions">
                <button type="button" class="small-action">
                  <span class="mini-heart" aria-hidden="true"></span>
                  <span>Lưu yêu thích</span>
                </button>
                <button type="button" class="delete-btn" aria-label="Xóa sản phẩm">
                  <span aria-hidden="true">✕</span>
                </button>
              </div>
            </div>
          </div>
        </article>

        <div class="cart-footer">
          <a href="index.php?page=catalog" class="continue-link">← Tiếp tục mua sắm</a>
          <span>2 sản phẩm · 2 đôi giày</span>
        </div>
      </div>

      <aside class="summary-panel" aria-label="Tóm tắt đơn hàng">
        <div class="summary-card">
          <h3>Tóm tắt đơn hàng</h3>
          <div class="coupon-box">
            <span>Nhập mã ưu đãi</span>
            <button type="button">Áp dụng</button>
          </div>
          <div class="summary-row">
            <span>Tạm tính (2 sản phẩm)</span>
            <strong>6.758.000 ₫</strong>
          </div>
          <div class="summary-row">
            <span>Phí vận chuyển</span>
            <strong>Miễn phí</strong>
          </div>
          <div class="summary-row">
            <span>Giảm giá</span>
            <strong>0 ₫</strong>
          </div>
          <div class="divider"></div>
          <div class="summary-row total-row">
            <span>Tổng cộng</span>
            <strong>6.758.000 ₫</strong>
          </div>
          <div class="vat-text">Đã bao gồm VAT</div>
          <button class="primary-btn" type="button">Thanh toán</button>
          <div class="secure-row">
            <span class="secure-icon" aria-hidden="true"></span>
            <span>Thông tin của bạn được bảo mật</span>
          </div>
        </div>

        <div class="help-box">
          <h4>Cần hỗ trợ với đơn hàng?</h4>
          <p>Gọi 1900 6868 (9:00–18:00)<br />Hoặc nhắn tin để được tư vấn chọn size.</p>
        </div>
      </aside>
    </div>

    <div class="signin-banner">
      <div class="banner-copy">
        <h3>Đôi giày mới. Trải nghiệm thuận tiện hơn.</h3>
        <p>Đăng nhập để lưu địa chỉ và theo dõi đơn hàng ở một nơi.</p>
      </div>
      <button class="secondary-btn" type="button">Đăng nhập / Đăng ký</button>
    </div>
  </div>
</main>
