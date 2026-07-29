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
