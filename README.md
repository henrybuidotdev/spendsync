# SpendSync

Ứng dụng theo dõi chi tiêu cho khách hàng ngân hàng Việt Nam, thiết kế ưu tiên điện thoại. Bạn kết nối Gmail bằng **mật khẩu ứng dụng Google**. Ứng dụng đọc qua IMAP (chỉ đọc) **duy nhất** các email từ địa chỉ của ngân hàng được hỗ trợ, phân tích từng giao dịch (số tiền, chiều tiền, người nhận/gửi, thời gian, nội dung) và hiển thị trên web app (cài được như PWA). Tại đó bạn phân loại giao dịch và xem tổng theo tháng.

Công nghệ: Laravel 13, Livewire 3 + Volt (Breeze), Tailwind, SQLite, hàng đợi database, Pest, `webklex/php-imap`.

Ngân hàng hỗ trợ: **Vietcombank (VCB)**, **CAKE by VPBank (CAKE)**, **VPBank NEO (VPB)**.

## Cài đặt

Yêu cầu: PHP 8.4+ với `intl`, `mbstring`, `pdo_sqlite`; Composer; Node 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
```

### Danh mục mặc định

Các danh mục dùng chung mà mọi người dùng đều thấy nằm trong `database/seeders/SystemCategorySeeder.php`. Để thêm một danh mục, thêm một dòng vào đó rồi chạy:

```bash
php artisan db:seed --class=SystemCategorySeeder
```

Lệnh này chạy lại nhiều lần vẫn an toàn: nó chỉ tạo danh mục chưa có (so theo tên và loại) và không bao giờ sửa dữ liệu đã có. Khi tạo một danh mục mới, danh mục riêng của người dùng trùng tên và loại sẽ được gộp vào nó. Đổi icon hay tên của một dòng đã có không ảnh hưởng tới dữ liệu cũ (đổi tên sẽ tạo danh mục mới), nên những thay đổi đó cần làm bằng migration.

### Chạy demo không cần Gmail

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
php artisan serve
```

Đăng nhập bằng **demo@spendsync.test / password**. Demo seeder nhập toàn bộ mẫu email trong `tests/Fixtures/emails` qua đúng quy trình thật (`spendsync:import-eml`), và thêm vài quy tắc phân loại để một số giao dịch đã có danh mục sẵn. Các mẫu email có ngày tháng 11/2026. Nếu tháng hiện tại trống, trang giao dịch có nút nhảy tới tháng gần nhất có dữ liệu.

### Queue worker và scheduler

Việc đồng bộ và phân tích email chạy trên hàng đợi `database`. Mở hai terminal:

```bash
php artisan queue:work      # chạy các job SyncMailAccount / ParseRawEmail
php artisan schedule:work   # mỗi phút: xếp lịch đồng bộ cho nhóm tài khoản của phút đó
```

Trên production, dùng một dòng cron `* * * * * php artisan schedule:run` cùng một `queue:work` có supervisor giám sát.

#### Cách chia lịch đồng bộ

Mỗi tài khoản đang hoạt động được đồng bộ khoảng một lần mỗi giờ, vào phút riêng của nó: `id % 60`. Mỗi phút, scheduler chỉ xếp job `SyncMailAccount` cho các tài khoản thuộc phút đó, nên tải IMAP được rải đều cả giờ thay vì dồn vào tất cả hộp thư lúc :00.

- Tài khoản vừa đồng bộ chưa tới 30 phút (`last_synced_at`) sẽ được bỏ qua, để lần "Đồng bộ ngay" thủ công hoặc lần kết nối mới không bị lịch chạy lặp lại ngay sau đó.
- Job được xếp không có độ trễ, nên lần đồng bộ thủ công không bao giờ bị chặn bởi một job theo lịch đang chờ trong hàng đợi (`SyncMailAccount` chỉ cho phép một job đang chờ cho mỗi tài khoản).
- Nếu lỡ một lần `schedule:run` (ví dụ lúc deploy), các tài khoản của phút đó sẽ được đồng bộ ở giờ kế tiếp.

### Kết nối Gmail thật

1. Bật Xác minh 2 bước cho tài khoản Google.
2. Mở <https://myaccount.google.com/apppasswords> và tạo một mật khẩu ứng dụng tên "SpendSync".
3. Đăng ký trong app. Bạn sẽ được đưa tới `/connect`. Dán địa chỉ Gmail và mật khẩu 16 chữ cái (có dấu cách cũng được).
4. App thử đăng nhập IMAP ngay, lưu mật khẩu đã mã hóa, rồi xếp lần đồng bộ đầu tiên (đọc lại 90 ngày, chỉnh bằng `MAIL_SYNC_FIRST_SYNC_DAYS`). Nhớ để `queue:work` chạy.

