<?php
// Hành động của khu vực quản trị. MỌI hàm đều gọi require_admin() trước tiên.
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}
require_once __DIR__ . '/orders.php';   // change_order_status(), mark_order_paid(), log_stock()
require_once __DIR__ . '/returns.php';  // process_return()

/* ---------- Upload ảnh sản phẩm (kiểm tra kỹ: không tin tên file/đuôi file người dùng gửi) ---------- */
function save_product_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // không chọn ảnh mới
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new BusinessException('Tải ảnh lên thất bại (mã lỗi ' . (int)$file['error'] . ').');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new BusinessException('Ảnh tối đa 2MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);   // đọc loại file thật từ nội dung
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($file['tmp_name']) === false) {
        throw new BusinessException('Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP hợp lệ.');
    }
    $dir = __DIR__ . '/../../public/uploads/products';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new BusinessException('Không tạo được thư mục lưu ảnh.');
    }
    $newName = bin2hex(random_bytes(8)) . '.' . $ext;                   // tên ngẫu nhiên, đuôi do mình quyết định
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $newName)) {
        throw new BusinessException('Không lưu được ảnh.');
    }
    return 'uploads/products/' . $newName;
}

// Chuẩn hóa $_FILES['gallery'] (nhiều file) thành mảng các file riêng lẻ, bỏ qua ô không chọn file
function normalize_uploaded_files($f): array
{
    if (!is_array($f) || !isset($f['name'])) {
        return [];
    }
    $out = [];
    if (is_array($f['name'])) {
        foreach ($f['name'] as $i => $_) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i] ?? 0];
        }
    } elseif (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $out[] = $f;
    }
    return $out;
}

// Xóa file ảnh do hệ thống tạo ra (chỉ nhận đúng định dạng tên mà save_product_image() sinh ra => không thể xóa file lạ)
function remove_upload_file(string $rel): void
{
    if (preg_match('#^uploads/products/[a-f0-9]{16}\.(jpg|png|webp)$#', $rel)) {
        @unlink(__DIR__ . '/../../public/' . $rel);
    }
}

function cleanup_uploads(array $paths): void
{
    foreach ($paths as $p) {
        remove_upload_file((string)$p);
    }
}

