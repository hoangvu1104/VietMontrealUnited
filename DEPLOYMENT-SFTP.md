# Deploy SFTP lên GoDaddy

## 1. Cấu hình trong VS Code

Mở **đúng thư mục `D:\VietMontrealUnited\TeamManagement`** bằng File → Open Folder. Cài extension **SFTP**, publisher **Natizyskunk** (`Natizyskunk.sftp`); project đã thêm gợi ý extension, chưa tự cài.

Chỉnh **`.vscode/sftp.json`**:

| Thuộc tính | Giá trị cần điền |
|---|---|
| `host` | Thay `YOUR_GODADDY_SFTP_HOST` bằng host/IP SFTP GoDaddy |
| `port` | `22`, hoặc cổng SSH được hosting cung cấp |
| `username` | Thay `YOUR_GODADDY_USERNAME` |
| `password` | Thay `YOUR_GODADDY_PASSWORD` |
| `remotePath` | `/public_html/vmu/` |

`host` không kèm `https://` hoặc đường dẫn. File `.vscode/sftp.example.json` chứa placeholder để lưu trong Git. File thật `.vscode/sftp.json` được `.gitignore` loại trừ và SFTP cũng không upload `.vscode/`. Không điền mật khẩu thật vào file example. Nếu chuyển máy, copy example thành `sftp.json` rồi điền lại.

## 2. Đường dẫn upload của project này

`context: "."` ánh xạ **nội dung thư mục project** trực tiếp vào `remotePath`:

```text
LOCAL                           REMOTE
index.php                       /public_html/vmu/index.php
.htaccess                       /public_html/vmu/.htaccess
app/                            /public_html/vmu/app/
config/database.php             /public_html/vmu/config/database.php
public/index.php                /public_html/vmu/public/index.php
public/assets/                  /public_html/vmu/public/assets/
public/.htaccess                /public_html/vmu/public/.htaccess
public/uploads/.htaccess        /public_html/vmu/public/uploads/.htaccess
```

Không có thư mục `/public_html/vmu/TeamManagement/`. Project thực tế không có `admin/`, `player/`, `assets/` ở root; các trang Admin/Player nằm trong `app/views`, assets nằm trong `public/assets`. Giữ nguyên đường dẫn để PHP hiện tại hoạt động; không đổi context thành `public` hoặc kéo cả thư mục project vào Remote Explorer.

Theo code hiện tại, mở `https://vmu.vietcanadianlive.com` sẽ chuyển tới `/public/index.php`. Đây là hành vi sẵn có; cấu hình SFTP không thay đổi URL routing hay document root. Document root của subdomain cần trỏ đúng thư mục này trên hosting.

## 3. Test kết nối trước khi upload

1. Điền thông tin thật trong `.vscode/sftp.json` và lưu.
2. Mở file **`index.php` ở root** trong editor.
3. Nhấn `Ctrl+Shift+P` → **SFTP: List Active Folder**. Đây là thao tác liệt kê, không upload code.
4. Kiểm tra danh sách đúng thư mục `/public_html/vmu/`. Xem **View → Output → SFTP** nếu có lỗi.

Nếu đăng nhập được nhưng báo không tìm thấy thư mục, kiểm tra đường dẫn tuyệt đối mà tài khoản SFTP nhìn thấy. Một số tài khoản có thể cần `/home/USERNAME/public_html/vmu/`; chỉ sửa `remotePath` sau khi xác nhận với hosting. Nếu connection refused/timeout, kiểm tra host, cổng và trạng thái SSH/SFTP của tài khoản GoDaddy; lỗi authentication cần kiểm tra tài khoản SSH/SFTP.

## 4. Upload lần đầu

1. Giữ file **`index.php` ở root** đang mở.
2. `Ctrl+Shift+P` → **SFTP: Upload Active Folder** để upload nội dung root cùng các thư mục con theo ignore.
3. Đợi Output → SFTP hoàn tất. Xác nhận có `/public_html/vmu/index.php`, `app/`, `config/`, `public/` và các file `.htaccess`.

**Không dùng Force Upload**: lệnh này bỏ qua ignore, có thể gửi cấu hình riêng tư và backup lên server.

Thiết lập server một lần vẫn cần thực hiện riêng:

- Tạo `/public_html/vmu/config/database.local.php` trực tiếp trên hosting với thông tin MySQL **GoDaddy**. File local cùng tên không được upload, nên các lần sync sau không ghi đè cấu hình server.
- Tạo/import database qua phpMyAdmin theo README. SFTP không import SQL và không đồng bộ database. Thư mục `database/` được loại khỏi upload source.
- Đảm bảo PHP có quyền ghi `/public_html/vmu/public/uploads/`.
- Vì giữ private source dưới document root theo yêu cầu, cần Apache áp dụng `.htaccess` gốc hiện có. Sau upload, truy cập `/app/functions.php` và `/config/database.php` qua trình duyệt phải trả về **403**, không được lộ source/config. Nếu `.htaccess` không được áp dụng, cần chỉnh hosting trước khi đưa site vào sử dụng.

## 5. Cập nhật những file thay đổi

