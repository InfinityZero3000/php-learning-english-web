# Git Workflow và Fly.io CD Design

## Mục tiêu

Bổ sung quy trình làm việc cho nhóm và tự động deploy ứng dụng skeleton lên Fly.io sau khi code được merge vào `main` và toàn bộ CI vượt qua.

## Thiết kế

- Nhóm dùng feature branch ngắn, Pull Request vào `main`, Conventional Commits ở mức đơn giản và không push trực tiếp vào `main`.
- GitHub Actions tiếp tục chạy PHPUnit trên PHP 8.3–8.5 với MySQL 8.4. Job deploy chỉ chạy cho sự kiện push vào `main`, phụ thuộc job test và dùng deploy token giới hạn phạm vi.
- Fly.io chạy một Machine gồm Nginx và PHP-FPM. Supervisord chạy foreground, quản lý `php-fpm -F` và `nginx -g 'daemon off;'`, tự khởi động lại process lỗi và chuyển tín hiệu dừng. Nginx nghe port 8080 và FastCGI tới `127.0.0.1:9000`. Image production cài dependency Composer, không chứa Node/Vite.
- `fly.toml` dùng region Singapore, internal port 8080, HTTPS, health check `/health`, rolling deploy và release command `php artisan migrate --force`.
- Tên ứng dụng lấy từ GitHub Variable `FLY_APP`; token lấy từ Secret `FLY_API_TOKEN`. Thông tin Laravel, MySQL và Redis được đặt bằng Fly secrets, không commit vào Git.

Job test chạy `./vendor/bin/pint --test`, migration/seed/rollback và PHPUnit. Deploy dùng `needs: tests`, nên Pint và PHPUnit đều là cổng bắt buộc.

## Cấu hình production

`fly.toml` đặt các giá trị không bí mật: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `DB_CONNECTION=mysql`, `SESSION_DRIVER=database`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=phpredis`.

Fly secrets bắt buộc: `APP_KEY`, `APP_URL`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_URL`. Nhà cung cấp Redis TLS dùng URL `rediss://`. Nếu MySQL yêu cầu CA hoặc tham số TLS riêng, nhóm phải cấu hình theo nhà cung cấp trước khi deploy; skeleton không commit chứng thư hoặc tắt xác minh TLS.

Migration phải theo hướng expand-contract và tương thích ngược với image trước đó. `fly releases rollback` chỉ rollback image, không rollback database. Không tự động chạy `migrate:rollback` trên production; khi schema gây lỗi, nhóm khôi phục backup của nhà cung cấp DB hoặc tạo migration sửa tiến có review.

## Giới hạn

Không provision MySQL/Redis, staging, queue worker, cron, volume hoặc multi-region. Nhóm phải cung cấp MySQL/Redis có thể truy cập từ Fly.io. Storage local là tạm thời; tính năng upload sau này phải dùng object storage.

## Tiêu chí hoàn thành

- Production image khởi động Nginx/PHP-FPM và phục vụ `/health` trên port 8080.
- Cấu hình Fly hợp lệ về mặt cú pháp và không chứa tên app/bí mật cố định.
- Deploy không chạy trên Pull Request hoặc khi test thất bại.
- Tài liệu mô tả đầy đủ branch, commit, push, PR, PHPUnit/Pint, migration, CI, thiết lập Fly secrets/variables, deploy và rollback.
