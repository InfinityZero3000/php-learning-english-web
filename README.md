# Website học tiếng Anh

Hệ thống học tiếng Anh gồm Laravel API, learner Next.js và admin Next.js của
Nhóm 8. Trạng thái chức năng và phần còn thiếu được duy trì tại
[`docs/CURRENT_STATUS.md`](docs/CURRENT_STATUS.md).

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

Các giá trị này chỉ dành cho development. Production/Fly.io phải dùng secrets
riêng theo [Production Environment Variables](docs/PRODUCTION_ENV.md) và
[Development Workflow](docs/DEVELOPMENT_WORKFLOW.md).

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

`mysql` và `redis` phải ở trạng thái healthy. Sau đó mở
<http://localhost:8080>. Adminer để xem dữ liệu MySQL chạy tại
<http://localhost:8081>.

Đăng nhập Adminer bằng:

| Trường | Giá trị local |
|---|---|
| System | `MySQL` |
| Server | `mysql` |
| Username | Giá trị `DB_USERNAME` |
| Password | Giá trị `DB_PASSWORD` |
| Database | Giá trị `DB_DATABASE` |

Adminer chỉ có trong `compose.yaml` cho local/dev, không được deploy lên Fly
production.

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

- `GET /`: chuyển sang learner frontend đã cấu hình.
- `GET /health`: readiness check cho ứng dụng, MySQL và Redis đang sử dụng.
- `GET /admin`: chuyển sang admin Next.js; admin dùng xác thực session.
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

## Postman

Bộ Postman collection + environment tại `postman/` là nguồn xác minh API
dùng chung cho toàn bộ `/api/v1` và `/api/admin` — issue tài liệu API (#26)
chỉ liên kết tới đây, không tạo bản sao khác.

```bash
docker compose exec app php artisan migrate:fresh --seed
# Import postman/php-learning-english-web.postman_collection.json
# và postman/local.postman_environment.json vào Postman, hoặc chạy CLI:
npx newman run postman/php-learning-english-web.postman_collection.json \
  -e postman/local.postman_environment.json --folder "0a - Login as Learner" \
  --folder "2 - Catalog" --folder "3 - Learning"
```

Ứng dụng xác thực bằng session + CSRF cookie (không dùng bearer token), nên
collection cần chạy đúng thứ tự đăng nhập/đăng xuất — xem mô tả trong
collection (`postman/php-learning-english-web.postman_collection.json`) để
biết chi tiết từng folder.

## Cấu trúc phát triển

- `app/Models`: model và quan hệ Eloquent nền.
- `database/migrations`: schema cho nội dung học, quiz và tiến độ.
- `database/seeders`: role, level và topic mẫu; không tạo user.
- `frontend/`: giao diện learner Next.js.
- `admin-frontend/`: giao diện admin và super admin Next.js.
- `routes`: điểm vào web và API.
- `docs/PROJECT_PLAN.md`: phân chia nhiệm vụ và phụ thuộc.
- `docs/CURRENT_STATUS.md`: trạng thái triển khai và backlog hiện hành.
- `docs/USER_GUIDE.md`: hướng dẫn learner, teacher, admin và super admin.
- `docs/architecture.md`: ranh giới hệ thống và luồng dữ liệu.
- `docs/DEVELOPMENT_WORKFLOW.md`: branch, push, Pull Request, PHPUnit và CI/CD Fly.io.
- `postman/`: Postman collection + environment dùng chung cho kiểm thử API thủ công.

Controller, Form Request, Policy, Resource và Service chỉ được tạo khi module nghiệp vụ tương ứng bắt đầu.

## Xử lý lỗi thường gặp

- **Port 8080, 3306 hoặc 6379 bị chiếm:** đổi `APP_PORT`, `FORWARD_DB_PORT` hoặc `FORWARD_REDIS_PORT` trong `.env`, rồi chạy lại Compose.
- **Thiếu application key:** chạy `docker compose exec app php artisan key:generate`.
- **Không kết nối MySQL:** kiểm tra `docker compose ps`; `mysql` phải ở trạng thái healthy và `DB_HOST=mysql`.
- **Không ghi được storage:** chạy `docker compose exec app chmod -R ug+rw storage bootstrap/cache`.
- **Thay đổi env chưa có hiệu lực:** chạy `docker compose exec app php artisan config:clear`.
- **Muốn làm sạch database dev:** chạy `docker compose exec app php artisan migrate:fresh --seed` (lệnh này xóa dữ liệu hiện có).

## Trạng thái triển khai

Auth/profile, catalog, quiz, progress, FSRS, learning session, teacher,
admin/super-admin và hai giao diện Next.js đã có implementation và test.
OpenAPI đã đồng bộ với runtime (parity hai chiều được `ApiContractParityTest`
khoá). Các việc còn lại trước release là mở `apply` sang đủ 6 entity import
(hiện chỉ `categories`), xác minh LexiLingo với provider thật, smoke test theo
vai trò trên staging và diễn tập backup/rollback; xem
[`docs/CURRENT_STATUS.md`](docs/CURRENT_STATUS.md).

Quy trình cộng tác và tự động deploy được mô tả tại [Development Workflow](docs/DEVELOPMENT_WORKFLOW.md).
