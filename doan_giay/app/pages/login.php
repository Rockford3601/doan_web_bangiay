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

      <div id="loginMsg" style="display:none;margin-bottom:14px;padding:12px;border-radius:8px;font-size:14px;"></div>

      <form id="loginForm" method="post" action="index.php" class="form-stack" autocomplete="on" onsubmit="handleAjaxLogin(event)">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="return" value="<?php echo e($return); ?>">
        <label>Tên đăng nhập hoặc email
          <input type="text" name="identifier" required maxlength="254" autofocus>
        </label>
        <label>Mật khẩu
          <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" id="btnLoginSubmit" class="btn btn-dark btn-block">Đăng nhập</button>
      </form>

      <p class="auth-alt"><a href="index.php?page=forgot">Quên mật khẩu?</a></p>
      <p class="auth-alt">Chưa có tài khoản? <a href="index.php?page=register">Đăng ký ngay</a></p>

      <script>
      // Xử lý đăng nhập bằng AJAX không tải lại trang
      function handleAjaxLogin(event) {
        event.preventDefault(); // Chặn tải lại trang mặc định của form
        var form = event.target;
        var btn  = document.getElementById('btnLoginSubmit');
        var msg  = document.getElementById('loginMsg');

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang đăng nhập...';
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
              }, 600);
            } else {
              btn.disabled = false;
              btn.textContent = 'Đăng nhập';
              msg.style.display = 'block';
              msg.style.background = '#fee2e2';
              msg.style.color = '#b91c1c';
              msg.style.border = '1px solid #fca5a5';
              msg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + res.message;
              showToast(res.message, 'error');
            }
          })
          .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Đăng nhập';
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
