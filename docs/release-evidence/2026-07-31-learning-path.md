# Release evidence — Self-owned Learning Path

Kế hoạch: `docs/superpowers/plans/2026-07-29-self-owned-learning-path.md`
Ngày ghi nhận: 2026-07-31

> **Trạng thái:** Bước 1 (cổng kiểm thử) **hoàn tất, gồm cả MySQL thật trên CI**.
> Bước 2–5 (staging, provider thật, backup/restore, mở apply) **chưa chạy** —
> cần staging host, secret provider và quyền production DB.
> `FEATURE_LEXILINGO_IMPORT_APPLY` **vẫn `false`** ở mọi môi trường.

## Phạm vi đã hợp nhất

Hai nhánh công việc song song đã được kết hợp qua 4 PR:

| PR | Nội dung |
|---|---|
| #61 | Catalog ownership; giữ chỉnh sửa local khi sync; `conflict`/`unchanged` sinh ra thật; staged item phân biệt `staged`/`applied`; Google step-up ràng buộc session+subject; cờ `FEATURE_LEXILINGO_IMPORT_APPLY` |
| #62 | Unit authoring + reorder an toàn va chạm, prerequisite, `CourseLearningPath`, FSRS insight + preview không mutate, UI Course Path/Progress/Review |
| #63 | Apply tuân thủ ownership: ghi qua `syncFromSource`, token chống stale đổi từ `updated_at` sang `catalog_revision` + fingerprint |
| #64 | Parity OpenAPI ↔ runtime khoá bằng test hai chiều |

Phần import staging/history/diff/polling của #58/#59 được giữ nguyên kiến trúc
(`staged_items`, `StagedItem`, `useImportPolling`, trang `/import`).

## Bước 1 — Cổng kiểm thử

Local (SQLite) 2026-07-31, và CI GitHub Actions trên **MySQL 8.4 × PHP 8.3/8.4/8.5**.

| Cổng | Kết quả |
|---|---|
| `./vendor/bin/pint --test` | pass |
| `php artisan test` (local, SQLite) | pass — 500 test, 5.882 assertion, 1 skipped |
| `php artisan test` (CI, MySQL 8.4) | pass — **0 skipped** |
| `migrate:fresh --seed` → `migrate:rollback` → `migrate` (CI) | pass |
| Learner Vitest / ESLint / build | pass — 54 test, 24 route |
| Admin Vitest / ESLint / build | pass — 22 test, 29 route |
| `redocly lint docs/openapi/laravel-v1.yaml` | valid — 0 error, 12 warning được chấp nhận có ý thức |
| `ApiContractParityTest` | pass — 164 path khớp hai chiều |
| Postman collection | JSON hợp lệ |

### `mysql-concurrency` — **đã chạy và pass**

Gap này trước đây bị ghi là "chưa chạy". Nay đã đóng:
`CourseLearningPathMysqlConcurrencyTest::concurrent request ids create one
active enrollment lesson session` **PASS trên MySQL 8.4** trong job Backend của
CI (5,20s). Test từ chối chạy trên SQLite nên nó chỉ có ý nghĩa ở đó; local vẫn
báo skipped, đó là đúng thiết kế.

### Warning Redocly được chấp nhận có ý thức

12 warning, đều thuộc hai nhóm có sẵn từ trước:
- `operation-4xx-response` (7): endpoint public chỉ đọc, không phát sinh 4xx xác định.
- `operation-2xx-response` (5): endpoint chỉ trả redirect `302` (OAuth entry/callback/handoff, email verify).

## Bước 2–5 — **CHƯA CHẠY**

Chưa có staging host, secret LexiLingo thật, và quyền production DB.

| Bước | Nội dung | Trạng thái |
|---|---|:---:|
| 2 | Smoke test theo vai trò (learner / admin / super admin) trên staging | [ ] |
| 3 | `lexilingo:import all --limit=1 --dry-run` rồi tạo một staged run với provider thật | [ ] |
| 4 | Diễn tập backup/restore và rollback deploy (runbook mục 2b) | [ ] |
| 5 | Bật `FEATURE_LEXILINGO_IMPORT_APPLY` **chỉ trên staging**, thử ranh giới ghi, rồi tắt lại | [ ] |

Ô cần điền cho Bước 2: staging URL, backend release, hai deployment ID frontend,
ID của ba danh tính `RELEASE_*`, timestamp, request ID, pass/fail. Không ghi
token hay payload.

## Giới hạn đã biết của luồng import hiện tại

- `apply` **chỉ hỗ trợ `categories`**; các entity khác trả `422`. Courses,
  vocabulary và lessons vẫn ghi trực tiếp lúc fetch (staged item của chúng mang
  `status='applied'`, là nhật ký chứ không phải hàng chờ duyệt).
- Chưa có hành động review theo từng item, chưa có cascade exclusion, chưa có
  thứ tự phụ thuộc khi apply. Đây là phạm vi GĐ3b.
- `lexilingo_import_failures` chưa có đường replay; bản ghi lỗi chỉ được lưu lại.

## Tiêu chí còn treo

- [ ] Bước 2–5 ở trên.
- [ ] Mở rộng apply ra đủ 6 entity theo thứ tự phụ thuộc (GĐ3b).
- [ ] Chỉ khi tất cả xong mới cân nhắc bật apply ở production; hiện giữ `false`.
