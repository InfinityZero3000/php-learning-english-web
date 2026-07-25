# English Learning Web Application

A comprehensive English learning platform built with Laravel, featuring vocabulary management, quizzes, flashcard system, FSRS-based spaced repetition, and both web and API interfaces.

## System Requirements

- **Docker** 24+ & Docker Compose v2+
- **PHP** 8.3+ (if running without Docker)
- **MySQL** 8.4+
- **Redis** 8+
- **Composer** 2+
- **Node.js** 20+ (for admin frontend)

## Installation

### Quick Start with Docker

```bash
# Clone the repository
git clone https://github.com/InfinityZero3000/php-learning-english-web.git
cd php-learning-english-web

# Copy environment file
cp .env.example .env

# Configure .env file with your settings (API keys, mail, DB credentials)

# Start Docker containers
docker compose up -d

# Install dependencies
docker exec php-learning-english-web-app-1 composer install

# Generate application key
docker exec php-learning-english-web-app-1 php artisan key:generate

# Run migrations and seeders
docker exec php-learning-english-web-app-1 php artisan migrate --seed

# The app is now running at http://localhost:8080
```

### Manual Installation (Without Docker)

```bash
# Clone repository
git clone https://github.com/InfinityZero3000/php-learning-english-web.git
cd php-learning-english-web

# Install dependencies
composer install

# Configure environment
cp .env.example .env
# Edit .env with your DB credentials

# Generate key
php artisan key:generate

# Run migrations
php artisan migrate --seed

# Start development server
php artisan serve --port=8080
```

### Admin Frontend Setup

```bash
cd admin-frontend
npm install
npm run dev
```

## Architecture

### Technology Stack

| Layer       | Technology                                         |
| ----------- | -------------------------------------------------- |
| Backend     | Laravel 11                                         |
| Database    | MySQL 8.4                                          |
| Cache/Queue | Redis                                              |
| Web Server  | Nginx 1.28 (Docker)                                |
| Frontend    | Blade templates + Next.js 15 (Admin)               |
| API         | REST JSON with Sanctum authentication              |
| Testing     | PHPUnit                                           |
| Container   | Docker & Docker Compose                            |

### Directory Structure

```
├── app/
│   ├── Http/
│   │   ├── Controllers/      # Web & API controllers
│   │   │   ├── Api/           # REST API controllers
│   │   │   └── ...            # Web controllers
│   │   ├── Middleware/        # Auth, Admin middleware
│   │   ├── Requests/          # Form request validation
│   │   └── Resources/         # API resources
│   ├── Models/               # Eloquent models
│   ├── Policies/             # Authorization policies
│   ├── Providers/            # Service providers
│   └── Services/             # Business logic services
├── admin-frontend/           # Next.js admin panel
├── config/                   # Application config
├── database/
│   ├── factories/            # Model factories
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── docker/                   # Docker configuration files
├── resources/
│   └── views/                # Blade templates
├── routes/
│   ├── api.php               # API routes
│   └── web.php               # Web routes
└── tests/
    ├── Feature/              # Feature tests
    └── Unit/                 # Unit tests
```

## Entity Relationship Diagram (ERD)

### Core Tables

- **users** - User accounts with roles
- **roles** - User roles (admin, learner)
- **levels** - Difficulty levels (Beginner, Intermediate, Advanced)
- **courses** - Learning courses grouped by level
- **course_topic** - Course-Topic pivot table
- **topics** - Subject topics
- **lessons** - Individual lessons within courses
- **vocabularies** - Vocabulary words with translations, pronunciation, etc.
- **quizzes** - Quizzes associated with lessons
- **questions** - Questions within quizzes
- **answers** - Multiple choice answers for questions
- **attempts** - User quiz attempts and scores
- **progress** - Lesson completion tracking
- **bookmarks** - Vocabulary bookmarks

### Extended Tables (FSRS & Learning Features)

