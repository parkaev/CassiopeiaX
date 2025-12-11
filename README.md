# Отчет по проделанной работе

Проект CassiopeiaX — распределённый монолит для работы с космическими данными (ISS, NASA OSDR, JWST, Astronomy API). Далее описаны внесенные изменения:

## 1. Инфраструктура (Docker)
- Добавлена база данных Redis для быстрого хранения временных данных
- Данные хранятся в отдельном томе `redisdata`
- Сервис переписан с Pascal на Python
- Все секреты (все данные связанные с API) вынесены из docker-compose.yml в .env
- Создан .env.example как шаблон
- Добавлен healthcheck для Redis (`redis-cli ping`)
- PHP-сервис теперь зависит от Redis (`condition: service_healthy`)
- Настроен `CACHE_DRIVER=redis` для Laravel

### Архитектура сервисов
![architecture](https://github.com/parkaev/CassiopeiaX/blob/master/images_for_report/service_architecture.png)

## 2. База данных
- Была создана новая таблица `cms_blocks` (id, slug, title, content, is_active) — CMS блоки
- Было удалено дублирование для инициализации `iss_fetch_log` — удалена из Rust, но осталась в init.sql
- Добавлены начальные данные для CMS блоков (`dashboard_welcome`, `dashboard_unsafe`) через `ON CONFLICT DO NOTHING`

### Схема таблиц
| Таблица           | Назначение                          |
|-------------------|-------------------------------------|
| iss_fetch_log     | Лог запросов к ISS API              |
| telemetry_legacy  | Телеметрия от Pascal/Python сервиса |
| cms_blocks        | CMS блоки для динамического контента|
| osdr_items        | Данные NASA OSDR                    |

## 3. Backend (PHP/Laravel)
Главная страница `Dashboard` была разбита на несколько страниц — каждая отдельная страница позволяет взаимодействовать с каждым отдельным API. На данных страницах было реализовано следующее:

### CmsBlockService (`app/Services/CmsBlockService.php`)
- Получение контента CMS блоков по slug
- Кеширование на 1 час
- XSS-защита: удаление script, style, iframe, on* атрибутов, javascript: в href/src

```php
private function sanitizeHtml(?string $html): string
{
    // Удаляем script, style, iframe, object, embed теги
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    // Удаляем on* атрибуты (onclick, onerror и т.д.)
    $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    // Удаляем javascript: в href/src
    $html = preg_replace('/\b(href|src)\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '', $html);
    return $html;
}
```

### IssService (`app/Services/IssService.php`)
- Кеширование ответов от Rust ISS сервиса
- `getLast()` — кеш 30 сек
- `getTrend()` — кеш 60 сек
- `getOsdrList()` — кеш 1 час

### RateLimitMiddleware (`app/Http/Middleware/RateLimitMiddleware.php`)
- Ограничение запросов по IP
- Страницы: 60 req/min
- API: 120 req/min
- Добавлены заголовки `X-RateLimit-Limit` и `X-RateLimit-Remaining`

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
- **CmsController** — отображение CMS блоков
- **ProxyController** — проксирование запросов к Rust сервису

### Маршрутизация (`routes/web.php`)
```php
// Панели (с rate-limit 60 req/min)
Route::middleware([RateLimitMiddleware::class . ':60'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/astronomy', [AstronomyController::class, 'index']);
    Route::get('/iss', [IssController::class, 'index']);
    // ...
});

// API (с rate-limit 120 req/min)
Route::middleware([RateLimitMiddleware::class . ':120'])->group(function () {
    Route::get('/api/iss/last', [ProxyController::class, 'last']);
    Route::get('/api/jwst/feed', [DashboardController::class, 'jwstFeed']);
    // ...
});
```

### XLSX экспорт телеметрии
Реализован экспорт в формате Office Open XML без сторонних библиотек:
- Формирование XML структуры workbook
- Создание ZIP архива с помощью `ZipArchive`
- Автоматическое удаление временного файла после отправки

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
    ├── iss.rs       # сбор данных ISS
    ├── osdr.rs      # сбор данных OSDR
    ├── nasa.rs      # APOD, NEO, DONKI, SpaceX
    └── space_cache.rs
```

### ApiError (`src/error.rs`)
Кастомный тип ошибок с автоматической конвертацией в HTTP ответы:
```rust
pub enum ApiError {
    Internal(String),
    NotFound(String),
    BadRequest(String),
}

impl From<sqlx::Error> for ApiError { ... }
impl From<anyhow::Error> for ApiError { ... }
```

### Advisory Locks (`src/lock.rs`)
Предотвращение параллельного выполнения фоновых задач:
```rust
const LOCK_ISS: i64 = 1001;
const LOCK_OSDR: i64 = 1002;
const LOCK_APOD: i64 = 1003;
// ...

pub async fn try_advisory_lock(pool: &PgPool, lock_id: i64) -> bool {
    sqlx::query_scalar::<_, bool>("SELECT pg_try_advisory_lock($1)")
        .bind(lock_id)
        .fetch_one(pool)
        .await
        .unwrap_or(false)
}
```

### Rate Limiter (`src/rate_limiter.rs`)
Token Bucket алгоритм для ограничения запросов к внешним API:
```rust
pub struct RateLimiter {
    last_request: Arc<Mutex<Instant>>,
    min_interval: Duration,
}

impl RateLimiter {
    pub fn new(requests_per_minute: u32) -> Self { ... }
    pub async fn wait(&self) { ... }
}
```

### Repository Pattern (`src/repo/`)
SQL запросы вынесены в отдельные модули:
- **IssRepo** — работа с `iss_fetch_log`
- **OsdrRepo** — работа с `osdr_items` (включая upsert)
- **CacheRepo** — работа с кешем космических данных

### Фоновые задачи
Все фоновые задачи используют advisory locks:
```rust
tokio::spawn(async move {
    let lid = lock_id("osdr");
    loop {
        if try_advisory_lock(&st.pool, lid).await {
            if let Err(e) = fetch_and_store_osdr(&st).await { error!("osdr err {e:?}") }
            release_advisory_lock(&st.pool, lid).await;
        } else {
            warn!("osdr lock held, skipping");
        }
        tokio::time::sleep(Duration::from_secs(st.every_osdr)).await;
    }
});
```

### API Endpoints
| Endpoint            | Описание                    |
|---------------------|-----------------------------|
| GET /health         | Проверка состояния сервиса  |
| GET /last           | Последние данные ISS        |
| GET /fetch          | Принудительный запрос ISS   |
| GET /iss/trend      | Тренд движения ISS          |
| GET /osdr/sync      | Синхронизация OSDR          |
| GET /osdr/list      | Список OSDR данных          |
| GET /space/:src/latest | Последние космические данные |
| GET /space/refresh  | Обновление космических данных|
| GET /space/summary  | Сводка по всем источникам   |

## 5. Frontend

### Новые страницы
- `/astronomy` — Astronomy API с формой параметров

![astronomy](https://github.com/parkaev/CassiopeiaX/blob/main/images_for_report/astronomy.png)

- `/telemetry` — таблица телеметрии + экспорт XLSX

![telemetry](https://github.com/parkaev/CassiopeiaX/blob/main/images_for_report/telemetry.png)

- `/cms` — отображение CMS блоков

![cms](https://github.com/parkaev/CassiopeiaX/blob/main/images_for_report/cms.png)

### Dashboard
- Real-time отслеживание МКС (обновление каждые 15 сек)
- JWST Viewer с expand/collapse анимацией
- Графики скорости и высоты МКС (Chart.js)
- Карта с траекторией движения
- Галерея JWST изображений с фильтрацией по инструменту/программе

![dashboard](https://github.com/parkaev/CassiopeiaX/blob/main/images_for_report/dashboard.png)

![jvst](https://github.com/parkaev/CassiopeiaX/blob/main/images_for_report/jvst.png)

### OSDR
- Поиск по названию (фильтрация в реальном времени)
- Сортировка по колонкам (клик по заголовку)

![osdr](https://github.com/parkaev/CassiopeiaX/blob/main/images_for_report/osdr.png)

### ISS
- Кнопка "Обновить" с loading state (spinner)
- Асинхронное обновление данных через fetch API
- Отображение последнего снимка и тренда движения

![iss](https://github.com/parkaev/CassiopeiaX/blob/main/images_for_report/iss.png)

### Layout
- Sticky navbar (фиксируется при прокрутке)
- Mobile burger menu (navbar-toggler)
- Активная вкладка выделяется жирным шрифтом
- CSS анимации: fadeInDown, pulse, dots

```css
@keyframes fadeInDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.spinner-dots::after {
  content: '';
  animation: dots 1.5s steps(4, end) infinite;
}
```

## 6. Pascal → Python миграция

Сервис `pascal_legacy` был переписан с Pascal на Python:

Преимущества:
- Упрощённая поддержка и отладка
- Нативная работа с PostgreSQL через psql
- Меньший размер Docker образа

## 7. Паттерны проектирования (новые)

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
| Service Layer        | CmsBlockService, IssService в PHP                |
| Proxy Pattern        | ProxyController для запросов к Rust              |
