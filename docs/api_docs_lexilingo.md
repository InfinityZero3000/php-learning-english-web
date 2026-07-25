# Tài liệu API LexiLingo

> Cập nhật theo các router đang được đăng ký trong
> `backend-service/app/main.py` và `ai-service/api/main.py`.

LexiLingo gồm hai FastAPI service:

- **Backend Service**: tài khoản, nội dung học, tiến độ, từ vựng, game và quản trị.
- **AI Service**: chat, TraceCAG, dịch, STT, TTS và hội thoại theo chủ đề.

Các URL bên dưới là path của service tương ứng. Host và port phụ thuộc môi
trường triển khai.

## Xác thực

- API người dùng dùng `Authorization: Bearer <access_token>`.
- Access token mặc định hết hạn sau 30 phút; refresh token sau 7 ngày.
- API quản trị AI dùng `X-Admin-Key`.
- API gọi nội bộ dùng secret riêng, ví dụ `X-AI-Service-Secret`.
- Yêu cầu xác thực cụ thể của từng route được thể hiện đầy đủ tại `/docs`.

## Backend Service

Phần lớn endpoint nghiệp vụ có tiền tố `/api/v1`. Các endpoint health và
well-known không có tiền tố này.

### Health và App Links

| Method | Endpoint |
| --- | --- |
| `GET` | `/health` |
| `GET` | `/health/ready` |
| `GET` | `/ping` |
| `GET` | `/.well-known/assetlinks.json` |
| `GET` | `/.well-known/apple-app-site-association` |

### Authentication

| Method | Endpoint |
| --- | --- |
| `POST` | `/api/v1/auth/register` |
| `POST` | `/api/v1/auth/resend-verification` |
| `POST` | `/api/v1/auth/login` |
| `POST` | `/api/v1/auth/refresh` |
| `GET` | `/api/v1/auth/me` |
| `POST` | `/api/v1/auth/logout` |
| `POST` | `/api/v1/auth/google` |
| `POST` | `/api/v1/auth/facebook` |
| `POST` | `/api/v1/auth/change-password` |
| `POST` | `/api/v1/auth/forgot-password` |
| `POST` | `/api/v1/auth/reset-password` |
| `POST` | `/api/v1/auth/verify-email` |
| `POST` | `/api/v1/auth/admin/login` |
| `POST` | `/api/v1/auth/admin/request-otp` |
| `POST` | `/api/v1/auth/admin/verify-otp` |

### Users, Devices, Reminders và Referral

| Method | Endpoint |
| --- | --- |
| `GET` | `/api/v1/users/me` |
| `PUT` | `/api/v1/users/me` |
| `DELETE` | `/api/v1/users/me` |
| `DELETE` | `/api/v1/users/me/permanent` |
| `GET` | `/api/v1/users/search` |
| `GET` | `/api/v1/users/{user_id}` |
| `GET` | `/api/v1/users/me/level` |
| `GET` | `/api/v1/users/me/level-full` |
| `GET` | `/api/v1/users/me/stats` |
| `GET` | `/api/v1/users/me/weekly-activity` |
| `POST` | `/api/v1/users/me/xp` |
| `POST` | `/api/v1/devices` |
| `GET` | `/api/v1/devices` |
| `PUT` | `/api/v1/devices/{device_id}` |
| `DELETE` | `/api/v1/devices/{device_id}` |
| `GET` | `/api/v1/users/me/reminder-preferences` |
| `PUT` | `/api/v1/users/me/reminder-preferences` |
| `GET` | `/api/v1/referral/my-code` |
| `POST` | `/api/v1/referral/claim/{code}` |

### Courses, Categories và Learning

