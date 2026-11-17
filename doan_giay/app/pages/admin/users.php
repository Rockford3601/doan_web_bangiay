<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$users = db()->query('SELECT user_id, username, name, email, phone, role, status, points, created_at FROM users ORDER BY user_id LIMIT 300')->fetchAll();
$me = (int)$_SESSION['user']['user_id'];
$role_labels = ['customer' => 'Khách hàng', 'staff' => 'Nhân viên', 'admin' => 'Admin'];
?>
<main class="page-wrap wide">
  <h1>Quản trị</h1>
  <?php include __DIR__ . '/../../components/admin_nav.php'; ?>

  <div class="table-scroll">
  <table class="table">
    <thead><tr><th>ID</th><th>Tài khoản</th><th>Họ tên</th><th>Email</th><th>Điểm</th><th>Quyền</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <?php $self = ((int)$u['user_id'] === $me); $fid = 'uf' . (int)$u['user_id']; ?>
      <tr>
        <td>
          <?php echo (int)$u['user_id']; ?>
          <!-- form đặt trong ô, các ô khác liên kết bằng thuộc tính form="..." (không đặt <form> trực tiếp trong <tr>) -->
          <form id="<?php echo e($fid); ?>" method="post" action="index.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="admin_user_update">
            <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
          </form>
        </td>
        <td><?php echo e($u['username']); ?></td>
        <td><?php echo e($u['name']); ?></td>
        <td><?php echo e($u['email']); ?></td>
        <td><?php echo (int)$u['points']; ?> <small class="muted">(<?php echo e(member_tier((int)$u['points'])); ?>)</small></td>
        <td>
          <select name="role" form="<?php echo e($fid); ?>" <?php echo $self ? 'disabled' : ''; ?>>
            <?php foreach ($role_labels as $k => $label): ?>
              <option value="<?php echo e($k); ?>" <?php echo $u['role'] === $k ? 'selected' : ''; ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <select name="status" form="<?php echo e($fid); ?>" <?php echo $self ? 'disabled' : ''; ?>>
            <option value="active" <?php echo $u['status'] === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
            <option value="locked" <?php echo $u['status'] !== 'active' ? 'selected' : ''; ?>>Khóa</option>
          </select>
        </td>
        <td>
          <?php if (!$self): ?>
            <button type="submit" form="<?php echo e($fid); ?>" class="btn btn-light btn-sm">Lưu</button>
          <?php else: ?>
            <small class="muted">(bạn)</small>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</main>
