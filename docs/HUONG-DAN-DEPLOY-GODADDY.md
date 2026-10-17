# HƯỚNG DẪN DEPLOY VIET MONTREAL UNITED LÊN GODADDY

Bản đóng gói: 2026-09-30. Dành cho GoDaddy Web Hosting (cPanel), PHP 8.1 trở lên, MySQL 8+ hoặc MariaDB 10.4+. Đã kiểm thử local PHP 8.4/MariaDB 10.4; chưa triển khai lên GoDaddy.

## 1. Giải nén gói trên máy tính

Giải nén VMU-GoDaddy-2026-09-30.zip trên máy của bạn. Gói gồm:

- website.zip: toàn bộ source cần chạy website (app, public, config mặc định, index.php, các .htaccess).
- database/install.sql: schema + cấu hình CLB + Admin + 21 cầu thủ thật, hỗ trợ ảnh đại diện và số áo unique.
- database/verify.sql: truy vấn chỉ đọc để kiểm tra sau import.
- database.local.php.example: mẫu cấu hình kết nối MySQL trên hosting.
- PRIVATE/admin-credentials.txt: username/password Admin đã bàn giao. Chỉ đọc ở máy cá nhân.
- HUONG-DAN-DEPLOY.md: tài liệu này.
- SHA256SUMS.txt: checksum các file trong gói.

Chỉ upload website.zip qua File Manager. Không upload nguyên gói ngoài hoặc thư mục PRIVATE lên public_html. File SQL được chọn từ máy tính bằng chức năng Import của phpMyAdmin.

Đây là bộ cài sạch, không phải bản dump database preview: không có account thử nghiệm an.tran, dữ liệu test, log hoặc mật khẩu database local. Database có một Admin, 21 cầu thủ Active, không có trận/attendance/stats. Tuan Nguyen = 99, Steven Ha = 1 tạm thời. Không tự tạo 21 tài khoản Player.

## 2. Mở cPanel, xác định thư mục website và sao lưu

GoDaddy → My Products → Web Hosting → Manage → cPanel Admin. Trong Domains, tìm vmu.vietcanadianlive.com và ghi lại Document Root thực tế. Cấu hình SFTP dự án trước đây dùng /public_html/vmu/; chỉ dùng đường dẫn đó nếu trùng cấu hình domain hiện tại.

Trong File Manager, bật Settings → Show Hidden Files (dotfiles) để thấy .htaccess. Sao lưu source hiện tại, config/database.local.php và public/uploads. Tải backup về máy hoặc giữ ngoài public_html. Trong phpMyAdmin, chọn database cũ → Export → SQL, tải về máy. Không xóa database cũ để có thể quay lại nếu cần.

## 3. Tạo database MỚI và database user

Trong cPanel, mở MySQL Database Wizard (hoặc mục quản lý MySQL Databases nếu giao diện khác). Tạo database mới, ví dụ vmu_real; tạo database user và mật khẩu mạnh; gán user này vào database mới với ALL PRIVILEGES để cài đặt.

Ghi lại TÊN ĐẦY ĐỦ có prefix cPanel, ví dụ account_vmu_real và account_vmuuser; không chỉ ghi phần vmu_real. Mật khẩu database do bạn vừa tạo khác với mật khẩu đăng nhập Admin website. Host thường là localhost; dùng hostname MySQL được GoDaddy cung cấp nếu tài khoản hiển thị khác.

## 4. Import database

cPanel → phpMyAdmin → chọn database mới ở cột trái. Xác nhận database chưa có bảng. Chọn Import → Choose File → database/install.sql từ gói trên máy → format SQL → Go/Import. Chờ thông báo thành công, không bỏ qua lỗi.

Không import vào database đang có bảng. Không import thêm schema.sql, sample.sql hoặc các migration: install.sql đã đầy đủ, gồm cả cột ảnh photo_path và UNIQUE số áo. Nếu import lỗi giữa chừng, đừng kết nối website vào database đó; lưu thông báo lỗi để xử lý, vì DDL MySQL không rollback như INSERT.

Sau import, mở tab SQL, dán nội dung database/verify.sql và chạy. Mong đợi:
- players = 21, active_players = 21, unique_jerseys = 21, unique_names = 21, empty_jerseys = 0.
- users chỉ có Admin; matches, availability, statistics đều = 0.
- Tuan Nguyen áo 99; Steven Ha áo 1.

Nếu đã cài database thật bằng bản SQL trước và đã nhập dữ liệu mới, không dùng install.sql để ghi đè; cần nâng cấp riêng, giữ dữ liệu đó.

## 5. Upload source website

Trong File Manager, mở đúng thư mục ứng dụng. Với cấu trúc đã dùng trước đây, đó là /public_html/vmu/. Upload website.zip vào đây → chọn file → Extract tại chính thư mục này. Khi được hỏi ghi đè source, dùng bản mới.

Sau giải nén phải có:

    /public_html/vmu/index.php
    /public_html/vmu/.htaccess
    /public_html/vmu/app/
    /public_html/vmu/config/database.php
    /public_html/vmu/public/index.php
    /public_html/vmu/public/.htaccess
    /public_html/vmu/public/uploads/.htaccess

Không có thêm lớp website/ hoặc TeamManagement/ ở giữa. Nếu Document Root đang trỏ /public_html/vmu/public, vẫn giải nén toàn bộ source vào thư mục cha /public_html/vmu. Không đổi Document Root đang hoạt động chỉ để khớp ví dụ này.

