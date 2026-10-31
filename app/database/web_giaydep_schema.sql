-- Database: web_giaydep (cửa hàng giày dép)
-- Import qua phpMyAdmin > Import. An toàn khi chạy lại: không xóa dữ liệu cũ.

CREATE DATABASE IF NOT EXISTS web_giaydep
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE web_giaydep;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  user_id       INT(11) NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone         VARCHAR(20)  DEFAULT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'customer',
  status        VARCHAR(20)  NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS category (
  category_id INT(11) NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  description TEXT,
  status      VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (category_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product (
  product_id  INT(11) NOT NULL AUTO_INCREMENT,
  category_id INT(11) NOT NULL,
  name        VARCHAR(200) NOT NULL,
  description TEXT,
  base_price  DECIMAL(18,2) NOT NULL DEFAULT 0,
  thumbnail   VARCHAR(500) DEFAULT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id),
  KEY idx_product_category (category_id),
  CONSTRAINT fk_product_category FOREIGN KEY (category_id)
    REFERENCES category(category_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_variant (
  variant_id     INT(11) NOT NULL AUTO_INCREMENT,
  product_id     INT(11) NOT NULL,
  size           VARCHAR(20) NOT NULL,
  color          VARCHAR(50) NOT NULL,
  sku            VARCHAR(50) NOT NULL,
  price          DECIMAL(18,2) NOT NULL DEFAULT 0,
  stock_quantity INT(11) NOT NULL DEFAULT 0,
  status         VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (variant_id),
  UNIQUE KEY uq_variant_sku (sku),
  KEY idx_variant_product (product_id),
  CONSTRAINT fk_variant_product FOREIGN KEY (product_id)
    REFERENCES product(product_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_image (
  image_id   INT(11) NOT NULL AUTO_INCREMENT,
  product_id INT(11) NOT NULL,
  image_url  VARCHAR(500) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (image_id),
  KEY idx_image_product (product_id),
  CONSTRAINT fk_image_product FOREIGN KEY (product_id)
    REFERENCES product(product_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_address (
  address_id     INT(11) NOT NULL AUTO_INCREMENT,
  user_id        INT(11) NOT NULL,
  recipient_name VARCHAR(100) NOT NULL,
  phone          VARCHAR(20) NOT NULL,
  address        TEXT NOT NULL,
  is_default     TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (address_id),
  KEY idx_address_user (user_id),
  CONSTRAINT fk_address_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS favorite (
  favorite_id INT(11) NOT NULL AUTO_INCREMENT,
  user_id     INT(11) NOT NULL,
  product_id  INT(11) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (favorite_id),
  UNIQUE KEY uq_favorite (user_id, product_id),
  KEY idx_favorite_product (product_id),
  CONSTRAINT fk_favorite_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_favorite_product FOREIGN KEY (product_id)
    REFERENCES product(product_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cart (
  cart_id    INT(11) NOT NULL AUTO_INCREMENT,
  user_id    INT(11) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (cart_id),
  KEY idx_cart_user (user_id),
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cart_item (
  cart_item_id INT(11) NOT NULL AUTO_INCREMENT,
  cart_id      INT(11) NOT NULL,
  variant_id   INT(11) NOT NULL,
  quantity     INT(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (cart_item_id),
  UNIQUE KEY uq_cart_variant (cart_id, variant_id),
  KEY idx_cartitem_variant (variant_id),
  CONSTRAINT fk_cartitem_cart FOREIGN KEY (cart_id)
    REFERENCES cart(cart_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cartitem_variant FOREIGN KEY (variant_id)
    REFERENCES product_variant(variant_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS voucher (
  voucher_id      INT(11) NOT NULL AUTO_INCREMENT,
  code            VARCHAR(50) NOT NULL,
  discount_type   VARCHAR(20) NOT NULL,            -- 'percent' hoặc 'fixed'
  discount_value  DECIMAL(18,2) NOT NULL DEFAULT 0,
  min_order_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  max_discount    DECIMAL(18,2) DEFAULT NULL,
  start_date      DATETIME NOT NULL,
  end_date        DATETIME NOT NULL,
  usage_limit     INT(11) NOT NULL DEFAULT 0,
  used_count      INT(11) NOT NULL DEFAULT 0,
  status          VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (voucher_id),
  UNIQUE KEY uq_voucher_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  order_id        INT(11) NOT NULL AUTO_INCREMENT,
  user_id         INT(11) NOT NULL,
  address_id      INT(11) NOT NULL,
  voucher_id      INT(11) DEFAULT NULL,
  total_amount    DECIMAL(18,2) NOT NULL DEFAULT 0,
  shipping_fee    DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  final_amount    DECIMAL(18,2) NOT NULL DEFAULT 0,
  payment_method  VARCHAR(30) NOT NULL,
  payment_status  VARCHAR(20) NOT NULL DEFAULT 'unpaid',
  order_status    VARCHAR(30) NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (order_id),
  KEY idx_orders_user (user_id),
  KEY idx_orders_address (address_id),
  KEY idx_orders_voucher (voucher_id),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_orders_address FOREIGN KEY (address_id)
    REFERENCES user_address(address_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_orders_voucher FOREIGN KEY (voucher_id)
    REFERENCES voucher(voucher_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_detail (
  order_detail_id INT(11) NOT NULL AUTO_INCREMENT,
  order_id        INT(11) NOT NULL,
  variant_id      INT(11) NOT NULL,
  product_name    VARCHAR(200) NOT NULL,   -- lưu lại tại thời điểm mua
  sku             VARCHAR(50)  NOT NULL,
  size            VARCHAR(20)  NOT NULL,
  color           VARCHAR(50)  NOT NULL,
  quantity        INT(11) NOT NULL,
  unit_price      DECIMAL(18,2) NOT NULL,
  subtotal        DECIMAL(18,2) NOT NULL,
  PRIMARY KEY (order_detail_id),
  KEY idx_detail_order (order_id),
  KEY idx_detail_variant (variant_id),
  CONSTRAINT fk_detail_order FOREIGN KEY (order_id)
    REFERENCES orders(order_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_detail_variant FOREIGN KEY (variant_id)
    REFERENCES product_variant(variant_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment (
  payment_id       INT(11) NOT NULL AUTO_INCREMENT,
  order_id         INT(11) NOT NULL,
  payment_method   VARCHAR(30) NOT NULL,
  transaction_code VARCHAR(100) DEFAULT NULL,
  amount           DECIMAL(18,2) NOT NULL,
  payment_status   VARCHAR(20) NOT NULL DEFAULT 'unpaid',
  paid_at          DATETIME DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (payment_id),
  KEY idx_payment_order (order_id),
  CONSTRAINT fk_payment_order FOREIGN KEY (order_id)
    REFERENCES orders(order_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS review (
  review_id       INT(11) NOT NULL AUTO_INCREMENT,
  user_id         INT(11) NOT NULL,
  product_id      INT(11) NOT NULL,
  order_detail_id INT(11) DEFAULT NULL,
  rating          INT(11) NOT NULL,
  comment         TEXT,
  status          VARCHAR(20) NOT NULL DEFAULT 'visible',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (review_id),
  KEY idx_review_user (user_id),
  KEY idx_review_product (product_id),
  KEY idx_review_detail (order_detail_id),
  CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_review_user FOREIGN KEY (user_id)
    REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_review_product FOREIGN KEY (product_id)
    REFERENCES product(product_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_review_detail FOREIGN KEY (order_detail_id)
    REFERENCES order_detail(order_detail_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS flash_sale (
  flash_sale_id INT(11) NOT NULL AUTO_INCREMENT,
  name          VARCHAR(150) NOT NULL,
  description   TEXT,
  start_time    DATETIME NOT NULL,
  end_time      DATETIME NOT NULL,
  status        VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (flash_sale_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS flash_sale_item (
  flash_sale_item_id INT(11) NOT NULL AUTO_INCREMENT,
  flash_sale_id      INT(11) NOT NULL,
  variant_id         INT(11) NOT NULL,
  sale_price         DECIMAL(18,2) NOT NULL,
  quantity_limit     INT(11) NOT NULL DEFAULT 0,
  sold_quantity      INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (flash_sale_item_id),
  UNIQUE KEY uq_flash_variant (flash_sale_id, variant_id),
  KEY idx_fsi_variant (variant_id),
  CONSTRAINT fk_fsi_flash FOREIGN KEY (flash_sale_id)
    REFERENCES flash_sale(flash_sale_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_fsi_variant FOREIGN KEY (variant_id)
    REFERENCES product_variant(variant_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
