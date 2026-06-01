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
TRUNCATE TABLE wallets;
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
-- THÊM VÍ MẪU CHO NGƯỜI DÙNG
-- ==========================================================================

INSERT INTO wallets (user_id, name, balance) VALUES
(1, 'Ví Tiền Mặt', 0),
(2, 'Ví Tiền Mặt', 0),
(3, 'Ví Tiền Mặt', 0);

-- ==========================================================================
-- THÊM GIAO DỊCH THÁNG 3/2025 - NGƯỜI DÙNG 1
-- ==========================================================================

-- Lương (Thu) - category_id = 1
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2025-03-01', 'Lương tháng 3/2025');

-- Thưởng (Thu) - category_id = 2
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 2, 1, 2000000, '2025-03-15', 'Thưởng thăng chức');

-- Ăn uống (Chi) - category_id = 5
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 5, 1, 150000, '2025-03-02', 'Ăn trưa công ty'),
(1, 5, 1, 80000, '2025-03-03', 'Ăn tối'),
(1, 5, 1, 200000, '2025-03-05', 'Đi ăn nhà hàng'),
(1, 5, 1, 120000, '2025-03-08', 'Ăn cơm tập'),
(1, 5, 1, 100000, '2025-03-10', 'Ăn phở sáng'),
(1, 5, 1, 250000, '2025-03-12', 'Tiệc tối với bạn bè'),
(1, 5, 1, 150000, '2025-03-15', 'Ăn trưa công ty'),
(1, 5, 1, 180000, '2025-03-18', 'Nhà hàng SeaFood'),
(1, 5, 1, 90000, '2025-03-20', 'Cơm chiều'),
(1, 5, 1, 200000, '2025-03-25', 'Ăn uống cuối tháng');

-- Di chuyển (Chi) - category_id = 6
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 6, 1, 500000, '2025-03-01', 'Xăng xe tháng'),
(1, 6, 1, 100000, '2025-03-05', 'Đỗ xe'),
(1, 6, 1, 50000, '2025-03-08', 'Uber đi làm'),
(1, 6, 1, 50000, '2025-03-10', 'Taxi về nhà'),
(1, 6, 1, 120000, '2025-03-15', 'Bảo hiểm xe');

-- Nhà ở (Chi) - category_id = 7
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 7, 1, 3000000, '2025-03-01', 'Tiền nhà tháng 3'),
(1, 7, 1, 200000, '2025-03-20', 'Dọn vệ sinh');

-- Mua sắm (Chi) - category_id = 8
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 8, 1, 500000, '2025-03-05', 'Quần áo'),
(1, 8, 1, 800000, '2025-03-10', 'Giày thể thao'),
(1, 8, 1, 300000, '2025-03-18', 'Mỹ phẩm');

-- Y tế (Chi) - category_id = 9
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 9, 1, 200000, '2025-03-08', 'Khám bác sĩ'),
(1, 9, 1, 150000, '2025-03-22', 'Mua thuốc');

-- Giáo dục (Chi) - category_id = 10
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 10, 1, 500000, '2025-03-03', 'Khóa học online'),
(1, 10, 1, 300000, '2025-03-20', 'Sách lập trình');

-- Giải trí (Chi) - category_id = 11
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 11, 1, 250000, '2025-03-08', 'Xem phim rạp'),
(1, 11, 1, 180000, '2025-03-15', 'Game online'),
(1, 11, 1, 150000, '2025-03-25', 'KTV với bạn');

-- Hóa đơn điện/nước (Chi) - category_id = 12
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 12, 1, 250000, '2025-03-10', 'Tiền điện');

-- Internet (Chi) - category_id = 13
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 13, 1, 200000, '2025-03-01', 'Internet tháng');

-- ==========================================================================
-- THÊM GIAO DỊCH THÁNG 4-10/2025 - NGƯỜI DÙNG 1 (CÁC THÁNG KHÁC)
-- ==========================================================================

-- Tháng 4
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2025-04-01', 'Lương tháng 4/2025'),
(1, 5, 1, 160000, '2025-04-02', 'Ăn trưa'),
(1, 5, 1, 220000, '2025-04-08', 'Nhà hàng'),
(1, 6, 1, 500000, '2025-04-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2025-04-01', 'Tiền nhà tháng 4'),
(1, 12, 1, 260000, '2025-04-10', 'Tiền điện'),
(1, 13, 1, 200000, '2025-04-01', 'Internet tháng');