/* ---------- Sản phẩm + biến thể (size/màu/kho) ---------- */
function action_admin_product_save(): void
{
    require_staff();
    $pdo = db();
    $adminId = (int)$_SESSION['user']['user_id'];

    $id     = post_int('product_id');
    $cat    = post_int('category_id');
    $name   = post_str('name');
    $desc   = post_str('description');
    $price  = post_str('base_price');
    $gender = post_str('gender');
    $status = post_str('status');
    $brand  = post_int('brand_id');   // 0 = chưa chọn thương hiệu
    $badge  = post_str('badge');
    if (!in_array($badge, ['none', 'new', 'sale', 'hot'], true)) {
        $badge = 'none';
    }
    $salePriceRaw = trim((string)($_POST['sale_price'] ?? ''));
    $salePriceVal = null;
    $back   = 'index.php?page=admin_product_form' . ($id > 0 ? '&id=' . $id : '');

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 200)          $errors[] = 'Tên sản phẩm phải từ 2 đến 200 ký tự.';
    if (!is_numeric($price) || $price < 0 || $price > 1000000000) $errors[] = 'Giá cơ bản không hợp lệ.';
    if ($salePriceRaw !== '') {
        if (!is_numeric($salePriceRaw) || (float)$salePriceRaw < 0) {
            $errors[] = 'Giá khuyến mãi không hợp lệ.';
        } elseif (is_numeric($price) && (float)$salePriceRaw >= (float)$price && (float)$price > 0) {
            $errors[] = 'Giá khuyến mãi phải nhỏ hơn giá cơ bản.';
        } else {
            $salePriceVal = (float)$salePriceRaw;
            if ($badge === 'none') {
                $badge = 'sale'; // tự động gắn nhãn sale khi nhập giá khuyến mãi
            }
        }
    }
    if (!array_key_exists($gender, GENDERS))                      $errors[] = 'Đối tượng (nam/nữ/trẻ em/unisex) không hợp lệ.';
    if (!in_array($status, ['active', 'inactive'], true))         $errors[] = 'Trạng thái không hợp lệ.';
    if ($brand > 0) {
        $b = $pdo->prepare('SELECT COUNT(*) FROM brand WHERE brand_id = ?');
        $b->execute([$brand]);
        if ((int)$b->fetchColumn() === 0)                         $errors[] = 'Thương hiệu không tồn tại.';
    }
    $brandVal = $brand > 0 ? $brand : null;
    $c = $pdo->prepare('SELECT COUNT(*) FROM category WHERE category_id = ?');
    $c->execute([$cat]);
    if ((int)$c->fetchColumn() === 0)                             $errors[] = 'Danh mục không tồn tại.';

    // Kiểm tra từng dòng biến thể
    $variants = [];
    $rawRows = (isset($_POST['variants']) && is_array($_POST['variants'])) ? $_POST['variants'] : [];
    foreach ($rawRows as $i => $r) {
        if (!is_array($r)) continue;
        $size = trim((string)($r['size'] ?? ''));
        $color = trim((string)($r['color'] ?? ''));
        $sku = trim((string)($r['sku'] ?? ''));
        if ($size === '' && $color === '' && $sku === '') continue;   // dòng trống: bỏ qua
        $vp = trim((string)($r['price'] ?? ''));
        $vs = trim((string)($r['stock_quantity'] ?? ''));
        $vst = (string)($r['status'] ?? 'active');
        $line = 'Biến thể dòng ' . ((int)$i + 1) . ': ';
        if ($size === '' || mb_strlen($size) > 20)                  $errors[] = $line . 'size không hợp lệ.';
        if ($color === '' || mb_strlen($color) > 50)                $errors[] = $line . 'màu không hợp lệ.';
        if (!preg_match('/^[A-Za-z0-9_\-]{2,50}$/', $sku))          $errors[] = $line . 'SKU gồm 2-50 ký tự chữ/số/gạch.';
        if (!is_numeric($vp) || $vp < 0)                            $errors[] = $line . 'giá không hợp lệ.';
        if (!ctype_digit($vs))                                      $errors[] = $line . 'tồn kho phải là số nguyên >= 0.';
        if (!in_array($vst, ['active', 'inactive'], true))          $errors[] = $line . 'trạng thái không hợp lệ.';
        $variants[] = ['id' => (int)($r['variant_id'] ?? 0), 'size' => $size, 'color' => $color, 'sku' => $sku,
                       'price' => $vp, 'stock' => (int)$vs, 'status' => $vst];
    }

    // Kiểm tra trùng lặp SKU và cặp Màu - Size giữa các dòng và trong toàn bộ hệ thống
    $seenSkus = [];
    $seenPairs = [];
    foreach ($variants as $idx => $v) {
        $skuUpper = strtoupper($v['sku']);
        if (isset($seenSkus[$skuUpper])) {
            $errors[] = 'Mã SKU "' . $v['sku'] . '" bị trùng lặp giữa các dòng biến thể!';
        }
        $seenSkus[$skuUpper] = true;

        $pairKey = mb_strtolower($v['color']) . '|' . mb_strtolower($v['size']);
        if (isset($seenPairs[$pairKey])) {
            $errors[] = 'Biến thể cặp Màu "' . $v['color'] . '" và Size "' . $v['size'] . '" bị trùng lặp!';
        }
        $seenPairs[$pairKey] = true;

        // Kiểm tra SKU với CSDL (tránh trùng với sản phẩm khác)
        $chkSku = $pdo->prepare('SELECT COUNT(*) FROM product_variant WHERE sku = ? AND variant_id != ?');
        $chkSku->execute([$v['sku'], $v['id']]);
        if ((int)$chkSku->fetchColumn() > 0) {
            $errors[] = 'Mã SKU "' . $v['sku'] . '" đã tồn tại ở sản phẩm khác trong hệ thống. Hãy chọn mã SKU khác!';
        }
    }

    if ($errors) {
        foreach ($errors as $e) flash_set('error', $e);
        redirect($back);
    }

    // ----- Thư viện ảnh xem thêm: thứ tự, xóa, đặt làm ảnh đại diện, tải ảnh mới -----
    $imgSort   = (isset($_POST['img_sort']) && is_array($_POST['img_sort'])) ? $_POST['img_sort'] : [];
    $imgDelete = (isset($_POST['img_delete']) && is_array($_POST['img_delete'])) ? array_values(array_filter(array_map('intval', $_POST['img_delete']))) : [];
    $makeCover = post_int('make_cover');
    $newFiles  = normalize_uploaded_files($_FILES['gallery'] ?? null);
    if (count($newFiles) > 6) {
        flash_set('error', 'Mỗi lần chỉ tải lên tối đa 6 ảnh xem thêm.');
        redirect($back);
    }

    $uploaded = [];      // ảnh mới đã lưu vào đĩa (xóa lại nếu giao dịch lỗi)
    $unlinkAfter = [];   // ảnh cũ bị xóa khỏi thư viện (xóa file sau khi commit)
    $galleryNew = [];
    $newImage = null;
    try {
        $newImage = save_product_image($_FILES['thumbnail'] ?? []);
        if ($newImage !== null) $uploaded[] = $newImage;
        foreach ($newFiles as $f) {
            $path = save_product_image($f);
            if ($path !== null) { $uploaded[] = $path; $galleryNew[] = $path; }
        }
        $pdo->beginTransaction();

        if ($id > 0) {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM product WHERE product_id = ?');
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() === 0) throw new BusinessException('Sản phẩm không tồn tại.');
            $pdo->prepare('UPDATE product SET category_id=?, name=?, description=?, base_price=?, gender=?, status=?, brand_id=?, badge=?, sale_price=? WHERE product_id=?')
                ->execute([$cat, $name, $desc, $price, $gender, $status, $brandVal, $badge, $salePriceVal, $id]);
        } else {
            $pdo->prepare('INSERT INTO product (category_id, name, description, base_price, gender, status, brand_id, badge, sale_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$cat, $name, $desc, $price, $gender, $status, $brandVal, $badge, $salePriceVal]);
            $id = (int)$pdo->lastInsertId();
        }
        if ($newImage !== null) {
            $pdo->prepare('UPDATE product SET thumbnail = ? WHERE product_id = ?')->execute([$newImage, $id]);
        }

        // Xóa các biến thể admin đánh dấu xóa
        $delVarIds = (isset($_POST['delete_variant_ids']) && is_array($_POST['delete_variant_ids']))
            ? array_filter(array_map('intval', $_POST['delete_variant_ids']), fn($x) => $x > 0) : [];
        foreach ($delVarIds as $dvid) {
            $chkOrd = $pdo->prepare('SELECT COUNT(*) FROM order_detail WHERE variant_id = ?');
            $chkOrd->execute([$dvid]);
            if ((int)$chkOrd->fetchColumn() > 0) {
                // Đã có trong đơn hàng: ngừng bán (inactive) để bảo toàn dữ liệu
                $pdo->prepare("UPDATE product_variant SET status = 'inactive' WHERE variant_id = ? AND product_id = ?")
                    ->execute([$dvid, $id]);
            } else {
                // Chưa có đơn hàng: xóa an toàn
                $pdo->prepare('DELETE FROM product_variant WHERE variant_id = ? AND product_id = ?')
                    ->execute([$dvid, $id]);
            }
        }

        foreach ($variants as $v) {
            if ($v['id'] > 0) {
                // Khóa dòng để đọc tồn kho cũ chính xác rồi mới ghi nhật ký chênh lệch
                $o = $pdo->prepare('SELECT stock_quantity FROM product_variant WHERE variant_id = ? AND product_id = ? FOR UPDATE');
                $o->execute([$v['id'], $id]);
                $old = $o->fetchColumn();
                if ($old === false) throw new BusinessException('Biến thể không thuộc sản phẩm này.');
                $pdo->prepare('UPDATE product_variant SET size=?, color=?, sku=?, price=?, stock_quantity=?, status=? WHERE variant_id=?')
                    ->execute([$v['size'], $v['color'], $v['sku'], $v['price'], $v['stock'], $v['status'], $v['id']]);
                if ($v['stock'] !== (int)$old) {
                    log_stock($pdo, $v['id'], $v['stock'] - (int)$old, 'admin_adjust', null, $adminId);
                }
            } else {
                $pdo->prepare('INSERT INTO product_variant (product_id, size, color, sku, price, stock_quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$id, $v['size'], $v['color'], $v['sku'], $v['price'], $v['stock'], $v['status']]);
                if ($v['stock'] > 0) {
                    log_stock($pdo, (int)$pdo->lastInsertId(), $v['stock'], 'admin_init', null, $adminId);
                }
            }

            // Tự động bổ sung màu sắc và size vào bảng color/size_option nếu chưa có
            if ($v['color'] !== '') {
                $pdo->prepare('INSERT IGNORE INTO color (name, status) VALUES (?, "active")')->execute([$v['color']]);
            }
            if ($v['size'] !== '') {
                $pdo->prepare('INSERT IGNORE INTO size_option (name, status) VALUES (?, "active")')->execute([$v['size']]);
            }
        }

        // ----- Thư viện ảnh xem thêm -----
        foreach ($imgDelete as $imgId) {                                   // 1. xóa
            $q = $pdo->prepare('SELECT image_url FROM product_image WHERE image_id = ? AND product_id = ?');
            $q->execute([$imgId, $id]);
            $url = $q->fetchColumn();
            if ($url !== false) {
                $pdo->prepare('DELETE FROM product_image WHERE image_id = ?')->execute([$imgId]);
                $unlinkAfter[] = (string)$url;
            }
        }
        foreach ($imgSort as $imgId => $val) {                             // 2. thứ tự hiển thị
            if (!is_scalar($val) || !ctype_digit((string)$val)) continue;
            $pdo->prepare('UPDATE product_image SET sort_order = ? WHERE image_id = ? AND product_id = ?')
                ->execute([min((int)$val, 999), (int)$imgId, $id]);
        }
        $cnt = $pdo->prepare('SELECT COUNT(*), COALESCE(MAX(sort_order), 0) FROM product_image WHERE product_id = ?');
        $cnt->execute([$id]);
        [$have, $maxSort] = array_map('intval', $cnt->fetch(PDO::FETCH_NUM));
        if ($have + count($galleryNew) > 10) {
            throw new BusinessException('Mỗi sản phẩm tối đa 10 ảnh xem thêm (hiện có ' . $have . ').');
        }
        foreach ($galleryNew as $path) {                                   // 3. thêm ảnh mới
            $maxSort++;
            $pdo->prepare('INSERT INTO product_image (product_id, image_url, is_primary, sort_order) VALUES (?, ?, 0, ?)')->execute([$id, $path, $maxSort]);
        }
        if ($makeCover > 0 && $newImage === null) {                        // 4. đặt một ảnh trong thư viện làm ảnh đại diện
            $q = $pdo->prepare('SELECT image_url FROM product_image WHERE image_id = ? AND product_id = ?');
            $q->execute([$makeCover, $id]);
            $coverUrl = $q->fetchColumn();
            if ($coverUrl !== false) {
                $cur = $pdo->prepare('SELECT thumbnail FROM product WHERE product_id = ?');
                $cur->execute([$id]);
                $oldCover = (string)$cur->fetchColumn();
                if ($oldCover !== '' && $oldCover !== $coverUrl) {          // ảnh đại diện cũ không bị mất: chuyển vào thư viện
                    $ex = $pdo->prepare('SELECT COUNT(*) FROM product_image WHERE product_id = ? AND image_url = ?');
                    $ex->execute([$id, $oldCover]);
                    if ((int)$ex->fetchColumn() === 0) {
                        $pdo->prepare('INSERT INTO product_image (product_id, image_url, is_primary, sort_order) VALUES (?, ?, 0, ?)')->execute([$id, $oldCover, $maxSort + 1]);
                    }
                }
                $pdo->prepare('UPDATE product SET thumbnail = ? WHERE product_id = ?')->execute([$coverUrl, $id]);
            }
        }
        $pdo->commit();

        // Xóa file ảnh đã bị gỡ khỏi thư viện (chỉ khi không còn nơi nào dùng)
        foreach ($unlinkAfter as $url) {
            $u1 = $pdo->prepare('SELECT (SELECT COUNT(*) FROM product WHERE thumbnail = ?) + (SELECT COUNT(*) FROM product_image WHERE image_url = ?)');
            $u1->execute([$url, $url]);
            if ((int)$u1->fetchColumn() === 0) {
                remove_upload_file($url);
            }
        }
    } catch (BusinessException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        cleanup_uploads($uploaded);          // giao dịch lỗi thì xóa các ảnh vừa tải lên
        flash_set('error', $e->getMessage());
        redirect($back);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        cleanup_uploads($uploaded);
        error_log('admin_product_save loi: ' . $e->getMessage());
        flash_set('error', ($e->getCode() === '23000') ? 'SKU bị trùng với một biến thể khác.' : 'Lỗi cơ sở dữ liệu, không lưu được.');
        redirect($back);
    }

    flash_set('success', 'Đã lưu sản phẩm.');
    redirect('index.php?page=admin_product_form&id=' . $id);
}

