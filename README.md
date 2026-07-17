# Website học tiếng Anh

Bộ khung Laravel MVC cho đồ án lập trình mã nguồn mở của Nhóm 8.

## Công nghệ

- Laravel 13, PHP 8.5
- Nginx 1.28, MySQL 8.4, Redis 8
- Bootstrap 5.3 qua CDN
- PHPUnit và Laravel Pint

## Khởi động bằng Docker

Yêu cầu: Docker Desktop hoặc Docker Engine có Compose.

```bash
cp .env.example .env
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
```

Mở <http://localhost:8080>. Các điểm kiểm tra:

- `GET /`: trang skeleton.
- `GET /health`: `{"status":"ok"}`.
- `GET /admin`: trang quản trị placeholder, chưa có xác thực.
- `GET /api/status`: `{"status":"ok","version":"v1"}`.

## Lệnh kiểm tra

```bash
docker compose config
docker compose exec app ./vendor/bin/pint --test
docker compose exec app php artisan test
docker compose exec app php artisan migrate:rollback
docker compose exec app php artisan migrate
docker compose exec redis redis-cli ping
curl http://localhost:8080/health
```

Redis phải trả `PONG`; health endpoint phải trả HTTP 200.

## Cấu trúc phát triển

- `app/Models`: model và quan hệ Eloquent nền.
- `database/migrations`: schema cho nội dung học, quiz và tiến độ.
- `database/seeders`: role, level và topic mẫu; không tạo user.
- `resources/views`: layout Bootstrap và trang placeholder.
- `routes`: điểm vào web và API.
- `docs/PROJECT_PLAN.md`: phân chia nhiệm vụ và phụ thuộc.

Controller, Form Request, Policy, Resource và Service chỉ được tạo khi module nghiệp vụ tương ứng bắt đầu.

## Xử lý lỗi thường gặp

- **Port 8080, 3306 hoặc 6379 bị chiếm:** đổi `APP_PORT`, `FORWARD_DB_PORT` hoặc `FORWARD_REDIS_PORT` trong `.env`, rồi chạy lại Compose.
- **Thiếu application key:** chạy `docker compose exec app php artisan key:generate`.
- **Không kết nối MySQL:** kiểm tra `docker compose ps`; `mysql` phải ở trạng thái healthy và `DB_HOST=mysql`.
- **Không ghi được storage:** chạy `docker compose exec app chmod -R ug+rw storage bootstrap/cache`.
- **Thay đổi env chưa có hiệu lực:** chạy `docker compose exec app php artisan config:clear`.
- **Muốn làm sạch database dev:** chạy `docker compose exec app php artisan migrate:fresh --seed` (lệnh này xóa dữ liệu hiện có).

## Phạm vi skeleton

Chưa triển khai Auth, CRUD, phân quyền, chấm quiz, upload, API nghiệp vụ hoặc production deployment. Xem [đặc tả skeleton](docs/superpowers/specs/2026-07-17-laravel-mvc-skeleton-design.md) trước khi phát triển tiếp.