Bản ZIP không chứa database.local.php nên không tự ghi đè cấu hình hosting hiện có. Giữ ảnh thật trong public/uploads; không xóa thư mục này khi cập nhật. Xóa website.zip khỏi hosting sau khi giải nén xong.

## 6. Cấu hình kết nối database

Trong File Manager, mở config/database.local.php. Nếu chưa có, tạo file này bằng cách copy nội dung database.local.php.example. Thay các giá trị:

```php
<?php
return [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'account_vmu_real',
    'username' => 'account_vmuuser',
    'password' => 'MAT_KHAU_DATABASE_BAN_VUA_TAO',
    'timezone' => 'America/Toronto',
];
```

Dùng đúng thông tin ở bước 3, không dùng thông tin local, cổng 3312 hay mật khẩu Admin. Nếu mật khẩu có dấu nháy đơn hoặc dấu gạch chéo ngược, cần escape đúng trong chuỗi PHP. Lưu file.

## 7. PHP, upload ảnh và truy cập

Trong phần cấu hình PHP của hosting, chọn PHP 8.1+ và bật pdo_mysql, mbstring, fileinfo; ứng dụng cần session và getimagesize. Đặt upload_max_filesize tối thiểu 2M, post_max_size tối thiểu 8M. Giới hạn của ứng dụng vẫn là 2 MB mỗi ảnh JPG/PNG/WebP.

public/uploads cần PHP có quyền ghi (thường thư mục 755, tùy ownership hosting); giữ nguyên .htaccess chặn thực thi. Dùng HTTPS của domain.

Mở https://vmu.vietcanadianlive.com. Nếu domain trỏ thư mục ứng dụng gốc, index.php sẽ chuyển tới public/index.php; nếu domain trỏ trực tiếp public, website hiển thị ngay. Trong cấu trúc ứng dụng gốc, thử /app/functions.php và /config/database.local.php phải bị chặn 403. Nếu không bị chặn, cần xử lý cấu hình bảo vệ thư mục hoặc chuyển Document Root sang public trước khi dùng thật.

## 8. Đăng nhập và kiểm tra

Đọc PRIVATE/admin-credentials.txt trên máy cá nhân, đăng nhập username admin và mật khẩu trong file. Vào My account đổi mật khẩu sau lần đăng nhập đầu. Tài khoản Admin cũ vẫn nằm trong database cũ; database mới dùng Admin trong bộ cài này.

Kiểm tra:
1. Management → Players: đủ 21 người; Steven Ha = 1, Tuan Nguyen = 99.
2. Edit Steven Ha, thử số áo 99: phải bị từ chối và thông báo 99 đang thuộc Tuan Nguyen. Không cần đổi số nếu chưa có số chính thức mới.
3. Management → Users: chọn cầu thủ Active, tạo username/password riêng. Làm tương tự cho những cầu thủ cần đăng nhập.
4. Đăng xuất, đăng nhập tài khoản cầu thủ vừa tạo → bấm tên cuối menu → Ảnh đại diện → chọn JPG/PNG/WebP dưới 2 MB → Lưu ảnh đại diện.
5. Ảnh hiển thị trên hồ sơ, danh sách cầu thủ và account. Kiểm tra thêm bằng điện thoại.
6. Trang Matches/Statistics lúc đầu không có dữ liệu mẫu. Admin tạo trận thật khi cần.

## Xử lý lỗi thường gặp

- Database is not configured / lỗi 503: kiểm tra database.local.php, tên có prefix, mật khẩu, host và quyền database user. Xem PHP error log trong cPanel.
- Table already exists khi import: đã chọn database không trống; không tiếp tục ghi đè, dùng database mới/trống.
- Không tìm thấy cột photo_path hoặc jersey_key: source đang kết nối database cũ chưa nâng cấp; kiểm tra tên database trong config.
- Trang trắng/500: kiểm tra phiên bản/extension PHP và Error Log. Không bật hiển thị chi tiết lỗi cho mọi người trên website thật.
- Không upload được ảnh: kiểm tra loại/kích thước ảnh, upload_max_filesize, post_max_size và quyền ghi public/uploads.
- Domain hiển thị website khác/404: đối chiếu Domains → Document Root và vị trí giải nén; tránh thư mục lồng thêm một cấp.

Nếu cần quay lại: khôi phục source và config đã sao lưu để trỏ về database cũ; không xóa database mới khi chưa lưu dữ liệu phát sinh. Không tự tạo lại dữ liệu mẫu.

Tài liệu chính thức: [tạo database](https://help-center.dc-aws.godaddy.com/help/create-a-mysql-database-in-my-web-hosting-cpanel-16016), [import SQL](https://www.godaddy.com/en-ca/help/import-sql-files-into-a-mysql-database-6802), [upload bằng File Manager](https://help.dc-aws.godaddy.com/help/upload-files-using-my-web-hosting-cpanel-file-manager-3239).

## Cập nhật 2026-10-01
Dùng gói VMU-GoDaddy-2026-10-01.zip thay cho gói ngày 30/09 vì gói cũ thiếu config/match-venue.php. Nếu đã deploy và gặp lỗi require_once file này, chỉ giải nén VMU-fix-match-venue-2026-10-01.zip vào thư mục ứng dụng vmu/. Không import lại database hoặc ghi đè database.local.php.