function action_admin_product_toggle(): void
{
    require_staff();
    $pid = post_int('product_id');
    db()->prepare("UPDATE product SET status = IF(status = 'active', 'inactive', 'active') WHERE product_id = ?")
        ->execute([$pid]);

    $s = db()->prepare("SELECT status FROM product WHERE product_id = ?");
    $s->execute([$pid]);
    $newStatus = $s->fetchColumn();

    if (is_ajax()) {
        json_response([
            'success'      => true,
            'message'      => 'Đã cập nhật trạng thái sản phẩm #' . $pid,
            'product_id'   => $pid,
            'status'       => $newStatus,
            'status_label' => ($newStatus === 'active') ? 'Đang bán' : 'Đã ẩn',
            'btn_text'     => ($newStatus === 'active') ? 'Ẩn' : 'Hiện'
        ]);
    }

    flash_set('success', 'Đã đổi trạng thái hiển thị sản phẩm.');
    redirect('index.php?page=admin_products');
}

/* ---------- Đơn hàng ---------- */
function action_admin_order_status(): void
{
    require_staff();
    $orderId  = post_int('order_id');
    $new      = post_str('new_status');
    $note     = mb_substr(post_str('note'), 0, 200);
    $tracking = post_str('tracking_code');
    $return   = safe_return_url(post_str('return'), 'index.php?page=admin_orders');

    if ($new === 'cancelled') {
        if (mb_strlen($note) < 3) {
            $msg = 'Vui lòng nhập lý do hủy đơn (tối thiểu 3 ký tự).';
            if (is_ajax()) {
                json_response(['success' => false, 'message' => $msg]);
            }
            flash_set('error', $msg);
            redirect($return);
        }
        $note = 'Admin hủy: ' . $note;
    }
    [$ok, $msg] = change_order_status($orderId, $new, (int)$_SESSION['user']['user_id'],
                                      $note === '' ? null : $note, $tracking === '' ? null : $tracking);

    if (is_ajax()) {
        json_response([
            'success'      => $ok,
            'message'      => $msg,
            'order_id'     => $orderId,
            'new_status'   => $new,
            'status_label' => ORDER_STATUS[$new] ?? $new
        ]);
    }

    flash_set($ok ? 'success' : 'error', $msg);
    redirect($return);
}