- **vocabulary_sets** - User-created vocabulary collections
- **vocabulary_set_word** - Pivot: sets ↔ words
- **user_word_progress** - FSRS-based spaced repetition tracking
- **quiz_sessions** - Detailed quiz session tracking
- **quiz_session_answers** - Individual answers in sessions
- **user_streaks** - Daily learning streak tracking
- **notifications** - User notifications
- **notification_settings** - Notification preferences
- **flashcards** - Flashcard data for review
- **flashcard_sources** - Sources for flashcard batches
- **import_jobs** - Vocabulary import job tracking
- **word_of_the_day** - Daily featured word

### Key Relationships

```
Course ──belongsTo──> Level
Course ──belongsToMany──> Topic
Course ──hasMany──> Lesson
Lesson ──hasMany──> Vocabulary
Lesson ──hasMany──> Quiz
Quiz ──hasMany──> Question
Question ──hasMany──> Answer
User ──hasMany──> Attempt
User ──hasMany──> Progress
User ──hasMany──> Bookmark
User ──belongsTo──> Role
```

## API Documentation

### Base URL
`http://localhost:8080/api/v1`

### Authentication

All API endpoints (except login/register) require Bearer token authentication via Laravel Sanctum.

### Available Endpoints

#### Authentication
| Method | Endpoint                | Description           |
| ------ | ----------------------- | --------------------- |
| POST   | /api/v1/auth/register   | Register new account  |
| POST   | /api/v1/auth/login      | Login, receive token  |
| POST   | /api/v1/auth/logout     | Logout, revoke token  |
| GET    | /api/v1/profile         | Get current user profile |

#### Courses
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| GET    | /api/v1/courses               | List courses (paginated) |
| GET    | /api/v1/courses/{id}           | Get course details       |
| POST   | /api/v1/courses               | Create course (admin)    |
| PUT    | /api/v1/courses/{id}           | Update course (admin)    |
| DELETE | /api/v1/courses/{id}           | Delete course (admin)    |

#### Lessons
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| GET    | /api/v1/courses/{course}/lessons | List lessons          |
| GET    | /api/v1/lessons/{id}           | Get lesson details       |

#### Vocabulary
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| GET    | /api/v1/vocabulary            | List vocabulary (paginated, searchable, filterable) |
| GET    | /api/v1/vocabulary/{id}        | Get vocabulary details   |
| POST   | /api/v1/vocabulary            | Create vocabulary        |
| PUT    | /api/v1/vocabulary/{id}        | Update vocabulary        |
| DELETE | /api/v1/vocabulary/{id}        | Delete vocabulary        |

#### Vocabulary Sets
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| GET    | /api/v1/vocabulary-sets        | List user's sets         |
| POST   | /api/v1/vocabulary-sets        | Create vocabulary set    |
| GET    | /api/v1/vocabulary-sets/{id}   | Get set with words       |

#### Flashcards
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| GET    | /api/v1/flashcards/review      | Get words due for review |
| POST   | /api/v1/flashcards/review      | Submit review result     |
| GET    | /api/v1/flashcards/stats       | Get review statistics    |

#### Quiz
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| POST   | /api/v1/quiz/start             | Start a quiz session     |
| POST   | /api/v1/quiz/answer            | Submit quiz answer       |
| POST   | /api/v1/quiz/complete          | Complete quiz session    |
| GET    | /api/v1/quiz/history           | Get quiz history         |

#### Progress
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| GET    | /api/v1/progress               | Get learning progress    |
| POST   | /api/v1/progress/lesson/{id}   | Mark lesson complete     |
| GET    | /api/v1/progress/stats         | Get progress statistics  |

#### Import
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| POST   | /api/v1/import/upload          | Upload CSV/JSON vocabulary |
| GET    | /api/v1/import/jobs            | List import jobs         |
| GET    | /api/v1/import/jobs/{id}       | Get import job status    |

#### Social Auth
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| POST   | /api/v1/auth/social            | Login via Google/Facebook |

#### Notifications
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| GET    | /api/v1/notifications          | Get user notifications   |
| PUT    | /api/v1/notifications/{id}/read | Mark notification read  |