| Method | Endpoint |
| --- | --- |
| `GET` | `/api/v1/courses` |
| `GET` | `/api/v1/courses/enrolled` |
| `GET` | `/api/v1/courses/{course_id}` |
| `POST` | `/api/v1/courses/{course_id}/enroll` |
| `GET` | `/api/v1/categories` |
| `GET` | `/api/v1/categories/{category_id}` |
| `GET` | `/api/v1/categories/slug/{slug}` |
| `GET` | `/api/v1/categories/{category_id}/courses` |
| `POST` | `/api/v1/categories` |
| `PUT` | `/api/v1/categories/{category_id}` |
| `DELETE` | `/api/v1/categories/{category_id}` |
| `POST` | `/api/v1/categories/update-counts` |
| `POST` | `/api/v1/learning/lessons/{lesson_id}/start` |
| `GET` | `/api/v1/learning/lessons/{lesson_id}/content` |
| `GET` | `/api/v1/learning/lessons/{lesson_id}/context` |
| `POST` | `/api/v1/learning/attempts/{attempt_id}/answer` |
| `POST` | `/api/v1/learning/attempts/{attempt_id}/complete` |
| `GET` | `/api/v1/learning/courses/{course_id}/roadmap` |

### Progress và Proficiency

| Method | Endpoint |
| --- | --- |
| `GET` | `/api/v1/progress/me` |
| `GET` | `/api/v1/progress/courses/{course_id}` |
| `POST` | `/api/v1/progress/lessons/{lesson_id}/complete` |
| `GET` | `/api/v1/progress/xp` |
| `GET` | `/api/v1/progress/weekly` |
| `GET` | `/api/v1/progress/streak` |
| `POST` | `/api/v1/progress/streak/update` |
| `POST` | `/api/v1/progress/streak/freeze` |
| `POST` | `/api/v1/progress/streak/restore` |
| `POST` | `/api/v1/progress/streak/claim-daily-reward` |
| `GET` | `/api/v1/proficiency/profile` |
| `POST` | `/api/v1/proficiency/record-exercises` |
| `GET` | `/api/v1/proficiency/level-check` |
| `GET` | `/api/v1/proficiency/level-thresholds` |
| `GET` | `/api/v1/proficiency/history` |
| `GET` | `/api/v1/proficiency/placement-test` |
| `POST` | `/api/v1/proficiency/placement-test/submit` |
| `POST` | `/api/v1/proficiency/exam-gated/submit` |

### Vocabulary và Mistake Notebook

| Method | Endpoint |
| --- | --- |
| `GET` | `/api/v1/vocabulary/word-of-day` |
| `GET` | `/api/v1/vocabulary/items` |
| `GET` | `/api/v1/vocabulary/items/{vocabulary_id}` |
| `GET` | `/api/v1/vocabulary/collection` |
| `POST` | `/api/v1/vocabulary/collection` |
| `POST` | `/api/v1/vocabulary/collection/quick-save` |
| `POST` | `/api/v1/vocabulary/collection/bulk` |
| `POST` | `/api/v1/vocabulary/pronunciation/evaluate` |
| `GET` | `/api/v1/vocabulary/due` |
| `POST` | `/api/v1/vocabulary/review/{user_vocabulary_id}` |
| `GET` | `/api/v1/vocabulary/stats` |
| `POST` | `/api/v1/vocabulary/decks` |
| `GET` | `/api/v1/vocabulary/decks` |
| `GET` | `/api/v1/vocabulary/decks/{deck_id}/items` |
| `POST` | `/api/v1/vocabulary/decks/{deck_id}/items` |
| `DELETE` | `/api/v1/vocabulary/decks/{deck_id}` |
| `DELETE` | `/api/v1/vocabulary/decks/{deck_id}/items/{user_vocabulary_id}` |
| `GET` | `/api/v1/mistakes` |
| `POST` | `/api/v1/mistakes` |
| `PATCH` | `/api/v1/mistakes/{mistake_id}/review` |
| `PATCH` | `/api/v1/mistakes/{mistake_id}/reopen` |
| `DELETE` | `/api/v1/mistakes/{mistake_id}` |

### Gamification, XP và Challenges

