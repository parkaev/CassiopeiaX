# Документация изменений проекта CassiopeiaX

## Обзор

Проект CassiopeiaX — распределённый монолит для работы с космическими данными (ISS, NASA OSDR, JWST, Astronomy API). Документ описывает все изменения, внесённые после initial commit (315a459).
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
## 2. База данных
### Новые таблицы
- `cms_blocks` (id, slug, title, content, is_active) — CMS блоки
### Удалённые таблицы
- `cms_pages` — заменена на cms_blocks
- `iss_fetch_log` — удалена инициализация из Rust сервиса
## 3. Backend (PHP/Laravel)
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
| ------------------ | -------------------------------------------------- |
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
## 4. Frontend (Blade Views)
### Новые страницы
- `/astronomy` — Astronomy API с формой параметров
- `/telemetry` — таблица телеметрии + экспорт XLSX
- `/cms` — отображение CMS блоков
### Dashboard
- Real-time отслеживание МКС (обновление каждые 15 сек)
- JWST Viewer с expand/collapse анимацией
- Графики скорости и высоты МКС (Chart.js)
- Карта с траекторией движения (Leaflet)
### OSDR
- Поиск по названию
- Сортировка по колонкам (клик по заголовку)
- Раскрытие JSON для каждой записи
### ISS
- Кнопка "Обновить" с loading state
- Асинхронное обновление данных
### Layout
- Sticky navbar с shadow
- Mobile burger menu (navbar-toggler)
- Подсветка активного пункта меню (fw-bold)
- CSS анимации: fadeInDown, pulse, dots
## 5. Backend (Rust)
### Рефакторинг структуры
Монолитный `main.rs` разбит на модули:

```
src/
├── lib.rs          # экспорт модулей
├── config.rs       # конфигурация, AppState
├── database.rs     # инициализация БД
├── types.rs        # типы данных
├── utils.rs        # утилиты
├── handlers/
│   ├── iss.rs      # /last, /iss/trend
│   ├── osdr.rs     # /osdr/list
│   └── space.rs    # космические данные
└── services/
    ├── iss.rs
    ├── osdr.rs
    ├── nasa.rs
    └── space_cache.rs
```
## 6. Паттерны проектирования (новые)

| Паттерн | Где применён |
|---------|--------------|
| Service Layer | CmsBlockService, IssService |
| Cache-Aside | Redis кеширование в сервисах |
| Rate Limiting | RateLimitMiddleware |
| Validator Pattern | Отдельные классы валидации |
| Dependency Injection | Контроллеры получают сервисы через DI |
| Modular Architecture | Rust: разделение на handlers/services/types |
| Repository Pattern | database.rs в Rust |
## 7. Безопасность
### XSS Protection
- Санитизация HTML в CmsBlockService
- Удаление: script, style, iframe, object, embed, form
- Удаление: on* атрибуты, javascript: в href/src
### Rate Limiting
- Защита от DDoS/брутфорса
- Разные лимиты для страниц и API
### Input Validation
- Валидация всех входных параметров
- Возврат 422 с описанием ошибок
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
