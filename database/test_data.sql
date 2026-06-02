-- ==========================================================================
-- DỮ LIỆU KIỂM THỬ CHO HỆ THỐNG QUẢN LÝ CHI TIÊU CÁ NHÂN
-- ==========================================================================
-- Hướng dẫn:
-- 1. Mở phpMyAdmin
-- 2. Chọn database: quanly_chitieu
-- 3. Chọn tab SQL
-- 4. Copy toàn bộ script này vào
-- 5. Nhấn Execute
-- ==========================================================================

-- Xóa dữ liệu cũ (nếu có) - dùng TRUNCATE để reset auto_increment
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE transactions;
TRUNCATE TABLE budgets;
TRUNCATE TABLE categories;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================================
-- THÊM NGƯỜI DÙNG DEMO
-- ==========================================================================
-- Mật khẩu: 123456 (Password hash dùng PASSWORD_BCRYPT)
-- Hash: $2y$10$Vs1FCW/OyPSYHPty0VrbUewqV3Rz./V1kZLJ4QHFl6dyAvo/qq7dC

INSERT INTO users (id, username, full_name, email, password) VALUES
(1, 'ngongoc', 'Ngô Thị Ngọc', 'ngongoc@gmail.com', '$2y$10$Vs1FCW/OyPSYHPty0VrbUewqV3Rz./V1kZLJ4QHFl6dyAvo/qq7dC'),
(2, 'hachi', 'Trần Hà Chi', 'tranhachi@gmail.com', '$2y$10$Vs1FCW/OyPSYHPty0VrbUewqV3Rz./V1kZLJ4QHFl6dyAvo/qq7dC'),
(3, 'thanhhai', 'Nguyễn Thanh Hải', 'nguyenthanhhai10@gmail.com', '$2y$10$Vs1FCW/OyPSYHPty0VrbUewqV3Rz./V1kZLJ4QHFl6dyAvo/qq7dC');

-- ==========================================================================
-- THÊM DANH MỤC CHO NGƯỜI DÙNG 1
-- ==========================================================================

-- Danh mục THU
INSERT INTO categories (user_id, name, type) VALUES
(1, 'Lương', 'Thu'),
(1, 'Thưởng', 'Thu'),
(1, 'Freelance', 'Thu'),
(1, 'Khác', 'Thu');

-- Danh mục CHI
INSERT INTO categories (user_id, name, type) VALUES
(1, 'Ăn uống', 'Chi'),
(1, 'Di chuyển', 'Chi'),
(1, 'Nhà ở', 'Chi'),
(1, 'Mua sắm', 'Chi'),
(1, 'Y tế', 'Chi'),
(1, 'Giáo dục', 'Chi'),
(1, 'Giải trí', 'Chi'),
(1, 'Hóa đơn điện/nước', 'Chi'),
(1, 'Internet', 'Chi'),
(1, 'Khác', 'Chi');

-- ==========================================================================
-- THÊM DANH MỤC CHO NGƯỜI DÙNG 2
-- ==========================================================================

INSERT INTO categories (user_id, name, type) VALUES
(2, 'Lương', 'Thu'),
(2, 'Ăn uống', 'Chi'),
(2, 'Di chuyển', 'Chi'),
(2, 'Nhà ở', 'Chi');

-- ==========================================================================
-- THÊM DANH MỤC CHO NGƯỜI DÙNG 3
-- ==========================================================================

INSERT INTO categories (user_id, name, type) VALUES
(3, 'Lương', 'Thu'),
(3, 'Ăn uống', 'Chi'),
(3, 'Di chuyển', 'Chi');

-- ==========================================================================
-- THÊM GIAO DỊCH THÁNG 3/2025 - NGƯỜI DÙNG 1
-- ==========================================================================
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2025-03-01', 'Lương tháng 3/2025'),
(1, 3, 150000, '2025-03-02', 'Ăn trưa công ty'),
(1, 6, 200000, '2025-03-15', 'Tiền nhà'),
(1, 13, 200000, '2025-03-01', 'Internet tháng');

-- ==========================================================================
-- THÊM GIAO DỊCH THÁNG 4-10/2025 - NGƯỜI DÙNG 1 (CÁC THÁNG KHÁC)
-- ==========================================================================

-- Tháng 4
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2025-04-01', 'Lương tháng 4/2025'),
(1, 5, 160000, '2025-04-02', 'Ăn trưa'),
(1, 5, 220000, '2025-04-08', 'Nhà hàng'),
(1, 6, 500000, '2025-04-01', 'Xăng xe tháng'),
(1, 7, 3000000, '2025-04-01', 'Tiền nhà tháng 4'),
(1, 12, 260000, '2025-04-10', 'Tiền điện'),
(1, 13, 200000, '2025-04-01', 'Internet tháng');