| Method | Endpoint |
| --- | --- |
| `GET` | `/api/v1/gamification/achievements` |
| `GET` | `/api/v1/gamification/achievements/me` |
| `GET` | `/api/v1/gamification/achievements/recent` |
| `POST` | `/api/v1/gamification/achievements/check` |
| `GET` | `/api/v1/gamification/rewards/starter/pending` |
| `POST` | `/api/v1/gamification/rewards/starter/seen` |
| `GET` | `/api/v1/gamification/wallet` |
| `GET` | `/api/v1/gamification/wallet/history` |
| `GET` | `/api/v1/gamification/leaderboard` |
| `GET` | `/api/v1/gamification/leaderboard/me` |
| `GET` | `/api/v1/gamification/shop` |
| `POST` | `/api/v1/gamification/shop/purchase` |
| `GET` | `/api/v1/gamification/inventory` |
| `POST` | `/api/v1/gamification/inventory/avatar/equip` |
| `POST` | `/api/v1/gamification/inventory/use` |
| `GET` | `/api/v1/gamification/boosts/active` |
| `GET` | `/api/v1/gamification/boosts/xp-multiplier` |
| `POST` | `/api/v1/gamification/users/{user_id}/follow` |
| `DELETE` | `/api/v1/gamification/users/{user_id}/follow` |
| `POST` | `/api/v1/gamification/users/{user_id}/unfollow` |
| `GET` | `/api/v1/gamification/users/{user_id}/followers` |
| `GET` | `/api/v1/gamification/users/{user_id}/following` |
| `GET` | `/api/v1/gamification/feed` |
| `GET` | `/api/v1/gamification/users/suggestions` |
| `POST` | `/api/v1/gamification/users/location` |
| `GET` | `/api/v1/gamification/users/nearby` |
| `POST` | `/api/v1/xp/award` |
| `GET` | `/api/v1/xp/profile` |
| `GET` | `/api/v1/xp/leaderboard` |
| `GET` | `/api/v1/challenges/daily` |
| `POST` | `/api/v1/challenges/daily/bonus/claim` |
| `POST` | `/api/v1/challenges/daily/{challenge_id}/claim` |

### Games

| Method | Endpoint |
| --- | --- |
| `GET` | `/api/v1/games/word-scramble` |
| `GET` | `/api/v1/games/matching` |
| `GET` | `/api/v1/games/spelling-bee` |
| `GET` | `/api/v1/games/hangman` |
| `GET` | `/api/v1/games/fill-blank` |
| `GET` | `/api/v1/games/grammar-quiz` |
| `POST` | `/api/v1/games/sessions/{session_id}/complete` |
| `GET` | `/api/v1/games/categories` |

### Content: YouTube, News, Podcasts và Books

| Method | Endpoint |
| --- | --- |
| `GET` | `/api/v1/youtube/channels` |
| `GET` | `/api/v1/youtube/search` |
| `GET` | `/api/v1/youtube/captions/{video_id}` |
| `GET` | `/api/v1/youtube/channels/{channel_id}/videos` |
| `GET` | `/api/v1/youtube/translate` |
| `GET` | `/api/v1/news` |
| `GET` | `/api/v1/news/proxy/image` |
| `GET` | `/api/v1/news/categories` |
| `POST` | `/api/v1/news/full-content` |
| `GET` | `/api/v1/news/{article_id}/quiz` |
| `GET` | `/api/v1/podcasts/proxy/image` |
| `GET` | `/api/v1/podcasts/search` |
| `GET` | `/api/v1/podcasts/curated` |
| `GET` | `/api/v1/podcasts/episodes` |
| `POST` | `/api/v1/podcasts/transcript` |
| `GET` | `/api/v1/books/proxy/image` |
| `GET` | `/api/v1/books/proxy/text` |
| `GET` | `/api/v1/books/recommended` |
| `GET` | `/api/v1/books/search` |
| `GET` | `/api/v1/books/browse` |
| `GET` | `/api/v1/books/{book_id}/quiz` |

### Notifications

| Method | Endpoint |
| --- | --- |
| `GET` | `/api/v1/notifications` |
| `PATCH` | `/api/v1/notifications/{notification_id}/read` |
| `PATCH` | `/api/v1/notifications/read-all` |
| `DELETE` | `/api/v1/notifications` |
| `DELETE` | `/api/v1/notifications/{notification_id}` |

