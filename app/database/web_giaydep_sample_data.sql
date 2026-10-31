-- Dữ liệu mẫu cho web_giaydep. Chạy SAU web_giaydep_schema.sql, chỉ chạy một lần.
-- Mật khẩu của mọi tài khoản mẫu: password
USE web_giaydep;

INSERT INTO users (user_id, username, name, email, password_hash, phone, role, status) VALUES
(1,'admin','Quản trị viên','admin@giaydep.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','0900000001','admin','active'),
(2,'an.nguyen','Nguyễn Văn An','an@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','0901234567','customer','active'),
(3,'binh.tran','Trần Thị Bình','binh@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','0912345678','customer','active');

INSERT INTO category (category_id, name, description, status) VALUES
(1,'Giày thể thao','Giày chạy bộ, tập gym, đi hằng ngày','active'),
(2,'Giày da','Giày công sở, giày tây','active'),
(3,'Sandal','Sandal nam nữ quai hậu','active'),
(4,'Dép','Dép đi trong nhà và đi biển','active');

INSERT INTO product (product_id, category_id, name, description, base_price, thumbnail, status) VALUES
(1,1,'Giày chạy bộ Runner X','Đế êm, thân giày lưới thoáng khí',650000,'images/runner-x.jpg','active'),
(2,2,'Giày da Oxford cổ điển','Da bò thật, đế khâu chắc chắn',1200000,'images/oxford.jpg','active'),
(3,3,'Sandal quai chéo Lướt','Quai dán điều chỉnh, đế chống trượt',380000,'images/sandal-luot.jpg','active'),
(4,4,'Dép quai ngang Bãi Biển','Cao su nguyên chất, nhẹ',150000,'images/dep-bai-bien.jpg','active'),
(5,1,'Sneaker trắng Classic','Phong cách tối giản, dễ phối đồ',520000,'images/sneaker-classic.jpg','active'),
(6,2,'Giày lười da Moka','Không dây, đi nhanh, đế cao su',890000,'images/moka.jpg','active');

INSERT INTO product_variant (variant_id, product_id, size, color, sku, price, stock_quantity, status) VALUES
(1,1,'40','Đen','RX-40-BLK',650000,20,'active'),
(2,1,'41','Đen','RX-41-BLK',650000,15,'active'),
(3,1,'42','Trắng','RX-42-WHT',650000,10,'active'),
(4,2,'41','Nâu','OX-41-BRN',1200000,8,'active'),
(5,2,'42','Đen','OX-42-BLK',1200000,6,'active'),
(6,3,'39','Be','SD-39-BEI',380000,25,'active'),
(7,3,'40','Nâu','SD-40-BRN',380000,30,'active'),
(8,4,'38','Xanh','DP-38-BLU',150000,50,'active'),
(9,5,'41','Trắng','SN-41-WHT',520000,12,'active'),
(10,6,'42','Đen','LU-42-BLK',890000,9,'active');

INSERT INTO product_image (product_id, image_url, is_primary, sort_order) VALUES
(1,'images/runner-x-1.jpg',1,1),(1,'images/runner-x-2.jpg',0,2),
(2,'images/oxford-1.jpg',1,1),(3,'images/sandal-luot-1.jpg',1,1),
(4,'images/dep-bai-bien-1.jpg',1,1),(5,'images/sneaker-classic-1.jpg',1,1),
(6,'images/moka-1.jpg',1,1);

INSERT INTO user_address (address_id, user_id, recipient_name, phone, address, is_default) VALUES
(1,2,'Nguyễn Văn An','0901234567','12 Đường số 5, Thuận An, Bình Dương',1),
(2,3,'Trần Thị Bình','0912345678','45 Nguyễn Huệ, Quận 1, TP.HCM',1);

INSERT INTO favorite (user_id, product_id) VALUES (2,2),(2,5),(3,1);

INSERT INTO cart (cart_id, user_id) VALUES (1,3);
INSERT INTO cart_item (cart_id, variant_id, quantity) VALUES (1,6,1),(1,8,2);

INSERT INTO voucher (voucher_id, code, discount_type, discount_value, min_order_value, max_discount, start_date, end_date, usage_limit, used_count, status) VALUES
(1,'GIAM10','percent',10,500000,100000,'2026-10-01 00:00:00','2026-12-31 23:59:59',100,1,'active'),
(2,'FREESHIP30','fixed',30000,300000,30000,'2026-10-01 00:00:00','2026-11-30 23:59:59',200,0,'active');

INSERT INTO flash_sale (flash_sale_id, name, description, start_time, end_time, status) VALUES
(1,'Flash sale cuối tháng','Giảm giá sneaker trong 3 ngày','2026-10-25 00:00:00','2026-10-28 23:59:59','active');
INSERT INTO flash_sale_item (flash_sale_id, variant_id, sale_price, quantity_limit, sold_quantity) VALUES
(1,9,450000,10,1),(1,2,550000,10,0);

-- Đơn 1: đã giao, dùng voucher GIAM10 (950.000 + 30.000 - 95.000 = 885.000)
INSERT INTO orders (order_id, user_id, address_id, voucher_id, total_amount, shipping_fee, discount_amount, final_amount, payment_method, payment_status, order_status, created_at) VALUES
(1,2,1,1,950000,30000,95000,885000,'Chuyển khoản','paid','delivered','2026-10-20 09:15:00'),
-- Đơn 2: đang giao, thanh toán khi nhận hàng
(2,3,2,NULL,1200000,30000,0,1230000,'COD','unpaid','shipping','2026-10-22 14:30:00'),
-- Đơn 3: mua sneaker giá flash sale, chờ xác nhận
(3,2,1,NULL,450000,30000,0,480000,'COD','unpaid','pending','2026-10-25 20:05:00');

INSERT INTO order_detail (order_detail_id, order_id, variant_id, product_name, sku, size, color, quantity, unit_price, subtotal) VALUES
(1,1,1,'Giày chạy bộ Runner X','RX-40-BLK','40','Đen',1,650000,650000),
(2,1,8,'Dép quai ngang Bãi Biển','DP-38-BLU','38','Xanh',2,150000,300000),
(3,2,4,'Giày da Oxford cổ điển','OX-41-BRN','41','Nâu',1,1200000,1200000),
(4,3,9,'Sneaker trắng Classic','SN-41-WHT','41','Trắng',1,450000,450000);

INSERT INTO payment (order_id, payment_method, transaction_code, amount, payment_status, paid_at) VALUES
(1,'Chuyển khoản','TXN20261020001',885000,'paid','2026-10-20 09:20:00'),
(2,'COD',NULL,1230000,'unpaid',NULL),
(3,'COD',NULL,480000,'unpaid',NULL);

INSERT INTO review (user_id, product_id, order_detail_id, rating, comment, status) VALUES
(2,1,1,5,'Giày nhẹ, đi êm chân, giao hàng nhanh.','visible');
