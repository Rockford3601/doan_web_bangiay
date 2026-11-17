<?php
// Hàm nghiệp vụ dùng chung cho giỏ hàng, giá, voucher, yêu thích.
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

/* ---------------- GIỎ HÀNG ---------------- */
function get_or_create_cart_id(int $user_id): int
{
    $pdo = db();
    $s = $pdo->prepare('SELECT cart_id FROM cart WHERE user_id = ? ORDER BY cart_id LIMIT 1');
    $s->execute([$user_id]);
    $id = $s->fetchColumn();
    if ($id) {
        return (int)$id;
    }
    $pdo->prepare('INSERT IGNORE INTO cart (user_id) VALUES (?)')->execute([$user_id]);
    $s->execute([$user_id]);
    return (int)$s->fetchColumn();
}

// Tổng số lượng trong giỏ (hiện ở header)
function cart_count(): int
{
    static $count = null;
    if (!is_logged_in()) {
        return 0;
    }
    if ($count === null) {
        $s = db()->prepare('SELECT COALESCE(SUM(ci.quantity), 0) FROM cart ca JOIN cart_item ci ON ci.cart_id = ca.cart_id WHERE ca.user_id = ?');
        $s->execute([(int)$_SESSION['user']['user_id']]);
        $count = (int)$s->fetchColumn();
    }
    return $count;
}

// Dòng flash sale đang chạy cho 1 variant (hoặc null)
function active_flash_item(int $variant_id): ?array
{
    $s = db()->prepare(
        "SELECT fsi.flash_sale_item_id, fsi.sale_price, fsi.quantity_limit, fsi.sold_quantity
           FROM flash_sale_item fsi
           JOIN flash_sale fs ON fs.flash_sale_id = fsi.flash_sale_id
          WHERE fsi.variant_id = ? AND fs.status = 'active'
            AND NOW() BETWEEN fs.start_time AND fs.end_time
            AND fsi.sold_quantity < fsi.quantity_limit
          ORDER BY fsi.sale_price ASC LIMIT 1"
    );
    $s->execute([$variant_id]);
    $row = $s->fetch();
    return $row ?: null;
}

// Các dòng trong giỏ, đã tính giá hiện hành (giá flash sale nếu còn) và kiểm tra tồn kho
function get_cart_lines(int $cart_id): array
{
    $s = db()->prepare(
        "SELECT ci.cart_item_id, ci.quantity,
                v.variant_id, v.size, v.color, v.sku, v.price, v.stock_quantity, v.status AS v_status,
                p.product_id, p.name, p.thumbnail, p.base_price, p.sale_price, p.status AS p_status
           FROM cart_item ci
           JOIN product_variant v ON v.variant_id = ci.variant_id
           JOIN product p ON p.product_id = v.product_id
          WHERE ci.cart_id = ?
          ORDER BY v.variant_id"
    );
    $s->execute([$cart_id]);

    $lines = [];
    foreach ($s->fetchAll() as $r) {
        $qty   = (int)$r['quantity'];
        $unit  = (float)$r['price'];
        $flash_id = null;

        $flash = active_flash_item((int)$r['variant_id']);
        // chỉ áp giá flash nếu còn đủ suất cho số lượng đang mua
        if ($flash && ((int)$flash['quantity_limit'] - (int)$flash['sold_quantity']) >= $qty) {
            $unit = (float)$flash['sale_price'];
            $flash_id = (int)$flash['flash_sale_item_id'];
        } elseif (!empty($r['sale_price']) && (float)$r['sale_price'] > 0 && (float)$r['sale_price'] < (float)$r['base_price']) {
            $baseP = (float)$r['base_price'];
            $saleP = (float)$r['sale_price'];
            $unit = ($unit == $baseP) ? $saleP : round($unit * ($saleP / $baseP));
        }

        $r['quantity']      = $qty;
        $r['regular_price'] = (float)$r['price'];
        $r['unit_price']    = $unit;
        $r['flash_item_id'] = $flash_id;
        $r['line_total']    = $unit * $qty;
        $r['available']     = ($r['p_status'] === 'active' && $r['v_status'] === 'active' && (int)$r['stock_quantity'] >= $qty);
        $lines[] = $r;
    }
    return $lines;
}