### Admin: Content

| Method | Endpoint |
| --- | --- |
| `GET`, `POST` | `/api/v1/admin/courses` |
| `PUT`, `DELETE` | `/api/v1/admin/courses/{course_id}` |
| `GET`, `POST` | `/api/v1/admin/units` |
| `PUT`, `DELETE` | `/api/v1/admin/units/{unit_id}` |
| `GET`, `PUT`, `DELETE` | `/api/v1/admin/lessons/{lesson_id}` |
| `GET`, `POST` | `/api/v1/admin/lessons` |
| `PUT` | `/api/v1/admin/lessons/{lesson_id}/content` |
| `GET`, `POST` | `/api/v1/admin/vocabulary` |
| `PUT`, `DELETE` | `/api/v1/admin/vocabulary/{vocab_id}` |
| `POST` | `/api/v1/admin/vocabulary/bulk-import` |
| `POST` | `/api/v1/admin/upload/badge` |
| `GET`, `POST` | `/api/v1/admin/grammar` |
| `PUT`, `DELETE` | `/api/v1/admin/grammar/{grammar_id}` |
| `GET`, `POST` | `/api/v1/admin/questions` |
| `PUT`, `DELETE` | `/api/v1/admin/questions/{question_id}` |
| `GET`, `POST` | `/api/v1/admin/test-exams` |
| `PUT`, `DELETE` | `/api/v1/admin/test-exams/{test_exam_id}` |

### Admin: Gamification, Users, RBAC và System

| Method | Endpoint |
| --- | --- |
| `GET`, `POST` | `/api/v1/admin/achievements` |
| `PUT`, `DELETE` | `/api/v1/admin/achievements/{achievement_id}` |
| `GET`, `POST` | `/api/v1/admin/shop` |
| `PUT`, `DELETE` | `/api/v1/admin/shop/{item_id}` |
| `GET` | `/api/v1/admin/users` |
| `GET`, `PUT` | `/api/v1/admin/users/{user_id}` |
| `PUT` | `/api/v1/admin/users/{user_id}/role` |
| `PUT` | `/api/v1/admin/users/{user_id}/status` |
| `DELETE` | `/api/v1/admin/users/{user_id}/permanent` |
| `GET` | `/api/v1/admin/users/{user_id}/activity` |
| `POST` | `/api/v1/admin/users/bulk-action` |
| `POST` | `/api/v1/admin/users/{user_id}/gift` |
| `GET` | `/api/v1/admin/rbac/roles` |
| `GET` | `/api/v1/admin/rbac/roles/{role_slug}` |
| `GET` | `/api/v1/admin/rbac/permissions` |
| `GET` | `/api/v1/admin/rbac/users` |
| `POST` | `/api/v1/admin/rbac/users/assign-role` |
| `POST` | `/api/v1/admin/rbac/users/{user_id}/deactivate` |
| `POST` | `/api/v1/admin/rbac/users/{user_id}/activate` |
| `GET` | `/api/v1/admin/rbac/audit-logs` |
| `GET` | `/api/v1/admin/rbac/dashboard` |
| `POST` | `/api/v1/admin/seed` |
| `GET`, `PUT` | `/api/v1/admin/system-info` |
| `GET` | `/api/v1/admin/quota-usage` |
| `POST` | `/api/v1/admin/quota-reset/{api_name}` |
| `GET` | `/api/v1/admin/monitoring/system` |
| `GET` | `/api/v1/admin/monitoring/services` |
| `GET` | `/api/v1/admin/monitoring/db-stats` |
| `GET` | `/api/v1/admin/monitoring/request-stats` |

### Admin: Analytics và Agents

