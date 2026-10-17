# Viet Montreal United — Club management

Ứng dụng PHP 8.1+, MySQL 8+/MariaDB 10.4+, PDO, Bootstrap. Cấu hình database qua `config/database.local.php` hoặc DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD.

## Cài trên GoDaddy

Xem [hướng dẫn dữ liệu thật](docs/GODADDY-REAL-DATA.md) và [hướng dẫn upload](DEPLOYMENT-SFTP.md). Bộ SQL mới nằm trong `var/godaddy-real-roster/install.sql`, dành riêng database trống. Tài khoản Admin ngẫu nhiên nằm trong file riêng tư cùng thư mục. Không upload file mật khẩu.

Bộ cài gồm đúng 21 cầu thủ Active, không có dữ liệu mẫu. Tuan Nguyen = 99; Steven Ha = 1 (tạm thời, tự tìm số chưa dùng). Không tự tạo tài khoản Player; Admin tạo tại Users khi cần.

## Cài mới bằng CLI

Đặt ADMIN_PASSWORD (12–72 ký tự) trong môi trường, cấu hình database, rồi chạy `php scripts/install.php`. Chỉ chạy trên database trống. Script tạo schema, một Admin và roster thật. Chạy local bằng `php -S 127.0.0.1:8765 -t public`.

## Nâng cấp database có sẵn

Với schema cũ còn birth_date, chạy `php scripts/migrate-players.php` trước. Sau đó chạy `php scripts/migrate-jerseys.php` khi đã dừng ghi dữ liệu. Migration không tự xóa hay tự sửa các số áo trùng; lỗi sẽ cần được xử lý trước khi tiếp tục.

Để reset dữ liệu development mẫu theo yêu cầu CLB: `php scripts/reset-sample-and-import-real-players.php --development-reset`. Xem chi tiết transaction, backup, kiểm tra nhận diện dữ liệu và bảo toàn Admin trong hướng dẫn GoDaddy. Không chạy trên dữ liệu thật.

## Số áo và cầu thủ

Form gồm Họ và Tên, Số áo, Năm sinh, Active. Số áo vẫn là VARCHAR(20), hỗ trợ 7, 99, 2003, Y-07 và 007. Giá trị hiển thị được giữ nguyên. So sánh duy nhất bỏ khoảng trắng hai đầu bằng SQL TRIM và không phân biệt hoa thường bằng LOWER. 007 và 7 là hai chuỗi khác nhau. Áp dụng cho cả cầu thủ Active/Inactive.

Admin Add/Edit chặn số áo trùng và hiển thị tên cầu thủ đang giữ số áo. Khóa generated jersey_key và UNIQUE trong database bảo vệ cả request đồng thời; lỗi constraint được chuyển thành thông báo validation. Số áo của chính cầu thủ được phép giữ nguyên khi Edit.

## Không có startup seed

Bootstrap chỉ kết nối và đọc settings. `database/sample.sql` chỉ còn chú thích; `scripts/generate-sample.php` đã disable. Bộ fixture cũ chỉ nằm trong `tests/fixtures/demo.sql`, không được installer hoặc startup gọi.

## Kiểm thử

`tests/prepare-roster.php` tạo database kiểm thử mới trên instance MariaDB riêng tại 127.0.0.1:3311; không dùng cấu hình database thật. Script này chỉ dành môi trường phát triển cục bộ. `tests/workflows.php` là bộ regression HTTP cũ, yêu cầu database có hậu tố `_test` và fixture test, không chạy trên database thật. Kết quả mới và các giới hạn kiểm tra được ghi trong `docs/CODEX-PROGRESS.md`.

Private source, scripts, SQL, cấu hình và backup phải nằm ngoài web root hoặc được Apache chặn theo `.htaccess`. Không upload `var/` và `tests/`. Sao lưu database GoDaddy trước khi chuyển sang database mới.

## Ảnh cầu thủ

Cầu thủ đăng nhập → My account (bấm tên cuối menu) → chọn ảnh JPG/PNG/WebP tối đa 2 MB → Lưu ảnh đại diện. Ảnh hiển thị công khai trên hồ sơ/danh sách. Chỉ chính cầu thủ được thay ảnh. Với database cũ, chạy `php scripts/migrate-player-photos.php` trước khi cập nhật source; cài mới đã có cột ảnh. Xem hướng dẫn GoDaddy về quyền ghi uploads và giới hạn PHP.

## Bản local để duyệt trước deploy

Mở http://127.0.0.1:8780/. Tài khoản Admin và cầu thủ An Tran nằm trong `var/local-preview-accounts.txt`. Database riêng `vmu_preview` chạy tại 127.0.0.1:3312; không ảnh hưởng GoDaddy. Nếu đã tắt dịch vụ, chạy `./start-preview.ps1` từ PowerShell trong thư mục dự án. Tài khoản cầu thủ dùng thử chỉ có ở local, không được đưa vào SQL deploy.
