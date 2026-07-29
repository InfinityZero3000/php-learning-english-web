# Kế hoạch đồ án Website học tiếng Anh

Nguồn: [Plan-Project-PHP](https://docs.google.com/spreadsheets/d/1FEMThp6qikntxxWZBMP4xU36zlVGsNTJ_NxxyTZHl3A/edit?gid=0#gid=0). Mốc báo cáo: **31/07/2026**.

> Trạng thái hiện hành: xem [`CURRENT_STATUS.md`](CURRENT_STATUS.md). Các mục
> "Trạng thái xác minh" theo ngày bên dưới là nhật ký lịch sử, không phải backlog
> hiện tại.

| # | Module | Nhiệm vụ | Phụ trách | Phụ thuộc | Sản phẩm bàn giao |
|---|---|---|---|---|---|
| 1 | Nền tảng | Laravel, MVC, routing, env, Docker, GitHub workflow | Thắng | Không | Repository, `compose.yaml`, README |
| 2 | Cơ sở dữ liệu | ERD, migrations, seeders, factories, indexes, Eloquent | Chưa phân công | 1 | ERD, migrations, seeders |
| 3 | Xác thực | Đăng ký/đăng nhập/đăng xuất, quên mật khẩu, profile, CSRF, validation | Chưa phân công | 1, 2 | Auth controllers, requests, views |
| 4 | Frontend | Layout, navbar, sidebar, trang chủ, khóa học, học tập, dashboard | Thắng | 1 | Blade components, assets |
| 5 | Nội dung học | CRUD Course, Level, Topic, Vocabulary, tìm kiếm/lọc/phân trang/upload | Thư | 2, 4 | Controllers, services, views |
| 6 | Nội dung học | Lesson, Quiz, Question, Answer, làm bài và chấm điểm | Thư | 2, 3, 5 | Quiz/Lesson module |
| 7 | Học tập | Tiến độ, điểm quiz, lịch sử học, bookmark, dashboard cá nhân | Thành | 2, 3, 6 | Progress module, dashboard queries |
| 8 | Quản trị | Dashboard admin, quản lý user/nội dung, role, policy/gate/middleware | Thư, Nhi | 3, 5, 6 | Admin module, policies, middleware |
| 9 | API | REST API course, lesson, vocabulary, quiz result, progress | Nhi, Thư, Thành, Danh | 3, 5, 6, 7 | `routes/api.php`, resources, Postman |
| 10 | Bảo mật | CSRF, SQLi, XSS, upload validation, rate limit, quyền tài nguyên | Chưa phân công | 3, 5, 8, 9 | Security checklist, test evidence |
| 11 | Chất lượng mã | PSR-12/Pint, exception, logging có context | Chưa phân công | Các module đang phát triển | Pint config, exception handlers, logs |
| 12 | Kiểm thử | Feature/Unit test cho Auth, CRUD, quyền, quiz và API | Chưa phân công | 3, 5, 6, 8, 9 | `tests/`, test report |
| 13 | Triển khai | Laravel, MySQL, Redis, queue/cache, HTTPS, backup/rollback | Thắng | 9, 10, 11, 12 | Production URL, deployment guide |
| 14 | Tài liệu | Cài đặt, kiến trúc/DB, tài khoản demo, API, hướng dẫn, troubleshooting | Nhi | 5–13 | README, User Guide, diagrams |
| 15 | Bảo vệ | Báo cáo, slide, demo end-to-end, phân công thuyết trình, Q&A | Chưa phân công | Tất cả | Report, slides, demo script, Q&A |

## Thứ tự thực hiện

1. Hoàn tất nền tảng và schema để các nhánh dùng chung cấu trúc ổn định.
2. Làm Auth và layout trước các module cần tài khoản/giao diện.
3. Làm Course/Vocabulary trước Lesson/Quiz, rồi mới làm Progress và Admin.
4. Chốt API sau khi model và nghiệp vụ web ổn định.
5. Bảo mật, test và chất lượng mã chạy xuyên suốt; triển khai sau khi các kiểm tra chính vượt qua.

## Kiến trúc tích hợp đã chốt

Laravel là hệ thống chính và là nguồn dữ liệu nghiệp vụ duy nhất:

- Laravel quản lý tài khoản, đăng nhập, email, phân quyền, khóa học, tiến độ,
  quiz và dữ liệu người dùng.
- Hai ứng dụng Next.js (`frontend/`, `admin-frontend/`) chỉ gọi Laravel API.
- LexiLingo cung cấp dataset Course/Unit/Lesson/Vocabulary để đồng bộ về MySQL.
- AI, dịch, STT và TTS của LexiLingo được Laravel gọi server-to-server; frontend
  không giữ token hoặc service secret.
- Frontend deploy thành hai Vercel Project; Laravel API deploy trên Fly.io.

### Các phase triển khai

| Phase | Phạm vi | Kết quả |
|---|---|---|
| 1 | Schema và API contract | Unit, external ID, metadata nội dung, response/error chuẩn |
| 2 | Nền tảng API | Auth middleware, CSRF/CORS/rewrite, rate limit, health |
| 3 | Auth và Mail | Register/login/logout, verify, forgot/reset, profile |
| 4 | Dataset | Import category/course/unit/lesson/vocabulary từ LexiLingo |
| 5 | Học tập | Catalog, bookmark, quiz, progress, lịch sử và FSRS |
| 6 | Admin | Dashboard, CRUD nội dung, user, role/policy |
| 7 | Frontend | Chuyển user/admin Next.js sang Laravel API contract |
| 8 | AI Services | Proxy translate, pronunciation, STT và TTS; AI tutor chỉ triển khai sau khi có external-subject contract |
| 9 | Chất lượng/Deploy | Feature test, frontend build, Vercel/Fly verification |

### Nguyên tắc dữ liệu LexiLingo

- Import bằng `external_id` và `upsert()` để chạy lại không tạo dữ liệu trùng.
- Dataset văn bản được lưu tại MySQL; ảnh/audio ngoài vẫn phụ thuộc LexiLingo
  cho đến khi dự án có object storage riêng.
- Không import user, token, progress, notification hoặc dữ liệu cá nhân.
- Request AI thời gian thực phải có timeout, lỗi an toàn và không làm lộ secret.

Các route giao diện Laravel hiện chuyển hướng sang hai ứng dụng Next.js.
Giao diện production là `frontend/` và `admin-frontend/`; Blade không còn là
nguồn giao diện runtime.

### Nhật ký xác minh 25/07/2026 (lịch sử)

- Hoàn thành contract Laravel v1, schema import LexiLingo, migration/model tích
  hợp, session Auth API, mail/password/profile API và login guard cho hai
  frontend.
- Laravel đã có cấu hình server-to-server cho hai host LexiLingo qua
  `LEXILINGO_BACKEND_URL` và `LEXILINGO_AI_URL`; import key và AI service
  secret chỉ đọc từ biến môi trường.
- `php artisan test`: **55 tests, 236 assertions, pass**.
- `./vendor/bin/pint --test`: pass.
- LexiLingo JSON Schema fixtures: pass.
- Vocabulary core-data sync đã có lệnh
  `php artisan lexilingo:sync-vocabulary`: ghi/upsert vào MySQL theo
  `external_id`, cache page upstream ngắn hạn bằng Redis; request runtime đọc
  dữ liệu local thay vì gọi LexiLingo lặp lại.
- Redocly OpenAPI lint: valid, còn 4 warning tài liệu không chặn build.
- Learner frontend: TypeScript, ESLint và production build pass.
- Admin frontend: TypeScript và production build pass; ESLint còn 12 lỗi trong
  các màn CRUD cũ chưa nối Laravel API.
- Chưa hoàn thành: protected lesson-content sync (`description`/
  `prerequisites`/`estimated_minutes`/`pass_threshold` qua
  `/api/v1/learning/lessons/{id}/content`), các proxy
  translate/STT/TTS/pronunciation, admin CRUD API, browser smoke production và
  kiểm thử trực tiếp với host/secret LexiLingo thật.

### Nhật ký xác minh 26/07/2026 — Issue #9 (lịch sử)

- Importer idempotent đã có cho category, course (kèm unit và lesson outline
  lồng trong course detail) và vocabulary: validate bằng
  `docs/openapi/lexilingo-import.schema.json` (thư viện `opis/json-schema`,
  draft 2020-12) trước khi upsert theo `external_id`.
- Checkpoint/cursor lưu ở bảng `lexilingo_import_checkpoints` (theo từng
  entity); payload không hợp lệ được archive an toàn vào
  `lexilingo_import_failures` thay vì làm fail cả trang.
- Lệnh `php artisan lexilingo:import {entity} --limit= --dry-run --reset`
  (entity: `categories`/`courses`/`vocabulary`/`all`). `--dry-run` không ghi
  DB. Mỗi course được upsert (cùng unit/lesson) trong transaction riêng — một
  course lỗi chỉ rollback chính nó, không ảnh hưởng các course khác trong
  cùng trang.
- `tests/Feature/LexiLingoImportTest.php`: fixture hợp lệ/không hợp lệ, rerun
  idempotent, rollback theo transaction, dry-run không ghi DB — đều có test.
- Chưa làm trong đợt này: liên kết `category_id` cho course (payload course
  list không mang `category_id`), đồng bộ `tags` sang `Topic`, và nội dung đầy
  đủ từng lesson (vẫn chỉ có outline).

### Nhật ký xác minh 27/07/2026 — Issue #10 (lịch sử)

- 4 endpoint mới dưới `/api/v1/ai/*` (`translate`, `pronunciation`,
  `speech-to-text`, `text-to-speech`), yêu cầu đăng nhập, dùng
  `LexiLingoClient::internalAi()` (đã có sẵn từ trước, chưa từng có caller
  thật) nên `X-AI-Service-Secret` không lộ ra frontend.
- Validate `mimes:mp3,wav,m4a,ogg` + `max:LEXILINGO_AI_MAX_AUDIO_KB` cho audio,
  `max` độ dài nội dung cho text; retry giới hạn
  (`LEXILINGO_AI_RETRY_TIMES`/`_DELAY_MS`, mặc định 2 lần/200ms) chỉ khi
  timeout hoặc lỗi 5xx, không retry lỗi 4xx.
- Lỗi upstream được chuẩn hóa, không bao giờ trả nguyên body upstream:
  timeout/connection → 504 `UPSTREAM_TIMEOUT`; upstream 4xx → 422
  `UPSTREAM_REJECTED`; upstream 5xx → 502 `UPSTREAM_ERROR`. Log chỉ ghi
  action/user_id/exception class/status — không log audio, text hay secret.
- text-to-speech trả về audio nhị phân trực tiếp (không bọc JSON); 3 endpoint
  còn lại relay nguyên JSON body của upstream trong `data` (xem giới hạn bên
  dưới).
- `tests/Feature/Api/V1/AiProxyTest.php`: đủ 6 kịch bản theo acceptance
  criteria (success/validation/timeout/4xx/5xx/rate-limit) trên `translate`,
  cộng test riêng cho audio validation, JSON passthrough, response nhị phân
  và guard chưa đăng nhập.
- `docs/openapi/laravel-v1.yaml` đã thêm 4 path trên, `redocly lint` pass
  (0 lỗi, giữ nguyên 4 warning cũ không liên quan).
- **Giới hạn đã biết**: `docs/api_docs_lexilingo.md` chỉ liệt kê method+path,
  không có schema request/response thật (schema nằm ở Swagger của chính AI
  Service, sandbox này không truy cập được) — field request là suy đoán hợp
  lý theo REST convention, và response JSON được relay nguyên trạng thay vì
  ánh xạ field cụ thể. Cần đối chiếu lại khi có Swagger/OpenAPI thật của AI
  Service. Không triển khai `WEBSOCKET /api/v1/stt/stream`, `GET
  /api/v1/voice/ready`, `POST /api/v1/voice/ticket` (thuộc luồng streaming
  thời gian thực, kiến trúc khác với proxy HTTP này).

### Nhật ký xác minh 28/07/2026 — Issue #30 (lịch sử)

- `users` có thêm `locked_at`/`last_login_at`; đăng nhập session ghi
  `last_login_at` và từ chối tài khoản đã khóa (`403 ACCOUNT_LOCKED`) trước
  khi tạo session — nếu không, khóa tài khoản sẽ không có tác dụng thật.
- Bảng `audit_logs` (actor snapshot theo email/role tại thời điểm ghi, action,
  resource, detail, ip, status) và model `AuditLog::record()`; ghi cho cả 4
  action `USER_LOCKED`/`USER_UNLOCKED`/`ROLE_CHANGED`/`PASSWORD_RESET`, không
  bao giờ ghi mật khẩu thật vào `detail`.
- API JSON `/api/admin/users` (list có search theo tên/email, filter
  role/status, phân trang `page` 0-based), `/api/admin/users/{id}`,
  `/api/admin/users/{id}/history`, `PUT .../lock`, `PUT .../unlock`,
  `POST .../reset-password`, `PUT .../role`, `GET /api/admin/audit-logs` —
  đặt trong `routes/spa.php` (nhóm `web` middleware, dùng chung session/CSRF
  với `api/v1/*`), **không** dùng `routes/api.php` (nhóm `api` mặc định
  không có session/CSRF, sai với cách app này xác thực).
- Role trả về ở API là `ADMIN`/`USER` (map từ 2 role hiện có `admin`/
  `learner`); `MODERATOR` chỉ dùng được ở filter danh sách (trả về rỗng), gán
  role `MODERATOR` bị từ chối 422 vì role này chưa tồn tại trong DB.
- Bảo vệ quyền cuối cùng: không tự khóa/tự hạ quyền chính mình, không khóa
  hoặc hạ quyền quản trị viên đang hoạt động cuối cùng. Khi porting logic hạ
  quyền từ `Admin\UserController::updateRole` (Blade) sang API, phát hiện và
  sửa một lỗi có sẵn: guard cũ chỉ kiểm tra tổng số admin mà không kiểm tra
  *người bị đổi quyền* có đang là admin hay không, dẫn tới false-block khi hạ
  quyền một learner không liên quan trong lúc hệ thống chỉ có 1 admin — đã có
  test hồi quy cho case này.
- Route model binding đổi sang nhận `int` + `findOrFail()` thủ công **sau**
  `Gate::authorize()` ở mọi action nhận `{user}` — phát hiện qua test rằng
  route binding mặc định resolve trước khi middleware `role` kiểm tra quyền,
  khiến một learner có thể phân biệt user id tồn tại (403) hay không (404),
  lộ thông tin tồn tại tài khoản.
- `tests/Feature/Admin/UserManagementApiTest.php`: list/search/filter/phân
  trang, stats/history tính từ `UserVocabulary`/`VocabularyReview` thật,
  lock/unlock (kèm guard tự khóa và khóa admin cuối), đổi role (kèm guard và
  test hồi quy ở trên), reset password (không lộ mật khẩu vào audit log),
  audit log ghi/liệt kê đúng, 401/403 cho mọi route kể cả với id không tồn
  tại (chống rò rỉ IDOR).
- `admin-frontend`: nối `/users`, `/users/{id}`, `/audit-logs` vào API thật
  (trước đây `/audit-logs` dùng dữ liệu mock cứng); đã kiểm tra bằng
  TypeScript build + ESLint và một phiên trình duyệt thật (đăng nhập
  `admin@example.com`, thao tác lock/unlock/reset-password/đổi role, xem
  audit log xuất hiện đúng).
- Chưa làm trong đợt này (cố ý, ghi lại để không nhầm là thiếu sót): tính
  năng Deck (trang chi tiết user luôn trả `decks: []` — app này chưa có khái
  niệm Deck), tính năng Streak (luôn trả 0/null — chưa có bảng/nghiệp vụ
  streak), role `MODERATOR` thật sự (chỉ là placeholder ở frontend), action
  audit `ADMIN_LOGIN`/`WORD_*`/`DECK_*`/`SETTINGS_CHANGED` (thuộc phạm vi
  tính năng khác). Reset password trả mật khẩu tạm dạng plaintext một lần
  (theo đúng hợp đồng UI đã build sẵn) thay vì gửi email reset link — có thể
  cân nhắc đổi hướng này khi có yêu cầu bảo mật chặt hơn.

### Nhật ký xác minh 29/07/2026 — Issue #28 (lịch sử)

- Rà soát toàn bộ route `/api/v1` và `/api/admin` (auth, catalog, learning,
  admin taxonomy/media, admin user management, AI proxy) so với độ phủ test
  hiện có; bổ sung test cho các gap thật sự tìm thấy: validation lỗi cho
  register/login, 401 cho route `/api/v1/profile*`, 404 cho id không tồn tại
  (catalog/bookmark/quiz/progress/taxonomy/media), validate answer không
  khớp question khi submit quiz, cách ly dữ liệu giữa user ở dashboard tiến
  độ, pagination shape (`per_page`/`last_page`) cho danh sách taxonomy, test
  idempotent-theo-slug còn thiếu cho category, và 4 route hoàn toàn chưa có
  test (`/`, `content/news`, `content/youtube`, `enrichment/words/{id}`).
- `php artisan test`: **226 tests pass** (từ 218 trước đợt này, +8 test file
  mới/mở rộng, không đụng test đã có).
- `./vendor/bin/pint --test`: pass.
- Postman collection + environment mới tại `postman/` — không dùng bearer
  token mà dùng đúng cơ chế session + CSRF cookie của app (pre-request
  script tự lấy `/api/v1/csrf-cookie` và gắn `X-XSRF-TOKEN`, giống hệt
  `admin-frontend/src/lib/api.ts`). Đã chạy thật bằng `newman run` (không chỉ
  đọc JSON) trên `migrate:fresh --seed` local — phát hiện và sửa 2 lỗi thiết
  kế thật: (1) gộp login/logout vào chung 1 folder khiến chạy folder đó luôn
  kết thúc ở trạng thái đăng xuất, đã tách thành 3 folder độc lập
  `0a`/`0b`/`0c`; (2) request kiểm tra "non-admin bị 403" đặt trong folder
  chỉ chạy được với session admin nên luôn tự fail — bỏ script test tự động,
  giữ lại như request tham khảo có mô tả rõ cách chạy thủ công.
- Gap giữa route thực tế và `docs/openapi/laravel-v1.yaml` được ghi lại tại
  `docs/api/route-doc-gap-log.md` để bàn giao cho issue #26 — **không** tự
  viết thêm OpenAPI path/schema trong đợt này, đúng phạm vi đã thống nhất.
- README.md đã liên kết `postman/` làm nguồn Postman duy nhất.
- Chưa làm trong đợt này (cố ý): tự viết OpenAPI cho các path còn thiếu
  (việc của #26); một số route trong `4 - AI Proxy` của Postman collection
  cần `LEXILINGO_AI_URL`/`LEXILINGO_BACKEND_URL` cấu hình thật mới chạy được
  local, không đảm bảo chạy được ngay mặc định.

## Quy ước bàn giao

- Biến môi trường production được quản lý tại `docs/PRODUCTION_ENV.md`; mọi thay
  đổi env trong code phải cập nhật tài liệu này và `.env.example`.
- Mỗi nhiệm vụ có issue riêng, nhánh riêng và pull request được review.
- Migration đã merge không được sửa lịch sử; tạo migration mới khi schema thay đổi.
- Pull request phải chạy `php artisan test` và `./vendor/bin/pint --test`.
- Không commit `.env`, mật khẩu, token hoặc dữ liệu người dùng thật.
