# Progress API

All endpoints require authentication (currently session `auth`; switch to `auth:sanctum` after Sanctum is installed).

| Method | Endpoint | Parameters |
|---|---|---|
| PATCH | `/api/lessons/{lesson}/progress` | `status`, optional `duration_seconds` |
| POST | `/api/vocabulary/{vocabulary}/bookmark` | optional `note`; posts again to toggle off |
| DELETE | `/api/vocabulary/{vocabulary}/bookmark` | — |
| GET | `/api/bookmarks` | `page`, `topic_id` |
| GET | `/api/study-history` | `activity_type`, `from`, `to`, `page` |
| GET | `/api/dashboard` | — |

Collections use Laravel Resource pagination (`data`, `links`, `meta`). Progress response is `{"data":{"lesson_id":1,"status":"completed","progress_percent":100}}`.
