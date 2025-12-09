use chrono::{DateTime, Utc};
use serde_json::Value;
use sqlx::{PgPool, Row};

pub async fn write(pool: &PgPool, source: &str, payload: Value) -> Result<(), sqlx::Error> {
    sqlx::query("INSERT INTO space_cache(source, payload) VALUES ($1, $2)")
        .bind(source).bind(payload).execute(pool).await?;
    Ok(())
}

pub async fn get_latest(pool: &PgPool, source: &str) -> Option<(DateTime<Utc>, Value)> {
    sqlx::query("SELECT fetched_at, payload FROM space_cache WHERE source=$1 ORDER BY id DESC LIMIT 1")
        .bind(source)
        .fetch_optional(pool).await.ok().flatten()
        .map(|r| (r.get("fetched_at"), r.get("payload")))
}
