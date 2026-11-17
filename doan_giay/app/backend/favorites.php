<?php
// Thêm/bỏ sản phẩm yêu thích (bấm lần 1 = thêm, bấm lần 2 = bỏ)
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

function action_favorite_toggle(): void
{
    require_login();
    $uid = (int)$_SESSION['user']['user_id'];
    $pid = post_int('product_id');
    $return = safe_return_url(post_str('return'));

    $s = db()->prepare("SELECT COUNT(*) FROM product WHERE product_id = ? AND status = 'active'");
    $s->execute([$pid]);
    if ((int)$s->fetchColumn() === 0) {
        flash_set('error', 'Sản phẩm không tồn tại.');
        redirect($return);
    }

    $del = db()->prepare('DELETE FROM favorite WHERE user_id = ? AND product_id = ?');
    $del->execute([$uid, $pid]);
    if ($del->rowCount() === 0) {
        // chưa có -> thêm (UNIQUE(user_id, product_id) + INSERT IGNORE chống trùng)
        db()->prepare('INSERT IGNORE INTO favorite (user_id, product_id) VALUES (?, ?)')->execute([$uid, $pid]);
        flash_set('success', 'Đã thêm vào danh sách yêu thích.');
    } else {
        flash_set('info', 'Đã bỏ khỏi danh sách yêu thích.');
    }
    redirect($return);
}
