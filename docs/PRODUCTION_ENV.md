# Production Environment Variables

Tài liệu này là nguồn chuẩn cho biến môi trường production. Khi code thêm,
đổi tên hoặc bỏ một biến môi trường, pull request phải cập nhật đồng thời:

1. File này.
2. `.env.example` tương ứng.
3. `fly.toml` nếu biến là cấu hình công khai, không phải secret.

Không commit giá trị secret thật. Fly dùng `fly secrets`; Vercel dùng Project
Settings → Environment Variables.

## 1. Laravel API trên Fly.io

Tài nguyên Fly hiện tại:

```text
Laravel: linguist
MySQL:   linguist-db-hufi      (volume mysql_data, 10 GB)
Redis:   linguist-redis-hufi   (volume redis_data, 1 GB)
Region:  sin
```

### Bắt buộc

| Biến | Secret | Giá trị/mục đích |
|---|---:|---|
| `APP_KEY` | Có | Khóa Laravel, tạo bằng `php artisan key:generate --show`. Không đổi sau khi có dữ liệu/session mã hóa. |
| `APP_ENV` | Không | `production` |
| `APP_DEBUG` | Không | `false` |
| `APP_URL` | Không | URL HTTPS trực tiếp của Laravel/Fly, ví dụ `https://english-api.fly.dev` |
| `FRONTEND_URL` | Không | URL learner Vercel, không có dấu `/` cuối |
| `ADMIN_FRONTEND_URL` | Không | URL admin Vercel, không có dấu `/` cuối |
| `DB_CONNECTION` | Không | `mysql` |
| `DB_HOST` | Có | Host MySQL production |
| `DB_PORT` | Không | `3306` |
| `DB_DATABASE` | Có | Database production |
| `DB_USERNAME` | Có | User database có quyền tối thiểu cần thiết |
| `DB_PASSWORD` | Có | Mật khẩu database |
| `REDIS_HOST` hoặc `REDIS_URL` | Có | Kết nối Redis production |
| `CACHE_STORE` | Không | `redis` |
| `QUEUE_CONNECTION` | Không | `database`; worker được Supervisor quản lý cùng Fly machine |
| `DB_QUEUE_RETRY_AFTER` | Không | `360`; phải lớn hơn worker timeout 300 giây |
| `SESSION_DRIVER` | Không | `database` |
| `SESSION_SECURE_COOKIE` | Không | `true` |
| `SESSION_SAME_SITE` | Không | `lax` |
| `SESSION_DOMAIN` | Không | Để trống/null để dùng host-only cookie qua Vercel rewrite |
| `MAIL_MAILER` | Không | `smtp`, `postmark`, `resend` hoặc mailer production đã chọn |
| `MAIL_FROM_ADDRESS` | Không | Địa chỉ gửi đã xác minh |
| `MAIL_FROM_NAME` | Không | `${APP_NAME}` hoặc tên sản phẩm |

Nếu nhà cung cấp database cấp một connection string, có thể dùng `DB_URL`
thay cho nhóm `DB_HOST`–`DB_PASSWORD`. Nếu nhà cung cấp Redis cấp connection
string, ưu tiên `REDIS_URL`.

### Mail SMTP

Các biến sau bắt buộc khi `MAIL_MAILER=smtp`:

| Biến | Secret | Ghi chú |
|---|---:|---|
| `MAIL_HOST` | Không | SMTP host |
| `MAIL_PORT` | Không | Thường `465`, `587` hoặc port nhà cung cấp |
| `MAIL_USERNAME` | Có | SMTP username |
| `MAIL_PASSWORD` | Có | SMTP password/API credential |
| `MAIL_SCHEME` | Không | Theo nhà cung cấp, thường `tls` hoặc `ssl` |

Không sử dụng Mailtrap Sandbox ở production.

### LexiLingo