function cart_subtotal(array $lines): float
{
    $sum = 0.0;
    foreach ($lines as $l) {
        if ($l['available']) {
            $sum += $l['line_total'];
        }
    }
    return $sum;
}

function calc_totals(float $subtotal, float $discount): array
{
    $shipping = ($subtotal <= 0 || $subtotal >= FREE_SHIP_FROM) ? 0 : SHIPPING_FEE;
    return [
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'discount' => $discount,
        'final'    => max(0, $subtotal + $shipping - $discount),
    ];
}

/* ---------------- VOUCHER ---------------- */
function find_voucher(string $code, bool $lock = false): ?array
{
    $sql = 'SELECT *, (NOW() BETWEEN start_date AND end_date) AS in_time FROM voucher WHERE code = ?';
    if ($lock) {
        $sql .= ' FOR UPDATE'; // khóa dòng voucher trong transaction để 2 người không dùng quá giới hạn
    }
    $s = db()->prepare($sql);
    $s->execute([strtoupper($code)]);
    $row = $s->fetch();
    return $row ?: null;
}

// Trả về [true|false, 'thông báo']
function voucher_check(?array $v, float $subtotal, int $user_id): array
{
    if (!$v || $v['status'] !== 'active') {
        return [false, 'Mã giảm giá không tồn tại hoặc đã bị tắt.'];
    }
    if (!(int)$v['in_time']) {
        return [false, 'Mã giảm giá chưa đến ngày dùng hoặc đã hết hạn.'];
    }
    if ((int)$v['usage_limit'] > 0 && (int)$v['used_count'] >= (int)$v['usage_limit']) {
        return [false, 'Mã giảm giá đã hết lượt sử dụng.'];
    }
    if ($subtotal < (float)$v['min_order_value']) {
        return [false, 'Đơn hàng cần tối thiểu ' . format_vnd($v['min_order_value']) . ' để dùng mã này.'];
    }
    $s = db()->prepare('SELECT COUNT(*) FROM voucher_usage WHERE voucher_id = ? AND user_id = ?');
    $s->execute([(int)$v['voucher_id'], $user_id]);
    if ((int)$s->fetchColumn() > 0) {
        return [false, 'Bạn đã sử dụng mã này rồi.'];
    }
    return [true, 'OK'];
}

function voucher_discount(array $v, float $subtotal): float
{
    if ($v['discount_type'] === 'percent') {
        $d = $subtotal * (float)$v['discount_value'] / 100;
    } else {
        $d = (float)$v['discount_value'];
    }
    if ($v['max_discount'] !== null && (float)$v['max_discount'] > 0) {
        $d = min($d, (float)$v['max_discount']);
    }
    return floor(min($d, $subtotal));
}

/* ---------------- YÊU THÍCH ---------------- */
// Trả về mảng [product_id => true] của người dùng hiện tại
function favorite_ids(): array
{
    static $ids = null;
    if (!is_logged_in()) {
        return [];
    }
    if ($ids === null) {
        $s = db()->prepare('SELECT product_id FROM favorite WHERE user_id = ?');
        $s->execute([(int)$_SESSION['user']['user_id']]);
        $ids = array_fill_keys(array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN)), true);
    }
    return $ids;
}

/* ---------------- ĐÁNH GIÁ ---------------- */
// Dòng đơn hàng (đã mua, chưa đánh giá) mà người dùng có thể dùng để đánh giá sản phẩm. null nếu không đủ điều kiện.
function reviewable_detail_id(int $user_id, int $product_id): ?int
{
    $s = db()->prepare(
        "SELECT d.order_detail_id
           FROM order_detail d
           JOIN orders o ON o.order_id = d.order_id
           JOIN product_variant v ON v.variant_id = d.variant_id
          WHERE o.user_id = ? AND o.order_status != 'cancelled' AND v.product_id = ?
            AND NOT EXISTS (SELECT 1 FROM review r WHERE r.order_detail_id = d.order_detail_id)
          ORDER BY d.order_detail_id LIMIT 1"
    );
    $s->execute([$user_id, $product_id]);
    $v = $s->fetchColumn();
    return $v === false ? null : (int)$v;
}
