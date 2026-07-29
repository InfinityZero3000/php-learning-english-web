# Kiểm tra tự rà soát tài liệu — Issue #26

Date: 2026-07-29

## Phạm vi

Rà soát mọi liên kết và câu lệnh được thêm/sửa trong đợt tài liệu hóa issue
#26: `README.md`, `docs/USER_GUIDE.md`, `docs/architecture.md`,
`docs/openapi/laravel-v1.yaml`, `docs/api_docs_lexilingo.md`,
`docs/PROJECT_PLAN.md`.

## Liên kết đã kiểm

Mỗi dòng: nguồn → đích, cách xác minh.

| Nguồn | Đích | Xác minh |
|---|---|---|
| `README.md` §Tài liệu bàn giao | `docs/USER_GUIDE.md` | File tồn tại, tạo trong commit "docs: add user guide..." |
| `README.md` §Tài liệu bàn giao | `docs/architecture.md` | File tồn tại, tạo trong commit "docs: add system architecture..." |
| `README.md` §Tài liệu bàn giao | `docs/openapi/laravel-v1.yaml` | File tồn tại từ trước, `redocly lint` pass |
| `README.md` §Tài liệu bàn giao | `postman/` | Thư mục tồn tại từ issue #28 (đã merge) |
| `README.md` §Tài liệu bàn giao | `docs/PROJECT_PLAN.md` | File tồn tại từ trước; nâng cấp từ inline-code sang link thật |
| `README.md` §Tài liệu bàn giao | `docs/DEVELOPMENT_WORKFLOW.md` | File tồn tại từ trước |
| `README.md` §Tài liệu bàn giao | `docs/PRODUCTION_ENV.md` | File tồn tại từ trước |
| `README.md` §Tài liệu bàn giao | `docs/api_docs_lexilingo.md` | File tồn tại, đã sửa trong commit "docs: note LexiLingo endpoints..." |
| `README.md` §Tài liệu bàn giao | `docs/api/route-doc-gap-log.md` | File tồn tại từ issue #28 (đã merge) |
| `README.md` §Tài liệu bàn giao | `docs/DOCS_REVIEW_CHECKLIST.md` | File này — tự tham chiếu, xác nhận tồn tại sau commit |
| `docs/USER_GUIDE.md` | `../README.md#cài-đặt-cho-thành-viên-nhóm` | Khớp heading `## Cài đặt cho thành viên nhóm` trong README.md |
| `docs/USER_GUIDE.md` | `../README.md#xử-lý-lỗi-thường-gặp` | Khớp heading `## Xử lý lỗi thường gặp` trong README.md |
| `docs/USER_GUIDE.md` | `../postman/` | Thư mục tồn tại |
| `docs/USER_GUIDE.md` | `openapi/laravel-v1.yaml` | File tồn tại (đường dẫn tương đối từ `docs/`) |
| `docs/USER_GUIDE.md` | `architecture.md` | File tồn tại (đường dẫn tương đối từ `docs/`) |
| `docs/USER_GUIDE.md` | `api/route-doc-gap-log.md` | File tồn tại (đường dẫn tương đối từ `docs/`) |
| `docs/USER_GUIDE.md` | `PRODUCTION_ENV.md` | File tồn tại (đường dẫn tương đối từ `docs/`) |
| `docs/architecture.md` | `PROJECT_PLAN.md` | File tồn tại (đường dẫn tương đối từ `docs/`) |
| `docs/architecture.md` | `USER_GUIDE.md` | File tồn tại (đường dẫn tương đối từ `docs/`) |

## Câu lệnh đã chạy thật

Mỗi dòng: lệnh, nơi xuất hiện, kết quả thật đã quan sát (không phải suy đoán).

