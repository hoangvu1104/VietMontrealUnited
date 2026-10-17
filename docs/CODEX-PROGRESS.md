# CODEX PROGRESS — dữ liệu thật CLB

Ngày: 2026-09-29, America/Toronto.

## Phạm vi đã thống nhất

Yêu cầu ban đầu reset database development. Kết nối local vmuuser bị MySQL từ chối. Người dùng sau đó xác nhận database đang ở GoDaddy và yêu cầu tạo mới toàn bộ để tự upload. Vì vậy kết quả bàn giao là **bộ cài database mới + source website**, đã kiểm thử bằng MariaDB 10.4 trên instance riêng 127.0.0.1:3311. Không truy cập, xóa hoặc thay đổi database GoDaddy.

## Dữ liệu mẫu và reset

Script `scripts/reset-sample-and-import-real-players.php` có transaction, backup riêng tư, kiểm tra dữ liệu nhận diện mẫu, bảo toàn Admin/cấu hình, từ chối dữ liệu lạ và từ chối reset lại roster thật. Migration DDL được tách riêng vì MySQL implicit commit.

Đã chạy reset trên fixture test (không phải GoDaddy):

| Bảng | Records đã xóa trong test |
|---|---:|
| player_match_stats | 112 |
| match_availability | 220 |
| users (chỉ PLAYER) | 24 |
| matches | 11 |
| players | 24 |

Thứ tự xóa đúng như bảng; không tắt foreign keys, không DROP/TRUNCATE. Goals/assists nằm trong player_match_stats; kết quả mẫu nằm trong matches. Tổng thống kê tính bằng query nên không có bảng tổng khác cần xóa.

Giữ nguyên mọi cột của ADMIN (gồm password hash), settings, login_attempts, schema và migrations. Không có bảng roles riêng; quyền ADMIN/PLAYER vẫn giữ trong schema. Script từ chối Admin đang liên kết player và các bảng chưa nhận diện.

Bộ cài mới không xóa records: tạo database trống với một Admin mới và cấu hình hệ thống. Admin GoDaddy cũ được giữ trong database cũ; không thể sao chép Admin cũ khi chưa có export. Mật khẩu Admin mới ngẫu nhiên trong `var/godaddy-real-roster/admin-credentials.txt`, không nằm trong source ZIP.

## Roster đã import và xác minh trên test

| Name | Jersey | Active |
|---|---|---|
| An Tran | 2 | true |
| An Vinh | 4 | true |
| Danny Truong Dan | 6 | true |
| Kevin Tran Tai | 7 | true |
| Minh Luong | 8 | true |
| Nguyen Vu Thang | 9 | true |
| Tony Vinh Tuong | 10 | true |
| Cuong Le | 11 | true |
| Vu Ngoc Hai Nam | 13 | true |
| Nguyen Nguyen | 15 | true |
| Bui Quang | 16 | true |
| Minh Tri Ng | 17 | true |
| Son Nguyen | 18 | true |
| Duy Khoa Huynh | 19 | true |
| Hoang Binh | 22 | true |
| Tim Nguyen | 23 | true |
| Steven Hoang | 24 | true |
| Kien Ho | 29 | true |
| Muriel Luong Dat | 86 | true |
| Tuan Nguyen | 99 | true |
| Steven Ha | 1 | true |

Steven Ha nhận **1** qua tìm số dương chưa dùng từ 1; không hard-code riêng theo tên. Roster nguồn vẫn chứa 99 cho cả hai người; allocator giữ số cho người xuất hiện trước. Dữ liệu SQL thực tế: 21 cầu thủ, 21 Active, 21 số áo unique, 21 tên unique, không số áo NULL/rỗng; matches/availability/stats rỗng, không Player account mẫu.

## Source đã sửa/thêm

