<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

$uid = (int)$_SESSION['user']['user_id'];
$orderId = get_int('id');

$s = db()->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ? AND order_status <> 'cancelled'");
$s->execute([$orderId, $uid]);
$order = $s->fetch();

if (!$order) {
    flash_set('error', 'Không tìm thấy đơn hàng cần thanh toán.');
    redirect('index.php?page=orders');
}

$isPaid = ($order['payment_status'] === 'paid');
$amount = (int)$order['final_amount'];
$memo = 'DH' . (int)$order['order_id'];
$bankId = VIETQR_BANK_ID;
$bankBin = defined('VIETQR_BANK_BIN') ? VIETQR_BANK_BIN : '970436';
$bankName = VIETQR_BANK_NAME;
$accountNo = VIETQR_ACCOUNT_NO;
$accountName = VIETQR_ACCOUNT_NAME;
$template = VIETQR_TEMPLATE;

// 1. GỌI REST API CHÍNH THỨC CỦA VIETQR (POST https://api.vietqr.io/v2/generate) QUA cURL
$qrUrl = null;
$apiSuccess = false;
if (function_exists('curl_init')) {
    $payload = json_encode([
        'accountNo'   => $accountNo,
        'accountName' => $accountName,
        'acqId'       => $bankBin,
        'amount'      => $amount,
        'addInfo'     => $memo,
        'format'      => 'text',
        'template'    => $template
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.vietqr.io/v2/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $res) {
        $apiData = json_decode($res, true);
        if (($apiData['code'] ?? '') === '00' && !empty($apiData['data']['qrDataURL'])) {
            $qrUrl = $apiData['data']['qrDataURL'];
            $apiSuccess = true;
        }
    }
}

// 2. Fallback dự phòng nếu mạng chập chờn
if (!$qrUrl) {
    $qrUrl = "https://img.vietqr.io/image/{$bankId}-{$accountNo}-{$template}.png?amount={$amount}&addInfo={$memo}&accountName=" . rawurlencode($accountName);
}
?>

<main class="page-wrap wide">
  <div class="breadcrumbs">
    <a href="index.php">Trang chủ</a> / 
    <a href="index.php?page=orders">Đơn hàng của tôi</a> / 
    <span>Thanh toán VietQR</span>
  </div>

  <div class="pay-transfer-container">
    <div class="pay-transfer-card">
      
      <!-- Cột trái: Mã QR thanh toán -->
      <div class="pay-qr-column">
        <div class="pay-qr-badge">QUÉT MÃ ĐỂ THANH TOÁN</div>
        <?php if ($apiSuccess): ?>
          <div style="font-size:11px; color:#2e7d32; font-weight:600; margin-bottom:12px; display:inline-flex; align-items:center; gap:5px; background:#e8f5e9; padding:2px 8px; border-radius:4px;">
            <i class="fa-solid fa-satellite-dish"></i> REST API VietQR: Online (200 OK)
          </div>
        <?php endif; ?>
        <div class="pay-qr-box" id="qrContainer">
          <img src="<?php echo e($qrUrl); ?>" alt="Mã VietQR Đơn #<?php echo (int)$order['order_id']; ?>" class="vietqr-img" id="vietqrImg" />
          <div class="qr-overlay-paid" id="qrPaidOverlay" style="<?php echo $isPaid ? 'display:flex;' : 'display:none;'; ?>">
            <div class="paid-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="paid-text">ĐÃ THANH TOÁN THÀNH CÔNG</div>
          </div>
        </div>
      </div>

      <!-- Cột phải: Thông tin chuyển khoản (KHÓA TOÀN BỘ - CHỈ ĐỌC) -->
      <div class="pay-info-column">
        <div class="pay-header">
          <span class="pay-badge-status" id="payStatusBadge">
            <?php if ($isPaid): ?>
              <i class="fa-solid fa-circle-check"></i> ĐÃ THANH TOÁN
            <?php else: ?>
              <span class="pulsing-dot"></span> ĐANG CHỜ THANH TOÁN...
            <?php endif; ?>
          </span>
          <h1 class="pay-title">Đơn hàng #<?php echo (int)$order['order_id']; ?></h1>
          <p class="pay-amount-highlight"><?php echo e(format_vnd($order['final_amount'])); ?></p>
        </div>

       

        <!-- Bảng chi tiết chuyển khoản chỉ đọc có nút sao chép -->
        <div class="pay-fields-list">
          <div class="pay-field-item">
            <div class="pay-field-label">Ngân hàng thụ hưởng</div>
            <div class="pay-field-val">
              <strong><?php echo e($bankName); ?></strong>
            </div>
          </div>

          <div class="pay-field-item">
            <div class="pay-field-label">Số tài khoản</div>
            <div class="pay-field-row">
              <span class="pay-field-highlight" id="valAccountNo"><?php echo e($accountNo); ?></span>
              
            </div>
          </div>

          <div class="pay-field-item">
            <div class="pay-field-label">Tên chủ tài khoản</div>
            <div class="pay-field-val">
              <strong><?php echo e($accountName); ?></strong>
            </div>
          </div>

          <div class="pay-field-item">
            <div class="pay-field-label">Số tiền chính xác</div>
            <div class="pay-field-row">
              <span class="pay-field-highlight text-accent" id="valAmount"><?php echo e(number_format($amount, 0, ',', '.')); ?> ₫</span>
              
            </div>
          </div>

          <div class="pay-field-item">
            <div class="pay-field-label">Nội dung chuyển khoản</div>
            <div class="pay-field-row">
              <span class="pay-field-highlight text-code" id="valMemo"><?php echo e($memo); ?></span>
            </div>
          </div>
        </div>

      

      

          <div class="pay-links">
            <a href="index.php?page=orders" class="btn-link-muted">← Quay lại danh sách đơn hàng</a>
          </div>
        </div>

      </div>

    </div>
  </div>
</main>

<style>
.pay-transfer-container {
    max-width: 960px;
    margin: 20px auto 40px;
}
.pay-transfer-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 14px;
    display: grid;
    grid-template-columns: 380px 1fr;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,0.05);
}
.pay-qr-column {
    background: #fbfbfb;
    border-right: 1px solid #e5e5e5;
    padding: 36px 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}