-- Tháng 5
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2025-05-01', 'Lương tháng 5/2025'),
(1, 2, 1, 1200000, '2025-05-17', 'Thưởng dự án'),
(1, 5, 1, 170000, '2025-05-03', 'Ăn trưa'),
(1, 6, 1, 520000, '2025-05-02', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2025-05-01', 'Tiền nhà tháng 5'),
(1, 12, 1, 270000, '2025-05-10', 'Tiền điện'),
(1, 13, 1, 200000, '2025-05-01', 'Internet tháng');

-- Tháng 6
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2025-06-01', 'Lương tháng 6/2025'),
(1, 2, 1, 1000000, '2025-06-20', 'Thưởng giữa năm'),
(1, 3, 1, 2500000, '2025-06-15', 'Thu freelance thiết kế'),
(1, 5, 1, 180000, '2025-06-02', 'Ăn trưa'),
(1, 6, 1, 530000, '2025-06-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2025-06-01', 'Tiền nhà tháng 6'),
(1, 12, 1, 280000, '2025-06-11', 'Tiền điện'),
(1, 13, 1, 210000, '2025-06-01', 'Internet tháng');

-- Tháng 7
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2025-07-01', 'Lương tháng 7/2025'),
(1, 5, 1, 210000, '2025-07-04', 'Ăn tối lễ quốc khánh'),
(1, 6, 1, 540000, '2025-07-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2025-07-01', 'Tiền nhà tháng 7'),
(1, 12, 1, 290000, '2025-07-10', 'Tiền điện'),
(1, 13, 1, 210000, '2025-07-01', 'Internet tháng');

-- Tháng 8
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2025-08-01', 'Lương tháng 8/2025'),
(1, 2, 1, 700000, '2025-08-16', 'Thưởng quý'),
(1, 5, 1, 190000, '2025-08-03', 'Ăn trưa'),
(1, 6, 1, 550000, '2025-08-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2025-08-01', 'Tiền nhà tháng 8'),
(1, 12, 1, 300000, '2025-08-10', 'Tiền điện'),
(1, 13, 1, 220000, '2025-08-01', 'Internet tháng');

-- Tháng 9
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2025-09-01', 'Lương tháng 9/2025'),
(1, 5, 1, 170000, '2025-09-05', 'Ăn trưa'),
(1, 6, 1, 560000, '2025-09-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2025-09-01', 'Tiền nhà tháng 9'),
(1, 12, 1, 310000, '2025-09-10', 'Tiền điện'),
(1, 13, 1, 220000, '2025-09-01', 'Internet tháng');

-- Tháng 10
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2025-10-01', 'Lương tháng 10/2025'),
(1, 3, 1, 1800000, '2025-10-18', 'Thu freelance nội dung'),
(1, 5, 1, 200000, '2025-10-04', 'Ăn trưa'),
(1, 6, 1, 570000, '2025-10-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2025-10-01', 'Tiền nhà tháng 10'),
(1, 12, 1, 320000, '2025-10-10', 'Tiền điện'),
(1, 13, 1, 220000, '2025-10-01', 'Internet tháng');

-- ==========================================================================
-- THÊM GIAO DỊCH THÁNG 11/2025 - NGƯỜI DÙNG 1
-- ==========================================================================

INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2025-11-01', 'Lương tháng 11/2025'),
(1, 5, 1, 210000, '2025-11-05', 'Ăn trưa công ty'),
(1, 6, 1, 560000, '2025-11-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2025-11-01', 'Tiền nhà tháng 11'),
(1, 12, 1, 330000, '2025-11-10', 'Tiền điện'),
(1, 13, 1, 220000, '2025-11-01', 'Internet tháng');

-- ==========================================================================
-- THÊM GIAO DỊCH THÁNG 12/2025 - NGƯỜI DÙNG 1
-- ==========================================================================

INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2025-12-01', 'Lương tháng 12/2025'),
(1, 2, 1, 1500000, '2025-12-18', 'Thưởng cuối năm'),
(1, 5, 1, 240000, '2025-12-06', 'Tiệc tất niên'),
(1, 6, 1, 580000, '2025-12-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2025-12-01', 'Tiền nhà tháng 12'),
(1, 12, 1, 340000, '2025-12-10', 'Tiền điện'),
(1, 13, 1, 220000, '2025-12-01', 'Internet tháng');

-- ==========================================================================
-- THÊM GIAO DỊCH THÁNG 1-4/2026 - NGƯỜI DÙNG 1
-- ==========================================================================

-- Tháng 1/2026
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2026-01-01', 'Lương tháng 1/2026'),
(1, 5, 1, 170000, '2026-01-04', 'Ăn trưa đầu năm'),
(1, 6, 1, 560000, '2026-01-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2026-01-01', 'Tiền nhà tháng 1'),
(1, 12, 1, 350000, '2026-01-10', 'Tiền điện'),
(1, 13, 1, 230000, '2026-01-01', 'Internet tháng');
-- Cập nhật số dư ví theo giao dịch đã nhập
UPDATE wallets w
JOIN (
    SELECT wallet_id, SUM(CASE WHEN LOWER(c.type) = 'Thu' THEN t.amount ELSE -t.amount END) AS balance
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    GROUP BY wallet_id
) tx ON w.id = tx.wallet_id
SET w.balance = tx.balance;
-- Tháng 2/2026
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2026-02-01', 'Lương tháng 2/2026'),
(1, 5, 1, 180000, '2026-02-05', 'Ăn trưa'),
(1, 6, 1, 570000, '2026-02-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2026-02-01', 'Tiền nhà tháng 2'),
(1, 12, 1, 360000, '2026-02-10', 'Tiền điện'),
(1, 13, 1, 230000, '2026-02-01', 'Internet tháng');

-- Tháng 3/2026
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2026-03-01', 'Lương tháng 3/2026'),
(1, 2, 1, 900000, '2026-03-22', 'Thưởng tháng 3'),
(1, 5, 1, 190000, '2026-03-07', 'Ăn trưa công ty'),
(1, 6, 1, 580000, '2026-03-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2026-03-01', 'Tiền nhà tháng 3'),
(1, 12, 1, 370000, '2026-03-10', 'Tiền điện'),
(1, 13, 1, 230000, '2026-03-01', 'Internet tháng');

-- Tháng 4/2026
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2026-04-01', 'Lương tháng 4/2026'),
(1, 5, 1, 185000, '2026-04-06', 'Ăn trưa'),
(1, 6, 1, 590000, '2026-04-01', 'Xăng xe tháng'),
(1, 7, 1, 3000000, '2026-04-01', 'Tiền nhà tháng 4'),
(1, 12, 1, 380000, '2026-04-10', 'Tiền điện'),
(1, 13, 1, 230000, '2026-04-01', 'Internet tháng');

-- ==========================================================================
-- THÊM GIAO DỊCH THÁNG 5/2026 - NGƯỜI DÙNG 1 (THÁNG HIỆN TẠI)
-- ==========================================================================

INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(1, 1, 1, 10000000, '2026-05-01', 'Lương tháng 5/2026'),
(1, 3, 1, 3000000, '2026-05-05', 'Thu freelance thiết kế website'),
(1, 5, 1, 160000, '2026-05-02', 'Ăn trưa công ty'),
(1, 5, 1, 95000, '2026-05-03', 'Ăn phở sáng'),
(1, 5, 1, 220000, '2026-05-05', 'Ăn tối nhà hàng Nhật'),
(1, 5, 1, 130000, '2026-05-07', 'Cơm trưa văn phòng'),
(1, 5, 1, 280000, '2026-05-09', 'Tiệc sinh nhật bạn'),
(1, 6, 1, 600000, '2026-05-01', 'Xăng xe tháng'),
(1, 6, 1, 75000, '2026-05-04', 'Grab đi họp'),
(1, 6, 1, 50000, '2026-05-08', 'Gửi xe'),
(1, 7, 1, 3000000, '2026-05-01', 'Tiền nhà tháng 5'),
(1, 8, 1, 1200000, '2026-05-03', 'Mua áo khoác mùa đông'),
(1, 8, 1, 450000, '2026-05-07', 'Giày chạy bộ giảm giá'),
(1, 9, 1, 350000, '2026-05-06', 'Khám răng định kỳ'),
(1, 10, 1, 800000, '2026-05-02', 'Khóa học React online'),
(1, 11, 1, 200000, '2026-05-04', 'Xem phim Avengers'),
(1, 11, 1, 150000, '2026-05-09', 'Netflix tháng 5'),
(1, 12, 1, 390000, '2026-05-10', 'Tiền điện'),
(1, 13, 1, 230000, '2026-05-01', 'Internet tháng');

-- ==========================================================================
-- THÊM GIAO DỊCH CHO NGƯỜI DÙNG 2 (MỞ RỘNG)
-- ==========================================================================

-- User 2: Categories: 15=Lương(Thu), 16=Ăn uống(Chi), 17=Di chuyển(Chi), 18=Nhà ở(Chi)
-- (User1 có 14 categories id=1..14, User2 bắt đầu từ id=15)
-- Tháng 3/2025
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(2, 15, 2, 8000000, '2025-03-01', 'Lương tháng 3'),
(2, 16, 2, 100000, '2025-03-05', 'Ăn trưa'),
(2, 16, 2, 150000, '2025-03-10', 'Nhà hàng'),
(2, 16, 2, 80000, '2025-03-15', 'Cà phê'),
(2, 17, 2, 400000, '2025-03-01', 'Xăng xe'),
(2, 18, 2, 2500000, '2025-03-01', 'Tiền nhà');

-- Tháng 4/2025
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(2, 15, 2, 8000000, '2025-04-01', 'Lương tháng 4'),
(2, 16, 2, 120000, '2025-04-03', 'Ăn trưa'),
(2, 16, 2, 180000, '2025-04-12', 'Ăn tối nhà hàng'),
(2, 16, 2, 90000, '2025-04-20', 'Cà phê với bạn'),
(2, 17, 2, 420000, '2025-04-01', 'Xăng xe'),
(2, 17, 2, 60000, '2025-04-15', 'Grab đi chợ'),
(2, 18, 2, 2500000, '2025-04-01', 'Tiền nhà');

-- Tháng 5/2025
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(2, 15, 2, 8000000, '2025-05-01', 'Lương tháng 5'),
(2, 16, 2, 110000, '2025-05-04', 'Ăn trưa'),
(2, 16, 2, 200000, '2025-05-10', 'Tiệc sinh nhật'),
(2, 17, 2, 430000, '2025-05-01', 'Xăng xe'),
(2, 18, 2, 2500000, '2025-05-01', 'Tiền nhà');

-- ==========================================================================
-- THÊM GIAO DỊCH CHO NGƯỜI DÙNG 3
-- ==========================================================================

-- User 3: Categories: 19=Lương(Thu), 20=Ăn uống(Chi), 21=Di chuyển(Chi)
-- (User1: 14 cats id=1..14, User2: 4 cats id=15..18, User3 bắt đầu từ id=19)
-- Tháng 3/2025
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(3, 19, 3, 12000000, '2025-03-01', 'Lương tháng 3/2025'),
(3, 20, 3, 130000, '2025-03-03', 'Ăn trưa công ty'),
(3, 20, 3, 170000, '2025-03-08', 'Ăn tối với đồng nghiệp'),
(3, 20, 3, 90000, '2025-03-12', 'Cà phê sáng'),
(3, 20, 3, 250000, '2025-03-18', 'Nhà hàng cuối tuần'),
(3, 21, 3, 450000, '2025-03-01', 'Xăng xe tháng'),
(3, 21, 3, 80000, '2025-03-10', 'Grab đi làm');

-- Tháng 4/2025
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(3, 19, 3, 12000000, '2025-04-01', 'Lương tháng 4/2025'),
(3, 20, 3, 140000, '2025-04-05', 'Ăn trưa'),
(3, 20, 3, 200000, '2025-04-15', 'Đi ăn buffet'),
(3, 21, 3, 460000, '2025-04-01', 'Xăng xe tháng'),
(3, 21, 3, 100000, '2025-04-12', 'Taxi về quê');

-- Tháng 5/2025
INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note) VALUES
(3, 19, 3, 12000000, '2025-05-01', 'Lương tháng 5/2025'),
(3, 20, 3, 160000, '2025-05-06', 'Ăn trưa'),
(3, 20, 3, 95000, '2025-05-10', 'Cà phê'),
(3, 21, 3, 470000, '2025-05-01', 'Xăng xe tháng');

