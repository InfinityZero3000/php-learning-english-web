# Trạng thái hệ thống hiện hành

Cập nhật: 31/07/2026. Đây là nguồn trạng thái hiện hành của dự án. Các file
trong `docs/superpowers/specs/` và `docs/superpowers/plans/` là hồ sơ thiết kế,
không phải backlog; checkbox chưa đánh dấu trong plan cũ không tự động có nghĩa
là chức năng còn thiếu.

## Kết quả xác minh

| Thành phần | Kết quả |
|---|---|
| Laravel | 519 test, 6.057 assertion, pass (1 skipped) |
| Pint | pass |
| Learner Next.js | 54 test, ESLint và production build pass; 24 route |
| Admin Next.js | 21 test, ESLint và production build pass; 29 route |
| OpenAPI | Redocly valid, 0 error, 12 warning được chấp nhận có ý thức |
| Runtime/OpenAPI parity | pass — 168 path khớp hai chiều (`ApiContractParityTest`) |
| `mysql-concurrency` | **chưa chạy** — cần MySQL thật; skipped trên SQLite |

## Đối chiếu plan/spec

| Phạm vi | Trạng thái | Bằng chứng chính |
|---|---|---|
| Foundation, Auth, profile, OAuth | Hoàn thành | Auth/profile controllers, hai auth UI và feature test |
| Public learner access | Hoàn thành | route policy, shell auth và test frontend |
| Blade sang Next.js | Hoàn thành ở runtime | `routes/web.php` chuyển hướng sang Next.js; learner/admin build pass |
| Catalog, bookmark, quiz, progress | Hoàn thành | API v1, learner UI và feature test |
| FSRS, session học, TraceCAG | Hoàn thành phạm vi HTTP | scheduler, review/session API, learner session/review UI và test |
| Teacher supervision | Hoàn thành phạm vi đã chốt | teacher API/UI, assignment, evidence, alert và test |
| Admin và Super Admin | Hoàn thành phạm vi ứng dụng | role-aware navigation, CRUD, analytics, import, operations và test |
| LexiLingo import | Hoàn thành luồng staged review | fetch chỉ staging; apply có cờ riêng, Google step-up và bảo vệ stale revision |
| Learning path tự sở hữu | Hoàn thành | `CourseLearningPath`, Unit CRUD/reorder, prerequisite, Course Path UI và test |
| Progress/FSRS insight | Hoàn thành | `LearnerProgressSummary`, `POST /api/v1/fsrs/preview` không mutate, UI Progress/Review |
| API documentation | Đồng bộ | parity hai chiều được test khoá; 0 operation ảo, 0 route thiếu tài liệu |
| Production verification | Chưa hoàn thành toàn bộ | Bước 1 pass; Bước 2–5 chưa chạy — xem `docs/release-evidence/2026-07-29-learning-path.md` |

## Chức năng còn thiếu thật sự

### Ưu tiên trước release

Chi tiết và ô ghi bằng chứng nằm trong
[`release-evidence/2026-07-29-learning-path.md`](release-evidence/2026-07-29-learning-path.md).

1. Chạy `--group=mysql-concurrency` trên MySQL thật. Test tồn tại và từ chối
   SQLite đúng thiết kế, nhưng chưa từng chạy thật nên tính chất "nhiều request
   ID đồng thời chỉ tạo một active session" chưa được chứng minh dưới row lock.
2. Chạy smoke test theo vai trò (learner, admin, super admin) trên staging với
   cấu hình thật; kiểm tra CSRF/cookie giữa Fly.io và hai Vercel project.
3. Xác minh provider LexiLingo thật: `--dry-run --limit=1` không lỗi
   transport/schema, rồi tạo đúng một staged run; chỉ ghi số đếm, không in
   payload hay secret.
4. Diễn tập backup/restore và rollback deploy theo runbook mục 2b.
5. Bật `FEATURE_LEXILINGO_IMPORT_APPLY` **chỉ trên staging** để thử ranh giới
   ghi, rồi tắt lại và assert cả runtime web lẫn queue đều thấy `false`.
   Production apply giữ `false` cho tới khi 1–4 có bằng chứng.

### Gap nghiệp vụ đã biết

- Course import chưa có nguồn chắc chắn để ánh xạ `category_id`; tags upstream
  chưa được chuẩn hóa thành `Topic`.
- Xác nhận lại gần đây cho thao tác đặc quyền của admin đã chốt là **Google
  re-auth ràng buộc theo session + subject** (`RecentGoogleAdmin`, hết hạn 15
  phút), không phải xác nhận mật khẩu. `RecentPassword` chỉ còn phục vụ learner
  xoá tài khoản. Backend, UI và OpenAPI đã đồng bộ theo contract này.
- Voice chỉ hỗ trợ HTTP STT/TTS/pronunciation. WebSocket streaming và voice
  ticket/readiness không thuộc phạm vi hiện tại.
- Chưa có object storage; ảnh/audio ngoài vẫn phụ thuộc URL upstream hoặc local
  storage.
- Chưa có mô hình class/membership, streak và moderator. Chỉ triển khai khi yêu
  cầu nghiệp vụ hoặc tiêu chí chấm bổ sung chúng.

## Giao diện

Không phát hiện màn bắt buộc nào trong spec hiện hành bị thiếu.

- Learner: Today, course, assignment, session/summary, listening, FSRS review,
  flashcards, vocabulary quiz/import, progress, profile và teacher workspace.
- Admin: dashboard/analytics, catalog CRUD, lesson/quiz authoring, vocabulary,
  deck, import, user/role, teacher scope, reports, notifications/preferences,
  operations và audit.
- `/review` không nằm trên sidebar nhưng có liên kết từ Today, dashboard và
  session summary. Bốn route admin cũ chỉ là alias sang route chính.

Trang course của admin đã quản lý Unit trực tiếp (tạo/sửa, reorder an toàn
va chạm, gán lesson, badge provenance) và `/review` đã hiển thị khoảng lặp dự
đoán cho cả bốn rating từ `POST /api/v1/fsrs/preview`. `/import` đã có lịch sử
run, bảng review theo phân loại, diff từng bản ghi và polling trạng thái.

## Quy ước đọc tài liệu

- `PROJECT_PLAN.md`: phạm vi và phân công cấp dự án.
- `CURRENT_STATUS.md`: trạng thái triển khai và backlog hiện tại.
- `superpowers/specs/`: quyết định thiết kế tại thời điểm viết.
- `superpowers/plans/`: trình tự triển khai lịch sử; không dùng checkbox cũ để
  suy ra trạng thái hiện tại.
- `openapi/laravel-v1.yaml`: contract công khai. Đồng bộ với runtime được
  `tests/Feature/ApiContractParityTest.php` khoá theo cả hai chiều — thêm route
  mà quên tài liệu (hoặc ngược lại) sẽ làm fail suite.
- `release-evidence/`: bằng chứng release theo ngày; mục nào ghi "chưa có" là
  chưa chạy, không phải đã pass.
