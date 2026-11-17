<?php
// Viết đánh giá sản phẩm: người dùng đã mua sản phẩm mới được đánh giá, mỗi dòng đơn hàng đánh giá đúng 1 lần.
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

function action_review_save(): void
{
    require_login();
    $uid       = (int)$_SESSION['user']['user_id'];
    $productId = post_int('product_id');
    $rating    = post_int('rating');
    $comment   = post_str('comment');
    $back      = 'index.php?page=product&id=' . $productId;
    $isAjax    = (isset($_POST['ajax']) && $_POST['ajax'] == '1');

    if ($rating < 1 || $rating > 5) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Vui lòng chọn số sao từ 1 đến 5.']);
            exit;
        }
        flash_set('error', 'Vui lòng chọn số sao từ 1 đến 5.');
        redirect($back);
    }
    if (mb_strlen($comment) > 1000) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Nhận xét tối đa 1000 ký tự.']);
            exit;
        }
        flash_set('error', 'Nhận xét tối đa 1000 ký tự.');
        redirect($back);
    }
    $detailId = reviewable_detail_id($uid, $productId);
    if ($detailId === null) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Bạn chỉ có thể đánh giá sản phẩm đã mua (và mỗi lần mua chỉ đánh giá một lần).']);
            exit;
        }
        flash_set('error', 'Bạn chỉ có thể đánh giá sản phẩm đã mua (và mỗi lần mua chỉ đánh giá một lần).');
        redirect($back);
    }
    try {
        db()->prepare("INSERT INTO review (user_id, product_id, order_detail_id, rating, comment, status) VALUES (?, ?, ?, ?, ?, 'visible')")
            ->execute([$uid, $productId, $detailId, $rating, $comment === '' ? null : $comment]);
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Cảm ơn bạn đã đánh giá sản phẩm!']);
            exit;
        }
        flash_set('success', 'Cảm ơn bạn đã đánh giá!');
    } catch (PDOException $e) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Bạn đã đánh giá lần mua này rồi.']);
            exit;
        }
        flash_set('error', 'Bạn đã đánh giá lần mua này rồi.');
    }
    redirect($back);
}
