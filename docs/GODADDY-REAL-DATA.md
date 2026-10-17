# GoDaddy: bộ cài dữ liệu thật

Dùng `var/godaddy-real-roster/install.sql` cho **database mới, trống** trong phpMyAdmin. File tạo schema, cấu hình CLB, một Admin mới và 21 cầu thủ Active. Không có tài khoản cầu thủ, trận đấu, kết quả hay thống kê mẫu. Tuan Nguyen giữ 99; Steven Ha được logic tìm số trống cấp **1**.

1. Sao lưu database GoDaddy hiện tại. Tạo database mới và gán database user trong cPanel.
2. Chọn database mới trong phpMyAdmin, Import `install.sql`. Không import vào database cũ; không DROP bất kỳ bảng nào.
3. Upload source trong `app/`, `public/` và root theo `DEPLOYMENT-SFTP.md`. Giữ cấu hình riêng của hosting.
4. Sửa `config/database.local.php` **trên hosting** sang database mới (hostname, tên database có prefix cPanel, username, password).
5. Xem tài khoản mới tại `var/godaddy-real-roster/admin-credentials.txt` trên máy này. Không upload file mật khẩu, SQL, `var/`, tests hoặc scripts vào web root. Đăng nhập Admin rồi đổi mật khẩu.
6. Mở Management → Players: 21 cầu thủ; thử thêm/sửa và nhập trùng 99 để kiểm tra thông báo. Khởi động lại ứng dụng không tạo dữ liệu mẫu.

Database cũ và Admin cũ được giữ nguyên trong database cũ. Admin mới có mật khẩu ngẫu nhiên, hash bằng PHP. Nếu cần giữ chính tài khoản Admin cũ trong database mới, cần export record từ database GoDaddy; bộ cài không có thông tin đó.

## Kiểm tra SQL sau import

```sql
SELECT full_name AS Name, jersey_number AS Jersey, is_active AS Active FROM players ORDER BY full_name;
SELECT COUNT(*) AS players, SUM(is_active=1) AS active_players,
       COUNT(DISTINCT jersey_key) AS unique_jerseys,
       SUM(jersey_number IS NULL OR TRIM(jersey_number)='') AS empty_jerseys,
       COUNT(DISTINCT full_name) AS unique_names FROM players;
```

Kết quả mong đợi: 21, 21, 21, 0, 21. Các bảng matches, match_availability, player_match_stats rỗng; users chỉ có Admin.

## Nâng cấp/reset database development cũ bằng CLI

Dừng các request ghi, sao lưu và cấu hình đúng database đích. Chạy:

```text
php scripts/migrate-jerseys.php
php scripts/reset-sample-and-import-real-players.php --development-reset
```

Migration 002 bổ sung khóa số áo chuẩn hóa và UNIQUE; DDL MySQL tự commit, nên tách khỏi transaction reset. Nếu có số áo trùng/rỗng, migration dừng để xử lý trước. Script reset nhận diện tên/ID mẫu và đối thủ/ID mẫu; từ chối dữ liệu không rõ hoặc roster đã import. Không dùng để reset database đang vận hành thật.

Reset sao lưu JSON riêng tư trong `var/`, xóa stats → availability → PLAYER accounts → matches → players rồi import 21 cầu thủ trong một transaction. Lỗi sẽ rollback dữ liệu. ADMIN, settings, login_attempts, schema và migrations được giữ. Không tắt foreign keys. Không DROP/TRUNCATE. Chạy trong maintenance để tránh ghi cạnh tranh khi reset; Add/Edit thông thường được UNIQUE bảo vệ khỏi request cạnh tranh.

Bộ cài mới có DDL nên không thể rollback toàn bộ schema bằng transaction MySQL; chỉ import vào database trống. Phần insert Admin và roster có transaction. Nếu phpMyAdmin báo lỗi, không đổi cấu hình website sang database chưa hoàn tất.

## File bàn giao

- website.zip: source website, giải nén vào thư mục website hiện tại theo hướng dẫn SFTP. Không xóa config/database.local.php của hosting; cập nhật file đó sang database mới. config/database.php trong ZIP chỉ có placeholder, không chứa mật khẩu local.
- install.sql: import bằng phpMyAdmin, không upload vào thư mục public.
- admin-credentials.txt: chỉ đọc tại máy local, không upload.


## Cầu thủ cập nhật ảnh đại diện

Cầu thủ đăng nhập tài khoản do Admin cấp tại Users, bấm tên mình ở cuối menu → **Ảnh đại diện** → chọn ảnh → **Lưu ảnh đại diện**. Có thể mở mục này từ nút **Cập nhật ảnh đại diện** trên hồ sơ cá nhân.

Ảnh JPG/PNG/WebP tối đa 2 MB, hiển thị trên hồ sơ và danh sách cầu thủ. Mỗi cầu thủ chỉ thay được ảnh của mình. PHP cần quyền ghi `public/uploads`; giữ file `.htaccess` trong thư mục này. Cấu hình `upload_max_filesize=2M` hoặc lớn hơn và `post_max_size=8M` hoặc lớn hơn. Sao lưu thư mục uploads cùng database.

**Chưa import bản cũ:** dùng install.sql mới trong bộ bàn giao, đã có cột ảnh.

**Đã import bản trước:** giữ dữ liệu hiện có; import riêng `003_player_photos.sql` một lần bằng phpMyAdmin (hoặc chạy `php scripts/migrate-player-photos.php`). Không import lại install.sql. Sau đó upload website.zip mới. Tài khoản/mật khẩu Admin không đổi.
