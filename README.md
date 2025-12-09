# Документация изменений проекта CassiopeiaX

## Обзор

Проект CassiopeiaX — распределённый монолит для работы с космическими данными (ISS, NASA OSDR, JWST, Astronomy API). Документ описывает все изменения, внесённые после initial commit (315a459).

---

## 1. Инфраструктура (Docker)

### Redis
- Добавлен сервис `redis:7-alpine` с персистентностью (appendonly)
- Volume `redisdata` для хранения данных
- Healthcheck для проверки доступности

### PHP
- Установлен phpredis extension
- Добавлен zip extension для XLSX экспорта
- Переменные окружения: `CACHE_DRIVER=redis`, `REDIS_HOST`, `REDIS_PORT`

### Pascal Legacy → Python
- Сервис переписан с Pascal на Python
- Базовый образ: `debian:12-slim` → `python:3.11-slim`
- Генерация телеметрии и COPY в PostgreSQL

### Конфигурация (.env)
- Все секреты вынесены из docker-compose.yml в .env
- Интервалы обновления API настраиваются через переменные окружения
- Создан .env.example как шаблон

---

## 2. База данных

### Новые таблицы
- `cms_blocks` (id, slug, title, content, is_active) — CMS блоки

### Удалённые таблицы
- `cms_pages` — заменена на cms_blocks
- `iss_fetch_log` — удалена инициализация из Rust сервиса (но осталась в init.sql)

---

## 3. Backend (PHP/Laravel)

### Новые сервисы

**CmsBlockService** (`app/Services/CmsBlockService.php`)
- Получение контента CMS блоков по slug
- Кеширование на 1 час (Cache::remember)
- XSS-защита: удаление script, style, iframe, on* атрибутов, javascript: в href/src

**IssService** (`app/Services/IssService.php`)
- Кеширование ответов от Rust ISS сервиса
- getLast() — кеш 30 сек
- getTrend() — кеш 60 сек
- getOsdrList() — кеш 1 час

### Middleware

**RateLimitMiddleware** (`app/Http/Middleware/RateLimitMiddleware.php`)
- Ограничение запросов по IP
- Страницы: 60 req/min
- API: 120 req/min
- Заголовки: X-RateLimit-Limit, X-RateLimit-Remaining

### Валидаторы (`app/Validators/`)

| Валидатор          | Параметры                                          |
|--------------------|----------------------------------------------------|
| AstroValidator     | lat, lon, days, elevation, time                    |
| JwstFeedValidator  | source, suffix, program, instrument, page, perPage |
| OsdrValidator      | limit (1-100)                                      |
| TelemetryValidator | limit (1-1000)                                     |

### Новые контроллеры

- **AstronomyController** — страница Astronomy API
- **TelemetryController** — страница телеметрии + XLSX экспорт

### Изменённые контроллеры

- **DashboardController** — использует CmsBlockService, IssService, JwstFeedValidator
- **AstroController** — добавлены elevation, time; раздельные запросы для sun/moon
- **IssController** — использует IssService
- **OsdrController** — использует IssService, OsdrValidator
- **ProxyController** — использует IssService
- **CmsController** — исправлены slug'и блоков

---

## 4. Frontend (Blade Views)

### Новые страницы

- `/astronomy` — Astronomy API с формой параметров
- `/telemetry` — таблица телеметрии + экспорт XLSX
- `/cms` — отображение CMS блоков

### Dashboard

- Real-time отслеживание МКС (обновление каждые 15 сек)
- JWST Viewer с expand/collapse анимацией
- Графики скорости и высоты МКС (Chart.js)
- Карта с траекторией движения

### OSDR

- Поиск по названию
- Сортировка по колонкам (клик по заголовку)

### ISS

- Кнопка "Обновить" с loading state
- Асинхронное обновление данных

### Layout

- Sticky navbar с shadow
- Mobile burger menu (navbar-toggler)
- Подсветка активного пункта меню (fw-bold)
- CSS анимации: fadeInDown, pulse, dots

---

## 5. Backend (Rust)

### Рефакторинг структуры

Монолитный `main.rs` разбит на модули:

```
src/
├── lib.rs           # экспорт модулей
├── config.rs        # конфигурация, AppState
├── database.rs      # инициализация БД
├── error.rs         # кастомный ApiError
├── lock.rs          # pg advisory locks
├── rate_limiter.rs  # rate limiting для внешних API
├── types.rs         # типы данных
├── utils.rs         # утилиты
├── handlers/
│   ├── iss.rs       # /last, /iss/trend
│   ├── osdr.rs      # /osdr/list
│   └── space.rs     # космические данные
├── repo/
│   ├── iss_repo.rs  # репозиторий ISS
│   ├── osdr_repo.rs # репозиторий OSDR
│   └── cache_repo.rs# репозиторий кеша
└── services/
    ├── iss.rs
    ├── osdr.rs
    ├── nasa.rs
    └── space_cache.rs
```

### Repository Layer (repo/)