// Thao tác hàng loạt: tích chọn nhiều đơn rồi xác nhận / giao / hủy cùng lúc
function action_admin_orders_bulk(): void
{
    require_staff();
    $new    = post_str('new_status');
    $note   = mb_substr(post_str('note'), 0, 200);
    $return = safe_return_url(post_str('return'), 'index.php?page=admin_orders');
    $ids    = (isset($_POST['ids']) && is_array($_POST['ids'])) ? $_POST['ids'] : [];

    if (!array_key_exists($new, ORDER_STATUS) || $new === 'pending') {
        flash_set('error', 'Hành động không hợp lệ.');
        redirect($return);
    }
    if (!$ids) {
        flash_set('error', 'Bạn chưa chọn đơn hàng nào.');
        redirect($return);
    }
    if ($new === 'cancelled') {
        if (mb_strlen($note) < 3) {
            flash_set('error', 'Vui lòng nhập lý do hủy đơn (tối thiểu 3 ký tự).');
            redirect($return);
        }
        $note = 'Admin hủy: ' . $note;
    }
    [$ok, $fails] = bulk_change_status($ids, $new, (int)$_SESSION['user']['user_id'], $note === '' ? null : $note);
    if ($ok > 0) {
        flash_set('success', 'Đã chuyển ' . $ok . ' đơn sang "' . ORDER_STATUS[$new] . '".');
    }
    foreach (array_slice($fails, 0, 5) as $f) {
        flash_set('error', $f);
    }
    if (count($fails) > 5) {
        flash_set('error', '... và ' . (count($fails) - 5) . ' đơn khác không thực hiện được.');
    }
    redirect($return);
}

