# Release evidence — Self-owned Learning Path

Plan: `docs/superpowers/plans/2026-07-29-self-owned-learning-path.md`
Design: `docs/superpowers/specs/2026-07-29-self-owned-learning-path-design.md`
Branch: `feat/import-approval-google-stepup`
Ngày ghi nhận: 2026-07-31

> Trạng thái tổng: **Bước 1 hoàn tất. Bước 2–5 chưa chạy** vì cần staging host,
> secret provider thật và quyền truy cập production DB — không có trong môi
> trường phát triển này. `FEATURE_LEXILINGO_IMPORT_APPLY` **vẫn `false`** ở mọi
> môi trường; production apply chưa được mở và không được mở cho tới khi Bước
> 2–5 có bằng chứng ký nhận bên dưới.

---

## Bước 1 — Cổng kiểm thử cục bộ

Đã chạy 2026-07-31 trên `darwin 24.6.0`, PHP 8.3, SQLite in-memory cho suite mặc định.

| Cổng | Lệnh | Kết quả |
|---|---|---|
| Pint | `./vendor/bin/pint --test` | **pass** |
| Laravel | `php artisan test` | **pass** — 519 test, 6.057 assertion, 1 skipped |
| Learner Vitest | `cd frontend && vitest run` | **pass** — 14 file, 54 test |
| Learner ESLint | `cd frontend && eslint .` | **pass** — 0 finding |
| Learner build | `cd frontend && next build` | **pass** — 24 route |
| Admin Vitest | `cd admin-frontend && vitest run` | **pass** — 6 file, 21 test |
| Admin ESLint | `cd admin-frontend && eslint .` | **pass** — 0 finding |
| Admin build | `cd admin-frontend && next build` | **pass** — 29 route |
| OpenAPI lint | `redocly lint docs/openapi/laravel-v1.yaml` | **valid** — 0 error, 12 warning (xem bên dưới) |
| Contract parity | `php artisan test tests/Feature/ApiContractParityTest.php` | **pass** — 168 path, khớp hai chiều |
| Postman | `json_decode(..., JSON_THROW_ON_ERROR)` | **valid** |

### Warning Redocly được chấp nhận có ý thức

12 warning, tất cả thuộc hai nhóm đã có từ trước và không phản ánh sai lệch contract:

- `operation-4xx-response` (7): endpoint public chỉ đọc (`/health`, `/csrf-cookie`,
  `/vocabulary`, `/catalog/topics`, `/catalog/lessons`, `/content/news`,
  `/content/youtube`) không phát sinh 4xx xác định trong runtime.
- `operation-2xx-response` (5): endpoint chỉ trả redirect (`302`) — OAuth entry,
  callback, admin handoff start và email verify. Không có 2xx để khai báo.

### Nhóm `mysql-concurrency` — **chưa chạy**

`php artisan test --group=mysql-concurrency` báo **skipped**:
`CourseLearningPathMysqlConcurrencyTest` từ chối chạy trên SQLite (đúng thiết
kế — nó cần row lock thật của MySQL). Máy phát triển này không có MySQL server
(`127.0.0.1:3306` từ chối kết nối) và không có Docker daemon.

**Chưa được chứng minh:** hai request ID đồng thời trên cùng enrollment chỉ tạo
một active session dưới MySQL row lock. Trước release phải chạy:

```bash
DB_CONNECTION=mysql DB_HOST="$MYSQL_TEST_HOST" DB_PORT="$MYSQL_TEST_PORT" \
DB_DATABASE="$MYSQL_TEST_DATABASE" DB_USERNAME="$MYSQL_TEST_USERNAME" \
DB_PASSWORD="$MYSQL_TEST_PASSWORD" php artisan test --group=mysql-concurrency
```

| Trường | Giá trị |
|---|---|
| Người chạy | _(chưa có)_ |
| Thời điểm | _(chưa có)_ |
| MySQL version | _(chưa có)_ |
| Kết quả | _(chưa có)_ |

### Seeder kịch bản release

`ReleaseImportScenarioSeeder` + `ReleaseImportScenarioSeederTest`: **pass** (4 test).
Đã chứng minh seeder từ chối `production`, từ chối chạy khi
`features.lexilingo_import_apply=true`, chỉ tạo source identity dưới tiền tố
`release-fixture-`, và phát manifest gồm 4 run ID riêng biệt cho `add`,
`upstream_update`, `local_conflict`, `stale`.

---

## Bước 2 — Smoke test trình duyệt theo vai trò — **CHƯA CHẠY**

Không có staging URL và ba danh tính seeded. Phải điền trước khi release.

