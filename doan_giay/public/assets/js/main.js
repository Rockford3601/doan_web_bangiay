/**
 * main.js - Thư viện AJAX & Tiện ích tương tác giao diện người dùng
 * Được thiết kế ngắn gọn, trực quan, dễ dàng giải thích và trình bày.
 */

// 1. Hàm hiển thị thông báo Toast góc phải màn hình
function showToast(message, type) {
  type = type || 'success';
  var container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  var toast = document.createElement('div');
  toast.className = 'toast-msg ' + type;
  var icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-circle-xmark' : 'fa-circle-info');
  toast.innerHTML = '<i class="fa-solid ' + icon + '"></i><span>' + message + '</span>';
  container.appendChild(toast);

  // Tự động xóa thẻ thông báo sau 3.5 giây
  setTimeout(function () {
    toast.remove();
  }, 3600);
}

// 2. Hàm gửi yêu cầu AJAX POST đơn giản bằng Fetch API
function ajaxPost(url, data) {
  var fd;
  if (data instanceof FormData) {
    fd = data;
  } else {
    fd = new FormData();
    for (var k in data) {
      if (Object.prototype.hasOwnProperty.call(data, k)) {
        fd.append(k, data[k]);
      }
    }
  }
  // Gửi thêm cờ ajax để backend PHP nhận diện
  if (!fd.has('ajax')) {
    fd.append('ajax', '1');
  }

  return fetch(url, {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: fd
  }).then(function (response) {
    return response.json();
  });
}