function action_admin_order_paid(): void
{
    require_staff();
    $orderId = post_int('order_id');
    [$ok, $msg] = mark_order_paid($orderId, mb_substr(post_str('transaction_code'), 0, 100));

    if (is_ajax()) {
        json_response([
            'success'        => $ok,
            'message'        => $msg,
            'order_id'       => $orderId,
            'payment_status' => 'paid',
            'payment_label'  => PAYMENT_STATUS['paid']
        ]);
    }

    flash_set($ok ? 'success' : 'error', $msg);
    redirect(safe_return_url(post_str('return'), 'index.php?page=admin_orders'));
}

function action_admin_order_refund(): void
{
    require_staff();
    $orderId = post_int('order_id');
    [$ok, $msg] = mark_order_refunded($orderId, mb_substr(post_str('refund_code'), 0, 100), (int)$_SESSION['user']['user_id']);

    if (is_ajax()) {
        json_response([
            'success'        => $ok,
            'message'        => $msg,
            'order_id'       => $orderId,
            'payment_status' => 'refunded',
            'payment_label'  => PAYMENT_STATUS['refunded']
        ]);
    }

    flash_set($ok ? 'success' : 'error', $msg);
    redirect(safe_return_url(post_str('return'), 'index.php?page=admin_orders'));
}

// Cho phép nhân viên chạy tác vụ tự động ngay lập tức (thay vì chờ chu kỳ)
function action_admin_run_maintenance(): void
{
    require_staff();
    db()->prepare('UPDATE system_job SET last_run = NOW() WHERE job_name = ?')->execute(['maintenance']);
    flash_set('success', maintenance_summary(run_maintenance()));
    redirect(safe_return_url(post_str('return'), 'index.php?page=admin_orders'));
}

/* ---------- Voucher ---------- */
function action_admin_voucher_save(): void
{
    require_admin();
    $id    = post_int('voucher_id');
    $code  = strtoupper(post_str('code'));
    $type  = post_str('discount_type');
    $value = post_str('discount_value');
    $min   = post_str('min_order_value');
    $max   = post_str('max_discount');
    $start = post_str('start_date');
    $end   = post_str('end_date');
    $limit = post_str('usage_limit');
    $status = post_str('status');
    $back  = 'index.php?page=admin_vouchers' . ($id > 0 ? '&edit=' . $id : '');

    $errors = [];
    if ($id === 0 && !preg_match('/^[A-Z0-9_\-]{3,30}$/', $code))  $errors[] = 'Mã voucher gồm 3-30 ký tự: chữ in hoa, số, gạch.';
    if (!in_array($type, ['percent', 'fixed'], true))              $errors[] = 'Loại giảm giá không hợp lệ.';
    if (!is_numeric($value) || $value <= 0)                        $errors[] = 'Giá trị giảm phải lớn hơn 0.';
    if ($type === 'percent' && is_numeric($value) && $value > 100) $errors[] = 'Giảm theo % không được quá 100.';
    if (!is_numeric($min) || $min < 0)                             $errors[] = 'Giá trị đơn tối thiểu không hợp lệ.';
    if ($max !== '' && (!is_numeric($max) || $max < 0))            $errors[] = 'Mức giảm tối đa không hợp lệ.';
    if (!ctype_digit($limit))                                      $errors[] = 'Giới hạn lượt dùng phải là số nguyên >= 0 (0 = không giới hạn).';
    if (!in_array($status, ['active', 'inactive'], true))          $errors[] = 'Trạng thái không hợp lệ.';
    $ds = DateTime::createFromFormat('Y-m-d\TH:i', $start);
    $de = DateTime::createFromFormat('Y-m-d\TH:i', $end);
    if (!$ds || !$de)                                              $errors[] = 'Ngày bắt đầu/kết thúc không hợp lệ.';
    elseif ($de <= $ds)                                            $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu.';

    if ($errors) {
        foreach ($errors as $e) flash_set('error', $e);
        redirect($back);
    }

    $maxVal = ($max === '') ? null : $max;
    try {
        if ($id > 0) {
            db()->prepare('UPDATE voucher SET discount_type=?, discount_value=?, min_order_value=?, max_discount=?, start_date=?, end_date=?, usage_limit=?, status=? WHERE voucher_id=?')
                ->execute([$type, $value, $min, $maxVal, $ds->format('Y-m-d H:i:00'), $de->format('Y-m-d H:i:00'), (int)$limit, $status, $id]);
        } else {
            db()->prepare('INSERT INTO voucher (code, discount_type, discount_value, min_order_value, max_discount, start_date, end_date, usage_limit, used_count, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)')
                ->execute([$code, $type, $value, $min, $maxVal, $ds->format('Y-m-d H:i:00'), $de->format('Y-m-d H:i:00'), (int)$limit, $status]);
        }
    } catch (PDOException $e) {
        error_log('admin_voucher_save loi: ' . $e->getMessage());
        flash_set('error', ($e->getCode() === '23000') ? 'Mã voucher đã tồn tại.' : 'Lỗi cơ sở dữ liệu, không lưu được.');
        redirect($back);
    }
    flash_set('success', 'Đã lưu voucher.');
    redirect('index.php?page=admin_vouchers');
}

function action_admin_voucher_toggle(): void
{
    require_admin();
    db()->prepare("UPDATE voucher SET status = IF(status = 'active', 'inactive', 'active') WHERE voucher_id = ?")
        ->execute([post_int('voucher_id')]);
    flash_set('success', 'Đã đổi trạng thái voucher.');
    redirect('index.php?page=admin_vouchers');
}

