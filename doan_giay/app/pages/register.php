<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>
<?php $old = pull_old('old'); ?>
<main class="page-wrap">
  <div class="auth-card">
    <?php if (is_logged_in()): ?>
      <h1>Bạn đã đăng nhập</h1>
      <p><a class="btn btn-dark" href="index.php?page=home">Về trang chủ</a></p>
    <?php else: ?>
      <h1>Tạo tài khoản</h1>
      <form method="post" action="index.php" class="form-stack">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="register">
        <label>Tên đăng nhập
          <input type="text" name="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_.]+"
                 value="<?php echo e($old['username'] ?? ''); ?>">
          <small>3-30 ký tự: chữ không dấu, số, _ hoặc .</small>
        </label>
        <label>Họ và tên
          <input type="text" name="name" required maxlength="100" value="<?php echo e($old['name'] ?? ''); ?>">
        </label>
        <label>Email
          <input type="email" name="email" required maxlength="254" value="<?php echo e($old['email'] ?? ''); ?>">
        </label>
        <label>Số điện thoại (không bắt buộc)
          <input type="tel" name="phone" maxlength="11" pattern="0[0-9]{9,10}" value="<?php echo e($old['phone'] ?? ''); ?>">
        </label>
        <label>Địa chỉ nhận hàng mặc định (không bắt buộc)
          <textarea name="address" rows="2" maxlength="500" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành..."><?php echo e($old['address'] ?? ''); ?></textarea>
          <small>Địa chỉ này sẽ được dùng mặc định khi thanh toán đơn hàng.</small>
        </label>
        <label>Mật khẩu
          <input type="password" name="password" required minlength="8" autocomplete="new-password">
          <small>Tối thiểu 8 ký tự.</small>
        </label>
        <label>Nhập lại mật khẩu
          <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
        </label>
        <button type="submit" class="btn btn-dark btn-block">Đăng ký</button>
      </form>

      <p class="auth-alt">Đã có tài khoản? <a href="index.php?page=login">Đăng nhập</a></p>
    <?php endif; ?>
  </div>
</main>
