<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>
<?php $return = safe_return_url(get_str('return')); ?>
<main class="page-wrap">
  <div class="auth-card">
    <?php if (is_logged_in()): ?>
      <h1>Bạn đã đăng nhập</h1>
      <p>Xin chào <strong><?php echo e($_SESSION['user']['name']); ?></strong>.</p>
      <p><a class="btn btn-dark" href="index.php?page=home">Về trang chủ</a></p>
    <?php else: ?>
      <h1>Đăng nhập</h1>
      <form method="post" action="index.php" class="form-stack" autocomplete="on">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="return" value="<?php echo e($return); ?>">
        <label>Tên đăng nhập hoặc email
          <input type="text" name="identifier" required maxlength="254" autofocus>
        </label>
        <label>Mật khẩu
          <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn btn-dark btn-block">Đăng nhập</button>
      </form>

      <p class="auth-alt"><a href="index.php?page=forgot">Quên mật khẩu?</a></p>
      <p class="auth-alt">Chưa có tài khoản? <a href="index.php?page=register">Đăng ký ngay</a></p>
    <?php endif; ?>
  </div>
</main>
