<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>
<!-- Chatbot tư vấn (RAG). Gọi API: index.php?api=chat -->
<button type="button" id="chatToggle" class="chat-toggle" aria-label="Mở trợ lý tư vấn">
  <i class="fa-regular fa-comments"></i> Tư vấn
</button>
<section id="chatPanel" class="chat-panel" hidden aria-label="Trợ lý tư vấn">
  <header class="chat-head">
    <strong>Trợ lý Tuyển Chọn</strong>
    <button type="button" id="chatClose" aria-label="Đóng">✕</button>
  </header>
  <div id="chatLog" class="chat-log" aria-live="polite"></div>
  <div class="chat-chips">
    <button type="button" data-q="Tư vấn size giày giúp mình">Tư vấn size</button>
    <button type="button" data-q="Chính sách đổi trả như thế nào?">Đổi trả</button>
    <button type="button" data-q="Phí vận chuyển bao nhiêu?">Phí ship</button>
  </div>
  <form id="chatForm" class="chat-form" autocomplete="off">
    <input id="chatInput" type="text" maxlength="500" placeholder="Nhập câu hỏi..." aria-label="Câu hỏi">
    <button type="submit">Gửi</button>
  </form>
</section>
<script>
(function () {
  var token = <?php echo json_encode(csrf_token()); ?>;
  var panel = document.getElementById('chatPanel'), log = document.getElementById('chatLog');
  var input = document.getElementById('chatInput'), form = document.getElementById('chatForm');
  var opened = false, busy = false;

  function add(role, text, links) {
    var d = document.createElement('div');
    d.className = 'chat-msg chat-' + role;
    d.textContent = text;                                   // textContent: không bao giờ chèn HTML từ server/người dùng
    (links || []).forEach(function (l) {
      if (typeof l.url !== 'string' || l.url.indexOf('index.php') !== 0) return;
      var a = document.createElement('a');
      a.href = l.url; a.textContent = l.title; a.className = 'chat-link';
      d.appendChild(a);
    });
    log.appendChild(d); log.scrollTop = log.scrollHeight;
    return d;
  }
  function open() {
    panel.hidden = false;
    if (!opened) { opened = true; add('bot', 'Xin chào! Mình có thể tư vấn size giày, đổi trả, phí vận chuyển, gợi ý sản phẩm và tra cứu đơn hàng (ví dụ: "đơn #12").'); }
    input.focus();
  }
  function send(text) {
    text = (text || '').trim();
    if (!text || busy) return;
    busy = true; add('user', text); input.value = '';
    var wait = add('bot', 'Đang trả lời...');
    fetch('index.php?api=chat', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-Token': token},
      body: JSON.stringify({message: text})
    }).then(function (r) { return r.json(); }).then(function (j) {
      wait.remove(); add('bot', j.answer || j.error || 'Xin lỗi, có lỗi xảy ra.', j.links);
    }).catch(function () {
      wait.remove(); add('bot', 'Xin lỗi, không kết nối được. Vui lòng thử lại.');
    }).then(function () { busy = false; });
  }
  document.getElementById('chatToggle').addEventListener('click', function () { panel.hidden ? open() : (panel.hidden = true); });
  document.getElementById('chatClose').addEventListener('click', function () { panel.hidden = true; });
  form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
  Array.prototype.forEach.call(document.querySelectorAll('.chat-chips button'), function (b) {
    b.addEventListener('click', function () { open(); send(b.getAttribute('data-q')); });
  });
})();
</script>