Chi tiết IMAP: `imap.gmail.com:993` qua SSL, có kiểm tra chứng chỉ. App tìm trong `[Gmail]/All Mail` (nếu không có thì dùng `INBOX`) với `FROM <địa chỉ ngân hàng> SINCE <ngày>` cho từng địa chỉ gửi. Email được tải bằng PEEK, nên không bao giờ bị đánh dấu đã đọc, di chuyển hay xóa.

Để thu hồi quyền truy cập, xóa mật khẩu ứng dụng trong Google, hoặc vào **Cài đặt → Ngắt kết nối** để xóa mật khẩu đã lưu.

## Lệnh Artisan

| Lệnh | Tác dụng |
| --- | --- |
| `spendsync:sync {account?}` | Đồng bộ ngay tất cả tài khoản đang hoạt động (hoặc một tài khoản), chạy trực tiếp. Job phân tích email được đưa vào hàng đợi. |
| `spendsync:reparse {--failed} {--bank=}` | Phân tích lại các email gốc đã lưu. Giữ nguyên danh mục và ghi chú. Dùng sau khi sửa parser. |
| `spendsync:import-eml {user} {path}` | Nhập file `.eml` (một file hoặc cả thư mục, đệ quy) làm email gốc cho một user (id hoặc email) rồi phân tích. Bỏ qua người gửi không được hỗ trợ. |
| `spendsync:parse-stats` | Bảng theo từng ngân hàng: tổng số, đã phân tích, thất bại, tỉ lệ thành công. |

## Test

```bash
php artisan test
```

Tất cả các test chạy tại local và không gọi ra mạng internet. `FakeMailboxClient` thay cho IMAP: nó trả về các mẫu `.eml` và có thể giả lập lỗi đăng nhập hoặc lỗi kết nối. `tests/Unit/BankParserFixturesTest.php` phân tích mọi file `tests/Fixtures/emails/{BANK}/*.eml` và so kết quả với file `*.expected.json` cùng tên (`null` nghĩa là "email ngân hàng nhưng không phải giao dịch", ví dụ OTP hoặc quảng cáo).

Test trình duyệt (`tests/Browser`) dùng Pest Browser Plugin với Playwright.

## Cách hoạt động

```
Gmail ──IMAP SEARCH FROM+SINCE──▶ SyncMailAccount ──firstOrCreate(user_id, message_id)──▶ raw_emails
                                                                    │
                                                         ParseRawEmail (queued)
                                                                    │
                       ParserRegistry::findFor(from) ─▶ BankParser::parse() ─▶ transactions ─▶ CategoryMatcher
```

- `app/Mail/Inbox`: interface `MailboxClient`, `WebklexGmailClient` (thật) và `FakeMailboxClient` (dùng cho test).
- `app/Parsing`: interface parser, các DTO, `ParserRegistry`, và các helper `Support/{Money,Date,Text}`. Parser của từng ngân hàng nằm trong `Banks/`.
- `app/Jobs`: `SyncMailAccount` (duy nhất cho mỗi tài khoản, timeout 120 giây, thử 2 lần) và `ParseRawEmail`.
- Danh mục: các danh mục hệ thống dùng chung (`user_id` NULL, chỉ đọc) cộng với danh mục riêng của từng người dùng. Dùng `$user->availableCategories()` để lấy danh sách một người dùng có thể chọn. Danh mục Vay/Nợ là cố định, người dùng không tạo được.
- `app/Categorization/CategoryMatcher.php`: quy tắc theo người nhận/gửi so khớp toàn bộ tên đã bỏ dấu. Quy tắc theo nội dung so khớp một đoạn con. Quy tắc theo người nhận/gửi được xét trước.
- Thời gian lưu theo UTC và hiển thị theo `Asia/Ho_Chi_Minh` (`APP_TIMEZONE`, dùng qua `config('app.display_timezone')`).
- Số dư tài khoản trong email không được đọc hay lưu.

## Thêm parser cho ngân hàng mới

1. **Khai báo người gửi.** Thêm một mục vào `config/banks.php`:
   ```php
   'ACB' => [
       'name' => 'ACB',
       'senders' => ['mailalert@acb.com.vn'], // chữ thường; chỉ những địa chỉ này được tìm
       'parser' => \App\Parsing\Banks\AcbParser::class,
       'directions' => [Direction::In, Direction::Out], // ngân hàng gửi email cho tiền vào, tiền ra hay cả hai
   ],
   ```