- **Một file:** mở file → `Ctrl+Shift+P` → **SFTP: Upload Active File**.
- **Toàn project:** mở `index.php` root → **SFTP: Sync Local -> Remote**. Với `update: true`, file mới và file local có thời gian sửa mới hơn sẽ được gửi; đây là so sánh thời gian sửa, không phải nội dung. Nếu checkout/revert giữ timestamp cũ hoặc đồng hồ server lệch, dùng Upload Active File/Folder để gửi bản đã chọn.
- **Tự upload lúc lưu:** sau khi test đúng đích, có thể đổi `uploadOnSave` thành `true`. Mặc định `false` để việc lưu code chưa hoàn chỉnh không tự deploy.
- **Upload Changed Files** dựa trên thay đổi so với Git commit, không phải lần deploy gần nhất. Project hiện chưa có repository Git; dùng hai cách đầu. Khi dùng Git sau này, không coi lệnh này là lịch sử deployment.

`syncOption.delete: false` giữ file có riêng trên server. File xóa/đổi tên ở local sẽ không tự bị xóa trên server; xử lý file cũ riêng khi cần. Không dùng sync hai chiều để deploy local lên production.

## 6. Những gì bị loại trừ

Git/editor metadata; `node_modules`; log/cache; ZIP và backup; `.env`/private keys; cấu hình database local; toàn bộ `var/` (ảnh kiểm thử, backup dữ liệu, staging, công cụ browser); `tests/`, `scripts/`, `database/`, tài liệu; script khởi chạy local và metadata npm.

Các ảnh runtime trong `public/uploads/` không được gửi; **`public/uploads/.htaccess` vẫn được upload**. Logo CLB ở `public/assets/images/` vẫn được gửi bình thường. Các file `.htaccess` không bị ignore toàn cục.

## Trạng thái bàn giao

Đã tạo cấu hình local và template chỉ chứa placeholder, cùng quy tắc Git ignore. Chưa kết nối/upload lên GoDaddy vì chưa có thông tin đăng nhập thật. Không thay đổi PHP, database hoặc chức năng website.

Tham khảo chính thức: [cấu hình SFTP](https://github.com/Natizyskunk/vscode-sftp/wiki/Configuration), [các lệnh upload và sync](https://github.com/Natizyskunk/vscode-sftp/wiki/Commands).

## GitHub Actions — tự deploy GoDaddy

Push lên nhánh main kích hoạt .github/workflows/deploy-godaddy.yml, tương tự Phở Mivy. Commit chỉ ở local chưa kích hoạt deployment.

Trong GitHub Settings > Environments, tạo production. Thêm secrets: SFTP_HOST, SFTP_USER, SFTP_PUBLIC_ROOT (đường dẫn tuyệt đối tới thư mục VMU, ví dụ /home/CPANEL_USER/public_html/vmu), SFTP_PRIVATE_KEY (private key SSH không có passphrase), SFTP_KNOWN_HOSTS (host key đã xác minh của GoDaddy). Key phải được authorize trong cPanel. Không dùng đường dẫn website Phở Mivy.

Thêm repository variable GODADDY_DEPLOY_ENABLED=true sau khi xác nhận đủ secrets và đúng đường dẫn VMU. Khi chưa bật, job deploy được bỏ qua.

Workflow kiểm tra cú pháp PHP và danh sách tools/deploy-manifest.json trước khi upload SFTP. Chỉ 34 file ứng dụng trong manifest được gửi; config/database.local.php, ảnh upload, SQL, backup và dữ liệu không được gửi hoặc xóa. Hosting phải có sẵn database.local.php. Không chạy migration hay import SQL. File bị xóa khỏi source không tự xóa trên hosting. Upload không atomic; nếu lỗi giữa chừng, kiểm tra log và chạy lại workflow sau khi xử lý lỗi.

Kiểm tra local: pwsh -File tools/deploy-sftp.ps1 -DryRun.

## 2026-10-17 — FTPS tự động đã cấu hình

Workflow hiện dùng explicit FTPS port 21 qua tools/deploy-ftps.py (thư viện chuẩn Python), thay cho SSH/SFTP. Khi push main hoặc chạy workflow thủ công, job kiểm tra PHP và manifest rồi upload 34 file. Production environment chứa FTP_HOST, FTP_PORT, FTP_USER, FTP_PASSWORD, FTP_ROOT; repository variable GODADDY_DEPLOY_ENABLED=true đã bật. Mật khẩu không nằm trong source.

ftp.vietcanadianlive.com hiện không có DNS. Đã xác minh hostname máy chủ từ redirect cPanel và đăng nhập FTPS thành công với kiểm tra chứng chỉ đầy đủ: p3plzcpnl503791.prod.phx3.secureserver.net. FTP root / là thư mục VMU. Không thêm /vmu vào đường dẫn FTP. Script yêu cầu app/config/public và config/database.local.php tồn tại trước khi upload. Không xóa file server, không import SQL, không thay ảnh upload hay cấu hình database riêng. Thêm file ứng dụng mới thì cập nhật tools/deploy-manifest.json.