.pay-qr-badge {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.2px;
    background: #111;
    color: #fff;
    padding: 5px 14px;
    border-radius: 20px;
    margin-bottom: 18px;
}
.pay-qr-box {
    position: relative;
    background: #fff;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid #e5e5e5;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    width: 270px;
    height: 330px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.vietqr-img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 6px;
}
.qr-overlay-paid {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.paid-icon {
    font-size: 52px;
    color: #2e7d32;
}
.paid-text {
    font-size: 14px;
    font-weight: 800;
    color: #2e7d32;
    letter-spacing: 0.5px;
}
.pay-qr-hint {
    margin-top: 18px;
    font-size: 12.5px;
    color: #727272;
    line-height: 1.5;
}

.pay-info-column {
    padding: 36px 36px;
    display: flex;
    flex-direction: column;
}
.pay-header {
    margin-bottom: 20px;
}
.pay-badge-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.5px;
    padding: 4px 10px;
    border-radius: 6px;
    background: #fff3e0;
    color: #e65100;
    margin-bottom: 10px;
}
.pay-badge-status.is-paid {
    background: #e8f5e9;
    color: #2e7d32;
}
.pulsing-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #e65100;
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0% { transform: scale(0.9); opacity: 0.7; }
    50% { transform: scale(1.3); opacity: 1; }
    100% { transform: scale(0.9); opacity: 0.7; }
}
.pay-title {
    margin: 0 0 6px;
    font-size: 22px;
    font-weight: 800;
    color: #111;
}
.pay-amount-highlight {
    margin: 0;
    font-size: 24px;
    font-weight: 800;
    color: #c74721;
}
.pay-security-notice {
    background: #f7f7f7;
    border: 1px dashed #d5d5d5;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 12px;
    color: #555;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 22px;
}
.pay-fields-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 24px;
}
.pay-field-item {
    background: #fff;
    border: 1px solid #ebebeb;
    border-radius: 8px;
    padding: 10px 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.pay-field-label {
    font-size: 12px;
    color: #727272;
    font-weight: 500;
}
.pay-field-row {
    display: flex;
    align-items: center;
    gap: 10px;
}
.pay-field-highlight {
    font-size: 14.5px;
    font-weight: 700;
    color: #111;
}
.pay-field-highlight.text-accent {
    color: #c74721;
}
.pay-field-highlight.text-code {
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 15px;
    letter-spacing: 0.5px;
}
.btn-copy {
    background: #f5f5f5;
    border: 1px solid #ddd;
    color: #333;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-copy:hover {
    background: #111;
    color: #fff;
    border-color: #111;
}
.auto-sync-status {
    font-size: 12.5px;
    color: #727272;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}
.demo-simulate-box {
    background: #f4f8ff;
    border: 1px solid #d0e1fd;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 16px;
}
.demo-label {
    margin: 0 0 8px;
    font-size: 11.5px;
    font-weight: 700;
    color: #1a56db;
    text-transform: uppercase;
}
.btn-simulate {
    width: 100%;
    background: #1a56db;
    color: #fff;
    border: none;
    padding: 9px 14px;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-simulate:hover {
    background: #1342ad;
}
.pay-links {
    margin-top: 10px;
}
.btn-link-muted {
    font-size: 13px;
    color: #727272;
    text-decoration: none;
    transition: color 0.15s;
}
.btn-link-muted:hover {
    color: #111;
}

@media (max-width: 820px) {
    .pay-transfer-card {
        grid-template-columns: 1fr;
    }
    .pay-qr-column {
        border-right: none;
        border-bottom: 1px solid #e5e5e5;
        padding: 24px 16px;
    }
    .pay-info-column {
        padding: 24px 18px;
    }
    .pay-field-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    .pay-field-row {
        width: 100%;
        justify-content: space-between;
    }
}
</style>

<script>
function copyText(elementId, btn) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const text = el.innerText.trim();
    navigator.clipboard.writeText(text).then(() => {
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Đã sao chép';
        setTimeout(() => { btn.innerHTML = oldHtml; }, 2000);
    });
}

function copyRaw(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Đã sao chép';
        setTimeout(() => { btn.innerHTML = oldHtml; }, 2000);
    });
}

