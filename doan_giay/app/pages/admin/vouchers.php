<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$pdo = db();
$edit_id = get_int('edit');
$v = ['voucher_id' => 0, 'code' => '', 'discount_type' => 'percent', 'discount_value' => '', 'min_order_value' => 0, 'max_discount' => '',
      'start_date' => date('Y-m-d H:i:s'), 'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')), 'usage_limit' => 0, 'status' => 'active'];
if ($edit_id > 0) {
    $s = $pdo->prepare('SELECT * FROM voucher WHERE voucher_id = ?');
    $s->execute([$edit_id]);
    $found = $s->fetch();
    if ($found) $v = $found;
}
$list = $pdo->query('SELECT *, (NOW() BETWEEN start_date AND end_date) AS in_time FROM voucher ORDER BY voucher_id DESC LIMIT 100')->fetchAll();
$dt = fn($x) => date('Y-m-d\TH:i', strtotime($x));
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>

  <form method="post" action="index.php" class="form-card form-stack">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="admin_voucher_save">
    <input type="hidden" name="voucher_id" value="<?php echo (int)$v['voucher_id']; ?>">
    <h2><?php echo $v['voucher_id'] ? 'Sửa voucher ' . e($v['code']) : 'Thêm voucher'; ?></h2>

    <div class="two-col">
      <label>Mã voucher
        <input type="text" name="code" required maxlength="30" pattern="[A-Za-z0-9_\-]+" value="<?php echo e($v['code']); ?>" <?php echo $v['voucher_id'] ? 'readonly' : ''; ?>>
      </label>
      <label>Loại giảm
        <select name="discount_type">
          <option value="percent" <?php echo $v['discount_type'] === 'percent' ? 'selected' : ''; ?>>Phần trăm (%)</option>
          <option value="fixed" <?php echo $v['discount_type'] === 'fixed' ? 'selected' : ''; ?>>Số tiền cố định (₫)</option>
        </select>
      </label>
    </div>
    <div class="two-col">
      <label>Giá trị giảm <input type="number" name="discount_value" required min="1" step="any" value="<?php echo e($v['discount_value']); ?>"></label>
      <label>Đơn tối thiểu (₫) <input type="number" name="min_order_value" required min="0" value="<?php echo e((int)$v['min_order_value']); ?>"></label>
    </div>
    <div class="two-col">
      <label>Giảm tối đa (₫, để trống nếu không giới hạn) <input type="number" name="max_discount" min="0" value="<?php echo e($v['max_discount'] !== null && $v['max_discount'] !== '' ? (int)$v['max_discount'] : ''); ?>"></label>
      <label>Giới hạn lượt dùng (0 = không giới hạn) <input type="number" name="usage_limit" required min="0" value="<?php echo (int)$v['usage_limit']; ?>"></label>
    </div>
    <div class="two-col">
      <label>Bắt đầu <input type="datetime-local" name="start_date" required value="<?php echo e($dt($v['start_date'])); ?>"></label>
      <label>Kết thúc <input type="datetime-local" name="end_date" required value="<?php echo e($dt($v['end_date'])); ?>"></label>
    </div>
    <label>Trạng thái
      <select name="status">
        <option value="active" <?php echo $v['status'] === 'active' ? 'selected' : ''; ?>>Bật</option>
        <option value="inactive" <?php echo $v['status'] === 'inactive' ? 'selected' : ''; ?>>Tắt</option>
      </select>
    </label>
    <div class="btn-row">
      <button type="submit" class="btn btn-dark">Lưu voucher</button>
      <?php if ($v['voucher_id']): ?><a class="btn btn-light" href="index.php?page=admin_vouchers">Thêm mới</a><?php endif; ?>
    </div>
  </form>

  <div class="table-scroll">
  <table class="table">
    <thead><tr><th>Mã</th><th>Giảm</th><th>Đơn tối thiểu</th><th>Tối đa</th><th>Hiệu lực</th><th>Đã dùng</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr>
        <td><strong><?php echo e($r['code']); ?></strong></td>
        <td><?php echo $r['discount_type'] === 'percent' ? e(rtrim(rtrim(number_format((float)$r['discount_value'], 2, '.', ''), '0'), '.')) . '%' : e(format_vnd($r['discount_value'])); ?></td>
        <td><?php echo e(format_vnd($r['min_order_value'])); ?></td>
        <td><?php echo $r['max_discount'] !== null ? e(format_vnd($r['max_discount'])) : '—'; ?></td>
        <td><?php echo e(date('d/m/Y H:i', strtotime($r['start_date']))); ?> → <?php echo e(date('d/m/Y H:i', strtotime($r['end_date']))); ?>
            <?php if (!(int)$r['in_time']): ?><span class="badge badge-cancelled">Ngoài hạn</span><?php endif; ?></td>
        <td><?php echo (int)$r['used_count']; ?> / <?php echo (int)$r['usage_limit'] > 0 ? (int)$r['usage_limit'] : '∞'; ?></td>
        <td><span class="badge <?php echo $r['status'] === 'active' ? 'badge-delivered' : 'badge-cancelled'; ?>"><?php echo $r['status'] === 'active' ? 'Bật' : 'Tắt'; ?></span></td>
        <td class="nowrap">
          <a class="btn btn-light btn-sm" href="index.php?page=admin_vouchers&edit=<?php echo (int)$r['voucher_id']; ?>">Sửa</a>
          <form method="post" action="index.php" class="inline-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="admin_voucher_toggle">
            <input type="hidden" name="voucher_id" value="<?php echo (int)$r['voucher_id']; ?>">
            <button type="submit" class="btn btn-light btn-sm"><?php echo $r['status'] === 'active' ? 'Tắt' : 'Bật'; ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</main>
