<?php 
if (!defined('SECURE_CHECK')) exit('Access denied'); 

// 1. Lấy danh sách 4 sản phẩm mới nhất từ CSDL
$san_pham_moi = db()->query(
    "SELECT p.product_id, p.name, p.base_price, p.sale_price, p.badge, p.thumbnail, c.name AS category_name
       FROM product p
       JOIN category c ON c.category_id = p.category_id
      WHERE p.status = 'active' AND c.status = 'active'
      ORDER BY p.product_id DESC
      LIMIT 4"
)->fetchAll();

// 2. Lấy danh sách 4 sản phẩm bán chạy / nổi bật từ CSDL
$san_pham_banchay = db()->query(
    "SELECT p.product_id, p.name, p.base_price, p.sale_price, p.badge, p.thumbnail, c.name AS category_name
       FROM product p
       JOIN category c ON c.category_id = p.category_id
      WHERE p.status = 'active' AND c.status = 'active'
      ORDER BY (p.badge = 'hot') DESC, (p.badge = 'sale') DESC, p.product_id ASC
      LIMIT 4"
)->fetchAll();

// 3. Gợi ý sản phẩm cá nhân hóa (đồng bộ với trang home)
$goi_y_sp = recommend_products(4);
?>

<main class="giaodien_main">

  <!-- === SLIDESHOW QUẢNG CÁO === -->
  <section class="noidung">
      <div class="Khung_trang"></div>
      <?php include __DIR__ . '/../components/slides_quangcao.php'; ?>
  </section>

  <hr>

  <!-- === THANH LỢI ÍCH (đồng bộ với trang home) === -->
  <section class="gd_benefits">
      <article class="gd_benefit_item">
          <i class="fa-solid fa-truck-fast gd_benefit_icon"></i>
          <div>
              <h3>Giao hàng toàn quốc</h3>
              <p>Miễn phí với đơn từ 2.000.000 ₫</p>
          </div>
      </article>
      <article class="gd_benefit_item">
          <i class="fa-solid fa-rotate-left gd_benefit_icon"></i>
          <div>
              <h3>Đổi trả trong 30 ngày</h3>
              <p>Sản phẩm chưa sử dụng, còn nguyên hộp</p>
          </div>
      </article>
      <article class="gd_benefit_item">
          <i class="fa-solid fa-ruler gd_benefit_icon"></i>
          <div>
              <h3>Tư vấn chọn size</h3>
              <p>Hỗ trợ tìm kích cỡ phù hợp với bạn</p>
          </div>
      </article>
  </section>

  <hr>
  <br>

  <!-- === DANH MỤC QUẢNG CÁO === -->
  <h1 id="text_danhmuc">BẠN ĐANG TÌM KIẾM GÌ CHO HÔM NAY?</h1>
  <div class="khung_danhmuc_qc">

      <div class="danhmuc_qc danhmuc1">
          <a href="index.php?page=bosuutap" class="danhmuc_link">
              <span>XEM TRỌN BỘ SƯU TẬP Ở ĐÂY</span>
              <i class="fa-solid fa-arrow-right"></i>
          </a>
      </div>

      <div class="danhmuc_qc danhmuc2">
          <a href="index.php?page=catalog&gender=nam" class="danhmuc_link">
              <img src="public/assets/imgs/anh_danhmuc_qc/giaynam.png" alt="giay nam">
              <span class="danhmuc_label">GIÀY NAM</span>
          </a>
      </div>

      <div class="danhmuc_qc danhmuc3">
          <a href="index.php?page=catalog&gender=nu" class="danhmuc_link">
              <img src="public/assets/imgs/anh_danhmuc_qc/giaynu.png" alt="giay nu">
              <span class="danhmuc_label">GIÀY NỮ</span>
          </a>
      </div>

      <div class="danhmuc_qc danhmuc4">
          <a href="index.php?page=catalog&gender=tre" class="danhmuc_link">
              <img src="public/assets/imgs/anh_danhmuc_qc/giaytre.png" alt="giay tre em">
              <span class="danhmuc_label">GIÀY TRẺ EM</span>
          </a>
      </div>

  </div>


  <!-- === SẢN PHẨM MỚI VỪA RA MẮT === -->
  <div class="khungchua_sp_moi">
      <div class="gd_section_header">
          <h1 id="text_danhmuc">SẢN PHẨM MỚI VỪA RA MẮT</h1>
          <a href="index.php?page=catalog&sort=newest" class="gd_xem_them">Xem tất cả ↗</a>
      </div>
      <hr>
      <div class="khung_sp_moi">
          <div class="sp_moi">
              <?php if (!empty($san_pham_moi)): ?>
                  <?php foreach ($san_pham_moi as $product): ?>
                      <?php include __DIR__ . '/../components/product_card.php'; ?>
                  <?php endforeach; ?>
              <?php else: ?>
                  <p class="thongbao_trong">Hiện chưa có sản phẩm mới nào.</p>
              <?php endif; ?>
          </div>
      </div>
  </div>


  <!-- === SẢN PHẨM BÁN CHẠY === -->
  <div class="khungchua_sp_banchay">
      <div class="gd_section_header">
          <h1 id="text_danhmuc">CÁC SẢN PHẨM BÁN CHẠY</h1>
          <a href="index.php?page=catalog" class="gd_xem_them">Xem tất cả ↗</a>
      </div>
      <hr>
      <div class="khung_sp_banchay">
          <div class="sp_banchay">
              <?php if (!empty($san_pham_banchay)): ?>
                  <?php foreach ($san_pham_banchay as $product): ?>
                      <?php include __DIR__ . '/../components/product_card.php'; ?>
                  <?php endforeach; ?>
              <?php else: ?>
                  <p class="thongbao_trong">Hiện chưa có sản phẩm bán chạy nào.</p>
              <?php endif; ?>
          </div>
      </div>
  </div>


  <!-- === VỀ CHÚNG TÔI === -->
  <br>
  <hr>
  <br>
  <div class="khung_gioithieu">
      <div class="text_gioithieu"><h2>Về chúng tôi</h2></div>
      <div class="logo_gioithieu">
          <div class="logo_box">
              <i class="fa-solid fa-shield-halved"></i>
              <strong>NIKE - VIETNAM</strong>
              <small>Chính hãng 100%</small>
          </div>
      </div>
      <div class="noidung_gioithieu">
          <p>Chào mừng bạn đến với <strong>Nike - VIETNAM</strong> &ndash; không gian chuyên cung cấp các dòng giày thể thao và thời trang thể thao phong cách. Chúng tôi cam kết mang tới cho khách hàng những sản phẩm 100% chính hãng, đa dạng kích cỡ, mẫu mã cập nhật liên tục cùng chính sách bảo hành, đổi trả linh hoạt trong 30 ngày và giao hàng tận nơi trên toàn quốc.</p>
      </div>
  </div>

</main>

<!-- === GỢI Ý SẢN PHẨM (ngoài <main>, đồng bộ với trang home) === -->
<div class="container"><?php render_recommend_section('Gợi ý cho bạn', $goi_y_sp); ?></div>
