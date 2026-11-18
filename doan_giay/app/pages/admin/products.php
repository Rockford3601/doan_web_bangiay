<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$rows = db()->query(
    "SELECT p.product_id, p.name, p.base_price, p.sale_price, p.badge, p.thumbnail, p.gender, p.status, c.name AS category_name,
            COALESCE(SUM(v.stock_quantity), 0) AS total_stock, COUNT(v.variant_id) AS variant_count
       FROM product p
       JOIN category c ON c.category_id = p.category_id
       LEFT JOIN product_variant v ON v.product_id = p.product_id
      GROUP BY p.product_id, p.name, p.base_price, p.sale_price, p.badge, p.thumbnail, p.gender, p.status, c.name
      ORDER BY p.product_id DESC LIMIT 300"
)->fetchAll();
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>

  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
    <a class="btn btn-dark" href="index.php?page=admin_product_form">+ Thêm sản phẩm</a>
    
    <!-- Ô tìm kiếm tức thì (Live search bằng JavaScript) -->
    <div style="position:relative; max-width:360px; width:100%;">
      <input type="search" id="productQuickSearch" placeholder="Tìm nhanh tên, ID, danh mục..." 
             style="width:100%; height:38px; padding:6px 12px 6px 34px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px;">
      <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:12px; top:12px; color:#94a3b8; font-size:14px;"></i>
    </div>
  </div>

  <div class="table-scroll">
  <table class="table" id="productsTable">
    <thead><tr><th>ID</th><th>Ảnh</th><th>Tên</th><th>Nhãn</th><th>Danh mục</th><th>Đối tượng</th><th>Giá</th><th>Biến thể</th><th>Tổng tồn</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): 
      $baseP = (float)$r['base_price'];
      $saleP = !empty($r['sale_price']) ? (float)$r['sale_price'] : 0.0;
      $hasSale = ($saleP > 0 && $saleP < $baseP);
      $pct = $hasSale ? (int)round((1 - $saleP / $baseP) * 100) : 0;
    ?>
      <tr id="productRow-<?php echo (int)$r['product_id']; ?>">
        <td><?php echo (int)$r['product_id']; ?></td>
        <td><img class="thumb-sm" src="<?php echo e(img_url($r['thumbnail'])); ?>" alt="" onerror="this.onerror=null; this.src='public/assets/imgs/placeholder.svg';"></td>
        <td><b><?php echo e($r['name']); ?></b></td>
        <td>
          <?php if ($hasSale || $r['badge'] === 'sale'): ?>
            <span class="badge badge-cancelled" style="background:#e53935;color:#fff;">Sale <?php echo $pct > 0 ? "-{$pct}%" : ''; ?></span>
          <?php elseif ($r['badge'] === 'new'): ?>
            <span class="badge badge-confirmed" style="background:#111;color:#fff;">MỚI</span>
          <?php elseif ($r['badge'] === 'hot'): ?>
            <span class="badge badge-shipping" style="background:#ff9800;color:#000;">HOT</span>
          <?php else: ?>
            <span class="muted" style="font-size:12px;">--</span>
          <?php endif; ?>
        </td>
        <td><?php echo e($r['category_name']); ?></td>
        <td><?php echo e(GENDERS[$r['gender']] ?? $r['gender']); ?></td>
        <td>
          <?php if ($hasSale): ?>
            <div style="font-weight:700;color:#e53935;"><?php echo e(format_vnd($saleP)); ?></div>
            <del style="font-size:12px;opacity:0.6;"><?php echo e(format_vnd($baseP)); ?></del>
          <?php else: ?>
            <span><?php echo e(format_vnd($baseP)); ?></span>
          <?php endif; ?>
        </td>
        <td><?php echo (int)$r['variant_count']; ?></td>
        <td><?php echo (int)$r['total_stock']; ?></td>
        <td>
          <span class="badge status-badge <?php echo $r['status'] === 'active' ? 'badge-delivered' : 'badge-cancelled'; ?>">
            <?php echo $r['status'] === 'active' ? 'Đang bán' : 'Đã ẩn'; ?>
          </span>
        </td>
        <td class="nowrap">
          <a class="btn btn-light btn-sm" href="index.php?page=admin_product_form&id=<?php echo (int)$r['product_id']; ?>">Sửa</a>
          <form method="post" action="index.php" class="inline-form ajax-toggle-form" onsubmit="handleAjaxProductToggle(event)">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="admin_product_toggle">
            <input type="hidden" name="product_id" value="<?php echo (int)$r['product_id']; ?>">
            <button type="submit" class="btn btn-light btn-sm btn-toggle-status"><?php echo $r['status'] === 'active' ? 'Ẩn' : 'Hiện'; ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</main>

<script>
// 1. AJAX: Đổi trạng thái Ẩn / Hiện sản phẩm ngay tức thì không cần tải lại toàn bộ trang
function handleAjaxProductToggle(event) {
  event.preventDefault(); // Chặn hành vi submit mặc định
  var form = event.target;
  var btn  = form.querySelector('.btn-toggle-status');
  var originalText = btn.textContent;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

  var fd = new FormData(form);

  ajaxPost('index.php', fd)
    .then(function(res) {
      btn.disabled = false;
      if (res.success) {
        showToast(res.message, 'success');
        // Tìm dòng sản phẩm trong bảng
        var row = document.getElementById('productRow-' + res.product_id);
        if (row) {
          var badge = row.querySelector('.status-badge');
          if (badge) {
            badge.className = 'badge status-badge ' + (res.status === 'active' ? 'badge-delivered' : 'badge-cancelled');
            badge.textContent = res.status_label;
          }
          btn.textContent = res.btn_text;
        }
      } else {
        btn.textContent = originalText;
        showToast(res.message || 'Lỗi đổi trạng thái', 'error');
      }
    })
    .catch(function() {
      btn.disabled = false;
      btn.textContent = originalText;
      showToast('Lỗi kết nối máy chủ', 'error');
    });
}

// 2. Tìm kiếm nhanh trên bảng sản phẩm (Client-side live filter)
document.getElementById('productQuickSearch').addEventListener('input', function() {
  var term = this.value.toLowerCase().trim();
  var rows = document.querySelectorAll('#productsTable tbody tr');
  rows.forEach(function(row) {
    var text = row.textContent.toLowerCase();
    row.style.display = text.indexOf(term) !== -1 ? '' : 'none';
  });
});
</script>
