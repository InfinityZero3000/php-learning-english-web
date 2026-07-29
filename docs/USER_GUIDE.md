# Hướng dẫn sử dụng — Website học tiếng Anh

Date: 2026-07-29

## Phạm vi tài liệu này

Tài liệu này dành cho người muốn **chạy thử API học tập cục bộ** (cả luồng
learner và admin), không phải hướng dẫn cài đặt môi trường dev đầy đủ — xem
[README.md §"Cài đặt cho thành viên nhóm"](../README.md#cài-đặt-cho-thành-viên-nhóm)
cho việc đó (Docker, `.env`, build, migrate, seed).

Mọi ví dụ `curl` dưới đây đã được chạy thật với API local trên
`http://localhost:8080` sau `php artisan migrate:fresh --seed`, không phải
suy đoán từ code — response mẫu là response thật (rút gọn phần Unicode-escape
cho dễ đọc).

## Cài đặt nhanh để có API sẵn sàng

Giả định bạn đã hoàn thành setup trong README (đã `composer install`,
`.env`, container chạy). Đảm bảo có dữ liệu seed đã biết trước khi chạy các
ví dụ trong tài liệu này:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Xác nhận API sẵn sàng:

```bash
curl http://localhost:8080/api/v1/health
# {"status":"ok","version":"v1"}
```

**Không dùng URL hay giá trị nào trong tài liệu này cho môi trường
production** — `http://localhost:8080` và mọi tài khoản demo bên dưới chỉ
tồn tại trên DB local đã seed.

## Tài khoản demo

Được tạo bởi `database/seeders/CatalogSeeder.php` mỗi khi seed lại — chỉ
tồn tại trên DB local, không phải tài khoản thật:

| Vai trò | Email | Mật khẩu | Ghi chú |
|---|---|---|---|
| admin | `admin@example.com` | `admin123` | Role `admin`, dùng cho admin flow bên dưới |
| learner | `user@example.com` | `user123` | Role `learner`, dùng cho learner flow bên dưới |

Bộ [Postman collection](../postman/) (issue #28) dùng đúng hai tài khoản
này — xem `postman/local.postman_environment.json`.

## Cơ chế xác thực (bắt buộc đọc trước khi thử API)

Ứng dụng này xác thực bằng **session + CSRF cookie** (giống hệt
`admin-frontend/src/lib/api.ts`), **không dùng bearer token**. Với mọi
request POST/PUT/PATCH/DELETE, phải:

1. Gọi `GET /api/v1/csrf-cookie` trước để nhận cookie `XSRF-TOKEN`.
2. Giải mã URL (`urldecode`) giá trị cookie đó và gửi lại trong header
   `X-XSRF-TOKEN`.
3. Giữ cookie jar xuyên suốt (cookie session + XSRF-TOKEN) cho các request
   tiếp theo.

Ví dụ đăng nhập đầy đủ bằng `curl` (dùng cookie jar file):

```bash
curl -c cookies.txt http://localhost:8080/api/v1/csrf-cookie

XSRF=$(php -r "echo urldecode(\$argv[1]);" \
  "$(grep XSRF-TOKEN cookies.txt | awk '{print $7}')")

curl -c cookies.txt -b cookies.txt -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -d '{"email":"user@example.com","password":"user123"}'
```

Response thật:

```json
{"data":{"id":2,"name":"Standard Learner","email":"user@example.com","email_verified_at":"...","role":"learner"},"meta":[]}
```

Bộ Postman collection tại [`postman/`](../postman/) đã tự động hóa bước
này bằng pre-request script — nếu chỉ muốn thử nhanh qua UI, dùng Postman
thay vì `curl` thủ công (xem mô tả trong chính collection, folder
`0a - Login as Learner` / `0b - Login as Admin`).

## Luồng learner (learner flow)

Sau khi đăng nhập bằng tài khoản `user@example.com` như trên (cookie jar
`cookies.txt`), tiếp tục với cùng cookie jar:

**1. Duyệt catalog** (folder Postman "2 - Catalog"):

```bash
curl -b cookies.txt http://localhost:8080/api/v1/catalog/courses
```

```json
{"data":[{"id":1,"title":"Tiếng Anh Cơ Bản","slug":"tieng-anh-co-ban","status":"published","lessons_count":3,...}],"meta":{"current_page":1,"last_page":1,"per_page":20,"total":2}}
```

```bash
curl -b cookies.txt http://localhost:8080/api/v1/catalog/courses/1/lessons
```

**2. Bookmark một bài học** (folder "3 - Learning", cần CSRF token như
bước đăng nhập):

```bash
curl -c cookies.txt -b cookies.txt -X POST \
  http://localhost:8080/api/v1/bookmarks/lesson/1/toggle \
  -H "Accept: application/json" -H "X-XSRF-TOKEN: $XSRF"
```

```json
{"data":{"status":"bookmarked"},"meta":[]}
```

Gọi lại lần nữa với cùng lesson id sẽ trả `{"status":"unbookmarked"}` —
đây là hành vi toggle có chủ đích, không phải lỗi idempotency.

**3. Làm quiz**:

```bash
curl -b cookies.txt http://localhost:8080/api/v1/quizzes/1
```

Response liệt kê câu hỏi và đáp án — **không có trường `is_correct`**
(giữ ở server đến khi nộp bài):

```json
{"data":{"id":1,"title":"Quiz — Động vật hoang dã","questions":[{"id":1,"content":"...","answers":[{"id":1,"content":"con chó"},{"id":2,"content":"con mèo"},...]}]}}
```

Nộp bài (thay `question_id`/`answer_id` bằng id thật lấy từ response trên):

```bash
curl -c cookies.txt -b cookies.txt -X POST \
  http://localhost:8080/api/v1/quizzes/1/submit \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -d '{"answers":[{"question_id":1,"answer_id":2},{"question_id":2,"answer_id":7},{"question_id":3,"answer_id":11}]}'
```

```json
{"data":{"id":1,"quiz_id":1,"score":100,"completed_at":"...",...}}
```

**Nộp lại lần nữa (cùng quiz) sẽ trả về HTTP 200 với cùng Attempt cũ,
không tính điểm lại và không tạo bản ghi mới** — idempotent theo thiết kế.

**4. Xem tiến độ**:

```bash
curl -b cookies.txt http://localhost:8080/api/v1/progress/dashboard
```

```json
{"data":{"overview":{"completed_lessons":0,"quiz_attempts":1,"average_score":100},"recent_activity":[],"recent_attempts":[{"id":1,"quiz_id":1,"score":100,...}]}}
```

## Luồng admin (admin flow)

Đăng xuất tài khoản learner rồi đăng nhập lại bằng `admin@example.com` /
`admin123` (đúng chuỗi lệnh csrf-cookie → login như phần "Cơ chế xác
thực" ở trên, chỉ đổi email/password) trước khi thử các lệnh dưới đây.

**1. Quản lý taxonomy** (folder Postman "5 - Admin Taxonomy" —
`/api/v1/admin/*`, xác thực bằng `Gate::authorize()` trong controller,
**không** phải middleware `role:admin`):

```bash
curl -b admin_cookies.txt http://localhost:8080/api/v1/admin/topics
```

```json
{"data":[{"id":1,"name":"General","slug":"general","courses_count":0,"vocabularies_count":0},...],"meta":{"current_page":1,"last_page":1,"per_page":20,"total":5}}
```

**2. Quản lý người dùng và audit log** (folder "6 - Admin Users" —
`/api/admin/*`, xác thực bằng middleware `role:admin`, **cơ chế 403 khác**
với taxonomy ở trên):

```bash
curl -b admin_cookies.txt http://localhost:8080/api/admin/users
```

```json
{"users":[{"id":1,"email":"admin@example.com","role":"ADMIN","locked":false,...},{"id":2,"email":"user@example.com","role":"USER","locked":false,...}],"totalElements":2,"totalPages":1,"page":0,"size":20}
```

```bash
curl -b admin_cookies.txt http://localhost:8080/api/admin/audit-logs
```

Mọi hành động lock/unlock/reset-password/đổi role sẽ xuất hiện tại đây —
xem chi tiết cơ chế trong
[`docs/api/route-doc-gap-log.md`](api/route-doc-gap-log.md).

**Hai cơ chế 403 khác nhau, đã xác nhận bằng request thật**: một learner
đã đăng nhập gọi `GET /api/v1/admin/topics` nhận `403` từ
`Gate::authorize()`; cùng learner đó gọi `GET /api/admin/users` cũng nhận
`403` nhưng từ middleware `role:admin` (`App\Http\Middleware\CheckRole`) —
hai code path độc lập, cùng kết quả hôm nay nhưng dễ lệch nhau nếu chỉ sửa
một bên trong tương lai.

## Troubleshooting

Xem trước [README.md §"Xử lý lỗi thường gặp"](../README.md#xử-lý-lỗi-thường-gặp)
cho các lỗi cài đặt/container chung. Dưới đây chỉ là các lỗi riêng của
luồng API/Postman:

- **`401 Unauthenticated` dù vừa đăng nhập**: cookie jar không được giữ
  giữa các request (thiếu `-c`/`-b` cùng file trong `curl`, hoặc Postman
  chưa bật cookie jar cho domain). Gọi lại `GET /api/v1/auth/me` để kiểm
  tra session còn hiệu lực.
- **`419 CSRF token mismatch`**: thiếu header `X-XSRF-TOKEN`, hoặc gửi
  nguyên giá trị cookie chưa qua `urldecode` (giá trị cookie chứa
  `%3D`/`%2F`... phải giải mã trước khi đặt vào header).
- **`403` trên `/api/v1/admin/*` hoặc `/api/admin/*`**: tài khoản đang
  dùng không phải admin, hoặc đã đăng nhập nhầm tài khoản learner từ bước
  trước — kiểm tra `GET /api/v1/auth/me` để xác nhận `role`.
- **`503`/`504` khi gọi `/api/v1/content/*` hoặc `/api/v1/ai/*`**:
  `LEXILINGO_BACKEND_URL`/`LEXILINGO_AI_URL` chưa cấu hình trong `.env`
  local — các route này proxy tới dịch vụ LexiLingo thật, không hoạt động
  nếu chưa có host thật để trỏ tới. Xem
  [`docs/PRODUCTION_ENV.md`](PRODUCTION_ENV.md) mục LexiLingo.
- **Stack trace đầy đủ trong response lỗi**: bình thường khi
  `APP_DEBUG=true` (mặc định local) — production phải đặt `false`, khi đó
  response lỗi chỉ còn `{"message": "..."}`.

## Tài liệu liên quan

- [README.md](../README.md) — cài đặt môi trường dev đầy đủ.
- [`docs/openapi/laravel-v1.yaml`](openapi/laravel-v1.yaml) — hợp đồng API
  đầy đủ (schema, validation, mã lỗi) cho mọi route nhắc tới ở trên.
- [`docs/architecture.md`](architecture.md) — sơ đồ kiến trúc hệ thống và
  ERD.
- [`postman/`](../postman/) — Postman collection dùng chung (issue #28),
  tự động hóa toàn bộ luồng CSRF/session ở trên.
- [`docs/api/route-doc-gap-log.md`](api/route-doc-gap-log.md) — danh sách
  route và hai điểm khác biệt cơ chế 403/enrichment đã nhắc ở trên.