- `app/actions.php`: Add/Edit kiểm tra số áo trùng, loại trừ chính player khi Edit, bắt lỗi 1062 do request cạnh tranh và trả validation rõ ràng.
- `app/jerseys.php`: tìm chủ số áo, assertion, allocator dùng lại cho import/thêm cầu thủ; roster resolver cho cài mới/export.
- `app/bootstrap.php`: nạp helper; loại hướng dẫn import sample khỏi thông báo lỗi.
- `database/schema.sql`: jersey_number vẫn VARCHAR(20); generated jersey_key LOWER(TRIM(...)), collation utf8mb4_bin, UNIQUE và CHECK nonempty.
- `database/migrations/002_unique_jerseys.sql`, `scripts/migrate-jerseys.php`: nâng cấp database cũ, không sửa/xóa dữ liệu để ép migration thành công.
- `database/real-players.php`: danh sách nguồn 21 người.
- `scripts/database-connection.php`: kết nối CLI dùng cấu hình hiện có.
- `scripts/reset-sample-and-import-real-players.php`: reset development có guard/transaction/backup/report.
- `scripts/install.php`: bỏ seed demo, tạo roster thật và Admin từ ADMIN_PASSWORD.
- `scripts/export-godaddy.php`: tạo SQL cài mới và mật khẩu riêng tư, từ chối ghi đè export đã có.
- `scripts/generate-sample.php`: disabled, không sinh lại SQL mẫu.
- `database/sample.sql`: chỉ còn chú thích, không có INSERT.
- `tests/fixtures/demo.sql`: fixture cũ chuyển vào tests, chỉ dùng kiểm thử explicit.
- `tests/prepare-roster.php`, `tests/real-roster.php`, `tests/reset-rollback.php`, `tests/install-real-roster.php`: kiểm thử mới.
- `README.md`, `docs/GODADDY-REAL-DATA.md`, tài liệu này: hướng dẫn cài mới và kiểm chứng.

Không có startup seeding trong bootstrap hoặc start-local. Installer mới không tạo dữ liệu demo; test fixtures không được bootstrap/install gọi. Bản ZIP/backup cũ trong var và vmu-club.zip không phải source hiện hành; dùng bộ ZIP mới được bàn giao.

## Validation

Số áo hiển thị vẫn là string, hỗ trợ Y-07, 2003, 007. SQL TRIM bỏ dấu cách đầu/cuối; LOWER so sánh không phân biệt hoa thường. UNIQUE áp dụng cả Active/Inactive. Add/Edit thông báo ví dụ `Jersey number 99 is already assigned to Tuan Nguyen.`. Database ngăn insert trùng ngay cả khi bypass application; không có precheck đơn thuần thiếu constraint.

## Build và kiểm thử

- PHP syntax lint toàn bộ PHP app/public/scripts/tests/database/config: **38 files PASS**.
- Regression HTTP `tests/workflows.php`: **253 checks PASS** trên fixture riêng.
- Roster/export/Admin HTTP/database validation: **43 checks PASS**; kiểm tra toàn bộ 21 tên trên trang Admin, Add, Edit, Edit giữ nguyên số, trùng 99, chuỗi Y-07/2003, khác hoa thường/khoảng trắng, constraint trực tiếp và dọn records thử nghiệm.
- Restart PHP test server rồi chạy lại: **43 checks PASS**, không tự seed; roster giữ nguyên 21 người.
- Reset fixture: PASS, counts đúng bảng trên; ADMIN/settings/login_attempts không đổi.
- Forced failure ở insert cầu thủ thứ 21: PASS, toàn bộ 7 bảng trở về snapshot trước reset.
- Chạy reset lại trên roster thật: PASS, bị từ chối và không đổi records.
- Đã gặp lỗi collation binary ở prepared parameter MariaDB; sửa CONVERT(? USING utf8mb4) và chạy lại thành công.
- CLI installer: PASS, tạo 21 Active và một Admin trên database trống; log `var/install-real-roster-tests.txt`.
- Source ZIP: PASS, 33 entries; đủ .htaccess và helper, không chứa database.local.php, SQL hay mật khẩu. Config mặc định trong ZIP chỉ chứa placeholder; cấu hình hosting qua database.local.php.
- Không có build frontend/compiler riêng; PHP lint và runtime HTTP là kiểm tra build thích hợp.
- Chưa kiểm tra giao diện bằng trình duyệt trực quan; kiểm tra trang Admin bằng HTTP/rendered HTML.
- Chưa chạy trên MySQL 8 hoặc PHP của GoDaddy; đã chạy MariaDB 10.4/PHP 8.4 local.

