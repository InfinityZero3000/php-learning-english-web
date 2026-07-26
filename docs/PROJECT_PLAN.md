# Kế hoạch đồ án Website học tiếng Anh

Nguồn: [Plan-Project-PHP](https://docs.google.com/spreadsheets/d/1FEMThp6qikntxxWZBMP4xU36zlVGsNTJ_NxxyTZHl3A/edit?gid=0#gid=0). Mốc báo cáo: **31/07/2026**.

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

Blade hiện tại là giao diện chuyển tiếp để Auth/Profile tiếp tục hoạt động trong
khi hai Next.js app được chuyển đổi. Giao diện production cuối cùng là
`frontend/` và `admin-frontend/`.

### Trạng thái xác minh 25/07/2026

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

### Trạng thái xác minh 26/07/2026 — Issue #9 (importer category/course/unit/lesson/vocabulary)

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

## Quy ước bàn giao

- Biến môi trường production được quản lý tại `docs/PRODUCTION_ENV.md`; mọi thay
  đổi env trong code phải cập nhật tài liệu này và `.env.example`.
- Mỗi nhiệm vụ có issue riêng, nhánh riêng và pull request được review.
- Migration đã merge không được sửa lịch sử; tạo migration mới khi schema thay đổi.
- Pull request phải chạy `php artisan test` và `./vendor/bin/pint --test`.
- Không commit `.env`, mật khẩu, token hoặc dữ liệu người dùng thật.