| Biến | Bắt buộc | Secret | Mục đích |
|---|---:|---:|---|
| `LEXILINGO_BACKEND_URL` | Khi sync dataset | Không | HTTPS origin của Backend Service, không thêm `/api/v1` |
| `LEXILINGO_AI_URL` | Khi dùng AI/STT/TTS/TraceCAG | Không | HTTPS origin của AI Service |
| `LEXILINGO_PARTNER_API_KEY` | Khi sync category/course/vocabulary | Có | Gửi bằng `X-LexiLingo-API-Key`, dùng cho `LexiLingoClient::partner()` |
| `LEXILINGO_IMPORT_KEY` | Khi sync lesson content protected | Có | Key chỉ có quyền `content:read`; không dùng admin token 30 phút |
| `LEXILINGO_AI_SERVICE_SECRET` | Khi gọi internal AI | Có | Gửi bằng `X-AI-Service-Secret` |
| `LEXILINGO_TRACE_CAG_SERVICE_TOKEN` | Khi bật `FEATURE_AI` | Có | Gửi bằng `X-LexiLingo-Service-Token` tới `POST /api/v1/integrations/trace-cag/v1/analyze`. Phải khớp `TRACE_CAG_SERVICE_TOKEN_HASH` (SHA-256 của token này) phía AI Service, cùng với `TRACE_CAG_EXTERNAL_ENABLED=true` — hai biến đó **không nằm trong repo này**, đặt trực tiếp trên AI Service |
| `LEXILINGO_SUBJECT_HMAC_SECRET` | Khi bật `FEATURE_AI` | Có | Tối thiểu 32 ký tự; ký subject ẩn danh (HMAC-SHA256 của `user_id`) gửi cho TraceCAG thay vì lộ `user_id` thật. Thiếu biến này TraceCAG luôn trả kết quả `degraded` (fallback nội bộ), không lỗi 500 |
| `LEXILINGO_TIMEOUT` | Không | Không | `30`; code giới hạn trong khoảng 1–60 giây |
| `LEXILINGO_CONNECT_TIMEOUT` | Không | Không | `5`; timeout kết nối riêng, giới hạn 1–30 giây. `traceCag()` còn có thêm hard timeout 15s ở tầng gọi |
| `LEXILINGO_AI_RETRY_TIMES` | Không | Không | `2`; số lần thử lại khi timeout/5xx trên proxy AI (`/api/v1/ai/*`, `/api/v1/stt/*`, `/api/v1/tts/*`), không retry lỗi 4xx |
| `LEXILINGO_AI_RETRY_DELAY_MS` | Không | Không | `200`; thời gian chờ giữa các lần retry |
| `LEXILINGO_AI_MAX_AUDIO_KB` | Không | Không | `10240` (10 MB); giới hạn dung lượng file audio gửi lên cho pronunciation/STT |

### Feature flag AI/TraceCAG

| Biến | Bắt buộc | Secret | Mục đích |
|---|---:|---:|---|
| `FEATURE_AI` | Không | Không | Mặc định `false`. Bật `POST /api/v1/ai/translate`, `/pronunciation`, `/speech-to-text`, `/text-to-speech` và `/ai/trace-cag`. Tắt thì mọi endpoint này trả `503 FEATURE_DISABLED` ngay, không gọi AI Service |

`/ai/trace-cag` (chấm/gợi ý bài tập có cấu trúc — `TraceCagController` → `LexiLingoTraceCag` → `POST /api/v1/integrations/trace-cag/v1/analyze`) đã có sẵn: idempotent theo `X-Request-ID`, timeout 15s, và **tự động fallback** sang kết quả xác định (`degraded: true`) khi AI Service lỗi hoặc credential thiếu — không bao giờ chặn luồng học của learner. Bật `FEATURE_AI` mà thiếu `LEXILINGO_TRACE_CAG_SERVICE_TOKEN`/`LEXILINGO_SUBJECT_HMAC_SECRET` thì endpoint vẫn trả `200` nhưng luôn `degraded`.

### Feature flag import (hai cấp, tách biệt)

| Biến | Bắt buộc | Secret | Mục đích |
|---|---:|---:|---|
| `FEATURE_LEXILINGO_IMPORT` | Không | Không | Mặc định `false`. Bật fetch/staging. Khi `false`, mọi đường ghi đều bị chặn: HTTP start/reset, `RunAdminImport`, `AdminImportRunner` và cả hai lệnh CLI — job đã xếp hàng không lách được cổng này |
| `FEATURE_LEXILINGO_IMPORT_APPLY` | Không | Không | Mặc định `false` và **phải giữ `false` ở production** cho tới khi có đủ bằng chứng release. Chỉ mở đúng `POST /api/v1/admin/imports/runs/{run}/apply` — thao tác duy nhất ghi bản ghi đã staged vào catalog |

Hai cờ tách biệt có chủ đích: staging dữ liệu để review không bao giờ kéo theo
quyền ghi. Bật `FEATURE_LEXILINGO_IMPORT_APPLY` phải đi kèm `php artisan config:clear`
**và** restart queue worker, vì runtime web và runtime queue đọc config cache riêng.

Ngoài cờ, apply còn bị chặn bởi phân quyền: Super Admin (`apply-content-import`)
kèm Google re-auth mới trong 15 phút, ràng buộc theo user id + Google subject +
session id. Thiếu freshness trả `428`, sai quyền trả `403` (quyền được kiểm tra
trước, nên vai trò thấp không bao giờ nhận `428`). Hiện apply mới hỗ trợ
`categories`; entity khác trả `422`.

