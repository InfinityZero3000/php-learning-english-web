# Quy trình Git, kiểm thử và CI/CD

Tài liệu này áp dụng cho toàn bộ thành viên Nhóm 8. Nhánh `main` luôn phải ở trạng thái chạy được và chỉ nhận thay đổi qua Pull Request.

## 1. Chia nhánh

Mỗi GitHub Issue tương ứng một nhánh ngắn hạn:

| Loại | Mẫu tên | Ví dụ |
|---|---|---|
| Tính năng | `feature/<issue>-<ten-ngan>` | `feature/12-course-crud` |
| Sửa lỗi | `fix/<issue>-<ten-ngan>` | `fix/27-quiz-score` |
| Tài liệu | `docs/<issue>-<ten-ngan>` | `docs/31-api-guide` |
| Bảo trì | `chore/<issue>-<ten-ngan>` | `chore/18-pint` |

Không tạo nhánh dài hạn theo tên thành viên và không push trực tiếp vào `main`.

```bash
git switch main
git pull --ff-only origin main
git switch -c feature/12-course-crud
```

Trước khi mở Pull Request, cập nhật nhánh bằng rebase:

```bash
git fetch origin
git rebase origin/main
```

Nếu có xung đột, sửa từng file, chạy `git add <file>`, rồi `git rebase --continue`. Dùng `git rebase --abort` để quay lại trạng thái trước rebase.

## 2. Commit và push

Commit nhỏ, chỉ chứa một thay đổi có thể review. Dùng các tiền tố đơn giản:

- `feat:` thêm chức năng.
- `fix:` sửa lỗi.
- `test:` thêm hoặc sửa test.
- `docs:` sửa tài liệu.
- `chore:` cấu hình hoặc bảo trì.
- `refactor:` đổi cấu trúc nhưng không đổi hành vi.

```bash
git status
git diff
git add app/Http/Controllers/CourseController.php tests/Feature/CourseTest.php
git commit -m "feat: add course listing"
git push -u origin feature/12-course-crud
```

Không commit `.env`, token, mật khẩu, database dump, `vendor/` hoặc dữ liệu người dùng thật. Sau khi đã rebase một nhánh cá nhân, dùng `git push --force-with-lease`; không dùng `--force`.

## 3. Pull Request

Pull Request phải:

1. Liên kết Issue bằng `Closes #<số>`.
2. Mô tả thay đổi, cách kiểm tra và ảnh giao diện nếu có.
3. Có migration/rollback note khi thay đổi database.
4. Vượt qua toàn bộ GitHub Actions.
5. Có ít nhất một thành viên khác review trước khi merge.

Ưu tiên **Squash and merge** để lịch sử `main` gọn. Xóa feature branch sau khi merge.

## 4. PHPUnit và Pint

### Chạy toàn bộ kiểm tra

```bash
docker compose exec app ./vendor/bin/pint --test
docker compose exec app php artisan test
```

### Chạy một nhóm hoặc một test

```bash
docker compose exec app php artisan test --testsuite=Feature
docker compose exec app php artisan test tests/Feature/SkeletonRoutesTest.php
docker compose exec app php artisan test --filter=test_health_endpoint_is_available
```

Quy ước:

- `tests/Unit`: logic PHP độc lập, không cần HTTP/database.
- `tests/Feature`: route, Auth, database, middleware và API.
- Dùng `RefreshDatabase` cho test ghi database.
- Mỗi lỗi hồi quy phải có một test thất bại trước khi sửa.
- Không phụ thuộc thứ tự test hoặc dữ liệu còn lại từ test trước.

Khi thay đổi migration:

```bash
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan migrate:rollback
docker compose exec app php artisan migrate
```

`migrate:fresh` xóa toàn bộ dữ liệu, chỉ dùng trong development/test.

## 5. CI trên GitHub Actions

Workflow `.github/workflows/tests.yml` chạy khi push, mở/cập nhật Pull Request và theo lịch. CI:

