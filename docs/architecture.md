# Kiến trúc hệ thống & lược đồ dữ liệu

Date: 2026-07-29

Tài liệu này chỉ minh họa bằng sơ đồ những gì đã được chốt bằng chữ tại
[`docs/PROJECT_PLAN.md`](PROJECT_PLAN.md) (mục "Kiến trúc tích hợp đã chốt")
— không lặp lại hay mở rộng nội dung đó, chỉ trực quan hóa.

## Sơ đồ kiến trúc hệ thống

```mermaid
graph LR
    subgraph Frontend["Frontend (Vercel)"]
        FE["frontend/ (Next.js, learner)"]
        AFE["admin-frontend/ (Next.js, admin)"]
    end

    subgraph API["Laravel API (Fly.io)"]
        L["Laravel 13\nsession + CSRF cookie auth\nnguồn dữ liệu nghiệp vụ duy nhất"]
    end

    subgraph Data["Datastores"]
        MySQL[("MySQL\nauth, courses, quiz, progress, session")]
        Redis[("Redis\ncache + queue\n(không dùng cho session)")]
    end

    subgraph LexiLingo["LexiLingo (external)"]
        LLBackend["backend-service\ncourse/unit/lesson/vocabulary"]
        LLAI["ai-service\ntranslate, pronunciation, STT, TTS"]
    end

    FE -- "REST, session+CSRF cookie" --> L
    AFE -- "REST, session+CSRF cookie" --> L
    L --> MySQL
    L --> Redis
    L -- "lexilingo:import,\nlexilingo:sync-vocabulary\n(upsert theo external_id)" --> LLBackend
    L -- "/api/v1/ai/*\nserver-to-server proxy only" --> LLAI
```

Điểm quan trọng minh họa ở đây (đã ghi trong `PROJECT_PLAN.md`, không phải
thông tin mới):

- Laravel là nguồn dữ liệu nghiệp vụ duy nhất; hai frontend chỉ gọi Laravel
  API, không giữ token hay secret của LexiLingo.
- Kết nối tới LexiLingo backend-service là **một chiều, theo lệnh artisan**
  (`lexilingo:import`, `lexilingo:sync-vocabulary`), không phải gọi trực
  tiếp mỗi request — dữ liệu được đồng bộ (upsert theo `external_id`) về
  MySQL rồi phục vụ từ local.
- Kết nối tới LexiLingo ai-service chỉ là proxy server-to-server qua
  `/api/v1/ai/*`; frontend không bao giờ giữ `LEXILINGO_AI_SERVICE_SECRET`.
- Session lưu ở MySQL (`SESSION_DRIVER=database`), **không** ở Redis; Redis
  chỉ phục vụ cache và queue (`CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`
  — xác nhận từ `.env.example`).

## Sơ đồ ERD (lược đồ dữ liệu)

Phạm vi: 16 model thuộc miền học tập cốt lõi — đủ để đọc hiểu learner flow
và admin flow trong [`docs/USER_GUIDE.md`](USER_GUIDE.md). **Không vẽ**
`AuditLog` và `LexiLingoImportCheckpoint`/`LexiLingoImportFailure` — đây là
các bảng vận hành/nội bộ (ghi log hành động admin; bookkeeping cho lệnh
import), không nằm trong luồng nghiệp vụ học tập và không được bất kỳ route
API nào truy vấn trực tiếp.

```mermaid
erDiagram
    ROLE ||--o{ USER : "role_id"
    COURSE_CATEGORY ||--o{ COURSE : "category_id"
    LEVEL ||--o{ COURSE : "level_id"
    COURSE ||--o{ UNIT : "course_id"
    COURSE ||--o{ LESSON : "course_id"
    UNIT ||--o{ LESSON : "unit_id"
    TOPIC ||--o{ VOCABULARY : "topic_id"
    LESSON ||--o{ VOCABULARY : "lesson_id"
    LESSON ||--o{ QUIZ : "lesson_id"
    QUIZ ||--o{ QUESTION : "quiz_id"
    QUESTION ||--o{ ANSWER : "question_id"
    USER ||--o{ ATTEMPT : "user_id"
    QUIZ ||--o{ ATTEMPT : "quiz_id"
    USER ||--o{ PROGRESS : "user_id"
    LESSON ||--o{ PROGRESS : "lesson_id"
    USER ||--o{ BOOKMARK : "user_id"
    VOCABULARY ||--o{ BOOKMARK : "vocabulary_id (nullable)"
    LESSON ||--o{ BOOKMARK : "lesson_id (nullable)"
    USER ||--o{ USER_VOCABULARY : "user_id"
    VOCABULARY ||--o{ USER_VOCABULARY : "vocabulary_id"
    USER_VOCABULARY ||--o{ VOCABULARY_REVIEW : "user_vocabulary_id"

    ROLE {
        bigint id PK
        string name
        string slug
    }
    USER {
        bigint id PK
        bigint role_id FK
        string name
        string email
        timestamp locked_at
        timestamp last_login_at
    }
    LEVEL {
        bigint id PK
        string name
        string slug
        int sort_order
    }
    TOPIC {
        bigint id PK
        string name
        string slug
    }
    COURSE_CATEGORY {
        bigint id PK
        string name
        string slug
        boolean is_active
    }
    COURSE {
        bigint id PK
        bigint level_id FK
        bigint category_id FK
        string title
        string status
    }
    UNIT {
        bigint id PK
        bigint course_id FK
        string title
        int sort_order
    }
    LESSON {
        bigint id PK
        bigint course_id FK
        bigint unit_id FK
        string title
        string status
    }
    VOCABULARY {
        bigint id PK
        bigint lesson_id FK
        bigint topic_id FK
        string word
        text meaning
    }
    QUIZ {
        bigint id PK
        bigint lesson_id FK
        string title
        int passing_score
        string status
    }
    QUESTION {
        bigint id PK
        bigint quiz_id FK
        text content
    }
    ANSWER {
        bigint id PK
        bigint question_id FK
        text content
        boolean is_correct
    }
    ATTEMPT {
        bigint id PK
        bigint user_id FK
        bigint quiz_id FK
        decimal score
        timestamp completed_at
    }
    PROGRESS {
        bigint id PK
        bigint user_id FK
        bigint lesson_id FK
        timestamp completed_at
    }
    BOOKMARK {
        bigint id PK
        bigint user_id FK
        bigint vocabulary_id FK
        bigint lesson_id FK
        string bookmark_type
    }
    USER_VOCABULARY {
        bigint id PK
        bigint user_id FK
        bigint vocabulary_id FK
        string state
        timestamp due_at
    }
    VOCABULARY_REVIEW {
        bigint id PK
        bigint user_vocabulary_id FK
        tinyint rating
        timestamp reviewed_at
    }
```
