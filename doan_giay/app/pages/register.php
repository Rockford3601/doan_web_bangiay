<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>
<?php $old = pull_old('old'); ?>
<main class="page-wrap">
  <div class="auth-card">
    <?php if (is_logged_in()): ?>
      <h1>Bạn đã đăng nhập</h1>
      <p><a class="btn btn-dark" href="index.php?page=home">Về trang chủ</a></p>
    <?php else: ?>
      <h1>Tạo tài khoản</h1>

      <div id="registerMsg" style="display:none;margin-bottom:14px;padding:12px;border-radius:8px;font-size:14px;line-height:1.5;"></div>

      <form id="registerForm" method="post" action="index.php" class="form-stack" onsubmit="handleAjaxRegister(event)">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="register">
        <label>Tên đăng nhập
          <input type="text" name="username" id="regUsername" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_.]+"
                 value="<?php echo e($old['username'] ?? ''); ?>" onblur="checkFieldExists('username', this.value)">
          <small id="usernameFeedback">3-30 ký tự: chữ không dấu, số, _ hoặc .</small>
        </label>
        <label>Họ và tên
          <input type="text" name="name" required maxlength="100" value="<?php echo e($old['name'] ?? ''); ?>">
        </label>
        <label>Email
          <input type="email" name="email" id="regEmail" required maxlength="254" value="<?php echo e($old['email'] ?? ''); ?>"
                 onblur="checkFieldExists('email', this.value)">
          <small id="emailFeedback">Email chính xác để nhận xác nhận đơn hàng.</small>
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
        <button type="submit" id="btnRegisterSubmit" class="btn btn-dark btn-block">Đăng ký</button>
      </form>

      <p class="auth-alt">Đã có tài khoản? <a href="index.php?page=login">Đăng nhập</a></p>

      <script>
      var csrfToken = '<?php echo csrf_token(); ?>';

      // 1. AJAX: Kiểm tra xem username hoặc email đã tồn tại hay chưa ngay khi rời ô nhập (onblur)
      function checkFieldExists(field, val) {
        val = val.trim();
        if (!val) return;
        var feedbackEl = document.getElementById(field + 'Feedback');

        var fd = new FormData();
        fd.append('action', 'check_user_exists');
        fd.append('csrf_token', csrfToken);
        fd.append('field', field);
        fd.append('value', val);

        ajaxPost('index.php', fd)
          .then(function(res) {
            if (res.exists) {
              feedbackEl.style.color = '#dc2626';
              feedbackEl.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + res.message;
            } else {
              feedbackEl.style.color = '#16a34a';
              feedbackEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + (field === 'username' ? 'Tên đăng nhập khả dụng.' : 'Email hợp lệ.');
            }
          })
          .catch(function() {});
      }

      // 2. AJAX: Đăng ký tài khoản không tải lại trang
      function handleAjaxRegister(event) {
        event.preventDefault();
        var form = event.target;
        var btn  = document.getElementById('btnRegisterSubmit');
        var msg  = document.getElementById('registerMsg');

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang tạo tài khoản...';
        msg.style.display = 'none';

        var fd = new FormData(form);

        ajaxPost('index.php', fd)
          .then(function(res) {
            if (res.success) {
              msg.style.display = 'block';
              msg.style.background = '#dcfce7';
              msg.style.color = '#15803d';
              msg.style.border = '1px solid #86efac';
              msg.innerHTML = '<i class="fa-solid fa-check"></i> ' + res.message;
              showToast(res.message, 'success');
              setTimeout(function() {
                window.location.href = res.redirect || 'index.php?page=home';
              }, 1000);
            } else {
              btn.disabled = false;
              btn.textContent = 'Đăng ký';
              msg.style.display = 'block';
              msg.style.background = '#fee2e2';
              msg.style.color = '#b91c1c';
              msg.style.border = '1px solid #fca5a5';
              msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + res.message;
              showToast('Đăng ký không thành công', 'error');
            }
          })
          .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Đăng ký';
            msg.style.display = 'block';
            msg.style.background = '#fee2e2';
            msg.style.color = '#b91c1c';
            msg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Lỗi kết nối đến máy chủ.';
          });
      }
      </script>
    <?php endif; ?>
  </div>
</main>