### Mô hình sở hữu catalog

Mỗi bản ghi catalog mang `source_system` + `external_id` (identity ghép),
`source_fingerprint`, `source_snapshot`, `local_override_at` và `catalog_revision`.
Sync bỏ qua hàng có `local_override_at`, nên chỉnh sửa của admin không bị import
ghi đè. Apply so `catalog_revision` + `source_fingerprint` đã ghi lúc staging;
hàng đã đổi được đánh `stale` thay vì bị ghi đè.

### Danh tính dùng cho release rehearsal

| Biến | Bắt buộc | Secret | Mục đích |
|---|---:|---:|---|
| `RELEASE_LEARNER_EMAIL` | Chỉ ở staging | Không | Tài khoản learner chuyên dụng cho smoke test; không dùng tài khoản cá nhân |
| `RELEASE_ADMIN_EMAIL` | Chỉ ở staging | Không | Tài khoản admin chuyên dụng |
| `RELEASE_SUPER_ADMIN_EMAIL` | Chỉ ở staging | Không | Tài khoản super admin chuyên dụng để kiểm tra apply |

Public category/course/vocabulary request không được gửi import key. Vocabulary
được đồng bộ vào MySQL bằng:

```bash
fly ssh console -C "php artisan lexilingo:sync-vocabulary --limit=100"
```

Page upstream được cache Redis 5 phút; dữ liệu vocabulary core được upsert vào
MySQL theo `external_id`. API `/api/v1/vocabulary` chỉ đọc dữ liệu local.

Category/course/unit/lesson (outline) dùng chung `LEXILINGO_*` ở trên, không
cần biến mới:

```bash
fly ssh console -C "php artisan lexilingo:import categories --limit=50"
fly ssh console -C "php artisan lexilingo:import courses --limit=50"
fly ssh console -C "php artisan lexilingo:import all --dry-run"
```

`--dry-run` chỉ validate và không ghi DB (bao gồm không tạo/nâng checkpoint).
`--reset` bỏ qua checkpoint đã lưu và chạy lại từ offset 0. Import idempotent
theo `external_id`, an toàn khi chạy lại cùng payload. Hai bảng vận hành:

- `lexilingo_import_checkpoints`: vị trí (`cursor`) catalog **thật sự** đã đồng
  bộ tới theo từng entity, dùng để resume. Entity ghi trực tiếp tiến cursor ngay
  lúc fetch; entity staged (`categories`) chỉ tiến khi apply thành công và run
  không còn item chờ duyệt, nên một run bị huỷ không làm mất trang dữ liệu.
- `lexilingo_import_failures`: payload gốc + lỗi validate của các bản ghi bị
  từ chối (không làm fail cả trang) — kiểm tra bảng này khi nghi ngờ dữ liệu
  import thiếu.

Nội dung đầy đủ của từng lesson (`description`/`prerequisites`/
`estimated_minutes`/`pass_threshold` qua `/api/v1/learning/lessons/{id}/content`)
chưa được đồng bộ — chỉ mới có outline (tiêu đề, thứ tự, loại, XP) từ course
detail. Đây là việc tiếp theo, chưa triển khai.

### Khuyến nghị

```dotenv
APP_NAME="English Learning"
APP_LOCALE=vi
LOG_CHANNEL=stderr
LOG_LEVEL=info
BCRYPT_ROUNDS=12
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
REDIS_CLIENT=phpredis
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
LEXILINGO_TIMEOUT=30
```

`Dockerfile.fly` đã cài extension `phpredis`.

Supervisor chạy một database queue worker với `--tries=1 --timeout=300` và
graceful shutdown 330 giây. Kiểm tra log worker và trạng thái durable import
run sau mỗi deploy. Không chuyển về `sync`, vì HTTP import không được giữ mở
trong lúc gọi nhiều trang dữ liệu LexiLingo.

### Ví dụ thiết lập Fly secrets

Không copy nguyên lệnh này vào lịch sử shell cùng secret thật. Có thể nhập
interactive bằng `fly secrets set TEN_BIEN`:

```bash
fly secrets set APP_KEY
fly secrets set DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD
fly secrets set REDIS_URL
fly secrets set MAIL_USERNAME MAIL_PASSWORD
fly secrets set LEXILINGO_IMPORT_KEY LEXILINGO_AI_SERVICE_SECRET
```

Các URL/config không nhạy cảm có thể đặt trong `fly.toml` hoặc bằng
`fly secrets set` nếu muốn quản lý thống nhất.

### Google/Facebook OAuth

