# Production Operation Runbook & Rollback Plan

**Người thực hiện**: Yuu-25-uuY (Thư) / annienhigiaq (Nhi)
**Ngày hoàn thành**: 2026-07-28
**Trạng thái**: Draft / Sẵn sàng cho Vận hành
**Dự án**: Website học tiếng Anh

Tài liệu này cung cấp hướng dẫn từng bước để deploy hệ thống lên production (Fly.io và Vercel), kiểm thử nhanh sau deploy (smoke test) và quy trình rollback dự phòng khi có sự cố.

---

## 1. Hướng dẫn Deploy lên Production

### Bước 1: Chuẩn bị Môi trường & Environment Keys
Trước khi deploy, hãy kiểm tra và thiết lập các biến môi trường nhạy cảm trên Fly.io:
```bash
# Thiết lập Laravel secrets trên Fly.io
fly secrets set APP_KEY="base64:your-laravel-key"
fly secrets set DB_HOST="your-db-host" DB_DATABASE="your-db-name" DB_USERNAME="your-db-user" DB_PASSWORD="your-db-password"
fly secrets set REDIS_URL="redis://your-redis-url"
fly secrets set LEXILINGO_IMPORT_KEY="your-import-key" LEXILINGO_AI_SERVICE_SECRET="your-ai-secret"
```

### Bước 2: Deploy Backend lên Fly.io
Chạy lệnh deploy bằng Fly CLI từ thư mục dự án chứa file `fly.toml`:
```bash
fly deploy --config fly.toml
```
*Lưu ý: Lệnh này tự động build Docker image theo `Dockerfile.fly`, đẩy lên Registry, chạy các migrations (`php artisan migrate --force`) và thực hiện khởi động rolling update.*

### Bước 3: Deploy Frontend & Admin-Frontend lên Vercel
- Các dự án Next.js kết nối trực tiếp với Repository trên GitHub.
- Khi nhánh `main` được cập nhật hoặc Pull Request được merge, Vercel sẽ tự động trigger quá trình build và release bản production.
- Đảm bảo biến môi trường `LARAVEL_API_ORIGIN` đã được set chính xác trong Project Settings của Vercel trỏ đến URL của Laravel API.

---

## 1b. Ranh giới ghi của import

Import chạy hai pha tách rời bằng hai cờ.

`FEATURE_LEXILINGO_IMPORT` mở fetch. Với `categories`, bản ghi được phân loại
(`new`, `update`, `conflict`, `unchanged`, `invalid`) và lưu vào `staged_items`
với `status='staged'` mà **không** chạm catalog. Với `courses`, `vocabulary` và
`lessons`, bản ghi vẫn ghi trực tiếp và staged item mang `status='applied'` —
đó là nhật ký, không phải hàng chờ duyệt.

`FEATURE_LEXILINGO_IMPORT_APPLY` mở pha ghi và mặc định `false` ở production.
Apply là Super Admin only, cần Google re-auth mới, khoá hàng đích, so
`catalog_revision` + `source_fingerprint` đã ghi lúc staging; hàng đã đổi được
đánh `stale` và **không** bị ghi đè. Chỉ item `status='staged'` được ghi, nên
gọi lại cùng lệnh là no-op.

Checkpoint (`lexilingo_import_checkpoints.cursor`) ghi vị trí catalog **thật sự**
đã đồng bộ tới, nên nó chỉ tiến khi dữ liệu đã nằm trong catalog: ngay lúc fetch
với entity ghi trực tiếp, và lúc apply với entity staged. Một staged run bị huỷ
hoặc không bao giờ được duyệt sẽ không đẩy cửa sổ fetch đi — lần fetch sau vẫn
thấy đúng trang đó. Checkpoint cũng chỉ tiến khi run không còn item nào ở
`status='staged'`; item `stale` kết thúc run mà không ghi được hàng của nó, muốn
lấy lại phải `--reset`.

**Không bao giờ dùng `migrate:rollback` để hoàn tác một import đã apply.** Cột
ownership là additive; rollback migration sẽ mất provenance của mọi nguồn. Muốn
hoàn tác nội dung, restore từ backup dữ liệu (mục 2b).

### Bật apply trên staging (không dùng cho production)

```bash
# Cài EXIT trap trước, để mọi nhánh thoát đều tắt lại cờ
trap 'fly secrets set FEATURE_LEXILINGO_IMPORT_APPLY=false && \
      fly ssh console -C "php artisan config:clear" && \
      fly apps restart "$STAGING_APP"' EXIT

fly secrets set FEATURE_LEXILINGO_IMPORT_APPLY=true
fly ssh console -C "php artisan config:clear"
fly apps restart "$STAGING_APP"    # queue worker đọc config cache riêng
```