-- ==========================================================================
-- THIẾT LẬP NGÂN SÁCH CHO NGƯỜI DÙNG 1
-- ==========================================================================

INSERT INTO budgets (user_id, limit_amount, month, year) VALUES
(1, 8000000, 1, 3, 2025),
(1, 8000000, 1, 4, 2025),
(1, 8000000, 1, 5, 2025),
(1, 8500000, 1, 6, 2025),
(1, 8500000, 1, 7, 2025),
(1, 8500000, 1, 8, 2025),
(1, 8500000, 1, 9, 2025),
(1, 9000000, 1, 10, 2025),
(1, 9000000, 1, 11, 2025),
(1, 9500000, 1, 12, 2025),
(1, 8000000, 1, 1, 2026),
(1, 8000000, 1, 2, 2026),
(1, 8500000, 1, 3, 2026),
(1, 8500000, 1, 4, 2026),
(1, 9000000, 1, 5, 2026);

-- ==========================================================================
-- THIẾT LẬP NGÂN SÁCH CHO NGƯỜI DÙNG 2
-- ==========================================================================

INSERT INTO budgets (user_id, limit_amount, month, year) VALUES
(2, 6000000, 2, 3, 2025),
(2, 6000000, 2, 4, 2025),
(2, 6500000, 2, 5, 2025);

-- ==========================================================================
-- THIẾT LẬP NGÂN SÁCH CHO NGƯỜI DÙNG 3
-- ==========================================================================

INSERT INTO budgets (user_id, limit_amount, month, year) VALUES
(3, 7000000, 3, 3, 2025),
(3, 7000000, 3, 4, 2025),
(3, 7500000, 3, 5, 2025);

-- ==========================================================================
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