// Polling tự động kiểm tra trạng thái đơn hàng mỗi 2.5s
(function() {
    const orderId = <?php echo (int)$order['order_id']; ?>;
    let isAlreadyPaid = <?php echo $isPaid ? 'true' : 'false'; ?>;
    
    if (isAlreadyPaid) {
        document.getElementById('syncStatus').innerHTML = '<i class="fa-solid fa-circle-check" style="color:#2e7d32"></i> Đơn hàng đã được thanh toán thành công!';
        return;
    }

    let pollInterval = setInterval(checkPaymentStatus, 2500);

    function checkPaymentStatus() {
        fetch('index.php?api=check_payment&order_id=' + orderId)
            .then(res => res.json())
            .then(data => {
                if (data && data.paid) {
                    clearInterval(pollInterval);
                    handlePaymentSuccess();
                }
            })
            .catch(err => console.log('Poll check error:', err));
    }

    function handlePaymentSuccess() {
        const badge = document.getElementById('payStatusBadge');
        if (badge) {
            badge.className = 'pay-badge-status is-paid';
            badge.innerHTML = '<i class="fa-solid fa-circle-check"></i> ĐÃ THANH TOÁN THÀNH CÔNG';
        }
        const overlay = document.getElementById('qrPaidOverlay');
        if (overlay) overlay.style.display = 'flex';
        
        const syncStatus = document.getElementById('syncStatus');
        if (syncStatus) {
            syncStatus.innerHTML = '<span style="color:#2e7d32; font-weight:700;"><i class="fa-solid fa-circle-check"></i> Hệ thống đã nhận được tiền! Đang chuyển hướng trong giây lát...</span>';
        }

        // Tự động chuyển hướng sau 2 giây
        setTimeout(() => {
            window.location.href = 'index.php?page=order_success&id=' + orderId;
        }, 2200);
    }

    // Đưa hàm simulatePaymentSuccess ra window để nút demo bấm được
    window.simulatePaymentSuccess = function() {
        const btn = document.getElementById('btnSimulatePay');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý giao dịch...';
        }

        fetch('index.php?api=simulate_pay&order_id=' + orderId, { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    handlePaymentSuccess();
                } else {
                    alert(data.error || 'Có lỗi xảy ra');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Mô phỏng khách quét & thanh toán thành công';
                    }
                }
            })
            .catch(err => {
                alert('Lỗi kết nối API');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Mô phỏng khách quét & thanh toán thành công';
                }
            });
    };
})();
</script>