| Method | Endpoint |
| --- | --- |
| `GET` | `/api/v1/admin/analytics/dashboard/kpis` |
| `GET` | `/api/v1/admin/analytics/dashboard/user-growth` |
| `GET` | `/api/v1/admin/analytics/dashboard/engagement` |
| `GET` | `/api/v1/admin/analytics/dashboard/course-popularity` |
| `GET` | `/api/v1/admin/analytics/dashboard/completion-funnel` |
| `GET` | `/api/v1/admin/analytics/user-metrics` |
| `GET` | `/api/v1/admin/analytics/retention-cohorts` |
| `GET` | `/api/v1/admin/analytics/content-performance` |
| `GET` | `/api/v1/admin/analytics/vocabulary-effectiveness` |
| `POST` | `/api/v1/admin/content-agent/uploads` |
| `GET` | `/api/v1/admin/content-agent/sources` |
| `POST`, `GET` | `/api/v1/admin/content-agent/jobs` |
| `GET` | `/api/v1/admin/content-agent/jobs/{job_id}` |
| `GET` | `/api/v1/admin/content-agent/jobs/{job_id}/preview` |
| `POST` | `/api/v1/admin/content-agent/jobs/{job_id}/apply` |
| `POST` | `/api/v1/admin/content-agent/jobs/{job_id}/retry` |
| `POST` | `/api/v1/admin/content-agent/jobs/{job_id}/cancel` |
| `POST`, `GET` | `/api/v1/admin/notification-campaign/jobs` |
| `GET` | `/api/v1/admin/notification-campaign/jobs/{job_id}` |
| `POST` | `/api/v1/admin/notification-campaign/jobs/{job_id}/apply` |
| `POST` | `/api/v1/admin/notification-campaign/jobs/{job_id}/cancel` |
| `POST` | `/api/v1/admin/notification-campaign/jobs/{job_id}/retry` |
| `POST`, `GET` | `/api/v1/admin/ranking-agent/jobs` |
| `GET` | `/api/v1/admin/ranking-agent/jobs/{job_id}` |
| `GET` | `/api/v1/admin/ranking-agent/jobs/{job_id}/preview` |
| `POST` | `/api/v1/admin/ranking-agent/jobs/{job_id}/apply` |
| `POST` | `/api/v1/admin/ranking-agent/jobs/{job_id}/cancel` |
| `POST` | `/api/v1/admin/ranking-agent/jobs/{job_id}/retry` |

### Backend Internal và AI Audit

| Method | Endpoint |
| --- | --- |
| `POST` | `/api/v1/internal/learner-state/batch-get` |
| `POST` | `/api/v1/internal/learner-state/observations:batch` |
| `POST` | `/api/v1/ai-audit/events` |
| `GET` | `/api/v1/ai-audit/events/me` |
| `GET` | `/api/v1/ai-audit/quality-summary` |

## AI Service

### Service, Chat và Lexi Chat

| Method | Endpoint |
| --- | --- |
| `GET` | `/` |
| `GET` | `/health` |
| `GET`, `POST` | `/warmup` |
| `GET`, `POST` | `/api/v1/warmup` |
| `POST` | `/api/v1/chat/sessions` |
| `POST` | `/api/v1/chat/messages` |
| `GET` | `/api/v1/chat/sessions/{session_id}/messages` |
| `GET` | `/api/v1/chat/sessions/{session_id}/messages/paged` |
| `GET` | `/api/v1/chat/sessions/{session_id}/messages/metadata` |
| `GET` | `/api/v1/chat/sessions/user/{user_id}` |
| `POST` | `/api/v1/lexi/sessions` |
| `POST` | `/api/v1/lexi/chat` |
| `POST` | `/api/v1/lexi/stream` |
| `GET` | `/api/v1/lexi/sessions/{session_id}/messages` |
| `GET` | `/api/v1/lexi/sessions/{session_id}/messages/paged` |
| `GET` | `/api/v1/lexi/sessions/{session_id}/messages/metadata` |
| `GET` | `/api/v1/lexi/sessions/user/{user_id}` |
| `POST` | `/api/v1/lexi/sessions/{session_id}/rename` |
| `POST` | `/api/v1/lexi/sessions/{session_id}/delete` |
| `GET` | `/api/v1/lexi/health` |

