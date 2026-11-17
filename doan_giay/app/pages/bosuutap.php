<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$pdo = db();

// Lấy 4 sản phẩm mới/nổi bật để gợi ý ngay dưới các bộ sưu tập
$sqlFeatured = "SELECT p.product_id, p.name, p.base_price, p.sale_price, p.badge, p.thumbnail, c.name AS category_name,
                       AVG(r.rating) AS avg_rating
                  FROM product p
                  INNER JOIN category c ON p.category_id = c.category_id
                  LEFT JOIN review r ON r.product_id = p.product_id AND r.status = 'visible'
                 WHERE p.status = 'active' AND c.status = 'active'
                 GROUP BY p.product_id, p.name, p.base_price, p.sale_price, p.badge, p.thumbnail, c.category_id, c.name
                 ORDER BY (p.badge = 'hot' OR p.badge = 'sale') DESC, p.product_id DESC
                 LIMIT 4";
$featuredProducts = $pdo->query($sqlFeatured)->fetchAll();

// Danh sách bộ sưu tập chính
$collections = [
    [
        'title'    => 'Bộ Sưu Tập Xuân Hè 2026',
        'subtitle' => 'XUÂN HÈ COLLECTION',
        'desc'     => 'Cảm hứng từ những tia nắng đầu mùa, phối màu tươi sáng cùng chất liệu mỏng nhẹ, thoáng khí và êm ái tối đa cho từng bước chuyển động.',
        'img'      => 'public/assets/imgs/bosuutap/anh_collect_xuan.png',
        'link'     => 'index.php?page=catalog',
        'tag'      => 'MỚI RA MẮT'
    ],
    [
        'title'    => 'Hồng Pastel - Sweet Energy',
        'subtitle' => 'PASTEL EDITION',
        'desc'     => 'Gam màu hồng pastel nhẹ nhàng nhưng đầy cá tính, form dáng thanh thoát nâng niu bàn chân và tạo điểm nhấn ngọt ngào cho outfit.',
        'img'      => 'public/assets/imgs/bosuutap/giay_hong.png',
        'link'     => 'index.php?page=catalog&gender=nu',
        'tag'      => 'YÊU THÍCH'
    ],
    [
        'title'    => 'Vàng Nắng - Radiant Stride',
        'subtitle' => 'SUNSHINE RUNNER',
        'desc'     => 'Tràn ngập năng lượng bùng nổ cho ngày mới với tông vàng ấm áp, đệm đàn hồi êm ái hỗ trợ tối đa cho các buổi dạo phố và vận động.',
        'img'      => 'public/assets/imgs/bosuutap/giay_vang.png',
        'link'     => 'index.php?page=catalog',
        'tag'      => 'HOT'
    ],
];
?>

<div class="bst-container">
    <!-- Breadcrumb & Header -->
    <header class="bst-header">
        <nav class="bst-breadcrumb" aria-label="Đường dẫn trang">
            <a href="index.php">Trang chủ</a>
            <span>/</span>
            <span class="current">Bộ sưu tập</span>
        </nav>
        <div class="bst-title-wrap">
            <h1 class="bst-title">Bộ Sưu Tập 2026</h1>
            <p class="bst-subtitle">Khám phá các thiết kế theo mùa với phong cách tối giản, êm ái và tràn đầy cảm hứng.</p>
        </div>
    </header>

    <!-- Spotlight Banner -->
    <section class="bst-spotlight">
        <div class="bst-spotlight-inner">
            <div class="bst-spotlight-img">
                <img src="public/assets/imgs/bosuutap/tonghop_xuan.png" alt="Bộ sưu tập tổng hợp Xuân 2026" />
            </div>
            <div class="bst-spotlight-content">
                <span class="bst-spotlight-badge">ĐẶC QUYỀN MÙA XUÂN</span>
                <h2 class="bst-spotlight-title">Spring Edition 2026</h2>
                <p class="bst-spotlight-desc">Sự hòa quyện giữa kiểu dáng kinh điển và công nghệ êm chân tân tiến. Đa dạng sắc màu cho cả nam và nữ, sẵn sàng đồng hành cùng bạn mọi nẻo đường.</p>
                <div class="bst-spotlight-action">
                    <a href="index.php?page=catalog" class="bst-btn-primary">
                        <span>Xem toàn bộ dòng sản phẩm</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    

    <!-- Featured Products in Collection -->
    <?php if (!empty($featuredProducts)): ?>
    <section class="bst-products-section">
        <div class="bst-section-header">
            <div>
                <h2 class="bst-section-title">Sản Phẩm Trong Bộ Sưu Tập</h2>
                <p class="bst-section-desc">Những đôi giày nổi bật vừa cập bến với mức giá ưu đãi hấp dẫn.</p>
            </div>
            <a href="index.php?page=catalog" class="bst-view-all">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="bst-product-grid">
            <?php foreach ($featuredProducts as $product): ?>
                <?php include __DIR__ . '/../components/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>
