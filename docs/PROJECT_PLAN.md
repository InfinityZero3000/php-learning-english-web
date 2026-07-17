# Kế hoạch đồ án Website học tiếng Anh

Nguồn: [Plan-Project-PHP](https://docs.google.com/spreadsheets/d/1FEMThp6qikntxxWZBMP4xU36zlVGsNTJ_NxxyTZHl3A/edit?gid=0#gid=0). Mốc báo cáo: **31/07/2026**.

| # | Module | Nhiệm vụ | Phụ trách | Phụ thuộc | Sản phẩm bàn giao |
|---|---|---|---|---|---|
| 1 | Nền tảng | Laravel, MVC, routing, env, Docker, GitHub workflow | Thắng | Không | Repository, `compose.yaml`, README |
| 2 | Cơ sở dữ liệu | ERD, migrations, seeders, factories, indexes, Eloquent | Chưa phân công | 1 | ERD, migrations, seeders |
| 3 | Xác thực | Đăng ký/đăng nhập/đăng xuất, quên mật khẩu, profile, CSRF, validation | Chưa phân công | 1, 2 | Auth controllers, requests, views |
| 4 | Frontend | Layout, navbar, sidebar, trang chủ, khóa học, học tập, dashboard | Thắng | 1 | Blade components, assets |
| 5 | Nội dung học | CRUD Course, Level, Topic, Vocabulary, tìm kiếm/lọc/phân trang/upload | Thư | 2, 4 | Controllers, services, views |
| 6 | Nội dung học | Lesson, Quiz, Question, Answer, làm bài và chấm điểm | Thư | 2, 3, 5 | Quiz/Lesson module |
| 7 | Học tập | Tiến độ, điểm quiz, lịch sử học, bookmark, dashboard cá nhân | Thành | 2, 3, 6 | Progress module, dashboard queries |
| 8 | Quản trị | Dashboard admin, quản lý user/nội dung, role, policy/gate/middleware | Thư, Nhi | 3, 5, 6 | Admin module, policies, middleware |
| 9 | API | REST API course, lesson, vocabulary, quiz result, progress | Nhi, Thư, Thành, Danh | 3, 5, 6, 7 | `routes/api.php`, resources, Postman |
| 10 | Bảo mật | CSRF, SQLi, XSS, upload validation, rate limit, quyền tài nguyên | Chưa phân công | 3, 5, 8, 9 | Security checklist, test evidence |
| 11 | Chất lượng mã | PSR-12/Pint, exception, logging có context | Chưa phân công | Các module đang phát triển | Pint config, exception handlers, logs |
| 12 | Kiểm thử | Feature/Unit test cho Auth, CRUD, quyền, quiz và API | Chưa phân công | 3, 5, 6, 8, 9 | `tests/`, test report |
| 13 | Triển khai | Laravel, MySQL, Redis, queue/cache, HTTPS, backup/rollback | Thắng | 9, 10, 11, 12 | Production URL, deployment guide |
| 14 | Tài liệu | Cài đặt, kiến trúc/DB, tài khoản demo, API, hướng dẫn, troubleshooting | Nhi | 5–13 | README, User Guide, diagrams |
| 15 | Bảo vệ | Báo cáo, slide, demo end-to-end, phân công thuyết trình, Q&A | Chưa phân công | Tất cả | Report, slides, demo script, Q&A |

## Thứ tự thực hiện

1. Hoàn tất nền tảng và schema để các nhánh dùng chung cấu trúc ổn định.
2. Làm Auth và layout trước các module cần tài khoản/giao diện.
3. Làm Course/Vocabulary trước Lesson/Quiz, rồi mới làm Progress và Admin.
4. Chốt API sau khi model và nghiệp vụ web ổn định.
5. Bảo mật, test và chất lượng mã chạy xuyên suốt; triển khai sau khi các kiểm tra chính vượt qua.

## Quy ước bàn giao

- Mỗi nhiệm vụ có issue riêng, nhánh riêng và pull request được review.
- Migration đã merge không được sửa lịch sử; tạo migration mới khi schema thay đổi.
- Pull request phải chạy `php artisan test` và `./vendor/bin/pint --test`.
- Không commit `.env`, mật khẩu, token hoặc dữ liệu người dùng thật.