2. **Viết class parser** trong `app/Parsing/Banks/AcbParser.php`. Kế thừa `AbstractBankParser`, trả mã ngân hàng từ `bankCode()`, và viết `parse()`. Helper `field($text, 'Nhãn|Nhãn khác')` đọc các dòng `Nhãn: giá trị` từ văn bản đã chuẩn hóa (HTML đã được làm phẳng, mỗi dòng bảng thành một dòng). Truyền các chuỗi gốc vào `$this->transaction(...)`: hàm này tự đọc số tiền và ngày, xác định chiều tiền từ dấu `+`/`-` hoặc từ khóa (ghi có/credit/nhận và ghi nợ/debit/trừ), và dùng thời gian nhận email nếu không có ngày. Trả về `null` cho email không phải giao dịch. `$this->transaction()` ném `UnparseableEmailException` khi thiếu số tiền hoặc chiều tiền.
3. **Thêm mẫu email.** Lưu email thật (đã che thông tin) thành `tests/Fixtures/emails/ACB/01_something.eml`, kèm một email OTP hoặc quảng cáo. Cạnh mỗi file, viết `01_something.expected.json` với `direction`, `amount`, `counterparty`, `counterparty_account`, `description`, `reference`, `occurred_at` (ISO 8601 với `+07:00`), `currency`, `original_amount` và `amount_estimated`, hoặc `null`.
4. **Chạy test.** Chạy `php artisan test --filter=fixture`. Test theo dữ liệu tự nhận thư mục mới, và yêu cầu mỗi ngân hàng có ít nhất 3 mẫu giao dịch và 1 mẫu không phải giao dịch.
5. **Phân tích lại email đã lưu.** Chạy `php artisan spendsync:reparse --bank=ACB` (hoặc `--failed`), rồi xem `php artisan spendsync:parse-stats`.

## Bảo mật và quyền riêng tư

- `mail_accounts.app_password` dùng cast `encrypted` và nằm trong `$hidden`. Form kết nối xóa thuộc tính Livewire trước khi validate, và các tham số mật khẩu có `#[\SensitiveParameter]`, nên bị che khỏi stack trace. Lỗi được lưu dạng mã ngắn (`invalid_credentials`, `connection_failed`).
- Khi phân tích thất bại, log chỉ ghi `raw_email_id`, `bank_code` và lý do, không bao giờ ghi nội dung email.
- Mọi truy vấn đều giới hạn trong người dùng đang đăng nhập. Có policy cho Transaction, Category, CategoryRule và MailAccount, và có test chứng minh dữ liệu được tách biệt.
- Form kết nối giới hạn 5 lần thử mỗi 10 phút cho mỗi người dùng. "Đồng bộ ngay" giới hạn mỗi phút một lần.
- Email gốc chỉ hiển thị cho chủ sở hữu, và chỉ dưới dạng văn bản thuần đã escape.
- `/privacy` và `/terms` có bản tiếng Việt và tiếng Anh (mỗi ngôn ngữ một view), giải thích những gì được đọc, được lưu và cách xóa. **Cài đặt → Vùng nguy hiểm** có hai thao tác, đều cần xác nhận mật khẩu: **Xóa dữ liệu** xóa giao dịch, email, quy tắc, danh mục riêng và kết nối Gmail nhưng giữ tài khoản đăng nhập; **Xóa tài khoản** xóa người dùng cùng mọi thứ họ sở hữu (cascade qua khóa ngoại).

## Giới hạn đã biết

- Chuyển tiền giữa các tài khoản của chính bạn vẫn bị tính là chi tiêu.
- Mỗi người dùng chỉ một tài khoản Gmail.
- Với Gmail dùng ngôn ngữ khác, việc tìm thư mục All Mail chỉ biết tên tiếng Anh và tiếng Việt. Nếu không tìm thấy, app dùng `INBOX`, nên sẽ bỏ sót thư đã lưu trữ.
- Quy tắc phân loại được so khớp trong PHP (SQL không bỏ dấu tiếng Việt được), nên việc áp dụng lại cho giao dịch cũ có độ phức tạp O(số giao dịch của người dùng). Ở quy mô MVP thì chấp nhận được.
- PWA chỉ cache file build tĩnh. Không có dữ liệu offline.

## Giấy phép

[AGPL-3.0](LICENSE). Nếu bạn chạy bản đã sửa đổi thành dịch vụ cho người khác dùng, bạn phải công khai mã nguồn của bản đó.