/* ---------- Người dùng / nhân viên ---------- */
function action_admin_user_update(): void
{
    require_admin();
    $uid    = post_int('user_id');
    $role   = post_str('role');
    $status = post_str('status');

    if ($uid === (int)$_SESSION['user']['user_id']) {
        flash_set('error', 'Bạn không thể tự đổi quyền hoặc khóa chính mình.');
    } elseif (!in_array($role, ['customer', 'staff', 'admin'], true) || !in_array($status, ['active', 'locked'], true)) {
        flash_set('error', 'Dữ liệu không hợp lệ.');
    } else {
        db()->prepare('UPDATE users SET role = ?, status = ? WHERE user_id = ?')->execute([$role, $status, $uid]);
        flash_set('success', 'Đã cập nhật người dùng.');
    }
    redirect('index.php?page=admin_users');
}


/* ---------- Đổi trả, đánh giá (nhân viên được làm) ---------- */
function action_admin_return_update(): void
{
    require_staff();
    [$ok, $msg] = process_return(post_int('return_id'), post_str('new_status'), mb_substr(post_str('admin_note'), 0, 500), (int)$_SESSION['user']['user_id']);
    flash_set($ok ? 'success' : 'error', $msg);
    redirect('index.php?page=admin_returns');
}

function action_admin_review_toggle(): void
{
    require_staff();
    db()->prepare("UPDATE review SET status = IF(status = 'visible', 'hidden', 'visible') WHERE review_id = ?")->execute([post_int('review_id')]);
    flash_set('success', 'Đã đổi trạng thái hiển thị đánh giá.');
    redirect('index.php?page=admin_reviews');
}

/* ---------- Danh mục & thương hiệu (chỉ admin) ---------- */
function action_admin_category_save(): void
{
    require_admin();
    $id = post_int('category_id');
    $name = post_str('name');
    $desc = post_str('description');
    $status = post_str('status');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || mb_strlen($desc) > 500 || !in_array($status, ['active', 'inactive'], true)) {
        flash_set('error', 'Tên danh mục 2-100 ký tự, mô tả tối đa 500 ký tự, trạng thái hợp lệ.');
        redirect('index.php?page=admin_catalog');
    }
    if ($id > 0) {
        db()->prepare('UPDATE category SET name = ?, description = ?, status = ? WHERE category_id = ?')->execute([$name, $desc, $status, $id]);
    } else {
        db()->prepare('INSERT INTO category (name, description, status) VALUES (?, ?, ?)')->execute([$name, $desc, $status]);
    }
    flash_set('success', 'Đã lưu danh mục.');
    redirect('index.php?page=admin_catalog');
}

function action_admin_brand_save(): void
{
    require_admin();
    $id = post_int('brand_id');
    $name = post_str('name');
    $status = post_str('status');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || !in_array($status, ['active', 'inactive'], true)) {
        flash_set('error', 'Tên thương hiệu 2-100 ký tự và trạng thái hợp lệ.');
        redirect('index.php?page=admin_catalog');
    }
    try {
        if ($id > 0) {
            db()->prepare('UPDATE brand SET name = ?, status = ? WHERE brand_id = ?')->execute([$name, $status, $id]);
        } else {
            db()->prepare('INSERT INTO brand (name, status) VALUES (?, ?)')->execute([$name, $status]);
        }
        flash_set('success', 'Đã lưu thương hiệu.');
    } catch (PDOException $e) {
        flash_set('error', 'Tên thương hiệu đã tồn tại.');
    }
    redirect('index.php?page=admin_catalog');
}

