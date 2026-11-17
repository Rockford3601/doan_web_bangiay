<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>
<main class="page-wrap">
  <div class="auth-card">
    <h1>Quên mật khẩu</h1>
    <p class="muted">Nhập email đã đăng ký. Chúng tôi sẽ gửi liên kết đặt lại mật khẩu (hiệu lực 30 phút).</p>
    <form method="post" action="index.php" class="form-stack">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="password_forgot">
      <label>Email <input type="email" name="email" required maxlength="254" autofocus></label>
      <button type="submit" class="btn btn-dark btn-block">Gửi liên kết</button>
    </form>
    <?php if (MAIL_MODE === 'outbox'): ?>
    <?php endif; ?>
    <p class="auth-alt"><a href="index.php?page=login">← Quay lại đăng nhập</a></p>
  </div>
</main>