#### Admin
| Method | Endpoint                      | Description              |
| ------ | ----------------------------- | ------------------------ |
| GET    | /api/v1/admin/dashboard        | Admin dashboard stats    |
| GET    | /api/v1/admin/users            | List users               |
| PUT    | /api/v1/admin/users/{id}/role  | Update user role         |

### Response Format

All API responses follow a consistent JSON structure:

```json
{
  "data": {},
  "message": "Success",
  "errors": null
}
```

Paginated responses include:
```json
{
  "data": [],
  "links": {},
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

HTTP Status Codes used: 200, 201, 204, 400, 401, 403, 404, 422, 429, 500

## Demo Accounts

| Role    | Email             | Password |
| ------- | ----------------- | -------- |
| Admin   | admin@example.com | password |
| Learner | user@example.com  | password |

## User Guide

### Web Interface

1. **Register/Login**: Create account or login with email/password or Google/Facebook
2. **Dashboard**: View learning progress, quiz scores, bookmarks
3. **Courses**: Browse courses, view lessons, study vocabulary
4. **Quiz**: Take quizzes to test vocabulary knowledge
5. **Vocabulary**: Search, filter, bookmark, and review vocabulary
6. **Flashcards**: Use FSRS-based spaced repetition for effective memorization
7. **Profile**: Update profile, change password, manage account

### Admin Panel

1. Access `/admin` after logging in as admin
2. Manage courses, levels, topics
3. CRUD operations for all learning content
4. Monitor user progress and statistics

### Next.js Admin Frontend

```bash
cd admin-frontend
npm run dev
# Access at http://localhost:3000
```

## Troubleshooting

### Docker Issues

```bash
# Check container status
docker compose ps

# View logs
docker compose logs app
docker compose logs mysql
docker compose logs nginx

# Rebuild containers
docker compose down
docker compose build --no-cache
docker compose up -d

# Reset database
docker exec php-learning-english-web-app-1 php artisan migrate:fresh --seed
```

### Common Issues

1. **Port conflict**: Change `APP_PORT` and `FORWARD_DB_PORT` in `.env`
2. **Permission denied**: Adjust storage permissions:
   ```bash
   docker exec php-learning-english-web-app-1 chmod -R 775 storage bootstrap/cache
   ```
3. **Database connection failed**: Ensure MySQL container is healthy:
   ```bash
   docker compose ps mysql
   ```
4. **Email not sending**: Verify SMTP settings in `.env`

### Running Tests

```bash
# Run all tests
docker exec php-learning-english-web-app-1 php artisan test

# Run specific test suite
docker exec php-learning-english-web-app-1 php artisan test --testsuite=Feature
docker exec php-learning-english-web-app-1 php artisan test --testsuite=Unit

# Run specific test file
docker exec php-learning-english-web-app-1 php artisan test --filter=AuthorizationTest
```

## Production Deployment

### Environment Configuration

For production, update `.env`:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_PASSWORD=<strong-password>
REDIS_PASSWORD=<strong-password>

SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=your-domain.com

QUEUE_CONNECTION=redis
CACHE_STORE=redis
```

### Docker Production

```bash
# Build for production
docker compose -f compose.yaml build

# Run with production overrides
docker compose up -d
```

### Security Checklist

- [x] CSRF Protection enabled
- [x] SQL Injection prevention via Eloquent ORM
- [x] XSS protection via Blade auto-escaping
- [x] Input validation with Form Requests
- [x] MIME type and size validation for uploads
- [x] Rate limiting on API routes
- [x] Authorization via Policies and Gates
- [x] No sensitive information in logs
- [x] HTTPS enforced in production
- [x] API authentication via Sanctum tokens

## Code Standards

This project follows:
- **PSR-12** coding standards
- **Laravel Pint** for automatic code formatting
  ```bash
  ./vendor/bin/pint
  ```
- Service Layer pattern for business logic
- Repository pattern where applicable
- Custom exceptions for error handling
- Structured logging with Laravel's logging system

## License

MIT License