Log kiểm thử trong `var/roster-workflows-results.txt`, `var/real-roster-tests.txt`, `var/real-roster-after-restart.txt`, `var/reset-rollback-tests.txt`. Backup và report reset chỉ trong var, không upload.

## Việc còn lại / bước tiếp theo

Người dùng import `var/godaddy-real-roster/install.sql` vào database GoDaddy mới/trống; upload source ZIP mới; cập nhật cấu hình database trên hosting. Mở file credentials local, đăng nhập Admin mới và đổi mật khẩu. Kiểm tra 21 cầu thủ và duplicate 99 trên hosting theo `docs/GODADDY-REAL-DATA.md`. Không cần và không được import sample.sql. Chưa có số lượng record đã xóa trên GoDaddy vì database đó không bị thay đổi.


## Bổ sung: cầu thủ tự cập nhật ảnh đại diện

Đã hoàn tất trước khi deploy. Cầu thủ đăng nhập → bấm tên ở cuối menu → **Ảnh đại diện** → chọn ảnh → **Lưu ảnh đại diện**. Hồ sơ cá nhân cũng có nút dẫn tới trang này.

- JPG/JPEG, PNG, WebP; tối đa 2 MB. Kiểm tra upload error, kích thước, extension, MIME thực tế, cấu trúc ảnh và is_uploaded_file; không dùng tên file từ người dùng để lưu.
- Ảnh hiển thị trong account, sidebar, hồ sơ, danh sách cầu thủ và các bảng xếp hạng dùng avatar. Chưa có ảnh dùng chữ viết tắt. Form thông báo ảnh sẽ hiển thị công khai trên hồ sơ/danh sách.
- Endpoint yêu cầu đăng nhập PLAYER, CSRF và player Active; player_id chỉ lấy từ session. Không cho Admin hoặc cầu thủ đổi chủ ảnh bằng POST player_id.
- Khóa row cầu thủ trong transaction khi cập nhật. Lưu database lỗi: rollback và xóa file mới; cập nhật thành công: xóa ảnh cũ có đường dẫn do hệ thống sinh. Không xóa file tùy ý từ đường dẫn đầu vào.
- `players.photo_path` nullable VARCHAR(190); không ảnh hưởng số áo, roster, account hoặc thống kê. Upload không tự tạo tài khoản: Admin vẫn cấp tài khoản cầu thủ tại Users.

Source cập nhật: `app/actions.php`, `app/auth.php`, `app/functions.php`, `app/views/account.php`, `app/views/profile.php`, `app/views/players.php`, `app/views/layout.php`, `public/assets/club-theme.css`, `database/schema.sql`. Thêm `database/migrations/003_player_photos.sql`, `scripts/migrate-player-photos.php`, `tests/player-photos.php`, `tests/player-photos-browser.cjs`.

Bộ `var/godaddy-real-roster/install.sql` đã bổ sung cột ảnh và giữ nguyên hash Admin đã bàn giao. ZIP website được đóng gói lại. Nếu chưa import: dùng SQL mới. Nếu đã import bản trước: chỉ chạy migration 003, không import lại toàn bộ install.sql. Thư mục public/uploads cần quyền ghi của PHP; giữ .htaccess chặn file thực thi và sao lưu ảnh cùng database.

Kiểm chứng:

- Migration lần đầu và chạy lại: PASS, không thay dữ liệu cũ.
- 20 kiểm tra photo HTTP/database: PASS (own upload, giả player_id, guest, CSRF, file thiếu/giả/extension thực thi/quá 2 MB, hiển thị ảnh, thay ảnh, rollback và dọn file khi database lỗi, account bị khóa, Admin không được giả chủ ảnh).
- 43 kiểm tra roster/jersey: PASS sau thay đổi ảnh.
- 253 kiểm tra regression: PASS sau thay đổi ảnh.
- SQL GoDaddy cập nhật: import thành công vào database mới, đủ 21 Active và cột photo_path.
- PHP lint: 40 files PASS.
- Instance test cũ gặp lỗi Aria khi cấp quyền và dừng; đã tạo instance test riêng mới, chạy lại các kiểm tra trên thành công. Không thay database ngoài môi trường test.