Chỉ cấu hình khi bật social login:

| Biến | Secret | Giá trị |
|---|---:|---|
| `GOOGLE_CLIENT_ID` | Không | OAuth 2.0 Web client ID |
| `GOOGLE_CLIENT_SECRET` | Có | Secret đã rotate, lưu bằng Fly secret |
| `GOOGLE_REDIRECT_URI` | Không | `${FRONTEND_URL}/api/v1/auth/oauth/google/callback` |
| `FACEBOOK_CLIENT_ID` | Không | Facebook App ID |
| `FACEBOOK_CLIENT_SECRET` | Có | Facebook App Secret |
| `FACEBOOK_REDIRECT_URI` | Không | `${FRONTEND_URL}/api/v1/auth/oauth/facebook/callback` |
| `ADMIN_GOOGLE_EMAILS` | Không | Danh sách email (phân tách bằng dấu phẩy) được whitelist vào role `admin` khi đăng nhập qua `google.admin` middleware |
| `SUPER_ADMIN_GOOGLE_EMAILS` | Không | Như trên, whitelist vào role `super_admin` |

Toàn bộ route `/api/v1/admin/catalog/*`, `/admin/users`, `/admin/operations/*`
và `/admin/imports/*` nằm sau middleware `google.admin`: chỉ chấp nhận session
đã đăng nhập Google thật với email nằm trong hai biến trên. Không có cách giả
lập hợp lệ bằng chỉ `email/password` hay Postman/curl thuần — xem mục "Tài
khoản demo" trong [`../README.md`](../README.md) để biết giới hạn khi test
local.

Authorized redirect URI trong Google/Facebook Console phải khớp tuyệt đối với
biến redirect tương ứng và đi qua domain frontend để giữ session cookie host-only.
Production hiện dùng:

```text
https://linguist-nova.vercel.app/api/v1/auth/oauth/google/callback
https://linguist-nova.vercel.app/api/v1/auth/oauth/facebook/callback
```

Không tải hoặc commit file
`client_secret_*.json`; pattern này đã được chặn trong `.gitignore`.

## 2. Learner frontend trên Vercel

Root Directory: `frontend`

| Biến | Phạm vi | Giá trị |
|---|---|---|
| `LARAVEL_API_ORIGIN` | Production, Preview | URL HTTPS trực tiếp của Laravel/Fly |

Đây là biến server-only. Không đổi thành `NEXT_PUBLIC_LARAVEL_API_ORIGIN`;
trình duyệt phải gọi đường dẫn tương đối `/api/*` qua Next.js rewrite.

## 3. Admin frontend trên Vercel

Root Directory: `admin-frontend`

| Biến | Phạm vi | Giá trị |
|---|---|---|
| `LARAVEL_API_ORIGIN` | Production, Preview | URL HTTPS trực tiếp của Laravel/Fly |

Không cấu hình `NEXT_PUBLIC_API_URL`, bearer token hoặc admin token trong
frontend.

## 4. Biến chưa cần ở production hiện tại

- `DB_ROOT_PASSWORD`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, `APP_PORT`:
  chỉ dùng Docker/local.
- `VITE_APP_NAME`: Blade/Vite cũ, không cần cho hai Next.js app.
- `AWS_*`: chỉ cần khi bật S3/object storage.
- `GOOGLE_*`, `FACEBOOK_*`: chỉ cần khi bật social login production.

## 5. Checklist sau khi thay đổi env

```bash
php artisan config:clear
php artisan config:cache
php artisan migrate --force
php artisan test
```

Kiểm tra production:

```text
GET  Laravel /health
GET  learner /api/v1/health
GET  admin /api/v1/health
POST login → GET /api/v1/auth/me → POST logout
php artisan lexilingo:sync-vocabulary --limit=1
php artisan queue:monitor database:default --max=100
GET  /api/v1/vocabulary?per_page=1
```

`/health` là readiness check: endpoint trả HTTP 503 nếu MySQL hoặc Redis đang
được cấu hình nhưng không truy cập được. Response chỉ nêu component `down`;
chi tiết exception nằm trong application log.

Vercel production của cả hai project phải theo nhánh `main`. Trên GitHub, bật
branch protection để cấm push trực tiếp và bắt buộc các check `Backend`,
`Learner frontend`, `Admin frontend` đạt trước khi merge. Preview deployment
có thể chạy trên Pull Request trước khi các check hoàn tất.

Không chạy `migrate:fresh` trên production. Nếu đổi `APP_KEY`, database/Redis
credential, cookie domain hoặc LexiLingo secret, phải có kế hoạch rotation và
rollback trước khi deploy.