Sau khi bật, phải chứng minh **cả** runtime web và runtime queue cùng thấy `true`
bằng assertion phía ứng dụng, không suy đoán từ giá trị secret. Kết thúc phiên,
kích hoạt trap và assert lại rằng cả hai runtime đã thấy `false`.

---

## 2. Quy trình Rollback dự phòng (Khi deploy lỗi)

### Rollback Backend (Fly.io)
Nếu bản deploy mới trên Fly.io gặp lỗi nghiêm trọng (mất kết nối, crash loop, HTTP 500 liên tục), thực hiện rollback về phiên bản ổn định trước đó:
```bash
# 1. Liệt kê danh sách các phiên bản đã deploy
fly releases

# 2. Quay về phiên bản ổn định gần nhất (ví dụ: phiên bản v12)
fly deploy --image registry.fly.io/linguist:v12
```

### Rollback Frontend (Vercel)
1. Truy cập Vercel Dashboard của dự án tương ứng.
2. Chọn tab **Deployments**.
3. Tìm bản deployment ổn định trước đó.
4. Click vào nút menu ba chấm (`...`) bên cạnh bản deploy đó và chọn **Instant Rollback**.
5. Xác nhận và Vercel sẽ chuyển hướng traffic về bản cũ trong vòng vài giây.

---

## 2b. Diễn tập Backup / Restore dữ liệu

Dùng MySQL option file để credential không lọt vào shell history hay process
list. Không bao giờ restore vào production trong lúc diễn tập.

```bash
# 1. Backup nhất quán từ production (chỉ đọc)
mysqldump --defaults-extra-file="$PROD_MYSQL_CNF" \
  --single-transaction --routines --triggers "$DB_DATABASE" > "$BACKUP_FILE"
shasum -a 256 "$BACKUP_FILE"          # ghi checksum vào file bằng chứng

# 2. Chốt cứng đích restore trước khi ghi bất kỳ byte nào
test -n "$RESTORE_DB_DATABASE"
test "$RESTORE_DB_DATABASE" != "$DB_DATABASE"
case "$RESTORE_DB_DATABASE" in restore_rehearsal_*) ;; *) echo 'Sai quy ước tên'; exit 1;; esac
mysql --defaults-extra-file="$RESTORE_MYSQL_CNF" -N -e 'SELECT DATABASE()' \
  | grep -qx "$RESTORE_DB_DATABASE" || { echo 'Option file trỏ sai DB'; exit 1; }

# 3. Restore và đối chiếu
mysql --defaults-extra-file="$RESTORE_MYSQL_CNF" "$RESTORE_DB_DATABASE" < "$BACKUP_FILE"
```

Sau restore: so row count và checksum các bảng trọng yếu, rồi chạy health check
ứng dụng trỏ vào đích restore. Ghi vào file bằng chứng: checksum/định danh
backup, đích restore, deployment reference của backend + learner + admin, và thứ
tự rollback (**tắt đường ghi trước, rollback deploy sau**) cùng quyết định có
khôi phục dữ liệu catalog hay không.

---

## 3. Smoke Test Checklist (Chạy thử kiểm tra sau deploy)

Sau khi hoàn tất quá trình deploy, người vận hành phải thực hiện kiểm thử nhanh theo checklist sau để đảm bảo hệ thống chạy tốt:

| STT | Endpoint / Hành động | Mong đợi | Trạng thái |
|---|---|---|:---:|
| 1 | `GET /health` | Trả về HTTP 200 `{ "status": "ok" }`. | [ ] |
| 2 | `GET /api/v1/health` | Trả về HTTP 200 `{ "status": "ok", "version": "v1" }`. | [ ] |
| 3 | Đăng nhập hệ thống | Đăng nhập thành công, nhận token và lưu session. | [ ] |
| 4 | Gọi `/api/v1/auth/me` | Trả về thông tin user đã đăng nhập chính xác. | [ ] |
| 5 | Giao diện học tập | Lấy danh sách danh mục và khoá học từ local MySQL ổn định. | [ ] |
| 6 | Ghi nhận log | Kiểm tra log console trên Fly.io (`fly logs`) xem có định dạng context `request_id`, `user_id` không. | [ ] |
| 7 | Tải file Audio / Dịch thuật | Thử gọi tính năng AI xem proxy kết nối sang LexiLingo API có bị lỗi gì không. | [ ] |
