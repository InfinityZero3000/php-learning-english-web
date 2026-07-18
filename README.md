# Website học tiếng Anh

Bộ khung Laravel MVC cho đồ án lập trình mã nguồn mở của Nhóm 8.

## Công nghệ

- Laravel 13, PHP 8.5
- Nginx 1.28, MySQL 8.4, Redis 8
- Bootstrap 5.3 qua CDN
- PHPUnit và Laravel Pint

## Cài đặt cho thành viên nhóm

Yêu cầu: Docker Desktop hoặc Docker Engine có Compose.

### 1. Clone repository

```bash
git clone https://github.com/InfinityZero3000/php-learning-english-web.git
cd php-learning-english-web
```

Nếu đã clone trước đó:

```bash
git switch main
git pull --ff-only origin main
```

### 2. Khởi tạo môi trường

Tạo `.env` cá nhân từ file mẫu. Không gửi file này cho thành viên khác và không commit lên Git.

```bash
cp .env.example .env
```

Các biến development quan trọng:

| Biến | Giá trị mặc định | Mục đích |
|---|---|---|
| `APP_KEY` | Để trống ban đầu | Khóa mã hóa của Laravel; tạo ở bước tiếp theo |
| `APP_URL` | `http://localhost:8080` | URL chạy local |
| `APP_PORT` | `8080` | Port website trên máy host |
| `DB_HOST` | `mysql` | Tên service MySQL trong Docker |
| `DB_DATABASE` | `english_learning` | Database local |
| `DB_USERNAME` / `DB_PASSWORD` | `laravel` / `secret` | Tài khoản MySQL local |
| `DB_ROOT_PASSWORD` | `root` | Mật khẩu root MySQL local |
| `REDIS_HOST` | `redis` | Tên service Redis trong Docker |

Các giá trị này chỉ dành cho development. Production/Fly.io phải dùng secrets riêng theo [Development Workflow](docs/DEVELOPMENT_WORKFLOW.md).

### 3. Build và cài dependency

```bash
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
```

Lệnh `key:generate` tự ghi `APP_KEY` vào `.env`. Mỗi thành viên dùng key local riêng; không copy key production vào máy cá nhân.

### 4. Khởi động và tạo database

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
```

Kiểm tra trạng thái container:

```bash
docker compose ps
```

`mysql` và `redis` phải ở trạng thái healthy. Sau đó mở <http://localhost:8080>.

### 5. Làm việc hằng ngày

```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

Dừng project mà vẫn giữ database:

```bash
docker compose down
```

Không dùng `docker compose down -v` trừ khi muốn xóa toàn bộ dữ liệu MySQL/Redis local.

Các điểm kiểm tra:

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
- `docs/DEVELOPMENT_WORKFLOW.md`: branch, push, Pull Request, PHPUnit và CI/CD Fly.io.

Controller, Form Request, Policy, Resource và Service chỉ được tạo khi module nghiệp vụ tương ứng bắt đầu.

## Xử lý lỗi thường gặp

- **Port 8080, 3306 hoặc 6379 bị chiếm:** đổi `APP_PORT`, `FORWARD_DB_PORT` hoặc `FORWARD_REDIS_PORT` trong `.env`, rồi chạy lại Compose.
- **Thiếu application key:** chạy `docker compose exec app php artisan key:generate`.
- **Không kết nối MySQL:** kiểm tra `docker compose ps`; `mysql` phải ở trạng thái healthy và `DB_HOST=mysql`.
- **Không ghi được storage:** chạy `docker compose exec app chmod -R ug+rw storage bootstrap/cache`.
- **Thay đổi env chưa có hiệu lực:** chạy `docker compose exec app php artisan config:clear`.
- **Muốn làm sạch database dev:** chạy `docker compose exec app php artisan migrate:fresh --seed` (lệnh này xóa dữ liệu hiện có).

## Phạm vi skeleton

Chưa triển khai Auth, CRUD, phân quyền, chấm quiz, upload hoặc API nghiệp vụ. Fly.io CD hiện chỉ phục vụ mức demo đồ án; chưa có staging, worker hay object storage. Xem [đặc tả skeleton](docs/superpowers/specs/2026-07-17-laravel-mvc-skeleton-design.md) trước khi phát triển tiếp.

Quy trình cộng tác và tự động deploy được mô tả tại [Development Workflow](docs/DEVELOPMENT_WORKFLOW.md).