1. Khởi tạo MySQL 8.4.
2. Cài dependency Composer trên PHP 8.3, 8.4 và 8.5.
3. Chạy Pint.
4. Chạy migrate, seed, rollback và migrate lại.
5. Chạy PHPUnit.

Không merge khi bất kỳ matrix job nào thất bại. Xem log tại tab **Actions**, sửa lỗi trên cùng feature branch và push lại.

Nên bật branch protection cho `main`:

- Require a pull request before merging.
- Require at least one approval.
- Require status checks to pass.
- Require branches to be up to date.
- Block force pushes và deletions.

## 6. Thiết lập Fly.io một lần

CD dùng một Fly Machine tại Singapore. MySQL và Redis phải là dịch vụ ngoài có thể truy cập từ Fly.io.

### Tạo app và deploy token

```bash
fly auth login
fly apps create <fly-app-name>
fly tokens create deploy -a <fly-app-name> -x 720h
```

Trong GitHub repository, mở **Settings → Secrets and variables → Actions**:

- Variable `FLY_APP`: tên app vừa tạo.
- Environment secret `FLY_API_TOKEN`: deploy token vừa tạo.

Tạo environment tên `production`. Có thể bật **Required reviewers** nếu giảng viên/nhóm trưởng muốn duyệt thủ công trước deployment.

### Cấu hình Fly secrets

Tạo application key:

```bash
docker compose run --rm app php artisan key:generate --show
```

Đặt secrets, thay toàn bộ giá trị mẫu:

```bash
fly secrets set -a <fly-app-name> \
  APP_KEY='<base64-app-key>' \
  APP_URL='https://<fly-app-name>.fly.dev' \
  DB_HOST='<mysql-host>' \
  DB_PORT='3306' \
  DB_DATABASE='<database>' \
  DB_USERNAME='<username>' \
  DB_PASSWORD='<password>' \
  REDIS_URL='rediss://<username>:<password>@<redis-host>:<port>'
```

Không đưa các giá trị này vào `fly.toml`, `.env.example`, Issue hoặc log CI. Dùng deploy token giới hạn theo app và xoay token định kỳ.

## 7. Tự động deploy

Mỗi push vào `main` (thông thường là commit tạo bởi Squash and merge):

1. Job `tests` chạy Pint, migration và PHPUnit.
2. Nếu mọi PHP matrix đều đạt, job `deploy` chạy `flyctl deploy --remote-only`.
3. Fly build `Dockerfile.fly`, chạy `php artisan migrate --force`, sau đó rolling deploy.
4. Fly chỉ chuyển traffic khi `/health` trả HTTP 200. Endpoint này kiểm tra HTTP/process liveness; kết nối MySQL/Redis được xác minh bởi release migration và log ứng dụng.

Pull Request, branch khác, scheduled test hoặc CI thất bại sẽ không deploy.

Theo dõi production:

```bash
fly status -a <fly-app-name>
fly checks list -a <fly-app-name>
fly logs -a <fly-app-name>
```

## 8. Rollback và database

Liệt kê image của các release:

```bash
fly releases --app <fly-app-name> --image
```

Deploy lại image tốt gần nhất:

```bash
fly deploy --app <fly-app-name> --image registry.fly.io/<fly-app-name>:<image-tag>
```

Rollback image **không rollback database**. Migration production phải tương thích ngược theo hướng expand-contract. Không tự động chạy `migrate:rollback` trên production; nếu schema gây lỗi, khôi phục backup của nhà cung cấp DB hoặc tạo migration sửa tiến và review qua Pull Request.

## 9. Giới hạn hiện tại

- Chưa có staging, queue worker, cron hoặc multi-region.
- Filesystem Fly Machine là tạm thời; module upload phải dùng object storage trước production.
- `composer.lock` phải được cập nhật cùng `composer.json`; không xóa lockfile khỏi Pull Request.
