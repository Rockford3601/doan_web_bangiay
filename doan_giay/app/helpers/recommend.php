<?php
// GỢI Ý SẢN PHẨM (thuật toán chấm điểm, không cần mô hình AI bên ngoài). Kết hợp:
//  - Item-to-item: sản phẩm hay được mua CHUNG với sản phẩm đang xem (collaborative filtering đơn giản)
//  - Content-based: cùng danh mục, cùng đối tượng (nam/nữ/trẻ em)
//  - Cá nhân hóa: danh mục người dùng đã yêu thích/đã mua
//  - Độ phổ biến: số đôi đã bán (không tính đơn hủy)
// Chỉ gợi ý sản phẩm đang bán và còn hàng; không gợi ý lại thứ người dùng đã mua/đã yêu thích.
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

function recommend_products(int $limit = 4, ?int $productId = null): array
{
    $pdo = db();
    $cand = $pdo->query(
        "SELECT p.product_id, p.name, p.base_price, p.sale_price, p.badge, p.thumbnail, p.category_id, p.gender, c.name AS category_name, COALESCE(s.sold, 0) AS sold
           FROM product p
           JOIN category c ON c.category_id = p.category_id
           LEFT JOIN (SELECT v.product_id, SUM(d.quantity) AS sold
                        FROM order_detail d
                        JOIN product_variant v ON v.variant_id = d.variant_id
                        JOIN orders o ON o.order_id = d.order_id AND o.order_status <> 'cancelled'
                       GROUP BY v.product_id) s ON s.product_id = p.product_id
          WHERE p.status = 'active' AND c.status = 'active'
            AND EXISTS (SELECT 1 FROM product_variant v2 WHERE v2.product_id = p.product_id AND v2.status = 'active' AND v2.stock_quantity > 0)
          LIMIT 500"
    )->fetchAll();

    $baseCat = null;
    $baseGender = null;
    $co = [];
    if ($productId !== null) {
        $b = $pdo->prepare('SELECT category_id, gender FROM product WHERE product_id = ?');
        $b->execute([$productId]);
        if ($row = $b->fetch()) {
            $baseCat = (int)$row['category_id'];
            $baseGender = $row['gender'];
        }
        $q = $pdo->prepare(
            "SELECT v2.product_id, COUNT(DISTINCT d1.order_id) AS c
               FROM order_detail d1
               JOIN product_variant v1 ON v1.variant_id = d1.variant_id
               JOIN orders o ON o.order_id = d1.order_id AND o.order_status <> 'cancelled'
               JOIN order_detail d2 ON d2.order_id = d1.order_id
               JOIN product_variant v2 ON v2.variant_id = d2.variant_id
              WHERE v1.product_id = ? AND v2.product_id <> ?
              GROUP BY v2.product_id"
        );
        $q->execute([$productId, $productId]);
        $co = $q->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    $catPref = [];
    $exclude = [];
    if ($productId !== null) {
        $exclude[$productId] = true;
    }
    if (is_logged_in()) {
        $uid = (int)$_SESSION['user']['user_id'];
        $f = $pdo->prepare('SELECT p.product_id, p.category_id FROM favorite f JOIN product p ON p.product_id = f.product_id WHERE f.user_id = ?');
        $f->execute([$uid]);
        foreach ($f->fetchAll() as $r) {
            $catPref[$r['category_id']] = ($catPref[$r['category_id']] ?? 0) + 1;
            $exclude[(int)$r['product_id']] = true;
        }
        $o = $pdo->prepare(
            "SELECT DISTINCT p.product_id, p.category_id FROM orders od
               JOIN order_detail d ON d.order_id = od.order_id
               JOIN product_variant v ON v.variant_id = d.variant_id
               JOIN product p ON p.product_id = v.product_id
              WHERE od.user_id = ? AND od.order_status <> 'cancelled'"
        );
        $o->execute([$uid]);
        foreach ($o->fetchAll() as $r) {
            $catPref[$r['category_id']] = ($catPref[$r['category_id']] ?? 0) + 1;
            $exclude[(int)$r['product_id']] = true;
        }
    }

    $scored = [];
    foreach ($cand as $p) {
        $pid = (int)$p['product_id'];
        if (isset($exclude[$pid])) continue;
        $score = 0.0;
        $score += 5 * (int)($co[$pid] ?? 0);
        if ($baseCat !== null && (int)$p['category_id'] === $baseCat) $score += 2;
        if ($baseGender !== null && ($p['gender'] === $baseGender || $p['gender'] === 'unisex')) $score += 1;
        $score += 1.5 * min(3, (int)($catPref[$p['category_id']] ?? 0));
        $score += 0.1 * min(20, (int)$p['sold']);
        $p['score'] = $score;
        $scored[] = $p;
    }
    usort($scored, fn($a, $b) => ($b['score'] <=> $a['score']) ?: ((int)$a['product_id'] <=> (int)$b['product_id']));
    return array_slice($scored, 0, $limit);
}

function render_recommend_section(string $title, array $items): void
{
    if (!$items) {
        return;
    }
    echo '<section class="rec-section"><h2>' . e($title) . '</h2><div class="rec-grid">';
    foreach ($items as $p) {
        $url = 'index.php?page=product&id=' . (int)$p['product_id'];
        $baseP = (float)($p['base_price'] ?? 0);
        $saleP = !empty($p['sale_price']) ? (float)$p['sale_price'] : 0.0;
        $isSale = ($saleP > 0 && $saleP < $baseP);
        $pct = $isSale ? (int)round((1 - $saleP / $baseP) * 100) : 0;
        $badge = (string)($p['badge'] ?? 'none');

        $badgeHtml = '';
        if ($isSale || $badge === 'sale') {
            $badgeHtml = '<span class="badge-tag badge-tag-sale" style="position:absolute;top:8px;left:8px;font-size:10px;padding:3px 6px;">Sale ' . ($pct > 0 ? "-{$pct}%" : '') . '</span>';
        } elseif ($badge === 'new') {
            $badgeHtml = '<span class="badge-tag badge-tag-new" style="position:absolute;top:8px;left:8px;font-size:10px;padding:3px 6px;">MỚI</span>';
        } elseif ($badge === 'hot') {
            $badgeHtml = '<span class="badge-tag badge-tag-hot" style="position:absolute;top:8px;left:8px;font-size:10px;padding:3px 6px;">HOT</span>';
        }

        echo '<a class="rec-card" href="' . e($url) . '" style="position:relative;">'
           . $badgeHtml
           . '<img src="' . e(img_url($p['thumbnail'] ?? '')) . '" alt="' . e($p['name']) . '" onerror="this.onerror=null; this.src=\'public/assets/imgs/placeholder.svg\';">'
           . '<small>' . e($p['category_name']) . '</small>'
           . '<strong>' . e($p['name']) . '</strong>';
        if ($isSale) {
            echo '<div style="display:flex;align-items:baseline;gap:6px;">'
               . '<span style="color:#e53935;font-weight:700;">' . e(format_vnd($saleP)) . '</span>'
               . '<del style="font-size:12px;opacity:0.6;">' . e(format_vnd($baseP)) . '</del>'
               . '</div>';
        } else {
            echo '<span>' . e(format_vnd($baseP)) . '</span>';
        }
        echo '</a>';
    }
    echo '</div></section>';
}
