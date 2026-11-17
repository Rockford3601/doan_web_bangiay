<?php
// TỰ KIỂM TRA & NÂNG CẤP CƠ SỞ DỮ LIỆU.
// Mỗi phiên làm việc, hàm schema_ensure() so sánh CSDL thực tế với danh sách cột/bảng cần thiết và tự bổ sung phần còn thiếu.
// => Không còn lỗi "Unknown column ..." dù bạn chưa import file migration. An toàn khi chạy lại nhiều lần.
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

function schema_ensure(): void
{
    if (($_SESSION['schema_version'] ?? 0) === SCHEMA_VERSION) {
        return;
    }
    $pdo = db();
    $errors = [];
    $exec = function (string $label, string $sql) use ($pdo, &$errors): bool {
        try {
            $pdo->exec($sql);
            return true;
        } catch (Throwable $e) {
            $errors[] = '[' . $label . '] ' . $e->getMessage();
            return false;
        }
    };

    // Đọc cấu trúc hiện tại
    $cols = [];
    foreach ($pdo->query('SELECT TABLE_NAME AS t, COLUMN_NAME AS c, IS_NULLABLE AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()')->fetchAll() as $r) {
        $cols[strtolower($r['t'])][strtolower($r['c'])] = $r['n'];
    }
    $idx = [];
    foreach ($pdo->query('SELECT TABLE_NAME AS t, INDEX_NAME AS i FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()')->fetchAll() as $r) {
        $idx[strtolower($r['t'])][strtolower($r['i'])] = true;
    }
    foreach (['users', 'product', 'orders', 'order_detail', 'product_variant', 'voucher', 'review', 'cart'] as $must) {
        if (!isset($cols[$must])) {
            throw new RuntimeException('Thiếu bảng "' . $must . '". Hãy import app/database/web_giaydep_schema.sql (rồi web_giaydep_sample_data.sql) trước.');
        }
    }

    /* ---------- 1. Cột còn thiếu ---------- */
    $addCols = [
        ['product', 'gender', "VARCHAR(10) NOT NULL DEFAULT 'unisex'"],
        ['product', 'brand_id', 'INT NULL'],
        ['product', 'badge', "VARCHAR(20) NOT NULL DEFAULT 'none'"],
        ['product', 'sale_price', "DECIMAL(18,2) NULL DEFAULT NULL"],
        ['users', 'points', 'INT NOT NULL DEFAULT 0'],
        ['users', 'address', 'TEXT NULL'],
        ['users', 'google_id', 'VARCHAR(100) NULL'],
        ['users', 'avatar', 'VARCHAR(500) NULL'],
        ['orders', 'recipient_name', "VARCHAR(100) NOT NULL DEFAULT ''"],
        ['orders', 'phone', "VARCHAR(20) NOT NULL DEFAULT ''"],
        ['orders', 'shipping_address', 'TEXT NULL'],
        ['orders', 'note', 'VARCHAR(500) NULL'],
        ['order_detail', 'flash_sale_item_id', 'INT NULL'],
        ['orders', 'tracking_code', 'VARCHAR(50) NULL'],
    ];
    $added = [];
    foreach ($addCols as [$t, $c, $def]) {
        if (!isset($cols[$t][$c])) {
            if ($exec("$t.$c", "ALTER TABLE `$t` ADD COLUMN `$c` $def")) {
                $added["$t.$c"] = true;
            }
        }
    }
    if (isset($added['product.gender'])) {
        $exec('gender mẫu', "UPDATE product SET gender = 'nam' WHERE product_id IN (2, 6)");
    }
    if (isset($added['product.badge']) || isset($added['product.sale_price'])) {
        $exec('badge mẫu 1', "UPDATE product SET badge = 'sale', sale_price = 520000 WHERE product_id = 1");
        $exec('badge mẫu 2', "UPDATE product SET badge = 'new' WHERE product_id = 2");
        $exec('badge mẫu 5', "UPDATE product SET badge = 'sale', sale_price = 390000 WHERE product_id = 5");
        $exec('badge mẫu 6', "UPDATE product SET badge = 'hot' WHERE product_id = 6");
    }
    if (isset($added['orders.recipient_name'])) {
        $exec('backfill địa chỉ đơn cũ',
            "UPDATE orders o JOIN user_address a ON a.address_id = o.address_id
                SET o.recipient_name = a.recipient_name, o.phone = a.phone, o.shipping_address = a.address
              WHERE o.recipient_name = ''");
    }
    // Đơn hàng giữ địa chỉ riêng nên address_id không còn bắt buộc
    if (($cols['orders']['address_id'] ?? 'YES') === 'NO') {
        $exec('orders.address_id NULL', 'ALTER TABLE orders MODIFY address_id INT(11) NULL');
    }

    /* ---------- 2. Bảng còn thiếu ---------- */
    $tables = [
        'voucher_usage' => 'CREATE TABLE voucher_usage (
            usage_id INT AUTO_INCREMENT PRIMARY KEY, voucher_id INT NOT NULL, user_id INT NOT NULL, order_id INT NOT NULL,
            used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_voucher_user (voucher_id, user_id), KEY idx_vu_order (order_id),
            CONSTRAINT fk_vu_voucher FOREIGN KEY (voucher_id) REFERENCES voucher(voucher_id) ON DELETE CASCADE,
            CONSTRAINT fk_vu_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            CONSTRAINT fk_vu_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'stock_movement' => 'CREATE TABLE stock_movement (
            movement_id INT AUTO_INCREMENT PRIMARY KEY, variant_id INT NOT NULL, change_qty INT NOT NULL, reason VARCHAR(30) NOT NULL,
            order_id INT NULL, user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sm_variant (variant_id, created_at),
            CONSTRAINT fk_sm_variant FOREIGN KEY (variant_id) REFERENCES product_variant(variant_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'order_status_history' => 'CREATE TABLE order_status_history (
            history_id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, old_status VARCHAR(30) NULL, new_status VARCHAR(30) NOT NULL,
            changed_by INT NULL, note VARCHAR(255) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_osh_order (order_id),
            CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'point_transaction' => 'CREATE TABLE point_transaction (
            point_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, order_id INT NOT NULL, points INT NOT NULL,
            reason VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_point_order (order_id),
            CONSTRAINT fk_pt_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            CONSTRAINT fk_pt_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'login_attempt' => 'CREATE TABLE login_attempt (
            attempt_id INT AUTO_INCREMENT PRIMARY KEY, identifier VARCHAR(254) NOT NULL, ip VARCHAR(45) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_la (identifier, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'brand' => "CREATE TABLE brand (
            brand_id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active',
            UNIQUE KEY uq_brand_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'return_request' => "CREATE TABLE return_request (
            return_id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, order_detail_id INT NOT NULL, user_id INT NOT NULL,
            request_type VARCHAR(10) NOT NULL DEFAULT 'return', quantity INT NOT NULL, reason VARCHAR(500) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'requested', refund_amount DECIMAL(18,2) NOT NULL DEFAULT 0, admin_note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_rr_order (order_id), KEY idx_rr_user (user_id), KEY idx_rr_detail (order_detail_id),
            CONSTRAINT fk_rr_detail FOREIGN KEY (order_detail_id) REFERENCES order_detail(order_detail_id) ON DELETE CASCADE,
            CONSTRAINT fk_rr_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'size_chart' => 'CREATE TABLE size_chart (
            eu_size INT NOT NULL PRIMARY KEY, foot_length_cm DECIMAL(4,1) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'knowledge_base' => "CREATE TABLE knowledge_base (
            kb_id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, content TEXT NOT NULL, tags VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'chat_log' => "CREATE TABLE chat_log (
            chat_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, question VARCHAR(500) NOT NULL, answer TEXT NOT NULL,
            mode VARCHAR(10) NOT NULL DEFAULT 'rule', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'system_job' => 'CREATE TABLE system_job (
            job_name VARCHAR(50) NOT NULL PRIMARY KEY, last_run DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'password_reset' => 'CREATE TABLE password_reset (
            reset_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL,
            used_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_reset_token (token_hash),
            CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        // Bảng quản lý danh sách màu sắc (admin tạo sẵn, chọn khi thêm biến thể)
        'color' => "CREATE TABLE color (
            color_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            hex_code VARCHAR(7) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            UNIQUE KEY uq_color_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        // Bảng quản lý danh sách size (admin tạo sẵn, chọn khi thêm biến thể)
        'size_option' => "CREATE TABLE size_option (
            size_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(20) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            UNIQUE KEY uq_size_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    $created = [];
    foreach ($tables as $name => $sql) {
        if (!isset($cols[$name])) {
            if ($exec($name, $sql)) {
                $created[$name] = true;
            }
        }
    }
    if (isset($created['voucher_usage'])) {
        $exec('backfill voucher_usage', 'INSERT IGNORE INTO voucher_usage (voucher_id, user_id, order_id) SELECT voucher_id, user_id, order_id FROM orders WHERE voucher_id IS NOT NULL');
    }

    if (isset($cols['system_job']) || isset($created['system_job'])) {
        $exec('seed system_job', "INSERT IGNORE INTO system_job (job_name, last_run) VALUES ('maintenance', '2000-01-01 00:00:00')");
    }

    // Dữ liệu mẫu cho bảng màu sắc
    if (isset($created['color'])) {
        $exec('seed color', "INSERT IGNORE INTO color (name, hex_code) VALUES
            ('Đen','#000000'),('Trắng','#FFFFFF'),('Đỏ','#FF0000'),('Xanh dương','#0000FF'),
            ('Xanh lá','#008000'),('Vàng','#FFD700'),('Nâu','#8B4513'),('Xám','#808080'),
            ('Hồng','#FFC0CB'),('Be','#F5F5DC'),('Cam','#FF8C00'),('Tím','#800080')");
    }
    // Dữ liệu mẫu cho bảng size
    if (isset($created['size_option'])) {
        $exec('seed size_option', "INSERT IGNORE INTO size_option (name, sort_order) VALUES
            ('35',1),('36',2),('37',3),('38',4),('39',5),('40',6),
            ('41',7),('42',8),('43',9),('44',10),('45',11),('46',12)");
    }

    // Đồng bộ collation của bảng color và size_option về utf8mb4_unicode_ci để khớp với database gốc
    if (isset($cols['color'])) {
        $exec('collate color', 'ALTER TABLE color CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
    if (isset($cols['size_option'])) {
        $exec('collate size_option', 'ALTER TABLE size_option CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /* ---------- 3. Dữ liệu khởi tạo cho chatbot ---------- */
    if (isset($created['size_chart'])) {
        // Bảng quy đổi THAM KHẢO: size EU -> chiều dài LÒNG GIÀY (cm). Nhóm nên thay bằng bảng size chính thức của hãng.
        $exec('seed size_chart', 'INSERT INTO size_chart (eu_size, foot_length_cm) VALUES
            (36,22.5),(37,23.0),(38,23.5),(39,24.5),(40,25.0),(41,26.0),(42,26.5),(43,27.5),(44,28.0),(45,29.0),(46,29.5),(47,30.5)');
    }
    if (isset($created['knowledge_base'])) {
        $kb = [
            ['Chính sách đổi trả', 'Cửa hàng hỗ trợ đổi hoặc trả hàng trong ' . RETURN_DAYS . ' ngày kể từ khi đơn hàng ở trạng thái Đã giao. Sản phẩm cần còn nguyên tem mác, chưa qua sử dụng và còn hộp. Vào mục Đơn hàng của tôi, chọn Đổi/Trả hàng để gửi yêu cầu. Sau khi cửa hàng nhận lại hàng, tiền được hoàn theo giá sản phẩm đã mua.', 'doi tra hoan tien bao hanh tra hang doi hang'],
            ['Phí vận chuyển', 'Phí vận chuyển mặc định là ' . format_vnd(SHIPPING_FEE) . '. Đơn hàng từ ' . format_vnd(FREE_SHIP_FROM) . ' trở lên được miễn phí vận chuyển.', 'phi van chuyen ship giao hang mien phi'],
            ['Phương thức thanh toán', 'Cửa hàng hỗ trợ thanh toán khi nhận hàng (COD), chuyển khoản ngân hàng và ví MoMo. Với chuyển khoản, đơn được xử lý sau khi cửa hàng xác nhận đã nhận tiền.', 'thanh toan cod chuyen khoan momo vi'],
            ['Cách đo chiều dài bàn chân', 'Đặt bàn chân lên tờ giấy trên nền phẳng, đánh dấu điểm gót và điểm đầu ngón dài nhất rồi đo khoảng cách bằng cm. Nên đo vào cuối ngày khi chân hơi nở. Chọn size có chiều dài lớn hơn chân khoảng 0.5 đến 1 cm.', 'do chan chieu dai ban chan chon size cm'],
            ['Chọn size giày thể thao và chạy bộ', 'Với giày chạy bộ nên chọn dư khoảng 0.5 đến 1 cm phía mũi giày để ngón chân không bị chạm khi chạy. Nếu bàn chân bè hoặc hay mang tất dày, hãy tăng thêm nửa size. Khi phân vân giữa hai size nên chọn size lớn hơn.', 'size giay the thao chay bo ban chan be tat day'],
            ['Chọn size giày da và sandal', 'Giày da nên ôm vừa chân, chừa khoảng 0.5 cm ở mũi. Sandal nên chọn sao cho gót chân nằm gọn trong đế và ngón chân không chạm mép trước.', 'size giay da sandal dep'],
            ['Voucher và điểm thưởng', 'Nhập mã giảm giá ở bước giỏ hàng. Mỗi tài khoản chỉ dùng mỗi mã một lần và đơn phải đạt giá trị tối thiểu của mã. Mỗi ' . format_vnd(POINT_PER_VND) . ' của đơn đã giao được cộng 1 điểm; từ 500 điểm là hạng Bạc, từ 2000 điểm là hạng Vàng.', 'voucher ma giam gia diem thuong tich diem hang thanh vien'],
            ['Theo dõi và hủy đơn hàng', 'Xem trạng thái đơn ở mục Đơn hàng của tôi. Đơn ở trạng thái Chờ xác nhận có thể tự hủy; các trạng thái sau đó vui lòng liên hệ cửa hàng. Khi hủy, kho và mã giảm giá được hoàn lại.', 'theo doi don hang huy don trang thai'],
            ['Bảo quản và vệ sinh giày', 'Không giặt máy và không phơi dưới nắng gắt. Dùng bàn chải mềm với xà phòng nhẹ để làm sạch, nhồi giấy vào trong để giữ form khi phơi nơi thoáng gió.', 'bao quan ve sinh giat giay'],
            ['Liên hệ hỗ trợ', 'Gọi hotline 1900 6868 (9:00 đến 18:00) để được hỗ trợ.', 'lien he ho tro hotline tu van'],
        ];
        $ins = $pdo->prepare('INSERT INTO knowledge_base (title, content, tags) VALUES (?, ?, ?)');
        foreach ($kb as $row) {
            try { $ins->execute($row); } catch (Throwable $e) { $errors[] = '[seed kb] ' . $e->getMessage(); }
        }
    }

    /* ---------- 4. Chỉ mục phụ (nếu lỗi thì bỏ qua, không ảnh hưởng chạy) ---------- */
    if (!isset($idx['cart']['uq_cart_user'])) {
        try { $pdo->exec('ALTER TABLE cart ADD UNIQUE INDEX uq_cart_user (user_id)'); } catch (Throwable $e) { /* có thể đã có giỏ trùng: bỏ qua */ }
    }
    if (!isset($idx['review']['uq_review_detail'])) {
        try { $pdo->exec('ALTER TABLE review ADD UNIQUE INDEX uq_review_detail (order_detail_id)'); } catch (Throwable $e) { /* bỏ qua */ }
    }

    if ($errors) {
        throw new RuntimeException("Không thể tự nâng cấp cơ sở dữ liệu:\n" . implode("\n", $errors));
    }
    $_SESSION['schema_version'] = SCHEMA_VERSION;
}