| Trường | Giá trị |
|---|---|
| Staging URL | _(chưa có)_ |
| Backend release | _(chưa có)_ |
| Learner deployment ID | _(chưa có)_ |
| Admin deployment ID | _(chưa có)_ |
| `RELEASE_LEARNER_EMAIL` → user ID | _(chưa có)_ |
| `RELEASE_ADMIN_EMAIL` → user ID | _(chưa có)_ |
| `RELEASE_SUPER_ADMIN_EMAIL` → user ID | _(chưa có)_ |
| Thời điểm (UTC) | _(chưa có)_ |

Chạy với apply **vẫn tắt**. Không ghi token hay payload vào bằng chứng.

| # | Luồng | Request ID | Kết quả |
|---|---|---|:---:|
| 1 | Learner: enroll → Course Path → lesson đủ điều kiện → session → summary | | [ ] |
| 2 | Learner: Progress (retention, forecast, breakdown) → Review (4 nhãn interval) | | [ ] |
| 3 | Admin: tạo/sửa Unit, reorder, gán lesson, badge provenance | | [ ] |
| 4 | Admin: lịch sử import, bảng review, diff, lưu draft | | [ ] |
| 5 | Learner gọi route admin → `403`; Admin gọi route super admin → `403` | | [ ] |
| 6 | Console/API error đã ghi (không kèm token/payload) | | [ ] |

---

## Bước 3 — Xác minh provider thật có giới hạn — **CHƯA CHẠY**

Cần secret LexiLingo thật và `APP_ENV=staging`.

```bash
php artisan tinker --execute='throw_unless(app()->environment("staging") \
  && config("features.lexilingo_import") \
  && ! config("features.lexilingo_import_apply"));'
php artisan lexilingo:import all --limit=1 --dry-run   # yêu cầu 0 lỗi transport/schema
php artisan lexilingo:import all --limit=1             # tạo đúng 1 staged run
```

| Trường | Giá trị |
|---|---|
| Tiền điều kiện đã chứng minh | _(chưa có)_ |
| Lỗi transport/schema ở dry-run | _(chưa có)_ |
| Staged run ID | _(chưa có)_ |
| Đếm theo phân loại (new / duplicate / conflict / invalid) | _(chưa có)_ |

Chỉ ghi số đếm. Không in payload hay credential.

---

## Bước 4 — Diễn tập backup/restore và rollback deploy — **CHƯA CHẠY**

Quy trình: `docs/security/production-runbook.md` mục 2b.

| Trường | Giá trị |
|---|---|
| Checksum/định danh backup | _(chưa có)_ |
| Đích restore (`restore_rehearsal_*`) | _(chưa có)_ |
| Row count / checksum đối chiếu | _(chưa có)_ |
| Health check trên đích restore | _(chưa có)_ |
| Backend / learner / admin deployment reference | _(chưa có)_ |
| Thứ tự rollback (tắt đường ghi trước) | _(chưa có)_ |
| Quyết định khôi phục dữ liệu catalog | _(chưa có)_ |

Không restore vào production. Không dùng migration rollback để hoàn tác import.

---

## Bước 5 — Bật apply trên staging và thử ranh giới ghi — **CHƯA CHẠY**

Chỉ chạy sau khi Bước 1–4 pass. Dùng manifest của
`php artisan db:seed --class=ReleaseImportScenarioSeeder --force`, không phụ
thuộc vào việc trang provider thật có đủ mọi phân loại.

| Trường | Giá trị |
|---|---|
| Manifest run ID / source identity | _(chưa có)_ |
| EXIT trap đã cài | _(chưa có)_ |
| Runtime web thấy `apply=true` | _(chưa có)_ |
| Runtime queue thấy `apply=true` | _(chưa có)_ |

| # | Kiểm tra | Kết quả |
|---|---|:---:|
| 1 | Admin apply `new` — không cần step-up | [ ] |
| 2 | Admin thay thế `upstream_update` sau Google re-auth | [ ] |
| 3 | Super Admin thay thế `local_conflict` sau Google re-auth | [ ] |
| 4 | Run `stale` bị từ chối `409`, quay lại `review_ready`, checkpoint không tiến | [ ] |
| 5 | Vai trò thấp hơn nhận `403` (không phải `428`) | [ ] |
| 6 | Cleanup chạy ở cả nhánh thành công và thất bại | [ ] |
| 7 | Runtime web **và** queue đều thấy `apply=false` sau cleanup | [ ] |

Không bật apply ở production trong bước này.

---

## Tiêu chí hoàn thành còn treo

- [ ] `mysql-concurrency` chạy trên MySQL thật (Bước 1).
- [ ] Smoke test theo vai trò trên staging (Bước 2).
- [ ] Dry-run + staged run với provider thật (Bước 3).
- [ ] Backup/restore và rollback deploy (Bước 4).
- [ ] Ranh giới ghi trên staging (Bước 5).
- [ ] Chỉ khi tất cả mục trên xong mới cân nhắc `FEATURE_LEXILINGO_IMPORT_APPLY`
      ở production; hiện tại giữ `false`.