**IssRepo:**
- `get_last()` — последняя запись ISS
- `get_last_two()` — две последние записи для trend
- `insert()` — вставка новой записи

**OsdrRepo:**
- `list()` — список OSDR items
- `upsert()` — вставка/обновление (ON CONFLICT DO UPDATE)

**CacheRepo:**
- `write()` — запись в кеш
- `get_latest()` — последняя запись из кеша

### Custom ApiError

```rust
enum ApiError {
    Internal(String),
    NotFound(String),
    BadRequest(String),
}
```
- Реализует `IntoResponse` для Axum
- Автоматическая конвертация из `sqlx::Error` и `anyhow::Error`

### PostgreSQL Advisory Locks

- Защита от одновременного выполнения фоновых задач
- Уникальные lock_id для каждой задачи (1001-1006)
- `pg_try_advisory_lock` / `pg_advisory_unlock`

### Rate Limiting для внешних API

- `RateLimiter` с настраиваемым requests_per_minute
- NASA API: 30 req/min
- SpaceX API: 30 req/min
- Использует `Arc<Mutex<Instant>>` для thread-safe доступа

---

## 6. Паттерны проектирования (новые)

| Паттерн | Где применён |
|---------|--------------|
| Service Layer | CmsBlockService, IssService |
| Cache-Aside | Redis кеширование в сервисах |
| Rate Limiting | RateLimitMiddleware (PHP), RateLimiter (Rust) |
| Validator Pattern | Отдельные классы валидации |
| Dependency Injection | Контроллеры получают сервисы через DI |
| Modular Architecture | Rust: разделение на handlers/services/types/repo |
| Repository Pattern | repo/ модуль в Rust |
| Custom Error Type | ApiError в Rust |
| Advisory Lock | pg_try_advisory_lock для фоновых задач |
| Upsert Pattern | ON CONFLICT DO UPDATE в OsdrRepo |
| Token Bucket | RateLimiter для внешних API |

---

## 7. Безопасность

### XSS Protection
- Санитизация HTML в CmsBlockService
- Удаление: script, style, iframe, object, embed, form
- Удаление: on* атрибуты, javascript: в href/src

### Rate Limiting
- PHP: защита от DDoS/брутфорса (60/120 req/min)
- Rust: защита от бана внешними API (30 req/min)

### Input Validation
- Валидация всех входных параметров
- Возврат 422 с описанием ошибок

### Secrets Management
- Все секреты в .env файле
- docker-compose.yml использует ${VARIABLE}

---

## 8. Маршруты

### Страницы (60 req/min)

| Путь | Контроллер |
|------|------------|
| / | redirect → /dashboard |
| /dashboard | DashboardController@index |
| /astronomy | AstronomyController@index |
| /iss | IssController@index |
| /osdr | OsdrController@index |
| /telemetry | TelemetryController@index |
| /telemetry/export | TelemetryController@export |
| /cms | CmsController@index |
| /page/{slug} | CmsController@page |

### API (120 req/min)

| Путь | Контроллер |
|------|------------|
| /api/iss/last | ProxyController@last |
| /api/iss/trend | ProxyController@trend |
| /api/jwst/feed | DashboardController@jwstFeed |
| /api/astro/events | AstroController@events |

### Rust API

| Путь | Handler |
|------|---------|
| /health | health check |
| /last | last_iss |
| /fetch | trigger_iss |
| /iss/trend | iss_trend |
| /osdr/sync | osdr_sync |
| /osdr/list | osdr_list |
| /space/:src/latest | space_latest |
| /space/refresh | space_refresh |
| /space/summary | space_summary |

---

## 9. Зависимости

### PHP Extensions
- pdo_pgsql
- zip
- redis

### Frontend Libraries
- Bootstrap 5.3.3
- Leaflet 1.9.4
- Chart.js

### Docker Services
- PostgreSQL 16
- Redis 7
- Nginx 1.27
- PHP 8.3-fpm
- Python 3.11 (legacy)
- Rust (ISS service)

### Rust Crates
- axum
- sqlx
- tokio
- reqwest
- serde/serde_json
- chrono
- anyhow
- tracing

---

## 10. Переменные окружения

### Database
- `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`
- `DATABASE_URL`

### API Keys
- `NASA_API_KEY`
- `JWST_API_KEY`, `JWST_EMAIL`, `JWST_PROGRAM_ID`
- `ASTRO_APP_ID`, `ASTRO_APP_SECRET`

### Intervals (seconds)
- `FETCH_EVERY_SECONDS` — OSDR (600)
- `ISS_EVERY_SECONDS` — ISS (120)
- `APOD_EVERY_SECONDS` — APOD (43200)
- `NEO_EVERY_SECONDS` — NEO (7200)
- `DONKI_EVERY_SECONDS` — DONKI (3600)
- `SPACEX_EVERY_SECONDS` — SpaceX (3600)
- `PAS_LEGACY_PERIOD` — Legacy (300)

### Other
- `RATE_LIMIT_PER_MINUTE` — PHP rate limit (60)
- `WHERE_ISS_URL` — ISS API URL
