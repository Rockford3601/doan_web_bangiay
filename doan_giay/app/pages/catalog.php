<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$pdo = db();

// 1. Đọc và làm sạch tham số trên URL
$gender = get_str('gender');
if (!in_array($gender, ['nam', 'nu', 'tre'], true)) {
    $gender = '';                         // rỗng = tất cả
}
$cat_id    = get_int('cat_id');
$brand_id  = get_int('brand_id');
$sort      = get_str('sort');
$q         = mb_substr(get_str('q'), 0, 100);
$price_min = (get_str('price_min') !== '' && is_numeric(get_str('price_min'))) ? (float)get_str('price_min') : null;
$price_max = (get_str('price_max') !== '' && is_numeric(get_str('price_max'))) ? (float)get_str('price_max') : null;
$pg        = max(1, get_int('pg'));

// 2. Dựng điều kiện WHERE bằng tham số (?) - KHÔNG nối dữ liệu người dùng vào câu SQL
$where  = ["p.status = 'active'", "c.status = 'active'"];
$params = [];
if ($gender !== '') {
    $where[]  = "(p.gender = ? OR p.gender = 'unisex')";
    $params[] = $gender;
}
if ($cat_id > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $cat_id;
}
if ($brand_id > 0) {
    $where[]  = 'p.brand_id = ?';
    $params[] = $brand_id;
}
if ($q !== '') {
    $where[]  = 'p.name LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($price_min !== null) {
    $where[]  = 'p.base_price >= ?';
    $params[] = $price_min;
}
if ($price_max !== null) {
    $where[]  = 'p.base_price <= ?';
    $params[] = $price_max;
}
$where_sql = ' WHERE ' . implode(' AND ', $where);

// ORDER BY không thể dùng tham số => chỉ cho phép các giá trị trong whitelist
switch ($sort) {
    case 'price_asc':  $order_sql = ' ORDER BY p.base_price ASC, p.product_id DESC';  break;
    case 'price_desc': $order_sql = ' ORDER BY p.base_price DESC, p.product_id DESC'; break;
    default:           $sort = 'newest'; $order_sql = ' ORDER BY p.product_id DESC';  break;
}

// 3. Đếm tổng để phân trang
$cnt = $pdo->prepare('SELECT COUNT(*) FROM product p INNER JOIN category c ON p.category_id = c.category_id' . $where_sql);
$cnt->execute($params);
$total       = (int)$cnt->fetchColumn();
$total_pages = max(1, (int)ceil($total / PRODUCTS_PER_PAGE));
$pg          = min($pg, $total_pages);
$offset      = ($pg - 1) * PRODUCTS_PER_PAGE;   // đã là số nguyên nên an toàn khi ghép vào LIMIT/OFFSET

$sql = 'SELECT p.product_id, p.name, p.base_price, p.sale_price, p.badge, p.thumbnail, c.name AS category_name,
               AVG(r.rating) AS avg_rating
          FROM product p
          INNER JOIN category c ON p.category_id = c.category_id
          LEFT JOIN review r ON r.product_id = p.product_id AND r.status = \'visible\'
        ' . $where_sql . '
         GROUP BY p.product_id, p.name, p.base_price, p.sale_price, p.badge, p.thumbnail, c.category_id, c.name
        ' . $order_sql . ' LIMIT ' . (int)PRODUCTS_PER_PAGE . ' OFFSET ' . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT category_id, name FROM category WHERE status = 'active' ORDER BY name")->fetchAll();
$brands     = $pdo->query("SELECT brand_id, name FROM brand WHERE status = 'active' ORDER BY name")->fetchAll();

// Tham số giữ lại khi chuyển trang / đổi sắp xếp
$keep = array_filter([
    'page' => 'catalog', 'gender' => $gender, 'cat_id' => $cat_id > 0 ? $cat_id : '', 'brand_id' => $brand_id > 0 ? $brand_id : '', 'q' => $q,
    'price_min' => $price_min ?? '', 'price_max' => $price_max ?? '', 'sort' => $sort,
], fn($v) => $v !== '');

$banner = ($gender !== '') ? 'GIÀY ' . mb_strtoupper(GENDERS[$gender], 'UTF-8') : 'TẤT CẢ GIÀY';
if ($q !== '') {
    $banner = 'KẾT QUẢ CHO "' . mb_strtoupper($q, 'UTF-8') . '"';
}
?>