/* ---------- Flash sale (chỉ admin) ---------- */
function action_admin_flash_save(): void
{
    require_admin();
    $id = post_int('flash_sale_id');
    $name = post_str('name');
    $desc = post_str('description');
    $status = post_str('status');
    $ds = DateTime::createFromFormat('Y-m-d\TH:i', post_str('start_time'));
    $de = DateTime::createFromFormat('Y-m-d\TH:i', post_str('end_time'));
    $back = 'index.php?page=admin_flash' . ($id > 0 ? '&edit=' . $id : '');

    if (mb_strlen($name) < 3 || mb_strlen($name) > 150 || !in_array($status, ['active', 'inactive'], true) || !$ds || !$de || $de <= $ds) {
        flash_set('error', 'Tên 3-150 ký tự, thời gian bắt đầu/kết thúc hợp lệ (kết thúc sau bắt đầu) và trạng thái hợp lệ.');
        redirect($back);
    }
    if ($id > 0) {
        db()->prepare('UPDATE flash_sale SET name=?, description=?, start_time=?, end_time=?, status=? WHERE flash_sale_id=?')
            ->execute([$name, $desc, $ds->format('Y-m-d H:i:00'), $de->format('Y-m-d H:i:00'), $status, $id]);
    } else {
        db()->prepare('INSERT INTO flash_sale (name, description, start_time, end_time, status) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $desc, $ds->format('Y-m-d H:i:00'), $de->format('Y-m-d H:i:00'), $status]);
        $id = (int)db()->lastInsertId();
    }
    flash_set('success', 'Đã lưu đợt flash sale.');
    redirect('index.php?page=admin_flash&edit=' . $id);
}

function action_admin_flash_item_save(): void
{
    require_admin();
    $saleId = post_int('flash_sale_id');
    $variantId = post_int('variant_id');
    $price = post_str('sale_price');
    $limit = post_str('quantity_limit');
    $back = 'index.php?page=admin_flash&edit=' . $saleId;

    $s = db()->prepare('SELECT price FROM product_variant WHERE variant_id = ?');
    $s->execute([$variantId]);
    $regular = $s->fetchColumn();
    $c = db()->prepare('SELECT COUNT(*) FROM flash_sale WHERE flash_sale_id = ?');
    $c->execute([$saleId]);

    if ($regular === false || (int)$c->fetchColumn() === 0) {
        flash_set('error', 'Đợt flash sale hoặc biến thể không tồn tại.');
    } elseif (!is_numeric($price) || $price <= 0 || $price >= (float)$regular) {
        flash_set('error', 'Giá flash sale phải lớn hơn 0 và thấp hơn giá bán thường (' . format_vnd($regular) . ').');
    } elseif (!ctype_digit($limit) || (int)$limit < 1) {
        flash_set('error', 'Số suất flash sale phải là số nguyên >= 1.');
    } else {
        // Nếu biến thể đã có trong đợt này thì cập nhật (không cho giảm số suất xuống dưới số đã bán)
        $u = db()->prepare('UPDATE flash_sale_item SET sale_price = ?, quantity_limit = ? WHERE flash_sale_id = ? AND variant_id = ? AND sold_quantity <= ?');
        $u->execute([$price, (int)$limit, $saleId, $variantId, (int)$limit]);
        $exists = db()->prepare('SELECT COUNT(*) FROM flash_sale_item WHERE flash_sale_id = ? AND variant_id = ?');
        $exists->execute([$saleId, $variantId]);
        if ((int)$exists->fetchColumn() === 0) {
            db()->prepare('INSERT INTO flash_sale_item (flash_sale_id, variant_id, sale_price, quantity_limit, sold_quantity) VALUES (?, ?, ?, ?, 0)')
                ->execute([$saleId, $variantId, $price, (int)$limit]);
            flash_set('success', 'Đã thêm sản phẩm vào flash sale.');
        } elseif ($u->rowCount() === 0) {
            flash_set('info', 'Không có thay đổi (hoặc số suất mới thấp hơn số đã bán).');
        } else {
            flash_set('success', 'Đã cập nhật sản phẩm trong flash sale.');
        }
    }
    redirect($back);
}

function action_admin_flash_item_delete(): void
{
    require_admin();
    $saleId = post_int('flash_sale_id');
    $d = db()->prepare('DELETE FROM flash_sale_item WHERE flash_sale_item_id = ? AND flash_sale_id = ? AND sold_quantity = 0');
    $d->execute([post_int('flash_sale_item_id'), $saleId]);
    flash_set($d->rowCount() ? 'success' : 'error', $d->rowCount() ? 'Đã xóa khỏi flash sale.' : 'Không thể xóa vì đã có người mua (hãy đặt trạng thái đợt sale là Tắt).');
    redirect('index.php?page=admin_flash&edit=' . $saleId);
}

/* ---------- Quản lý Màu sắc (admin tạo/sửa danh sách màu) ---------- */
function action_admin_color_save(): void
{
    require_admin();
    $id     = post_int('color_id');
    $name   = post_str('name');
    $hex    = post_str('hex_code');
    $status = post_str('status');

    // Kiểm tra dữ liệu
    if (mb_strlen($name) < 1 || mb_strlen($name) > 50) {
        flash_set('error', 'Tên màu phải từ 1 đến 50 ký tự.');
        redirect('index.php?page=admin_attributes');
    }
    if ($hex !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
        flash_set('error', 'Mã hex phải có dạng #RRGGBB (ví dụ: #FF0000).');
        redirect('index.php?page=admin_attributes');
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        flash_set('error', 'Trạng thái không hợp lệ.');
        redirect('index.php?page=admin_attributes');
    }

    $hexVal = ($hex === '') ? null : strtoupper($hex);

    try {
        if ($id > 0) {
            // Sửa màu đã có
            db()->prepare('UPDATE color SET name = ?, hex_code = ?, status = ? WHERE color_id = ?')
                ->execute([$name, $hexVal, $status, $id]);
        } else {
            // Thêm màu mới
            db()->prepare('INSERT INTO color (name, hex_code, status) VALUES (?, ?, ?)')
                ->execute([$name, $hexVal, $status]);
        }
        flash_set('success', 'Đã lưu màu sắc "' . $name . '".');
    } catch (PDOException $e) {
        // Lỗi trùng tên màu (UNIQUE KEY)
        flash_set('error', 'Tên màu "' . $name . '" đã tồn tại. Hãy chọn tên khác.');
    }
    redirect('index.php?page=admin_attributes');
}

/* Xóa màu (chỉ xóa nếu chưa có sản phẩm nào dùng, nếu đang dùng thì chuyển sang ẩn) */
function action_admin_color_delete(): void
{
    require_admin();
    $id = post_int('color_id');
    $pdo = db();

    // Lấy tên màu
    $s = $pdo->prepare('SELECT name FROM color WHERE color_id = ?');
    $s->execute([$id]);
    $name = $s->fetchColumn();

    if (!$name) {
        flash_set('error', 'Màu không tồn tại.');
        redirect('index.php?page=admin_attributes');
    }

    // Kiểm tra xem biến thể sản phẩm nào đang dùng tên màu này không
    $chk = $pdo->prepare('SELECT COUNT(*) FROM product_variant WHERE color = ?');
    $chk->execute([$name]);
    $usedCount = (int)$chk->fetchColumn();

    if ($usedCount > 0) {
        // Đang có sản phẩm dùng: không xóa hẳn để tránh lỗi đơn hàng, mà chuyển sang inactive
        $pdo->prepare("UPDATE color SET status = 'inactive' WHERE color_id = ?")->execute([$id]);
        flash_set('info', 'Màu "' . $name . '" đang được dùng bởi ' . $usedCount . ' biến thể sản phẩm nên đã được chuyển sang trạng thái Ẩn.');
    } else {
        // Chưa có sản phẩm dùng: xóa an toàn
        $pdo->prepare('DELETE FROM color WHERE color_id = ?')->execute([$id]);
        flash_set('success', 'Đã xóa màu "' . $name . '".');
    }
    redirect('index.php?page=admin_attributes');
}

/* ---------- Quản lý Size (admin tạo/sửa danh sách size) ---------- */
function action_admin_size_save(): void
{
    require_admin();
    $id     = post_int('size_id');
    $name   = post_str('name');
    $sort   = post_int('sort_order');
    $status = post_str('status');

    // Kiểm tra dữ liệu
    if (mb_strlen($name) < 1 || mb_strlen($name) > 20) {
        flash_set('error', 'Tên size phải từ 1 đến 20 ký tự.');
        redirect('index.php?page=admin_attributes');
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        flash_set('error', 'Trạng thái không hợp lệ.');
        redirect('index.php?page=admin_attributes');
    }

    try {
        if ($id > 0) {
            // Sửa size đã có
            db()->prepare('UPDATE size_option SET name = ?, sort_order = ?, status = ? WHERE size_id = ?')
                ->execute([$name, $sort, $status, $id]);
        } else {
            // Thêm size mới
            db()->prepare('INSERT INTO size_option (name, sort_order, status) VALUES (?, ?, ?)')
                ->execute([$name, $sort, $status]);
        }
        flash_set('success', 'Đã lưu size "' . $name . '".');
    } catch (PDOException $e) {
        // Lỗi trùng tên size (UNIQUE KEY)
        flash_set('error', 'Size "' . $name . '" đã tồn tại. Hãy chọn tên khác.');
    }
    redirect('index.php?page=admin_attributes');
}

/* Xóa size (chỉ xóa nếu chưa có sản phẩm nào dùng, nếu đang dùng thì chuyển sang ẩn) */
function action_admin_size_delete(): void
{
    require_admin();
    $id = post_int('size_id');
    $pdo = db();

    // Lấy tên size
    $s = $pdo->prepare('SELECT name FROM size_option WHERE size_id = ?');
    $s->execute([$id]);
    $name = $s->fetchColumn();

    if (!$name) {
        flash_set('error', 'Size không tồn tại.');
        redirect('index.php?page=admin_attributes');
    }

    // Kiểm tra xem biến thể sản phẩm nào đang dùng size này không
    $chk = $pdo->prepare('SELECT COUNT(*) FROM product_variant WHERE size = ?');
    $chk->execute([$name]);
    $usedCount = (int)$chk->fetchColumn();

    if ($usedCount > 0) {
        // Đang có sản phẩm dùng: chuyển sang inactive
        $pdo->prepare("UPDATE size_option SET status = 'inactive' WHERE size_id = ?")->execute([$id]);
        flash_set('info', 'Size "' . $name . '" đang được dùng bởi ' . $usedCount . ' biến thể nên đã được chuyển sang trạng thái Ẩn.');
    } else {
        // Chưa có sản phẩm dùng: xóa an toàn
        $pdo->prepare('DELETE FROM size_option WHERE size_id = ?')->execute([$id]);
        flash_set('success', 'Đã xóa size "' . $name . '".');
    }
    redirect('index.php?page=admin_attributes');
}

/* Tùy chỉnh trực tiếp mã SKU của một biến thể sản phẩm */
function action_admin_variant_sku_update(): void
{
    require_admin();
    $variantId = post_int('variant_id');
    $newSku    = trim(post_str('sku'));

    if ($variantId <= 0) {
        flash_set('error', 'Biến thể không hợp lệ.');
        redirect('index.php?page=admin_attributes');
    }
    if (!preg_match('/^[A-Za-z0-9_\-]{2,50}$/', $newSku)) {
        flash_set('error', 'Mã SKU gồm 2-50 ký tự chữ, số, dấu gạch ngang hoặc gạch dưới.');
        redirect('index.php?page=admin_attributes');
    }

    $pdo = db();
    // Kiểm tra xem SKU có bị trùng với biến thể khác không
    $chk = $pdo->prepare('SELECT COUNT(*) FROM product_variant WHERE sku = ? AND variant_id != ?');
    $chk->execute([$newSku, $variantId]);
    if ((int)$chk->fetchColumn() > 0) {
        flash_set('error', 'Mã SKU "' . $newSku . '" đã bị trùng với một sản phẩm khác!');
        redirect('index.php?page=admin_attributes');
    }

    // Cập nhật SKU trong product_variant
    $pdo->prepare('UPDATE product_variant SET sku = ? WHERE variant_id = ?')->execute([$newSku, $variantId]);
    flash_set('success', 'Đã cập nhật mã SKU thành công: ' . $newSku);
    redirect('index.php?page=admin_attributes');
}