-- Tháng 5
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2025-05-01', 'Lương tháng 5/2025'),
(1, 2, 1200000, '2025-05-17', 'Thưởng dự án'),
(1, 5, 170000, '2025-05-03', 'Ăn trưa'),
(1, 6, 520000, '2025-05-02', 'Xăng xe tháng'),
(1, 7, 3000000, '2025-05-01', 'Tiền nhà tháng 5'),
(1, 12, 270000, '2025-05-10', 'Tiền điện'),
(1, 13, 200000, '2025-05-01', 'Internet tháng');

-- Tháng 6
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2025-06-01', 'Lương tháng 6/2025'),
(1, 2, 1000000, '2025-06-20', 'Thưởng giữa năm'),
(1, 3, 2500000, '2025-06-15', 'Thu freelance thiết kế'),
(1, 5, 180000, '2025-06-02', 'Ăn trưa'),
(1, 6, 530000, '2025-06-01', 'Xăng xe tháng'),
(1, 7, 3000000, '2025-06-01', 'Tiền nhà tháng 6'),
(1, 12, 280000, '2025-06-11', 'Tiền điện'),
(1, 13, 210000, '2025-06-01', 'Internet tháng');

-- Tháng 7
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2025-07-01', 'Lương tháng 7/2025'),
(1, 5, 210000, '2025-07-04', 'Ăn tối lễ quốc khánh'),
(1, 6, 540000, '2025-07-01', 'Xăng xe tháng'),
(1, 7, 3000000, '2025-07-01', 'Tiền nhà tháng 7'),
(1, 12, 290000, '2025-07-10', 'Tiền điện'),
(1, 13, 210000, '2025-07-01', 'Internet tháng');

-- Tháng 8
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2025-08-01', 'Lương tháng 8/2025'),
(1, 2, 700000, '2025-08-16', 'Thưởng quý'),
(1, 5, 190000, '2025-08-03', 'Ăn trưa'),
(1, 6, 550000, '2025-08-01', 'Xăng xe tháng'),
(1, 7, 3000000, '2025-08-01', 'Tiền nhà tháng 8'),
(1, 12, 300000, '2025-08-10', 'Tiền điện'),
(1, 13, 220000, '2025-08-01', 'Internet tháng');

-- Tháng 9
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2025-09-01', 'Lương tháng 9/2025'),
(1, 5, 170000, '2025-09-05', 'Ăn trưa'),
(1, 6, 560000, '2025-09-01', 'Xăng xe tháng'),
(1, 7, 3000000, '2025-09-01', 'Tiền nhà tháng 9'),
(1, 12, 310000, '2025-09-10', 'Tiền điện'),
(1, 13, 220000, '2025-09-01', 'Internet tháng');

-- Tháng 10
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2025-10-01', 'Lương tháng 10/2025'),
(1, 3, 1800000, '2025-10-18', 'Thu freelance nội dung'),
(1, 5, 200000, '2025-10-04', 'Ăn trưa'),
(1, 6, 570000, '2025-10-01', 'Xăng xe tháng'),
(1, 7, 3000000, '2025-10-01', 'Tiền nhà tháng 10'),
(1, 12, 320000, '2025-10-10', 'Tiền điện'),
(1, 13, 220000, '2025-10-01', 'Internet tháng');

-- ==========================================================================
-- THÊM GIAO DỊCH THÁNG 11-12/2025 - NGƯỜI DÙNG 1
-- ==========================================================================

INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2025-11-01', 'Lương tháng 11/2025'),
(1, 5, 210000, '2025-11-05', 'Ăn trưa công ty'),
(1, 6, 560000, '2025-11-01', 'Xăng xe tháng'),
(1, 12, 330000, '2025-11-10', 'Tiền điện'),
(1, 14, 1500000, '2025-12-18', 'Thưởng cuối năm');

-- ==========================================================================
-- THÊM GIAO DỊCH THÁNG 1-4/2026 - NGƯỜI DÙNG 1
-- ==========================================================================

INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2026-01-01', 'Lương tháng 1/2026'),
(1, 5, 170000, '2026-01-04', 'Ăn trưa đầu năm'),
(1, 7, 3000000, '2026-01-01', 'Tiền nhà tháng 1'),
(1, 6, 520000, '2026-01-03', 'Xăng xe tháng'),
(1, 12, 350000, '2026-01-10', 'Tiền điện'),
(1, 13, 240000, '2026-01-01', 'Internet tháng'),
(1, 1, 10000000, '2026-02-01', 'Lương tháng 2/2026'),
(1, 5, 180000, '2026-02-05', 'Ăn trưa'),
(1, 6, 520000, '2026-02-01', 'Xăng xe tháng'),
(1, 7, 3000000, '2026-02-01', 'Tiền nhà tháng 2'),
(1, 12, 360000, '2026-02-10', 'Tiền điện'),
(1, 13, 230000, '2026-02-01', 'Internet tháng'),
(1, 5, 220000, '2026-02-14', 'Quà Valentine'),
(1, 1, 10000000, '2026-03-01', 'Lương tháng 3/2026'),
(1, 2, 900000, '2026-03-22', 'Thưởng tháng 3'),
(1, 5, 190000, '2026-03-07', 'Ăn trưa công ty'),
(1, 6, 580000, '2026-03-01', 'Xăng xe tháng'),
(1, 7, 3000000, '2026-03-01', 'Tiền nhà tháng 3'),
(1, 12, 370000, '2026-03-10', 'Tiền điện'),
(1, 13, 230000, '2026-03-01', 'Internet tháng'),
(1, 5, 210000, '2026-03-15', 'Ăn tối cuối tuần'),
(1, 1, 10000000, '2026-04-01', 'Lương tháng 4/2026'),
(1, 5, 185000, '2026-04-06', 'Ăn trưa'),
(1, 6, 590000, '2026-04-01', 'Xăng xe tháng'),
(1, 7, 3000000, '2026-04-01', 'Tiền nhà tháng 4'),
(1, 12, 380000, '2026-04-10', 'Tiền điện'),
(1, 13, 230000, '2026-04-01', 'Internet tháng'),
(1, 11, 250000, '2026-04-18', 'Netflix tháng 4');

-- ========================================================================== 
-- THÊM GIAO DỊCH THÁNG 5/2026 - NGƯỜI DÙNG 1 (THÁNG HIỆN TẠI)
-- ========================================================================== 

INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(1, 1, 10000000, '2026-05-01', 'Lương tháng 5/2026'),
(1, 3, 3000000, '2026-05-05', 'Thu freelance thiết kế website'),
(1, 5, 160000, '2026-05-02', 'Ăn trưa công ty'),
(1, 5, 95000, '2026-05-03', 'Ăn phở sáng'),
(1, 5, 220000, '2026-05-05', 'Ăn tối nhà hàng Nhật'),
(1, 5, 130000, '2026-05-07', 'Cơm trưa văn phòng'),
(1, 5, 280000, '2026-05-09', 'Tiệc sinh nhật bạn'),
(1, 6, 600000, '2026-05-01', 'Xăng xe tháng'),
(1, 6, 75000, '2026-05-04', 'Grab đi họp'),
(1, 6, 50000, '2026-05-08', 'Gửi xe'),
(1, 7, 3000000, '2026-05-01', 'Tiền nhà tháng 5'),
(1, 8, 1200000, '2026-05-03', 'Mua áo khoác mùa đông'),
(1, 8, 450000, '2026-05-07', 'Giày chạy bộ giảm giá'),
(1, 9, 350000, '2026-05-06', 'Khám răng định kỳ'),
(1, 10, 800000, '2026-05-02', 'Khóa học React online'),
(1, 11, 200000, '2026-05-04', 'Xem phim Avengers'),
(1, 11, 150000, '2026-05-09', 'Netflix tháng 5'),
(1, 12, 390000, '2026-05-10', 'Tiền điện'),
(1, 13, 230000, '2026-05-01', 'Internet tháng');

-- ==========================================================================
-- THÊM GIAO DỊCH CHO NGƯỜI DÙNG 2 (MỞ RỘNG)
-- ==========================================================================

-- User 2: Categories: 15=Lương(Thu), 16=Ăn uống(Chi), 17=Di chuyển(Chi), 18=Nhà ở(Chi)
-- (User1 có 14 categories id=1..14, User2 bắt đầu từ id=15)
-- Tháng 3/2025
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(2, 15, 8000000, '2025-03-01', 'Lương tháng 3'),
(2, 16, 100000, '2025-03-05', 'Ăn trưa'),
(2, 16, 150000, '2025-03-10', 'Nhà hàng'),
(2, 16, 80000, '2025-03-15', 'Cà phê'),
(2, 17, 400000, '2025-03-01', 'Xăng xe'),
(2, 18, 2500000, '2025-03-01', 'Tiền nhà');

-- Tháng 4/2025
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(2, 15, 8000000, '2025-04-01', 'Lương tháng 4'),
(2, 16, 120000, '2025-04-03', 'Ăn trưa'),
(2, 16, 180000, '2025-04-12', 'Ăn tối nhà hàng'),
(2, 16, 90000, '2025-04-20', 'Cà phê với bạn'),
(2, 17, 420000, '2025-04-01', 'Xăng xe'),
(2, 17, 60000, '2025-04-15', 'Grab đi chợ'),
(2, 18, 2500000, '2025-04-01', 'Tiền nhà');

