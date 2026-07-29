# Trạng thái hệ thống hiện hành

Cập nhật: 29/07/2026. Đây là nguồn trạng thái hiện hành của dự án. Các file
trong `docs/superpowers/specs/` và `docs/superpowers/plans/` là hồ sơ thiết kế,
không phải backlog; checkbox chưa đánh dấu trong plan cũ không tự động có nghĩa
là chức năng còn thiếu.

## Kết quả xác minh

| Thành phần | Kết quả |
|---|---|
| Laravel | 394 test, 5.237 assertion, pass |
| Pint | pass |
| Learner Next.js | 42 test, ESLint và production build pass; 23 route |
| Admin Next.js | 5 test, ESLint và production build pass; 31 route |
| Admin navigation | 22 route chính và 4 alias, pass |

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
| LexiLingo import | Hoàn thành luồng local/queue | importer/checkpoint/failure/run UI và test bằng HTTP fake |
| Production verification | Chưa hoàn thành toàn bộ | chưa có smoke test với host/secret thật và diễn tập rollback đầy đủ |
| API documentation | Chưa đồng bộ hoàn toàn | 222 method-path runtime; 160 chưa có operation tương ứng trong OpenAPI |

## Chức năng còn thiếu thật sự

### Ưu tiên trước release

1. Đồng bộ OpenAPI với toàn bộ route đang hỗ trợ. Inventory theo method + path
   cho thấy runtime có 222 operation, trong đó 160 chưa có operation tương ứng.
   Khoảng trống lớn nhất là admin catalog, legacy admin compatibility, OAuth,
   listening, lesson quiz và import. OpenAPI cũng mô tả 13 operation không khớp
   runtime, gồm path catalog dạng `{resource}`, `sync-runs`, retry sync và secret
   rotation; phải thay bằng path thật hoặc bỏ sau khi chốt scope.
2. Chạy smoke test production cho learner, teacher, admin và super admin với
   cấu hình thật; kiểm tra CSRF/cookie giữa Fly.io và hai Vercel project.
3. Xác minh contract LexiLingo bằng OpenAPI/Swagger thật và chạy một import
   dry-run có giới hạn mà không ghi hoặc in secret.
4. Diễn tập backup/restore và rollback deploy; ghi lại kết quả trong production
   runbook.

### Gap nghiệp vụ đã biết

- Course import chưa có nguồn chắc chắn để ánh xạ `category_id`; tags upstream
  chưa được chuẩn hóa thành `Topic`.
- Spec yêu cầu xác nhận mật khẩu gần đây cho đổi role/teacher scope/quota/alert
  rule và import reset. Runtime hiện chỉ dựa vào Google-admin session/request ID;
  cần chốt một contract rồi đồng bộ backend, UI và OpenAPI.
- Unit được import và trả trong course detail nhưng chưa có Unit CRUD/API/UI độc
  lập. Lesson chỉ nhận `unit_id` đã tồn tại.
- Import UI mới hiển thị checkpoint và các run bắt đầu trong phiên trình duyệt;
  chưa có lịch sử run/failure đầy đủ hoặc polling trạng thái như production
  import spec.
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

Các chi tiết UI trong spec chưa có đầy đủ: trang course chưa thể quản lý/drill
down Unit độc lập và nút FSRS rating chưa hiển thị khoảng lặp dự đoán. Đây là
gap hoàn thiện, không phải màn route bị thiếu.

## Quy ước đọc tài liệu

- `PROJECT_PLAN.md`: phạm vi và phân công cấp dự án.
- `CURRENT_STATUS.md`: trạng thái triển khai và backlog hiện tại.
- `superpowers/specs/`: quyết định thiết kế tại thời điểm viết.
- `superpowers/plans/`: trình tự triển khai lịch sử; không dùng checkbox cũ để
  suy ra trạng thái hiện tại.
- `openapi/laravel-v1.yaml`: contract công khai, hiện còn cần đồng bộ như nêu
  trên.