### Topic Chat

| Method | Endpoint |
| --- | --- |
| `GET` | `/api/v1/topics/stories` |
| `GET` | `/api/v1/topics/categories` |
| `POST` | `/api/v1/topics/stories/warm` |
| `GET` | `/api/v1/topics/stories/{story_id}` |
| `POST` | `/api/v1/topics/topic-sessions` |
| `POST` | `/api/v1/topics/topic-sessions/{session_id}/messages` |
| `GET` | `/api/v1/topics/topic-sessions/{session_id}` |
| `GET` | `/api/v1/topics/topic-sessions/{session_id}/messages` |
| `GET` | `/api/v1/topics/topic-sessions/{session_id}/messages/paged` |
| `GET` | `/api/v1/topics/topic-sessions/{session_id}/messages/metadata` |
| `GET` | `/api/v1/topics/llm/health` |

### Speech và Voice

| Method | Endpoint |
| --- | --- |
| `WEBSOCKET` | `/api/v1/stt/stream` |
| `POST` | `/api/v1/stt/transcribe` |
| `POST` | `/api/v1/stt/assess-pronunciation` |
| `POST` | `/api/v1/tts/synthesize` |
| `GET` | `/api/v1/voice/ready` |
| `POST` | `/api/v1/voice/ticket` |

### AI, TraceCAG, Analytics và Translate

| Method | Endpoint |
| --- | --- |
| `POST` | `/api/v1/ai/trace-cag/analyze` |
| `POST` | `/api/v1/ai/analyze` |
| `GET` | `/api/v1/ai/trace-cag/health` |
| `GET`, `POST` | `/api/v1/ai/warmup` |
| `GET` | `/api/v1/ai/graph-analytics` |
| `GET` | `/api/v1/ai/graph-analytics/communities/{community_id}` |
| `GET` | `/api/v1/ai/monitoring/dashboard` |
| `GET` | `/api/v1/ai/monitoring/metrics/{metric_name}` |
| `GET` | `/api/v1/ai/monitoring/system` |
| `GET` | `/api/v1/ai/monitoring/health` |
| `POST` | `/api/v1/ai/interactions` |
| `GET` | `/api/v1/ai/interactions/user/{user_id}` |
| `GET` | `/api/v1/ai/interactions/session/{session_id}` |
| `POST` | `/api/v1/ai/interactions/{interaction_id}/feedback` |
| `GET` | `/api/v1/ai/analytics/user/{user_id}/errors` |
| `GET` | `/api/v1/ai/translate` |

### AI Admin và Ollama

| Method | Endpoint |
| --- | --- |
| `GET`, `PUT` | `/api/v1/admin/config` |
| `GET`, `POST` | `/api/v1/admin/topics` |
| `GET` | `/api/v1/ollama/health` |
| `POST` | `/api/v1/ollama/chat` |
| `POST` | `/api/v1/ollama/analyze` |
| `GET` | `/api/v1/ollama/models` |

### AI Internal Agents

| Method | Endpoint |
| --- | --- |
| `POST` | `/api/v1/internal/content-agent/jobs/{job_id}/records` |
| `POST` | `/api/v1/internal/content-agent/jobs/{job_id}/generate` |
| `DELETE` | `/api/v1/internal/content-agent/jobs/{job_id}` |
| `GET` | `/api/v1/internal/content-agent/sources` |
| `POST` | `/api/v1/internal/content-agent/jobs/{job_id}/snapshots` |
| `POST` | `/api/v1/internal/ranking-agent/insights` |
| `POST` | `/api/notification-agent/generate-content` |

## Tài liệu tương tác

Mỗi FastAPI service tự cung cấp:

- Swagger UI: `/docs`
- ReDoc: `/redoc`
- OpenAPI schema: `/openapi.json`

Swagger/OpenAPI là nguồn chi tiết cho request body, query parameter, response
schema, mã lỗi và yêu cầu xác thực của từng endpoint.