-- Tháng 5/2025
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(2, 15, 8000000, '2025-05-01', 'Lương tháng 5'),
(2, 16, 110000, '2025-05-04', 'Ăn trưa'),
(2, 16, 200000, '2025-05-10', 'Tiệc sinh nhật'),
(2, 17, 430000, '2025-05-01', 'Xăng xe'),
(2, 18, 2500000, '2025-05-01', 'Tiền nhà');

-- ==========================================================================
-- THÊM GIAO DỊCH CHO NGƯỜI DÙNG 3
-- ==========================================================================

-- User 3: Categories: 19=Lương(Thu), 20=Ăn uống(Chi), 21=Di chuyển(Chi)
-- (User1: 14 cats id=1..14, User2: 4 cats id=15..18, User3 bắt đầu từ id=19)
-- Tháng 3/2025
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(3, 19, 12000000, '2025-03-01', 'Lương tháng 3/2025'),
(3, 20, 130000, '2025-03-03', 'Ăn trưa công ty'),
(3, 20, 170000, '2025-03-08', 'Ăn tối với đồng nghiệp'),
(3, 20, 90000, '2025-03-12', 'Cà phê sáng'),
(3, 20, 250000, '2025-03-18', 'Nhà hàng cuối tuần'),
(3, 21, 450000, '2025-03-01', 'Xăng xe tháng'),
(3, 21, 80000, '2025-03-10', 'Grab đi làm');

-- Tháng 4/2025
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(3, 19, 12000000, '2025-04-01', 'Lương tháng 4/2025'),
(3, 20, 140000, '2025-04-05', 'Ăn trưa'),
(3, 20, 200000, '2025-04-15', 'Đi ăn buffet'),
(3, 21, 460000, '2025-04-01', 'Xăng xe tháng'),
(3, 21, 100000, '2025-04-12', 'Taxi về quê');

-- Tháng 5/2025
INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES
(3, 19, 12000000, '2025-05-01', 'Lương tháng 5/2025'),
(3, 20, 160000, '2025-05-06', 'Ăn trưa'),
(3, 20, 95000, '2025-05-10', 'Cà phê'),
(3, 21, 470000, '2025-05-01', 'Xăng xe tháng');

-- ==========================================================================
-- THIẾT LẬP NGÂN SÁCH CHO NGƯỜI DÙNG 1
-- ==========================================================================

INSERT INTO budgets (user_id, limit_amount, month, year) VALUES
(1, 8000000, 3, 2025),
(1, 8000000, 4, 2025),
(1, 8000000, 5, 2025),
(1, 8500000, 6, 2025),
(1, 8500000, 7, 2025),
(1, 8500000, 8, 2025),
(1, 8500000, 9, 2025),
(1, 9000000, 10, 2025),
(1, 9000000, 11, 2025),
(1, 9500000, 12, 2025),
(1, 8000000, 1, 2026),
(1, 8000000, 2, 2026),
(1, 8500000, 3, 2026),
(1, 8500000, 4, 2026),
(1, 9000000, 5, 2026);

-- ==========================================================================
-- THIẾT LẬP NGÂN SÁCH CHO NGƯỜI DÙNG 2
-- ==========================================================================

INSERT INTO budgets (user_id, limit_amount, month, year) VALUES
(2, 6000000, 3, 2025),
(2, 6000000, 4, 2025),
(2, 6500000, 5, 2025);

-- ==========================================================================
-- THIẾT LẬP NGÂN SÁCH CHO NGƯỜI DÙNG 3
-- ==========================================================================

INSERT INTO budgets (user_id, limit_amount, month, year) VALUES
(3, 7000000, 3, 2025),
(3, 7000000, 4, 2025),
(3, 7500000, 5, 2025);
-- HOÀN TẤT
-- ==========================================================================

-- Kiểm tra dữ liệu đã được thêm
SELECT COUNT(*) as 'Tổng Users' FROM users;
SELECT COUNT(*) as 'Tổng Categories' FROM categories;
SELECT COUNT(*) as 'Tổng Transactions' FROM transactions;
SELECT COUNT(*) as 'Tổng Budgets' FROM budgets;

-- Kiểm tra dữ liệu chi tiêu tháng 3/2025 cho user 1
SELECT 
    SUM(CASE WHEN c.type = 'Thu' THEN t.amount ELSE 0 END) as 'Tổng Thu',
    SUM(CASE WHEN c.type = 'Chi' THEN t.amount ELSE 0 END) as 'Tổng Chi'
FROM transactions t
JOIN categories c ON t.category_id = c.id
WHERE t.user_id = 1 AND MONTH(t.transaction_date) = 3 AND YEAR(t.transaction_date) = 2025;
