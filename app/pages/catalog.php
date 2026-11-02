<?php
// app/pages/catalog.php
global $conn;
if (!$conn) {
    require_once __DIR__ . '/../config/database.php';
}

// 1. Nhận bộ lọc từ URL
$gender    = $_GET['gender'] ?? 'nam';
$cat_id    = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$sort      = $_GET['sort'] ?? 'newest';
$price_min = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (float)$_GET['price_min'] : null;
$price_max = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (float)$_GET['price_max'] : null;

// 2. Dựng câu lệnh SQL
$where = ["p.status = 'active'"];
if (!empty($gender)) {
    $where[] = "p.gender = '" . mysqli_real_escape_string($conn, $gender) . "'";
}
if ($cat_id > 0) {
    $where[] = "p.category_id = $cat_id";
}
if ($price_min !== null) {
    $where[] = "p.base_price >= $price_min";
}
if ($price_max !== null) {
    $where[] = "p.base_price <= $price_max";
}

$where_sql = " WHERE " . implode(" AND ", $where);

switch ($sort) {
    case 'price_asc':  $order_sql = " ORDER BY p.base_price ASC"; break;
    case 'price_desc': $order_sql = " ORDER BY p.base_price DESC"; break;
    default:           $order_sql = " ORDER BY p.product_id DESC"; break;
}

$sql = "SELECT p.product_id, p.name, p.base_price, p.thumbnail, c.name AS category_name,
               COALESCE(AVG(r.rating), 5.0) AS avg_rating
        FROM product p
        INNER JOIN category c ON p.category_id = c.category_id
        LEFT JOIN review r ON p.product_id = r.product_id
        $where_sql
        GROUP BY p.product_id
        $order_sql";

$result = mysqli_query($conn, $sql);
$categories_result = mysqli_query($conn, "SELECT * FROM category WHERE status = 'active'");
?>

<div class="khungchua_danhmuc">
    <!-- BANNER KHU VỰC TRÊN CÙNG (CAM NHẠT) -->
    <div class="banner_danhmuc">
        <h2>Banner / Tiêu đề danh mục - GIÀY <?php echo mb_strtoupper($gender, 'UTF-8'); ?></h2>
    </div>

    <!-- THANH NÚT SẮP XẾP NẰM GÓC PHẢI (MÀU ĐỎ) -->
    <div class="thanh_dieuhanh">
        <form action="index.php" method="GET" class="form_sort_top">
            <input type="hidden" name="page" value="catalog">
            <input type="hidden" name="gender" value="<?php echo htmlspecialchars($gender); ?>">
            <?php if ($cat_id > 0): ?><input type="hidden" name="cat_id" value="<?php echo $cat_id; ?>"><?php endif; ?>
            <?php if ($price_min !== null): ?><input type="hidden" name="price_min" value="<?php echo $price_min; ?>"><?php endif; ?>
            <?php if ($price_max !== null): ?><input type="hidden" name="price_max" value="<?php echo $price_max; ?>"><?php endif; ?>

            <select name="sort" class="nut_sapxep_do" onchange="this.form.submit()">
                <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>SẮP XẾP: MỚI NHẤT</option>
                <option value="price_asc" <?php echo ($sort === 'price_asc') ? 'selected' : ''; ?>>SẮP XẾP: GIÁ THẤP -> CAO</option>
                <option value="price_desc" <?php echo ($sort === 'price_desc') ? 'selected' : ''; ?>>SẮP XẾP: GIÁ CAO -> THẤP</option>
            </select>
        </form>
    </div>

    <!-- BỐ CỤC LƯỚI CHÍNH: SIDEBAR + MAIN CONTENT -->
    <div class="khung_danhmuc">
        
        <!-- SIDEBAR BỘ LỌC (MÀU XANH LÁ CÂY) -->
        <aside class="slidebar">
            <h3>Sidebar bộ lọc</h3>
            <form action="index.php" method="GET" id="filterForm">
                <input type="hidden" name="page" value="catalog">
                <input type="hidden" name="gender" value="<?php echo htmlspecialchars($gender); ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

                <!-- KHU VỰC VÀNG: DANH MỤC GIÀY -->
                <div class="box_danhmuc_vang">
                    <p><b>CHỦNG LOẠI GIÀY</b></p>
                    <label class="item_filter">
                        <input type="radio" name="cat_id" value="0" <?php echo ($cat_id === 0) ? 'checked' : ''; ?>> Tất cả danh mục
                    </label>
                    <?php if ($categories_result): ?>
                        <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                            <label class="item_filter">
                                <input type="radio" name="cat_id" value="<?php echo $cat['category_id']; ?>" 
                                    <?php echo ($cat_id == $cat['category_id']) ? 'checked' : ''; ?>> 
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </label>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <!-- KHU VỰC XANH DƯƠNG: LỌC CHI TIẾT -->
                <div class="box_sort_xanh">
                    <p><b>BỘ LỌC CHI TIẾT</b></p>
                    <div class="filter_group">
                        <label>Khoảng giá (VNĐ):</label>
                        <input type="number" name="price_min" placeholder="Từ" value="<?php echo $price_min ?? ''; ?>">
                        <input type="number" name="price_max" placeholder="Đến" value="<?php echo $price_max ?? ''; ?>">
                    </div>
                    <button type="submit" class="btn_submit_filter">LỌC SẢN PHẨM</button>
                </div>
            </form>
        </aside>

        <!-- KHU VỰC NỘI DUNG SẢN PHẨM (MÀU TEAL) -->
        <main class="noidung_danhmuc">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($product = mysqli_fetch_assoc($result)): ?>
                    <?php include __DIR__ . '/../components/product_card.php'; ?>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: white; padding: 20px;">Không có sản phẩm nào.</p>
            <?php endif; ?>
        </main>
    </div>
</div>