<?php
if (!defined('SECURE_CHECK')) exit('Access denied');
$token = get_str('token');
$valid = false;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $s = db()->prepare('SELECT COUNT(*) FROM password_reset WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()');
    $s->execute([hash('sha256', $token)]);
    $valid = ((int)$s->fetchColumn() > 0);
}
?>
<main class="page-wrap">
  <div class="auth-card">
    <?php if (!$valid): ?>
      <h1>Liên kết không hợp lệ</h1>
      <p>Liên kết đã hết hạn, đã được sử dụng hoặc không đúng.</p>
      <p><a class="btn btn-dark" href="index.php?page=forgot">Yêu cầu liên kết mới</a></p>
    <?php else: ?>
      <h1>Đặt lại mật khẩu</h1>
      <form method="post" action="index.php" class="form-stack">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="password_reset">
        <input type="hidden" name="token" value="<?php echo e($token); ?>">
        <label>Mật khẩu mới (tối thiểu 8 ký tự) <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <label>Nhập lại mật khẩu <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password"></label>
        <button type="submit" class="btn btn-dark btn-block">Đặt lại mật khẩu</button>
      </form>
    <?php endif; ?>
  </div>
</main>
