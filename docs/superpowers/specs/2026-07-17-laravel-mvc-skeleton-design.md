# Laravel MVC Skeleton Design

## Mục tiêu

Khởi tạo bộ khung cho đồ án mã nguồn mở “Website học tiếng Anh” theo kế hoạch trong Google Sheet. Kết quả phải giúp nhóm bắt đầu phát triển và chia việc ngay, nhưng chưa triển khai nghiệp vụ CRUD, xác thực, phân quyền, quiz hoặc thống kê.

## Phạm vi

- Tạo một ứng dụng Laravel theo cấu trúc MVC chuẩn.
- Cung cấp môi trường Docker Compose gồm Nginx, PHP-FPM, MySQL và Redis.
- Tạo schema và model nền cho các miền đã có trong kế hoạch.
- Tạo route, giao diện và kiểm thử smoke ở mức đủ chứng minh skeleton hoạt động.
- Ghi hướng dẫn cài đặt và kế hoạch chia module/phụ thuộc.

Không thuộc phạm vi hiện tại: nghiệp vụ hoàn chỉnh, xác thực/admin seed, giao diện chi tiết, tích hợp upload thật, queue job, API hoàn chỉnh, production deployment và bộ test nghiệp vụ.

Nguồn phạm vi là bảng [Plan-Project-PHP](https://docs.google.com/spreadsheets/d/1FEMThp6qikntxxWZBMP4xU36zlVGsNTJ_NxxyTZHl3A/edit?gid=0#gid=0), gồm các nhóm việc: nền tảng, cơ sở dữ liệu, xác thực, frontend, nội dung học, tiến độ, quản trị, API, bảo mật, chất lượng mã, kiểm thử, triển khai, tài liệu và bảo vệ. Skeleton hiện tại chỉ thực hiện phần nền tảng, schema nền, layout và điểm vào cho các nhóm còn lại.

## Phiên bản mục tiêu

- Laravel `^13.0` và PHP 8.5. Laravel 13 hỗ trợ PHP 8.3–8.5; chọn 8.5 để đồng bộ tài liệu cài đặt hiện hành.
- MySQL 8.4 LTS, Redis 8 Alpine, Nginx 1.28 Alpine và Composer 2.
- Bootstrap 5.3 qua CDN; chưa thêm Node/Vite build vì trang placeholder không cần bundle tài sản.

## Kiến trúc

Ứng dụng giữ nguyên cấu trúc Laravel mặc định để thành viên dễ tra cứu tài liệu và tiếp tục phát triển. Không thêm kiến trúc module tùy biến, Repository interface hoặc Service class rỗng.

- `app/Models`: các thực thể Eloquent nền.
- `app/Http/Controllers`: chỉ có controller khi route hiện tại cần nó; placeholder đơn giản dùng closure.
- `app/Http/Requests`, `app/Policies`, `app/Http/Resources`: dùng thư mục chuẩn của Laravel khi module tương ứng được triển khai; không tạo class rỗng.
- `database/migrations`: schema ban đầu và khóa ngoại.
- `database/seeders`: dữ liệu phân loại tối thiểu (role, level, topic); chưa seed tài khoản vì Auth chưa thuộc phạm vi.
- `resources/views`: master layout Bootstrap và trang chào/dashboard placeholder.
- `routes/web.php`, `routes/api.php`: nhóm route public, learner, admin và API ở mức placeholder.

## Schema tối thiểu

Mọi bảng có `id` bigint và timestamps, trừ pivot. Các cột chuỗi có độ dài mặc định Laravel; nội dung dài dùng `text`.

- `roles`: `name` unique, `slug` unique.
- `users`: cột mặc định Laravel và `role_id` nullable; xóa role đặt khóa ngoại thành null.
- `levels`: `name`, `slug` unique, `sort_order` unsigned integer mặc định 0.
- `topics`: `name`, `slug` unique.
- `courses`: `level_id` nullable, `title`, `slug` unique, `description` nullable, `status` mặc định `draft`; index `(status, level_id)`.
- `course_topic`: khóa ghép (`course_id`, `topic_id`), cascade khi course/topic bị xóa.
- `lessons`: `course_id`, `title`, `slug`, `content` nullable, `sort_order` mặc định 0, `status` mặc định `draft`; unique `(course_id, slug)` và cascade theo course.
- `vocabularies`: `lesson_id` nullable, `topic_id` nullable, `word`, `meaning`, `example` nullable, `image_path` nullable, `audio_path` nullable; xóa lesson/topic đặt null, index `word`.
- `quizzes`: `lesson_id`, `title`, `passing_score` unsigned tiny integer mặc định 60, `status` mặc định `draft`; cascade theo lesson.
- `questions`: `quiz_id`, `content`, `explanation` nullable, `sort_order` mặc định 0; cascade theo quiz.
- `answers`: `question_id`, `content`, `is_correct` boolean mặc định false; cascade theo question.
- `attempts`: `user_id`, `quiz_id`, `score` unsigned tiny integer, `started_at` nullable, `completed_at` nullable; cascade theo user/quiz, index `(user_id, quiz_id)`.
- `progress`: `user_id`, `lesson_id`, `completed_at` nullable; unique `(user_id, lesson_id)`, cascade theo user/lesson.
- `bookmarks`: `user_id`, `vocabulary_id`; unique `(user_id, vocabulary_id)`, cascade theo user/vocabulary.

Các giá trị trạng thái ban đầu chỉ gồm chuỗi `draft` và `published`, được lưu bằng `string` thay vì DB enum để module CRUD có thể thay đổi quy tắc sau này. `passing_score` và `score` được giới hạn 0–100 ở tầng migration bằng unsigned tiny integer và sẽ được validation nghiệp vụ bổ sung sau.

Các quan hệ chính:

- Role có nhiều User.
- Level thuộc Course; Topic có quan hệ nhiều-nhiều với Course và một-nhiều với Vocabulary.
- Course có nhiều Lesson; Lesson có thể có Vocabulary và Quiz.
- Quiz có nhiều Question; Question có nhiều Answer.
- User có nhiều Attempt, Progress và Bookmark.

Tên bảng, khóa ngoại và index tuân theo quy ước Laravel. Migration phải rollback được.

## Luồng chạy và giao diện

Nginx chuyển request vào PHP-FPM và thư mục `public/`. Laravel dùng MySQL làm cơ sở dữ liệu, Redis được cấu hình sẵn cho cache/queue nhưng chưa có job nghiệp vụ.

Trang `GET /` render Blade layout Bootstrap và thông báo skeleton sẵn sàng. `GET /health` trả JSON `{ "status": "ok" }` với HTTP 200. `GET /admin` trả Blade placeholder với HTTP 200 và ghi rõ chưa có Auth; không hiển thị dữ liệu quản trị. `GET /api/status` trả JSON gồm `status: ok` và `version: v1` với HTTP 200. Các route placeholder chưa được bảo vệ vì không thực hiện thao tác hoặc trả dữ liệu nhạy cảm; middleware Auth/Policy được thêm cùng module xác thực.

`routes/api.php` được đăng ký trong `bootstrap/app.php` theo cơ chế Laravel 13. API thực tế sau này dùng prefix `/api/v1`; endpoint trạng thái giữ `/api/status` để phục vụ health/smoke check.

## Hợp đồng Docker

- `app`: build từ `docker/php/Dockerfile`, base `php:8.5-fpm-alpine`, cài extension cần cho Laravel/MySQL và lấy Composer 2 từ image chính thức; mount mã nguồn vào `/var/www/html`.
- `nginx`: image `nginx:1.28-alpine`, mount cấu hình read-only và mã nguồn, phụ thuộc `app`, public port `${APP_PORT:-8080}:80`.
- `mysql`: image `mysql:8.4`, volume `mysql-data`, public port `${FORWARD_DB_PORT:-3306}:3306`, healthcheck bằng `mysqladmin ping`.
- `redis`: image `redis:8-alpine`, volume `redis-data`, public port `${FORWARD_REDIS_PORT:-6379}:6379`, healthcheck bằng `redis-cli ping`.
- `app` phụ thuộc healthcheck MySQL/Redis. Lệnh mặc định chạy PHP-FPM; cài dependency và migrate là các lệnh setup tách biệt để không tự sửa database mỗi lần container khởi động.

Biến môi trường bắt buộc: `APP_PORT`, `APP_URL`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`; nội bộ dùng `DB_HOST=mysql`, `DB_PORT=3306`, `REDIS_HOST=redis`, `REDIS_PORT=6379`.

## Xử lý lỗi và cấu hình

- `.env.example` chứa biến cấu hình Docker nhưng không chứa bí mật thật.
- Container chờ MySQL và Redis bằng healthcheck của Compose.
- Laravel giữ cơ chế exception/logging mặc định; không tạo custom exception khi chưa có nghiệp vụ.
- README nêu rõ lỗi thường gặp về port, quyền ghi `storage`, key ứng dụng và kết nối database.

## Kiểm thử và tiêu chí hoàn thành

Một smoke test xác nhận trang chính hoặc health endpoint trả phản hồi thành công. Các kiểm tra hoàn thành:

1. `docker compose config` hợp lệ; `docker compose up -d --build` đưa bốn service về trạng thái running/healthy.
2. Sau setup, `curl http://localhost:8080/health` nhận HTTP 200 và JSON `status=ok`; `/`, `/admin`, `/api/status` trả đúng response nêu trên.
3. `docker compose exec app php artisan migrate:fresh --seed` thành công; `migrate:rollback` rồi `migrate` thành công; kiểm tra schema có đủ 14 bảng miền và pivot `course_topic`.
4. Seeder tạo đúng role `admin`, `learner`, ít nhất ba level và một topic mẫu; không tạo user.
5. `docker compose exec app php artisan test` chạy smoke test cho bốn endpoint và thành công.
6. Redis được xác minh bằng `docker compose exec redis redis-cli ping` trả `PONG`; Laravel có `CACHE_STORE=redis` và `QUEUE_CONNECTION=redis` trong `.env.example`.
7. README chứa đúng chuỗi lệnh: copy env, build/up, Composer install, app key, migrate/seed, test và URL mong đợi; có mục xử lý port, `storage`, app key và DB.
8. `docs/PROJECT_PLAN.md` ánh xạ từng dòng nhiệm vụ Google Sheet sang module, người phụ trách đã ghi, phụ thuộc và sản phẩm bàn giao; ô thành viên trống được đánh dấu “Chưa phân công”.

Nếu máy thực thi thiếu PHP/Composer host nhưng có Docker, mọi kiểm tra chạy trong container. Nếu Docker không chạy hoặc không thể tải image/package, kiểm tra tĩnh tối thiểu gồm `docker compose config`, rà soát route/schema bằng file và ghi rõ kiểm tra runtime chưa thực hiện; README vẫn cung cấp lệnh xác minh chuẩn cho nhóm.

## Nguyên tắc triển khai

- Ưu tiên generator và cấu trúc mặc định của Laravel.
- Không thêm package ngoài nếu Laravel, PHP hoặc Bootstrap CDN đã đáp ứng.
- Không tạo class rỗng chỉ để “dùng sau”; mỗi file khung phải có vai trò hiện tại trong schema, route, render hoặc kiểm thử.
- Giữ diff nhỏ, dễ chia commit và dễ giao lại cho từng thành viên.
