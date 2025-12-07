-- Basic schema

CREATE TABLE IF NOT EXISTS iss_fetch_log (
    id BIGSERIAL PRIMARY KEY,
    fetched_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    source_url TEXT NOT NULL,
    payload JSONB NOT NULL
);

CREATE TABLE IF NOT EXISTS telemetry_legacy (
    id BIGSERIAL PRIMARY KEY,
    recorded_at TIMESTAMPTZ NOT NULL,
    voltage NUMERIC(6,2) NOT NULL,
    temp NUMERIC(6,2) NOT NULL,
    source_file TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS cms_blocks (
    id BIGSERIAL PRIMARY KEY,
    slug TEXT UNIQUE NOT NULL,
    title TEXT,
    content TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

INSERT INTO cms_blocks(slug, title, content)
VALUES
('dashboard_welcome', 'Добро пожаловать', '<h3>Демо контент</h3><p>Этот текст хранится в БД</p>'),
('dashboard_unsafe', 'Небезопасный пример', '<script>console.log("XSS training")</script><p>Если вы видите всплывашку значит защита не работает</p>')
ON CONFLICT DO NOTHING;