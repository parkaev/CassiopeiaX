# Отчет по проделанной работе

Проект CassiopeiaX — распределённый монолит для работы с космическими данными (ISS, NASA OSDR, JWST, Astronomy API). Далее описаны внесенные изменения: 
## 1. Инфраструктура (Docker)
- Добавлена база данных Redis для быстрого хранения временных данных.
- Данные хранятся в отдельном томе `redisdata`.
- Сервис переписан с Pascal на Python
- Все секреты (все данные связанные с API) вынесены из docker-compose.yml в .env
- Создан .env.example как шаблон
## 2. База данных
- Была создана новая таблица `cms_blocks` (id, slug, title, content, is_active) — CMS блоки
- Была удалена таблица `cms_pages` — заменена на cms_blocks, поскольку функционал с выводом целых страниц не требовалось реализовать.
- Было удалено дублирование для инициализации `iss_fetch_log` — удалена из Rust, но осталась в init.sql.
## 3. Backend (PHP/Laravel)
Главная страница `Dashboard` была разбита на несколько станиц  — каждая отдельная страница позволяет взаимодействовать с каждым отдельным API. На данных страницах было реализовано следующее:
**CmsBlockService** (`app/Services/CmsBlockService.php`)
- Получение контента CMS блоков по slug
- Кеширование на 1 час
- XSS-защита: удаление script, style, iframe, on* атрибутов, javascript: в href/src
**IssService** (`app/Services/IssService.php`)
- Кеширование ответов от Rust ISS сервиса
- getLast() — кеш 30 сек
- getTrend() — кеш 60 сек
- getOsdrList() — кеш 1 час
Также был реализован RateLimitMiddleware (`app/Http/Middleware/RateLimitMiddleware.php`). Он реализует следующее:
- Ограничение запросов по IP
- Страницы: 60 req/min
- API: 120 req/min
### Валидаторы

| Валидатор          | Параметры                                          |
|--------------------|----------------------------------------------------|
| AstroValidator     | lat, lon, days, elevation, time                    |
| JwstFeedValidator  | source, suffix, program, instrument, page, perPage |
| OsdrValidator      | limit (1-100)                                      |
| TelemetryValidator | limit (1-1000)                                     |

### Контроллеры
Для обеспечения работоспособности были добавлены новые контроллеры:
- **AstronomyController** — страница Astronomy API
- **TelemetryController** — страница телеметрии + XLSX экспорт
## 4. Backend (Rust)

Был произведен рефакторинг структуры для `main.rs`:

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

## 5. Frontend
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
- Сортировка по колонкам
### ISS
- Кнопка "Обновить" с loading state
- Асинхронное обновление данных
### Layout
- Sticky navbar
- Mobile burger menu (navbar-toggler)
- CSS анимации: fadeInDown, pulse, dots
## 6. Паттерны проектирования (новые)

| Паттерн              | Где применён                                     |
| -------------------- | ------------------------------------------------ |
| Cache-Aside          | Redis кеширование в сервисах                     |
| Rate Limiting        | RateLimitMiddleware (PHP), RateLimiter (Rust)    |
| Validator Pattern    | Отдельные классы валидации                       |
| Dependency Injection | Контроллеры получают сервисы через DI            |
| Modular Architecture | Rust: разделение на handlers/services/types/repo |
| Repository Pattern   | repo/ модуль в Rust                              |
| Custom Error Type    | ApiError в Rust                                  |
| Advisory Lock        | pg_try_advisory_lock для фоновых задач           |
| Upsert Pattern       | ON CONFLICT DO UPDATE в OsdrRepo                 |
| Token Bucket         | RateLimiter для внешних API                      |