| Lệnh | Nơi xuất hiện | Kết quả |
|---|---|---|
| `docker compose exec app php artisan migrate:fresh --seed` | `docs/USER_GUIDE.md`, `README.md` §Postman | Chạy thật nhiều lần trong đợt này, seed 2 user + 2 course + 3 lesson + 2 quiz |
| `curl http://localhost:8080/api/v1/health` | `docs/USER_GUIDE.md` | Chạy thật, trả `{"status":"ok","version":"v1"}` |
| Chuỗi `curl` csrf-cookie → login (learner) | `docs/USER_GUIDE.md` §Cơ chế xác thực | Chạy thật, HTTP 200, trả đúng user learner |
| `GET /api/v1/catalog/courses`, `/catalog/courses/{id}/lessons` | `docs/USER_GUIDE.md` §Luồng learner | Chạy thật với session learner, HTTP 200 |
| `POST /api/v1/bookmarks/lesson/1/toggle` | `docs/USER_GUIDE.md` §Luồng learner | Chạy thật, `{"status":"bookmarked"}` HTTP 201 |
| `GET /api/v1/quizzes/1` | `docs/USER_GUIDE.md` §Luồng learner | Chạy thật, xác nhận response không có `is_correct` |
| `POST /api/v1/quizzes/1/submit` | `docs/USER_GUIDE.md` §Luồng learner | Chạy thật với đáp án đúng, `score:100` HTTP 201 |
| `GET /api/v1/progress/dashboard` | `docs/USER_GUIDE.md` §Luồng learner | Chạy thật, phản ánh đúng attempt vừa nộp |
| Chuỗi `curl` csrf-cookie → login (admin) | `docs/USER_GUIDE.md` §Luồng admin | Chạy thật, HTTP 200, trả đúng user admin |
| `GET /api/v1/admin/topics` | `docs/USER_GUIDE.md` §Luồng admin | Chạy thật với session admin, HTTP 200 (5 topic seed) |
| `POST /api/v1/admin/topics` | Xác minh thủ công (không đưa vào USER_GUIDE.md) | Chạy thật, tạo topic mới HTTP 201 |
| `GET /api/admin/users`, `/api/admin/audit-logs` | `docs/USER_GUIDE.md` §Luồng admin | Chạy thật với session admin, HTTP 200 |
| `GET /api/v1/admin/topics` (không session) và (session learner) | Xác minh thủ công cho §Luồng admin | Chạy thật: guest → 401 qua `Authenticate` middleware; learner → 403 qua `Gate::authorize()` |
| `GET /api/admin/users` (session learner) | Xác minh thủ công cho §Luồng admin | Chạy thật: 403 qua `App\Http\Middleware\CheckRole`, xác nhận khác code path với dòng trên |
| `npx --prefix frontend redocly lint docs/openapi/laravel-v1.yaml` | Verification (không phải nội dung doc) | Chạy thật, 0 lỗi, 9 warning (cùng loại 4 warning cũ trước đợt này) |
| `./vendor/bin/pint --test` | Verification | Chạy thật sau mỗi commit, pass (159 file) |
| `php artisan test` | Verification | Chạy thật sau mỗi commit, 226 tests pass |
| `npx @mermaid-js/mermaid-cli` trên cả 2 sơ đồ | Verification cho `docs/architecture.md` | Chạy thật, cả hai sinh SVG hợp lệ không lỗi |

## Xác nhận nghiệm thu

- [x] Không có URL/secret production thật trong bất kỳ ví dụ nào — chỉ dùng
      `http://localhost:8080` và hai tài khoản demo đã public sẵn trong
      `CatalogSeeder.php`.
- [x] Mọi ví dụ request trong `docs/USER_GUIDE.md` đã chạy thật với API
      local (xem bảng câu lệnh ở trên).
- [x] README liên kết đủ: USER_GUIDE, architecture, openapi, postman,
      PROJECT_PLAN, DEVELOPMENT_WORKFLOW, PRODUCTION_ENV,
      api_docs_lexilingo, route-doc-gap-log, checklist này.
- [x] `docs/api_docs_lexilingo.md` có ghi chú tập con endpoint LexiLingo
      thực sự được Laravel gọi.
- [x] Tài liệu API (`docs/openapi/laravel-v1.yaml`) khớp kết quả regression
      từ issue #28 — mọi route trong `docs/api/route-doc-gap-log.md` mục
      "Missing from the OpenAPI spec entirely" nay đã có trong spec.