<div class="khungchua_danhmuc">
    <div class="banner_danhmuc">
        <h2><?php echo e($banner); ?> </h2>
    </div>

    <div class="thanh_dieuhanh">
        <form action="index.php" method="GET" class="form_sort_top">
            <?php foreach ($keep as $k => $v): if ($k === 'sort') continue; ?>
                <input type="hidden" name="<?php echo e($k); ?>" value="<?php echo e($v); ?>">
            <?php endforeach; ?>
            <select name="sort" class="nut_sapxep_do" onchange="this.form.submit()">
                <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>SẮP XẾP: MỚI NHẤT</option>
                <option value="price_asc" <?php echo ($sort === 'price_asc') ? 'selected' : ''; ?>>SẮP XẾP: GIÁ THẤP -> CAO</option>
                <option value="price_desc" <?php echo ($sort === 'price_desc') ? 'selected' : ''; ?>>SẮP XẾP: GIÁ CAO -> THẤP</option>
            </select>
        </form>
    </div>

    <div class="khung_danhmuc">
        <aside class="slidebar">
            <h3>Bộ lọc</h3>
            <form action="index.php" method="GET" id="filterForm">
                <input type="hidden" name="page" value="catalog">
                <input type="hidden" name="gender" value="<?php echo e($gender); ?>">
                <input type="hidden" name="sort" value="<?php echo e($sort); ?>">
                <input type="hidden" name="q" value="<?php echo e($q); ?>">

                <div class="box_danhmuc_vang">
                    <p><b>CHỦNG LOẠI GIÀY</b></p>
                    <label class="item_filter">
                        <input type="radio" name="cat_id" value="0" <?php echo ($cat_id === 0) ? 'checked' : ''; ?>> Tất cả danh mục
                    </label>
                    <?php foreach ($categories as $cat): ?>
                        <label class="item_filter">
                            <input type="radio" name="cat_id" value="<?php echo (int)$cat['category_id']; ?>"
                                <?php echo ($cat_id === (int)$cat['category_id']) ? 'checked' : ''; ?>>
                            <?php echo e($cat['name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php if ($brands): ?>
                <div class="box_danhmuc_vang">
                    <p><b>THƯƠNG HIỆU</b></p>
                    <label class="item_filter">
                        <input type="radio" name="brand_id" value="0" <?php echo ($brand_id === 0) ? 'checked' : ''; ?>> Tất cả thương hiệu
                    </label>
                    <?php foreach ($brands as $b): ?>
                        <label class="item_filter">
                            <input type="radio" name="brand_id" value="<?php echo (int)$b['brand_id']; ?>" <?php echo ($brand_id === (int)$b['brand_id']) ? 'checked' : ''; ?>>
                            <?php echo e($b['name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="box_sort_xanh">
                    <div class="filter_group">
                    <div class="price-slider-wrap"></div>
                    <button type="submit" class="btn_submit_filter">Lọc sản phẩm</button>
                </div>
            </form>
        </aside>

        <div>
            <main class="noidung_danhmuc">
                <?php if ($products): ?>
                    <?php foreach ($products as $product): ?>
                        <?php include __DIR__ . '/../components/product_card.php'; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="catalog-empty">Không có sản phẩm nào phù hợp với bộ lọc.</p>
                <?php endif; ?>
            </main>

            <?php if ($total_pages > 1): ?>
            <nav class="pagination" aria-label="Phân trang">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a class="<?php echo ($i === $pg) ? 'active' : ''; ?>"
                       href="index.php?<?php echo e(http_build_query(array_merge($keep, ['pg' => $i]))); ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const sliderMin = document.getElementById('sliderMin');
    const sliderMax = document.getElementById('sliderMax');
    const labelMin = document.getElementById('priceMinLabel');
    const labelMax = document.getElementById('priceMaxLabel');
    const sliderRange = document.getElementById('sliderRange');

    if (!sliderMin || !sliderMax || !sliderRange) return;

    const minGap = 50000;
    const maxVal = parseInt(sliderMin.max, 10) || 1500000;

    function formatVND(val) {
        return new Intl.NumberFormat('vi-VN').format(val) + ' ₫';
    }

    function updateSlider() {
        let val1 = parseInt(sliderMin.value, 10);
        let val2 = parseInt(sliderMax.value, 10);

        if (val2 - val1 < minGap) {
            if (this === sliderMin) {
                sliderMin.value = Math.max(0, val2 - minGap);
                val1 = parseInt(sliderMin.value, 10);
            } else {
                sliderMax.value = Math.min(maxVal, val1 + minGap);
                val2 = parseInt(sliderMax.value, 10);
            }
        }

        const percent1 = (val1 / maxVal) * 100;
        const percent2 = (val2 / maxVal) * 100;

        sliderRange.style.left = percent1 + '%';
        sliderRange.style.width = (percent2 - percent1) + '%';

        if (labelMin) labelMin.textContent = formatVND(val1);
        if (labelMax) labelMax.textContent = formatVND(val2);
    }

    sliderMin.addEventListener('input', updateSlider);
    sliderMax.addEventListener('input', updateSlider);
    updateSlider.call(null);
})();
</script>
