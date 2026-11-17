-- =====================================================================
-- MIGRATION v2 cho web_giaydep
-- Chạy SAU web_giaydep_schema.sql (và web_giaydep_sample_data.sql nếu dùng).
-- Viết cho MariaDB của XAMPP: dùng "IF NOT EXISTS" nên chạy lại nhiều lần không lỗi.
-- (Nếu dùng MySQL 8 thuần, "ADD COLUMN IF NOT EXISTS" không được hỗ trợ -> phải bỏ IF NOT EXISTS và chạy 1 lần.)
-- =====================================================================
USE web_giaydep;

-- 1. Cột mới cho bảng có sẵn ------------------------------------------
ALTER TABLE product ADD COLUMN IF NOT EXISTS gender VARCHAR(10) NOT NULL DEFAULT 'unisex' AFTER category_id;
ALTER TABLE users   ADD COLUMN IF NOT EXISTS points INT NOT NULL DEFAULT 0;

-- Mỗi người dùng chỉ có 1 giỏ hàng
ALTER TABLE cart ADD UNIQUE INDEX IF NOT EXISTS uq_cart_user (user_id);

-- Đơn hàng phải GIỮ NGUYÊN địa chỉ lúc đặt (không phụ thuộc user_address về sau)
ALTER TABLE orders MODIFY address_id INT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS recipient_name  VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS phone           VARCHAR(20)  NOT NULL DEFAULT '';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_address TEXT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS note            VARCHAR(500) NULL;

-- Nhớ dòng flash sale đã dùng để hoàn lại số lượng khi hủy đơn
ALTER TABLE order_detail ADD COLUMN IF NOT EXISTS flash_sale_item_id INT NULL;

-- Điền dữ liệu địa chỉ cho các đơn cũ (từ bảng user_address)
UPDATE orders o JOIN user_address a ON a.address_id = o.address_id
   SET o.recipient_name = a.recipient_name, o.phone = a.phone, o.shipping_address = a.address
 WHERE o.recipient_name = '';

-- 2. Bảng mới ----------------------------------------------------------
-- Mỗi người chỉ được dùng 1 voucher 1 lần (UNIQUE chặn dùng trùng ngay cả khi bấm 2 lần)
CREATE TABLE IF NOT EXISTS voucher_usage (
  usage_id   INT AUTO_INCREMENT PRIMARY KEY,
  voucher_id INT NOT NULL,
  user_id    INT NOT NULL,
  order_id   INT NOT NULL,
  used_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_voucher_user (voucher_id, user_id),
  KEY idx_vu_order (order_id),
  CONSTRAINT fk_vu_voucher FOREIGN KEY (voucher_id) REFERENCES voucher(voucher_id) ON DELETE CASCADE,
  CONSTRAINT fk_vu_user    FOREIGN KEY (user_id)    REFERENCES users(user_id)      ON DELETE CASCADE,
  CONSTRAINT fk_vu_order   FOREIGN KEY (order_id)   REFERENCES orders(order_id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nhật ký kho: mọi lần tồn kho thay đổi đều có 1 dòng (đặt hàng, hủy đơn, admin chỉnh)
CREATE TABLE IF NOT EXISTS stock_movement (
  movement_id INT AUTO_INCREMENT PRIMARY KEY,
  variant_id  INT NOT NULL,
  change_qty  INT NOT NULL,
  reason      VARCHAR(30) NOT NULL,
  order_id    INT NULL,
  user_id     INT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sm_variant (variant_id, created_at),
  CONSTRAINT fk_sm_variant FOREIGN KEY (variant_id) REFERENCES product_variant(variant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_status_history (
  history_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id   INT NOT NULL,
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NOT NULL,
  changed_by INT NULL,
  note       VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_osh_order (order_id),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Điểm tích lũy. UNIQUE(order_id) => 1 đơn chỉ được cộng điểm đúng 1 lần
CREATE TABLE IF NOT EXISTS point_transaction (
  point_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  order_id   INT NOT NULL,
  points     INT NOT NULL,
  reason     VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_point_order (order_id),
  CONSTRAINT fk_pt_user  FOREIGN KEY (user_id)  REFERENCES users(user_id)   ON DELETE CASCADE,
  CONSTRAINT fk_pt_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Đếm số lần đăng nhập sai (chống dò mật khẩu)
CREATE TABLE IF NOT EXISTS login_attempt (
  attempt_id INT AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(254) NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_la (identifier, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng quản lý danh mục Màu sắc cho sản phẩm (admin tùy chỉnh thêm/sửa/xóa)
CREATE TABLE IF NOT EXISTS color (
  color_id INT AUTO_INCREMENT PRIMARY KEY,
  name     VARCHAR(50) NOT NULL,
  hex_code VARCHAR(7)  NULL,
  status   VARCHAR(20) NOT NULL DEFAULT 'active',
  UNIQUE KEY uq_color_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng quản lý danh mục Kích cỡ (Size) cho sản phẩm (admin tùy chỉnh thêm/sửa/xóa)
CREATE TABLE IF NOT EXISTS size_option (
  size_id    INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(20) NOT NULL,
  sort_order INT         NOT NULL DEFAULT 0,
  status     VARCHAR(20) NOT NULL DEFAULT 'active',
  UNIQUE KEY uq_size_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE color CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE size_option CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Thêm dữ liệu mẫu màu sắc và size
INSERT IGNORE INTO color (name, hex_code) VALUES
('Đen','#000000'),('Trắng','#FFFFFF'),('Đỏ','#FF0000'),('Xanh dương','#0000FF'),
('Xanh lá','#008000'),('Vàng','#FFD700'),('Nâu','#8B4513'),('Xám','#808080'),
('Hồng','#FFC0CB'),('Be','#F5F5DC'),('Cam','#FF8C00'),('Tím','#800080');

INSERT IGNORE INTO size_option (name, sort_order) VALUES
('35',1),('36',2),('37',3),('38',4),('39',5),('40',6),
('41',7),('42',8),('43',9),('44',10),('45',11),('46',12);

-- 3. Cập nhật dữ liệu mẫu ---------------------------------------------
UPDATE product SET gender = 'nam' WHERE product_id IN (2, 6) AND gender = 'unisex';

-- Đơn mẫu cũ đã dùng voucher -> ghi vào voucher_usage để đồng bộ
INSERT IGNORE INTO voucher_usage (voucher_id, user_id, order_id)
SELECT voucher_id, user_id, order_id FROM orders WHERE voucher_id IS NOT NULL;

-- (Tùy chọn) bật flash sale mẫu trong 3 ngày tới để thử tính năng giá flash sale:
-- UPDATE flash_sale SET start_time = NOW(), end_time = NOW() + INTERVAL 3 DAY WHERE flash_sale_id = 1;