Log: `var/player-photos-tests.txt`, `var/roster-after-photos-tests.txt`, `var/photos-regression-tests.txt`. GoDaddy chưa được thay đổi; bước tiếp theo vẫn là upload bộ mới và import/cấu hình theo hướng dẫn.

Browser Edge: PASS login, JPG upload, ảnh load thực tế, desktop 1440px/mobile 390px không tràn ngang, không lỗi JavaScript. Đã xem ảnh screenshot cả hai kích thước trong var/screenshots/player-photo-desktop.png và player-photo-mobile.png. Ảnh test đã dọn khỏi public/uploads trước đóng gói. Dịch vụ test đã dừng.


## Chạy local để duyệt trước deploy — 2026-09-29

Theo yêu cầu người dùng, đã chạy bản hiện hành tại **http://127.0.0.1:8780/** và mở tab xem thử. Chưa deploy GoDaddy.

- Database local riêng: `vmu_preview`, MariaDB trên 127.0.0.1:3312, datadir `var/local-preview-db`.
- Website local: PHP trên 127.0.0.1:8780, source public/ hiện hành.
- Admin: `admin`; cầu thủ dùng thử: `an.tran` liên kết An Tran (#2). Mật khẩu nằm trong `var/local-preview-accounts.txt`; không đưa vào log/progress hoặc gói deploy.
- Tài khoản an.tran chỉ tồn tại local; SQL GoDaddy không thay đổi và không tự tạo tài khoản Player.
- `start-preview.ps1` khởi động lại/reuse riêng hai cổng này, không sửa config/database.local.php hoặc các database/dịch vụ khác. Thông số DB riêng nằm trong var/local-preview.json và truyền qua môi trường.
- MySQL được chạy với --console và redirect log để giữ tiến trình nền ổn định. Đã restart database bằng launcher thành công rồi kiểm tra lại HTTP/database.

Chạy lại kiểm thử trên các database `_test` riêng, không dùng database preview cho bài test có thay đổi dữ liệu:

| Kiểm tra | Kết quả |
|---|---|
| Regression nghiệp vụ HTTP | 253 PASS |
| Roster, Add/Edit, số áo unique | 43 PASS |
| Upload ảnh, quyền sở hữu, file sai, rollback file | 20 PASS |
| Browser trên chính preview: public/Admin/Player/desktop/mobile | 25 PASS |
| Reset và migration jersey, chạy migration lần hai | PASS |
| Rollback lỗi ở cầu thủ thứ 21 và chặn reset lại roster thật | 2 PASS |
| CLI installer | PASS |
| PHP lint | 40 files PASS |
| Restart preview giữ dữ liệu, không sample seeding | PASS |

Đã xem screenshot mobile account: form ảnh và mật khẩu hiển thị đầy đủ, không tràn ngang. Browser kiểm tra danh sách 21 cards, login Admin và Player, đủ 10 trang Admin, link cập nhật ảnh, Player bị chặn trang Admin và không lỗi JavaScript.

Database preview sau restart: 21 players, 21 Active, 21 unique normalized jerseys, 21 unique names, 0 jersey rỗng; 0 matches. Tuan Nguyen = 99, Steven Ha = 1. Admin và account an.tran tồn tại. Không có demo players/matches.

Logs mới: var/predeploy-workflow-results.txt, var/predeploy-roster-results.txt, var/predeploy-photo-results.txt, var/predeploy-reset-results.txt, var/predeploy-rollback-results.txt, var/predeploy-install-results.txt, var/local-preview-browser-results.txt, var/local-preview-verification.json. Screenshots: var/screenshots/local-preview-*.png.

Đã dừng PHP servers test 8769/8770; giữ PHP preview 8780 và MySQL local 3312 để người dùng xem. Không chỉnh sửa source ứng dụng trong lượt này; chỉ bổ sung launcher/provision local và test browser, cho phép TEST_DB_PORT trong các test CLI. ZIP/SQL bàn giao trước vẫn là bản ứng dụng đã kiểm thử. Bước tiếp theo: người dùng duyệt local trước khi deploy.

## 2026-09-30 — Khôi phục local preview
Người dùng báo không truy cập được. Xác minh cổng 8780 từ chối kết nối; đã chạy start-preview.ps1 để khởi động lại MySQL 3312 và PHP 8780. Trang Players trả HTTP 200. Thêm start-preview.cmd để người dùng nhấp đúp khởi động dịch vụ và mở trình duyệt. Không reset dữ liệu, không deploy GoDaddy.


## 2026-09-30 — Simplify match management and official venue

Completed work:
- Removed opponent logo upload from both Create/Edit Match and retired its upload processing. Public cards render opponent names without logos or crest placeholders.
- Added shared official venue configuration: Complexe sportif Marie-Victorin, 7000 Bd Maurice-Duplessis, Montréal, QC H1G 0A1. Google Maps directions URL is generated once from the exact name/address.
- Admin Create/Edit shows read-only venue text; no venue field is submitted or required. New matches store the official venue name. Editing preserves existing location and opponent_logo values and ignores forged location input.
- Home always shows a bilingual Match Venue card, including when there are no matches. Next/last match cards and Match Details show clickable venue name, full address and Get Directions. Calendar and Admin attendance/statistics headings use the same official venue name.
- Retained existing visual style; responsive venue card, wrapping addresses and minimum 44px directions target. No new dependencies.

Files created: config/match-venue.php; tests/prepare-venue.php; tests/match-venue-browser.cjs.
Files modified: app/functions.php; app/actions.php; app/views/admin-match-edit.php; app/views/components.php; app/views/home.php; app/views/matches.php; app/views/admin-match-data.php; public/assets/app.css; tests/workflows.php; docs/CODEX-PROGRESS.md.

Database changes/migrations: NONE to application databases. No columns dropped, no existing matches rewritten, no logo files deleted. Players, attendance, confirmations, goals, assists, results and statistics retain existing schema and behavior. Verification used NEW isolated vmu_venue_20260930_test on local port 3312; the preview roster database and hosted database were not modified. Test fixtures/users/matches exist only in that isolated database.

Build/test results:
- PHP lint: config/match-venue.php and all 23 app PHP files PASS. No build step for this PHP app.
- Extended HTTP workflow regression: 269 checks PASS. Includes create/save without location/logo, Home/detail venue and safe links, edit preserving legacy location/logo, suppression of legacy logo/placeholder, legacy match pages, confirmations, goal/assist validation, team/player aggregates, account permissions and existing workflows.
- Existing local preview browser suite: 25 checks PASS.
- Dedicated venue browser suite: 42 checks PASS. Created a match through Admin without venue/logo and verified it on Home. Home, Create Match and Match Details at widths 1440, 768, 390, 320 have no horizontal overflow. Directions href resolves to exact configured destination, with target=_blank and rel=noopener noreferrer.
- Visual review: inspected 390px Home screenshot; venue card/address/button and Next Match fit the existing layout.
- Logs: var/venue-workflow-results.txt, var/venue-browser-results.txt. Screenshots: var/screenshots/venue-*.png.

Remaining limitations: did not navigate the external Google Maps service to verify its live geocoding or route selection; verified exact destination and link attributes locally. No hosted deployment performed. Existing ZIP release bundles were not regenerated.
Next recommended steps: review local preview http://127.0.0.1:8780/, click Get Directions on a device to confirm routing, then deploy the changed source/assets plus config/match-venue.php together using the existing deployment process. No database migration needed.

## 2026-09-30 — Gói deploy đầy đủ
Đã tạo VMU-GoDaddy-2026-09-30.zip gồm website.zip dựng lại từ source hiện hành, database/install.sql, database/verify.sql, mẫu database.local.php.example, PRIVATE/admin-credentials.txt, HUONG-DAN-DEPLOY.md và SHA256SUMS.txt. Website ZIP không chứa config local, SQL, mật khẩu, test/log; có đủ .htaccess. SQL đối chiếu khớp schema hiện hành và 21 cầu thủ, hash Admin khớp credentials, không account an.tran, không DROP/TRUNCATE. PHP packaged lint PASS. Hướng dẫn từng bước cPanel đã ghi tại docs/HUONG-DAN-DEPLOY-GODADDY.md. Database này là bộ cài sạch, không export account/dữ liệu thử local. Chưa upload hoặc deploy GoDaddy.


## 2026-10-01 — Sửa thiếu config/match-venue.php trên GoDaddy
Log hosting báo require_once thất bại trong app/functions.php dòng 2. Xác nhận source local và functions.php trong website.zip cũ đều yêu cầu file venue nhưng gói đóng trước chỉ sao chép config/database.php. Đã tạo VMU-fix-match-venue-2026-10-01.zip chỉ chứa config/match-venue.php; giải nén vào /home/xxkttpus7wla/public_html/vmu/ để sửa. Đã bổ sung file vào gói đầy đủ VMU-GoDaddy-2026-10-01.zip, cập nhật checksum và hướng dẫn. Không đổi app code, database, Admin hoặc config/database.local.php. Test mới tests/deployment-package.php thực sự require packaged functions.php để phát hiện thiếu dependency; PASS. PHP lint 26 packaged files PASS. Chưa upload file sửa lên hosting; người dùng cần upload hotfix, không import lại SQL.


## 2026-10-01 — Tạo tài khoản cầu thủ còn thiếu

Theo yêu cầu: username tên viết thường, khoảng trắng chuyển thành dấu chấm (An Tran → an.tran); mật khẩu chung do người dùng chỉ định, lưu bcrypt trong SQL.

- File để chạy trong phpMyAdmin: database/create-player-accounts.sql. Generator: scripts/generate-player-accounts-sql.php.
- Chỉ INSERT tài khoản PLAYER chưa có liên kết users.player_id. Không UPDATE tài khoản/password/Admin hiện có, không xóa dữ liệu.
- Bỏ qua username đã có, tên chuẩn hóa bị trùng, tên không thuộc định dạng username hỗ trợ hoặc quá dài. SELECT cuối file liệt kê tài khoản và trường hợp bị bỏ qua; trả số accounts_created.
- Chuẩn hóa khoảng trắng liên tiếp thành một dấu chấm; trạng thái account theo player Active/Inactive.
- Một INSERT atomic trong transaction; UNIQUE database vẫn bảo vệ request cạnh tranh. Chạy lại không sửa tài khoản cũ.
- Kiểm thử tests/player-account-import.php trên database test mới: PASS tạo 19 account còn thiếu khi có 1 existing player account và 1 collision Admin; PASS giữ nguyên toàn bộ hai account/password cũ; PASS password_verify cho account mới; PASS giữ Inactive; PASS chạy lại không đổi; PASS tên trùng/không hợp lệ bị bỏ qua và nhiều khoảng trắng thành new.player.
- Chưa chạy trên GoDaddy hoặc database local-preview; người dùng import file SQL vào database CLB đã có players/users. Không chạy lại install.sql.

## 2026-10-17 — GitHub và tự deploy GoDaddy
Khởi tạo Git/main cho repository hoangvu1104/VietMontrealUnited. Thêm GitHub Actions push main/workflow_dispatch và SFTP script/manifest theo dự án Phở Mivy, điều chỉnh thành một root VMU. Deploy yêu cầu production secrets và GODADDY_DEPLOY_ENABLED=true. Bỏ mật khẩu database mặc định khỏi cấu hình nguồn; database.local.php vẫn bị loại trừ. Không thay đổi database hoặc dữ liệu hosting. Loại release ZIP, var, credentials, SQL tài khoản cùng generator/test chứa mật khẩu khỏi Git.
Verification: PHP lint app/config/public PASS; SFTP dry-run PASS 34 file. Chưa xác minh secrets hoặc chạy deploy thật.

## 2026-10-17 — Bật GitHub Actions explicit FTPS
Theo tài khoản FTP được người dùng cung cấp, chuyển workflow từ SSH sang explicit FTPS cổng 21. tools/deploy-ftps.py dùng Python standard library, xác minh chứng chỉ TLS, upload allowlist 34 file và kiểm tra thư mục/cấu hình production trước khi ghi. GitHub production secrets đã cấu hình, GODADDY_DEPLOY_ENABLED=true. Host ftp.vietcanadianlive.com không có DNS; xác minh hostname GoDaddy qua redirect cPanel: p3plzcpnl503791.prod.phx3.secureserver.net. Login TLS và liệt kê root / thành công, có app/config/public/index.php. Không có mật khẩu trong commit. Dry-run 34 files PASS. Chưa chạy import/migration database.
